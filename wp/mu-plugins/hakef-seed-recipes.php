<?php
/**
 * Plugin Name: הכיף שבאפיה: ייבוא מתכונים ראשוני
 * Description: מייבא את המתכונים מהאתר הישן (hakef-seed/recipes.json) כשמנהל נכנס לממשק, כולל התמונות שהיו להם. רץ פעם אחת לכל גרסה, ולא נוגע במתכונים קיימים שכבר יש להם תמונה. אחרי הייבוא אפשר למחוק את הקובץ.
 */
defined( 'ABSPATH' ) || exit;

const HAKEF_SEED_VERSION = 2;

add_action( 'admin_init', function () {
	if ( (int) get_option( 'hakef_recipes_seeded' ) >= HAKEF_SEED_VERSION || ! current_user_can( 'manage_options' ) || ! post_type_exists( 'recipe' ) ) {
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
		$existing = get_page_by_path( $r['slug'], OBJECT, 'recipe' );
		if ( $existing ) {
			$id = $existing->ID;
		} else {
			$id = wp_insert_post( array(
				'post_type'   => 'recipe',
				'post_status' => 'publish',
				'post_title'  => $r['title'],
				'post_name'   => $r['slug'],
			) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			foreach ( array( 'summary', 'ingredients', 'steps', 'time', 'yield', 'tip' ) as $k ) {
				update_post_meta( $id, '_hakef_' . $k, isset( $r[ $k ] ) ? $r[ $k ] : '' );
			}
		}
		if ( has_post_thumbnail( $id ) ) {
			continue;
		}
		hakef_seed_set_image( $id, $r );
	}
	update_option( 'hakef_recipes_seeded', HAKEF_SEED_VERSION );
} );

/** התמונה שהייתה למתכון באתר הישן: קודם לפי מספר הקובץ בספריית המדיה, ואם הוא לא קיים, הורדה מהכתובת המקורית. */
function hakef_seed_set_image( $post_id, array $r ) {
	$aid = isset( $r['attachment_id'] ) ? (int) $r['attachment_id'] : 0;
	if ( $aid && wp_attachment_is_image( $aid ) ) {
		set_post_thumbnail( $post_id, $aid );
		return;
	}
	if ( empty( $r['image_url'] ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$new = media_sideload_image( $r['image_url'], $post_id, isset( $r['alt'] ) ? $r['alt'] : $r['title'], 'id' );
	if ( ! is_wp_error( $new ) ) {
		if ( ! empty( $r['alt'] ) ) {
			update_post_meta( $new, '_wp_attachment_image_alt', $r['alt'] );
		}
		set_post_thumbnail( $post_id, $new );
	}
}
