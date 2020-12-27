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
            $postid = wp_insert_post(
                array(
                    'post_title' => $title, 
                    'post_status'   => 'publish',
                    'post_type' => $type, 
                    'post_content' => $content
                )
            );
        }

        return $postid;
    }


    public function saveCSVCallback($data){
        $counter = 0;
        foreach($data['data'] as $sdata):
            if($counter != 0 ){
                // Course
                $course_id = $this->create_post($sdata[25], 'stm-courses');
                
                // Quiz
                $quiz_id = $this->create_post($sdata[19], 'stm-quizzes', $sdata[20]);

                // Questions
                $question_id = $this->create_post($sdata[1], 'stm-questions');

            } 
            $counter +=1;
        endforeach;

        $config = ['general' =>
            [
                'data' => $data['data']
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
