<?php

define('SHORTINIT', 'true');
require_once dirname(__FILE__) . '/../../../wp-load.php';
global $wpdb;

/**
 * Plugin Name: Information 
 * Description: Information plugin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

require_once ABSPATH . '/wp-admin/includes/file.php';
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Registering custom post type for documents
add_action('init', function () {
    register_post_type('information', [
        'labels' => [
            'name' => __('Information'),
            'singular_name' => __('information')
        ],
        'public' => false,
        'capability_type' => 'post',
        'hierarchical'       => false,
        'menu_position'      => null,
        'show_in_rest'       => true,
        'rest_base'          => 'information',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'supports' => ['title', 'author']
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/information', [
        'methods' => Wp_rest_server::READABLE,
        'callback' => 'get_all_information',
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/information/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'get_information',
        'args' => array(
            'id' => array(
                'validate_callback' => function ($value) {
                    return is_numeric($value);
                }
            ),
        ),
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/information', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'create_information',
        'permission_callback' => function ($request) {
            return current_user_can('edit_posts');
        }
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/information/(?P<id>\d+)', [
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => 'update_information',
        'args' => array(
            'id' => array(
                'validate_callback' => function ($value) {
                    return is_numeric($value);
                }
            ),
        ),
        'permission_callback' => function ($request) {
            return current_user_can('edit_posts');
        }
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/information/(?P<id>\d+)', [
        'methods' => WP_REST_Server::DELETABLE,
        'callback' => 'delete_information',
        'args' => array(
            'id' => array(
                'validate_callback' => function ($value) {
                    return is_numeric($value);
                }
            ),
        ),
        'permission_callback' => function ($request) {
            return current_user_can('delete_posts');
        }
    ]);
});

function get_information($request)
{
    $params = $request->get_params();
    $post_id = $params['id'];

    $post = get_post($post_id);
    if (!$post) {
        return new WP_Error('invalid_information_id', __('Invalid information ID'), ['status' => 404]);
    }

    return [
        'id' => $post_id,
        'title' => $post->post_title,
        'content' => $post->post_content,
    ];
}

function get_all_information($request)
{
    $query = new WP_Query([
        'post_type' => 'information',
        'posts_per_page' => -1
    ]);

    // Check if query is successful
    if (!$query || $query->post_count === 0) {
        return new WP_Error('query_failed', __('No information list found'), ['status' => 404]);
    }

    $response = [];
    foreach ($query->posts as $post) {
        $response[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
        ];
    }

    return $response;
}

function create_information($request)
{
    $params = $request->get_params();

    // Validate required fields
    if (empty($params['title'])) {
        return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['content'])) {
        return new WP_Error('missing_information_content', __('Content is required.', 'text-domain'), array('status' => 400));
    }

    // Sanitize and validate input data
    $title = sanitize_text_field($params['title']);
    $content = sanitize_text_field($params['content']);

    $post = array(
        'post_title' => $title,
        'post_content' => $content,
        'post_type' => 'information',
        'post_status' => 'publish'
    );
    $post_id = wp_insert_post($post, true);

    // Handle post creation errors
    if (is_wp_error($post_id)) {
        return new WP_Error('create_error', __('Failed to create information post.', 'text-domain'), array('status' => 500));
    }

    return array(
        'message' => 'Information created successfully.',
        'data' => array(
            'id' => $post_id,
            'title' => $title,
            'content' => $content,
        )
    );
}

function update_information($request)
{
    $params = $request->get_params();
    $id = $params['id'];

    $post = get_post($id);
    if (!$post) {
        return new WP_Error('information_not_found', __('Information not found'), ['status' => 404]);
    }

    // Validate required fields
    if (empty($params['title'])) {
        return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['content'])) {
        return new WP_Error('missing_information_content', __('Content is required.', 'text-domain'), array('status' => 400));
    }

    // Sanitize and validate input data
    $title = sanitize_text_field($params['title']);
    $content = sanitize_text_field($params['content']);

    $post_data = [
        'ID' => $id,
        'post_title' => $title,
        'post_content' => $content,
    ];
    wp_update_post($post_data);

    // Get the updated lesson data
    $updated_post = get_post($id);
    // Return the updated lesson data
    $response = [
        'message' => 'Information updated successfully.',
        'data'    => array(
            'id' => $updated_post->ID,
            'title' => $updated_post->post_title,
            'content' => $updated_post->post_content,
        ),
    ];
    return $response;
}

function delete_information($request)
{
    $id = $request->get_param('id');

    $post = get_post($id);
    if (!$post || $post->post_type !== 'information') {
        return new WP_Error('not_found', __('Information not found'), ['status' => 404]);
    }

    $result = wp_delete_post($id, true);

    // Check if post is successfully deleted
    if (!$result || is_wp_error($result)) {
        return new WP_Error('delete_failed', __('Failed to delete information'), ['status' => 500]);
    }
    return [
        'id' => $id,
        'message' => __('Information deleted successfully')
    ];
}
