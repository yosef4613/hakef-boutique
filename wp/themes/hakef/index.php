<?php
/* וורדפרס באתר הזה מציג רק מתכונים; כל השאר מופנה לדף הבית ב-functions.php.
   דף הבית עצמו הוא index.html סטטי. עד שהוא עולה, מוצגת כאן הודעה קצרה. */
defined( 'ABSPATH' ) || exit;
if ( is_front_page() || is_home() ) {
	hakef_set_head( array( 'title' => HAKEF_SITE['name'], 'noindex' => true ) );
	get_header();
	echo '<header class="page-head container" style="padding-top:calc(var(--header-h) + 80px)"><h1>' . esc_html( HAKEF_SITE['name'] ) . '</h1>'
		. '<p class="lead">האתר החדש עולה בקרוב.</p>'
		. '<p><a class="text-link" href="' . esc_url( get_post_type_archive_link( 'recipe' ) ) . '">למתכונים</a></p></header>';
	get_footer();
	exit;
}
wp_safe_redirect( home_url( '/' ), 301 );
exit;
