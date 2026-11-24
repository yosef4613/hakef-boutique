<?php
defined( 'ABSPATH' ) || exit;
hakef_set_head( array( 'title' => 'העמוד לא נמצא | ' . HAKEF_SITE['name'], 'noindex' => true ) );
get_header();
?>
  <section class="page-head container" style="padding-top:calc(var(--header-h) + 80px)">
    <p class="eyebrow">404</p>
    <h1>העמוד הזה לא נמצא</h1>
    <p class="lead">אולי הוא עבר מקום. אפשר להמשיך מכאן:</p>
    <p><a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">לדף הבית <span aria-hidden="true">←</span></a></p>
  </section>
  <div class="container prose">
    <ul class="custom-list custom-list--wide">
      <li><a class="text-link" href="<?php echo esc_url( home_url( '/בר-קינוחים-לאירועים/' ) ); ?>">בר קינוחים לאירועים</a></li>
      <li><a class="text-link" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">כל המוצרים</a></li>
      <li><a class="text-link" href="<?php echo esc_url( get_post_type_archive_link( 'recipe' ) ); ?>">מתכונים</a></li>
    </ul>
  </div>
<?php
get_footer();
