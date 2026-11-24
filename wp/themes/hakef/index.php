<?php
/* וורדפרס באתר הזה מציג רק מתכונים; כל השאר מופנה לדף הבית ב-functions.php */
defined( 'ABSPATH' ) || exit;
wp_safe_redirect( home_url( '/' ), 301 );
exit;
