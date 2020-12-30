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
                'post_title' => $title, 
                'post_status'   => 'publish',
                'post_type' => $type
            );

            if(!empty($content)) $postArray['post_content'] = $content;
            $postid = wp_insert_post($postArray);
        }

        return $postid;
    }


    public function Generate_Featured_Image( $image_url, $post_id  ){
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
        $res2= set_post_thumbnail( $post_id, $attach_id );
    }


    public function saveCSVCallback($data){
        $counter = 0;
        $imgTypeArray = array();
        foreach($data['data'] as $sdata):
            if($counter > 0 && isset($sdata[25]) && $sdata[25] != ''){
                // Course
                $course_id = $this->create_post($sdata[25], 'stm-courses', '');
                
                // Quiz
                $quiz_id = $this->create_post($sdata[19], 'stm-quizzes', $sdata[20]);

                // Questions
                $question_id = $this->create_post($sdata[1], 'stm-questions', '');
                
                // Course Curriculum 
                $curriculum = array(
                    $sdata[26], 
                    $sdata[27], 
                    $quiz_id
                );
                array_push($imgTypeArray, $counter);
                $curriculum = implode(',', $curriculum);
                update_post_meta( $course_id, 'curriculum', $curriculum );

                // Quiz duration_measure
                $duration_measure = ($sdata[24] == 'Minutes') ? '' : strtolower($sdata[24]);
                update_post_meta( $quiz_id, 'duration_measure', $duration_measure );

                // Quiz Duration 
                $duration = (isset($sdata[23]) && $sdata[23] != '') ? $sdata[23] : 0;
                update_post_meta( $quiz_id, 'duration', $sdata[23] );

                // Process Quiz Featured image
                if (filter_var($sdata[22], FILTER_VALIDATE_URL)) { 
                    $this->Generate_Featured_Image($sdata[22], $quiz_id);
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
                $q_cat = (array) get_term_by( 'name', $sdata[2], 'stm_lms_question_taxonomy' );
                if(!$q_cat){
                    $q_cat = wp_insert_term( $sdata[2], 'stm_lms_question_taxonomy');
                }
                $q_cat_id = $q_cat['term_id'];
                wp_set_post_terms( $question_id, array($q_cat_id), 'stm_lms_question_taxonomy' );

                $answers = array();
                $order = 1;
                for($i = 4; $i <= 12; $i+=2){
                    if(!empty($sdata[$i])){
                        $explanationIndex = $i + 1;
                        $newArray = array(
                            'text' => $sdata[$i], 
                            'isTrue' => (int)$sdata[14] == $order ? 0 : 1,
                            'explain' => $sdata[$explanationIndex]
                        );
                        array_push($answers, $newArray);
                    }
                    $order += 1;
                }

                update_post_meta( $question_id, 'answers', $answers );

            } 
            $counter +=1;
        endforeach;

        $config = ['general' =>
            [
                'data' => $data['data'], 
                'counter' => $imgTypeArray
            ]
        ];
        return new WP_REST_Response($config, 200);
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
