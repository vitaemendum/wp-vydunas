<?php

if (!defined('SHORTINIT')) {
    define('SHORTINIT', 'true');
}

require_once dirname(__FILE__) . '/../../../wp-load.php';

/**
 * Plugin Name: Document 
 * Description: Document plugin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */


// Registering custom post type for documents
add_action('init', function () {
  register_post_type('document', [
    'labels' => [
      'name' => __('Documents'),
      'singular_name' => __('Document')
    ],
    'public' => false,
    'capability_type' => 'post',
    'hierarchical'       => false,
    'menu_position'      => null,
    'show_in_rest'       => true,
    'rest_base'          => 'documents',
    'rest_controller_class' => 'WP_REST_Posts_Controller',
    'supports' => ['title', 'author', 'thumbnail']
  ]);
});

function register_document_fields()
{
  $document_fields = array(
    'document_category',
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
    'methods' => Wp_rest_server::READABLE,
    'callback' => 'get_documents',
	'permission_callback' => '__return_true',
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/documents/(?P<id>\d+)', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => 'get_document',
	'permission_callback' => '__return_true',
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
  register_rest_route('wp/v2', '/documents', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'create_document',
    'permission_callback' => '__return_true',
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/documents/(?P<id>\d+)', [
    'methods' => WP_REST_Server::EDITABLE,
    'callback' => 'update_document',
    'args' => array(
      'id' => array(
        'validate_callback' => function ($value) {
          return is_numeric($value);
        }
      ),
    ),
    'permission_callback' => '__return_true',
  ]);
});

add_action('rest_api_init', function () {
  register_rest_route('wp/v2', '/documents/(?P<id>\d+)', [
    'methods' => WP_REST_Server::DELETABLE,
    'callback' => 'delete_document',
    'args' => array(
      'id' => array(
        'validate_callback' => function ($value) {
          return is_numeric($value);
        }
      ),
    ),
    'permission_callback' => '__return_true',
  ]);
});

function get_document($request)
{
  $params = $request->get_params();
  $post_id = $params['id'];

  $post = get_post($post_id);
  if (!$post) {
    return new WP_Error('invalid_document_id', __('Invalid document ID'), ['status' => 404]);
  }

  $file = array(
    'post_type' => 'attachment',
    'post_parent' => $post_id,
  );
  $attachment = get_posts($file);
  if (!$attachment) {
    return new WP_Error('no_attachment', __('No attachment found for this document'), ['status' => 404]);
  }

  $file_url = wp_get_attachment_url($attachment[0]->ID);

  return [
    'id' => $post_id,
    'title' => $post->post_title,
    'category' => get_post_meta($post_id, 'category', true),
    'file_type' => get_post_meta($post_id, 'file_type', true),
    'url' => $file_url,
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
      'category' => get_post_meta($post->ID, 'category', true),
      'file_type' => get_post_meta($post->ID, 'file_type', true),
      'attachment_id' => $attachment_id,
      'attachment_url' => $attachment_url,
    ];
  }

  return $response;
}

function create_document($request)
{
	error_log( json_encode($request->get_headers('content_type')) );
  $params = $request->get_params();
  // Validate required fields
  if (empty($params['title'])) {
    return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['document_category'])) {
    return new WP_Error('missing_category', __('Category is required.', 'text-domain'), array('status' => 400));
  }
  // Sanitize and validate input data
  $title = sanitize_text_field($params['title']);
  $document_category = sanitize_text_field($params['document_category']);

  $file = $_FILES['document_file'];
  //var_dump($file);
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
    $file_name = basename($file['name']);
    $file_type = wp_check_filetype($file_name, null);
    // Create a new post for the document
    $post = [
      'post_title' => $title,
      'post_type' => 'document',
      'post_status' => 'publish',
      'meta_input' => [
        'category' => $document_category,
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
      return new WP_REST_Response([
		  'message' => 'Document created successfully.',
		  'data'    => array(
			'id' => $post_id,
			'title' => $title,
			'category' => $document_category,
			'file_type' => $file_type['ext'],
			'url' => wp_get_attachment_url($attachment_id),
		  ),
		], 201);
    } else {
      // Delete the post if attachment creation failed
      wp_delete_post($post_id, true);
      return new WP_Error('upload_failed', __('Failed to upload document'), ['status' => 500]);
    }
  } else {
    return new WP_Error('upload_failed', __('Failed to upload document'), ['status' => 500]);
  }
}

function update_document($request)
{
  $params = $request->get_params();
  $id = $params['id'];

  // Validate required fields
  if (empty($params['title'])) {
    return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
  }
  if (empty($params['document_category'])) {
    return new WP_Error('missing_category', __('Category is required.', 'text-domain'), array('status' => 400));
  }

  // Sanitize and validate input data
  $title = sanitize_text_field($params['title']);
  $document_category = sanitize_text_field($params['document_category']);

  // Get the existing document post
  $post = get_post($id);
  if (!$post) {
    return new WP_Error('document_not_found', __('Document not found'), ['status' => 404]);
  }

  // Check if there's a new file upload
  if (!empty($_FILES['document_file'])) {
    $file = $_FILES['document_file'];
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
      $file_name = basename($file['name']);
      $file_type = wp_check_filetype($file_name, null);

      // Update the document post
      $post_data = [
        'ID' => $id,
        'post_title' => $title,
        'meta_input' => [
          'category' => $document_category,
          'file_type' => $file_type['ext'],
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
        'post_content' => '',
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
      'data'    => array(
        'id' => $id,
        'post_title' => $title,
        'meta_input' => [
          'category' => $document_category,
        ],
      ),
    ];
    wp_update_post($post_data);
  }

  // Get the updated document data
  $updated_post = get_post($id);
  // Return the updated document data
  $response = [
    'message' => 'Document updated successfully.',
    'data'    => array(
      'id' => $updated_post->ID,
      'title' => $updated_post->post_title,
      'category' => $document_category,
    ),
  ];
  return $response;
}

function delete_document($request)
{
  $id = $request->get_param('id');

  $post = get_post($id);
  if (!$post || $post->post_type !== 'document') {
    return new WP_Error('not_found', __('Document not found'), ['status' => 404]);
  }

  // Delete document attachment if it exists
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
    return new WP_Error('delete_failed', __('Failed to delete document'), ['status' => 500]);
  }
  $response = new WP_REST_Response();
  $response->set_status(204);
  return $response;
}
