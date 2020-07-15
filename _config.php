<?php
$config = [
    // YOU MUST EDIT THESE
    'domain'    => 'canvas.instructure.com',
    'token'     => '...',
    
    // OPTIONALLY EDIT THESE
    'create_assignment_attributes' => 
                    [
                        'name',
                        'description',
                        'due_at',
                        'lock_at',
                        'unlock_at',
                        'points_possible',
                        'grading_type',
                        'peer_reviews',
                        'automatic_peer_reviews',
                        'grade_group_students_individually',
                        'anonymous_peer_reviews',
                        'moderated_grading',
                        'omit_from_final_grade',
                        'intra_group_peer_reviews',
                        'anonymous_instructor_annotations',
                        'anonymous_grading',
                        'graders_anonymous_to_graders',
                        'grader_count',
                        'grader_comments_visible_to_graders',
                        'grader_names_visible_to_final_grader',
                        'allowed_attempts',
                        'submission_types',
                        'allowed_extensions',
                        'turnitin_enabled',
                        'vericite_enabled',
                        'turnitin_settings',
                        'external_tool_tag_attributes',
                    ],
    'create_quiz_attributes' =>
                    [
                        "title",
                        "description",
                        "quiz_type",
                        "time_limit",
                        "shuffle_answers",
                        "show_correct_answers",
                        "scoring_policy",
                        "allowed_attempts",
                        "one_question_at_a_time",
                        "points_possible",
                        "cant_go_back",
                        "access_code",
                        "ip_filter",
                        "due_at",
                        "lock_at",
                        "unlock_at",
                        "hide_results",
                        "show_correct_answers_at",
                        "hide_correct_answers_at",
                        "one_time_results",
                        "show_correct_answers_last_attempt",
                    ],
    'create_page_attributes' =>
                    [
                        "title",
                        "url",
                        "editing_roles",
                        "published",
                        "hide_from_students",
                        "todo_date",
                        "body",
                    ],
    'create_module_attributes' =>
                    [
                        "name",
                        "position",
                        "unlock_at",
                        "require_sequential_progress",
                        "publish_final_grade",
                        "prerequisite_module_ids",
                        "published",
                        "items", // further filtered
                    ],
    'create_module_item_attributes' =>
                    [
                        "title",
                        "position",
                        "indent",
                        "type",
                        "external_url",
                        "content_id", // modified by acquire verb
                        "new_tab",
                        "page_url",
                        "completion_requirement",
                        "published",
                    ],
    'create_assignment_attributes' =>
                    [
                        "description",
                        "due_at",
                        "unlock_at",
                        "lock_at",
                        "points_possible",
                        "grading_type",
                        "peer_reviews",
                        "automatic_peer_reviews",
                        "position",
                        "grade_group_students_individually",
                        "anonymous_peer_reviews",
                        "moderated_grading",
                        "omit_from_final_grade",
                        "intra_group_peer_reviews",
                        "anonymous_instructor_annotations",
                        "anonymous_grading",
                        "graders_anonymous_to_graders",
                        "grader_count",
                        "grader_comments_visible_to_graders",
                        "grader_names_visible_to_final_grader",
                        "allowed_attempts",
                        "name",
                        "submission_types",
                        "external_tool_tag_attributes",
                        "published",
                        "anonymize_students",
                        "require_lockdown_browser",
                    ],
];
