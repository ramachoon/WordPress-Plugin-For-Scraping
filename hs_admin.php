<?php

defined('ABSPATH') or die("Cannot access pages directly.");

function hsAdminMenu() {    
    add_menu_page(        
        __('HSUHK Scrapping News', 'wp-scrapping-news'),
        __('HSUHK Scrapping News', 'wp-scrapping-news'),
        'manage_options',
        'hs-administration',
        'hsSetting',
        'dashicons-admin-page'
    );        
}
add_action('admin_menu', 'hsAdminMenu');


add_action( 'admin_enqueue_scripts', 'hsIncludeJS' );

function hsIncludeJS() {
    $version = time();
    $upload_dir = wp_upload_dir();
    $uploads_url = $upload_dir['baseurl'];
    wp_register_script('hs-script-js', HS_ROOT_URL.'hs_script.js', '', $version);
    wp_enqueue_script('hs-script-js');  
    wp_register_style( 'hs-style-css', HS_ROOT_URL.'hs_style.css', '', $version );
    wp_enqueue_style( 'hs-style-css' );
    wp_localize_script(
        'hs-script-js', // the handle of the script we enqueued above
        'hs_script_vars', // object name to access our PHP variables from in our script
        // register an array of variables we would like to use in our script
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'uploads_url' => $uploads_url,
            'update_news_nonce' => wp_create_nonce('hs_update_news_nonce'),
            'update_events_nonce' => wp_create_nonce('hs_update_events_nonce'),
            // 'update_staff_nonce' => wp_create_nonce('hs_update_staff_nonce'),
            'save_new_post_nonce' => wp_create_nonce('hs_save_new_post_nonce'),
        )
    );
}


/* Generic message display */
function hsGetMessage($message) {
    if($message) {
        return '<div id="message" class="'.$message['type'].'" style="display:block !important"><p>'.$message['content'].'</p></div>';
    }
    return '';
}


function hsSetting() {

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $sFile = HS_ROOT_PATH . 'page.inc.php';


    ob_start();
        include( $sFile );
        $sContents = ob_get_contents();
    ob_end_clean();

    // filter content before output
    $sContents = apply_filters( 'hs_admin_page_content', $sContents );
    echo $sContents;
}