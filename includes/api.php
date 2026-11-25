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

/**
 * Copies a field's value from one language into the shared ("All languages") storage.
 *
 * Use it once after switching a field to Synchronize: until then the field shows the shared value,
 * which may be empty or older than the per-language ones.
 *
 * @param string|array $field     Field key, name or array.
 * @param string       $post_id   Options page post ID, 'options' by default.
 * @param string       $from_lang Language to copy from; the default language when empty.
 * @return bool Whether a value was copied.
 */
function yaofp_copy_to_shared( $field, $post_id = 'options', $from_lang = '' ) {
	if ( ! is_array( $field ) ) {
		$field = acf_get_field( $field );
	}

	$split = yaofp_split_post_id( $post_id );
	$base  = $split ? $split['base'] : $post_id;
	$lang  = $from_lang ? $from_lang : yaofp_default_lang();

	if ( ! $field || ! $lang ) {
		return false;
	}

	$value = yaofp_raw_value( yaofp_post_id( $base, $lang ), $field );
	if ( yaofp_is_empty_value( $value, $field ) ) {
		return false;
	}

	yaofp_busy( true );
	acf_update_value( $value, $base, $field );
	yaofp_busy( false );

	return true;
}

/**
 * Copies every Synchronize field of a field group from one language into the shared storage.
 *
 * @param string $group_key Field group key.
 * @param string $post_id   Options page post ID, 'options' by default.
 * @param string $from_lang Language to copy from; the default language when empty.
 * @return string[] Names of the copied fields.
 */
function yaofp_copy_group_to_shared( $group_key, $post_id = 'options', $from_lang = '' ) {
	$split  = yaofp_split_post_id( $post_id );
	$base   = $split ? $split['base'] : $post_id;
	$copied = [];

	foreach ( (array) acf_get_fields( $group_key ) as $field ) {
		if ( YAOFP_MODE_SHARED === yaofp_field_mode( $field, $base ) && yaofp_copy_to_shared( $field, $base, $from_lang ) ) {
			$copied[] = $field['name'];
		}
	}

	return $copied;
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
