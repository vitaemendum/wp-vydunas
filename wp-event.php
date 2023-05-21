<?php

define('SHORTINIT', 'true');
require_once dirname(__FILE__) . '/../../../wp-load.php';
global $wpdb;

/**
 * Plugin Name: Event 
 * Description: Event plugin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

require_once ABSPATH . '/wp-admin/includes/file.php';
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Registering custom post type for events
add_action('init', function () {
  register_post_type('event', [
    'labels' => [
      'name' => __('Events'),
      'singular_name' => __('Event')
    ],
    'public' => false,
    'capability_type' => 'post',
    'hierarchical'       => false,
    'menu_position'      => null,
    'show_in_rest'       => true,
    'rest_base'          => 'events',
    'rest_controller_class' => 'WP_REST_Posts_Controller',
    'supports' => ['title', 'author', 'thumbnail']
  ]);
});

function register_event_fields()
{
  $event_fields = array(
    'event_start_date',
    'event_end_date',
    'event_price',
    'event_location',
    'event_image'
  );

  foreach ($event_fields as $field) {
    if ($field === 'event_image') {
      register_rest_field(
        'events',
        $field,
        array(
          'get_callback'   => null,
          'update_callback' => null,
          'schema'         => array(
            'type'         => 'object',
            'description'  => ucfirst(str_replace('_', ' ', $field)),
            'context'      => array('view', 'edit'),
            'properties'   => array(
              'id' => array(
                'type'        => 'integer',
                'description' => 'Attachment ID',
                'context'     => array('view', 'edit'),
                'readonly'    => true,
              ),
              'url' => array(
                'type'        => 'string',
                'description' => 'Attachment URL',
                'context'     => array('view', 'edit'),
                'readonly'    => true,
              ),
              'alt' => array(
                'type'        => 'string',
                'description' => 'Attachment alt text',
                'context'     => array('view', 'edit'),
              ),
            ),
          ),
          'sanitize_callback' => 'rest_sanitize_attachment_id',
        )
      );
    } else {
      register_rest_field(
        'events',
        $field,
        array(
          'schema'          => array(
            'type' => $field === 'event_price' ? 'integer' : 'string',
            'description' => ucfirst(str_replace('_', ' ', $field)),
            'context'     => array('view', 'edit'),
          ),
        )
      );
    }
  }
}
add_action('rest_api_init', 'register_event_fields');

add_action('rest_api_init', function () {
  // Register REST API endpoints for events
  register_rest_route('wp/v2', '/events', array(
    'methods' => Wp_rest_server::READABLE,
    'callback' => 'get_events',
    'permission_callback' => '__return_true',
  ));
  register_rest_route('wp/v2', '/events/(?P<id>\d+)', array(
    'methods' => Wp_rest_server::READABLE,
    'callback' => 'get_event',
    'permission_callback' => '__return_true',
    'args' => array(
      'id' => array(
        'validate_callback' => function ($value) {
          return is_numeric($value);
        }
      ),
    ),
  ));

  register_rest_route('wp/v2', '/events', array(
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'create_event',
    'permission_callback' => '__return_true',
  ));

  register_rest_route('wp/v2', '/events/(?P<id>\d+)', array(
    'methods' => WP_REST_Server::EDITABLE,
    'callback' => 'update_event',
    'permission_callback' => '__return_true',
    'args' => array(
      'id' => array(
        'validate_callback' => function ($value) {
          return is_numeric($value);
        }
      ),
    ),
  ));

  register_rest_route('wp/v2', '/events/(?P<id>\d+)', array(
    'methods' => 'DELETE',
    'callback' => 'delete_event',
    'args' => array(
      'id' => array(
        'validate_callback' => function ($value) {
          return is_numeric($value);
        }
      ),
    ),
    'permission_callback' => '__return_true',
  ));
});

// Get a single event
function get_event($request)
{
  $params = $request->get_params();
  $post_id = $params['id'];

  $post = get_post($post_id);
  if (!$post) {
    return new WP_Error('invalid_event_id', __('Invalid event ID'), ['status' => 404]);
  }

  $file = array(
    'post_type' => 'attachment',
    'post_parent' => $post_id,
  );

  $attachment = get_posts($file);
  if (!$attachment) {
    return new WP_Error('no_attachment', __('No attachment found for this event'), ['status' => 404]);
  }

  $file_url = wp_get_attachment_url($attachment[0]->ID);

  return [
    'id' => $post_id,
    'title' => $post->post_title,
    'content' => $post->post_content,
    'event_start_date' => get_post_meta($post->ID, 'event_start_date', true),
    'event_end_date' => get_post_meta($post->ID, 'event_end_date', true),
    'event_price' => get_post_meta($post->ID, 'event_price', true),
    'event_location' => get_post_meta($post->ID, 'event_location', true),
    'url' => $file_url,
  ];
}

// Get all events
function get_events($request)
{
  $query = new WP_Query([
    'post_type' => 'event',
    'posts_per_page' => -1
  ]);

  // Check if query is successful
  if (!$query || $query->post_count === 0) {
    return new WP_Error('query_failed', __('No events found'), ['status' => 404]);
  }

  $response = [];
  foreach ($query->posts as $post) {
    $attachments = get_posts([
      'post_type' => 'attachment',
      'posts_per_page' => 1,
      'post_parent' => $post->ID,
    ]);

    $attachment_id = $attachments ? $attachments[0]->ID : null;
    $attachment_url = $attachment_id ? wp_get_attachment_url($attachment_id) : null;

    $response[] = [
      'id' => $post->ID,
      'title' => $post->post_title,
      'content' => $post->post_content,
      'event_start_date' => get_post_meta($post->ID, 'event_start_date', true),
      'event_end_date' => get_post_meta($post->ID, 'event_end_date', true),
      'event_price' => get_post_meta($post->ID, 'event_price', true),
      'event_location' => get_post_meta($post->ID, 'event_location', true),
      'attachment_id' => $attachment_id,
      'attachment_url' => $attachment_url,
    ];
  }
  return $response;
}

function create_event($request)
{
  $params = $request->get_params();

  // Validate required fields
  if (empty($params['title'])) {
    return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['content'])) {
    return new WP_Error('missing_content', __('Content is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_start_date'])) {
    return new WP_Error('missing_event_start_date', __('Event start date is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_end_date'])) {
    return new WP_Error('missing_event_end_date', __('Event end date is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_price'])) {
    return new WP_Error('missing_event_price', __('Event price is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_location'])) {
    return new WP_Error('missing_event_location', __('Event location is required.', 'text-domain'), array('status' => 400));
  }

  // Sanitize and validate input data
  $title = sanitize_text_field($params['title']);
  $content = sanitize_text_field($params['content']);
  $event_start_date = sanitize_text_field($params['event_start_date']);
  $event_end_date = sanitize_text_field($params['event_end_date']);
  $event_price = floatval($params['event_price']);
  $event_location = sanitize_text_field($params['event_location']);

  // Validate start and end date
  if (strtotime($event_start_date) < time()) {
    return new WP_Error('invalid_date', __('Event start date cannot be older than the current date.', 'text-domain'), array('status' => 400));
  }

  if (strtotime($event_end_date) < time()) {
    return new WP_Error('invalid_date', __('Event end date cannot be older than the current date.', 'text-domain'), array('status' => 400));
  } elseif (strtotime($event_end_date) < strtotime($event_start_date)) {
    return new WP_Error('invalid_date', __('Event end date cannot be older than the event start date.', 'text-domain'), array('status' => 400));
  }

  // Handle image upload
  $file = $_FILES['event_image'];
  // Check for file upload errors
  if ($file['error'] !== UPLOAD_ERR_OK) {
    return new WP_Error('invalid_upload', __('Invalid file upload'), ['status' => 400]);
  }
  // Validate file type
  $allowed_types = ['jpg', 'png'];
  $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  if (!in_array(strtolower($file_ext), $allowed_types)) {
    return new WP_Error('invalid_file_type', __('Invalid file type'), ['status' => 400]);
  }
  // Process and validate the file upload
  $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
  if ($uploaded_file && !isset($uploaded_file['error'])) {
    $file_name = basename($file['name']);
    $file_type = wp_check_filetype($file_name, null);
    // Create a new post for the document
    $post = [
      'post_title' => $title,
      'post_content' => $content,
      'post_type' => 'event',
      'post_status' => 'publish',
      'meta_input' => [
        'event_start_date' => $event_start_date,
        'event_end_date' => $event_end_date,
        'event_price' => $event_price,
        'event_location' => $event_location,
      ]
    ];
    $post_id = wp_insert_post($post);
    // Attach the uploaded file to the post
    $attachment = [
      'guid' => $uploaded_file['url'],
      'post_mime_type' => $file_type['type'],
      'post_title' => $file_name,
      'post_content' => $content,
      'post_status' => 'inherit',
      'post_parent' => $post_id
    ];
    $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file'], $post_id);
    if (!is_wp_error($attachment_id)) {
      $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
      wp_update_attachment_metadata($attachment_id, $attachment_data);
      return [
        'message' => 'Event created successfully.',
        'data'    => array(
          'id' => $post_id,
          'title' => $title,
          'content' => $content,
          'event_start_date' => $event_start_date,
          'event_end_date' => $event_end_date,
          'event_price' => $event_price,
          'event_location' => $event_location,
          'url' => wp_get_attachment_url($attachment_id),
        ),
      ];
    } else {
      // Delete the post if attachment creation failed
      wp_delete_post($post_id, true);
      return new WP_Error('upload_failed', __('Failed to upload event'), ['status' => 500]);
    }
  } else {
    return new WP_Error('upload_failed', __('Failed to upload event'), ['status' => 500]);
  }
}

// Update event
function update_event($request)
{
  $params = $request->get_params();
  $id = $params['id'];

  // Validate required fields
  if (empty($params['title'])) {
    return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['content'])) {
    return new WP_Error('missing_content', __('Content is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_start_date'])) {
    return new WP_Error('missing_event_start_date', __('Event start date is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_end_date'])) {
    return new WP_Error('missing_event_end_date', __('Event end date is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_price'])) {
    return new WP_Error('missing_event_price', __('Event price is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['event_location'])) {
    return new WP_Error('missing_event_location', __('Event location is required.', 'text-domain'), array('status' => 400));
  }

  // Sanitize and validate input data
  $title = sanitize_text_field($params['title']);
  $content = sanitize_text_field($params['content']);
  $event_start_date = sanitize_text_field($params['event_start_date']);
  $event_end_date = sanitize_text_field($params['event_end_date']);
  $event_price = floatval($params['event_price']);
  $event_location = sanitize_text_field($params['event_location']);

  // Validate start and end date
  if (strtotime($event_start_date) < time()) {
    return new WP_Error('invalid_date', __('Event start date cannot be older than the current date.', 'text-domain'), array('status' => 400));
  }

  if (strtotime($event_end_date) < time()) {
    return new WP_Error('invalid_date', __('Event end date cannot be older than the current date.', 'text-domain'), array('status' => 400));
  } elseif (strtotime($event_end_date) < strtotime($event_start_date)) {
    return new WP_Error('invalid_date', __('Event end date cannot be older than the event start date.', 'text-domain'), array('status' => 400));
  }

  // Get the existing document post
  $post = get_post($id);
  if (!$post) {
    return new WP_Error('event_not_found', __('Event not found'), ['status' => 404]);
  }

  // Check if there's a new file upload
  if (!empty($_FILES['event_image'])) {
    $file = $_FILES['event_image'];
    // Check for file upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
      return new WP_Error('invalid_upload', __('Invalid file upload'), ['status' => 400]);
    }
    // Validate file type
    $allowed_types = ['jpg', 'png'];
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($file_ext), $allowed_types)) {
      return new WP_Error('invalid_file_type', __('Invalid file type'), ['status' => 400]);
    }
    // Process and validate the file upload
    $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
    if ($uploaded_file && !isset($uploaded_file['error'])) {
      $file_name = basename($file['name']);
      $file_type = wp_check_filetype($file_name, null);

      // Update the document post
      $post_data = [
        'ID' => $id,
        'post_title' => $title,
        'post_content' => $content,
        'meta_input' => [
          'event_start_date' => $event_start_date,
          'event_end_date' => $event_end_date,
          'event_price' => $event_price,
          'event_location' => $event_location
        ]
      ];
      wp_update_post($post_data);

      // Delete the existing file attachment
      $attachments = get_attached_media('', $id);
      if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
          wp_delete_attachment($attachment->ID, true);
        }
      }

      // Attach the new file to the post
      $attachment = [
        'guid' => $uploaded_file['url'],
        'post_mime_type' => $file_type['type'],
        'post_title' => $file_name,
        'post_content' => $content,
        'post_status' => 'inherit',
        'post_parent' => $id,

      ];
      $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file'], $id);
      if (!is_wp_error($id)) {
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
      } else {
        // Revert to the old file attachment if there was an error
        $attachment_id = add_attachment_by_url($post->ID, $post->guid);
      }
    }
  } else {
    // Update the document post with new title and meta data only
    $post_data = [
      'ID' => $id,
      'post_title' => $title,
      'post_content' => $content,
      'meta_input' => [
        'event_start_date' => $event_start_date,
        'event_end_date' => $event_end_date,
        'event_price' => $event_price,
        'event_location' => $event_location,
      ]
    ];
    wp_update_post($post_data);
  }

  // Get the updated document data
  $updated_post = get_post($id);
  // Return the updated document data
  $response = [
    'message' => 'Event updated successfully.',
    'data'    => array(
      'id' => $updated_post->ID,
      'title' => $updated_post->post_title,
      'content' => $updated_post->post_content,
      'event_start_date' => $event_start_date,
      'event_end_date' => $event_end_date,
      'event_price' => $event_price,
      'event_location' => $event_location,
    ),
  ];
  return $response;
}

function delete_event($request)
{
  $id = $request->get_param('id');

  $post = get_post($id);
  if (!$post || $post->post_type !== 'event') {
    return new WP_Error('not_found', __('Event not found'), ['status' => 404]);
  }

  // Delete event attachment if it exists
  $attachment = get_posts([
    'post_type' => 'attachment',
    'post_parent' => $id,
  ]);

  $attachment_id = $attachment ? $attachment[0]->ID : null;
  if ($attachment_id) {
    wp_delete_attachment($attachment_id, true);
  }

  $result = wp_delete_post($id, true);

  // Check if post is successfully deleted
  if (!$result || is_wp_error($result)) {
    return new WP_Error('delete_failed', __('Failed to delete event'), ['status' => 500]);
  }
  return [
    'id' => $id,
    'message' => __('Event deleted successfully')
  ];
}
