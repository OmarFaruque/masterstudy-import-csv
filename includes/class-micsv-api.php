<?php

if (!defined('ABSPATH')) {
    exit;
}

class MICSV_Api
{


    /**
     * @var    object
     * @access  private
     * @since    1.0.0
     */
    private static $instance = null;

    /**
     * The version number.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $version;
    /**
     * The token.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public string $token;

    public function __construct()
    {
        $this->token = MICSV_TOKEN;

        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    $this->token . '/v1',
                    '/config/',
                    array(
                        'methods' => 'GET',
                        'callback' => array($this, 'getConfig'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );


                // CSV save route
                register_rest_route(
                    $this->token . '/v1',
                    '/save/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'saveCSVCallback'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );


            }
        );
    }


    public function create_post($title, $type, $content=''){
        $existing_post = get_page_by_title($title, OBJECT, $type); 
        $postid = isset($existing_post->ID) ? $existing_post->ID : '';
        if(!$existing_post){
            $postArray =  array(
                'post_title' => wp_slash($title), 
                'post_status'   => 'publish',
                'post_type' => $type
            );

            if(!empty($content)) $postArray['post_content'] = $content;
            
            $postid = wp_insert_post($postArray);
        }

        return $postid;
    }


    public function Generate_Featured_Image( $image_url, $post_id, $question = false  ){
        
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        if(wp_mkdir_p($upload_dir['path']))
          $file = $upload_dir['path'] . '/' . $filename;
        else
          $file = $upload_dir['basedir'] . '/' . $filename;
        file_put_contents($file, $image_data);
    
        $wp_filetype = wp_check_filetype($filename, null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        $res1= wp_update_attachment_metadata( $attach_id, $attach_data );

        if(!$question){
            $res2= set_post_thumbnail( $post_id, $attach_id );
        }else{
            $res2 = update_post_meta( $post_id, 'image', $attach_id );
        }

    }


    public function saveCSVCallback($data){
        switch($data['type']){
            case "single_choice_question":
                $this->process_single_choice_question($data['data']);
            break;
            case "item_match_question": 
                $this->process_item_match_question($data['data']);
            break;
            case "multi_choice": 
                $this->process_multi_choice_question($data['data']);
            break;
            case "fill_the_gaps":
                $this->process_fill_the_gap_question($data['data']);
            break;
        }
       

        $config = ['general' =>
            [
                'data' => $data['data']
            ]
        ];
        return new WP_REST_Response($config, 200);
    }



    /**
     * Process Fill the gap Question
     * @param $data as array
     */
    public function process_fill_the_gap_question($data){
        unset($data[0]);
        foreach($data as $sdata):
                if(!empty($sdata[1])):
                if(isset($sdata[16]) && $sdata[16] != ''){
                    // Course
                    $course_id = $this->create_post($sdata[16], 'stm-courses', '');
                }
                // Quiz
                $quiz_id = $this->create_post($sdata[10], 'stm-quizzes', $sdata[11]);

                // Questions
                $question_id = $this->create_post($sdata[1], 'stm-questions', '');


                // Question Image 
               $imgArray = array();
               if (filter_var($sdata[3], FILTER_VALIDATE_URL)) { 
                   // Check if already upload before 
                  $posts = get_page_by_title( basename($sdata[3]), OBJECT, 'attachment' );
                  if($posts && isset($posts->ID)){
                       $image = wp_get_attachment_image_src($posts->ID, 'img-870-440');
                       $imgArray['id'] = $posts->ID;
                       $imgArray['url'] = $image[0];
                  }else{
                      $this->Generate_Featured_Image($sdata[3], $question_id, true);
                  }
              }else{
                  if(!empty($sdata[3])){
                   $image = wp_get_attachment_image_src($sdata[3], 'img-870-440');
                   $imgArray['id'] = $sdata[3];
                   $imgArray['url'] = $image[0];
                  }
              }

              if(count($imgArray) > 0){
                   update_post_meta( $question_id, 'image',  $imgArray);
              }

                
                if(isset($sdata[16]) && $sdata[16] != ''){

                    // Existing Curriculum  
                    $ex_curriculum = get_post_meta( $course_id, 'curriculum', true );
                    $ex_curriculum = ($ex_curriculum) ? explode(',',$ex_curriculum) : array();

                    // Course Curriculum 
                    $curriculum = count($ex_curriculum) > 0 ? $ex_curriculum : array();

                    if($sdata[17] && !empty($sdata[17])) array_push($curriculum, $sdata[17]);
                    if($sdata[18] && !empty($sdata[18])) array_push($curriculum, $sdata[18]);
                    if($quiz_id) array_push($curriculum, $quiz_id);

                    $curriculum = array_unique($curriculum);
                    $curriculum = implode(',', $curriculum);
                    
                    update_post_meta( $course_id, 'curriculum', $curriculum );
                }

                // Link Question to Quiz 
                $quiz_questions = get_post_meta( $quiz_id, 'questions', true );
                $quiz_questions = $quiz_questions ? explode(',', $quiz_questions) : array();
                array_push($quiz_questions, $question_id);
                update_post_meta( $quiz_id, 'questions', implode(',', $quiz_questions) );

                // Quiz duration_measure
                $duration_measure = ($sdata[15] == 'Minutes') ? '' : strtolower($sdata[15]);
                update_post_meta( $quiz_id, 'duration_measure', $duration_measure );

                // Quiz Duration 
                $duration = (isset($sdata[14]) && $sdata[14] != '') ? $sdata[14] : 0;
                update_post_meta( $quiz_id, 'duration', $duration );

                // Process Quiz Featured image
                if (filter_var($sdata[13], FILTER_VALIDATE_URL)) { 
                    // Check if already upload before 
                    $posts = get_page_by_title( basename($sdata[13]), OBJECT, 'attachment' );
                    if($posts && isset($posts->ID)){
                        set_post_thumbnail( $quiz_id, $posts->ID );    
                    }else{
                        $this->Generate_Featured_Image($sdata[13], $quiz_id);
                    }
                }else{
                    set_post_thumbnail( $quiz_id, $sdata[13] );
                }

                // Quiz Front-end Description 
                update_post_meta( $quiz_id, 'lesson_excerpt', $sdata[12] );

                // Show Currect Answer or not (For yes "on" Empty for no)
                $showCorrectAnswer = strtolower($sdata[6]) == 'yes' ? 'on':'';
                update_post_meta( $quiz_id, 'correct_answer', $showCorrectAnswer );

                // Passing Grade 
                update_post_meta( $quiz_id, 'passing_grade', (int)$sdata[7] );

                // Points total cut after re-take 
                update_post_meta( $quiz_id, 're_take_cut', (int)$sdata[8] );

                // Rendomize Question 
                $rendomize_question = strtolower($sdata[9]) == 'yes' ? 'on':'';
                update_post_meta( $quiz_id, 'random_questions', $rendomize_question );


                // Set Question Type 
                update_post_meta( $question_id, 'type', $sdata[0] );
                
                // Questin Category
                $q_cat = get_term_by( 'name', $sdata[2], 'stm_lms_question_taxonomy' );
                if(!$q_cat){
                    $q_cat = wp_insert_term( $sdata[2], 'stm_lms_question_taxonomy');
                }
                $q_cat_id = $q_cat->term_id;
                wp_set_post_terms( $question_id, array($q_cat_id), 'stm_lms_question_taxonomy' );

                $answers = array();
               
                        
                        
                $newArray = array(
                            'text' => wp_slash( $sdata[4] ), 
                            'isTrue' => '0'
                );
                array_push($answers, $newArray);
                   
                update_post_meta( $question_id, 'answers', $answers );

                if($sdata[5] && $sdata[5] != '') update_post_meta( $question_id, 'question_explanation', $sdata[5] );
            endif;
        endforeach;
    }


    /**
     * Process Multi Choice Question
     * @param $data as array
     */
    public function process_multi_choice_question($data){
        unset($data[0]);
        foreach($data as $sdata):
                if(!empty($sdata[1])):
                if(isset($sdata[25]) && $sdata[25] != ''){
                    // Course
                    $course_id = $this->create_post($sdata[25], 'stm-courses', '');
                }
                
                // Quiz
                $quiz_id = $this->create_post($sdata[19], 'stm-quizzes', $sdata[20]);

                // Questions
                $question_id = $this->create_post($sdata[1], 'stm-questions', '');

                // Question Image 
                $imgArray = array();
                if (filter_var($sdata[3], FILTER_VALIDATE_URL)) { 
                   // Check if already upload before 
                  $posts = get_page_by_title( basename($sdata[3]), OBJECT, 'attachment' );
                  if($posts && isset($posts->ID)){
                       $image = wp_get_attachment_image_src($posts->ID, 'img-870-440');
                       $imgArray['id'] = $posts->ID;
                       $imgArray['url'] = $image[0];
                  }else{
                      $this->Generate_Featured_Image($sdata[3], $question_id, true);
                  }
                }else{
                  if(!empty($sdata[3])){
                   $image = wp_get_attachment_image_src($sdata[3], 'img-870-440');
                   $imgArray['id'] = $sdata[3];
                   $imgArray['url'] = $image[0];
                  }
                }

              if(count($imgArray) > 0){
                   update_post_meta( $question_id, 'image',  $imgArray);
              }



                if(isset($sdata[25]) && $sdata[25] != ''){
                    // Existing Curriculum  
                    $ex_curriculum = get_post_meta( $course_id, 'curriculum', true );
                    $ex_curriculum = ($ex_curriculum) ? explode(',',$ex_curriculum) : array();

                    // Course Curriculum 
                    $curriculum = count($ex_curriculum) > 0 ? $ex_curriculum : array();

                    if($sdata[26] && !empty($sdata[26])) array_push($curriculum, $sdata[26]);
                    if($sdata[27] && !empty($sdata[27])) array_push($curriculum, $sdata[27]);
                    if($quiz_id) array_push($curriculum, $quiz_id);

                    $curriculum = array_unique($curriculum);
                    $curriculum = implode(',', $curriculum);
                    update_post_meta( $course_id, 'curriculum', $curriculum );
                }
                
                // Link Question to Quiz 
                $quiz_questions = get_post_meta( $quiz_id, 'questions', true );
                $quiz_questions = $quiz_questions ? explode(',', $quiz_questions) : array();
                array_push($quiz_questions, $question_id);
                update_post_meta( $quiz_id, 'questions', implode(',', $quiz_questions) );



                // Quiz duration_measure
                $duration_measure = ($sdata[24] == 'Minutes') ? '' : strtolower($sdata[24]);
                update_post_meta( $quiz_id, 'duration_measure', $duration_measure );

                // Quiz Duration 
                $duration = (isset($sdata[23]) && $sdata[23] != '') ? $sdata[23] : 0;
                update_post_meta( $quiz_id, 'duration', $sdata[23] );

                // Process Quiz Featured image
                if (filter_var($sdata[22], FILTER_VALIDATE_URL)) { 
                    // Check if already upload before 
                    $posts = get_page_by_title( basename($sdata[22]), OBJECT, 'attachment' );
                    if($posts && isset($posts->ID)){
                        set_post_thumbnail( $quiz_id, $posts->ID );    
                    }else{
                        $this->Generate_Featured_Image($sdata[22], $quiz_id);
                    }
                }else{
                    set_post_thumbnail( $quiz_id, $sdata[22] );
                }

                // Quiz Front-end Description 
                update_post_meta( $quiz_id, 'lesson_excerpt', $sdata[21] );

                // Show Currect Answer or not (For yes "on" Empty for no)
                $showCorrectAnswer = strtolower($sdata[15]) == 'yes' ? 'on':'';
                update_post_meta( $quiz_id, 'correct_answer', $showCorrectAnswer );

                // Passing Grade 
                update_post_meta( $quiz_id, 'passing_grade', (int)$sdata[16] );

                // Points total cut after re-take 
                update_post_meta( $quiz_id, 're_take_cut', (int)$sdata[17] );

                // Rendomize Question 
                $rendomize_question = strtolower($sdata[18]) == 'yes' ? 'on':'';
                update_post_meta( $quiz_id, 'random_questions', $rendomize_question );


                // Set Question Type 
                update_post_meta( $question_id, 'type', $sdata[0] );
                
                // Questin Category
                $q_cat = get_term_by( 'name', $sdata[2], 'stm_lms_question_taxonomy' );
                if(!$q_cat){
                    $q_cat = wp_insert_term( $sdata[2], 'stm_lms_question_taxonomy');
                }
                $q_cat_id = $q_cat->term_id;
                wp_set_post_terms( $question_id, array($q_cat_id), 'stm_lms_question_taxonomy' );

                $answers = array();
                $order = 1;
                for($i = 4; $i <= 12; $i+=2){
                    if(!empty($sdata[$i])){
                        $correctAnswer = explode(',', $sdata[14]);
                        $explanationIndex = $i + 1;
                        $newArray = array(
                            'text' => wp_slash($sdata[$i]), 
                            'isTrue' => in_array($order, $correctAnswer) ? '1' : '0',
                            'explain' => wp_slash($sdata[$explanationIndex])
                        );
                        array_push($answers, $newArray);
                    }
                    $order += 1;
                }

                update_post_meta( $question_id, 'answers', $answers );
            endif;
        endforeach;
    }


    /**
     * Process Item Match Question
     * @param $data as array
     */
    public function process_item_match_question($data = array()){
        unset($data[0]);
        foreach($data as $sdata):
                if(!empty($sdata[1])){
                if(isset($sdata[29]) && $sdata[29] != ''){
                    // Course
                    $course_id = $this->create_post($sdata[29], 'stm-courses', '');
                }
                
                // Quiz
                $quiz_id = $this->create_post($sdata[23], 'stm-quizzes', $sdata[24]);

                // Questions
                $question_id = $this->create_post($sdata[1], 'stm-questions', '');


                // Question Image 
                $imgArray = array();
                if (filter_var($sdata[3], FILTER_VALIDATE_URL)) { 
                   // Check if already upload before 
                  $posts = get_page_by_title( basename($sdata[3]), OBJECT, 'attachment' );
                  if($posts && isset($posts->ID)){
                       $image = wp_get_attachment_image_src($posts->ID, 'img-870-440');
                       $imgArray['id'] = $posts->ID;
                       $imgArray['url'] = $image[0];
                  }else{
                      $this->Generate_Featured_Image($sdata[3], $question_id, true);
                  }
                }else{
                  if(!empty($sdata[3])){
                   $image = wp_get_attachment_image_src($sdata[3], 'img-870-440');
                   $imgArray['id'] = $sdata[3];
                   $imgArray['url'] = $image[0];
                  }
                }

              if(count($imgArray) > 0){
                   update_post_meta( $question_id, 'image',  $imgArray);
              }


                
                if(isset($sdata[29]) && $sdata[29] != ''){
                    // Existing Curriculum  
                    $ex_curriculum = get_post_meta( $course_id, 'curriculum', true );
                    $ex_curriculum = ($ex_curriculum) ? explode(',',$ex_curriculum) : array();

                    // Course Curriculum 
                    $curriculum = count($ex_curriculum) > 0 ? $ex_curriculum : array();

                    if($sdata[30] && !empty($sdata[30])) array_push($curriculum, $sdata[30]);
                    if($sdata[31] && !empty($sdata[31])) array_push($curriculum, $sdata[31]);
                    if($quiz_id) array_push($curriculum, $quiz_id);
                    
                    $curriculum = array_unique($curriculum);
                    $curriculum = implode(',', $curriculum);
                    update_post_meta( $course_id, 'curriculum', $curriculum );
                }

                // Link Question to Quiz 
                $quiz_questions = get_post_meta( $quiz_id, 'questions', true );
                $quiz_questions = $quiz_questions ? explode(',', $quiz_questions) : array();
                array_push($quiz_questions, $question_id);
                update_post_meta( $quiz_id, 'questions', implode(',', $quiz_questions) );

                // Quiz duration_measure
                $duration_measure = ($sdata[28] == 'Minutes') ? '' : strtolower($sdata[28]);
                update_post_meta( $quiz_id, 'duration_measure', $duration_measure );

                // Quiz Duration 
                $duration = (isset($sdata[27]) && $sdata[27] != '') ? $sdata[27] : 0;
                update_post_meta( $quiz_id, 'duration', $sdata[23] );

                // Process Quiz Featured image
                if (filter_var($sdata[26], FILTER_VALIDATE_URL)) { 
                     // Check if already upload before 
                     $posts = get_page_by_title( basename($sdata[26]), OBJECT, 'attachment' );
                     if($posts && isset($posts->ID)){
                         set_post_thumbnail( $quiz_id, $posts->ID );    
                     }else{
                         $this->Generate_Featured_Image($sdata[26], $quiz_id);
                     }
                }else{
                    set_post_thumbnail( $quiz_id, $sdata[26] );
                }

                // Quiz Front-end Description 
                update_post_meta( $quiz_id, 'lesson_excerpt', $sdata[25] );

                // Show Currect Answer or not (For yes "on" Empty for no)
                $showCorrectAnswer = strtolower($sdata[19]) == 'yes' ? 'on':'';
                update_post_meta( $quiz_id, 'correct_answer', $showCorrectAnswer );

                // Passing Grade 
                update_post_meta( $quiz_id, 'passing_grade', (int)$sdata[20] );

                // Points total cut after re-take 
                update_post_meta( $quiz_id, 're_take_cut', (int)$sdata[21] );

                // Rendomize Question 
                $rendomize_question = strtolower($sdata[22]) == 'yes' ? 'on':'';
                update_post_meta( $quiz_id, 'random_questions', $rendomize_question );


                // Set Question Type 
                update_post_meta( $question_id, 'type', $sdata[0] );
                
                // Questin Category
                $q_cat = get_term_by( 'name', $sdata[2], 'stm_lms_question_taxonomy' );
                if(!$q_cat){
                    $q_cat = wp_insert_term( $sdata[2], 'stm_lms_question_taxonomy');
                }
                $q_cat_id = $q_cat->term_id;
                wp_set_post_terms( $question_id, array($q_cat_id), 'stm_lms_question_taxonomy' );

                $answers = array();
                for($i = 4; $i <= 16; $i+=3){
                    if(!empty($sdata[$i])){
                        $explanationIndex = $i + 2;
                        $questionIndex = $i + 1;
                        $newArray = array(
                            'text' => wp_slash($sdata[$i]), 
                            'isTrue' => '1',
                            'explain' => wp_slash($sdata[$explanationIndex])
                        );
                        if(!empty($sdata[$questionIndex])) $newArray['question'] =  $sdata[$questionIndex];
                        array_push($answers, $newArray);
                    }
                }

                update_post_meta( $question_id, 'answers', $answers );
            }
        endforeach;
    }


    /**
     * @param $data array
     * Procee Single choice Question
     *  
     **/
    public function process_single_choice_question($data = array()){
        unset($data[0]);

        $existing_curriculum = array();

        foreach($data as $sdata):
                if(!empty($sdata[1])){
                    if(isset($sdata[25]) && $sdata[25] != ''){
                        // Course
                        $course_id = $this->create_post($sdata[25], 'stm-courses', '');
                    }
                    
                    // Quiz
                    $quiz_id = $this->create_post($sdata[19], 'stm-quizzes', $sdata[20]);

                    // Questions
                    $question_id = $this->create_post($sdata[1], 'stm-questions', '');





                    // Question Image 
                    $imgArray = array();
                    if (filter_var($sdata[3], FILTER_VALIDATE_URL)) { 
                        // Check if already upload before 
                       $posts = get_page_by_title( basename($sdata[3]), OBJECT, 'attachment' );
                       if($posts && isset($posts->ID)){
                            $image = wp_get_attachment_image_src($posts->ID, 'img-870-440');
                            $imgArray['id'] = $posts->ID;
                            $imgArray['url'] = $image[0];
                       }else{
                           $this->Generate_Featured_Image($sdata[3], $question_id, true);
                       }
                   }else{
                       if(!empty($sdata[3])){
                        $image = wp_get_attachment_image_src($sdata[3], 'img-870-440');
                        $imgArray['id'] = $sdata[3];
                        $imgArray['url'] = $image[0];
                       }
                   }

                   if(count($imgArray) > 0){
                        update_post_meta( $question_id, 'image',  $imgArray);
                   }
                   


                    
                    if(isset($sdata[25]) && $sdata[25] != ''){
                        // Existing Curriculum  
                        $ex_curriculum = get_post_meta( $course_id, 'curriculum', true );
                        $ex_curriculum = ($ex_curriculum) ? explode(',',$ex_curriculum) : array();

                        // Course Curriculum 
                        $curriculum = count($ex_curriculum) > 0 ? $ex_curriculum : array();


                        if($sdata[26] && !empty($sdata[26])) array_push($curriculum, $sdata[26]);
                        if($sdata[27] && !empty($sdata[27])) array_push($curriculum, $sdata[27]);
                        if($quiz_id) array_push($curriculum, $quiz_id);
                        
                        $curriculum = array_unique($curriculum);
                        $curriculum = implode(',', $curriculum);
                        update_post_meta( $course_id, 'curriculum', $curriculum );
                    }


                    // Link Question to Quiz 
                    $quiz_questions = get_post_meta( $quiz_id, 'questions', true );
                    $quiz_questions = $quiz_questions ? explode(',', $quiz_questions) : array();
                    array_push($quiz_questions, $question_id);
                    update_post_meta( $quiz_id, 'questions', implode(',', $quiz_questions) );

                    // Quiz duration_measure
                    $duration_measure = ($sdata[24] == 'Minutes') ? '' : strtolower($sdata[24]);
                    update_post_meta( $quiz_id, 'duration_measure', $duration_measure );

                    // Quiz Duration 
                    $duration = (isset($sdata[23]) && $sdata[23] != '') ? $sdata[23] : 0;
                    update_post_meta( $quiz_id, 'duration', $sdata[23] );

                    // Process Quiz Featured image
                    if (filter_var($sdata[22], FILTER_VALIDATE_URL)) { 
                         // Check if already upload before 
                        $posts = get_page_by_title( basename($sdata[22]), OBJECT, 'attachment' );
                        if($posts && isset($posts->ID)){
                            set_post_thumbnail( $quiz_id, $posts->ID );    
                        }else{
                            $this->Generate_Featured_Image($sdata[22], $quiz_id);
                        }
                    }else{
                        set_post_thumbnail( $quiz_id, $sdata[22] );
                    }

                    // Quiz Front-end Description 
                    update_post_meta( $quiz_id, 'lesson_excerpt', $sdata[21] );

                    // Show Currect Answer or not (For yes "on" Empty for no)
                    $showCorrectAnswer = strtolower($sdata[15]) == 'yes' ? 'on':'';
                    update_post_meta( $quiz_id, 'correct_answer', $showCorrectAnswer );

                    // Passing Grade 
                    update_post_meta( $quiz_id, 'passing_grade', (int)$sdata[16] );

                    // Points total cut after re-take 
                    update_post_meta( $quiz_id, 're_take_cut', (int)$sdata[17] );

                    // Rendomize Question 
                    $rendomize_question = strtolower($sdata[18]) == 'yes' ? 'on':'';
                    update_post_meta( $quiz_id, 'random_questions', $rendomize_question );


                    // Set Question Type 
                    update_post_meta( $question_id, 'type', $sdata[0] );
                    
                    // Questin Category
                    $q_cat = get_term_by( 'name', $sdata[2], 'stm_lms_question_taxonomy' );
                    if(!$q_cat){
                        $q_cat = wp_insert_term( $sdata[2], 'stm_lms_question_taxonomy');
                    }

                    
                    
                    $q_cat_id = $q_cat->term_id;
                    wp_set_post_terms( $question_id, array($q_cat_id), 'stm_lms_question_taxonomy' );

                    $answers = array();
                    $order = 1;
                    for($i = 4; $i <= 12; $i+=2){
                        if(!empty($sdata[$i])){
                            $explanationIndex = $i + 1;
                            $newArray = array(
                                'text' => wp_slash($sdata[$i]), 
                                'isTrue' => (int)$sdata[14] != $order ? '0' : '1',
                                'explain' => wp_slash($sdata[$explanationIndex])
                            );
                            array_push($answers, $newArray);
                        }
                        $order += 1;
                    }

                    update_post_meta( $question_id, 'answers', $answers );
                }
        endforeach;
    }

    public function getConfig()
    {
        $config = ['general' =>
            ['title' => 'Acowebs Boiler Plate']
        ];

        return new WP_REST_Response($config, 200);
    }

    /**
     *
     * Ensures only one instance of APIFW is loaded or can be loaded.
     *
     * @param string $file Plugin root path.
     * @return Main APIFW instance
     * @see WordPress_Plugin_Template()
     * @since 1.0.0
     * @static
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Permission Callback
     **/
    public function getPermission()
    {
        if (current_user_can('administrator') || current_user_can('manage_woocommerce')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }
}
