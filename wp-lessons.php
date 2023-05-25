<?php

register_activation_hook(__FILE__, 'create_lesson_table');

function create_lesson_table()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'lessons';

    $sql = "CREATE TABLE $table_name (
           id mediumint(9) NOT NULL AUTO_INCREMENT,
           lesson_name varchar(255) NOT NULL,
           teacher_id mediumint(9) NOT NULL,
           cabinet varchar(255) NOT NULL,
           PRIMARY KEY (id)
       ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    dbDelta($sql);
}
