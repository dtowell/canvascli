<?php
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function(int $n,string $msg,string $file,int $line){
    if (error_reporting() !== 0) throw new \ErrorException($msg,0,$n,$file,$line);
},E_ALL);

require '_config.php';

class Request {
    private $curl = null;

    public function __construct() {
        $this->curl = curl_init();
        //curl_setopt($this->curl,CURLOPT_HEADER,false);
        curl_setopt($this->curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($this->curl,CURLOPT_URL,'https://teacher.socrative.com/v3/login/email');
        curl_setopt($this->curl,CURLOPT_POST,1);
        curl_setopt($this->curl,CURLOPT_POSTFIELDS,json_encode(['email'=>'dwayne.towell@lipscomb.edu','password'=>'87qco!QAZt']));
        curl_setopt($this->curl,CURLOPT_HTTPHEADER,['Content-Type: application/json']);
        curl_setopt($this->curl,CURLOPT_COOKIEFILE,"");
        $result = curl_exec($this->curl);
        if ($result === false)
            exit(1);
        $result_code = curl_getinfo($this->curl,CURLINFO_HTTP_CODE);
        if ($result_code!=200) {
            echo $result_code." $result\n";
            //echo json_encode($body,JSON_PRETTY_PRINT)."\n";
            exit(1);
        }
        $json = json_decode($result);
        if ($json === false) {
            echo $result."\n";
            exit(1);
        }
    }

    public function get(string $url) {
        curl_setopt($this->curl,CURLOPT_URL,$url);
        curl_setopt($this->curl,CURLOPT_HTTPGET,1);
        //curl_setopt($this->curl,CURLOPT_HTTPHEADER,[$this->auth_header]);
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
/*
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

*/
}

if (count($argv) < 2) {
    echo "usage: php socrativecli.php ...\n";
    echo "    list|get|summarize reports\n";
    //echo "    create quizzes <COURSE-ID> <JSON-FILE>\n";
    exit;
}

$request = new Request();

if ($argv[2] == 'reports') {
    $result = $request->get("https://api.socrative.com/activities/api/reports/?state=active&offset=0&limit=50");
    if ($argv[1] != 'list')
        foreach ($result->reports as $r) {
            $r->report = $request->get("https://api.socrative.com/activities/api/report/$r->id");
            $r->quiz = $request->get("https://teacher.socrative.com/quizzes/$r->activity_id");
        }

    if ($argv[1] == 'get') {
        echo json_encode($result->reports,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'summarize') {
        $participants = [];
        foreach ($result->reports as $r)
            foreach ($r->report->student_names as $n) 
                if (substr($n->name,0,5) != 'Anon ')
                    $participants[$n->user_uuid] = $n->name;
        
        $participation = [];
        foreach ($result->reports as $r)
            foreach ($r->report->responses as $a)
                if (isset($participants[$a->user_uuid]))
                    @$participation[$participants[$a->user_uuid]]++;
        
        echo json_encode($participation,JSON_PRETTY_PRINT)."\n";
    }
    else if ($argv[1] == 'list') {
        usort($result->reports,fn($a,$b)=>$b->start_time <=> $a->start_time);
        foreach ($result->reports as $r) {
            echo $r->id.": ".date("Y-m-d H:i:s",$r->start_time)." ".$r->name."  ($r->activity_type)\n";
        }
    }
    else 
        echo "unknown verb $argv[1]\n";
}
else
    echo "unknown verb $argv[2]\n";
