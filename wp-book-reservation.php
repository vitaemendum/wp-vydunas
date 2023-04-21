<?php

/**
 * Plugin Name: book reservation 
 * Description: Book reservations plugin 
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

require_once ABSPATH . '/wp-admin/includes/file.php';
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Registering custom post type for documents
add_action('init', function () {
    register_post_type('reservation', [
        'labels' => [
            'name' => __('Reservations'),
            'singular_name' => __('Reservation')
        ],
        'public' => false,
        'capability_type' => 'post',
        'hierarchical'       => false,
        'menu_position'      => null,
        'show_in_rest'       => true,
        'rest_base'          => 'reservations',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'supports' => ['title', 'author']
    ]);
});

function register_reservation_fields()
{
    $reservation_fields = array(
        'reservation_book_id',
        'reservation_status',
        'reservation_date',
    );

    foreach ($reservation_fields as $field) {
        register_rest_field(
            'reservations',
            $field,
            array(
                'schema'          => array(
                    'type' => $field === 'reservation_book_id' ? 'integer' : 'string',
                    'description' => ucfirst(str_replace('_', ' ', $field)),
                    'context'     => array('view', 'edit'),
                ),
            )
        );
    }
}
add_action('rest_api_init', 'register_reservation_fields');

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/reservations', [
        'methods' => Wp_rest_server::READABLE,
        'callback' => 'get_reservations',
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/reservations/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'get_reservation',
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
    register_rest_route('wp/v2', '/reservations', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'create_reservation',
        'permission_callback' => function ($request) {
            return current_user_can('edit_posts');
        }
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('wp/v2', '/reservations/(?P<id>\d+)', [
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => 'update_reservation',
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
    register_rest_route('wp/v2', '/reservations/(?P<id>\d+)', [
        'methods' => WP_REST_Server::DELETABLE,
        'callback' => 'delete_reservation',
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

function get_reservation($request)
{
    $params = $request->get_params();
    $post_id = $params['id'];

    $post = get_post($post_id);
    if (!$post) {
        return new WP_Error('invalid_lesson_id', __('Invalid reservation ID'), ['status' => 404]);
    }
    
    return [
        'id' => $post_id,
        'title' => $post->post_title,
        'user_id' => $post->post_author,
        'reservation_book_id' => get_post_meta($post_id, 'reservation_book_id', true),
        'reservation_status' => get_post_meta($post_id, 'reservation_status', true),
        'reservation_date' => get_post_meta($post_id, 'reservation_date', true)
    ];
}

function get_reservations($request)
{
    $query = new WP_Query([
        'post_type' => 'reservation',
        'posts_per_page' => -1
    ]);

    // Check if query is successful
    if (!$query || $query->post_count === 0) {
        return new WP_Error('query_failed', __('No reservations found'), ['status' => 404]);
    }

    $response = [];
    foreach ($query->posts as $post) {
        $response[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'user_id' => $post->post_author,
            'reservation_book_id' => get_post_meta($post->ID, 'reservation_book_id', true),
            'reservation_status' => get_post_meta($post->ID, 'reservation_status', true),
            'reservation_date' => get_post_meta($post->ID, 'reservation_date', true)
        ];
    }

    return $response;
}

function create_reservation($request)
{
    $params = $request->get_params();

    if (empty($params['reservation_book_id'])) {
        return new WP_Error('missing_reservation_book_id', __('Reservation book id is required.', 'text-domain'), array('status' => 400));
    }

    // Sanitize and validate input data
    $reservation_book_id = sanitize_text_field($params['reservation_book_id']);

    $reservation_reserved_status = true;
    $book = get_post($reservation_book_id);
    $book_quantity = get_post_meta($book->ID, 'book_quantity', true);
    if ($book_quantity < 1) {
        return new WP_Error('Book_quantity_below_0', __('Book quantity is below 0.', 'text-domain'), array('status' => 400));
    } else {
        update_post_meta($book->ID, 'book_quantity', $book_quantity - 1);
    }

    $post = array(
        'post_title' => $book->post_title,
        'post_type' => 'reservation',
        'post_status' => 'publish'
    );
    $post_id = wp_insert_post($post, true);

    // Handle post creation errors
    if (is_wp_error($post_id)) {
        return new WP_Error('create_error', __('Failed to create reservation post.', 'text-domain'), array('status' => 500));
    }

    // Add metadata
    update_post_meta($post_id, 'reservation_book_id', $reservation_book_id);
    update_post_meta($post_id, 'reservation_status', $reservation_reserved_status);
    update_post_meta($post_id, 'reservation_date', current_time('Y-m-d H:i:s'));

    return array(
        'message' => 'Lesson created successfully.',
        'data' => array(
            'id' => $post_id,
            'title' => $book->post_title,
            'reservation_book_id' => $reservation_book_id,
            'reservation_status' => $reservation_reserved_status,
            'reservation_date' => current_time('Y-m-d H:i:s'),
        )
    );
}

function update_reservation($request)
{
    $params = $request->get_params();
    $id = $params['id'];
    
    // Get the existing lesson post
    $post = get_post($id);
    if (!$post) {
        return new WP_Error('reservation_not_found', __('Reservation not found'), ['status' => 404]);
    }    

    // Validate required fields
    if (empty($params['reservation_book_id'])) {
        return new WP_Error('missing_reservation_book_id', __('Reservation book id is required.', 'text-domain'), array('status' => 400));
    }
    if (!isset($params['reservation_status'])) {
        return new WP_Error('missing_reservation_status', __('Reservation status is required.', 'text-domain'), array('status' => 400));
    }
    // Sanitize and validate input data
    $reservation_book_id = sanitize_text_field($params['reservation_book_id']);
    $reservation_status = boolval($params['reservation_status']);

    $old_book_id = get_post_meta($post->ID, 'reservation_book_id', true);
    $old_book = get_post($old_book_id);
    $old_book_quantity = get_post_meta($old_book->ID, 'book_quantity', true);
    update_post_meta($old_book->ID, 'book_quantity', $old_book_quantity);

    $book = get_post($reservation_book_id);
    $book_quantity = get_post_meta($book->ID, 'book_quantity', true);
    if ($book_quantity < 1) {
        return new WP_Error('Book_quantity_below_0', __('Book quantity is below 0.', 'text-domain'), array('status' => 400));
    } else {
        update_post_meta($book->ID, 'book_quantity', $book_quantity - 1);
    }

    // $post_data = [
    //     'ID' => $id,
    //     'post_title' => $title,
    //     'meta_input' => [
    //         'lesson_teacher' => $lesson_teacher,
    //         'lesson_day_of_week' => $lesson_day_of_week,
    //         'lesson_start_time' => $lesson_start_time,
    //         'lesson_end_time' => $lesson_end_time,
    //         'lesson_room_number' => $lesson_room_number
    //     ]
    // ];
    // wp_update_post($post_data);

    // // Get the updated lesson data
    // $updated_post = get_post($id);
    // // Return the updated lesson data
    // $response = [
    //     'message' => 'Document updated successfully.',
    //     'data'    => array(
    //         'id' => $updated_post->ID,
    //         'title' => $updated_post->post_title,
    //         'lesson_teacher' => $lesson_teacher,
    //         'lesson_day_of_week' => $lesson_day_of_week,
    //         'lesson_start_time' => $lesson_start_time,
    //         'lesson_end_time' => $lesson_end_time,
    //         'lesson_room_number' => $lesson_room_number
    //     ),
    // ];
    // return $response;
}

function delete_reservation($request)
{
    $id = $request->get_param('id');

    $post = get_post($id);
    if (!$post || $post->post_type !== 'reservation') {
        return new WP_Error('not_found', __('Reservation not found'), ['status' => 404]);
    }
    

    $result = wp_delete_post($id, true);

    // Check if post is successfully deleted
    if (!$result || is_wp_error($result)) {
        return new WP_Error('delete_failed', __('Failed to delete reservation'), ['status' => 500]);
    }
    return [
        'id' => $id,
        'message' => __('reservation deleted successfully')
    ];
}
