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

            '<p>The <strong>HSUHK Web scraping Tool</strong> plugin requires PHP version 5.4 or greater.</p>',

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
    $mainUrl = "https://scm.hsu.edu.hk/us/news/news";
    for($k = 1 ; $k < 30; $k++ ) {

        // Get HTML content from the site
        $dom = file_get_html($mainUrl.'&page='.$k, false);
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
                try {
                    save_new_post($newsList[$i]);
                } catch (Exception $e) {
                    continue;
                }
                $i++;
            }
            echo json_encode(array('success' => true));

        }
        hsUpdateEvents($k);
    }
    echo json_encode(array('success' => true));
    exit;
}


function save_new_post($newsData) {

    $news = $newsData;
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
        
        try {
            $newsEngContent = file_get_html($news['newsItemUrl'], false);
            if(!empty($newsEngContent)) {

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
                    'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
                    'post_excerpt'  => wp_strip_all_tags($news['abstract']),
                    'post_date'     => date('Y/m/d', strtotime($news['date']))
                );
                $english_post_id  = wp_insert_post($post_eng_data);
    
                wp_set_post_terms($english_post_id , 'en', 'language');
    
                $images = $contentEngTag ? $contentEngTag->find('img') : [];
                if($images !== null && isset($images) && !empty($images)) {
                    foreach ($images as $image) {
                        $origSrc = $src = trim($image->src);
                        if (strpos($src, 'data:image') === 0) {
                            // Skip current iteration and move to the next
                            continue;
                        }
                        if(strpos($src, 'http') === FALSE) {
                            $src = $scm_hsu_edu_hk_home_url.$src;
                        }
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
                                $imageId = media_handle_sideload( $file_array, $english_post_id, '');
                
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
                        $news['title'] = $elementsToRemove->plaintext;
                        $elementsToRemove->outertext = '';
                    }
                    $content = $contentTag->save();
                    $categoryNewsZh = "news-zh";
                    $chinese_post = array(
                        'post_type' => 'post',
                        'post_title'    => wp_strip_all_tags($news['title']),
                        'post_content'  => $content,
                        'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
                        'post_excerpt'  => wp_strip_all_tags($news['abstract']),
                        'post_date'     => date('Y/m/d', strtotime($news['date']))
                    );
            
                    $chinese_post_id = wp_insert_post($chinese_post);
                    wp_set_post_terms($chinese_post_id, 'zh', 'language');
                    
                    $chinese_images = $contentTag ? $contentTag->find('img') : [];
                    if($chinese_images !== null && isset($chinese_images) && !empty($chinese_images)) {
                        foreach ($chinese_images as $image) {
                            $origSrc = $src = trim($image->src);
                            if (strpos($src, 'data:image') === 0) {
                                // Skip current iteration and move to the next
                                continue;
                            }
    
                            if(strpos($src, 'http') === FALSE) {
                                $src = $scm_hsu_edu_hk_home_url.$src;
                            }
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
                                    $imageId = media_handle_sideload( $file_array, $chinese_post_id, '');
                
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
        } catch ( Exception $e) {
            echo "Error Handle-".$e->getMessage();
        }

    }
}


function hsUpdateEvents($ek) {	

    $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
    $mainEventsUrl = "https://scm.hsu.edu.hk/us/news/events";
    
    // Get HTML content from the site
    $dom = file_get_html($mainEventsUrl.'?page='.$ek, false);

    // Collect all user’s reviews into an array
    if (!empty($dom)) {
        foreach ($dom->find(".articleContent .news .newsItem") as $divClass) {
            $titleTag = $divClass->find(".title", 0);
            $event['title'] = $titleTag->plaintext;
            $event['newsItemUrl'] = $scm_hsu_edu_hk_home_url.$titleTag->href;
            // cover image
            $image = $divClass->find(".image img", 0);
            if ($image) {
                $event['featuredImgSrc'] = $image->src;
            }
            $desc = $divClass->find('.abstract', 0);
            if ($desc) {
                $event['abstract'] = html_entity_decode(preg_replace('/\&#39;/','"', $desc->plaintext));
            }
            $dateTag = $divClass->find(".date", 0);
            if ($dateTag) {
                $event['date'] = $dateTag->plaintext;
            }
            try {
                save_new_event($event);
            } catch (Exception $e) {
                continue;
            }
        }
    }

}



function save_new_event($event) {

    $news = $event;
    $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
    // Create a new WP_Query instance to check for existing posts
    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'category_name' => "events",
        'title' => $news['title'],
    ));

    if (!$query->have_posts()) {
        try {
            $category = get_category_by_slug('events');
            $category_id = $category->term_id;
         
            $newsEngContent = file_get_html($news['newsItemUrl'], false);
            if(!empty($newsEngContent)) {
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
                    'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
                    'post_excerpt'  => wp_strip_all_tags($news['abstract']),
                    'post_date'     => date('Y/m/d', strtotime($news['date'])),
                );
            
                $english_post_id  = wp_insert_post($post_eng_data);
                wp_set_post_terms($english_post_id , 'en', 'language');
                wp_set_post_categories($english_post_id, array($category_id));

                $images = $contentEngTag ? $contentEngTag->find('img') : [];
                if($images !== null && isset($images) && !empty($images)) {
                    
                    foreach ($images as $image) {
                        $origSrc = $src = trim($image->src);
                        if (strpos($src, 'data:image') === 0) {
                            // Skip current iteration and move to the next
                            continue;
                        }
    
    
                        if(strpos($src, 'http') === FALSE) {
                            $src = $scm_hsu_edu_hk_home_url.$src;
                        }
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
                                $imageId = media_handle_sideload( $file_array, $english_post_id, '');
                
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
                        'post_status'   => 'publish',
                        'post_date'     => date('Y/m/d', strtotime($news['date'])),
                        'post_content'  => $contentEngTag ? $contentEngTag : ''
                    )
                );
    
                $engUrl = $news['newsItemUrl'];
                $chiUrl = str_replace('/us/', '/hk/', $engUrl);
                $category = get_category_by_slug("events-zh");
                $category_id = $category->term_id;

                $newsChiContent = file_get_html($chiUrl, false);
                if(!empty($newsChiContent)) {
                    $contentTag = $newsChiContent->find(".articleContent", 0);
                    $elementsToRemove = $contentTag->find('h2', 0);
                    if ($elementsToRemove) {
                        $news['title'] = $elementsToRemove->plaintext;
                        $elementsToRemove->outertext = '';
                    }
                    $content = $contentTag->save();
    
                    $chinese_post = array(
                        'post_type' => 'post',
                        'post_title'    => wp_strip_all_tags($news['title']),
                        'post_content'  => $content,
                        'post_author'   => get_current_user_id() ? get_current_user_id() : 1,
                        'post_excerpt'  => wp_strip_all_tags($news['abstract']),
                        'post_date'     => date('Y/m/d', strtotime($news['date']))
                    );
            
                    $chinese_post_id = wp_insert_post($chinese_post);
                    wp_set_post_terms($chinese_post_id, 'zh', 'language');
                    $chinese_images = $contentTag ? $contentTag->find('img') : [];
                    if($chinese_images !== null && isset($chinese_images) && !empty($chinese_images)) {
    
                        foreach ($chinese_images as $image) {
                            $origSrc = $src = trim($image->src);
                            if (strpos($src, 'data:image') === 0) {
                                // Skip current iteration and move to the next
                                continue;
                            }
    
                            if(strpos($src, 'http') === FALSE) {
                                $src = $scm_hsu_edu_hk_home_url.$src;
                            }
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
                                    $imageId = media_handle_sideload( $file_array, $chinese_post_id, '');
                
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
                    }
                    $media_data = array(
                        'name'     => basename($news['featuredImgSrc']),
                        'tmp_name' => download_url($news['featuredImgSrc'])
                    );

                    wp_set_post_categories($chinese_post_id, array($category_id));

                    $media_id = media_handle_sideload($media_data, $chinese_post_id);
                    set_post_thumbnail( $chinese_post_id, $media_id );
                    $chinese_post_id = wp_update_post(
                        array(
                            'ID'            => (int) $chinese_post_id,
                            'post_status'   => 'publish',
                            'post_date'     => date('Y/m/d', strtotime($news['date'])),
                            'post_content'  => $contentTag ? $contentTag : '',
                        )
                    );
                }
                if (function_exists('pll_save_post_translations')) {
                    pll_save_post_translations(array('en' => $english_post_id, 'zh' => $chinese_post_id));
                }
            }
        } catch ( Exception $e) {
            echo "Error Handle-".$e->getMessage();
        }

    }
}


// * * * * * curl -s http://localhost/bingo/wp-json/scraping/v1
function handle_custom_endpoint() {
    // Import WordPress core files
    require_once(ABSPATH . 'wp-load.php');
    require_once(ABSPATH . 'wp-admin/includes/admin.php');
    global $wpdb;    
    //News update
    $scm_hsu_edu_hk_home_url = 'https://scm.hsu.edu.hk';
    $mainUrl = "https://scm.hsu.edu.hk/us/news/news";
    $currentDate = date("Y/m/d");
    $break = false;
    for($k = 1 ; $k < 30; $k++ ) {
        

        // Get HTML content from the site
        $dom = file_get_html($mainUrl.'&page='.$k, false);
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
                $thisDate = date('Y/m/d', strtotime($newsList[$i]['date']));
                if ($currentDate === $thisDate) {
                    try {
                        save_new_post($newsList[$i]);
                    } catch (Exception $e) {
                        continue;
                    }                    
                } else {
                    $break = true;
                }
                $i++;
            }
        }
        if($break ) {
            break;
        }
    }

    //  Events update
    $mainEventsUrl = "https://scm.hsu.edu.hk/us/news/events";
    $breakE = false;
    for($k = 1 ; $k < 30; $k++ ) {

        // Get HTML content from the site
        $dom = file_get_html($mainEventsUrl.'&page='.$k, false);
        $newsList = array();
        $i = 0;		
        // Collect all user’s reviews into an array
        if (!empty($dom)) {
            foreach ($dom->find(".articleContent .news .newsItem") as $divClass) {
                $titleTag = $divClass->find(".title", 0);
                $event['title'] = $titleTag->plaintext;
                $event['newsItemUrl'] = $scm_hsu_edu_hk_home_url.$titleTag->href;
                // cover image
                $image = $divClass->find(".image img", 0);
                if ($image) {
                    $event['featuredImgSrc'] = $image->src;
                }
                $desc = $divClass->find('.abstract', 0);
                if ($desc) {
                    $event['abstract'] = html_entity_decode(preg_replace('/\&#39;/','"', $desc->plaintext));
                }
                $dateTag = $divClass->find(".date", 0);
                if ($dateTag) {
                    $event['date'] = $dateTag->plaintext;
                }
                $thisDate = date('Y/m/d', strtotime($event['date']));
				
                if ($currentDate === $thisDate) {
                    try {
						save_new_event($event);
                    } catch (Exception $e) {
                        continue;
                    }
                } else {
                    $breakE = true;
                }


            }
        }
        if($breakE ) {
            break;
        }
		
    }

    return rest_ensure_response("OK");
}


/**
 * This function is where we register our routes for our example endpoint.
 */
function prefix_register_scraping_routes() {
    // register_rest_route() handles more arguments but we are going to stick to the basics for now.
    register_rest_route( 'scraping', '/v1', array(
        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
        'methods'  => WP_REST_Server::READABLE,
        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
        'callback' => 'handle_custom_endpoint',
    ) );
}

add_action( 'rest_api_init', 'prefix_register_scraping_routes' );