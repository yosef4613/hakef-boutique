<?php
/**
 * Plugin Name: הכיף שבאפיה: תגובות ורשימת תפוצה
 * Description: תגובות למתכונים (שם ומייל, בלי חשבון וסיסמה, וכל תגובה ממתינה לאישור), סינון ספאם, ורשימת תפוצה עם הסכמה מפורשת ואישור בקישור במייל. הנרשמים מופיעים בממשק תחת "רשימת התפוצה", עם הורדה לאקסל.
 * Version: 1.0.0
 *
 * מותקן כ־mu-plugin (wp-content/mu-plugins).
 *
 * החוק (סעיף 30א לחוק התקשורת): דיוור פרסומי רק למי שהסכים במפורש מראש. לכן:
 *   - תיבת ההסכמה נפרדת ולא מסומנת מראש. תגובה למתכון לבדה אינה הסכמה.
 *   - ההרשמה נכנסת לתוקף רק אחרי לחיצה על קישור האישור במייל (כך אי אפשר לרשום מייל של מישהו אחר).
 *   - נשמרים מועד ההסכמה, נוסח ההסכמה ומועד האישור, כהוכחה.
 *   - אפשר להסיר בכל רגע.
 */

defined( 'ABSPATH' ) || exit;

const HAKEF_CONSENT_TEXT = 'אני רוצה לקבל במייל מתכונים חדשים, עדכונים והצעות (דיוור פרסומי) מהכיף שבאפיה. אפשר להסיר בכל רגע.';

/* ================================================================== תגובות למתכונים */

// תגובות פתוחות רק במתכונים. בכל שאר התוכן סגורות, וגם פינגבקים סגורים.
add_filter( 'comments_open', function ( $open, $post_id ) {
	return 'recipe' === get_post_type( $post_id );
}, 20, 2 );
add_filter( 'pings_open', '__return_false', 20 );

// הגדרות התגובות קבועות בקוד, כדי ששינוי בטעות בהגדרות לא יפתח פרצה
foreach ( array(
	'comment_moderation'           => '1', // כל תגובה ממתינה לאישור לפני שהיא מופיעה
	'comment_registration'         => '0', // בלי חשבון: שם ומייל מספיקים
	'require_name_email'           => '1',
	'close_comments_for_old_posts' => '0',
	'moderation_notify'            => '1', // מייל למנהלת על כל תגובה שממתינה
	'thread_comments'              => '1',
	'thread_comments_depth'        => '2', // תגובה ותשובה עליה
	'page_comments'                => '0',
	'show_comments_cookies_opt_in' => '0', // בלי עוגיות
	'show_avatars'                 => '0',
) as $hakef_opt => $hakef_val ) {
	add_filter( 'pre_option_' . $hakef_opt, function () use ( $hakef_val ) {
		return $hakef_val;
	} );
}
unset( $hakef_opt, $hakef_val );

/** אסימון לטופס: זמן ההצגה עם חתימה. כך יודעים שהטופס הגיע מהאתר ולא מולא בשבריר שנייה. */
function hakef_form_token() {
	$t = (string) time();
	return $t . '.' . substr( hash_hmac( 'sha256', $t, wp_salt( 'nonce' ) ), 0, 20 );
}

/** בדיקה שמילא אדם ולא תוכנה. מחזיר מחרוזת ריקה כשהכול תקין, אחרת את הסיבה. */
function hakef_form_human_check( array $src ) {
	$tok = isset( $src['hakef_ts'] ) ? (string) wp_unslash( $src['hakef_ts'] ) : '';
	$js  = isset( $src['hakef_js'] ) ? (string) wp_unslash( $src['hakef_js'] ) : '';
	$hp  = isset( $src['website'] ) ? trim( (string) wp_unslash( $src['website'] ) ) : '';
	if ( '' !== $hp ) {
		return 'trap'; // שדה נסתר שרק תוכנות ממלאות
	}
	if ( ! preg_match( '/^(\d{10})\.([0-9a-f]{20})$/', $tok, $m ) || ! hash_equals( substr( hash_hmac( 'sha256', $m[1], wp_salt( 'nonce' ) ), 0, 20 ), $m[2] ) ) {
		return 'token';
	}
	$age = time() - (int) $m[1];
	if ( $age < 3 ) {
		return 'fast';
	}
	if ( $age > 60 * DAY_IN_SECONDS ) {
		return 'old';
	}
	if ( ! hash_equals( $tok, $js ) ) {
		return 'nojs'; // סקריפט האתר ממלא את השדה הזה. תוכנות ספאם בדרך כלל לא מריצות אותו
	}
	return '';
}

/** הגבלת קצב לפי כתובת IP */
function hakef_rate_ok( $bucket, $max, $window ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$key = 'hakef_rl_' . $bucket . '_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $key, $n + 1, $window );
	return true;
}

/** אסימון אישי (אותיות באנגלית וספרות, רגיש לאותיות גדולות וקטנות) */
function hakef_clean_token( $t ) {
	return preg_replace( '/[^A-Za-z0-9]/', '', (string) $t );
}

function hakef_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/** חזרה לעמוד המתכון עם הודעה, בלי לשמור את התגובה */
function hakef_talk_back( $post_id, $code ) {
	$url = get_permalink( (int) $post_id );
	if ( ! $url ) {
		$url = get_post_type_archive_link( 'recipe' );
	}
	wp_safe_redirect( add_query_arg( 'talk', $code, $url ) . '#talk-form' );
	exit;
}

function hakef_is_team( $user_id ) {
	return $user_id && user_can( (int) $user_id, 'moderate_comments' );
}

// סינון לפני שמירה. תשובות מהממשק (רחלי) עוברות בלי סינון.
add_filter( 'preprocess_comment', function ( $c ) {
	if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
		return $c;
	}
	$pid = (int) $c['comment_post_ID'];
	if ( ! empty( $c['comment_type'] ) && 'comment' !== $c['comment_type'] ) {
		hakef_talk_back( $pid, 'bot' );
	}
	if ( hakef_form_human_check( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- טופס ציבורי, נבדק באסימון חתום
		hakef_talk_back( $pid, 'bot' );
	}
	$text = (string) $c['comment_content'];
	$name = (string) $c['comment_author'];
	if ( ! preg_match( '/\p{Hebrew}/u', $text ) ) {
		hakef_talk_back( $pid, 'hebrew' );
	}
	if ( preg_match( '~https?://|www\.|\b[a-z0-9-]+\.(com|net|org|ru|info|xyz|top|io|biz|online|site|shop|store|link|click)\b~i', $text . ' ' . $name ) ) {
		hakef_talk_back( $pid, 'links' );
	}
	if ( mb_strlen( $text ) > 2000 || mb_strlen( $name ) > 60 ) {
		hakef_talk_back( $pid, 'long' );
	}
	if ( ! hakef_rate_ok( 'c', 6, 10 * MINUTE_IN_SECONDS ) ) {
		hakef_talk_back( $pid, 'many' );
	}
	$c['comment_author_url'] = '';
	return $c;
}, 1 );

// תגובה כפולה או מהירה מדי: חזרה לעמוד עם הודעה ברורה, במקום מסך שגיאה של וורדפרס
add_action( 'comment_duplicate_trigger', function ( $data ) {
	if ( ! is_admin() && ! empty( $data['comment_post_ID'] ) ) {
		hakef_talk_back( $data['comment_post_ID'], 'dup' );
	}
} );
add_action( 'comment_flood_trigger', function () {
	if ( ! is_admin() && ! empty( $_POST['comment_post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		hakef_talk_back( (int) $_POST['comment_post_ID'], 'many' ); // phpcs:ignore WordPress.Security.NonceVerification
	}
} );

// כל תגובה מהאתר ממתינה לאישור. רק תשובות של צוות האתר מתפרסמות מיד.
add_filter( 'pre_comment_approved', function ( $approved, $data ) {
	if ( is_wp_error( $approved ) || in_array( $approved, array( 'spam', 'trash' ), true ) ) {
		return $approved;
	}
	return hakef_is_team( isset( $data['user_id'] ) ? $data['user_id'] : 0 ) ? 1 : 0;
}, 99, 2 );

// אחרי שליחה: חזרה למתכון עם הודעת תודה
add_filter( 'comment_post_redirect', function ( $location, $comment ) {
	if ( 'recipe' !== get_post_type( $comment->comment_post_ID ) ) {
		return $location;
	}
	$code = '1' === (string) $comment->comment_approved ? 'live' : 'sent';
	if ( ! empty( $_POST['hakef_news'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$code .= '-news';
	}
	return add_query_arg( 'talk', $code, get_permalink( $comment->comment_post_ID ) ) . '#talk';
}, 10, 2 );

// מי שסימן בטופס התגובה שהוא רוצה להצטרף: מקבל מייל אישור. התגובה עצמה לא מספיקה.
add_action( 'comment_post', function ( $id, $approved, $data ) {
	if ( 'spam' === $approved || empty( $_POST['hakef_news'] ) || 'recipe' !== get_post_type( $data['comment_post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	hakef_sub_request( $data['comment_author_email'], $data['comment_author'], 'comment', (int) $data['comment_post_ID'] );
}, 10, 3 );

/* ------------------------------------------------------------------ תצוגה בעמוד המתכון */
function hakef_he_date( $mysql_date ) {
	$months = array( 'בינואר', 'בפברואר', 'במרץ', 'באפריל', 'במאי', 'ביוני', 'ביולי', 'באוגוסט', 'בספטמבר', 'באוקטובר', 'בנובמבר', 'בדצמבר' );
	$ts     = strtotime( $mysql_date );
	return (int) gmdate( 'j', $ts ) . ' ' . $months[ (int) gmdate( 'n', $ts ) - 1 ] . ' ' . gmdate( 'Y', $ts );
}

function hakef_talk_item( WP_Comment $c, $children = array() ) {
	$team = hakef_is_team( $c->user_id );
	$name = $team ? HAKEF_SITE['name'] : $c->comment_author;
	$html = '<li class="talk__item' . ( $team ? ' talk__item--team' : '' ) . '" id="comment-' . (int) $c->comment_ID . '">'
		. '<p class="talk__meta"><strong>' . esc_html( $name ) . '</strong>'
		. '<time datetime="' . esc_attr( mysql2date( 'c', $c->comment_date ) ) . '">' . esc_html( hakef_he_date( $c->comment_date ) ) . '</time></p>'
		. '<div class="talk__text">' . wpautop( esc_html( $c->comment_content ) ) . '</div>';
	if ( $children ) {
		$html .= '<ol class="talk__replies">';
		foreach ( $children as $ch ) {
			$html .= hakef_talk_item( $ch );
		}
		$html .= '</ol>';
	}
	return $html . '</li>';
}

function hakef_notice( $text, $kind = 'ok' ) {
	return '<p class="notice-box' . ( 'error' === $kind ? ' notice-box--error' : '' ) . '" role="' . ( 'error' === $kind ? 'alert' : 'status' ) . '">' . $text . '</p>';
}

function hakef_hidden_human_fields() {
	return '<input type="hidden" name="hakef_ts" value="' . esc_attr( hakef_form_token() ) . '"><input type="hidden" name="hakef_js" value="">'
		. '<p class="hp" aria-hidden="true"><label>אתר אינטרנט <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label></p>';
}

function hakef_privacy_url() {
	return home_url( '/privacy-policy-1/' );
}

/** אזור התגובות ותיבת ההרשמה, מתחת למתכון */
function hakef_talk_section( $post_id ) {
	$all = get_comments( array( 'post_id' => $post_id, 'status' => 'approve', 'type' => 'comment', 'orderby' => 'comment_date_gmt', 'order' => 'ASC' ) );
	$top = array();
	$kid = array();
	foreach ( $all as $c ) {
		if ( (int) $c->comment_parent ) {
			$kid[ (int) $c->comment_parent ][] = $c;
		} else {
			$top[] = $c;
		}
	}
	$code     = isset( $_GET['talk'] ) ? sanitize_key( wp_unslash( $_GET['talk'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$messages = array(
		'sent'      => array( 'תודה! התגובה התקבלה, והיא תופיע כאן אחרי אישור.', 'ok' ),
		'sent-news' => array( 'תודה! התגובה התקבלה, והיא תופיע כאן אחרי אישור. שלחנו לכם גם מייל לאישור ההצטרפות לרשימת התפוצה.', 'ok' ),
		'live'      => array( 'התגובה פורסמה.', 'ok' ),
		'live-news' => array( 'התגובה פורסמה.', 'ok' ),
		'hebrew'    => array( 'נא לכתוב את התגובה בעברית.', 'error' ),
		'links'     => array( 'אי אפשר לשלוח קישורים בתגובה. אפשר לכתוב אותה שוב בלי הקישור.', 'error' ),
		'long'      => array( 'התגובה או השם ארוכים מדי. נסו לקצר מעט.', 'error' ),
		'many'      => array( 'נשלחו כמה תגובות ברצף. נסו שוב בעוד כמה דקות.', 'error' ),
		'dup'       => array( 'התגובה הזאת כבר התקבלה. תודה!', 'ok' ),
		'bot'       => array( 'לא הצלחנו לשלוח את התגובה. נסו שוב בעוד רגע.', 'error' ),
	);
	$notice = isset( $messages[ $code ] ) ? hakef_notice( esc_html( $messages[ $code ][0] ), $messages[ $code ][1] ) : '';
	$count  = count( $all );

	$html  = '<section class="talk container" id="talk" aria-labelledby="talk-t"><div class="talk__main">';
	$html .= '<h2 id="talk-t">תגובות' . ( $count ? ' <span class="talk__count">(' . (int) $count . ')</span>' : '' ) . '</h2>';
	if ( $top ) {
		$html .= '<ol class="talk__list">';
		foreach ( $top as $c ) {
			$html .= hakef_talk_item( $c, isset( $kid[ (int) $c->comment_ID ] ) ? $kid[ (int) $c->comment_ID ] : array() );
		}
		$html .= '</ol>';
	} else {
		$html .= '<p class="talk__empty">עדיין אין תגובות. הכנתם את המתכון? נשמח לשמוע איך יצא.</p>';
	}
	$html .= '<form class="talk__form" id="talk-form" method="post" action="' . esc_url( site_url( '/wp-comments-post.php' ) ) . '" data-human data-talk>'
		. '<h3 class="talk__form-title">הכנתם? ספרו לנו איך יצא</h3>'
		. $notice
		. '<p class="field"><label class="field__label" for="talk-text">התגובה</label>'
		. '<textarea id="talk-text" name="comment" rows="5" maxlength="2000" required aria-describedby="talk-err"></textarea>'
		. '<span class="error" id="talk-err">נא לכתוב את התגובה בעברית, בלי קישורים.</span></p>'
		. '<div class="talk__fields">'
		. '<p class="field"><label class="field__label" for="talk-name">שם</label><input id="talk-name" name="author" type="text" maxlength="60" autocomplete="name" required></p>'
		. '<p class="field"><label class="field__label" for="talk-email">מייל <span class="opt">(לא יפורסם)</span></label><input id="talk-email" name="email" type="email" maxlength="100" autocomplete="email" dir="ltr" required></p>'
		. '</div>'
		. '<label class="consent"><input type="checkbox" name="hakef_news" value="1"><span>' . esc_html( HAKEF_CONSENT_TEXT ) . ' <span class="opt">(לא חובה)</span></span></label>'
		. '<input type="hidden" name="comment_post_ID" value="' . (int) $post_id . '"><input type="hidden" name="comment_parent" value="0">'
		. hakef_hidden_human_fields()
		. '<div class="talk__actions"><button class="btn btn--line" type="submit">שליחת התגובה</button>'
		. '<p class="hint">כל תגובה מופיעה באתר אחרי אישור. <a class="text-link" href="' . esc_url( hakef_privacy_url() ) . '">מדיניות הפרטיות</a></p></div>'
		. '</form></div>';
	$html .= hakef_news_box( get_permalink( $post_id ) );
	return $html . '</section>';
}

/* ================================================================== רשימת התפוצה */

add_action( 'init', function () {
	register_post_type( 'hakef_sub', array(
		'labels'          => array(
			'name'               => 'רשימת התפוצה',
			'singular_name'      => 'כתובת ברשימה',
			'menu_name'          => 'רשימת התפוצה',
			'all_items'          => 'רשימת התפוצה',
			'search_items'       => 'חיפוש לפי מייל',
			'not_found'          => 'עדיין אין נרשמים',
			'not_found_in_trash' => 'הפח ריק',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => false,
		'menu_position'   => 4,
		'menu_icon'       => 'dashicons-email-alt',
		'supports'        => array( 'title' ),
		'rewrite'         => false,
		'query_var'       => false,
		'map_meta_cap'    => false,
		'capabilities'    => array(
			'edit_post'          => 'manage_options',
			'read_post'          => 'manage_options',
			'delete_post'        => 'manage_options',
			'edit_posts'         => 'manage_options',
			'edit_others_posts'  => 'manage_options',
			'delete_posts'       => 'manage_options',
			'publish_posts'      => 'manage_options',
			'read_private_posts' => 'manage_options',
			'create_posts'       => 'do_not_allow', // מוסיפים רק דרך האתר, עם הסכמה
		),
	) );
} );

function hakef_sub_status_label( $s ) {
	$l = array( 'active' => 'פעיל', 'pending' => 'ממתין לאישור במייל', 'unsubscribed' => 'הוסר' );
	return isset( $l[ $s ] ) ? $l[ $s ] : $s;
}

function hakef_sub_find( $email ) {
	$q = get_posts( array( 'post_type' => 'hakef_sub', 'post_status' => 'any', 'title' => $email, 'numberposts' => 1, 'suppress_filters' => true ) );
	return $q ? $q[0] : null;
}

function hakef_sub_by_token( $t ) {
	$t = (string) $t;
	if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', $t ) ) {
		return null;
	}
	$q = get_posts( array( 'post_type' => 'hakef_sub', 'post_status' => 'any', 'meta_key' => '_hs_token', 'meta_value' => $t, 'numberposts' => 1, 'suppress_filters' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return $q ? $q[0] : null;
}

/** בקשת הצטרפות: שומר כ"ממתין" ושולח מייל עם קישור אישור. מחזיר: check | already | invalid | error */
function hakef_sub_request( $email, $name, $source, $post_id = 0 ) {
	$email = strtolower( trim( sanitize_email( $email ) ) );
	if ( ! is_email( $email ) ) {
		return 'invalid';
	}
	$name = mb_substr( trim( sanitize_text_field( $name ) ), 0, 60 );
	$p    = hakef_sub_find( $email );
	if ( $p ) {
		$status = get_post_meta( $p->ID, '_hs_status', true );
		if ( 'active' === $status ) {
			return 'already';
		}
		if ( 'pending' === $status && time() - (int) get_post_meta( $p->ID, '_hs_sent', true ) < 10 * MINUTE_IN_SECONDS ) {
			return 'check'; // מייל אישור כבר נשלח לפני רגע
		}
		$id = $p->ID;
	} else {
		$id = wp_insert_post( array( 'post_type' => 'hakef_sub', 'post_status' => 'publish', 'post_title' => $email ), true );
		if ( is_wp_error( $id ) ) {
			return 'error';
		}
	}
	if ( $name ) {
		update_post_meta( $id, '_hs_name', $name );
	}
	if ( ! get_post_meta( $id, '_hs_token', true ) ) {
		update_post_meta( $id, '_hs_token', wp_generate_password( 32, false, false ) );
	}
	update_post_meta( $id, '_hs_status', 'pending' );
	update_post_meta( $id, '_hs_source', 'comment' === $source ? 'comment' : 'form' );
	update_post_meta( $id, '_hs_post', (int) $post_id );
	update_post_meta( $id, '_hs_consent_at', current_time( 'mysql' ) );
	update_post_meta( $id, '_hs_consent_ip', hakef_client_ip() );
	update_post_meta( $id, '_hs_consent_text', HAKEF_CONSENT_TEXT );
	update_post_meta( $id, '_hs_sent', time() );
	return hakef_sub_send_confirm( $id ) ? 'check' : 'error';
}

function hakef_news_url( $action, $token ) {
	return add_query_arg( array( 'action' => $action, 't' => $token ), admin_url( 'admin-post.php' ) );
}

function hakef_sub_send_confirm( $id ) {
	$email = get_the_title( $id );
	$name  = get_post_meta( $id, '_hs_name', true );
	$yes   = hakef_news_url( 'hakef_news_confirm', get_post_meta( $id, '_hs_token', true ) );
	$font  = "font-family:Arial,'Segoe UI',sans-serif;";
	$html  = '<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
		. '<body style="margin:0;padding:0;background:#f6eee9;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6eee9;"><tr><td align="center" style="padding:24px 12px;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;">'
		. '<tr><td style="background:#1c110c;border-radius:14px 14px 0 0;padding:22px;text-align:center;">'
		. '<img src="' . esc_url( home_url( '/assets/img/logo-light-200.png' ) ) . '" width="120" alt="הכיף שבאפיה" style="display:inline-block;border:0;"></td></tr>'
		. '<tr><td dir="rtl" style="padding:28px 30px 10px;text-align:right;' . $font . 'color:#3a2a22;font-size:16px;line-height:1.75;">'
		. '<p style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1c110c;">שלום' . ( $name ? ' ' . esc_html( $name ) : '' ) . ',</p>'
		. '<p style="margin:0 0 14px;">קיבלנו בקשה לצרף את המייל הזה לרשימת התפוצה של הכיף שבאפיה: מתכונים חדשים מהמטבח, עדכונים והצעות.</p>'
		. '<p style="margin:0 0 20px;">כדי לאשר את ההרשמה, לחצו על הכפתור:</p>'
		. '<p style="margin:0 0 24px;"><a href="' . esc_url( $yes ) . '" style="display:inline-block;background:#ea7686;color:#2b1209;font-weight:700;text-decoration:none;padding:13px 28px;border-radius:999px;' . $font . 'font-size:16px;">אישור ההרשמה</a></p>'
		. '<p style="margin:0 0 18px;font-size:14px;color:#7a665c;">לא ביקשתם להירשם? אפשר פשוט להתעלם מהמייל הזה, ולא יישלח אליכם שום דבר.</p>'
		. '</td></tr>'
		. '<tr><td dir="rtl" style="padding:16px 30px 22px;border-top:1px solid #efe4de;text-align:right;' . $font . 'font-size:13px;line-height:1.7;color:#8a776d;">'
		. 'הכיף שבאפיה · קייטרינג קינוחים בוטיק · ירושלים והסביבה · <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#b4505f;">hakef.com</a></td></tr>'
		. '</table></td></tr></table></body></html>';
	return wp_mail( $email, 'אישור הרשמה לרשימת התפוצה של הכיף שבאפיה', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/** חזרה לעמוד עם הודעה ליד תיבת ההרשמה */
function hakef_news_back( $code, $back = '', $extra = array() ) {
	$back = $back ? $back : get_post_type_archive_link( 'recipe' );
	$back = remove_query_arg( array( 'news', 'talk', 't' ), $back );
	wp_safe_redirect( add_query_arg( array_merge( array( 'news' => $code ), $extra ), $back ) . '#news' );
	exit;
}

// הרשמה מתיבת ההרשמה
function hakef_news_subscribe() {
	// phpcs:disable WordPress.Security.NonceVerification -- טופס ציבורי, נבדק באסימון חתום
	$back = isset( $_POST['back'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ) ), '' ) : '';
	if ( hakef_form_human_check( $_POST ) ) {
		hakef_news_back( 'bot', $back );
	}
	if ( empty( $_POST['consent'] ) ) {
		hakef_news_back( 'consent', $back );
	}
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		hakef_news_back( 'invalid', $back );
	}
	if ( ! hakef_rate_ok( 's', 8, HOUR_IN_SECONDS ) ) {
		hakef_news_back( 'many', $back );
	}
	$name = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- מנוקה בפונקציה
	$from = $back ? url_to_postid( $back ) : 0;
	$from = ( $from && 'recipe' === get_post_type( $from ) ) ? $from : 0; // רק מתכון נחשב "נרשם דרך"
	hakef_news_back( hakef_sub_request( $email, $name, 'form', $from ), $back );
	// phpcs:enable
}
add_action( 'admin_post_nopriv_hakef_subscribe', 'hakef_news_subscribe' );
add_action( 'admin_post_hakef_subscribe', 'hakef_news_subscribe' );

// אישור ההרשמה מהקישור במייל
function hakef_news_confirm() {
	$p = hakef_sub_by_token( isset( $_GET['t'] ) ? wp_unslash( $_GET['t'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	if ( ! $p ) {
		hakef_news_back( 'expired' );
	}
	if ( 'active' !== get_post_meta( $p->ID, '_hs_status', true ) ) {
		update_post_meta( $p->ID, '_hs_status', 'active' );
		update_post_meta( $p->ID, '_hs_confirmed_at', current_time( 'mysql' ) );
		update_post_meta( $p->ID, '_hs_confirm_ip', hakef_client_ip() );
		delete_post_meta( $p->ID, '_hs_unsub_at' );
	}
	$post = (int) get_post_meta( $p->ID, '_hs_post', true );
	hakef_news_back( 'confirmed', ( $post && 'recipe' === get_post_type( $post ) ) ? get_permalink( $post ) : '' );
}
add_action( 'admin_post_nopriv_hakef_news_confirm', 'hakef_news_confirm' );
add_action( 'admin_post_hakef_news_confirm', 'hakef_news_confirm' );

// הסרה: הקישור פותח עמוד עם כפתור אישור (כדי שסורקי קישורים במייל לא יסירו בטעות), והכפתור מסיר.
function hakef_news_unsub() {
	$t = isset( $_GET['t'] ) ? hakef_clean_token( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	hakef_news_back( hakef_sub_by_token( $t ) ? 'unsub' : 'expired', '', array( 't' => $t ) );
}
add_action( 'admin_post_nopriv_hakef_news_unsub', 'hakef_news_unsub' );
add_action( 'admin_post_hakef_news_unsub', 'hakef_news_unsub' );

function hakef_news_unsub_do() {
	$p = hakef_sub_by_token( isset( $_POST['t'] ) ? wp_unslash( $_POST['t'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	if ( $p ) {
		update_post_meta( $p->ID, '_hs_status', 'unsubscribed' );
		update_post_meta( $p->ID, '_hs_unsub_at', current_time( 'mysql' ) );
	}
	hakef_news_back( $p ? 'bye' : 'expired' );
}
add_action( 'admin_post_nopriv_hakef_news_unsub_do', 'hakef_news_unsub_do' );
add_action( 'admin_post_hakef_news_unsub_do', 'hakef_news_unsub_do' );

/** קישור הסרה אישי, להוספה בתחתית כל דיוור שנשלח מהאתר */
function hakef_sub_unsub_url( $id ) {
	return hakef_news_url( 'hakef_news_unsub', get_post_meta( $id, '_hs_token', true ) );
}

/** תיבת ההרשמה לרשימת התפוצה */
function hakef_news_box( $back ) {
	// phpcs:disable WordPress.Security.NonceVerification
	$code     = isset( $_GET['news'] ) ? sanitize_key( wp_unslash( $_GET['news'] ) ) : '';
	$token    = isset( $_GET['t'] ) ? hakef_clean_token( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	// phpcs:enable
	$messages = array(
		'check'     => array( 'כמעט סיימנו: שלחנו לכם מייל עם קישור לאישור ההרשמה. אם הוא לא מגיע תוך כמה דקות, כדאי לבדוק בתיקיית הספאם.', 'ok', true ),
		'already'   => array( 'המייל הזה כבר ברשימת התפוצה. תודה!', 'ok', true ),
		'confirmed' => array( 'ההרשמה אושרה. מעכשיו מתכונים חדשים יגיעו אליכם למייל.', 'ok', true ),
		'bye'       => array( 'הוסרתם מרשימת התפוצה, ולא יישלחו אליכם עוד מיילים מאיתנו.', 'ok', true ),
		'consent'   => array( 'כדי להירשם צריך לסמן את תיבת ההסכמה.', 'error', false ),
		'invalid'   => array( 'נראה שכתובת המייל לא תקינה. אפשר לנסות שוב.', 'error', false ),
		'many'      => array( 'היו כמה ניסיונות ברצף. נסו שוב מאוחר יותר.', 'error', false ),
		'expired'   => array( 'הקישור כבר לא בתוקף. אפשר להירשם מחדש כאן.', 'error', false ),
		'bot'       => array( 'לא הצלחנו לשלוח. נסו שוב בעוד רגע.', 'error', false ),
		'error'     => array( 'משהו השתבש בשליחת מייל האישור. נסו שוב מאוחר יותר.', 'error', false ),
	);
	$html = '<aside class="news" id="news" aria-labelledby="news-t"><h2 id="news-t">מתכונים חדשים, ישר למייל</h2>';
	if ( 'unsub' === $code && hakef_sub_by_token( $token ) ) {
		return $html . '<p class="news__text">להסיר את המייל שלכם מרשימת התפוצה של הכיף שבאפיה?</p>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="hakef_news_unsub_do"><input type="hidden" name="t" value="' . esc_attr( $token ) . '">'
			. '<button class="btn btn--line" type="submit">כן, להסיר אותי</button></form></aside>';
	}
	$done  = isset( $messages[ $code ] ) && $messages[ $code ][2];
	$html .= '<p class="news__text">מדי פעם נשלח מתכון חדש מהמטבח, וגם עדכונים והצעות לקראת חגים ואירועים. בלי הצפה, ואפשר להסיר בכל רגע.</p>';
	if ( isset( $messages[ $code ] ) ) {
		$html .= hakef_notice( esc_html( $messages[ $code ][0] ), $messages[ $code ][1] );
	}
	if ( $done ) {
		return $html . '</aside>';
	}
	return $html . '<form class="news__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-human>'
		. '<input type="hidden" name="action" value="hakef_subscribe"><input type="hidden" name="back" value="' . esc_url( $back ) . '">'
		. '<p class="field"><label class="field__label" for="news-name">שם <span class="opt">(לא חובה)</span></label><input id="news-name" name="name" type="text" maxlength="60" autocomplete="name"></p>'
		. '<p class="field"><label class="field__label" for="news-email">מייל</label><input id="news-email" name="email" type="email" maxlength="100" autocomplete="email" dir="ltr" required></p>'
		. '<label class="consent"><input type="checkbox" name="consent" value="1" required><span>' . esc_html( HAKEF_CONSENT_TEXT ) . '</span></label>'
		. hakef_hidden_human_fields()
		. '<button class="btn btn--line" type="submit">הרשמה</button>'
		. '<p class="hint">נשלח אליכם מייל לאישור. <a class="text-link" href="' . esc_url( hakef_privacy_url() ) . '">מדיניות הפרטיות</a></p>'
		. '</form></aside>';
}

/* ------------------------------------------------------------------ ממשק הניהול: רשימת התפוצה */
add_filter( 'manage_hakef_sub_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox">',
		'title'     => 'מייל',
		'hs_name'   => 'שם',
		'hs_status' => 'מצב',
		'hs_source' => 'נרשם דרך',
		'hs_date'   => 'תאריך ההסכמה',
	);
} );

add_action( 'manage_hakef_sub_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'hs_name':
			echo esc_html( get_post_meta( $id, '_hs_name', true ) );
			break;
		case 'hs_status':
			$s = get_post_meta( $id, '_hs_status', true );
			echo '<span class="hs-badge hs-badge--' . esc_attr( $s ) . '">' . esc_html( hakef_sub_status_label( $s ) ) . '</span>';
			break;
		case 'hs_source':
			$post = (int) get_post_meta( $id, '_hs_post', true );
			$src  = 'comment' === get_post_meta( $id, '_hs_source', true ) ? 'תגובה למתכון' : 'טופס הרשמה';
			echo esc_html( $src );
			if ( $post && 'recipe' === get_post_type( $post ) ) {
				echo '<br><a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $post ) ) . '</a>';
			}
			break;
		case 'hs_date':
			echo esc_html( mysql2date( 'd/m/Y H:i', get_post_meta( $id, '_hs_consent_at', true ) ) );
			$conf = get_post_meta( $id, '_hs_confirmed_at', true );
			if ( $conf ) {
				echo '<br><small>אושר במייל: ' . esc_html( mysql2date( 'd/m/Y H:i', $conf ) ) . '</small>';
			}
			break;
	}
}, 10, 2 );

// בלי מסך עריכה: הנתונים מגיעים רק מהאתר. בשורה: מחיקה, או סימון כהוסר.
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'hakef_sub' !== $post->post_type ) {
		return $actions;
	}
	$out = array();
	if ( 'unsubscribed' !== get_post_meta( $post->ID, '_hs_status', true ) ) {
		$out['hs_unsub'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hakef_sub_mark&id=' . $post->ID ), 'hakef_sub_mark_' . $post->ID ) ) . '">סימון כהוסר</a>';
	}
	$out['delete'] = '<a class="submitdelete" href="' . esc_url( get_delete_post_link( $post->ID, '', true ) ) . '" onclick="return confirm(\'למחוק את הכתובת לצמיתות?\')">מחיקה לצמיתות</a>';
	return $out;
}, 10, 2 );

add_action( 'load-post.php', function () {
	// phpcs:disable WordPress.Security.NonceVerification
	$id     = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
	// phpcs:enable
	if ( $id && 'edit' === $action && 'hakef_sub' === get_post_type( $id ) ) { // רק מסך העריכה. מחיקה ממשיכה כרגיל
		wp_safe_redirect( admin_url( 'edit.php?post_type=hakef_sub' ) );
		exit;
	}
} );

add_filter( 'bulk_actions-edit-hakef_sub', function () {
	return array( 'delete' => 'מחיקה לצמיתות' );
} );

add_action( 'admin_post_hakef_sub_mark', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hakef_sub_mark_' . $id ) || 'hakef_sub' !== get_post_type( $id ) ) {
		wp_die( 'אין הרשאה.' );
	}
	update_post_meta( $id, '_hs_status', 'unsubscribed' );
	update_post_meta( $id, '_hs_unsub_at', current_time( 'mysql' ) );
	wp_safe_redirect( admin_url( 'edit.php?post_type=hakef_sub' ) );
	exit;
} );

// סינון לפי מצב: הכול / פעילים / ממתינים / הוסרו
add_filter( 'views_edit-hakef_sub', function () {
	$cur   = isset( $_GET['hs'] ) ? sanitize_key( wp_unslash( $_GET['hs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$base  = admin_url( 'edit.php?post_type=hakef_sub' );
	$views = array( '' => 'הכול' , 'active' => 'פעילים', 'pending' => 'ממתינים לאישור במייל', 'unsubscribed' => 'הוסרו' );
	$out   = array();
	foreach ( $views as $k => $label ) {
		$n       = hakef_sub_count( $k );
		$url     = $k ? add_query_arg( 'hs', $k, $base ) : $base;
		$out[ $k ? $k : 'all' ] = '<a href="' . esc_url( $url ) . '"' . ( $cur === $k ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) $n . ')</span></a>';
	}
	return $out;
} );

function hakef_sub_count( $status = '' ) {
	$args = array( 'post_type' => 'hakef_sub', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false );
	if ( $status ) {
		$args['meta_query'] = array( array( 'key' => '_hs_status', 'value' => $status ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$q = new WP_Query( $args );
	return (int) $q->found_posts;
}

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'hakef_sub' !== $q->get( 'post_type' ) ) {
		return;
	}
	$hs = isset( $_GET['hs'] ) ? sanitize_key( wp_unslash( $_GET['hs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( in_array( $hs, array( 'active', 'pending', 'unsubscribed' ), true ) ) {
		$q->set( 'meta_query', array( array( 'key' => '_hs_status', 'value' => $hs ) ) );
	}
} );

// הסבר וכפתור הורדה בראש הרשימה
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-hakef_sub' !== $screen->id ) {
		return;
	}
	$csv = wp_nonce_url( admin_url( 'admin-post.php?action=hakef_sub_export' ), 'hakef_sub_export' );
	echo '<div class="notice notice-info"><p><strong>מי ברשימה:</strong> רק מי שסימן בעצמו הסכמה לקבל דיוור, באתר או בטופס התגובה. '
		. 'הכתובת הופכת ל"פעיל" רק אחרי לחיצה על קישור האישור שנשלח אליו במייל. שולחים דיוור רק לפעילים.</p>'
		. '<p><a class="button button-primary" href="' . esc_url( $csv ) . '">הורדת הפעילים לאקסל (CSV)</a> '
		. '<span style="margin-inline-start:10px">את הקובץ אפשר להעלות לשירות דיוור (כמו Brevo או Mailchimp) ולשלוח משם, עם קישור הסרה אוטומטי בכל מייל.</span></p></div>';
	echo '<style>.hs-badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;background:#f0f0f1}.hs-badge--active{background:#d7f0dd;color:#14532d}.hs-badge--pending{background:#fdf0d5;color:#7a4b00}.hs-badge--unsubscribed{background:#f3e1e1;color:#7f1d1d}.post-type-hakef_sub .page-title-action{display:none}</style>';
} );

add_action( 'admin_post_hakef_sub_export', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hakef_sub_export' ) ) {
		wp_die( 'אין הרשאה.' );
	}
	$ids = get_posts( array( 'post_type' => 'hakef_sub', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_hs_status', 'meta_value' => 'active', 'orderby' => 'date', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="hakef-mailing-list-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- כדי שאקסל יציג עברית נכון
	fputcsv( $out, array( 'email', 'name', 'consent_date', 'confirmed_date', 'source' ) );
	foreach ( $ids as $id ) {
		fputcsv( $out, array(
			get_the_title( $id ),
			get_post_meta( $id, '_hs_name', true ),
			get_post_meta( $id, '_hs_consent_at', true ),
			get_post_meta( $id, '_hs_confirmed_at', true ),
			'comment' === get_post_meta( $id, '_hs_source', true ) ? 'תגובה למתכון' : 'טופס הרשמה',
		) );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
} );

/* ------------------------------------------------------------------ לוח הבקרה: תגובות ונרשמים */
add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'moderate_comments' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'hakef_community_widget', 'תגובות ורשימת התפוצה', function () {
		$waiting = (int) wp_count_comments()->moderated;
		$active  = hakef_sub_count( 'active' );
		$pending = hakef_sub_count( 'pending' );
		echo '<div style="font-size:14px;line-height:1.9">';
		printf(
			'<p><strong>תגובות שממתינות לאישור: %d</strong> · <a href="%s">לאישור התגובות</a></p>',
			$waiting, // phpcs:ignore
			esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) )
		);
		if ( current_user_can( 'manage_options' ) ) {
			printf(
				'<p><strong>ברשימת התפוצה: %d פעילים</strong>%s · <a href="%s">לרשימה</a></p>',
				$active, // phpcs:ignore
				$pending ? ' (ועוד ' . (int) $pending . ' שעוד לא אישרו במייל)' : '', // phpcs:ignore
				esc_url( admin_url( 'edit.php?post_type=hakef_sub' ) )
			);
		}
		echo '<p style="color:#50575e">כל תגובה מהאתר מגיעה קודם לכאן, וגם במייל. באישור היא מופיעה מתחת למתכון. אפשר גם להשיב לה מכאן, והתשובה תופיע בשם "הכיף שבאפיה".</p>';
		echo '</div>';
	} );
} );

/* ------------------------------------------------------------------ כלי הפרטיות של וורדפרס: ייצוא ומחיקה לפי מייל */
add_filter( 'wp_privacy_personal_data_exporters', function ( $ex ) {
	$ex['hakef-subs'] = array(
		'exporter_friendly_name' => 'רשימת התפוצה',
		'callback'               => function ( $email ) {
			$p    = hakef_sub_find( strtolower( $email ) );
			$data = array();
			if ( $p ) {
				$data[] = array(
					'group_id'    => 'hakef-subs',
					'group_label' => 'רשימת התפוצה',
					'item_id'     => 'hakef-sub-' . $p->ID,
					'data'        => array(
						array( 'name' => 'מייל', 'value' => $p->post_title ),
						array( 'name' => 'שם', 'value' => get_post_meta( $p->ID, '_hs_name', true ) ),
						array( 'name' => 'מצב', 'value' => hakef_sub_status_label( get_post_meta( $p->ID, '_hs_status', true ) ) ),
						array( 'name' => 'מועד ההסכמה', 'value' => get_post_meta( $p->ID, '_hs_consent_at', true ) ),
						array( 'name' => 'מועד האישור', 'value' => get_post_meta( $p->ID, '_hs_confirmed_at', true ) ),
					),
				);
			}
			return array( 'data' => $data, 'done' => true );
		},
	);
	return $ex;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $er ) {
	$er['hakef-subs'] = array(
		'eraser_friendly_name' => 'רשימת התפוצה',
		'callback'             => function ( $email ) {
			$p = hakef_sub_find( strtolower( $email ) );
			if ( $p ) {
				wp_delete_post( $p->ID, true );
			}
			return array( 'items_removed' => (bool) $p, 'items_retained' => false, 'messages' => array(), 'done' => true );
		},
	);
	return $er;
} );
