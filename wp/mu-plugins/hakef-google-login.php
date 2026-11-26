<?php
/**
 * Plugin Name: הכיף שבאפיה: כניסה עם Google
 * Description: כניסה והרשמה בלחיצה עם חשבון Google, ללקוחות ולמנהלים. לקוח חדש מקבל חשבון "מנוי" (בלי גישה לממשק). מי שכבר מנהל או עורך באתר נכנס לחשבון הקיים שלו לפי המייל, ובכותרת האתר מופיע לו כפתור "אזור ניהול". ההגדרות: הגדרות ← כניסה עם Google.
 * Version: 1.0.0
 *
 * הזרימה: כפתור "כניסה" ← /wp-json/hakef/v1/google/start ← בחירת חשבון בגוגל ← /wp-json/hakef/v1/google/callback ← חזרה לעמוד.
 * מפתחות Google (Client ID ו־Client secret) נשמרים בהגדרות וורדפרס, לא בקוד.
 */

defined( 'ABSPATH' ) || exit;

const HAKEF_G_CALLBACK = '/wp-json/hakef/v1/google/callback';

function hakef_g_settings() {
	$s = get_option( 'hakef_google_login', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array( 'client_id' => '', 'client_secret' => '', 'enabled' => 0 ) );
}

function hakef_g_ready() {
	$s = hakef_g_settings();
	return $s['enabled'] && $s['client_id'] && $s['client_secret'];
}

function hakef_g_redirect_uri() {
	return home_url( HAKEF_G_CALLBACK );
}

/** מנהל או עורך מתכונים: מי שמקבל את כפתור "אזור ניהול" */
function hakef_g_is_manager( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user && $user->exists() && ( user_can( $user, 'edit_recipes' ) || user_can( $user, 'moderate_comments' ) );
}

/** עוגייה גלויה לסקריפט (בלי שום מידע אישי), כדי שהעמודים הסטטיים יידעו לבדוק מי מחובר רק כשצריך */
function hakef_g_flag( $on ) {
	$secure = is_ssl();
	if ( $on ) {
		setcookie( 'hakef_user', '1', array( 'expires' => time() + 14 * DAY_IN_SECONDS, 'path' => '/', 'secure' => $secure, 'samesite' => 'Lax' ) );
	} else {
		setcookie( 'hakef_user', '', array( 'expires' => time() - 3600, 'path' => '/', 'secure' => $secure, 'samesite' => 'Lax' ) );
	}
}
add_action( 'wp_login', function () { hakef_g_flag( true ); } );
add_action( 'wp_logout', function () { hakef_g_flag( false ); } );

/* ------------------------------------------------------------------ הכניסה: יציאה לגוגל וחזרה */
add_action( 'rest_api_init', function () {
	register_rest_route( 'hakef/v1', '/google/start', array( 'methods' => 'GET', 'callback' => 'hakef_g_start', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'hakef/v1', '/google/callback', array( 'methods' => 'GET', 'callback' => 'hakef_g_callback', 'permission_callback' => '__return_true' ) );
} );

/** רישום ניסיון כניסה: רק זמן וקוד סיבה, בלי מייל, שם או מפתחות. נשמרים 20 אחרונים. */
function hakef_g_log( $code ) {
	$log = get_option( 'hakef_g_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 't' => current_time( 'mysql' ), 'c' => substr( sanitize_text_field( (string) $code ), 0, 80 ) ) );
	update_option( 'hakef_g_log', array_slice( $log, 0, 20 ), false );
}

function hakef_g_back( $to, $msg = '' ) {
	$to = wp_validate_redirect( $to, home_url( '/' ) );
	if ( $msg ) {
		$to = add_query_arg( 'login', $msg, $to );
	}
	wp_safe_redirect( $to );
	exit;
}

function hakef_g_start( WP_REST_Request $r ) {
	$back = (string) $r->get_param( 'r' );
	$back = ( $back && '/' === $back[0] && '/' !== substr( $back, 1, 1 ) ) ? home_url( $back ) : home_url( '/' );
	if ( ! hakef_g_ready() ) {
		hakef_g_back( $back, 'off' );
	}
	$s     = hakef_g_settings();
	$state = wp_generate_password( 24, false, false );
	// מצב חתום בעוגייה: מגן מפני זיוף בקשה, ושומר לאן לחזור
	$data = base64_encode( wp_json_encode( array( 's' => $state, 'r' => $back, 't' => time() ) ) );
	setcookie( 'hakef_g_state', $data . '.' . hash_hmac( 'sha256', $data, wp_salt( 'auth' ) ), array( 'expires' => time() + 600, 'path' => '/wp-json/hakef/v1/google/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	$url = add_query_arg( array(
		'client_id'     => rawurlencode( $s['client_id'] ),
		'redirect_uri'  => rawurlencode( hakef_g_redirect_uri() ),
		'response_type' => 'code',
		'scope'         => rawurlencode( 'openid email profile' ),
		'state'         => $state,
		'prompt'        => 'select_account',
		'access_type'   => 'online',
	), 'https://accounts.google.com/o/oauth2/v2/auth' );
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- גוגל, לא כתובת מהגולש
	exit;
}

function hakef_g_callback( WP_REST_Request $r ) {
	$raw  = isset( $_COOKIE['hakef_g_state'] ) ? (string) wp_unslash( $_COOKIE['hakef_g_state'] ) : '';
	$back = home_url( '/' );
	$st   = null;
	if ( $raw && false !== strpos( $raw, '.' ) ) {
		list( $data, $sig ) = explode( '.', $raw, 2 );
		if ( hash_equals( hash_hmac( 'sha256', $data, wp_salt( 'auth' ) ), $sig ) ) {
			$st = json_decode( base64_decode( $data ), true );
		}
	}
	setcookie( 'hakef_g_state', '', array( 'expires' => time() - 3600, 'path' => '/wp-json/hakef/v1/google/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	if ( is_array( $st ) && ! empty( $st['r'] ) ) {
		$back = $st['r'];
	}
	if ( ! is_array( $st ) || empty( $st['s'] ) || ! hash_equals( (string) $st['s'], (string) $r->get_param( 'state' ) ) || time() - (int) $st['t'] > 600 ) {
		hakef_g_log( ! $raw ? 'state: אין עוגייה' : ( ! is_array( $st ) ? 'state: חתימה' : ( time() - (int) $st['t'] > 600 ? 'state: פג תוקף' : 'state: לא תואם' ) ) );
		hakef_g_back( $back, 'expired' );
	}
	if ( $r->get_param( 'error' ) || ! $r->get_param( 'code' ) || ! hakef_g_ready() ) {
		hakef_g_log( 'google: ' . ( $r->get_param( 'error' ) ? (string) $r->get_param( 'error' ) : ( hakef_g_ready() ? 'אין קוד' : 'כבוי' ) ) );
		hakef_g_back( $back, 'cancel' );
	}
	$s   = hakef_g_settings();
	$res = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
		'timeout' => 15,
		'body'    => array(
			'code'          => (string) $r->get_param( 'code' ),
			'client_id'     => $s['client_id'],
			'client_secret' => $s['client_secret'],
			'redirect_uri'  => hakef_g_redirect_uri(),
			'grant_type'    => 'authorization_code',
		),
	) );
	$tok = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $tok['id_token'] ) ) {
		hakef_g_log( 'token: ' . ( is_wp_error( $res ) ? 'חיבור: ' . $res->get_error_code() : ( $tok['error'] ?? ( 'http ' . wp_remote_retrieve_response_code( $res ) ) ) ) );
		hakef_g_back( $back, 'error' );
	}
	// האסימון הגיע ישירות מגוגל בחיבור מוצפן, אז מספיק לבדוק את התוכן שלו
	$parts   = explode( '.', $tok['id_token'] );
	$payload = isset( $parts[1] ) ? json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ) : null;
	if ( ! is_array( $payload ) || ( $payload['aud'] ?? '' ) !== $s['client_id'] || ! in_array( $payload['iss'] ?? '', array( 'accounts.google.com', 'https://accounts.google.com' ), true )
		|| (int) ( $payload['exp'] ?? 0 ) < time() || empty( $payload['email'] ) || true !== ( $payload['email_verified'] ?? false ) || empty( $payload['sub'] ) ) {
		hakef_g_log( 'payload: ' . ( ! is_array( $payload ) ? 'לא נקרא' : ( ( $payload['aud'] ?? '' ) !== $s['client_id'] ? 'aud' : ( true !== ( $payload['email_verified'] ?? false ) ? 'מייל לא מאומת' : 'אחר' ) ) ) );
		hakef_g_back( $back, 'error' );
	}
	$user = hakef_g_user_for( $payload );
	if ( is_wp_error( $user ) ) {
		hakef_g_log( 'user: ' . $user->get_error_code() );
		hakef_g_back( $back, 'error' );
	}
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	do_action( 'wp_login', $user->user_login, $user );
	if ( hakef_g_is_manager( $user ) && false !== strpos( $back, '/wp-admin' ) ) {
		hakef_g_back( $back );
	}
	hakef_g_log( 'ok' . ( hakef_g_is_manager( $user ) ? ' (מנהל)' : '' ) );
	hakef_g_back( $back, 'ok' );
}

/** החשבון באתר לפי חשבון הגוגל: קודם לפי המזהה של גוגל, אחר כך לפי המייל, ואם אין, לקוח חדש */
function hakef_g_user_for( array $p ) {
	$sub   = (string) $p['sub'];
	$email = strtolower( sanitize_email( $p['email'] ) );
	$found = get_users( array( 'meta_key' => 'hakef_google_sub', 'meta_value' => $sub, 'number' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $found ) {
		return $found[0];
	}
	$user = get_user_by( 'email', $email );
	if ( $user ) {
		$known = get_user_meta( $user->ID, 'hakef_google_sub', true );
		if ( $known && $known !== $sub ) {
			return new WP_Error( 'mismatch', 'חשבון אחר' );
		}
		update_user_meta( $user->ID, 'hakef_google_sub', $sub );
		update_user_meta( $user->ID, 'hakef_google_email', $email );
		return $user;
	}
	$base  = sanitize_user( strstr( $email, '@', true ), true );
	$base  = $base ? $base : 'customer';
	$login = $base;
	for ( $i = 2; username_exists( $login ); $i++ ) {
		$login = $base . $i;
	}
	$name = sanitize_text_field( $p['name'] ?? '' );
	$id   = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => wp_generate_password( 32, true, true ),
		'user_email'   => $email,
		'display_name' => $name ? $name : $login,
		'first_name'   => sanitize_text_field( $p['given_name'] ?? '' ),
		'last_name'    => sanitize_text_field( $p['family_name'] ?? '' ),
		'role'         => 'subscriber',
	) );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_user_meta( $id, 'hakef_google_sub', $sub );
	update_user_meta( $id, 'hakef_google_email', $email );
	update_user_meta( $id, 'hakef_customer_since', current_time( 'mysql' ) );
	return get_user_by( 'id', $id );
}

/* ------------------------------------------------------------------ מי מחובר: לעמודים הסטטיים (רק כשיש עוגיית hakef_user) */
function hakef_g_me() {
	nocache_headers();
	if ( ! is_user_logged_in() ) {
		hakef_g_flag( false );
		wp_send_json( array( 'logged_in' => false ) );
	}
	$u = wp_get_current_user();
	wp_send_json( array(
		'logged_in' => true,
		'name'      => $u->display_name,
		'first'     => $u->first_name ? $u->first_name : $u->display_name,
		'manager'   => hakef_g_is_manager( $u ),
		'admin_url' => admin_url( hakef_g_is_manager( $u ) && user_can( $u, 'edit_recipes' ) ? 'edit.php?post_type=recipe' : '' ),
		'logout'    => wp_logout_url( home_url( '/' ) ),
	) );
}
add_action( 'wp_ajax_hakef_me', 'hakef_g_me' );
add_action( 'wp_ajax_nopriv_hakef_me', 'hakef_g_me' );

/* ------------------------------------------------------------------ לקוחות: בלי ממשק ניהול ובלי פס הניהול */
add_filter( 'show_admin_bar', function ( $show ) {
	return $show && hakef_g_is_manager();
} );
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! is_user_logged_in() || hakef_g_is_manager() ) {
		return;
	}
	$page = basename( isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '' );
	if ( 'admin-post.php' === $page ) {
		return;
	}
	wp_safe_redirect( home_url( '/' ) );
	exit;
} );

/* ------------------------------------------------------------------ מסך הכניסה של וורדפרס: כפתור Google גדול */
add_action( 'login_message', function ( $message ) {
	if ( ! hakef_g_ready() ) {
		return $message;
	}
	$to  = isset( $_REQUEST['redirect_to'] ) ? (string) wp_unslash( $_REQUEST['redirect_to'] ) : admin_url(); // phpcs:ignore WordPress.Security.NonceVerification
	$rel = wp_make_link_relative( wp_validate_redirect( $to, admin_url() ) );
	$url = add_query_arg( 'r', rawurlencode( $rel ), rest_url( 'hakef/v1/google/start' ) );
	return $message . '<p style="text-align:center;margin:0 0 18px"><a href="' . esc_url( $url ) . '" style="display:inline-flex;align-items:center;gap:10px;padding:10px 18px;border:1px solid #c3c4c7;border-radius:999px;background:#fff;color:#1d2327;text-decoration:none;font-size:15px;font-weight:600">'
		. hakef_g_logo() . ' כניסה עם Google</a></p><p style="text-align:center;color:#646970;margin:0 0 12px">או עם שם משתמש וסיסמה:</p>';
} );

function hakef_g_logo() {
	return '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.5z"/></svg>';
}

/* ------------------------------------------------------------------ הגדרות ← כניסה עם Google */
add_action( 'admin_menu', function () {
	add_options_page( 'כניסה עם Google', 'כניסה עם Google', 'manage_options', 'hakef-google-login', 'hakef_g_settings_page' );
} );

add_action( 'admin_post_hakef_g_save', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hakef_g_save' ) ) {
		wp_die( 'אין הרשאה.' );
	}
	$s  = hakef_g_settings();
	$id = isset( $_POST['client_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) ) : '';
	$sc = isset( $_POST['client_secret'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) ) : '';
	$s['client_id'] = $id;
	if ( '' !== $sc ) {
		$s['client_secret'] = $sc; // שדה ריק = לא משנים את הסוד השמור
	}
	$s['enabled'] = empty( $_POST['enabled'] ) ? 0 : 1;
	update_option( 'hakef_google_login', $s, false );
	wp_safe_redirect( admin_url( 'options-general.php?page=hakef-google-login&saved=1' ) );
	exit;
} );

function hakef_g_settings_page() {
	$s   = hakef_g_settings();
	$ok  = hakef_g_ready();
	$cb  = hakef_g_redirect_uri();
	echo '<div class="wrap" style="max-width:760px"><h1>כניסה עם Google</h1>';
	if ( ! empty( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p><strong>נשמר.</strong> ' . ( $ok ? 'הכניסה עם Google פעילה.' : 'הכניסה עוד לא פעילה: צריך מזהה, סוד, וסימון "להפעיל".' ) . '</p></div>';
	}
	echo '<p>כתובת החזרה שצריך להזין אצל Google (Authorized redirect URI):</p><p><code style="font-size:15px;direction:ltr;display:inline-block">' . esc_html( $cb ) . '</code></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'hakef_g_save' );
	echo '<input type="hidden" name="action" value="hakef_g_save"><table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="g-id">Client ID</label></th><td><input id="g-id" name="client_id" type="text" class="large-text" dir="ltr" value="' . esc_attr( $s['client_id'] ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="g-secret">Client secret</label></th><td><input id="g-secret" name="client_secret" type="password" class="large-text" dir="ltr" autocomplete="off" placeholder="' . ( $s['client_secret'] ? 'שמור. להשאיר ריק כדי לא לשנות' : '' ) . '"></td></tr>';
	echo '<tr><th scope="row">הפעלה</th><td><label><input type="checkbox" name="enabled" value="1"' . checked( $s['enabled'], 1, false ) . '> להפעיל כניסה עם Google באתר</label></td></tr>';
	echo '</table>';
	submit_button( 'שמירה' );
	echo '</form>';
	$count = count( get_users( array( 'role' => 'subscriber', 'meta_key' => 'hakef_google_sub', 'fields' => 'ID' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	echo '<p>לקוחות שנכנסו עם Google עד עכשיו: <strong>' . (int) $count . '</strong> · <a href="' . esc_url( admin_url( 'users.php?role=subscriber' ) ) . '">לרשימת הלקוחות</a></p>';
	$log = get_option( 'hakef_g_log', array() );
	if ( $log ) {
		echo '<h2>ניסיונות כניסה אחרונים</h2><table class="widefat striped" style="max-width:640px"><thead><tr><th>מתי</th><th>תוצאה</th></tr></thead><tbody>';
		foreach ( $log as $row ) {
			echo '<tr><td>' . esc_html( $row['t'] ) . '</td><td dir="auto">' . esc_html( 0 === strpos( $row['c'], 'ok' ) ? 'הצליח' . substr( $row['c'], 2 ) : $row['c'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}
