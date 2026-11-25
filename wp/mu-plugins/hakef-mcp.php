<?php
/**
 * Plugin Name: הכיף שבאפיה: שרת MCP לסוכני בינה מלאכותית (קריאה בלבד)
 * Description: שרת MCP קטן בכתובת https://hakef.com/mcp (Streamable HTTP, בלי הרשמה). ארבעה כלים: פרטי העסק, מוצרים ומחירי התחלה, הערכת כמויות לבר קינוחים, והכנת נוסח בקשה להצעת מחיר. שום כלי לא שולח הודעה, לא שומר נתונים ולא משנה דבר באתר.
 * Version: 1.0.0
 *
 * הנתונים נקראים מ־/assets/data/catalog.json, שנבנה מ־build/build.py יחד עם שאר האתר.
 * כרטיס השרת: /.well-known/mcp/server-card.json
 */

defined( 'ABSPATH' ) || exit;

const HAKEF_MCP_VERSION   = '1.0.0';
const HAKEF_MCP_PROTOCOLS = array( '2025-06-18', '2025-03-26', '2024-11-05' );

add_action( 'rest_api_init', function () {
	register_rest_route( 'hakef/v1', '/mcp', array(
		array( 'methods' => 'POST', 'callback' => 'hakef_mcp_post', 'permission_callback' => '__return_true' ),
		array( 'methods' => 'GET', 'callback' => 'hakef_mcp_get', 'permission_callback' => '__return_true' ),
		array( 'methods' => 'DELETE', 'callback' => 'hakef_mcp_get', 'permission_callback' => '__return_true' ),
	) );
} );

// כותרות לדפדפנים (CORS), כולל לבקשת OPTIONS המקדימה
add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
	if ( '/hakef/v1/mcp' === $request->get_route() ) {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Accept, Mcp-Session-Id, Mcp-Protocol-Version' );
		header( 'Cache-Control: no-store' );
	}
	return $served;
}, 10, 3 );

function hakef_mcp_get() {
	$r = new WP_REST_Response( array( 'error' => 'השרת מקבל רק בקשות POST בפורמט JSON-RPC (MCP Streamable HTTP, בלי זרם SSE).' ), 405 );
	$r->header( 'Allow', 'POST, OPTIONS' );
	return $r;
}

function hakef_mcp_catalog() {
	static $c = null;
	if ( null === $c ) {
		$file = ABSPATH . 'assets/data/catalog.json';
		$c    = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $c ) ) {
			$c = array();
		}
	}
	return $c;
}

function hakef_mcp_tools() {
	$ro    = array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false );
	$empty = array( 'type' => 'object', 'properties' => new stdClass() );
	return array(
		array(
			'name'        => 'get_business_info',
			'title'       => 'פרטי העסק',
			'description' => 'פרטי הכיף שבאפיה, קייטרינג קינוחים בוטיק בירושלים: מה מכינים, אזור שירות, כשרות, תשלום, איך מזמינים ודרכי קשר.',
			'inputSchema' => $empty,
			'annotations' => $ro,
		),
		array(
			'name'        => 'list_products',
			'title'       => 'מוצרים ומחירי התחלה',
			'description' => 'רשימת המוצרים: שם, קטגוריה, קישור, תיאור קצר ומחיר התחלה בשקלים (ILS). המחיר הסופי נקבע בהצעת מחיר אישית.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'category' => array( 'type' => 'string', 'description' => 'סינון לפי קטגוריה, למשל: עוגות, פטיפורים, מארזים, פינגר פוד. לא חובה.' ),
				),
			),
			'annotations' => $ro,
		),
		array(
			'name'        => 'estimate_dessert_bar',
			'title'       => 'הערכת כמויות לבר קינוחים',
			'description' => 'מעריך כמה יחידות פטיפורים וקינוחים צריך לאירוע, לפי מספר האורחים ותפקיד הבר, עם הרכב מומלץ ועוגה מרכזית מ־50 אורחים. זו הערכה ראשונית בלבד.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'guests' => array( 'type' => 'integer', 'minimum' => 10, 'maximum' => 1000, 'description' => 'מספר האורחים באירוע.' ),
					'role'   => array( 'type' => 'string', 'enum' => array( 'after', 'main' ), 'description' => 'after: קינוח אחרי ארוחה. main: הבר הוא הכיבוד המרכזי.' ),
				),
				'required'   => array( 'guests' ),
			),
			'annotations' => $ro,
		),
		array(
			'name'        => 'prepare_quote_request',
			'title'       => 'הכנת בקשה להצעת מחיר',
			'description' => 'מכין נוסח בקשה להצעת מחיר וקישור וואטסאפ לעסק עם הנוסח. הכלי לא שולח דבר: הלקוח פותח את הקישור ולוחץ שליחה בעצמו.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'name'       => array( 'type' => 'string', 'description' => 'שם הלקוח.' ),
					'event_type' => array( 'type' => 'string', 'description' => 'סוג האירוע, למשל בר מצווה, ברית, שבע ברכות.' ),
					'guests'     => array( 'type' => 'integer', 'description' => 'מספר אורחים משוער.' ),
					'date'       => array( 'type' => 'string', 'description' => 'תאריך האירוע.' ),
					'location'   => array( 'type' => 'string', 'description' => 'עיר או מקום האירוע.' ),
					'items'      => array( 'type' => 'string', 'description' => 'מה מזמינים: בר קינוחים, מגשי פטיפורים, עוגה מעוצבת וכו׳.' ),
					'notes'      => array( 'type' => 'string', 'description' => 'טעמים, צבעים, כיתוב או בקשות מיוחדות.' ),
				),
			),
			'annotations' => $ro,
		),
	);
}

function hakef_mcp_text( $data, $is_error = false ) {
	$out = array( 'content' => array( array( 'type' => 'text', 'text' => is_string( $data ) ? $data : wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ) ) );
	if ( $is_error ) {
		$out['isError'] = true;
	}
	return $out;
}

function hakef_mcp_call( $name, $args ) {
	$c = hakef_mcp_catalog();
	if ( ! $c ) {
		return hakef_mcp_text( 'הנתונים זמנית לא זמינים. אפשר לפנות לעסק בוואטסאפ: https://wa.me/972509153197', true );
	}
	$args = is_array( $args ) ? $args : array();
	switch ( $name ) {
		case 'get_business_info':
			return hakef_mcp_text( $c['business'] + array( 'categories' => $c['categories'], 'price_note' => $c['price_note'] ) );

		case 'list_products':
			$list = $c['products'];
			$cat  = isset( $args['category'] ) ? trim( (string) $args['category'] ) : '';
			if ( '' !== $cat ) {
				$list = array_values( array_filter( $list, function ( $p ) use ( $cat ) {
					return false !== mb_stripos( $p['category'] . ' ' . $p['name'], $cat );
				} ) );
			}
			return hakef_mcp_text( array( 'currency' => $c['currency'], 'price_note' => $c['price_note'], 'prices_page' => $c['business']['prices_page'], 'products' => $list ) );

		case 'estimate_dessert_bar':
			$g = isset( $args['guests'] ) ? (int) $args['guests'] : 0;
			if ( $g < 1 ) {
				return hakef_mcp_text( 'צריך מספר אורחים (guests).', true );
			}
			$g     = max( 10, min( 1000, $g ) );
			$role  = ( isset( $args['role'] ) && 'main' === $args['role'] ) ? 'main' : 'after';
			$pl    = $c['planner'];
			$r10   = function ( $n ) { return max( 10, (int) ( round( $n / 10 ) * 10 ) ); };
			$total = $r10( $g * $pl['per_guest'][ $role ] );
			$mix   = array();
			foreach ( $pl['mix'] as $m ) {
				$mix[] = array( 'item' => $m['label'], 'units' => $r10( $total * $m['share'] ) );
			}
			return hakef_mcp_text( array(
				'guests'      => $g,
				'role'        => 'main' === $role ? 'הכיבוד המרכזי באירוע' : 'קינוח אחרי ארוחה',
				'total_units' => $total,
				'mix'         => $mix,
				'add_cake'    => $g >= $pl['cake_from_guests'],
				'note'        => 'הערכה ראשונית לפי הניסיון של העסק. ההרכב, הטעמים והמחיר נקבעים בהצעת מחיר אישית.',
				'planner'     => $c['business']['url'] . '#plan',
			) );

		case 'prepare_quote_request':
			$labels = array( 'name' => 'שם', 'event_type' => 'סוג האירוע', 'guests' => 'מספר אורחים', 'date' => 'תאריך', 'location' => 'מקום', 'items' => 'מה מזמינים', 'notes' => 'הערות' );
			$lines  = array( 'שלום, אשמח לקבל הצעת מחיר.', '' );
			foreach ( $labels as $k => $label ) {
				if ( isset( $args[ $k ] ) && '' !== trim( (string) $args[ $k ] ) ) {
					$lines[] = $label . ': ' . mb_substr( sanitize_text_field( (string) $args[ $k ] ), 0, 300 );
				}
			}
			$msg = implode( "\n", $lines );
			return hakef_mcp_text( array(
				'message'      => $msg,
				'whatsapp_url' => 'https://wa.me/972509153197?text=' . rawurlencode( $msg ),
				'note'         => 'שום דבר לא נשלח. הלקוח צריך לפתוח את הקישור וללחוץ שליחה בוואטסאפ, או להתקשר ל־050-915-3197.',
			) );
	}
	return null;
}

function hakef_mcp_error( $id, $code, $message ) {
	return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) );
}

/** מטפל בהודעה אחת. מחזיר תשובה, או null להודעה בלי id (notification). */
function hakef_mcp_handle( $m ) {
	if ( ! is_array( $m ) || ! isset( $m['jsonrpc'], $m['method'] ) || '2.0' !== $m['jsonrpc'] ) {
		return hakef_mcp_error( is_array( $m ) && isset( $m['id'] ) ? $m['id'] : null, -32600, 'Invalid Request' );
	}
	$has_id = array_key_exists( 'id', $m );
	$id     = $has_id ? $m['id'] : null;
	$params = isset( $m['params'] ) && is_array( $m['params'] ) ? $m['params'] : array();
	switch ( $m['method'] ) {
		case 'initialize':
			$asked  = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
			$result = array(
				'protocolVersion' => in_array( $asked, HAKEF_MCP_PROTOCOLS, true ) ? $asked : HAKEF_MCP_PROTOCOLS[0],
				'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
				'serverInfo'      => array( 'name' => 'hakef', 'title' => 'הכיף שבאפיה', 'version' => HAKEF_MCP_VERSION ),
				'instructions'    => 'מידע על הכיף שבאפיה, קייטרינג קינוחים בוטיק בירושלים והסביבה. המחירים הם מחירי התחלה בשקלים. הזמנה נעשית רק על ידי הלקוח, בוואטסאפ או בטלפון.',
			);
			break;
		case 'ping':
			$result = new stdClass();
			break;
		case 'tools/list':
			$result = array( 'tools' => hakef_mcp_tools() );
			break;
		case 'tools/call':
			$name   = isset( $params['name'] ) ? (string) $params['name'] : '';
			$result = hakef_mcp_call( $name, isset( $params['arguments'] ) ? $params['arguments'] : array() );
			if ( null === $result ) {
				return $has_id ? hakef_mcp_error( $id, -32602, 'Unknown tool: ' . $name ) : null;
			}
			break;
		default:
			if ( 0 === strpos( (string) $m['method'], 'notifications/' ) || ! $has_id ) {
				return null;
			}
			return hakef_mcp_error( $id, -32601, 'Method not found' );
	}
	return $has_id ? array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ) : null;
}

function hakef_mcp_post( WP_REST_Request $r ) {
	$body = json_decode( $r->get_body(), true );
	if ( ! is_array( $body ) ) {
		return new WP_REST_Response( hakef_mcp_error( null, -32700, 'Parse error' ), 400 );
	}
	$batch = array_keys( $body ) === range( 0, count( $body ) - 1 ) && count( $body ) > 0;
	$out   = array();
	foreach ( $batch ? $body : array( $body ) as $m ) {
		$res = hakef_mcp_handle( $m );
		if ( null !== $res ) {
			$out[] = $res;
		}
	}
	if ( ! $out ) {
		// רק הודעות בלי id: מקבלים, בלי תוכן
		header( 'Access-Control-Allow-Origin: *' );
		status_header( 202 );
		exit;
	}
	return new WP_REST_Response( $batch ? $out : $out[0], 200 );
}
