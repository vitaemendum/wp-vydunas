<?php

/**
 * Plugin Name: Book 
 * Description: Book 
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

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

function register_book_fields()
{
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
        'callback' => 'create_book',
        'permission_callback' => function () {
            return current_user_can('publish_posts');
        }

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

    // Validate required fields
    if (
        empty($book_data['book_title']) || empty($book_data['book_description']) ||
        empty($book_data['book_author']) || empty($book_data['book_isbn']) ||
        empty($book_data['book_quantity'])
    ) {
        return new WP_Error('missing_fields', __('Missing required fields.', 'text-domain'), array('status' => 400));
    }

    // Sanitize and validate input data
    $title = sanitize_text_field($book_data['book_title']);
    $description = sanitize_text_field($book_data['book_description']);
    $author = sanitize_text_field($book_data['book_author']);
    $isbn = sanitize_text_field($book_data['book_isbn']);
    $quantity = floatval($book_data['book_quantity']);

    // Insert post
    $post_data = array(
        'post_title' => $title,
        'post_content' => $description,
        'post_type' => 'book',
        'post_status' => 'publish',
    );

    $post_id = wp_insert_post($post_data);
    update_post_meta($post_id, 'book_author', $author);
    update_post_meta($post_id, 'book_isbn', $isbn);
    update_post_meta($post_id, 'book_quantity', $quantity);

    // Handle image upload
    $image_id = 0;
    $image_url = '';
    if (isset($_FILES['book_image'])) {
        $upload = wp_upload_bits($_FILES['book_image']['name'], null, file_get_contents($_FILES['book_image']['tmp_name']));
        if (isset($upload['error']) && $upload['error'] != 0) {
            return new WP_Error('upload_error', __('Error uploading image.', 'text-domain'), array('status' => 400));
        } else {
            $image_id = wp_insert_attachment(array(
                'post_mime_type' => $upload['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $_FILES['book_image']['name']),
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
        'message' => 'Book created successfully!',
        'data' => array(
            'book_id' => $post_id,
            'book_title' => $title,
            'book_description' => $description,
            'book_author' => $author,
            'book_isbn' => $isbn,
            'book_quantity' => $quantity,
            'image_id' => $image_id,
            'image_url' => $image_url
        )
    );
    return new WP_REST_Response($response, 200);
}

// Get event data
function get_book_data($id)
{
    $book = get_post($id);

    if (!$book || $book->post_type !== 'book') {
        $response = new WP_Error('no_book', 'Book not found', array('status' => 404));
        return $response;
    }

    $book_data = array(
        'id' => $book->ID,
        'book_title' => $book->post_title,
        'book_description' => $book->post_content,
        'book_author' => get_post_meta($book->ID, 'book_author', true),
        'book_isbn' => get_post_meta($book->ID, 'book_isbn', true),
        'book_quantity' => get_post_meta($book->ID, 'book_quantity', true),
        'book_image' => get_the_post_thumbnail_url($book->ID, 'full')
    );

    return $book_data;
}

// Get all events
function get_all_books($request)
{
    $args = array(
        'post_type' => 'book',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $books = get_posts($args);
    $data = array();

    foreach ($books as $book) {
        $response = get_book_data($book->ID);
        if (is_wp_error($response)) {
            return $response;
        }
        $data[] = $response;
    }

    if (empty($data)) {
        $response = new WP_Error('no_books', 'No books found', array('status' => 404));
    } else {
        $response = new WP_REST_Response($data, 200);
    }

    return $response;
}

// Get a single event
function get_single_book($request)
{
    $id = (int) $request['id'];

    if (!$id) {
        $response = new WP_Error('invalid_id', 'Invalid event ID', array('status' => 400));
        return $response;
    }

    $book = get_post($id);

    if (!$book || $book->post_type !== 'book') {
        $response = new WP_Error('no_book', 'Book not found', array('status' => 404));
        return $response;
    }

    $data = get_book_data($id);

    if (is_wp_error($data)) {
        return $data;
    }

    $response = new WP_REST_Response($data, 200);

    return $response;
}

function delete_book($request)
{
    $id = (int) $request['id'];

    if (!$id) {
        $response = new WP_Error('invalid_id', 'Invalid event ID', array('status' => 400));
        return $response;
    }

    $book = get_post($id);

    if (!$book || $book->post_type !== 'book') {
        $response = new WP_Error('no_book', 'Book not found', array('status' => 404));
        return $response;
    }

    // Delete event image if it exists
    $image_id = get_post_thumbnail_id($book->ID);
    if ($image_id) {
        wp_delete_attachment($image_id, true);
    }

    // Delete event
    $result = wp_delete_post($id, true);

    if (!$result) {
        $response = new WP_Error('delete_failed', 'Book deletion failed', array('status' => 500));
        return $response;
    }

    $response = new WP_REST_Response(null, 204);

    return $response;
}

function update_book($request)
{
    $id = $request->get_param('id');
    $book = get_post($id);

    if (!$book || $book->post_type !== 'book') {
        $response = new WP_Error('no_book', 'Book not found', array('status' => 404));
        return $response;
    }

    $params = $request->get_params();
    $book_title = sanitize_text_field($params['book_title']);
    $book_description = sanitize_text_field($params['book_description']);
    $book_author = sanitize_text_field($params['book_author']);
    $book_isbn = sanitize_text_field($params['book_isbn']);
    $book_quantity = sanitize_text_field($params['book_quantity']);

    $post_data = array(
        'ID'           => $id,
        'post_title'   => $book_title,
        'post_content' => $book_description,
        'post_type'    => 'book',
        'post_status'  => 'publish',
    );

    $post_id = wp_update_post($post_data);

    if (is_wp_error($post_id)) {
        return new WP_Error('update_error', $post_id->get_error_message(), array('status' => 500));
    }

    // Update event meta data
    update_post_meta($post_id, 'book_author', $book_author);
    update_post_meta($post_id, 'book_isbn', $book_isbn);
    update_post_meta($post_id, 'book_quantity', $book_quantity);

    // Handle image upload
    $image_id = 0;
    $image_url = '';
    if (isset($_FILES['book_image'])) {
        $upload = wp_upload_bits($_FILES['book_image']['name'], null, file_get_contents($_FILES['book_image']['tmp_name']));
        if (isset($upload['error']) && $upload['error'] != 0) {
            return new WP_Error('upload_error', __('Error uploading image.', 'text-domain'), array('status' => 400));
        } else {
            $image_id = wp_insert_attachment(array(
                'post_mime_type' => $upload['type'],
                'post_title' => preg_replace('/.[^.]+$/', '', $_FILES['book_image']['name']),
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
            'post_title' => $book->post_title,
            'post_content' => $book->post_content,
            'book_author' => get_post_meta($book->ID, 'book_author', true),
            'book_isbn' => get_post_meta($book->ID, 'book_isbn', true),
            'book_quantity' => get_post_meta($book->ID, 'book_quantity', true),
            'book_image' => get_the_post_thumbnail_url($book->ID, 'full'),
            'image_id' => $image_id,
            'image_url' => $image_url
        )
    );
    return new WP_REST_Response($response, 200);
}