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
use WP_Query;

function create_events_table()
{
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $table_name = $wpdb->prefix . 'events';
  $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      event_id bigint(20) NOT NULL AUTO_INCREMENT,
      event_title varchar(255) NOT NULL,
      event_description varchar(255) NOT NULL,
      event_start_date datetime NOT NULL,
      event_end_date datetime NOT NULL,
      event_price decimal(10, 2) NOT NULL,
      event_location varchar(255) NOT NULL,
      PRIMARY KEY  (event_id)
  ) $charset_collate;";
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_events_table');

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
    'supports'            => array(),
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

function get_all_events($request)
{
  global $wpdb;

  $table_name = $wpdb->prefix . 'events';

  $query = "SELECT * FROM $table_name";
  $results = $wpdb->get_results($query, ARRAY_A);

  if (!empty($results)) {
    $events = array();
    foreach ($results as $result) {
      $event = array(
        'event_id' => $result['event_id'],
        'event_title' => $result['event_title'],
        'event_description' => $result['event_description'],
        'event_start_date' => $result['event_start_date'],
        'event_end_date' => $result['event_end_date'],
        'event_price' => $result['event_price'],
        'event_location' => $result['event_location'],
      );
      array_push($events, $event);
    }
    return new WP_REST_Response($events, 200);
  } else {
    return new WP_Error('no_events', 'No events found', array('status' => 404));
  }
}

function get_single_event($request)
{
  global $wpdb;

  $table_name = $wpdb->prefix . 'events';
  $params = $request->get_params();
  $event_id = $params['id'];

  $result = $wpdb->get_row("SELECT * FROM $table_name WHERE event_id = $event_id");

  if ($result === null) {
    return new WP_Error('no_event', 'Event not found', array('status' => 404));
  } elseif ($result === false) {
    return new WP_Error('database_error', $wpdb->last_error);
  } else {
    $event = array(
      'event_id' => $result->event_id,
      'event_title' => $result->event_title,
      'event_description' => $result->event_description,
      'event_start_date' => $result->event_start_date,
      'event_end_date' => $result->event_end_date,
      'event_price' => $result->event_price,
      'event_location' => $result->event_location,
    );
    return new WP_REST_Response($event, 200);
  }
}

function create_event($request)
{
  $event_data = $request->get_params();
  $event_title = sanitize_text_field($event_data['event_title']);
  $event_description = sanitize_text_field($event_data['event_description']);
  $event_start_date = sanitize_text_field($event_data['event_start_date']);
  $event_end_date = sanitize_text_field($event_data['event_end_date']);
  $event_price = floatval($event_data['event_price']);
  $event_location = sanitize_text_field($event_data['event_location']);

  global $wpdb;
  $table_name = $wpdb->prefix . 'events';

  $wpdb->insert($table_name, array(
    'event_title' => $event_title,
    'event_description' => $event_description,
    'event_start_date' => $event_start_date,
    'event_end_date' => $event_end_date,
    'event_price' => $event_price,
    'event_location' => $event_location
  ));

  $event_id = $wpdb->insert_id;

  if ($event_id) {
    $response = array(
      'event_id' => $event_id,
      'message' => __('Event created successfully.', 'text-domain')
    );
    return new WP_REST_Response($response, 200);
  } else {
    $error = new WP_Error('create_event_error', __('Failed to create event.', 'text-domain'), array('status' => 500));
    return $error;
  }
}

function update_event($request)
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'events';
  $id = $request['id'];

  // Check if the event with given ID exists
  $event = $wpdb->get_row("SELECT * FROM $table_name WHERE event_id = $id");
  if (!$event) {
    return new WP_Error('event_not_found', __('Event not found'), array('status' => 404));
  }

  // Update event data in the database
  $data = array();
  if (isset($request['event_title'])) {
    $data['event_title'] = $request['event_title'];
  }
  if (isset($request['event_description'])) {
    $data['event_description'] = $request['event_description'];
  }
  if (isset($request['event_start_date'])) {
    $data['event_start_date'] = $request['event_start_date'];
  }
  if (isset($request['event_end_date'])) {
    $data['event_end_date'] = $request['event_end_date'];
  }
  if (isset($request['event_price'])) {
    $data['event_price'] = $request['event_price'];
  }
  if (isset($request['event_location'])) {
    $data['event_location'] = $request['event_location'];
  }
  $wpdb->update($table_name, $data, array('event_id' => $id));

  // Get the updated event data
  $event = $wpdb->get_row("SELECT * FROM $table_name WHERE event_id = $id");
  $response = new WP_REST_Response($event);
  $response->set_status(200);
  return $response;
}


function delete_event($request)
{
  $params = $request->get_params();
  $event_id = $params['id'];

  global $wpdb;
  $table_name = $wpdb->prefix . 'events';

  $deleted = $wpdb->delete($table_name, array('event_id' => $event_id));

  if (!$deleted) {
    return new WP_Error('delete_error', __('Error occurred while deleting the event.'), array('status' => 500));
  }

  $response = new WP_REST_Response(array('message' => __('Event deleted successfully.')));
  $response->set_status(200);

  return $response;
}
