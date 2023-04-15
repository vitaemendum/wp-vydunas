<?php

/**
 * Plugin Name: Document 
 * Description: Document pluhin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

 <?php
 /*
 Plugin Name: My Plugin
 Description: Custom REST API endpoint for document upload
 */
 
 // Custom post type for documents
 add_action('init', function() {
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
 
 // Custom REST API endpoint for document upload
 add_action('rest_api_init', function() {
   register_rest_route('wp/v2', '/documents', [
     'methods' => 'POST',
     'callback' => 'upload_document',
     'permission_callback' => function($request) {
       return current_user_can('edit_posts');
     }
   ]);
 });
 
 add_action('rest_api_init', function() {
    register_rest_route('wp/v2', '/documents', [
      'methods' => 'POST',
      'callback' => 'create_document',
      'permission_callback' => function($request) {
        return current_user_can('edit_posts');
      }
    ]);
  });

  add_action('rest_api_init', function() {
    register_rest_route('wp/v2', '/documents', [
      'methods' => 'GET',
      'callback' => 'get_documents',
      'permission_callback' => function($request) {
        return current_user_can('edit_posts');
      }
    ]);
  });

  add_action('rest_api_init', function() {
    register_rest_route('wp/v2', '/documents/(?P<id>\d+)', [
      'methods' => 'GET',
      'callback' => 'myplugin_get_document',
      'permission_callback' => function($request) {
        return current_user_can('edit_posts');
      }
    ]);
  });

  add_action('rest_api_init', function() {
    register_rest_route('wp/v2', '/documents/(?P<id>\d+)', [
      'methods' => 'PUT',
      'callback' => 'update_document',
      'permission_callback' => function($request) {
        return current_user_can('edit_posts');
      }
    ]);
  });
  
  function update_document($request) {
    $id = $request->get_param('id');
    $params = $request->get_params();
  
    $post = get_post($id);
    if (!$post || $post->post_type !== 'document') {
      return new WP_Error('not_found', __('Document not found'), ['status' => 404]);
    }
  
    $updated_post = [
      'ID' => $id,
      'post_title' => $params['title']
    ];
    wp_update_post($updated_post);
  
    update_post_meta($id, 'type', $params['type']);
    update_post_meta($id, 'file_type', $params['file_type']);
  
    return [
      'id' => $id,
      'title' => $params['title'],
      'type' => $params['type'],
      'file_type' => $params['file_type'],
      'url' => wp_get_attachment_url(get_post_meta($id, 'thumbnail_id', true))
    ];
  }
  
  
  function get_document($request) {
    $id = $request->get_param('id');
  
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
  
  function get_documents($request) {
    $query = new WP_Query([
      'post_type' => 'document',
      'posts_per_page' => -1
    ]);
  
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
  
    // Check for file upload errors
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      return new WP_Error('invalid_upload', __('Invalid file upload'), ['status' => 400]);
    }
  
    // Process and validate the file upload
    $file = $_FILES['file'];
    $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
    if ($uploaded_file && !isset($uploaded_file['error'])) {
      $file_name = basename($uploaded_file['file']);
      $file_type = wp_check_filetype($file_name, null);
      $file_title = $params['title'];
      $file_type = $params['type'];
  
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
      return new WP_Error('upload_failed', __('Failed to upload document'), ['status' => 500]);
    }
  }

 // Function to handle document upload
 function upload_document($request) {
   $params = $request->get_params();
 
   // Check for file upload errors
   if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
     return new WP_Error('invalid_upload', __('Invalid file upload'), ['status' => 400]);
   }
 
   // Process and validate the file upload
   $file = $_FILES['file'];
   $uploaded_file = wp_handle_upload($file, ['test_form' => false]);
   if ($uploaded_file && !isset($uploaded_file['error'])) {
     $file_name = basename($uploaded_file['file']);
     $file_type = wp_check_filetype($file_name, null);
     $file_title = $params['title'];
     $file_type = $params['type'];
 
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
     $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
     wp_update_attachment_metadata($attachment_id, $attachment_data);
 
     return new WP_REST_Response(['success' => true, 'id' => $post_id]);
   } else {
     return new WP_Error('invalid_upload', __('Invalid file upload'), ['status' => 400]);
   }
 }