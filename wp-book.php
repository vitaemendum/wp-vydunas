<?php

/**
 * Plugin Name: Book 
 * Description: Book 
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

use WP_REST_Response;
use WP_Error;

function register_book_post_type()
{
    $labels = array(
        'name'               => __('Books'),
        'singular_name'      => __('Book'),
        'add_new'            => __('Add New Book'),
        'add_new_item'       => __('Add New Book'),
        'edit_item'          => __('Edit Book'),
        'new_item'           => __('New Book'),
        'view_item'          => __('View Book'),
        'search_items'       => __('Search Book'),
        'not_found'          => __('No books found'),
        'not_found_in_trash' => __('No books found in trash'),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'book'),
        'capability_type'     => 'book',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 5,
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        'show_in_rest'          => true,
        'rest_base'             => 'books',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'status' => 'book',
    );

    register_post_type('book', $args);
}
add_action('init', 'register_book_post_type');

function register_book_fields() {
    $book_fields = array(
      'book_title',
      'book_description',
      'book_author',
      'book_isbn',
      'book_quantity',
      'book_image'
    );
  
    foreach ($book_fields as $field) {
      if ($field === 'book_image') {
        register_rest_field(
          'books',
          $field,
          array(
            'get_callback'   => null,
            'update_callback'=> null,
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
          'books',
          $field,
          array(
            'schema'          => array(
              'type' => $field === 'book_quantity' ? 'integer' : 'string',       
              'description' => ucfirst(str_replace('_', ' ', $field)),
              'context'     => array('view', 'edit'),
            ),
          )
        );
      }
    }
  }
  add_action('rest_api_init', 'register_book_fields');
  


add_action('rest_api_init', function () {
    // Register REST API endpoints for events
    register_rest_route('wp/v2', '/books', array(
        'methods' => 'GET',
        'callback' => 'get_all_books',
    ));
    register_rest_route('wp/v2', '/books/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_single_book',
    ));

    register_rest_route('wp/v2', '/books', array(
        'methods' => 'POST',
        'callback' => 'create_book'
    ));
    register_rest_route('wp/v2', '/books/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'update_book',
        'permission_callback' => function () {
            return current_user_can('publish_posts');
        }
    ));
    register_rest_route('wp/v2', '/books/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'delete_book',
        'permission_callback' => function () {
            return current_user_can('publish_posts');
        }
    ));
});

function create_book($request)
{
    $book_data = $request->get_params();
    var_dump($book_data['book_author']);
}
