<?php
/**
 * Plugin Name: הכיף שבאפיה: תזכורת שבועית להעלות מתכון
 * Description: פעם בשבוע, ביום ובשעה שבעלת האתר בחרה, נשלח אליה מייל קצר עם כפתור "הוספת מתכון חדש". אם כבר עלה מתכון באותו שבוע, התזכורת מדלגת. ההגדרות: מתכונים ← תזכורת שבועית.
 * Version: 1.0.0
 *
 * השעון הוא שעון ישראל. אין תזכורת בשבת.
 * הבדיקה רצה כל שעה (משימה מתוזמנת של וורדפרס, שהשרת מפעיל גם כשאין כניסות לאתר).
 */

defined( 'ABSPATH' ) || exit;

const HAKEF_RR_DAYS = array( 0 => 'ראשון', 1 => 'שני', 2 => 'שלישי', 3 => 'רביעי', 4 => 'חמישי', 5 => 'שישי' );

function hakef_rr_settings() {
	$s = get_option( 'hakef_rr', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array(
		'enabled' => 1,
		'day'     => 0,   // ראשון
		'hour'    => 10,  // 10:00
		'chosen'  => 0,   // האם בעלת האתר כבר בחרה בעצמה
		'start'   => '',  // לא לשלוח לפני התאריך הזה (Y-m-d)
	) );
}

function hakef_rr_now() {
	return new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Jerusalem' ) );
}

/* ------------------------------------------------------------------ הבדיקה השעתית */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'hakef_rr_tick' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'hakef_rr_tick' );
	}
} );

add_action( 'hakef_rr_tick', 'hakef_rr_maybe_send' );

/** שולח אם הגיע היום והשעה, אם עוד לא נשלח השבוע, ואם לא עלה מתכון בשבוע האחרון. */
function hakef_rr_maybe_send( $force = false ) {
	$s   = hakef_rr_settings();
	$now = hakef_rr_now();
	if ( ! $force ) {
		if ( ! $s['enabled'] || 6 === (int) $now->format( 'w' ) ) {
			return 'off';
		}
		if ( $s['start'] && $now->format( 'Y-m-d' ) < $s['start'] ) {
			return 'not-yet';
		}
		if ( (int) $now->format( 'w' ) !== (int) $s['day'] || (int) $now->format( 'G' ) < (int) $s['hour'] ) {
			return 'not-now';
		}
		$week = $now->format( 'o-W' );
		if ( get_option( 'hakef_rr_last' ) === $week ) {
			return 'sent';
		}
		update_option( 'hakef_rr_last', $week, false );
		$recent = get_posts( array( 'post_type' => 'recipe', 'post_status' => 'publish', 'numberposts' => 1, 'date_query' => array( array( 'after' => '6 days ago' ) ) ) );
		if ( $recent ) {
			return 'skip-recent';
		}
	}
	return hakef_rr_send( get_option( 'admin_email' ) ) ? 'mailed' : 'mail-failed';
}

/* ------------------------------------------------------------------ המייל */
function hakef_rr_btn( $url, $label ) {
	return '<a href="' . esc_url( $url ) . '" style="display:inline-block;background:#e890a6;color:#2a1a1f;font-weight:700;text-decoration:none;padding:13px 28px;border-radius:999px;font-family:Arial,\'Segoe UI\',sans-serif;font-size:16px;">' . esc_html( $label ) . '</a>';
}

/** מסגרת מייל ממותגת: פס עליון בשזיף עם הלוגו, ותוכן על לבן */
function hakef_rr_wrap( $inner ) {
	$font = "font-family:Arial,'Segoe UI',sans-serif;";
	return '<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
		. '<body style="margin:0;padding:0;background:#f6e9ed;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6e9ed;"><tr><td align="center" style="padding:24px 12px;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;">'
		. '<tr><td style="background:#2a1a1f;border-radius:14px 14px 0 0;padding:22px;text-align:center;">'
		. '<img src="' . esc_url( home_url( '/assets/img/logo-light-200.png' ) ) . '" width="120" alt="הכיף שבאפיה" style="display:inline-block;border:0;"></td></tr>'
		. '<tr><td dir="rtl" style="padding:28px 30px 18px;text-align:right;' . $font . 'color:#3a2a2e;font-size:16px;line-height:1.75;">' . $inner . '</td></tr>'
		. '<tr><td dir="rtl" style="padding:16px 30px 22px;border-top:1px solid #f1e3e7;text-align:right;' . $font . 'font-size:13px;line-height:1.7;color:#8a7178;">'
		. 'הכיף שבאפיה · <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#b4505f;">hakef.com</a></td></tr>'
		. '</table></td></tr></table></body></html>';
}

function hakef_rr_send( $to ) {
	$s     = hakef_rr_settings();
	$new   = admin_url( 'post-new.php?post_type=recipe' );
	$set   = admin_url( 'edit.php?post_type=recipe&page=hakef-reminder' );
	$count = (int) wp_count_posts( 'recipe' )->publish;
	$p     = 'margin:0 0 14px;';
	$inner = '<p style="margin:0 0 14px;font-size:20px;font-weight:700;color:#2a1a1f;">שלום רחלי,</p>'
		. '<p style="' . $p . '">זו התזכורת השבועית להעלות מתכון לאתר. זה לוקח בערך 2 דקות:</p>'
		. '<p style="margin:0 0 22px;">' . hakef_rr_btn( $new, 'הוספת מתכון חדש' ) . '</p>'
		. '<ol style="margin:0 0 16px;padding:0 20px 0 0;">'
		. '<li>כותבים שם למתכון ובוחרים תמונה.</li>'
		. '<li>מצרכים: כל מצרך בשורה נפרדת.</li>'
		. '<li>אופן ההכנה: כל שלב בשורה נפרדת.</li>'
		. '<li>לוחצים "פרסום". העיצוב נעשה לבד.</li></ol>'
		. '<p style="' . $p . '">כל מתכון חדש עוזר לאתר להופיע בגוגל ומביא לקוחות חדשים. כרגע יש באתר ' . $count . ' מתכונים.</p>'
		. '<p style="margin:0;font-size:14px;color:#8a7178;">אין מתכון השבוע? אפשר פשוט לדלג. לשינוי היום או השעה, או להפסקת התזכורות: <a href="' . esc_url( $set ) . '" style="color:#b4505f;">הגדרות התזכורת</a>.</p>';
	return wp_mail( $to, 'תזכורת שבועית: מתכון חדש לאתר', hakef_rr_wrap( $inner ), array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/* ------------------------------------------------------------------ מסך ההגדרות: מתכונים ← תזכורת שבועית */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=recipe', 'תזכורת שבועית', 'תזכורת שבועית', 'edit_recipes', 'hakef-reminder', 'hakef_rr_page' );
} );

// גם לתפקיד "עורכת מתכונים" (שהתפריט שלו מצומצם) המסך נשאר
add_action( 'admin_post_hakef_rr_save', function () {
	if ( ! current_user_can( 'edit_recipes' ) || ! check_admin_referer( 'hakef_rr_save' ) ) {
		wp_die( 'אין הרשאה.' );
	}
	$old = hakef_rr_settings();
	$day = isset( $_POST['day'] ) ? (int) $_POST['day'] : 0;
	$hr  = isset( $_POST['hour'] ) ? (int) $_POST['hour'] : 10;
	$new = array(
		'enabled' => empty( $_POST['enabled'] ) ? 0 : 1,
		'day'     => isset( HAKEF_RR_DAYS[ $day ] ) ? $day : 0,
		'hour'    => max( 6, min( 23, $hr ) ),
		'chosen'  => 1,
		'start'   => '',
	);
	update_option( 'hakef_rr', $new, false );
	// אם התזכורת לשבוע הזה עוד לא נשלחה, השינוי חל כבר השבוע
	$notify = get_option( 'hakef_rr_notify' );
	if ( $notify && is_email( $notify ) && ( $new !== $old ) ) {
		$who  = wp_get_current_user();
		$text = $new['enabled'] ? 'כל יום ' . HAKEF_RR_DAYS[ $new['day'] ] . ' בשעה ' . $new['hour'] . ':00' : 'התזכורות הופסקו';
		wp_mail( $notify, 'תזכורת המתכונים עודכנה: ' . $text, hakef_rr_wrap( '<p style="margin:0 0 12px;">' . esc_html( $who->display_name ) . ' עדכנה את התזכורת השבועית באתר.</p><p style="margin:0;font-size:18px;font-weight:700;">' . esc_html( $text ) . '</p>' ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=recipe&page=hakef-reminder&saved=1' ) );
	exit;
} );

function hakef_rr_page() {
	$s     = hakef_rr_settings();
	$saved = ! empty( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="wrap" style="max-width:640px"><h1>תזכורת שבועית להעלות מתכון</h1>';
	if ( $saved ) {
		echo '<div class="notice notice-success"><p><strong>נשמר.</strong> ' . ( $s['enabled'] ? 'התזכורת תגיע כל יום ' . esc_html( HAKEF_RR_DAYS[ $s['day'] ] ) . ' בשעה ' . (int) $s['hour'] . ':00.' : 'התזכורות הופסקו.' ) . '</p></div>';
	}
	echo '<p style="font-size:15px">פעם בשבוע יגיע אלייך מייל קצר עם כפתור "הוספת מתכון חדש". אם כבר העלית מתכון באותו שבוע, התזכורת מדלגת.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="font-size:15px">';
	wp_nonce_field( 'hakef_rr_save' );
	echo '<input type="hidden" name="action" value="hakef_rr_save">';
	echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( $s['enabled'], 1, false ) . '> לשלוח לי תזכורת שבועית</label></p>';
	echo '<p><label for="rr-day"><strong>באיזה יום?</strong></label><br><select id="rr-day" name="day" style="min-width:200px;font-size:16px">';
	foreach ( HAKEF_RR_DAYS as $i => $name ) {
		echo '<option value="' . (int) $i . '"' . selected( (int) $s['day'], $i, false ) . '>יום ' . esc_html( $name ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label for="rr-hour"><strong>באיזו שעה?</strong></label><br><select id="rr-hour" name="hour" style="min-width:200px;font-size:16px">';
	for ( $h = 6; $h <= 23; $h++ ) {
		echo '<option value="' . (int) $h . '"' . selected( (int) $s['hour'], $h, false ) . '>' . (int) $h . ':00</option>';
	}
	echo '</select></p>';
	submit_button( 'שמירה', 'primary large' );
	echo '</form></div>';
}

/* ------------------------------------------------------------------ שורה בקופסת המתכונים בלוח הבקרה */
add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'edit_recipes' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'hakef_rr_widget', 'תזכורת שבועית', function () {
		$s = hakef_rr_settings();
		$t = $s['enabled'] ? 'כל יום ' . HAKEF_RR_DAYS[ $s['day'] ] . ' בשעה ' . (int) $s['hour'] . ':00' : 'כבויה';
		echo '<p style="font-size:14px">התזכורת להעלות מתכון: <strong>' . esc_html( $t ) . '</strong> · <a href="' . esc_url( admin_url( 'edit.php?post_type=recipe&page=hakef-reminder' ) ) . '">שינוי</a></p>';
	} );
} );
