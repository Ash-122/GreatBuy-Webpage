<?php

function sports-highlight-child() {
    wp_enqueue_style('parent-style', get_template directory_uri().'/style.css') ;
    wp_enqueue_style('child-style', get_stylesheet directory_uri().'/style.css' , array('parent-style'));
    
}

add_action('wp_enqueue_scripts', sports-highlight-child())

?>