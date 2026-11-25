<?php
/**
 * Public helpers for themes and plugins.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads options pages as "All languages" until yaofp_restore_current_lang() is called. Calls can be nested.
 *
 *     yaofp_switch_to_untranslated();
 *     $logo = get_field( 'logo', 'options' ); // the "All languages" value
 *     yaofp_restore_current_lang();
 */
function yaofp_switch_to_untranslated() {
	$GLOBALS['yaofp_untranslated_stack'][] = true;
}

/** Ends yaofp_switch_to_untranslated(). */
function yaofp_restore_current_lang() {
	if ( ! empty( $GLOBALS['yaofp_untranslated_stack'] ) ) {
		array_pop( $GLOBALS['yaofp_untranslated_stack'] );
	}
}

/**
 * Language mode of a field on an options page: 'translate', 'separate' or 'shared'.
 *
 * @param string|array $field   Field key, name or array.
 * @param string       $post_id Options page post ID, 'options' by default.
 */
function yaofp_get_field_mode( $field, $post_id = 'options' ) {
	if ( ! is_array( $field ) ) {
		$field = acf_get_field( $field );
	}

	$split = yaofp_split_post_id( $post_id );

	return $field ? yaofp_field_mode( $field, $split ? $split['base'] : $post_id ) : '';
}

/**
 * Value of an options-page field in a given language, whatever language is current.
 *
 * @param string $selector Field name or key.
 * @param string $lang     Language as stored (locale by default, e.g. en_US; slug with YAOFP_LANG_ATTRIBUTE = 'slug').
 * @param string $post_id  Options page post ID, 'options' by default.
 * @param bool   $format   Format the value like get_field().
 */
function yaofp_get_field_in( $selector, $lang, $post_id = 'options', $format = true ) {
	$split = yaofp_split_post_id( $post_id );
	$base  = $split ? $split['base'] : $post_id;

	return get_field( $selector, yaofp_post_id( $base, $lang ), $format );
}

// Drop-in compatibility with "ACF Options for Polylang" by Be API.
if ( ! function_exists( 'bea_aofp_switch_to_untranslated' ) ) {
	function bea_aofp_switch_to_untranslated() {
		yaofp_switch_to_untranslated();
	}
}

if ( ! function_exists( 'bea_aofp_restore_current_lang' ) ) {
	function bea_aofp_restore_current_lang() {
		yaofp_restore_current_lang();
	}
}
