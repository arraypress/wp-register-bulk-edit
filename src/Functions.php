<?php
/**
 * Bulk Edit Helper Functions
 *
 * Global helper functions for registering bulk edit fields.
 *
 * @package     ArrayPress\WP\RegisterBulkEdit
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterBulkEdit\BulkEdit;

if ( ! function_exists( 'register_bulk_edit_fields' ) ):
	/**
	 * Register bulk edit fields for posts or custom post types.
	 *
	 * @param string|array $post_types Post type(s) to register fields for.
	 * @param array        $fields     Array of field configurations.
	 *
	 * @return void
	 */
	function register_bulk_edit_fields( $post_types, array $fields ): void {
		$post_types = (array) $post_types;

		foreach ( $post_types as $post_type ) {
			new BulkEdit( $fields, $post_type );
		}
	}
endif;
