<?php
/**
 * Admin: which language is being edited, a badge on every field with its language mode,
 * and the "Translations" field setting when Polylang Pro (which adds it already) isn't installed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yaofp_register_admin_hooks() {
	add_action( 'acf/options_page/submitbox_before_major_actions', 'yaofp_submitbox_notice' );
	add_filter( 'acf/prepare_field', 'yaofp_prepare_field_badge', 20 );
	add_filter( 'acf/get_field_label', 'yaofp_field_label_badge', 20, 3 );
	add_action( 'acf/input/admin_enqueue_scripts', 'yaofp_enqueue_styles' );

	if ( ! defined( 'POLYLANG_PRO' ) ) {
		add_action( 'acf/render_field_settings', 'yaofp_render_field_setting' );
	}
}

/** Language name for a stored language value (locale or slug). */
function yaofp_lang_name( $lang ) {
	if ( function_exists( 'pll_languages_list' ) ) {
		$values = pll_languages_list( [ 'hide_empty' => false, 'fields' => yaofp_lang_attribute() ] );
		$names  = pll_languages_list( [ 'hide_empty' => false, 'fields' => 'name' ] );
		$index  = array_search( $lang, (array) $values, true );
		if ( false !== $index && isset( $names[ $index ] ) ) {
			return $names[ $index ];
		}
	}

	return $lang;
}

/** Tells, above the Update button of an options page, which values are being edited. */
function yaofp_submitbox_notice( $page ) {
	$base = isset( $page['post_id'] ) && is_string( $page['post_id'] ) ? ( 'option' === $page['post_id'] ? 'options' : $page['post_id'] ) : '';
	if ( ! $base || ! yaofp_is_translated_page( $base ) ) {
		return;
	}

	$lang = yaofp_current_lang();

	if ( $lang ) {
		/* translators: %s: language name */
		$text = sprintf( __( 'You are editing the values for <strong>%s</strong>.', 'yurba-acf-options-for-polylang' ), esc_html( yaofp_lang_name( $lang ) ) );
	} else {
		$text = __( 'You are editing the values for <strong>all languages</strong>. They are used where a language has no value of its own.', 'yurba-acf-options-for-polylang' );
	}

	$hint = __( 'Switch the language in the admin bar. Fields marked "All languages" are the same in every language.', 'yurba-acf-options-for-polylang' );

	echo '<div class="misc-pub-section yaofp-notice">' . wp_kses( $text, [ 'strong' => [] ] ) . '<br><span class="description">' . esc_html( $hint ) . '</span></div>';
}

/** Marks top-level fields on an options page with their language mode. */
function yaofp_prepare_field_badge( $field ) {
	if ( ! $field || yaofp_is_sub_field( $field ) || ! function_exists( 'acf_get_form_data' ) || 'options' !== acf_get_form_data( 'screen' ) ) {
		return $field;
	}

	$split = yaofp_split_post_id( acf_get_form_data( 'post_id' ) );
	if ( ! $split || ! yaofp_is_translated_page( $split['base'] ) || in_array( $field['type'], [ 'tab', 'accordion', 'message' ], true ) ) {
		return $field;
	}

	$lang    = yaofp_current_lang();
	$default = yaofp_default_lang();
	$mode    = yaofp_field_mode( $field, $split['base'] );
	$label   = '';

	if ( YAOFP_MODE_SHARED === $mode ) {
		$label = __( 'All languages', 'yurba-acf-options-for-polylang' );
	} elseif ( YAOFP_MODE_SEPARATE === $mode && $lang ) {
		$label = __( 'This language only', 'yurba-acf-options-for-polylang' );
	} elseif ( YAOFP_MODE_TRANSLATE === $mode && $lang && $default && $lang !== $default ) {
		/* translators: %s: default language name */
		$label = sprintf( __( 'Empty = %s', 'yurba-acf-options-for-polylang' ), yaofp_lang_name( $default ) );
	}

	if ( $label ) {
		$field['yaofp_badge'] = [ 'mode' => $mode, 'label' => $label ];
	}

	return $field;
}

/** Prints the badge set by yaofp_prepare_field_badge() after the field label. */
function yaofp_field_label_badge( $label, $field, $context ) {
	if ( empty( $field['yaofp_badge'] ) || 'admin' === $context ) {
		return $label;
	}

	return $label . ' <span class="yaofp-badge yaofp-badge--' . esc_attr( $field['yaofp_badge']['mode'] ) . '">' . esc_html( $field['yaofp_badge']['label'] ) . '</span>';
}

function yaofp_enqueue_styles() {
	wp_enqueue_style( 'yaofp-admin', YAOFP_URL . 'assets/css/admin.css', [], YAOFP_VERSION );
}

/**
 * The same "Translations" setting Polylang Pro adds to ACF fields, for sites on free Polylang.
 * Only affects fields shown on options pages.
 */
function yaofp_render_field_setting( $field ) {
	if ( in_array( $field['type'], [ 'tab', 'accordion', 'message' ], true ) || yaofp_is_sub_field( $field ) ) {
		return;
	}

	acf_render_field_setting(
		$field,
		[
			'label'         => __( 'Translations', 'yurba-acf-options-for-polylang' ),
			'instructions'  => __( 'On options pages: how the value differs between languages. Sub fields follow this field.', 'yurba-acf-options-for-polylang' ),
			'name'          => 'translations',
			'type'          => 'select',
			'choices'       => [
				'translate' => __( 'Translate (empty falls back to the default language)', 'yurba-acf-options-for-polylang' ),
				'ignore'    => __( 'Separate per language (no fallback)', 'yurba-acf-options-for-polylang' ),
				'sync'      => __( 'Same for all languages', 'yurba-acf-options-for-polylang' ),
			],
			'default_value' => 'translate',
		]
	);
}
