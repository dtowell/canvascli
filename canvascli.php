<?php
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function(int $n,string $msg,string $file,int $line){
    if (error_reporting() !== 0) throw new \ErrorException($msg,0,$n,$file,$line);
},E_ALL);

require '_config.php';

class Cache {
    private $map = [];
    private $request;
    private $url;
    private $name;
    private $id;

    public function __construct(Request $request,string $url,string $name='name',string $id='id') {
        $this->request = $request;
        $this->url = $url;
        $this->name = $name;
        $this->id = $id;
        $items = $this->request->unpage($this->url);
        foreach ($items as $i)
            $this->map[$i->$name] = $i->$id;
    }

    public function get_url():string {
        return $this->url;
    }

    public function acquire_id(array $body,string $label) {
        if (isset($this->map[$body[$this->name]]))
            return $this->map[$body[$this->name]];
        
        $result = $this->request->post($this->url,[$label=>$body]);
        return $map[$body[$this->name]] = $result->{$this->id};
    }
}

class Request {
    private $curl = null;
    private $auth_header;
    private $response_headers = [];

    public function __construct() {
        global $config;

        $this->curl = curl_init();
        $this->auth_header = 'Authorization: Bearer '.$config['token'];
        curl_setopt($this->curl,CURLOPT_HEADER,false);
        curl_setopt($this->curl,CURLOPT_RETURNTRANSFER,true);
        $headers =& $this->response_headers;
        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, function($curl,string $header)use(&$headers):int {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) >= 2)
                $headers[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        });
    }

    public function get(string $url) {
        global $config;

        if (!preg_match("|^https://|",$url))
            $url = 'https://'.$config['domain'].$url;

        $this->response_headers = [];
        curl_setopt($this->curl,CURLOPT_URL,$url);
        curl_setopt($this->curl,CURLOPT_HTTPGET,1);
        curl_setopt($this->curl,CURLOPT_HTTPHEADER,[$this->auth_header]);
        $result = curl_exec($this->curl);
        if ($result === false) 
            return null;
        $result_code = curl_getinfo($this->curl,CURLINFO_HTTP_CODE);
        if ($result_code == 401) // closed courses are "unauthorized" !?!?!?
            return [];
        $json = json_decode($result);
        if ($json === false)
            return null;
        return $json;
    }

    function unpage(string $url,string $member=null):array {
        $json = $this->get($url);
        $items = [];
        while (true) {
            $items = array_merge($items,$member ? $json->$member : $json);
            if (empty($this->response_headers['link'])) break;
            if (!preg_match("/<([^> ]+)>; +rel=\"next\"/",$this->response_headers['link'],$m)) break;
            $json = $this->get($m[1]);
        }
        return $items;
    }

    public function post(string $url,array $body) {
        global $config;

        if (!preg_match("|^https://|",$url))
            $url = 'https://'.$config['domain'].$url;

        $this->response_headers = [];
        curl_setopt($this->curl,CURLOPT_URL,$url);
        curl_setopt($this->curl,CURLOPT_POST,1);
        curl_setopt($this->curl,CURLOPT_POSTFIELDS,json_encode($body));
        curl_setopt($this->curl,CURLOPT_HTTPHEADER,[$this->auth_header,'Content-Type: application/json']);
        $result = curl_exec($this->curl);
        if ($result === false)
            return null;
        $result_code = curl_getinfo($this->curl,CURLINFO_HTTP_CODE);
        if ($result_code!=200 && $result_code!=201) {
            echo $result_code." $result\n";
            echo json_encode($body,JSON_PRETTY_PRINT)."\n";
            return null;
        }
        $json = json_decode($result);
        if ($json === false) {
            echo $result."\n";
            return null;
        }
        return $json;
    }

    public function post_quiz(string $course_id,object $quiz):int {
        $body = filter($quiz,'create_quiz_attributes');
        $quiz_id = $this->acquire_quiz_id($course_id,$body);
        foreach ($quiz->questions as $x) {
            unset($x->id);
            unset($x->quiz_id);
            unset($x->quiz_group_id);
            unset($x->assessment_question_id);
            $j = $this->post("/api/v1/courses/$course_id/quizzes/$quiz_id/questions",['question'=>$x]);
        }
        return $quiz_id;
    }

    private $module_cache = null;
    public function acquire_module_id(string $course_id,array $body):int {
        $url = "/api/v1/courses/$course_id/modules";
        if ($this->module_cache === null ||  $this->module_item_cache->get_url()!=$url)
            $this->module_cache = new Cache($this,$url);

        return $this->module_cache->acquire_id($body,'module');
    }

    private $module_item_cache = null;
    public function acquire_module_item_id(string $course_id,int $module_id,array $body):int {
        $url = "/api/v1/courses/$course_id/modules/$module_id/items";
        if ($this->module_item_cache === null || $this->module_item_cache->get_url()!=$url)
            $this->module_item_cache = new Cache($this,$url,'title');

        return $this->module_item_cache->acquire_id($body,'module_item');
    }

    private $quiz_cache = null;
    public function acquire_quiz_id(string $course_id,array $body):int {
        $url = "/api/v1/courses/$course_id/quizzes";
        if ($this->quiz_cache === null ||  $this->module_item_cache->get_url()!=$url)
            $this->quiz_cache = new Cache($this,$url,'title');

        return $this->quiz_cache->acquire_id($body,'quiz');
    }

    private $page_cache = null;
    public function acquire_page_id(string $course_id,array $body):string {
        $url = "/api/v1/courses/$course_id/pages";
        if ($this->page_cache === null ||  $this->module_item_cache->get_url()!=$url)
            $this->page_cache = new Cache($this,$url,'title','url');

        return $this->page_cache->acquire_id($body,'wiki_page');
    }

    private $assignment_cache = null;
    public function acquire_assignment_id(string $course_id,array $body):int {
        $url = "/api/v1/courses/$course_id/assignments";
        if ($this->assignment_cache === null ||  $this->module_item_cache->get_url()!=$url)
            $this->assignment_cache = new Cache($this,$url);

        return $this->assignment_cache->acquire_id($body,'assignment');
    }

}

function filter(object $arr,string $cfg):array {
    global $config;
    $new = [];
    foreach ($arr as $k=>$v)
        if (in_array($k,$config[$cfg]))
            $new[$k] = $v;
    return $new;
}

if (count($argv) < 2) {
    echo "usage: php canvascli.php ...\n";
    echo "    list|get courses\n";
    //echo "    list|get terms\n";
    echo "    list|get assignments <COURSE-ID>\n";
    echo "    create assignments <COURSE-ID> <JSON-FILE>\n";
    echo "    list|get modules <COURSE-ID>\n";
    echo "    acquire modules <COURSE-ID> [<MODULE-ID>]\n";
    echo "    create modules <COURSE-ID> <JSON-FILE>\n";
    echo "    list|get pages <COURSE-ID>\n";
    echo "    create pages <COURSE-ID> <JSON-FILE>\n";
    // list|get|create files|folders ???
    echo "    list|get quizzes <COURSE-ID>\n";
    echo "    create quizzes <COURSE-ID> <JSON-FILE>\n";
    exit;
}

$request = new Request();

if ($argv[2] == 'courses') {
    $courses = $request->unpage('/api/v1/courses');
    if ($argv[1] == 'get') {
        foreach ($courses as $c)
            $c->settings = $request->get("/api/v1/courses/$c->id/settings");
        echo json_encode($courses,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'list') {
        usort($courses,fn($a,$b)=>[$b->start_at,$a->name] <=> [$a->start_at,$b->name]);
        foreach ($courses as $c) {
            echo $c->id.": ".$c->name." ".$c->course_code." ($c->enrollment_term_id)\n";
        }
    }
    else 
        echo "unknown verb $argv[1]\n";
}
else if ($argv[2] == 'terms') {
    $terms = $request->unpage('/api/v1/accounts/1/terms','enrollment_terms');
    if ($argv[1] == 'get')
        echo json_encode($terms,JSON_PRETTY_PRINT)."\n";
    else if ($argv[1] == 'list') {
        usort($terms,fn($a,$b)=>$b->start_at <=> $a->start_at);
        foreach ($terms as $t) {
            echo $t->id.": ".$t->name."\n";
        }
    }
    else 
        echo "unknown verb $argv[1]\n";
}
else if ($argv[2] == 'assignments') {
    if (empty($argv[3]) || !ctype_digit($argv[3])) {
        echo "COURSE-ID is required\n";
        exit;
    }
    if ($argv[1] == 'get') {
        $assignments = $request->unpage("/api/v1/courses/$argv[3]/assignments");
        echo json_encode($assignments,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'list') {
        $assignments = $request->unpage("/api/v1/courses/$argv[3]/assignments");
        usort($assignments,fn($a,$b)=>$b->due_at <=> $a->due_at);
        foreach ($assignments as $a) {
            echo $a->id.": ".$a->name." ($a->points_possible points)\n";
        }
    }
    else if ($argv[1] == 'create') {
        if (empty($argv[4]) || !is_file($argv[4])) {
            echo "JSON-FILE is required\n";
            exit;
        }
        $assignments = file_get_contents($argv[4]);
        $assignments = json_decode($assignments);
        foreach ($assignments as $a) {
            $body = filter($a,'create_assignment_attributes');
            $json = $request->post("/api/v1/courses/$argv[3]/assignments",['assignment'=>$body]);
        }
    }
    else 
        echo "unknown verb $argv[1]\n";
}
else if ($argv[2] == 'modules') {
    if (empty($argv[3]) || !ctype_digit($argv[3])) {
        echo "COURSE-ID is required\n";
        exit;
    }
    if ($argv[1] == 'get') {
        $modules = $request->unpage("/api/v1/courses/$argv[3]/modules");
        foreach ($modules as $m)
            $m->items = $request->unpage($m->items_url);
        echo json_encode($modules,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'acquire') {
        $modules = $request->unpage("/api/v1/courses/$argv[3]/modules");
        foreach ($modules as $m) {
            $m->items = $request->unpage($m->items_url);
            foreach ($m->items as $i) {
                if ($i->type == 'File')
                    $i->file = $request->get("/api/v1/courses/$argv[3]/files/$i->content_id");
                else if ($i->type == 'Page')
                    $i->page = $request->get("/api/v1/courses/$argv[3]/pages/$i->page_url");
                else if ($i->type == 'Assignment')
                    $i->assignment = $request->get("/api/v1/courses/$argv[3]/assignments/$i->content_id");
                else if ($i->type == 'Quiz') {
                    $i->quiz = $request->get("/api/v1/courses/$argv[3]/quizzes/$i->content_id");
                    $i->quiz->questions = $request->unpage("/api/v1/courses/$argv[3]/quizzes/$i->content_id/questions");
                }
            }
        }
        echo json_encode($modules,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'list') {
        $modules = $request->unpage("/api/v1/courses/$argv[3]/modules");
        usort($modules,fn($a,$b)=>$a->position <=> $a->position);
        foreach ($modules as $a) {
            echo $a->id.": ".$a->name." ($a->items_count items)\n";
        }
    }
    else if ($argv[1] == 'create') {
        if (empty($argv[4]) || !is_file($argv[4])) {
            echo "JSON-FILE is required\n";
            exit;
        }
        $modules = file_get_contents($argv[4]);
        $modules = json_decode($modules);
        foreach ($modules as $m) {
            $body = filter($m,'create_module_attributes');
            $items = $body['items'];
            unset($body['items']);
            $module_id = $request->acquire_module_id($argv[3],$body);
            foreach ($items as $i) {

                if ($i->type == 'Quiz') {
                    $i->content_id = $request->post_quiz($argv[3],$i->quiz);
                }
                else if ($i->type == 'Page') {
                    $body = filter($i->page,'create_page_attributes');
                    $request->acquire_page_id($argv[3],$body);
                }
                else if ($i->type == 'Assignment') {
                    $body = filter($i->assignment,'create_assignment_attributes');
                    if (isset($body['external_tool_tag_attributes'])) {
                        unset($body['external_tool_tag_attributes']->resource_link_id);
                        unset($body['external_tool_tag_attributes']->external_data);
                        unset($body['external_tool_tag_attributes']->content_type);
                        unset($body['external_tool_tag_attributes']->content_id);
                    }
                    $i->content_id = $request->acquire_assignment_id($argv[3],$body);
                }

                $item = filter($i,'create_module_item_attributes');
                $request->acquire_module_item_id($argv[3],$module_id,$item);
            }
        }
    }
    else 
        echo "unknown verb $argv[1]\n";
}
else if ($argv[2] == 'pages') {
    if (empty($argv[3]) || !ctype_digit($argv[3])) {
        echo "COURSE-ID is required\n";
        exit;
    }
    if ($argv[1] == 'get') {
        $pages = $request->unpage("/api/v1/courses/$argv[3]/pages");
        foreach ($pages as $p)
            $p->body = $request->get("/api/v1/courses/$argv[3]/pages/$p->url")->body;
        echo json_encode($pages,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'list') {
        $pages = $request->unpage("/api/v1/courses/$argv[3]/pages");
        usort($pages,fn($a,$b)=>$a->title <=> $b->title);
        foreach ($pages as $a) {
            echo $a->url.": ".$a->title."\n";
        }
    }
    else if ($argv[1] == 'create') {
        if (empty($argv[4]) || !is_file($argv[4])) {
            echo "JSON-FILE is required\n";
            exit;
        }
        $pages = file_get_contents($argv[4]);
        $pages = json_decode($pages);
        foreach ($pages as $p) {
            $body = filter($p,'create_page_attributes');
            $json = $request->post("/api/v1/courses/$argv[3]/pages",['wiki_page'=>$body]);
        }
    }
    else 
        echo "unknown verb $argv[1]\n";
}
else if ($argv[2] == 'quizzes') {
    if (empty($argv[3]) || !ctype_digit($argv[3])) {
        echo "COURSE-ID is required\n";
        exit;
    }
    if ($argv[1] == 'get') {
        $quizzes = $request->unpage("/api/v1/courses/$argv[3]/quizzes");
        foreach ($quizzes as $q)
            $q->questions = $request->unpage("/api/v1/courses/$argv[3]/quizzes/$q->id/questions");
        echo json_encode($quizzes,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'list') {
        $quizzes = $request->unpage("/api/v1/courses/$argv[3]/quizzes");
        usort($quizzes,fn($a,$b)=>$b->due_at <=> $a->due_at);
        foreach ($quizzes as $a) {
            echo $a->id.": ".$a->title." ($a->points_possible points)\n";
        }
    }
    else if ($argv[1] == 'create') {
        if (empty($argv[4]) || !is_file($argv[4])) {
            echo "JSON-FILE is required\n";
            exit;
        }
        $quizzes = file_get_contents($argv[4]);
        $quizzes = json_decode($quizzes);
        foreach ($quizzes as $q)
            $request->post_quiz($argv[3],$q);
    }
    else 
        echo "unknown verb $argv[1]\n";
}
else
    echo "unknown verb $argv[2]\n";
