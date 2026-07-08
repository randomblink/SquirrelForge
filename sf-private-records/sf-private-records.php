<?php
/**
 * Plugin Name: SquirrelForge Private Records
 * Description: Registers a private Custom Post Type and exposes authorized read access through a custom REST API route.
 * Version: 1.0.0
 * Author: SquirrelForge
 * License: GPL-2.0-or-later
 * Text Domain: sf-private-records
 *
 * @package SquirrelForgePrivateRecords
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Private Record custom post type.
 */
function sf_private_records_register_post_type(): void {
	$labels = array(
		'name'                  => __( 'Private Records', 'sf-private-records' ),
		'singular_name'         => __( 'Private Record', 'sf-private-records' ),
		'menu_name'             => __( 'Private Records', 'sf-private-records' ),
		'name_admin_bar'        => __( 'Private Record', 'sf-private-records' ),
		'add_new'               => __( 'Add New', 'sf-private-records' ),
		'add_new_item'          => __( 'Add New Private Record', 'sf-private-records' ),
		'new_item'              => __( 'New Private Record', 'sf-private-records' ),
		'edit_item'             => __( 'Edit Private Record', 'sf-private-records' ),
		'view_item'             => __( 'View Private Record', 'sf-private-records' ),
		'all_items'             => __( 'All Private Records', 'sf-private-records' ),
		'search_items'          => __( 'Search Private Records', 'sf-private-records' ),
		'not_found'             => __( 'No private records found.', 'sf-private-records' ),
		'not_found_in_trash'    => __( 'No private records found in Trash.', 'sf-private-records' ),
		'filter_items_list'     => __( 'Filter private records list', 'sf-private-records' ),
		'items_list_navigation' => __( 'Private records list navigation', 'sf-private-records' ),
		'items_list'            => __( 'Private records list', 'sf-private-records' ),
	);

	$args = array(
		'labels'              => $labels,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => false,
		'show_in_rest'        => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'menu_icon'           => 'dashicons-lock',
		'supports'            => array( 'title' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	);

	register_post_type( 'sf_private_record', $args );
}
add_action( 'init', 'sf_private_records_register_post_type' );

/**
 * Register the custom REST API routes.
 */
function sf_private_records_register_rest_routes(): void {
	register_rest_route(
		'squirrelforge/v1',
		'/private-records',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'sf_private_records_rest_get_private_records',
			'permission_callback' => 'sf_private_records_rest_can_read_private_records',
			'args'                => array(
				'page'     => array(
					'description'       => __( 'Page of private records to return.', 'sf-private-records' ),
					'type'              => 'integer',
					'default'           => 1,
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => 'sf_private_records_rest_validate_positive_integer',
				),
				'per_page' => array(
					'description'       => __( 'Number of private records to return per page.', 'sf-private-records' ),
					'type'              => 'integer',
					'default'           => 20,
					'minimum'           => 1,
					'maximum'           => 100,
					'sanitize_callback' => 'absint',
					'validate_callback' => 'sf_private_records_rest_validate_positive_integer',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'sf_private_records_register_rest_routes' );

/**
 * Determine whether the current user can read private records through the REST API.
 *
 * @return bool True when the current user has the required capability.
 */
function sf_private_records_rest_can_read_private_records(): bool {
	return current_user_can( 'manage_options' );
}

/**
 * Validate a REST request parameter as a positive integer.
 *
 * @param mixed           $value   Parameter value.
 * @param WP_REST_Request $request REST request.
 * @param string          $param   Parameter name.
 *
 * @return bool True when the value is a positive integer.
 */
function sf_private_records_rest_validate_positive_integer( $value, WP_REST_Request $request, string $param ): bool {
	unset( $request, $param );

	return is_numeric( $value ) && 1 <= absint( $value );
}

/**
 * Return authorized private record data.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_REST_Response REST response containing private record summaries.
 */
function sf_private_records_rest_get_private_records( WP_REST_Request $request ): WP_REST_Response {
	$page     = max( 1, absint( $request->get_param( 'page' ) ) );
	$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );

	$query = new WP_Query(
		array(
			'post_type'              => 'sf_private_record',
			'post_status'            => array( 'publish', 'private' ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$records = array();

	foreach ( $query->posts as $post ) {
		$records[] = array(
			'id'       => (int) $post->ID,
			'title'    => get_the_title( $post ),
			'date'     => get_post_time( DATE_ATOM, false, $post ),
			'modified' => get_post_modified_time( DATE_ATOM, false, $post ),
			'status'   => get_post_status( $post ),
		);
	}

	$response = rest_ensure_response(
		array(
			'data'       => $records,
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
			),
		)
	);

	$response->header( 'X-WP-Total', (int) $query->found_posts );
	$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

	return $response;
}
