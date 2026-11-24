<?php
/** כל המתכונים */
defined( 'ABSPATH' ) || exit;

list( $crumbs, $crumbs_ld ) = hakef_breadcrumbs( array( array( 'מתכונים', null ) ) );
$items = array();
$pos   = 1;
if ( have_posts() ) {
	foreach ( $GLOBALS['wp_query']->posts as $p ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'url' => get_permalink( $p ), 'name' => get_the_title( $p ) );
	}
}
hakef_set_head( array(
	'title'     => 'מתכונים לעוגות, עוגיות וקינוחים | ' . HAKEF_SITE['name'],
	'desc'      => 'מתכונים ביתיים לעוגות, עוגיות וקינוחים מהמטבח של הכיף שבאפיה: מצרכים ואופן הכנה, צעד אחר צעד.',
	'canonical' => get_post_type_archive_link( 'recipe' ),
	'ld'        => array( $crumbs_ld, array( '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'מתכונים', 'itemListElement' => $items ) ),
) );
get_header();
?>
  <div class="page-band">
    <figure class="page-band__back" aria-hidden="true"><img src="<?php echo esc_url( home_url( '/assets/img/rec-coffee-nougat-1200.webp' ) ); ?>" width="1600" height="1196" alt="" fetchpriority="high" decoding="async"></figure>
    <?php echo $crumbs; // phpcs:ignore ?>
    <header class="page-head container">
      <p class="eyebrow">מתכונים</p>
      <h1>מתכונים מהמטבח של <?php echo esc_html( HAKEF_SITE['name'] ); ?></h1>
      <p class="lead">מתכונים ביתיים לעוגות, עוגיות וקינוחים, צעד אחר צעד. ואם אין זמן לאפות, אנחנו כאן.</p>
    </header>
  </div>
  <section class="container section--tight overlap" aria-label="רשימת המתכונים">
    <?php if ( have_posts() ) : ?>
    <div class="r-grid">
      <?php
      while ( have_posts() ) {
		the_post();
		echo hakef_recipe_card( get_the_ID() ); // phpcs:ignore
      }
      ?>
    </div>
    <?php the_posts_pagination( array( 'prev_text' => 'הקודם', 'next_text' => 'הבא' ) ); ?>
    <?php else : ?>
    <p class="lead">מתכונים חדשים בדרך.</p>
    <?php endif; ?>
  </section>
  <?php if ( function_exists( 'hakef_news_box' ) ) : ?>
  <section class="container section--tight news-band" aria-label="רשימת התפוצה">
    <?php echo hakef_news_box( get_post_type_archive_link( 'recipe' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- נבנה עם esc_* ?>
  </section>
  <?php endif; ?>
<?php
echo hakef_cta_band(); // phpcs:ignore
get_footer();
