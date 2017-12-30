<?php
/**
 * POSTS_DRUPAL_TO_WP_CLI_IMPORTER
 *
 * this script was implemented to import posts and their categories from Drupal 7 to wordpress 4.8.x
 * categories in drupal were stored by taxonomy module
 * you can put it inside your wordpress directory and run from command line (php import_posts_drupal_to_wordpress_cli.php)
 * to setup script with your data follow "TODO" comments
 *
 * Copyright (C) 2017 Michał Żarnecki
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once('wp-load.php');
require_once('wp-admin/includes/media.php');
require_once('wp-admin/includes/file.php');
require_once('wp-admin/includes/image.php');

//TODO fill Drupal db params
$databasehost = "";
$databasename = "";
$databaseusername ="";
$databasepassword = "";
$connectionDrupal = new mysqli($databasehost, $databasename, $databasepassword, $databaseusername);
$connectionDrupal->set_charset("utf8");

$query = 'SELECT DISTINCT n.nid `id`, FROM_UNIXTIME(n.created) `post_date`, b.body_value `post_content`, n.title `post_title`,'
    . ' IF(n.status = 1, \'publish\', \'private\') `post_status` FROM node n INNER JOIN node_revision r USING(vid)'
    . ' LEFT JOIN field_data_body b ON b.revision_id = n.vid';

$limit = 100;
$offset = 0;
$counter = 1;

while ($posts = $connectionDrupal->query("$query ORDER BY id DESC LIMIT $limit OFFSET $offset;")->fetch_all()) {
    $offset += 100;
    foreach ($posts as $post) {
        [$id, $createdAt, $content, $title, $status] = $post;
        $categories = $connectionDrupal->query('SELECT tid FROM taxonomy_index where nid = ' . $id)->fetch_row();
        $mappedCats = [];
        foreach ($categories as $category) {
            $mappedCat = mapCategory($category);
            $mappedCats[] = $mappedCat;
        }
        $postId = insertPost($title, $content, $createdAt, $mappedCats, $status);
        error_log($id);
        $counter++;
    }
    error_log('DONE: ' . $counter);
}

function mapCategory($categoryDrupal): int
{
    $mapping = [
        //TODO add here your category mapping 288 => 2, ....
    ];
    if (isset($mapping[$categoryDrupal])) {
        return $mapping[$categoryDrupal];
    }
    return 1;
}

function insertPost($title, $content, $date, $categories, $status) {
    global $user_ID;
    $newPost = array(
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => $status,
        'post_date' => $date,
        'post_author' => 1,
        'post_type' => 'post',
        'post_category' => $categories
    );
    return wp_insert_post($newPost);
}

function url_exists($url) {
    if (!$fp = curl_init($url)) return false;
    return true;
}