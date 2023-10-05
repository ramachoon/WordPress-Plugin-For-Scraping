<?php

require_once 'includes/simple_html_dom.php';

/**

 * Uninstall hook

 */

function hsUninstall() {    


}



function hsDeactivation() {



}



function hsActivation($networkWide) {    


    // Check PHP version

    if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {

        deactivate_plugins(WBS_BASENAME);

        wp_die(

            '<p>The <strong>WP Bulk Upldate Sale</strong> plugin requires PHP version 5.4 or greater.</p>',

            'Plugin Activation Error',

            ['response' => 200, 'back_link' => TRUE]

        );

    }

}




add_action("wp_ajax_nopriv_hs_update_news", "hsUpdateNews");

add_action("wp_ajax_hs_update_news", "hsUpdateNews");

function hsUpdateNews() {	

    global $wpdb;    

    if ( !wp_verify_nonce( $_REQUEST['nonce'], "hs_update_news_nonce")) {      	

        echo json_encode(array('error'=>1, 'message'=>'Invalid Request'));

        die();

    }

    $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
    $url = $_POST['url'];
    
    // Get HTML content from the site
    $dom = file_get_html($url, false);
    $newsList = array();
    $i = 0;
    // Collect all user’s reviews into an array
    if (!empty($dom)) {
        foreach ($dom->find(".articleContent .news .newsItem") as $divClass) {
            $titleTag = $divClass->find(".title", 0);
            $newsList[$i]['title'] = $titleTag->plaintext;
            $newsList[$i]['newsItemUrl'] = $scm_hsu_edu_hk_home_url.$titleTag->href;
            // cover image
            $image = $divClass->find(".image img", 0);
            if ($image) {
                $newsList[$i]['featuredImgSrc'] = $image->src;
            }
            $desc = $divClass->find('.abstract', 0);
            if ($desc) {
                $newsList[$i]['abstract'] = html_entity_decode(preg_replace('/\&#39;/','"', $desc->plaintext));
            }
            $dateTag = $divClass->find(".date", 0);
            if ($dateTag) {
                $newsList[$i]['date'] = $dateTag->plaintext;
            }
            $i++;
        }
    }

    echo json_encode($newsList);
    unset($dom);
    unset($newsList);
    exit;
}

add_action('wp_ajax_hs_save_new_post', 'save_new_post');
add_action('wp_ajax_nopriv_hs_save_new_post', 'save_new_post');

function save_new_post() {

    if ( !wp_verify_nonce( $_REQUEST['nonce'], "hs_save_new_post_nonce")) {      	

        echo json_encode(array('error'=>1, 'message'=>'Invalid Request'));

        die();

    }
    $news = $_POST['news'];
    $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
    $category = "news";
    // Create a new WP_Query instance to check for existing posts
    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'tax_query' => array(
            array(
                'taxonomy' => 'language',  // Replace with your actual taxonomy name
                'field' => 'slug',  // Replace with 'name' if you are using language names instead of slugs
                'terms' => 'en',
            ),
        ),
        'category_name' => $category,
        'posts_per_page' => 1,
        'title' => $news['title'],
    ));

    if (!$query->have_posts()) {
        $newsEngContent = file_get_html($news['newsItemUrl'], false);
        $contentEngTag = $newsEngContent->find(".articleContent", 0);
        $elementsToRemove = $contentEngTag->find('h2', 0);
        if ($elementsToRemove) {
            $elementsToRemove->outertext = '';
        }
        $content = $contentEngTag->save();
        $post_eng_data = array(
            'post_type' => 'post',
            'post_title'    => wp_strip_all_tags($news['title']),
            'post_content'  => $content,
            'post_author'   => get_current_user_id(),
            'category_name' => array($category),
            'post_excerpt'  => wp_strip_all_tags($news['abstract']),
            'post_date'     => date('Y/m/d', strtotime($news['date']))
        );
    
        $english_post_id  = wp_insert_post($post_eng_data);
        wp_set_post_terms($english_post_id , 'en', 'language');
        
        $images = $contentEngTag ? $contentEngTag->find('img') : [];
        foreach ($images as $image) {
            $origSrc = $src = trim($image->src);
            $src = $scm_hsu_edu_hk_home_url.$src;
            // Download to temp folder
            $tmp = download_url( $src );
            $file_array = array();
            $newSrc = '';
    
            preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $src, $matches);
            if (isset($matches[0]) && $matches[0]) {
                $file_array['name'] = basename($matches[0]);
                $file_array['tmp_name'] = $tmp;
                if ( is_wp_error( $tmp ) ) {
                    @unlink($file_array['tmp_name']);
                    $file_array['tmp_name'] = '';
                } else {
                    // do the validation and storage stuff
                    $imageId = media_handle_sideload( $file_array, $postId, '');
    
                    // If error storing permanently, unlink
                    if ( is_wp_error($imageId) ) {
                        @unlink($file_array['tmp_name']);
                    } else {
                        $newSrc = wp_get_attachment_url($imageId);
                    }
                }
            } else {
                @unlink($tmp);
            }
    
            // Replace images url in code
            if ($newSrc) {
                $contentEngTag = str_replace(htmlentities($origSrc), $newSrc, $contentEngTag);
            }
        }
        $media_data = array(
            'name'     => basename($news['featuredImgSrc']),
            'tmp_name' => download_url($news['featuredImgSrc'])
        );
    
        $media_id = media_handle_sideload($media_data, $english_post_id );
    
        set_post_thumbnail( $english_post_id , $media_id );
        $english_post_id  = wp_update_post(
            array(
                'ID'            => (int) $english_post_id ,
                'category_name' => array($category),
                'post_status'   => 'publish',
                'post_date'     => date('Y/m/d', strtotime($news['date'])),
                'post_content'  => $contentEngTag ? $contentEngTag : ''
            )
        );
    
    
        $engUrl = $news['newsItemUrl'];
        $chiUrl = str_replace('/us/', '/hk/', $engUrl);
    
        $newsChiContent = file_get_html($chiUrl, false);
        if(!empty($newsChiContent)) {
            $contentTag = $newsChiContent->find(".articleContent", 0);
            $elementsToRemove = $contentTag->find('h2', 0);
            if ($elementsToRemove) {
                $elementsToRemove->outertext = '';
            }
            $content = $contentTag->save();
            $categoryZh = "news-zh";
            $chinese_post = array(
                'post_type' => 'post',
                'post_title'    => wp_strip_all_tags($news['title']),
                'post_content'  => $content,
                'post_author'   => get_current_user_id(),
                'category_name' => array($categoryZh),
                'post_excerpt'  => wp_strip_all_tags($news['abstract']),
                'post_date'     => date('Y/m/d', strtotime($news['date']))
            );
    
            $chinese_post_id = wp_insert_post($chinese_post);
            wp_set_post_terms($chinese_post_id, 'zh', 'language');
            
            $chinese_images = $contentTag ? $contentTag->find('img') : [];
            foreach ($chinese_images as $image) {
                $origSrc = $src = trim($image->src);
                $src = $scm_hsu_edu_hk_home_url.$src;
                // Download to temp folder
                $tmp = download_url( $src );
                $file_array = array();
                $newSrc = '';
    
                preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $src, $matches);
                if (isset($matches[0]) && $matches[0]) {
                    $file_array['name'] = basename($matches[0]);
                    $file_array['tmp_name'] = $tmp;
                    if ( is_wp_error( $tmp ) ) {
                        @unlink($file_array['tmp_name']);
                        $file_array['tmp_name'] = '';
                    } else {
                        // do the validation and storage stuff
                        $imageId = media_handle_sideload( $file_array, $postId, '');
    
                        // If error storing permanently, unlink
                        if ( is_wp_error($imageId) ) {
                            @unlink($file_array['tmp_name']);
                        } else {
                            $newSrc = wp_get_attachment_url($imageId);
                        }
                    }
                } else {
                    @unlink($tmp);
                }
    
                // Replace images url in code
                if ($newSrc) {
                    $contentTag = str_replace(htmlentities($origSrc), $newSrc, $contentTag);
                }
            }
            $media_data = array(
                'name'     => basename($news['featuredImgSrc']),
                'tmp_name' => download_url($news['featuredImgSrc'])
            );
    
            $media_id = media_handle_sideload($media_data, $chinese_post_id);
    
            set_post_thumbnail( $chinese_post_id, $media_id );
            $chinese_post_id = wp_update_post(
                array(
                    'ID'            => (int) $chinese_post_id,
                    'category_name' => array($categoryZh),
                    'post_status'   => 'publish',
                    'post_date'     => date('Y/m/d', strtotime($news['date'])),
                    'post_content'  => $contentTag ? $contentTag : ''
                )
            );
        }


        if (function_exists('pll_save_post_translations')) {
            pll_save_post_translations(array('en' => $english_post_id, 'zh' => $chinese_post_id));
        }

    }

    echo json_encode(array('success'=> true,'zh'=>$chinese_post_id,'en'=>$english_post_id));
    exit;
}


add_action("wp_ajax_nopriv_hs_update_events", "hsUpdateEvents");

add_action("wp_ajax_hs_update_events", "hsUpdateEvents");

function hsUpdateEvents() {	

    global $wpdb;    

    if ( !wp_verify_nonce( $_REQUEST['nonce'], "hs_update_events_nonce")) {      	

        echo json_encode(array('error'=>1, 'message'=>'Invalid Request'));

        die();

    }

    $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
    $url = $_POST['url'];
    
    // Get HTML content from the site
    $dom = file_get_html($url, false);
    $newsList = array();
    $i = 0;
    // Collect all user’s reviews into an array
    if (!empty($dom)) {
        foreach ($dom->find(".articleContent .news .newsItem") as $divClass) {
            $titleTag = $divClass->find(".title", 0);
            $newsList[$i]['title'] = $titleTag->plaintext;
            $newsList[$i]['newsItemUrl'] = $scm_hsu_edu_hk_home_url.$titleTag->href;
            // cover image
            $image = $divClass->find(".image img", 0);
            if ($image) {
                $newsList[$i]['featuredImgSrc'] = $image->src;
            }
            $desc = $divClass->find('.abstract', 0);
            if ($desc) {
                $newsList[$i]['abstract'] = html_entity_decode(preg_replace('/\&#39;/','"', $desc->plaintext));
            }
            $dateTag = $divClass->find(".date", 0);
            if ($dateTag) {
                $newsList[$i]['date'] = $dateTag->plaintext;
            }
            $i++;
        }
    }

    echo json_encode($newsList);
    unset($dom);
    unset($newsList);
    exit;
}



add_action('wp_ajax_hs_save_new_event', 'save_new_event');
add_action('wp_ajax_nopriv_hs_save_new_event', 'save_new_event');

function save_new_event() {

    if ( !wp_verify_nonce( $_REQUEST['nonce'], "hs_save_new_post_nonce")) {      	

        echo json_encode(array('error'=>1, 'message'=>'Invalid Request'));

        die();

    }
    $news = $_POST['news'];
    $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
    $category = "events";
    // Create a new WP_Query instance to check for existing posts
    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'tax_query' => array(
            array(
                'taxonomy' => 'language',  // Replace with your actual taxonomy name
                'field' => 'slug',  // Replace with 'name' if you are using language names instead of slugs
                'terms' => 'en',
            ),
        ),
        'category_name' => $category,
        'posts_per_page' => 1,
        'title' => $news['title'],
    ));

    if (!$query->have_posts()) {
        $newsEngContent = file_get_html($news['newsItemUrl'], false);
        $contentEngTag = $newsEngContent->find(".articleContent", 0);
        $elementsToRemove = $contentEngTag->find('h2', 0);
        if ($elementsToRemove) {
            $elementsToRemove->outertext = '';
        }
        $content = $contentEngTag->save();
        $post_eng_data = array(
            'post_type' => 'post',
            'post_title'    => wp_strip_all_tags($news['title']),
            'post_content'  => $content,
            'post_author'   => get_current_user_id(),
            'category_name' => array($category),
            'post_excerpt'  => wp_strip_all_tags($news['abstract']),
            'post_date'     => date('Y/m/d', strtotime($news['date']))
        );
    
        $english_post_id  = wp_insert_post($post_eng_data);
        wp_set_post_terms($english_post_id , 'en', 'language');
        
        $images = $contentEngTag ? $contentEngTag->find('img') : [];
        foreach ($images as $image) {
            $origSrc = $src = trim($image->src);
            $src = $scm_hsu_edu_hk_home_url.$src;
            // Download to temp folder
            $tmp = download_url( $src );
            $file_array = array();
            $newSrc = '';
    
            preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $src, $matches);
            if (isset($matches[0]) && $matches[0]) {
                $file_array['name'] = basename($matches[0]);
                $file_array['tmp_name'] = $tmp;
                if ( is_wp_error( $tmp ) ) {
                    @unlink($file_array['tmp_name']);
                    $file_array['tmp_name'] = '';
                } else {
                    // do the validation and storage stuff
                    $imageId = media_handle_sideload( $file_array, $postId, '');
    
                    // If error storing permanently, unlink
                    if ( is_wp_error($imageId) ) {
                        @unlink($file_array['tmp_name']);
                    } else {
                        $newSrc = wp_get_attachment_url($imageId);
                    }
                }
            } else {
                @unlink($tmp);
            }
    
            // Replace images url in code
            if ($newSrc) {
                $contentEngTag = str_replace(htmlentities($origSrc), $newSrc, $contentEngTag);
            }
        }
        $media_data = array(
            'name'     => basename($news['featuredImgSrc']),
            'tmp_name' => download_url($news['featuredImgSrc'])
        );
    
        $media_id = media_handle_sideload($media_data, $english_post_id );
    
        set_post_thumbnail( $english_post_id , $media_id );
        $english_post_id  = wp_update_post(
            array(
                'ID'            => (int) $english_post_id ,
                'category_name' => array($category),
                'post_status'   => 'publish',
                'post_date'     => date('Y/m/d', strtotime($news['date'])),
                'post_content'  => $contentEngTag ? $contentEngTag : ''
            )
        );

        $engUrl = $news['newsItemUrl'];
        $chiUrl = str_replace('/us/', '/hk/', $engUrl);
        $categoryZh = "events-zh";
    
        $newsChiContent = file_get_html($chiUrl, false);
        if(!empty($newsChiContent)) {
            $contentTag = $newsChiContent->find(".articleContent", 0);
            $elementsToRemove = $contentTag->find('h2', 0);
            if ($elementsToRemove) {
                $elementsToRemove->outertext = '';
            }
            $content = $contentTag->save();

            $chinese_post = array(
                'post_type' => 'post',
                'post_title'    => wp_strip_all_tags($news['title']),
                'post_content'  => $content,
                'post_author'   => get_current_user_id(),
                'category_name' => array($categoryZh),
                'post_excerpt'  => wp_strip_all_tags($news['abstract']),
                'post_date'     => date('Y/m/d', strtotime($news['date']))
            );
    
            $chinese_post_id = wp_insert_post($chinese_post);
            wp_set_post_terms($chinese_post_id, 'zh', 'language');
            
            $chinese_images = $contentTag ? $contentTag->find('img') : [];
            foreach ($chinese_images as $image) {
                $origSrc = $src = trim($image->src);
                $src = $scm_hsu_edu_hk_home_url.$src;
                // Download to temp folder
                $tmp = download_url( $src );
                $file_array = array();
                $newSrc = '';
    
                preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $src, $matches);
                if (isset($matches[0]) && $matches[0]) {
                    $file_array['name'] = basename($matches[0]);
                    $file_array['tmp_name'] = $tmp;
                    if ( is_wp_error( $tmp ) ) {
                        @unlink($file_array['tmp_name']);
                        $file_array['tmp_name'] = '';
                    } else {
                        // do the validation and storage stuff
                        $imageId = media_handle_sideload( $file_array, $postId, '');
    
                        // If error storing permanently, unlink
                        if ( is_wp_error($imageId) ) {
                            @unlink($file_array['tmp_name']);
                        } else {
                            $newSrc = wp_get_attachment_url($imageId);
                        }
                    }
                } else {
                    @unlink($tmp);
                }
    
                // Replace images url in code
                if ($newSrc) {
                    $contentTag = str_replace(htmlentities($origSrc), $newSrc, $contentTag);
                }
            }
            $media_data = array(
                'name'     => basename($news['featuredImgSrc']),
                'tmp_name' => download_url($news['featuredImgSrc'])
            );
    
            $media_id = media_handle_sideload($media_data, $chinese_post_id);
            set_post_thumbnail( $chinese_post_id, $media_id );
            $chinese_post_id = wp_update_post(
                array(
                    'ID'            => (int) $chinese_post_id,
                    'category_name' => array($categoryZh),
                    'post_status'   => 'publish',
                    'post_date'     => date('Y/m/d', strtotime($news['date'])),
                    'post_content'  => $contentTag ? $contentTag : ''
                )
            );
        }
        if (function_exists('pll_save_post_translations')) {
            pll_save_post_translations(array('en' => $english_post_id, 'zh' => $chinese_post_id));
        }
    }

    echo json_encode(array('success'=> true));
    exit;
}



// add_action("wp_ajax_nopriv_hs_update_staff", "hsUpdateStaff");

// add_action("wp_ajax_hs_update_staff", "hsUpdateStaff");

// function hsUpdateStaff() {	

//     global $wpdb;    

//     if ( !wp_verify_nonce( $_REQUEST['nonce'], "hs_update_staff_nonce")) {      	

//         echo json_encode(array('error'=>1, 'message'=>'Invalid Request'));

//         die();

//     }

//     $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
//     $url = $_POST['url'];
    
//     // Get HTML content from the site
//     $dom = file_get_html($url, false);
//     $newsList = array();
//     $i = 0;
//     // Collect all user’s reviews into an array
//     if (!empty($dom)) {
//         foreach ($dom->find(".articleContent .newsItem") as $divClass) {
//             $titleTag = $divClass->find(".title", 0);
//             $newsList[$i]['title'] = $titleTag->plaintext;
//             $newsList[$i]['newsItemUrl'] = $scm_hsu_edu_hk_home_url.$titleTag->href;
//             // cover image
//             $image = $divClass->find(".image img", 0);
//             if ($image) {
//                 $newsList[$i]['featuredImgSrc'] = $image->src;
//             }
//             $desc = $divClass->find('.abstract', 0);
//             if ($desc) {
//                 $content = $desc->innertext;
//                 // Split the content by <br> tags
//                 $lines = explode('<br>', $content);

//                 // Trim each line and remove empty lines
//                 $lines = array_map('trim', $lines);
//                 $lines = array_filter($lines);

//                 $newsList[$i]['position']
//                 $newsList[$i]['phone']
//                 $newsList[$i]['email']
//                 $newsList[$i]['certification']

//                 $newsList[$i]['abstract'] = html_entity_decode(preg_replace('/\&#39;/','"', $desc->plaintext));
//             }
//             $dateTag = $divClass->find(".date", 0);
//             if ($dateTag) {
//                 $newsList[$i]['date'] = $dateTag->plaintext;
//             }
//             $i++;
//         }
//     }

//     echo json_encode($newsList);
//     unset($dom);
//     unset($newsList);
//     exit;
// }



// add_action('wp_ajax_hs_save_new_staff', 'save_new_staff');
// add_action('wp_ajax_nopriv_hs_save_new_staff', 'save_new_staff');

// function save_new_staff() {

//     if ( !wp_verify_nonce( $_REQUEST['nonce'], "hs_save_new_post_nonce")) {      	

//         echo json_encode(array('error'=>1, 'message'=>'Invalid Request'));

//         die();

//     }
//     $news = $_POST['news'];
//     $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
//     $category = "events";
//     // Create a new WP_Query instance to check for existing posts
//     $query = new WP_Query(array(
//         'post_type' => 'faculty_staff',
//         'post_status' => 'publish',
//         'tax_query' => array(
//             array(
//                 'taxonomy' => 'language',  // Replace with your actual taxonomy name
//                 'field' => 'slug',  // Replace with 'name' if you are using language names instead of slugs
//                 'terms' => 'en',
//             ),
//         ),
//         'category_name' => $category,
//         'posts_per_page' => 1,
//         'title' => $news['title'],
//     ));

//     if (!$query->have_posts()) {
//         $chinese_post_id = null;
//         $newsEngContent = file_get_html($news['newsItemUrl'], false);
//         $contentEngTag = $newsEngContent->find(".articleContent", 0);
//         $elementsToRemove = $contentEngTag->find('h2', 0);
//         if ($elementsToRemove) {
//             $elementsToRemove->outertext = '';
//         }
//         $content = $contentEngTag->save();
//         $post_eng_data = array(
//             'post_type' => 'post',
//             'post_title'    => wp_strip_all_tags($news['title']),
//             'post_content'  => $content,
//             'post_author'   => get_current_user_id(),
//             'category_name' => array($category),
//             'post_excerpt'  => wp_strip_all_tags($news['abstract']),
//             'post_date'     => date('Y/m/d', strtotime($news['date']))
//         );
    
//         $english_post_id  = wp_insert_post($post_eng_data);
        
//         $images = $contentEngTag ? $contentEngTag->find('img') : [];
//         foreach ($images as $image) {
//             $origSrc = $src = trim($image->src);
//             $src = $scm_hsu_edu_hk_home_url.$src;
//             // Download to temp folder
//             $tmp = download_url( $src );
//             $file_array = array();
//             $newSrc = '';
    
//             preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $src, $matches);
//             if (isset($matches[0]) && $matches[0]) {
//                 $file_array['name'] = basename($matches[0]);
//                 $file_array['tmp_name'] = $tmp;
//                 if ( is_wp_error( $tmp ) ) {
//                     @unlink($file_array['tmp_name']);
//                     $file_array['tmp_name'] = '';
//                 } else {
//                     // do the validation and storage stuff
//                     $imageId = media_handle_sideload( $file_array, $postId, '');
    
//                     // If error storing permanently, unlink
//                     if ( is_wp_error($imageId) ) {
//                         @unlink($file_array['tmp_name']);
//                     } else {
//                         $newSrc = wp_get_attachment_url($imageId);
//                     }
//                 }
//             } else {
//                 @unlink($tmp);
//             }
    
//             // Replace images url in code
//             if ($newSrc) {
//                 $contentEngTag = str_replace(htmlentities($origSrc), $newSrc, $contentEngTag);
//             }
//         }
//         $media_data = array(
//             'name'     => basename($news['featuredImgSrc']),
//             'tmp_name' => download_url($news['featuredImgSrc'])
//         );
    
//         $media_id = media_handle_sideload($media_data, $english_post_id );
    
//         set_post_thumbnail( $english_post_id , $media_id );
//         $english_post_id  = wp_update_post(
//             array(
//                 'ID'            => (int) $english_post_id ,
//                 'category_name' => array($category),
//                 'post_status'   => 'publish',
//                 'post_date'     => date('Y/m/d', strtotime($news['date'])),
//                 'post_content'  => $contentEngTag ? $contentEngTag : ''
//             )
//         );
//         wp_set_post_terms($english_post_id , 'en', 'language');

//         $engUrl = $news['newsItemUrl'];
//         $chiUrl = str_replace('/us/', '/hk/', $engUrl);
    
//         $newsChiContent = file_get_html($chiUrl, false);
//         if(!empty($newsChiContent)) {
//             $contentTag = $newsChiContent->find(".articleContent", 0);
//             $elementsToRemove = $contentTag->find('h2', 0);
//             if ($elementsToRemove) {
//                 $elementsToRemove->outertext = '';
//             }
//             $content = $contentTag->save();
//             $chinese_post = array(
//                 'post_type' => 'post',
//                 'post_title'    => wp_strip_all_tags($news['title']),
//                 'post_content'  => $content,
//                 'post_author'   => get_current_user_id(),
//                 'category_name' => array($category),
//                 'post_excerpt'  => wp_strip_all_tags($news['abstract']),
//                 'post_date'     => date('Y/m/d', strtotime($news['date']))
//             );
    
//             $chinese_post_id = wp_insert_post($chinese_post);
            
//             $chinese_images = $contentTag ? $contentTag->find('img') : [];
//             foreach ($chinese_images as $image) {
//                 $origSrc = $src = trim($image->src);
//                 $src = $scm_hsu_edu_hk_home_url.$src;
//                 // Download to temp folder
//                 $tmp = download_url( $src );
//                 $file_array = array();
//                 $newSrc = '';
    
//                 preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $src, $matches);
//                 if (isset($matches[0]) && $matches[0]) {
//                     $file_array['name'] = basename($matches[0]);
//                     $file_array['tmp_name'] = $tmp;
//                     if ( is_wp_error( $tmp ) ) {
//                         @unlink($file_array['tmp_name']);
//                         $file_array['tmp_name'] = '';
//                     } else {
//                         // do the validation and storage stuff
//                         $imageId = media_handle_sideload( $file_array, $postId, '');
    
//                         // If error storing permanently, unlink
//                         if ( is_wp_error($imageId) ) {
//                             @unlink($file_array['tmp_name']);
//                         } else {
//                             $newSrc = wp_get_attachment_url($imageId);
//                         }
//                     }
//                 } else {
//                     @unlink($tmp);
//                 }
    
//                 // Replace images url in code
//                 if ($newSrc) {
//                     $contentTag = str_replace(htmlentities($origSrc), $newSrc, $contentTag);
//                 }
//             }
//             $media_data = array(
//                 'name'     => basename($news['featuredImgSrc']),
//                 'tmp_name' => download_url($news['featuredImgSrc'])
//             );
    
//             $media_id = media_handle_sideload($media_data, $chinese_post_id);
    
//             set_post_thumbnail( $chinese_post_id, $media_id );
//             $chinese_post_id = wp_update_post(
//                 array(
//                     'ID'            => (int) $chinese_post_id,
//                     'category_name' => array($category),
//                     'post_status'   => 'publish',
//                     'post_date'     => date('Y/m/d', strtotime($news['date'])),
//                     'post_content'  => $contentTag ? $contentTag : ''
//                 )
//             );
//             wp_set_post_terms($chinese_post_id, 'zh', 'language');
//         }
//         if($chinese_post_id !== null) {
//             // Link the posts for translation
//             if ( function_exists( 'icl_object_id' ) ) {
//                 // // Get the WPML language code for Chinese
//                 // $chinese_language_code = apply_filters( 'wpml_element_language_code', null, array(
//                 //     'element_id' => $chinese_post_id,
//                 //     'element_type' => 'post'
//                 // ) );
    
//                 // Set the translation relationship between the posts
//                 do_action( 'wpml_set_element_language_details', $english_post_id, 'post', null, 'en' );
//                 do_action( 'wpml_set_element_language_details', $chinese_post_id, 'post', $english_post_id, 'zh' );
//                 // do_action( 'wpml_set_element_language_details', $chinese_post_id, 'post', $english_post_id, $chinese_language_code );
//             }
//         }
//     }

//     echo json_encode(array('success'=> true));
//     exit;
// }