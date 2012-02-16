<?php

/*
  Plugin Name: Hotlink Image Cacher with Keyword
  Plugin URI: http://yalamber.com/2012/02/hot-link-image-cacher-with-keywords/
  Description: A plugin to cache your hotlinked image in posts and save it with provided keywords.
  Version: 1.0
  Author: Yalamber subba
  Author URI: http://yalamber.com
  License: GPL2
 */
if (!function_exists('add_action')) {
    echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
    exit;
}
define('HLIK_IMG_SRC_REGEXP', '|<img.*?src=[\'"](.*?)[\'"].*?>|i');

if (is_admin())
    require_once dirname(__FILE__) . '/admin.php';

function hlik_cache_img($hlik_post_id, $options) {
    global $wpdb;
    static $suffix_map = array(
'image/gif' => 'gif',
 'image/jpeg' => 'jpg',
 'image/jpg' => 'jpg',
 'image/png' => 'png',
 'image/x-png' => 'png');

    $own_host = parse_url(get_option('siteurl'));
    $own_host = $own_host['host'];

    $hlik_post = get_post($hlik_post_id);
    $hlik_post_content = $hlik_post->post_content;

    preg_match_all(HLIK_IMG_SRC_REGEXP, $hlik_post_content, $matches);
    $img_processed = "";
    foreach ($matches[1] as $url) {
        $img_url = $url;
        //parse url
        $purl = parse_url($url);
        //remoge any double // from the url
        $url = str_replace($purl['scheme'] . '://', '', $url);
        $url = str_replace('//', '/', $url);
        $url = $purl['scheme'] . '://' . $dummy3;
        $url = $dummy4;

        if (!$purl['host'] || (strtolower($purl['host']) == strtolower($own_host))) {
            continue;  //This is local image
        }

        if ($img_processed[$img_url]) {
            continue;  // we've already processed this one
        }

        if ($purl['query']) {
            $url = $purl['scheme'] . '://' . $purl['host'] . str_replace(' ', '%20', $purl['path']) . '?' . $purl['query'];
        } else {
            $url = $purl['scheme'] . '://' . $purl['host'] . str_replace(' ', '%20', $purl['path']);
        }

        $img = "";
        $filename = "";

        if (function_exists('curl_exec')) {
            $referer = $purl['scheme'] . '://' . $purl['host'] . '/';
            $cReturn = hlik_curl($url, $referer);
            $info = $cReturn['info'];
            $img = $cReturn['img'];
            if ($purl['query']) {
                $content_type = strtolower($info['content_type']);
                $suffix = $suffix_map[$content_type];
                if ($suffix) {
                    if (get_option('hlik_image_keywords') != '') {
                        $filename = hlik_get_img_name($suffix);
                    } else {
                        $filename = md5($img) . "." . $suffix;
                    }
                } else {
                    $img = "";  //unable to determine suffix
                }
            }
        } else {
            //curl not available
            $img = file_get_contents($url);
        }

        if (!$filename) {
            //$filename = str_replace('%20', '-', basename($url));
            $p = pathinfo($purl['path']);
            $suffix = $p['extension'];
            if (get_option('hlik_image_keywords') != '') {
                $filename = hlik_get_img_name($suffix);
            } else {
                $filename = md5($img) . ".$suffix";
            }
            if (!preg_match('/^(gif|jpeg|jpg|png)$/', $suffix)) {
                $img = "";
                if ($options['show_progress']) {
                    echo "<li>WARNING: not cached, unable to determine file type of: $img_url</li>";
                    flush();
                }
            }
        }

        if ($img) {
            $upload = wp_upload_bits($filename, null, $img);
            if (isset($upload['url']) and $upload['url'] != '') {
                $hlik_post_content = str_replace($img_url, $upload['url'], $hlik_post_content);
                $wpdb->query("UPDATE " . $wpdb->posts . " SET post_content = '" . $hlik_post_content . "' WHERE ID = '" . $hlik_post_id . "';");
                $img_processed[$img_url] = true;
                if ($options['show_progress']) {
                    echo "<li>ID: $postid, Cached: $img_url => $local_img_url</li>";
                    flush();
                }
            } else {
                if ($options['show_progress']) {
                    echo "<li>ERROR: ID $postid, unable to cache image '" . $img_url . "'</li>";
                    flush();
                }
            }
        }
    }
}

function hlik_curl($url, $referer) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_REFERER, $referer);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.6pre) Gecko/2009011606 Firefox/3.1');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $img = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return array('img' => $img, 'info' => $info);
}

function hlik_cleanup_text($text) {
    $text = trim($text);
    $text = stripslashes($text);
    return $text;
}

function hlik_get_img_name($ext, $append = 0) {
    $image_kw = get_option('hlik_image_keywords');
    $image_kw_array = explode(',', $image_kw);
    array_map('trim', $image_kw_array);
    $clean_array = array_filter($image_kw_array);
    $count = count($clean_array);
    $rand_key = rand(0, $count - 1);
    $image_file = $clean_array[$rand_key];
    $idir = wp_upload_dir();
    $idir = $idir['path'];
    $i = $append;
    $file_name = trim($image_file . ($append == 0 ? '' : $append) . '.' . $ext);

    if (file_exists($idir . '/' . $file_name)) {
        $append = $i;
        $i++;
        $append = $append + 1;
        return hlik_get_img_name($ext, $append);
    } else {
        return $file_name;
    }
}

function hlik_save_post($hlik_post_id) {
    $options = array();
    hlik_cache_img($hlik_post_id, $options);
    return $hlik_post_id;
}

add_action('save_post', 'hlik_save_post');
?>