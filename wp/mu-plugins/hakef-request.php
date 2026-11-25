<?php
/**
 * Plugin Name: הכיף שבאפיה: בקשת הצעת מחיר במייל
 * Description: הבקשה מהאתר נשלחת ישירות למייל של העסק, כחלופה לוואטסאפ (למי שאין לו וואטסאפ, או שהוא חסום אצלו).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* לאן מגיעות הבקשות. אפשר לשנות כאן, או עם הפילטר hakef_request_to */
const HAKEF_REQUEST_TO = 'r0509153197@gmail.com';

add_action( 'rest_api_init', function () {
	register_rest_route( 'hakef/v1', '/request', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'hakef_request_handle',
	) );
} );

function hakef_request_handle( WP_REST_Request $r ) {
	/* רק מהאתר עצמו */
	$from = $r->get_header( 'origin' ) ?: $r->get_header( 'referer' );
	$host = $from ? (string) wp_parse_url( $from, PHP_URL_HOST ) : '';
	if ( $host !== '' && ! preg_match( '/(^|\.)hakef\.com$/i', $host ) ) return new WP_REST_Response( array( 'ok' => false, 'error' => 'origin' ), 403 );

	$p = $r->get_json_params();
	if ( ! is_array( $p ) ) $p = array();
	/* מלכודות לרובוטים: שדה נסתר, ושליחה מהירה מדי. הרובוט מקבל "הצלחה" ולא נשלח כלום */
	if ( ! empty( $p['website'] ) ) return array( 'ok' => true );
	if ( isset( $p['t'] ) && (int) $p['t'] < 3000 ) return array( 'ok' => true );

	$name  = mb_substr( sanitize_text_field( (string) ( $p['name'] ?? '' ) ), 0, 120 );
	$phone = mb_substr( sanitize_text_field( (string) ( $p['phone'] ?? '' ) ), 0, 40 );
	$msg   = mb_substr( sanitize_textarea_field( (string) ( $p['message'] ?? '' ) ), 0, 4000 );
	$page  = esc_url_raw( (string) ( $p['page'] ?? '' ) );
	$digits = preg_replace( '/\D/', '', $phone );
	if ( mb_strlen( $name ) < 2 || strlen( $digits ) < 9 ) return new WP_REST_Response( array( 'ok' => false, 'error' => 'missing' ), 422 );
	if ( preg_match_all( '~https?://|www\.~i', $msg . ' ' . $name ) > 2 ) return array( 'ok' => true );

	/* עד 5 בקשות ב־10 דקות מאותה כתובת */
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'hakef_req_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate' ), 429 );
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

	$tel = preg_replace( '/[^\d+]/', '', $phone );
	$wa  = preg_replace( '/^0/', '972', $digits );
	$e   = static function ( $s ) { return esc_html( $s ); };
	$html = '<div dir="rtl" style="font-family:Arial,sans-serif;font-size:16px;line-height:1.6;color:#2a1a1f">'
		. '<h2 style="margin:0 0 12px">בקשת הצעת מחיר מהאתר</h2>'
		. '<p style="margin:0 0 4px"><b>שם:</b> ' . $e( $name ) . '</p>'
		. '<p style="margin:0 0 4px"><b>טלפון:</b> <a href="tel:' . esc_attr( $tel ) . '" style="color:#7a1f3d;font-weight:700">' . $e( $phone ) . '</a>'
		. ' · <a href="https://wa.me/' . esc_attr( $wa ) . '" style="color:#1a7f37">לפתוח וואטסאפ עם הלקוח</a></p>'
		. '<div style="margin-top:14px;padding:14px 16px;background:#fbf3f5;border-radius:10px;white-space:pre-wrap">' . $e( $msg ) . '</div>'
		. ( $page ? '<p style="margin-top:12px;color:#777;font-size:13px">נשלח מהעמוד: ' . $e( $page ) . '</p>' : '' )
		. '<p style="margin-top:12px;color:#777;font-size:13px">הלקוח בחר לשלוח במייל ולא בוואטסאפ, כדאי לחזור אליו בטלפון.</p></div>';
	$to = apply_filters( 'hakef_request_to', HAKEF_REQUEST_TO );
	$ok = wp_mail( $to, 'בקשת הצעת מחיר מהאתר: ' . $name, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	return $ok ? array( 'ok' => true ) : new WP_REST_Response( array( 'ok' => false, 'error' => 'mail' ), 502 );
}
