<?php
/**
 * Plugin Name: הכיף שבאפיה: מתכונים
 * Description: סוג תוכן "מתכון" עם מסך הוספה פשוט (כותרת, תמונה, מצרכים, אופן הכנה), תפקיד "עורכת מתכונים", ומפת אתר למתכונים בלבד.
 * Version: 1.1.0
 *
 * מותקן כ־mu-plugin (wp-content/mu-plugins), ולכן נטען תמיד ואי אפשר לכבות אותו בטעות.
 */

defined( 'ABSPATH' ) || exit;

const HAKEF_RECIPES_VERSION = '1.0.0';

/** שדות המתכון: מפתח => [תווית, הסבר, סוג] */
function hakef_recipe_fields() {
	return array(
		'summary'     => array( 'תקציר קצר', 'משפט או שניים שמתארים את המתכון. יופיע בכרטיס ובתוצאות של גוגל.', 'textarea-short' ),
		'ingredients' => array( 'מצרכים', 'כל מצרך בשורה נפרדת. שורה שמסתיימת בנקודתיים (למשל "לקרם:") תהפוך לכותרת קטנה.', 'textarea' ),
		'steps'       => array( 'אופן ההכנה', 'כל שלב בשורה נפרדת. המספור נעשה לבד.', 'textarea' ),
		'time'        => array( 'זמן הכנה', 'למשל: 40 דקות, או: שעה וחצי. לא חובה.', 'text' ),
		'yield'       => array( 'כמות', 'למשל: תבנית 26, או: 24 עוגיות. לא חובה.', 'text' ),
		'tip'         => array( 'טיפ', 'טיפ קטן מהמטבח. לא חובה.', 'textarea-short' ),
	);
}

/* ------------------------------------------------------------------ סוג התוכן */
add_action( 'init', function () {
	register_post_type( 'recipe', array(
		'labels'        => array(
			'name'                  => 'מתכונים',
			'singular_name'         => 'מתכון',
			'menu_name'             => 'מתכונים',
			'add_new'               => 'מתכון חדש',
			'add_new_item'          => 'הוספת מתכון חדש',
			'edit_item'             => 'עריכת מתכון',
			'new_item'              => 'מתכון חדש',
			'view_item'             => 'צפייה במתכון',
			'view_items'            => 'צפייה במתכונים',
			'search_items'          => 'חיפוש מתכונים',
			'not_found'             => 'עדיין אין מתכונים',
			'not_found_in_trash'    => 'אין מתכונים בפח',
			'all_items'             => 'כל המתכונים',
			'featured_image'        => 'תמונת המתכון',
			'set_featured_image'    => 'בחירת תמונה',
			'remove_featured_image' => 'הסרת התמונה',
			'use_featured_image'    => 'שימוש כתמונת המתכון',
		),
		'public'        => true,
		'has_archive'   => 'מתכונים',
		'rewrite'       => array( 'slug' => 'מתכונים', 'with_front' => false ),
		'supports'      => array( 'title', 'thumbnail' ),
		'menu_icon'     => 'dashicons-carrot',
		'menu_position' => 3,
		'show_in_rest'  => false, // מסך עריכה קלאסי ופשוט, בלי עורך הבלוקים
		'capability_type' => array( 'recipe', 'recipes' ),
		'map_meta_cap'  => true,
	) );
} );

/* ------------------------------------------------------------------ הרשאות ותפקיד */
add_action( 'init', function () {
	if ( get_option( 'hakef_recipes_version' ) === HAKEF_RECIPES_VERSION ) {
		return;
	}
	$caps = array( 'edit_recipe', 'read_recipe', 'delete_recipe', 'edit_recipes', 'edit_others_recipes', 'publish_recipes',
		'read_private_recipes', 'delete_recipes', 'delete_published_recipes', 'delete_others_recipes', 'edit_published_recipes', 'delete_private_recipes', 'edit_private_recipes' );
	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			foreach ( $caps as $c ) {
				$role->add_cap( $c );
			}
		}
	}
	remove_role( 'hakef_recipe_editor' );
	add_role( 'hakef_recipe_editor', 'עורכת מתכונים', array_merge( array( 'read' => true, 'upload_files' => true ), array_fill_keys( $caps, true ) ) );
	flush_rewrite_rules( false );
	update_option( 'hakef_recipes_version', HAKEF_RECIPES_VERSION );
}, 20 );

/* ------------------------------------------------------------------ מסך העריכה */
add_action( 'add_meta_boxes_recipe', function () {
	add_meta_box( 'hakef_recipe_details', 'פרטי המתכון', 'hakef_render_recipe_box', 'recipe', 'normal', 'high' );
} );

function hakef_render_recipe_box( $post ) {
	wp_nonce_field( 'hakef_recipe_save', 'hakef_recipe_nonce' );
	echo '<div class="hakef-fields">';
	foreach ( hakef_recipe_fields() as $key => list( $label, $help, $type ) ) {
		$value = get_post_meta( $post->ID, '_hakef_' . $key, true );
		$id    = 'hakef_' . $key;
		echo '<p class="hakef-field hakef-field--' . esc_attr( $type ) . '">';
		echo '<label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
		echo '<span class="hakef-help">' . esc_html( $help ) . '</span>';
		if ( 'text' === $type ) {
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '">';
		} else {
			$rows = 'textarea' === $type ? 10 : 3;
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" rows="' . (int) $rows . '">' . esc_textarea( $value ) . '</textarea>';
		}
		echo '</p>';
	}
	echo '</div>';
}

add_action( 'save_post_recipe', function ( $post_id ) {
	if ( ! isset( $_POST['hakef_recipe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hakef_recipe_nonce'] ) ), 'hakef_recipe_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( hakef_recipe_fields() as $key => $def ) {
		$field = 'hakef_' . $key;
		if ( ! isset( $_POST[ $field ] ) ) {
			continue;
		}
		$raw   = wp_unslash( $_POST[ $field ] );
		$value = 'text' === $def[2] ? sanitize_text_field( $raw ) : sanitize_textarea_field( $raw );
		update_post_meta( $post_id, '_hakef_' . $key, $value );
	}
} );

/* הסבר קצר בראש מסך העריכה, ועיצוב נוח לשדות */
add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'recipe' !== $screen->post_type ) {
		return;
	}
	echo '<style>
	.hakef-fields{display:grid;gap:6px}
	.hakef-field{margin:0 0 14px;display:grid;gap:4px}
	.hakef-field label{font-size:15px}
	.hakef-help{color:#646970;font-size:13px}
	.hakef-field input,.hakef-field textarea{width:100%;font-size:15px;line-height:1.7;padding:8px 10px}
	.hakef-guide{background:#fff;border-inline-start:4px solid #b8935a;padding:12px 16px;margin:14px 0;font-size:14px;line-height:1.8}
	.column-hakef_thumb{width:64px}.column-hakef_thumb img{width:56px;height:56px;object-fit:cover;border-radius:6px}
	</style>';
} );

add_action( 'edit_form_after_title', function ( $post ) {
	if ( 'recipe' !== $post->post_type ) {
		return;
	}
	echo '<div class="hakef-guide"><strong>איך מוסיפים מתכון:</strong> כותבים שם למעלה, בוחרים תמונה בצד ("תמונת המתכון"), '
		. 'ממלאים מצרכים ואופן הכנה, שורה לכל פריט, ולוחצים "פרסום". העיצוב נעשה לבד.</div>';
} );

add_filter( 'admin_post_thumbnail_html', function ( $html, $post_id ) {
	if ( 'recipe' === get_post_type( $post_id ) ) {
		$html .= '<p class="hakef-help">הכי יפה: צילום אמיתי, לרוחב, באור טבעי. בלי תמונה תוצג כריכה מעוצבת עם שם המתכון.</p>';
	}
	return $html;
}, 10, 2 );

/* הודעות בעברית אחרי שמירה ופרסום */
add_filter( 'post_updated_messages', function ( $messages ) {
	$post = get_post();
	$view = $post ? ' <a href="' . esc_url( get_permalink( $post ) ) . '">לצפייה במתכון</a>' : '';
	$messages['recipe'] = array(
		0  => '',
		1  => 'המתכון עודכן.' . $view,
		4  => 'המתכון עודכן.',
		6  => 'המתכון פורסם!' . $view,
		7  => 'המתכון נשמר.',
		8  => 'המתכון נשלח לבדיקה.',
		9  => 'פרסום המתכון תוזמן.',
		10 => 'הטיוטה עודכנה.',
	);
	return $messages;
} );

/* עמודת תמונה ברשימת המתכונים */
add_filter( 'manage_recipe_posts_columns', function ( $cols ) {
	return array_merge( array( 'cb' => $cols['cb'], 'hakef_thumb' => 'תמונה' ), $cols );
} );
add_action( 'manage_recipe_posts_custom_column', function ( $col, $post_id ) {
	if ( 'hakef_thumb' === $col ) {
		echo get_the_post_thumbnail( $post_id, 'thumbnail' ) ?: '—';
	}
}, 10, 2 );

/* ------------------------------------------------------------------ ממשק מצומצם לעורכת מתכונים */
function hakef_is_recipe_editor() {
	$u = wp_get_current_user();
	return $u && in_array( 'hakef_recipe_editor', (array) $u->roles, true );
}

add_action( 'admin_menu', function () {
	if ( ! hakef_is_recipe_editor() ) {
		return;
	}
	global $menu;
	$keep = array( 'edit.php?post_type=recipe', 'upload.php', 'profile.php' );
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && ! in_array( $item[2], $keep, true ) ) {
			remove_menu_page( $item[2] );
		}
	}
}, 999 );

add_action( 'load-index.php', function () {
	if ( hakef_is_recipe_editor() ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=recipe' ) );
		exit;
	}
} );

add_filter( 'login_redirect', function ( $redirect, $requested, $user ) {
	if ( $user instanceof WP_User && in_array( 'hakef_recipe_editor', (array) $user->roles, true ) ) {
		return admin_url( 'edit.php?post_type=recipe' );
	}
	return $redirect;
}, 10, 3 );

/* ------------------------------------------------------------------ קיצור דרך למתכונים בראש לוח הבקרה (למנהלת האתר) */
add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'edit_recipes' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'hakef_recipes_widget', 'המתכונים באתר', 'hakef_render_dashboard_widget' );
	// "טיוטה מהירה" יוצרת פוסט ולא מתכון, וחדשות וורדפרס רק מסיחות: מורידים את שתיהן
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	global $wp_meta_boxes;
	$normal = $wp_meta_boxes['dashboard']['normal']['core'] ?? array();
	if ( isset( $normal['hakef_recipes_widget'] ) ) {
		$mine = array( 'hakef_recipes_widget' => $normal['hakef_recipes_widget'] );
		unset( $normal['hakef_recipes_widget'] );
		$wp_meta_boxes['dashboard']['normal']['core'] = $mine + $normal;
	}
} );

// גם כשנשמר למשתמשת סדר קופסאות משלה, קופסת המתכונים נשארת ראשונה
add_filter( 'get_user_option_meta-box-order_dashboard', function ( $order ) {
	if ( ! is_array( $order ) || ! current_user_can( 'edit_recipes' ) ) {
		return $order;
	}
	foreach ( $order as $col => $ids ) {
		$order[ $col ] = implode( ',', array_diff( array_filter( explode( ',', (string) $ids ) ), array( 'hakef_recipes_widget' ) ) );
	}
	$order['normal'] = ltrim( 'hakef_recipes_widget,' . ( $order['normal'] ?? '' ), ',' );
	$order['normal'] = rtrim( $order['normal'], ',' );
	return $order;
} );

function hakef_render_dashboard_widget() {
	$count     = wp_count_posts( 'recipe' );
	$published = (int) ( $count->publish ?? 0 );
	$drafts    = (int) ( $count->draft ?? 0 );
	$recent    = get_posts( array( 'post_type' => 'recipe', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => 5 ) );
	echo '<style>
	.hakef-dash{font-size:14px;line-height:1.8}
	.hakef-dash__actions{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0 16px}
	.hakef-dash__actions .button-hero{font-size:16px}
	.hakef-dash__list{margin:0 0 12px;padding:0;list-style:none}
	.hakef-dash__list li{display:flex;align-items:center;gap:10px;padding:6px 0;border-top:1px solid #f0f0f1}
	.hakef-dash__list img{width:40px;height:40px;object-fit:cover;border-radius:6px}
	.hakef-dash__steps{margin:6px 22px 0 0;color:#50575e}
	</style>';
	echo '<div class="hakef-dash">';
	echo '<p>כאן מוסיפים ומעדכנים את המתכונים שמופיעים באתר. העיצוב נעשה לבד.</p>';
	echo '<p class="hakef-dash__actions">'
		. '<a class="button button-primary button-hero" href="' . esc_url( admin_url( 'post-new.php?post_type=recipe' ) ) . '">+ הוספת מתכון חדש</a>'
		. '<a class="button button-hero" href="' . esc_url( admin_url( 'edit.php?post_type=recipe' ) ) . '">עריכת המתכונים הקיימים</a>'
		. '</p>';
	$pub_txt   = 1 === $published ? 'מתכון אחד' : '<strong>' . $published . '</strong> מתכונים';
	$draft_txt = ! $drafts ? '' : ( 1 === $drafts ? ' (ועוד טיוטה אחת)' : ' (ועוד ' . $drafts . ' טיוטות)' );
	printf(
		'<p>באתר עכשיו: %s%s. <a href="%s" target="_blank" rel="noopener">לצפייה בעמוד המתכונים</a></p>',
		$pub_txt,
		$draft_txt,
		esc_url( get_post_type_archive_link( 'recipe' ) )
	);
	if ( $recent ) {
		echo '<ul class="hakef-dash__list">';
		foreach ( $recent as $r ) {
			$thumb = get_the_post_thumbnail( $r, 'thumbnail' );
			printf(
				'<li>%s<a href="%s">%s</a>%s</li>',
				$thumb ?: '',
				esc_url( get_edit_post_link( $r->ID ) ),
				esc_html( get_the_title( $r ) ),
				'draft' === $r->post_status ? ' <em>(טיוטה)</em>' : ''
			);
		}
		echo '</ul>';
	}
	echo '<strong>איך מוסיפים מתכון:</strong>';
	echo '<ol class="hakef-dash__steps"><li>כותבים שם למתכון.</li><li>בוחרים תמונה ("תמונת המתכון" בצד).</li>'
		. '<li>ממלאים מצרכים ואופן הכנה, שורה לכל פריט.</li><li>לוחצים "פרסום".</li></ol>';
	echo '</div>';
}

/* ------------------------------------------------------------------ בלי הרשמה פתוחה לחשבונות
 * מגיבים למתכונים בשם ומייל בלבד (ראו hakef-community.php), כך שאין צורך בחשבונות,
 * וכל הרשמת ספאם הייתה שולחת התראה למייל של בעלת העסק. */
add_filter( 'pre_option_users_can_register', '__return_zero' );

/* ------------------------------------------------------------------ מפת אתר: רק מתכונים */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return in_array( $name, array( 'users', 'taxonomies' ), true ) ? false : $provider;
}, 10, 2 );
add_filter( 'wp_sitemaps_post_types', function ( $types ) {
	return isset( $types['recipe'] ) ? array( 'recipe' => $types['recipe'] ) : array();
} );

/* ------------------------------------------------------------------ עזרים לתבנית */
/**
 * מפרק טקסט לשורות: שורה שמסתיימת בנקודתיים היא כותרת.
 * מחזיר מערך של ['type' => 'heading'|'item', 'text' => ...]
 */
function hakef_parse_lines( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( preg_replace( '/^\s*(?:[-*•·]|\d+[.)])\s*/u', '', $line ) );
		if ( '' === $line ) {
			continue;
		}
		if ( preg_match( '/[:：]\s*$/u', $line ) ) {
			$out[] = array( 'type' => 'heading', 'text' => rtrim( $line, ":： \t" ) );
		} else {
			$out[] = array( 'type' => 'item', 'text' => $line );
		}
	}
	return $out;
}

/** "40 דקות" / "שעה וחצי" / "שעתיים" => ISO 8601 (PT40M), או ריק */
function hakef_iso_duration( $text ) {
	$text = (string) $text;
	$min  = 0;
	if ( preg_match( '/(\d+)\s*(?:דק|ד\')/u', $text, $m ) ) {
		$min += (int) $m[1];
	}
	if ( preg_match( '/(\d+)\s*שע/u', $text, $m ) ) {
		$min += 60 * (int) $m[1];
	} elseif ( false !== mb_strpos( $text, 'שעתיים' ) ) {
		$min += 120;
	} elseif ( false !== mb_strpos( $text, 'שעה' ) ) {
		$min += 60;
	}
	if ( false !== mb_strpos( $text, 'וחצי' ) ) {
		$min += 30;
	}
	return $min ? 'PT' . $min . 'M' : '';
}

function hakef_recipe_meta( $post_id ) {
	$data = array();
	foreach ( array_keys( hakef_recipe_fields() ) as $k ) {
		$data[ $k ] = (string) get_post_meta( $post_id, '_hakef_' . $k, true );
	}
	return $data;
}
