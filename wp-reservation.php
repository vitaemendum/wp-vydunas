<?php

/**
 * Plugin Name: reservation 
 * Description: Reservations plugin 
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

function register_Reservation_post_type()
{
    $labels = array(
        'name'               => __('Reservations'),
        'singular_name'      => __('Reservation'),
        'add_new'            => __('Add New Reservation'),
        'add_new_item'       => __('Add New Reservation'),
        'edit_item'          => __('Edit Reservation'),
        'new_item'           => __('New Reservation'),
        'view_item'          => __('View Reservation'),
        'search_items'       => __('Search Reservation'),
        'not_found'          => __('No Reservations found'),
        'not_found_in_trash' => __('No Reservations found in trash'),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'reservation'),
        'capability_type'     => 'reservation',
        'has_archive'         => true,
        'hierarchical'        => false,
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        'show_in_rest'          => true,
        'rest_base'             => 'Reservations',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'status' => 'reservation',
    );

    register_post_type('reservation', $args);
}
add_action('init', 'register_Reservation_post_type');

function register_Reservation_fields()
{
    $Reservation_fields = array(
        'reservation_book_id',
        'reservation_date_reserved',
        'reservation_date_due'
    );

    foreach ($Reservation_fields as $field) {

        register_rest_field(
            'Reservations',
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
add_action('rest_api_init', 'register_Reservation_fields');

add_action('rest_api_init', function () {
    // Register REST API endpoints for events
    register_rest_route('wp/v2', '/Reservations', array(
        'methods' => 'GET',
        'callback' => 'get_all_Reservations',
    ));
    register_rest_route('wp/v2', '/Reservations/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_single_Reservation',
    ));

    register_rest_route('wp/v2', '/Reservations', array(
        'methods' => 'POST',
        'callback' => 'create_Reservation',
        'permission_callback' => function () {
            return current_user_can('publish_posts');
        }

    ));
    register_rest_route('wp/v2', '/Reservations/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'update_Reservation',
        'permission_callback' => function () {
            return current_user_can('publish_posts');
        }
    ));
    register_rest_route('wp/v2', '/Reservations/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'delete_Reservation',
        'permission_callback' => function () {
            return current_user_can('publish_posts');
        }
    ));
});

// CREATE reservation
function create_Reservation($request)
{
    $Reservation_data = $request->get_params();

    // Validate required fields
    if (empty($Reservation_data['reservation_book_id']) || empty($Reservation_data['reservation_date_reserved']) || empty($Reservation_data['reservation_date_due'])) {
        return new WP_Error('missing_fields', __('Missing required fields.', 'text-domain'), array('status' => 400));
    }

    // Sanitize and validate input data
    $reservation_book_id = intval($Reservation_data['reservation_book_id']);
    $reservation_date_due = sanitize_text_field($Reservation_data['reservation_date_due']);
    $reservation_date_reserved = sanitize_text_field($Reservation_data['reservation_date_reserved']);

    // Insert post
    $post_data = array(
        'post_type' => 'reservation',
        'post_status' => 'publish',
    );

    $post_id = wp_insert_post($post_data);
    update_post_meta($post_id, 'reservation_book_id', $reservation_book_id);
    update_post_meta($post_id, 'reservation_date_due', $reservation_date_due);
    update_post_meta($post_id, 'reservation_date_reserved', $reservation_date_reserved);

    $response = array(
        'status' => 'success',
        'message' => 'reservation created successfully!',
        'data' => array(
            'Reservation_id' => $post_id,
            'reservation_book_id' => $reservation_book_id,
            'reservation_date_due' => $reservation_date_due,
            'reservation_date_reserved' => $reservation_date_reserved
        )
    );

    return new WP_REST_Response($response, 200);
}

// READ reservation
function get_Reservation_data($id)
{
    $reservation = get_post($id);

    if (!$reservation || $reservation->post_type !== 'reservation') {
        $response = new WP_Error('no_Reservation', 'reservation not found', array('status' => 404));
        return $response;
    }

    $Reservation_data = array(
        'id' => $reservation->ID,
        'reservation_book_id' => get_post_meta($reservation->ID, 'reservation_book_id', true),
        'reservation_date_due' => get_post_meta($reservation->ID, 'reservation_date_due', true),
        'reservation_date_reserved' => get_post_meta($reservation->ID, 'reservation_date_reserved', true)
    );

    return $Reservation_data;
}

function get_all_Reservations($request)
{
    $args = array(
        'post_type' => 'reservation',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $Reservations = get_posts($args);
    $data = array();

    foreach ($Reservations as $reservation) {
        $response = get_Reservation_data($reservation->ID);
        if (is_wp_error($response)) {
            return $response;
        }
        $data[] = $response;
    }

    if (empty($data)) {
        $response = new WP_Error('no_Reservations', 'No Reservations found', array('status' => 404));
    } else {
        $response = new WP_REST_Response($data, 200);
    }

    return $response;
}

// Get a single event
function get_single_reservation($request)
{
    $id = (int) $request['id'];

    if (!$id) {
        $response = new WP_Error('invalid_id', 'Invalid event ID', array('status' => 400));
        return $response;
    }

    $reservation = get_post($id);

    if (!$reservation || $reservation->post_type !== 'reservation') {
        $response = new WP_Error('no_Reservation', 'reservation not found', array('status' => 404));
        return $response;
    }

    $data = get_Reservation_data($id);

    if (is_wp_error($data)) {
        return $data;
    }

    $response = new WP_REST_Response($data, 200);

    return $response;
}

function delete_Reservation($request)
{
    $id = (int) $request['id'];

    if (!$id) {
        $response = new WP_Error('invalid_id', 'Invalid event ID', array('status' => 400));
        return $response;
    }

    $reservation = get_post($id);

    if (!$reservation || $reservation->post_type !== 'reservation') {
        $response = new WP_Error('no_Reservation', 'reservation not found', array('status' => 404));
        return $response;
    }

    // Delete event
    $result = wp_delete_post($id, true);

    if (!$result) {
        $response = new WP_Error('delete_failed', 'reservation deletion failed', array('status' => 500));
        return $response;
    }

    $response = new WP_REST_Response(null, 204);

    return $response;
}

function update_Reservation($request)
{
    $id = $request->get_param('id');
    $reservation = get_post($id);

    if (!$reservation || $reservation->post_type !== 'reservation') {
        $response = new WP_Error('no_reservation', 'reservation not found', array('status' => 404));
        return $response;
    }

    $params = $request->get_params();
    $reservation_book_id = isset($params['reservation_user_id']) ? sanitize_text_field($params['reservation_user_id']) : '';
    $reservation_date_due = isset($params['reservation_date_due']) ? sanitize_text_field($params['reservation_date_due']) : '';
    $reservation_date_reserved = isset($params['reservation_date_reserved']) ? sanitize_text_field($params['reservation_date_reserved']) : '';

    if (empty($reservation_book_id) || empty($reservation_date_due) || empty($reservation_date_reserved)) {
        $response = new WP_Error('missing_parameters', 'One or more required parameters are missing', array('status' => 400));
        return $response;
    }

    $date = DateTime::createFromFormat('Y-m-d', $reservation_date_due);

    if (!$date || $date->format('Y-m-d') !== $reservation_date_due) {
        $response = new WP_Error('invalid_date', 'Invalid reservation due date', array('status' => 400));
        return $response;
    }

    $post_data = array(
        'ID'           => $id,
        'post_type'    => 'reservation',
        'post_status'  => 'publish',
    );

    $post_id = wp_update_post($post_data);

    if (is_wp_error($post_id)) {
        return new WP_Error('update_error', $post_id->get_error_message(), array('status' => 500));
    }

    // Update event meta data
    update_post_meta($post_id, 'reservation_book_id', $reservation_book_id);
    update_post_meta($post_id, 'reservation_date_due', $reservation_date_due);
    update_post_meta($post_id, 'reservation_date_reserved', $reservation_date_reserved);

    $response = array(
        'status' => 'success',
        'message' => 'Reservation updated successfully!',
        'data' => array(
            'reservation_book_id' => get_post_meta($reservation->ID, 'reservation_book_id', true),
            'reservation_date_due' => get_post_meta($reservation->ID, 'reservation_date_due', true),
            'reservation_date_reserved' => get_post_meta($reservation->ID, 'reservation_date_reserved', true)
        )
    );
    return new WP_REST_Response($response, 200);
}
