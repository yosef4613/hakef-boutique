<?php
/**
 * הכיף שבאפיה: תבנית למתכונים בלבד.
 * הכותרת והתחתית (header.php / footer.php) נוצרות אוטומטית על ידי build/build.py, כדי שיהיו זהות לשאר האתר.
 */

defined( 'ABSPATH' ) || exit;

const HAKEF_SITE = array(
	'name'  => 'הכיף שבאפיה',
	'wa'    => '972509153197',
	'phone' => '050-915-3197',
);

add_action( 'after_setup_theme', function () {
	add_theme_support( 'post-thumbnails' );
	add_image_size( 'hakef-card', 640, 800, true );
	add_image_size( 'hakef-hero', 1400, 1050, false );
} );

/* ניקוי: בלי סקריפטים וסגנונות שוורדפרס מוסיף ושלא צריך */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_enqueue_scripts', function () {
	foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $h ) {
		wp_dequeue_style( $h );
	}
}, 100 );

/* באתר הזה וורדפרס מציג רק מתכונים. כל כתובת אחרת שמגיעה אליו עוברת לדף הבית */
add_action( 'template_redirect', function () {
	if ( is_singular( 'recipe' ) || is_post_type_archive( 'recipe' ) || is_404() || is_robots() ) {
		return;
	}
	// דף הבית הוא קובץ סטטי (index.html). אם בכל זאת וורדפרס התבקש להציג אותו, מגישים את הקובץ, בלי הפניה במעגל.
	if ( is_front_page() || is_home() ) {
		$static = ABSPATH . 'index.html';
		if ( is_readable( $static ) ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			readfile( $static ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit;
		}
		return; // לפני העלייה לאוויר: index.php מציג הודעה קצרה
	}
	if ( get_query_var( 'sitemap' ) || get_query_var( 'sitemap-subtype' ) || get_query_var( 'sitemap-stylesheet' ) ) {
		return;
	}
	wp_safe_redirect( home_url( '/' ), 301 );
	exit;
}, 20 );

/* ------------------------------------------------------------------ ראש העמוד */
function hakef_set_head( array $args ) {
	$GLOBALS['hakef_head'] = $args;
}

function hakef_head_meta() {
	$h     = isset( $GLOBALS['hakef_head'] ) ? $GLOBALS['hakef_head'] : array();
	$title = isset( $h['title'] ) ? $h['title'] : HAKEF_SITE['name'];
	$desc  = isset( $h['desc'] ) ? $h['desc'] : '';
	$canon = isset( $h['canonical'] ) ? $h['canonical'] : home_url( add_query_arg( array() ) );
	$img   = ! empty( $h['image'] ) ? $h['image'] : home_url( '/assets/img/og-image.jpg' );
	echo '<title>' . esc_html( $title ) . "</title>\n";
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . "\">\n";
	}
	if ( ! empty( $h['noindex'] ) ) {
		echo "<meta name=\"robots\" content=\"noindex\">\n";
	}
	echo '<link rel="canonical" href="' . esc_url( $canon ) . "\">\n";
	echo '<meta property="og:type" content="' . esc_attr( isset( $h['og_type'] ) ? $h['og_type'] : 'website' ) . "\">\n";
	echo "<meta property=\"og:locale\" content=\"he_IL\">\n";
	echo '<meta property="og:site_name" content="' . esc_attr( HAKEF_SITE['name'] ) . "\">\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . "\">\n";
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . "\">\n";
	}
	echo '<meta property="og:url" content="' . esc_url( $canon ) . "\">\n";
	echo '<meta property="og:image" content="' . esc_url( $img ) . "\">\n";
	echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
	foreach ( isset( $h['ld'] ) ? $h['ld'] : array() as $ld ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}
}

/* ------------------------------------------------------------------ עזרים */
function hakef_wa( $text ) {
	return 'https://wa.me/' . HAKEF_SITE['wa'] . '?text=' . rawurlencode( $text );
}

/** סמל וואטסאפ (זהה לזה שבשאר האתר) */
function hakef_wa_icon() {
	return '<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.1.7a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3Z"/></svg>';
}

function hakef_summary( $post_id ) {
	$m = function_exists( 'hakef_recipe_meta' ) ? hakef_recipe_meta( $post_id ) : array( 'summary' => '' );
	return trim( $m['summary'] ) ? trim( $m['summary'] ) : 'מתכון מהמטבח של הכיף שבאפיה.';
}

/** תמונת המתכון, או כריכה מעוצבת עם השם כשאין תמונה */
function hakef_recipe_visual( $post_id, $size, $sizes, $lazy = true ) {
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail( $post_id, $size, array(
			'sizes'    => $sizes,
			'loading'  => $lazy ? 'lazy' : 'eager',
			'decoding' => 'async',
			'alt'      => get_the_title( $post_id ),
		) );
	}
	return '<div class="recipe-cover" aria-hidden="true"><span class="recipe-cover__brand">' . esc_html( HAKEF_SITE['name'] ) . ' · מתכון</span>'
		. '<span class="recipe-cover__title">' . esc_html( get_the_title( $post_id ) ) . '</span></div>';
}

function hakef_recipe_card( $post_id, $heading = 'h2' ) {
	$m    = function_exists( 'hakef_recipe_meta' ) ? hakef_recipe_meta( $post_id ) : array( 'time' => '' );
	$meta = $m['time'] ? '<span class="r-card__meta">' . esc_html( $m['time'] ) . '</span>' : '';
	return '<article class="r-card"><a href="' . esc_url( get_permalink( $post_id ) ) . '">'
		. hakef_recipe_visual( $post_id, 'hakef-card', '(max-width: 760px) 92vw, 380px' )
		. '<' . $heading . ' class="r-card__name">' . esc_html( get_the_title( $post_id ) ) . '</' . $heading . '>'
		. '<p class="r-card__text">' . esc_html( hakef_summary( $post_id ) ) . '</p>' . $meta . '</a></article>';
}

function hakef_breadcrumbs( array $items ) {
	$parts = array( '<a href="' . esc_url( home_url( '/' ) ) . '">דף הבית</a>' );
	$ld    = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => 'דף הבית', 'item' => home_url( '/' ) ) );
	$pos   = 2;
	foreach ( $items as $it ) {
		list( $name, $url ) = $it;
		$parts[] = $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : '<span aria-current="page">' . esc_html( $name ) . '</span>';
		$el      = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $name );
		if ( $url ) {
			$el['item'] = $url;
		}
		$ld[] = $el;
	}
	return array(
		'<nav class="crumbs container" aria-label="פירורי לחם">' . implode( ' <span aria-hidden="true">/</span> ', $parts ) . '</nav>',
		array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $ld ),
	);
}

function hakef_cta_band() {
	return '<section class="cta-band"><div class="container cta-band__inner"><div><h2>אין זמן לאפות?</h2>'
		. '<p>בר קינוחים, מגשי פטיפורים ועוגות מעוצבות לאירוע שלכם, טריים ובהזמנה אישית. ירושלים והסביבה.</p></div>'
		. '<a class="btn btn--primary" href="' . esc_url( home_url( '/#plan' ) ) . '" data-order="bar">לתכנון הבר והצעת מחיר <span aria-hidden="true">←</span></a></div></section>';
}
