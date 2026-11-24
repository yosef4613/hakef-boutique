<?php
/**
 * Plugin Name: הכיף שבאפיה: ייבוא מתכונים ראשוני
 * Description: מייבא פעם אחת את המתכונים מהאתר הישן (hakef-seed/recipes.json) כשמנהל נכנס לממשק. אחרי הייבוא אפשר למחוק את הקובץ.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
	if ( get_option( 'hakef_recipes_seeded' ) || ! current_user_can( 'manage_options' ) || ! post_type_exists( 'recipe' ) ) {
		return;
	}
	$file = __DIR__ . '/hakef-seed/recipes.json';
	if ( ! is_readable( $file ) ) {
		return;
	}
	$items = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $r ) {
		if ( get_page_by_path( $r['slug'], OBJECT, 'recipe' ) ) {
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'   => 'recipe',
			'post_status' => 'publish',
			'post_title'  => $r['title'],
			'post_name'   => $r['slug'],
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			foreach ( array( 'summary', 'ingredients', 'steps', 'time', 'yield', 'tip' ) as $k ) {
				update_post_meta( $id, '_hakef_' . $k, isset( $r[ $k ] ) ? $r[ $k ] : '' );
			}
		}
	}
	update_option( 'hakef_recipes_seeded', 1 );
} );
