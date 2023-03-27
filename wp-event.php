<?php

/**
 * Plugin Name: Event 
 * Description: Event 
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

use WP_REST_Response;
use WP_Error;

function create_event_post_type()
{
  $labels = array(
    'name'               => __('Events'),
    'singular_name'      => __('Event'),
    'add_new'            => __('Add New Event'),
    'add_new_item'       => __('Add New Event'),
    'edit_item'          => __('Edit Event'),
    'new_item'           => __('New Event'),
    'view_item'          => __('View Event'),
    'search_items'       => __('Search Events'),
    'not_found'          => __('No events found'),
    'not_found_in_trash' => __('No events found in trash'),
  );

  $args = array(
    'labels'              => $labels,
    'public'              => true,
    'show_ui'             => true,
    'show_in_menu'        => true,
    'query_var'           => true,
    'rewrite'             => array('slug' => 'event'),
    'capability_type'     => 'event',
    'has_archive'         => true,
    'hierarchical'        => false,
    'menu_position'       => 5,
    'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
    'show_in_rest'          => true,
    'rest_base'             => 'events',
    'rest_controller_class' => 'WP_REST_Posts_Controller',
    'status' => 'event',
  );

  register_post_type('event', $args);
}
add_action('init', 'create_event_post_type');

add_action('rest_api_init', function () {

  // Register custom fields for events
  register_rest_field(
    'events',
    'event_title',
    array(
      'get_callback'    => 'get_event_title',
      'update_callback' => 'update_event_title',
      'schema'          => array(
        'type'        => 'string',
        'description' => 'Event title',
        'context'     => array('view', 'edit'),
      ),
    )
  );
  register_rest_field(
    'events',
    'event_description',
    array(
      'get_callback'    => 'get_event_description',
      'update_callback' => 'update_event_description',
      'schema'          => array(
        'type'        => 'string',
        'description' => 'Event description',
        'context'     => array('view', 'edit'),
      ),
    )
  );
  register_rest_field(
    'events',
    'event_start_date',
    array(
      'get_callback'    => 'get_event_start_date',
      'update_callback' => 'update_event_start_date',
      'schema'          => array(
        'type'        => 'string',
        'description' => 'Event start date',
        'context'     => array('view', 'edit'),
      ),
    )
  );
  register_rest_field(
    'events',
    'event_end_date',
    array(
      'get_callback'    => 'get_event_end_date',
      'update_callback' => 'update_event_end_date',
      'schema'          => array(
        'type'        => 'string',
        'description' => 'Event end date',
        'context'     => array('view', 'edit'),
      ),
    )
  );
  register_rest_field(
    'events',
    'event_price',
    array(
      'get_callback'    => 'get_event_price',
      'update_callback' => 'update_event_price',
      'schema'          => array(
        'type'        => 'number',
        'description' => 'Event price',
        'context'     => array('view', 'edit'),
      ),
    )
  );
  register_rest_field(
    'events',
    'event_location',
    array(
      'get_callback'    => 'get_event_location',
      'update_callback' => 'update_event_location',
      'schema'          => array(
        'type'        => 'string',
        'description' => 'Event location',
        'context'     => array('view', 'edit'),
      ),
    )
  );
  register_rest_field(
    'events',
    'event_image',
    array(
      'get_callback'    => function ($object, $field_name, $request) {
        $image_id = get_post_thumbnail_id($object['id']);
        $image = wp_get_attachment_image_src($image_id, 'full');
        return $image[0];
      },
      'update_callback' => null,
      'schema' => array(
        'type' => 'object',
        'properties' => array(
          'url' => array(
            'type' => 'string',
            'description' => 'URL of the event image',
            'context' => array('view', 'edit'),
          ),
          'width' => array(
            'type' => 'integer',
            'description' => 'Width of the event image',
            'context' => array('view', 'edit'),
          ),
          'height' => array(
            'type' => 'integer',
            'description' => 'Height of the event image',
            'context' => array('view', 'edit'),
          ),
        ),
      ),
    )
  );


  // Register REST API endpoints for events
  register_rest_route('wp/v2', '/events', array(
    'methods' => 'GET',
    'callback' => 'get_all_events',
  ));
  register_rest_route('wp/v2', '/events/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'get_single_event',
  ));


  register_rest_route('wp/v2', '/events', array(
    'methods' => 'POST',
    'callback' => 'create_event',
    'permission_callback' => function () {
      return current_user_can('publish_posts');
    }
  ));
  register_rest_route('wp/v2', '/events/(?P<id>\d+)', array(
    'methods' => 'PUT',
    'callback' => 'update_event',
    'permission_callback' => function () {
      return current_user_can('publish_posts');
    }
  ));
  register_rest_route('wp/v2', '/events/(?P<id>\d+)', array(
    'methods' => 'DELETE',
    'callback' => 'delete_event',
    'permission_callback' => function () {
      return current_user_can('publish_posts');
    }
  ));
});

// Get all events
function get_all_events($request)
{
  $args = array(
    'post_type' => 'event',
    'post_status' => 'publish',
    'posts_per_page' => -1,
  );
  $events = get_posts($args);
  $data = array();

  foreach ($events as $event) {
    $response = get_event_data($event->ID);
    if (is_wp_error($response)) {
      return $response;
    }
    $data[] = $response;
  }

  if (empty($data)) {
    $response = new WP_Error('no_events', 'No events found', array('status' => 404));
  } else {
    $response = new WP_REST_Response($data, 200);
  }

  return $response;
}

// Get a single event
function get_single_event($request)
{
  $id = (int) $request['id'];

  if (!$id) {
    $response = new WP_Error('invalid_id', 'Invalid event ID', array('status' => 400));
    return $response;
  }

  $event = get_post($id);

  if (!$event || $event->post_type !== 'event') {
    $response = new WP_Error('no_event', 'Event not found', array('status' => 404));
    return $response;
  }

  $data = get_event_data($id);

  if (is_wp_error($data)) {
    return $data;
  }

  $response = new WP_REST_Response($data, 200);

  return $response;
}

// Get event data
function get_event_data($id)
{
  $event = get_post($id);

  if (!$event || $event->post_type !== 'event') {
    $response = new WP_Error('no_event', 'Event not found', array('status' => 404));
    return $response;
  }

  $event_data = array(
    'id' => $event->ID,
    'event_title' => $event->post_title,
    'event_description' => $event->post_content,
    'event_start_date' => get_post_meta($event->ID, 'event_start_date', true),
    'event_end_date' => get_post_meta($event->ID, 'event_end_date', true),
    'event_price' => get_post_meta($event->ID, 'event_price', true),
    'event_location' => get_post_meta($event->ID, 'event_location', true),
    'event_image' => get_the_post_thumbnail_url($event->ID, 'full')
  );

  return $event_data;
}

function create_event($request)
{
  $event_data = $request->get_params();

  // Validate required fields
  if (empty($event_data['event_title']) || empty($event_data['event_description']) || empty($event_data['event_start_date']) || empty($event_data['event_end_date']) || empty($event_data['event_price']) || empty($event_data['event_location'])) {
    return new WP_Error('missing_fields', __('Missing required fields.', 'text-domain'), array('status' => 400));
  }

  // Sanitize and validate input data
  $title = sanitize_text_field($event_data['event_title']);
  $description = sanitize_text_field($event_data['event_description']);
  $start_date = sanitize_text_field($event_data['event_start_date']);
  $end_date = sanitize_text_field($event_data['event_end_date']);
  $price = floatval($event_data['event_price']);
  $location = sanitize_text_field($event_data['event_location']);

  // Validate input data
  if (strlen($title) < 5) {
    return new WP_Error('invalid_title', __('Title should be at least 5 characters long.', 'text-domain'), array('status' => 400));
  }

  if (strlen($description) < 10) {
    return new WP_Error('invalid_description', __('Description should be at least 10 characters long.', 'text-domain'), array('status' => 400));
  }

  if (!is_numeric($price)) {
    return new WP_Error('invalid_price', __('Price should be a number.', 'text-domain'), array('status' => 400));
  }

  // Validate start and end date
  $current_date = date('Y-m-d');
  if ($start_date < $current_date || $end_date < $current_date) {
    return new WP_Error('invalid_date', __('Event date cannot be older than the current date.', 'text-domain'), array('status' => 400));
  }

  // Insert post
  $post_data = array(
    'post_title' => $title,
    'post_content' => $description,
    'post_type' => 'event',
    'post_status' => 'publish',
  );

  $post_id = wp_insert_post($post_data);

  if (is_wp_error($post_id)) {
    return new WP_Error('insert_error', __('Error inserting post.', 'text-domain'), array('status' => 500));
  }
}

// Update event
function update_event($request)
{
  $id = (int) $request['id'];
  $event = get_post($id);

  // Check if event exists
  if (!$event || $event->post_type !== 'event') {
    return new WP_Error('no_event', __('Invalid event ID', 'text-domain'), array('status' => 404));
  }

  $event_data = $request->get_params();
  var_dump($event_data);
  // Validate required fields
  if (empty($event_data['event_title']) || empty($event_data['event_description']) || empty($event_data['event_start_date']) || empty($event_data['event_end_date']) || empty($event_data['event_price']) || empty($event_data['event_location'])) {
    return new WP_Error('missing_fields', __('Missing required fields.', 'text-domain'), array('status' => 400));
  }

  // Sanitize and validate input data
  $event_title = sanitize_text_field($event_data['event_title']);
  $event_description = sanitize_text_field($event_data['event_description']);
  $event_start_date = sanitize_text_field($event_data['event_start_date']);
  $event_end_date = sanitize_text_field($event_data['event_end_date']);
  $event_price = floatval($event_data['event_price']);
  $event_location = sanitize_text_field($event_data['event_location']);

  // Validate input data
  if (strlen($event_title) < 5) {
    return new WP_Error('invalid_title', __('Title should be at least 5 characters long.', 'text-domain'), array('status' => 400));
  }

  if (strlen($event_description) < 10) {
    return new WP_Error('invalid_description', __('Description should be at least 10 characters long.', 'text-domain'), array('status' => 400));
  }

  if (!is_numeric($event_price)) {
    return new WP_Error('invalid_price', __('Price should be a number.', 'text-domain'), array('status' => 400));
  }

  // Validate start and end date
  $current_date = date('Y-m-d');
  if ($event_start_date < $current_date || $event_end_date < $current_date) {
    return new WP_Error('invalid_date', __('Event date cannot be older than the current date.', 'text-domain'), array('status' => 400));
  }

  // Handle image file if uploaded
  $file = $request->get_file_params();
  if (!empty($file['file'])) {
    // Delete old image if exists
    $old_image = get_post_meta($id, 'event_image', true);
    if (!empty($old_image)) {
      wp_delete_attachment($old_image, true);
    }

    // Upload new image
    $attachment_id = media_handle_upload('file', $id);
    if (is_wp_error($attachment_id)) {
      return new WP_Error('upload_error', $attachment_id->get_error_message(), array('status' => 500));
    }

    // Update event image meta data
    update_post_meta($id, 'event_image', $attachment_id);
  }

  // Update post
  $post_data = array(
    'ID'           => $id,
    'post_title'   => $event_title,
    'post_content' => $event_description,
    'post_type'    => 'event',
    'post_status'  => 'publish',
  );

  $post_id = wp_update_post($post_data);

  if (is_wp_error($post_id)) {
    return new WP_Error('update_error', $post_id->get_error_message(), array('status' => 500));
  }

  // Update event meta data
  update_post_meta($id, 'event_start_date', $event_start_date);
  update_post_meta($id, 'event_end_date', $event_end_date);
  update_post_meta($id, 'event_price', $event_price);
  update_post_meta($id, 'event_location', $event_location);

  // Return updated event data
  $updated_event = get_post($id);
  $response = array(
    'event_id' => $updated_event->ID,
    'event_title' => $updated_event->post_title,
    'event_description' => $updated_event->post_content,
    'event_start_date' => get_post_meta($id, 'event_start_date', true),
    'event_end_date' => get_post_meta($id, 'event_end_date', true),
    'event_price' => get_post_meta($id, 'event_price', true),
    'event_location' => get_post_meta($id, 'event_location', true),
    'event_image' => get_post_meta($id, 'event_image', true),
  );

  return $response;
}

function delete_event($request)
{
  $id = (int) $request['id'];

  if (!$id) {
    $response = new WP_Error('invalid_id', 'Invalid event ID', array('status' => 400));
    return $response;
  }

  $event = get_post($id);

  if (!$event || $event->post_type !== 'event') {
    $response = new WP_Error('no_event', 'Event not found', array('status' => 404));
    return $response;
  }

  // Delete event image if it exists
  $image_id = get_post_thumbnail_id($event->ID);
  if ($image_id) {
    wp_delete_attachment($image_id, true);
  }

  // Delete event
  $result = wp_delete_post($id, true);

  if (!$result) {
    $response = new WP_Error('delete_failed', 'Event deletion failed', array('status' => 500));
    return $response;
  }

  $response = new WP_REST_Response(null, 204);

  return $response;
}
