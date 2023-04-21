<?php

/**
 * Plugin Name: Book 
 * Description: Book plugin
 * Version:     1.0.0
 * License:     GPLv2
 * Network:     true
 */

require_once ABSPATH . '/wp-admin/includes/file.php';
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Registering custom post type for books
add_action('init', function () {
    register_post_type('book', [
        'labels' => [
            'name' => __('Books'),
            'singular_name' => __('Book')
        ],
        'public' => false,
        'capability_type' => 'post',
        'hierarchical'       => false,
        'menu_position'      => null,
        'show_in_rest'       => true,
        'rest_base'          => 'books',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'supports' => ['title', 'author', 'thumbnail']
    ]);
});

function register_book_fields()
{
    $book_fields = array(
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
        'methods' => Wp_rest_server::READABLE,
        'callback' => 'get_books',
        'permission_callback' => function ($request) {
            return current_user_can('edit_posts');
        }
    ));
    register_rest_route('wp/v2', '/books/(?P<id>\d+)', array(
        'methods' => Wp_rest_server::READABLE,
        'callback' => 'get_book',
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
    ));

    register_rest_route('wp/v2', '/books', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'create_book',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }

    ));
    register_rest_route('wp/v2', '/books/(?P<id>\d+)', array(
        'methods' => WP_REST_Server::EDITABLE,
        'callback' => 'update_book',
        'args' => array(
            'id' => array(
                'validate_callback' => function ($value) {
                    return is_numeric($value);
                }
            ),
        ),
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
    register_rest_route('wp/v2', '/books/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'delete_book',
        'args' => array(
            'id' => array(
                'validate_callback' => function ($value) {
                    return is_numeric($value);
                }
            ),
        ),
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
});

// Get a single book
function get_book($request)
{
    $params = $request->get_params();
    $post_id = $params['id'];

    $post = get_post($post_id);
    if (!$post) {
        $response = new WP_Error('invalid_id', 'Invalid book ID', array('status' => 404));
        return $response;
    }

    $file = array(
        'post_type' => 'attachment',
        'post_parent' => $post_id,
    );
    $attachment = get_posts($file);
    if (!$attachment) {
        return new WP_Error('no_attachment', __('No attachment found for this book'), ['status' => 404]);
    }

    $attachment_url = wp_get_attachment_url($attachment[0]->ID);

    return [
        'id' => $post_id,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'book_author' => get_post_meta($post->ID, 'book_author', true),
        'book_isbn' => get_post_meta($post->ID, 'book_isbn', true),
        'book_quantity' => get_post_meta($post->ID, 'book_quantity', true),
        'url' => $attachment_url
    ];
}

// Get all books
function get_books($request)
{
    $query = new WP_Query([
        'post_type' => 'book',
        'posts_per_page' => -1
    ]);

    // Check if query is successful
    if (!$query || $query->post_count === 0) {
        return new WP_Error('query_failed', __('No books found'), ['status' => 400]);
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
            'book_author' => get_post_meta($post->ID, 'book_author', true),
            'book_isbn' => get_post_meta($post->ID, 'book_isbn', true),
            'book_quantity' => get_post_meta($post->ID, 'book_quantity', true),
            'attachment_id' => $attachment_id,
            'attachment_url' => $attachment_url,
        ];
    }

    return $response;
}

function create_book($request)
{
    $params = $request->get_params();

    // Validate required fields
    if (empty($params['title'])) {
        return new WP_Error('missing_title', __('Title is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['content'])) {
        return new WP_Error('missing_content', __('Content is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['book_author'])) {
        return new WP_Error('missing_book_author', __('Book author is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['book_isbn'])) {
        return new WP_Error('missing_book_isbn', __('Book ISBN is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['book_quantity'])) {
        return new WP_Error('missing_book_quantity', __('Book quantity is required.', 'text-domain'), array('status' => 400));
    }

    // Sanitize and validate input data
    $title = sanitize_text_field($params['title']);
    $content = sanitize_text_field($params['content']);
    $book_author = sanitize_text_field($params['book_author']);
    $book_isbn = sanitize_text_field($params['book_isbn']);
    $book_quantity = intval($params['book_quantity']);

    $file = $_FILES['book_image'];
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
            'post_type' => 'book',
            'post_status' => 'publish',
            'meta_input' => [
                'book_author' => $book_author,
                'book_isbn' => $book_isbn,
                'book_quantity' => $book_quantity,
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
                'message' => 'Book created successfully.',
                'data'    => array(
                    'id' => $post_id,
                    'title' => $title,
                    'content' => $content,
                    'book_author' => $book_author,
                    'book_isbn' => $book_isbn,
                    'book_quantity' => $book_quantity,
                    'url' => wp_get_attachment_url($attachment_id),
                ),
            ];
        } else {
            // Delete the post if attachment creation failed
            wp_delete_post($post_id, true);
            return new WP_Error('upload_failed', __('Failed to upload book'), ['status' => 500]);
        }
    } else {
        return new WP_Error('upload_failed', __('Failed to upload book'), ['status' => 500]);
    }
}

function update_book($request)
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
    if (empty($params['book_author'])) {
        return new WP_Error('missing_book_author', __('Book author is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['book_isbn'])) {
        return new WP_Error('missing_book_isbn', __('Book ISBN is required.', 'text-domain'), array('status' => 400));
    }
    if (empty($params['book_quantity'])) {
        return new WP_Error('missing_book_quantity', __('Book quantity is required.', 'text-domain'), array('status' => 400));
    }

    // Sanitize and validate input data
    $title = sanitize_text_field($params['title']);
    $content = sanitize_text_field($params['content']);
    $book_author = sanitize_text_field($params['book_author']);
    $book_isbn = sanitize_text_field($params['book_isbn']);
    $book_quantity = intval($params['book_quantity']);

    // Get the existing document post
    $post = get_post($id);
    if (!$post) {
        return new WP_Error('book_not_found', __('Book not found'), ['status' => 404]);
    }

    // Check if there's a new file upload
    if (!empty($_FILES['book_image'])) {
        $file = $_FILES['book_image'];
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
                    'book_author' => $book_author,
                    'book_isbn' => $book_isbn,
                    'book_quantity' => $book_quantity,
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
                'book_author' => $book_author,
                'book_isbn' => $book_isbn,
                'book_quantity' => $book_quantity,
            ]
        ];
        wp_update_post($post_data);
    }

    // Get the updated document data
    $updated_post = get_post($id);
    // Return the updated document data
    $response = [
        'message' => 'Book updated successfully.',
        'data'    => array(
            'id' => $updated_post->ID,
            'title' => $updated_post->post_title,
            'content' => $updated_post->post_content,
            'book_author' => $book_author,
            'book_isbn' => $book_isbn,
            'book_quantity' => $book_quantity,
        ),
    ];
    return $response;
}

function delete_book($request)
{
    $id = $request->get_param('id');

    $post = get_post($id);
    if (!$post || $post->post_type !== 'book') {
        return new WP_Error('not_found', __('Book not found'), ['status' => 404]);
    }

    // Delete book attachment if it exists
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
        return new WP_Error('delete_failed', __('Failed to delete book'), ['status' => 500]);
    }
    return [
        'id' => $id,
        'message' => __('Book deleted successfully')
    ];
}
