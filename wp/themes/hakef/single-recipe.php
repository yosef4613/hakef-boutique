<?php
/** עמוד מתכון: העיצוב נבנה לבד מהשדות (מצרכים, שלבים, זמן, כמות, טיפ). */
defined( 'ABSPATH' ) || exit;

the_post();
$pid     = get_the_ID();
$m       = hakef_recipe_meta( $pid );
$title   = get_the_title();
$summary = hakef_summary( $pid );
$ings    = hakef_parse_lines( $m['ingredients'] );
$steps   = hakef_parse_lines( $m['steps'] );
$n_ings  = count( array_filter( $ings, function ( $x ) { return 'item' === $x['type']; } ) );
$img_url = has_post_thumbnail() ? get_the_post_thumbnail_url( $pid, 'hakef-hero' ) : '';
// בלי תמונה: כריכה ממותגת, כי גוגל דורש תמונה במתכון (עדיף להעלות צילום אמיתי של המנה)
$ld_img  = $img_url ? $img_url : home_url( '/assets/img/recipe-cover-1200.jpg' );

list( $crumbs, $crumbs_ld ) = hakef_breadcrumbs( array( array( 'מתכונים', get_post_type_archive_link( 'recipe' ) ), array( $title, null ) ) );

$recipe_ld = array(
	'@context'           => 'https://schema.org',
	'@type'              => 'Recipe',
	'name'               => $title,
	'description'        => $summary,
	'author'             => array( '@type' => 'Organization', 'name' => HAKEF_SITE['name'], 'url' => home_url( '/' ) ),
	'datePublished'      => get_the_date( 'c' ),
	'recipeCategory'     => 'קינוחים',
	'inLanguage'         => 'he',
	'recipeIngredient'   => array_values( array_map( function ( $x ) { return $x['text']; }, array_filter( $ings, function ( $x ) { return 'item' === $x['type']; } ) ) ),
	'recipeInstructions' => array_values( array_map( function ( $x ) { return array( '@type' => 'HowToStep', 'text' => $x['text'] ); }, array_filter( $steps, function ( $x ) { return 'item' === $x['type']; } ) ) ),
);
$recipe_ld['image']    = array( $ld_img );
$recipe_ld['keywords'] = $title . ', מתכון, קינוח, אפייה, ' . HAKEF_SITE['name'];
if ( $m['yield'] ) {
	$recipe_ld['recipeYield'] = $m['yield'];
}
if ( $iso = hakef_iso_duration( $m['time'] ) ) {
	$recipe_ld['totalTime'] = $iso;
}

hakef_set_head( array(
	'title'     => $title . ' – מתכון | ' . HAKEF_SITE['name'],
	'desc'      => mb_substr( $summary . ' מצרכים ואופן הכנה, צעד אחר צעד.', 0, 160 ),
	'canonical' => get_permalink(),
	'image'     => $ld_img,
	'og_type'   => 'article',
	'ld'        => array( $crumbs_ld, $recipe_ld ),
) );

get_header();
echo $crumbs; // phpcs:ignore WordPress.Security.EscapeOutput -- נבנה עם esc_*
?>
  <article class="recipe container">
    <header class="recipe__head">
      <p class="eyebrow">מתכון מהמטבח של <?php echo esc_html( HAKEF_SITE['name'] ); ?></p>
      <h1><?php echo esc_html( $title ); ?></h1>
      <p class="lead"><?php echo esc_html( $summary ); ?></p>
      <ul class="recipe__facts">
        <?php if ( $m['time'] ) : ?><li><span>זמן הכנה</span><strong><?php echo esc_html( $m['time'] ); ?></strong></li><?php endif; ?>
        <?php if ( $m['yield'] ) : ?><li><span>כמות</span><strong><?php echo esc_html( $m['yield'] ); ?></strong></li><?php endif; ?>
        <?php if ( $n_ings ) : ?><li><span>מצרכים</span><strong><?php echo (int) $n_ings; ?></strong></li><?php endif; ?>
      </ul>
      <button class="text-link recipe__print" type="button" data-print>הדפסת המתכון</button>
    </header>
    <figure class="recipe__media"><?php echo hakef_recipe_visual( $pid, 'hakef-hero', '(max-width: 900px) 92vw, 620px', false ); // phpcs:ignore ?></figure>
    <div class="recipe__body">
      <?php if ( $ings ) : ?>
      <section class="recipe__ingredients" aria-labelledby="ing-t">
        <h2 id="ing-t">מצרכים</h2>
        <?php
        $open = false;
        foreach ( $ings as $row ) {
			if ( 'heading' === $row['type'] ) {
				if ( $open ) {
					echo '</ul>';
				}
				echo '<h3>' . esc_html( $row['text'] ) . '</h3><ul class="checklist">';
				$open = true;
				continue;
			}
			if ( ! $open ) {
				echo '<ul class="checklist">';
				$open = true;
			}
			echo '<li><label><input type="checkbox"><span>' . esc_html( $row['text'] ) . '</span></label></li>';
        }
        if ( $open ) {
			echo '</ul>';
        }
        ?>
      </section>
      <?php endif; ?>
      <?php if ( $steps ) : ?>
      <section class="recipe__steps" aria-labelledby="steps-t">
        <h2 id="steps-t">אופן ההכנה</h2>
        <?php
        $open = false;
        $n    = 0;
        foreach ( $steps as $row ) {
			if ( 'heading' === $row['type'] ) {
				if ( $open ) {
					echo '</ol>';
				}
				echo '<h3>' . esc_html( $row['text'] ) . '</h3><ol class="steps-list" start="' . ( $n + 1 ) . '">';
				$open = true;
				continue;
			}
			if ( ! $open ) {
				echo '<ol class="steps-list">';
				$open = true;
			}
			$n++;
			echo '<li><span class="steps-num" aria-hidden="true">' . (int) $n . '</span>' . esc_html( $row['text'] ) . '</li>';
        }
        if ( $open ) {
			echo '</ol>';
        }
        ?>
      </section>
      <?php endif; ?>
    </div>
    <?php if ( trim( $m['tip'] ) ) : ?>
    <aside class="recipe__tip"><strong>טיפ מהמטבח</strong><p><?php echo esc_html( $m['tip'] ); ?></p></aside>
    <?php endif; ?>
  </article>
  <section class="container recipe-order" aria-labelledby="order-t">
    <div class="recipe-order__inner">
      <div>
        <h2 id="order-t">רוצים את זה מוכן לאירוע?</h2>
        <p>את <?php echo esc_html( $title ); ?> ועוד עשרות קינוחים אנחנו מכינים טריים לאירועים, בבר קינוחים או במגשים מוכנים. ירושלים והסביבה.</p>
      </div>
      <a class="btn btn--primary" href="<?php echo esc_url( hakef_wa( 'שלום, ראיתי באתר את המתכון "' . $title . '" ואשמח להצעת מחיר לאירוע' ) ); ?>" target="_blank" rel="noopener" data-wa><?php echo hakef_wa_icon(); // phpcs:ignore ?> הצעת מחיר בוואטסאפ</a>
    </div>
  </section>
<?php
if ( function_exists( 'hakef_talk_section' ) ) {
	echo hakef_talk_section( $pid ); // phpcs:ignore WordPress.Security.EscapeOutput -- נבנה עם esc_*
}
$more = get_posts( array( 'post_type' => 'recipe', 'posts_per_page' => 3, 'post__not_in' => array( $pid ), 'orderby' => 'rand' ) );
if ( $more ) {
	echo '<section class="container section--tight" aria-labelledby="more-t"><h2 id="more-t" class="related__title">עוד מתכונים</h2><div class="r-grid">';
	foreach ( $more as $p ) {
		echo hakef_recipe_card( $p->ID, 'h3' ); // phpcs:ignore
	}
	echo '</div></section>';
}
get_footer();
