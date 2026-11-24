<?php /* נוצר אוטומטית על ידי build/build.py. לא לערוך ידנית. */ defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#141210">
<?php hakef_head_meta(); ?>
<link rel="icon" href="/assets/img/logo-120.png">
<link rel="apple-touch-icon" href="/assets/img/logo-240.png">
<link rel="preload" href="/assets/fonts/Playpen-hebrew.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/Fredoka-hebrew.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/css/fonts.css?v=0cddc6be">
<link rel="stylesheet" href="/assets/css/site.css?v=a01df662">
<script>
  /* העדפת תנועה: לפני הציור הראשון, כדי שלא יהיה הבהוב */
  (function () {
    var pref = null;
    try { pref = localStorage.getItem('hakef-motion'); } catch (e) {}
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.documentElement.dataset.motion = pref ? pref : (reduce ? 'off' : 'on');
    document.documentElement.classList.add('js');
  })();
</script>

<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">דילוג לתוכן</a>

<header class="site-header" id="top">
  <div class="container site-header__row">
    <a class="brand" href="/" aria-label="הכיף שבאפיה — דף הבית">
      <img class="brand__logo" src="/assets/img/logo-light-120.webp" srcset="/assets/img/logo-light-140.webp 2x" width="133" height="120" alt="">
      <span class="brand__text"><small>קייטרינג קינוחים<br>בוטיק</small></span>
    </a>
    <nav class="main-nav" aria-label="ניווט ראשי">
      <ul id="nav-list">
        <li><a href="/בר-קינוחים-לאירועים/">בר קינוחים</a></li>
        <li><a href="/product-category/עוגות/">עוגות</a></li>
        <li><a href="/product-category/פטיפורים/">פטיפורים</a></li>
        <li><a href="/shop/">כל המוצרים</a></li>
        <li><a href="/מתכונים/">מתכונים</a></li>
        <li><a href="/about/">אודות</a></li>
        <li><a href="/contact/">צור קשר</a></li>
      </ul>
    </nav>
    <div class="site-header__tools">
      <button class="motion-toggle" type="button" aria-pressed="false" data-motion-toggle>
        <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path d="M4 12c2.5-5 5.5-5 8 0s5.5 5 8 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <span class="motion-toggle__label">תנועה: פועלת</span>
      </button>
      <a class="icon-link" href="https://wa.me/972509153197?text=%D7%A9%D7%9C%D7%95%D7%9D%2C%20%D7%94%D7%92%D7%A2%D7%AA%D7%99%20%D7%9E%D7%94%D7%90%D7%AA%D7%A8%20%D7%A9%D7%9C%20%D7%94%D7%9B%D7%99%D7%A3%20%D7%A9%D7%91%D7%90%D7%A4%D7%99%D7%94%20%D7%95%D7%90%D7%A9%D7%9E%D7%97%20%D7%9C%D7%A4%D7%A8%D7%98%D7%99%D7%9D" target="_blank" rel="noopener" aria-label="שליחת הודעה בוואטסאפ" data-wa><svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.1.7a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3Z"/></svg></a>
      <button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav-list" data-menu-btn>
        <span class="visually-hidden">תפריט</span>
        <span class="menu-btn__bars" aria-hidden="true"></span>
      </button>
    </div>
  </div>
</header>

<main id="main">
