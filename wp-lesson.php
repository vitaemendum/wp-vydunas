<?php

/**
 * Plugin Name: Lesson 
 * Description: Lesson plugin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

require_once ABSPATH . '/wp-admin/includes/file.php';
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Registering custom post type for documents
add_action('init', function () {
  register_post_type('lesson', [
    'labels' => [
      'name' => __('Lessons'),
      'singular_name' => __('Lesson')
    ],
    'public' => false,
    'capability_type' => 'post',
    'hierarchical'       => false,
    'menu_position'      => null,
    'show_in_rest'       => true,
    'rest_base'          => 'lessons',
    'rest_controller_class' => 'WP_REST_Posts_Controller',
    'supports' => ['title', 'author']
  ]);
});

function register_lesson_fields()
{
  $lesson_fields = array(
    'lesson_teacher',
    'lesson_day_of_week',
    'lesson_start_time',
    'lesson_end_time',
    'lesson_room_number',
  );

  foreach ($lesson_fields as $field) {
    register_rest_field(
      'lessons',
      $field,
      array(
        'schema'          => array(
          'type' => $field === 'lessons_room_number' ? 'integer' : 'string',
          'description' => ucfirst(str_replace('_', ' ', $field)),
          'context'     => array('view', 'edit'),
        ),
      )
    );
  }
}
add_action('rest_api_init', 'register_lesson_fields');

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/lessons', [
    'methods' => Wp_rest_server::READABLE,
    'callback' => 'get_lessons',
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/lessons/(?P<id>\d+)', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'get_lesson',
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
  register_rest_route('wp/v2', '/lessons', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'create_lesson',
    'permission_callback' => function ($request) {
      return current_user_can('edit_posts');
    }
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/lessons/(?P<id>\d+)', [
    'methods' => WP_REST_Server::EDITABLE,
    'callback' => 'update_lesson',
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
  register_rest_route('wp/v2', '/lessons/(?P<id>\d+)', [
    'methods' => WP_REST_Server::DELETABLE,
    'callback' => 'delete_lesson',
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

function get_lesson($request)
{
  $params = $request->get_params();
  $post_id = $params['id'];

  $post = get_post($post_id);
  if (!$post) {
    return new WP_Error('invalid_lesson_id', __('Invalid lesson ID'), ['status' => 404]);
  }

  return [
    'id' => $post_id,
    'title' => $post->post_title,
    'lesson_teacher' => get_post_meta($post_id, 'lesson_teacher', true),
    'lesson_day_of_week' => get_post_meta($post_id, 'lesson_day_of_week', true),
    'lesson_start_time' => get_post_meta($post_id, 'lesson_start_time', true),
    'lesson_end_time' => get_post_meta($post_id, 'lesson_end_time', true),
    'lesson_room_number' => get_post_meta($post_id, 'lesson_room_number', true)
  ];
}

function get_lessons($request)
{
  $query = new WP_Query([
    'post_type' => 'lesson',
    'posts_per_page' => -1
  ]);

  // Check if query is successful
  if (!$query || $query->post_count === 0) {
    return new WP_Error('query_failed', __('No lessons found'), ['status' => 404]);
  }

  $response = [];
  foreach ($query->posts as $post) {
    $response[] = [
      'id' => $post->ID,
      'title' => $post->post_title,
      'lesson_teacher' => get_post_meta($post->ID, 'lesson_teacher', true),
      'lesson_day_of_week' => get_post_meta($post->ID, 'lesson_day_of_week', true),
      'lesson_start_time' => get_post_meta($post->ID, 'lesson_start_time', true),
      'lesson_end_time' => get_post_meta($post->ID, 'lesson_end_time', true),
      'lesson_room_number' => get_post_meta($post->ID, 'lesson_room_number', true)
    ];
  }

  return $response;
}

function create_lesson($request)
{
  $params = $request->get_params();

  // Validate required fields
  if (empty($params['title'])) {
    return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_teacher'])) {
    return new WP_Error('missing_lesson_teacher', __('Teacher is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_day_of_week'])) {
    return new WP_Error('missing_lesson_day_of_week', __('Day of the week is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_start_time'])) {
    return new WP_Error('missing_lesson_start_time', __('Lesson start time is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_end_time'])) {
    return new WP_Error('missing_lesson_end_time', __('Lesson start time is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_room_number'])) {
    return new WP_Error('missing_lesson_room_number', __('Lesson room number location is required.', 'text-domain'), array('status' => 400));
  }
  
  // Sanitize and validate input data
  $title = sanitize_text_field($params['title']);
  $lesson_teacher = sanitize_text_field($params['lesson_teacher']);
  $lesson_day_of_week = sanitize_text_field($params['lesson_day_of_week']);
  $lesson_start_time = sanitize_text_field($params['lesson_start_time']);
  $lesson_end_time = sanitize_text_field($params['lesson_end_time']);
  $lesson_room_number = intval($params['lesson_room_number']);

  $post = array(
    'post_title' => $title,
    'post_type' => 'lesson',
    'post_status' => 'publish'
  );
  $post_id = wp_insert_post( $post, true );
  
  // Handle post creation errors
  if ( is_wp_error( $post_id ) ) {
    return new WP_Error('create_error', __('Failed to create lesson post.', 'text-domain'), array('status' => 500));
  }

  // Add metadata
  update_post_meta( $post_id, 'lesson_teacher', $lesson_teacher );
  update_post_meta( $post_id, 'lesson_day_of_week', $lesson_day_of_week );
  update_post_meta( $post_id, 'lesson_start_time', $lesson_start_time );
  update_post_meta( $post_id, 'lesson_end_time', $lesson_end_time );
  update_post_meta( $post_id, 'lesson_room_number', $lesson_room_number );
  
  
  return array(
    'message' => 'Lesson created successfully.',
    'data' => array (
      'id' => $post_id,
      'title' => $title,
      'lesson_teacher' => $lesson_teacher,
      'lesson_day_of_week' => $lesson_day_of_week,
      'lesson_start_time' => $lesson_start_time,
      'lesson_end_time' => $lesson_end_time,
      'lesson_room_number' => $lesson_room_number
    )
  );
}

function update_lesson($request)
{
  $params = $request->get_params();
  $id = $params['id'];

  // Get the existing lesson post
  $post = get_post($id);
  if (!$post) {
    return new WP_Error('lesson_not_found', __('Lesson not found'), ['status' => 404]);
  }

  // Validate required fields
  if (empty($params['title'])) {
    return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_teacher'])) {
    return new WP_Error('missing_lesson_teacher', __('Teacher is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_day_of_week'])) {
    return new WP_Error('missing_lesson_day_of_week', __('Day of the week is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_start_time'])) {
    return new WP_Error('missing_lesson_start_time', __('Lesson start time is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_end_time'])) {
    return new WP_Error('missing_lesson_end_time', __('Lesson start time is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['lesson_room_number'])) {
    return new WP_Error('missing_lesson_room_number', __('Lesson room number location is required.', 'text-domain'), array('status' => 400));
  }
  
  // Sanitize and validate input data
  $title = sanitize_text_field($params['title']);
  $lesson_teacher = sanitize_text_field($params['lesson_teacher']);
  $lesson_day_of_week = sanitize_text_field($params['lesson_day_of_week']);
  $lesson_start_time = sanitize_text_field($params['lesson_start_time']);
  $lesson_end_time = sanitize_text_field($params['lesson_end_time']);
  $lesson_room_number = intval($params['lesson_room_number']);

  $post_data = [
    'ID' => $id,
    'post_title' => $title,
    'meta_input' => [
      'lesson_teacher' => $lesson_teacher,
      'lesson_day_of_week' => $lesson_day_of_week,
      'lesson_start_time' => $lesson_start_time,
      'lesson_end_time' => $lesson_end_time,
      'lesson_room_number' => $lesson_room_number
    ]
  ];
  wp_update_post($post_data);

  // Get the updated lesson data
  $updated_post = get_post($id);
  // Return the updated lesson data
  $response = [
    'message' => 'Document updated successfully.',
    'data'    => array(
      'id' => $updated_post->ID,
      'title' => $updated_post->post_title,
      'lesson_teacher' => $lesson_teacher,
      'lesson_day_of_week' => $lesson_day_of_week,
      'lesson_start_time' => $lesson_start_time,
      'lesson_end_time' => $lesson_end_time,
      'lesson_room_number' => $lesson_room_number
    ),
  ];
  return $response;
}

function delete_lesson($request)
{
  $id = $request->get_param('id');

  $post = get_post($id);
  if (!$post || $post->post_type !== 'lesson') {
    return new WP_Error('not_found', __('Lesson not found'), ['status' => 404]);
  }

  $result = wp_delete_post($id, true);

  // Check if post is successfully deleted
  if (!$result || is_wp_error($result)) {
    return new WP_Error('delete_failed', __('Failed to delete lesson'), ['status' => 500]);
  }
  return [
    'id' => $id,
    'message' => __('Lesson deleted successfully')
  ];
}
