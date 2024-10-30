<?php


use HBAM\V1\Plugin\Repository\Cron;
use HBAM\V1\Plugin\Repository\OptionPage;

require "vendor/autoload.php";

/*
Plugin Name: hatenablog-auto-migration
Plugin URI:
Description: はてなブログの記事を自動で取得、投稿します。
Version: 1.0.3
Author: Tadatsu<tada.purple.haze@gmail.com>
Author URI:
License: GPL2
*/


const HBAM_V1_TEXT_DOMAIN = 'hatena_blog_auto';
const HBAM_V1_DIR = 'hatenablog-auto-migrator';
const HBAM_V1_BLOG_ID = "hatenablog_id";
const HBAM_V1_BLOG_FLAG = 'from_hatenablog';
const HBAM_V1_BLOG_HASH = 'hatenablog_hash';

$hbam_v1_option_page_repo = new OptionPage();

$hbam_v1_option = $hbam_v1_option_page_repo->create(
    __('はてなブログ設定', HBAM_V1_TEXT_DOMAIN),
    __('はてなブログ設定', HBAM_V1_TEXT_DOMAIN),
    'manage_options',
    HBAM_V1_TEXT_DOMAIN . "-option",
    function () {
        ?>
        <div class="wrap">
            <h2>はてなブログ設定</h2>
            <form method="post" action="options.php">
                <?php settings_fields(HBAM_V1_TEXT_DOMAIN . "-option"); ?>
                <?php do_settings_sections(HBAM_V1_TEXT_DOMAIN . "-option"); ?>
                <?php submit_button(); ?>
            </form>

        </div>
        <?php
    },
    10
);

$hbam_v1_option->register_option(HBAM_V1_TEXT_DOMAIN . "-hatena-blog-url", "はてなブログのURL", "はてなブログのURLを入力してください", "https://~~~~~~.hatenablog.com");
$hbam_v1_option->register_option_select(HBAM_V1_TEXT_DOMAIN . "-hatena-blog-check", "チェック間隔", "チェックの間隔を選んでください", [
    [
        'label' => '12時間ごと',
        'value' => 'twicedaily',
    ],

    [
        'label' => '1日ごと',
        'value' => 'daily',
    ],
    [
        'label' => '1週間ごと',
        'value' => 'weekly',
    ],
    [
        'label' => '1時間ごと（アクセス制限を受ける可能性がありますのでご注意ください。）',
        'value' => 'hourly',
    ],
]);


$hbam_v1_cron = new Cron(__FILE__, function () {
    $domain = get_option(HBAM_V1_TEXT_DOMAIN . "-hatena-blog-url-option");
    if ($domain) {
        echo "\nStart check hatenablog";
        $posts = get_posts([
            "post_type" => "post",
            "posts_per_page" => 0,
            'meta_key' => HBAM_V1_BLOG_FLAG,
            'meta_value' => 'true'
        ]);
        $posts_keying_by_hatena_id = [];
        foreach ($posts as $post) {
            $hatena_id = get_post_meta($post->ID, HBAM_V1_BLOG_ID, true);
            $posts_keying_by_hatena_id[$hatena_id] = $post;
        }
        $posts = null;


        $client = new \GuzzleHttp\Client();
        $response = $client->request("GET", $domain . "/feed", []);
        $list = json_decode(json_encode(simplexml_load_string($response->getBody()->getContents())), true);
        $blog_category = get_category_by_slug('blog');

        foreach ($list["entry"] as $hatena_post) {
            $blog_id = $hatena_post["id"];
            $blog_title = $hatena_post["title"];
            $blog_content = $hatena_post["content"];
            $blog_summary = $hatena_post["summary"];
            $blog_published = $hatena_post["published"];
            $thumbnail_url = !empty($hatena_post["link"][1]) ? $hatena_post["link"][1]["@attributes"]["href"] : null;

            $post = !empty($posts_keying_by_hatena_id[$blog_id]) ? $posts_keying_by_hatena_id[$blog_id] : null;

            echo "\n\t" . $blog_id . ": " . $blog_title;

            if ($thumbnail_url) {
                $filename = wp_upload_dir()["path"] . "/" . basename($thumbnail_url);
                $file_exists = file_exists($filename);
                if (!$file_exists) {
                    echo "\n\t\t" . "Start download thumbnail...";
                    $tmp = file_get_contents($thumbnail_url);
                    $headers = $http_response_header;
                    $fp = fopen($filename, 'w');
                    fwrite($fp, $tmp);
                    fclose($fp);
                    echo "\n\t\t" . "Finish download thumbnail: " . $filename;
                }
            }

            if ($post) {
                // Exists Post

                $parent_post_id = $post->ID;

                if (get_post_meta($parent_post_id, HBAM_V1_BLOG_HASH, true) !== md5($blog_title . $blog_content)) {
                    echo "\n\t\tDetected update of exists post: " . $post->ID;
                    wp_update_post([
                        'ID' => $post->ID,
                        'post_title' => $blog_title,
                        'post_name' => $blog_title,
                        'post_content' => $blog_content,
                    ]);
                }
            } else {
                // New Post

                echo "\n\t\tDetected new entry: " . $blog_title;
                $post = array(
                    'post_content' => $blog_content,
                    'post_name' => $blog_id,
                    'post_title' => $blog_title,
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_excerpt' => $blog_summary,
                    'post_date' => date("Y-m-d H:i:s", strtotime($blog_published)),
                    'comment_status' => 'closed',
                );
                $parent_post_id = wp_insert_post($post);

                add_post_meta($parent_post_id, HBAM_V1_BLOG_FLAG, "true", false);
                add_post_meta($parent_post_id, HBAM_V1_BLOG_ID, $blog_id, true);
                add_post_meta($parent_post_id, HBAM_V1_BLOG_HASH, md5($blog_title . $blog_content), true);
            }

            if (get_the_post_thumbnail($parent_post_id) === "" && isset($filename) && isset($headers)) {
                foreach ($headers as $header) {
                    if (strpos($header, "Content-Type") !== false) {
                        preg_match("/Content-Type: (.*)/", $header, $matches);
                        $filetype = [
                            "type" => $matches[1]
                        ];
                    }
                }
                if (isset($filetype) && $filetype["type"]) {
                    $wp_upload_dir = wp_upload_dir(date("yyyy/mm", strtotime($blog_published)));
                    $attachment = array(
                        'guid' => $wp_upload_dir['url'] . '/' . basename($filename),
                        'post_mime_type' => $filetype['type'],
                        'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    );

                    $attach_id = wp_insert_attachment($attachment, $filename, $parent_post_id);

                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    set_post_thumbnail($parent_post_id, $attach_id);
                    echo "\n\t\t\tSet thumbnail to post: " . $attach_id . " -> " . $parent_post_id;
                }
            }

            if ($blog_category) {
                wp_set_post_terms($parent_post_id, $blog_category->cat_ID, "category", false);
                echo "\n\t\t\tSet category [blog] to post: " . $parent_post_id;
            }
        }

        echo "\n\nFinished all checks!\n\n";
    }
}, get_option(HBAM_V1_TEXT_DOMAIN . "-hatena-blog-check-option", "twicedaily"), HBAM_V1_TEXT_DOMAIN . "-cron-event-1");


add_action("update_option_" . HBAM_V1_TEXT_DOMAIN . "-hatena-blog-check-option", function () use ($hbam_v1_cron) {
    $hbam_v1_cron->change_term(get_option(HBAM_V1_TEXT_DOMAIN . "-hatena-blog-check-option", "twicedaily"));
});