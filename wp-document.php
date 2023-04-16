<?php

/**
 * Plugin Name: Document 
 * Description: Document pluhin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

// Custom post type for documents
add_action('init', function () {
  register_post_type('document', [
    'labels' => [
      'name' => __('Documents'),
      'singular_name' => __('Document')
    ],
    'public' => false,
    'capability_type' => 'post',
    'supports' => ['title', 'author', 'thumbnail']
  ]);
});

function register_document_fields()
{
  $document_fields = array(
    'document_type',
    'document_file'
  );

  foreach ($document_fields as $field) {
    if ($field === 'document_file') {
      register_rest_field(
        'documents',
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
        'documents',
        $field,
        array(
          'schema'          => array(
            'type' => 'string',
            'description' => ucfirst(str_replace('_', ' ', $field)),
            'context'     => array('view', 'edit'),
          ),
        )
      );
    }
  }
}
add_action('rest_api_init', 'register_document_fields');

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/documents', [
    'methods' => 'POST',
    'callback' => 'create_document',
    'permission_callback' => function ($request) {
      return current_user_can('edit_posts');
    }
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/documents', [
    'methods' => 'GET',
    'callback' => 'get_documents',
    'permission_callback' => function ($request) {
      return current_user_can('edit_posts');
    }
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/documents/(?P<id>\d+)', [
    'methods' => 'GET',
    'callback' => 'get_document',
    'permission_callback' => function ($request) {
      return current_user_can('edit_posts');
    }
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/documents/(?P<id>\d+)', [
    'methods' => 'PUT',
    'callback' => 'update_document',
    'permission_callback' => function ($request) {
      return current_user_can('edit_posts');
    }
  ]);
});

add_action('rest_api_init', function() {
  register_rest_route('wp/v', '/documents/(?P<id>\d+)', [
    'methods' => 'DELETE',
    'callback' => 'delete_document',
    'permission_callback' => function($request) {
      return current_user_can('delete_posts');
    }
  ]);
});

function update_document($request)
{
  // Check for required parameters
  $id = $request->get_param('id');
  $params = $request->get_params();
  if (empty($id) || empty($params['title']) || empty($params['type']) || empty($params['file_type'])) {
    return new WP_Error('missing_parameter', __('Missing required parameters'), ['status' => 400]);
  }

  // Check if document exists
  $post = get_post($id);
  if (!$post || $post->post_type !== 'document') {
    return new WP_Error('not_found', __('Document not found'), ['status' => 404]);
  }

  // Update the post and post meta
  $updated_post = [
    'ID' => $id,
    'post_title' => $params['title']
  ];
  $post_id = wp_update_post($updated_post);
  if (is_wp_error($post_id)) {
    return new WP_Error('update_failed', __('Failed to update document'), ['status' => 500]);
  }

  $meta_updated = update_post_meta($id, 'type', $params['type']);
  $meta_updated &= update_post_meta($id, 'file_type', $params['file_type']);
  if (!$meta_updated) {
    return new WP_Error('update_failed', __('Failed to update document'), ['status' => 500]);
  }

  // Return updated document details
  return [
    'id' => $id,
    'title' => $params['title'],
    'type' => $params['type'],
    'file_type' => $params['file_type'],
    'url' => wp_get_attachment_url(get_post_meta($id, 'thumbnail_id', true))
  ];
}

function delete_document($request) {
  $id = $request->get_param('id');

  // Validate input ID
  if (!$id || !is_numeric($id)) {
    return new WP_Error('invalid_input', __('Invalid input ID'), ['status' => 400]);
  }

  $post = get_post($id);
  if (!$post || $post->post_type !== 'document') {
    return new WP_Error('not_found', __('Document not found'), ['status' => 404]);
  }

  $result = wp_delete_post($id, true);

  // Check if post is successfully deleted
  if (!$result || is_wp_error($result)) {
    return new WP_Error('delete_failed', __('Failed to delete document'), ['status' => 500]);
  }

  return [
    'id' => $id,
    'message' => __('Document deleted successfully')
  ];
}

function get_document($request)
{
  $id = $request->get_param('id');

  // Validate input ID
  if (!$id || !is_numeric($id)) {
    return new WP_Error('invalid_input', __('Invalid input ID'), ['status' => 400]);
  }

  $post = get_post($id);
  if (!$post || $post->post_type !== 'document') {
    return new WP_Error('not_found', __('Document not found'), ['status' => 404]);
  }

  return [
    'id' => $post->ID,
    'title' => $post->post_title,
    'type' => get_post_meta($post->ID, 'type', true),
    'file_type' => get_post_meta($post->ID, 'file_type', true),
    'url' => wp_get_attachment_url(get_post_meta($post->ID, 'thumbnail_id', true))
  ];
}

function get_documents($request)
{
  $query = new WP_Query([
    'post_type' => 'document',
    'posts_per_page' => -1
  ]);

  // Check if query is successful
  if (!$query || $query->post_count === 0) {
    return new WP_Error('query_failed', __('No documents found'), ['status' => 404]);
  }

  $documents = [];
  foreach ($query->posts as $post) {
    $documents[] = [
      'id' => $post->ID,
      'title' => $post->post_title,
      'type' => get_post_meta($post->ID, 'type', true),
      'file_type' => get_post_meta($post->ID, 'file_type', true),
      'url' => wp_get_attachment_url(get_post_meta($post->ID, 'thumbnail_id', true))
    ];
  }

  return $documents;
}

function create_document($request) {
  $params = $request->get_params();

  // Check if file is uploaded
  if (!isset($_FILES['file'])) {
    return new WP_Error('missing_file', __('File is missing'), ['status' => 400]);
  }

  $file = $_FILES['file'];

  // Check for file upload errors
  if ($file['error'] !== UPLOAD_ERR_OK) {
    return new WP_Error('invalid_upload', __('Invalid file upload'), ['status' => 400]);
  }

  // Validate file type
  $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
  $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);

  if (!in_array(strtolower($file_ext), $allowed_types)) {
    return new WP_Error('invalid_file_type', __('Invalid file type'), ['status' => 400]);
  }

  // Process and validate the file upload
  $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
  if ($uploaded_file && !isset($uploaded_file['error'])) {
    $file_name = basename($uploaded_file['file']);
    $file_type = wp_check_filetype($file_name, null);
    $file_title = $params['title'];

    // Create a new post for the document
    $post = [
      'post_title' => $file_title,
      'post_type' => 'document',
      'post_status' => 'publish',
      'meta_input' => [
        'type' => $file_type,
        'file_type' => $file_type['ext']
      ]
    ];

    $post_id = wp_insert_post($post);

    // Attach the uploaded file to the post
    $attachment = [
      'guid' => $uploaded_file['url'],
      'post_mime_type' => $file_type['type'],
      'post_title' => $file_name,
      'post_content' => '',
      'post_status' => 'inherit',
      'post_parent' => $post_id
    ];

    $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file'], $post_id);
    if (!is_wp_error($attachment_id)) {
      $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
      wp_update_attachment_metadata($attachment_id, $attachment_data);

      return [
        'id' => $post_id,
        'title' => $file_title,
        'type' => $file_type,
        'file_type' => $file_type['ext'],
        'url' => wp_get_attachment_url($attachment_id)
      ];
    } else {
      // Delete the post if attachment creation failed
      wp_delete_post($post_id, true);
      return new WP_Error('upload_failed', __('Failed to upload document'), ['status' => 500]);
    }
  } else {
    return new WP_Error('upload_failed', __('Failed to upload document'), ['status' => 500]);
  }
}

