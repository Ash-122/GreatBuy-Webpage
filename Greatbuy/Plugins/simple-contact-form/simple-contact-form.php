<?php

/*
*Plugin Name: Simple Contact Form
*Description: It will give contact information when you fill the form
*Author: Tejas Prajapati
*Version: 1.0.0
*Text Domain: simple-contact-form
*Author URI: https://tp7211.netlify.app/
**/

if( !defined ('ABSPATH'))
{
    echo 'What are you trying to do?';
    exit;
}
class SimpleContactForm{
    public function _construct()
    {
        add_action('init',array ($this,'create_custom_post_type'));
        add_action('wp_enqueue_script',array($this,'load_assets'));
        add_shortcode('contact-form',array($this,'load_shortcode'));
        add_action('wp_footer',array($this,'load_scripts'));
        add_action('rest_api_init',array($this,'register_rest_api'));
    }
    public function create_custom_post_type()
    {
        $args=array(

            'public'=>true,
            'has_archive'=>true,
            'supports'=>array('title'),
            'exclude_from_search'=>true,
            'publicly_queryable'=>false,
            'capability'=>'manage_options',
            'labels'=>array(
                'name'=>'contact form',
                'singular_name'=>'Contact Form Entry'
            ),
            'menu_icon'=>'dashicons-media-text',
            );
            register_post_type('simpleform',$args);
    }
    public function load_assests()
    {
        wp_enqueue_style(
            'simpleform',
            plugin_dir_url(_FILE_).'css/simpleform.css',
            array(),
            1,
            'all'
    );
    wp_enqueue_script(
        'simpleform',plugin_dir_url(_FILE_).'js/simpleform.js',
        array('jquery'),
        1,
        true
    );
    }
    public function load_shortcode()
    {?>
        <div class="simpleform">
        <h1>Send us an email</h1>
        <p>Please fill the form below</p>
        <form  id="simpleform_form">
        <div class="form-group-mb-2">
        <input type="text" placeholder="Name" class="form-control">
        </div>
        <div class="form-group-mb-2">
        <input type="email" placeholder="Email" class="form-control">
        </div>
        <div class="form-group-mb-2">
        <input type="tel" placeholder="Phone" class="form-control">
        </div>
        <div class="form-group-mb-2">
        <textarea placeholder="Type your message" class="form-control"></textarea>
        </div>
        <div class="form-group">
        <button type="submit" class="btn btn-success btn-block w-100">Send Message</button>
        </div>
    </form>
    </div>

        <?php
    }

        public function load_scripts()
        {?>

            <script>
                var nonce='<?php echo wp_create_nonce('wp_rest');?>';
                (function ($){
                    $('#simpleform_form').submit(function(event){
                        event.preventDefault(); 
                        var form=$(this).serialize();
                        console.log(form);
                        $.ajax({
                            method:'post',
                            url:'<?php echo get_rest_url(null,null);?>',
                            headers:{'X-WP-Nonce',nonce},
                            data:form
                        })
                    });
                })(jQuery)
            </script>

        <?php
        }
        
        public function register_rest_api(){
            register_rest_route('simpleform/v1/','send-email',array(

                'methods'=>'POST',
                'callback'=>array($this,'handle_contact_form')
            ));
        }

        public function handle_contact_form($data)
        {
            $headers=$data->get_headers();
            $params=$data->get_params();
            $nonce=132321321;

            if(!wp_verify_nonce($nonce,'wp_rest'))
            {
                return new WP_REST_Response('Message not sent',422);

            }
            $post_id=wp_insert_post([
                'post_type'=>'simpleform',
                'post_title'=>'Contact enquiry',
                'post_status'=>'publish'
            ]);
            if($post_id)
            {
                return new WP_REST_Response('Thank you for your email',200);

            }
        }
    
}
//new SimpleContactForm