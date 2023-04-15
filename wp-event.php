<?php

/**
 * Plugin Name: Event 
 * Description: Event plugin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

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
    'query_var'           => true,
    'rewrite'             => array('slug' => 'event'),
    'capability_type'     => 'event',
    'has_archive'         => true,
    'hierarchical'        => false,
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
      'get_callback' => function ($object, $field_name, $request) {
        $image_id = get_post_thumbnail_id($object['id']);
        $image = wp_get_attachment_image_src($image_id, 'full');
        return $image[0];
      },
      'update_callback' => function ($value, $object, $field_name, $request) {
        if (isset($value['id'])) {
          set_post_thumbnail($object->ID, $value['id']);
          $image_url = wp_get_attachment_image_src($value['id'], 'full')[0];
        } elseif (isset($value['url'])) {
          $image_url = $value['url'];
          $image_id = media_sideload_image($image_url, $object->ID);
          if (!is_wp_error($image_id)) {
            set_post_thumbnail($object->ID, $image_id);
          }
        }
        update_post_meta($object->ID, 'event_image_url', $image_url);
        return true;
      },
      'schema' => array(
        'type'       => 'object',
        'properties' => array(
          'id' => array(
            'type'        => 'integer',
            'description' => 'ID of the event image attachment',
            'context'     => array('view', 'edit'),
          ),
          'url' => array(
            'type'        => 'string',
            'description' => 'URL of the event image',
            'context'     => array('view', 'edit'),
          ),
          'width' => array(
            'type'        => 'integer',
            'description' => 'Width of the event image',
            'context'     => array('view', 'edit'),
          ),
          'height' => array(
            'type'        => 'integer',
            'description' => 'Height of the event image',
            'context'     => array('view', 'edit'),
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
  update_post_meta($post_id, 'event_start_date', $start_date);
  update_post_meta($post_id, 'event_end_date', $end_date);
  update_post_meta($post_id, 'event_price', $price);
  update_post_meta($post_id, 'event_location', $location);

  // Handle image upload
  $image_id = 0;
  $image_url = '';
  if (isset($_FILES['event_image'])) {
    $upload = wp_upload_bits($_FILES['event_image']['name'], null, file_get_contents($_FILES['event_image']['tmp_name']));
    if (isset($upload['error']) && $upload['error'] != 0) {
      return new WP_Error('upload_error', __('Error uploading image.', 'text-domain'), array('status' => 400));
    } else {
      $image_id = wp_insert_attachment(array(
        'post_mime_type' => $upload['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', $_FILES['event_image']['name']),
        'post_content' => '',
        'post_status' => 'inherit'
      ), $upload['file']);
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      $attachment_data = wp_generate_attachment_metadata($image_id, $upload['file']);
      set_post_thumbnail($post_id, $image_id);
      wp_update_attachment_metadata($image_id, $attachment_data);
      $image_url = wp_get_attachment_url($image_id);
    }
  }

  $response = array(
    'status' => 'success',
    'message' => 'Event created successfully!',
    'data' => array(
      'event_id' => $post_id,
      'event_title' => $title,
      'event_description' => $description,
      'event_start_date' => $start_date,
      'event_end_date' => $end_date,
      'event_price' => $price,
      'event_location' => $location,
      'image_id' => $image_id,
      'image_url' => $image_url
    )
  );
  return new WP_REST_Response($response, 200);
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

  // Sanitize and validate input data
  $event_title = sanitize_text_field($request->get_param('event_title'));
  $event_description = sanitize_text_field($request->get_param('event_description'));
  $event_start_date = sanitize_text_field($request->get_param('event_start_date'));
  $event_end_date = sanitize_text_field($request->get_param('event_end_date'));
  $event_price = floatval($request->get_param('event_price'));
  $event_location = sanitize_text_field($request->get_param('event_location'));

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
  update_post_meta($post_id, 'event_start_date', $event_start_date);
  update_post_meta($post_id, 'event_end_date', $event_end_date);
  update_post_meta($post_id, 'event_price', $event_price);
  update_post_meta($post_id, 'event_location', $event_location);

  // Handle image upload
  $image_id = 0;
  $image_url = '';
  if (isset($_FILES['event_image'])) {
    $upload = wp_upload_bits($_FILES['event_image']['name'], null, file_get_contents($_FILES['event_image']['tmp_name']));
    if (isset($upload['error']) && $upload['error'] != 0) {
      return new WP_Error('upload_error', __('Error uploading image.', 'text-domain'), array('status' => 400));
    } else {
      $image_id = wp_insert_attachment(array(
        'post_mime_type' => $upload['type'],
        'post_title' => preg_replace('/.[^.]+$/', '', $_FILES['event_image']['name']),
        'post_content' => '',
        'post_status' => 'inherit'
      ), $upload['file']);
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      $attachment_data = wp_generate_attachment_metadata($image_id, $upload['file']);
      set_post_thumbnail($post_id, $image_id);
      wp_update_attachment_metadata($image_id, $attachment_data);
      $image_url = wp_get_attachment_url($image_id);
    }
  }

  $response = array(
    'status' => 'success',
    'message' => 'Event updated successfully!',
    'data' => array(
      'post_title' => $event_title,
      'post_content' => $event_description,
      'event_start_date' => $event_start_date,
      'event_end_date' => $event_end_date,
      'event_price' => $event_price,
      'event_location' => $event_location,
      'image_id' => $image_id,
      'image_url' => $image_url
    )
  );
  return new WP_REST_Response($response, 200);
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