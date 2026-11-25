<?php
/**
 * Languages, options-page post IDs and per-field language modes.
 *
 * Storage is the same as in "ACF Options for Polylang" by Be API, so the plugin is a drop-in replacement:
 * - "{post_id}_{lang}" holds the values of one language (e.g. options_en_US, bp_options_uk);
 * - "{post_id}" without a suffix holds the values shared by all languages ("All languages" in the admin bar).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A field gets its own value in every language and falls back to the default language when empty. */
const YAOFP_MODE_TRANSLATE = 'translate';

/** A field gets its own value in every language, with no fallback. */
const YAOFP_MODE_SEPARATE = 'separate';

/** A field has one value for all languages. */
const YAOFP_MODE_SHARED = 'shared';

/**
 * Polylang language property used as the storage suffix: 'locale' (en_US, default) or 'slug' (en).
 * Override with the YAOFP_LANG_ATTRIBUTE constant or the 'yaofp/lang_attribute' filter.
 * The constant of the original plugin is honoured too, so existing data keeps working.
 */
function yaofp_lang_attribute() {
	if ( defined( 'YAOFP_LANG_ATTRIBUTE' ) ) {
		$attribute = YAOFP_LANG_ATTRIBUTE;
	} elseif ( defined( 'BEA_ACF_OPTIONS_FOR_POLYLANG_LANG_ATTRIBUTE' ) ) {
		$attribute = BEA_ACF_OPTIONS_FOR_POLYLANG_LANG_ATTRIBUTE;
	} else {
		$attribute = 'locale';
	}

	$attribute = apply_filters( 'yaofp/lang_attribute', $attribute );

	return in_array( $attribute, [ 'locale', 'slug' ], true ) ? $attribute : 'locale';
}

/** The language being viewed or edited, or '' for "All languages" in the admin. */
function yaofp_current_lang() {
	$lang = function_exists( 'pll_current_language' ) ? pll_current_language( yaofp_lang_attribute() ) : '';

	return is_string( $lang ) ? $lang : '';
}

/** The default Polylang language. */
function yaofp_default_lang() {
	$lang = function_exists( 'pll_default_language' ) ? pll_default_language( yaofp_lang_attribute() ) : '';

	return is_string( $lang ) ? $lang : '';
}

/** All Polylang languages, empty ones included. */
function yaofp_languages() {
	static $cache = [];

	$attribute = yaofp_lang_attribute();
	if ( ! isset( $cache[ $attribute ] ) ) {
		$list                = function_exists( 'pll_languages_list' ) ? pll_languages_list( [ 'hide_empty' => false, 'fields' => $attribute ] ) : [];
		$cache[ $attribute ] = is_array( $list ) ? array_values( array_filter( $list, 'is_string' ) ) : [];
	}

	return $cache[ $attribute ];
}

/**
 * Options pages handled by the plugin, keyed by their post ID ('options' or a custom one).
 * A page registered with 'yaofp' => false (acf_add_options_page) keeps one set of values for all languages.
 *
 * @return array<string, array> Post ID => options page settings.
 */
function yaofp_option_pages() {
	$pages = [ 'options' => [] ];

	if ( function_exists( 'acf_get_options_pages' ) ) {
		foreach ( (array) acf_get_options_pages() as $page ) {
			if ( ! empty( $page['post_id'] ) && is_string( $page['post_id'] ) ) {
				$id           = 'option' === $page['post_id'] ? 'options' : $page['post_id'];
				$pages[ $id ] = $page;
			}
		}
	}

	/**
	 * Filters the options pages that get per-language values.
	 *
	 * @param array<string, array> $pages Post ID => options page settings. Remove an ID to keep it the same in every language.
	 */
	return apply_filters( 'yaofp/option_pages', $pages );
}

/** Whether a base post ID (no language suffix) is a translated options page. */
function yaofp_is_translated_page( $base ) {
	$pages = yaofp_option_pages();

	if ( ! isset( $pages[ $base ] ) ) {
		return false;
	}

	$translated = ! isset( $pages[ $base ]['yaofp'] ) || false !== $pages[ $base ]['yaofp'];

	// Compatibility with the original plugin's filter.
	if ( in_array( $base, (array) apply_filters( 'bea.aofp.excluded_post_ids', [] ), true ) ) {
		$translated = false;
	}

	return (bool) apply_filters( 'yaofp/is_translated_page', $translated, $base );
}

/**
 * Splits an ACF post ID of an options page into its base and language.
 *
 * Only exact matches count: the base must be a registered options page and the suffix a Polylang language.
 *
 * @param mixed $post_id ACF post ID.
 * @return array{base: string, lang: string}|null Null when the post ID isn't an options page.
 */
function yaofp_split_post_id( $post_id ) {
	if ( ! is_string( $post_id ) || '' === $post_id ) {
		return null;
	}

	if ( 'option' === $post_id ) {
		$post_id = 'options';
	}

	$pages = yaofp_option_pages();

	if ( isset( $pages[ $post_id ] ) ) {
		return [ 'base' => $post_id, 'lang' => '' ];
	}

	foreach ( yaofp_languages() as $lang ) {
		$suffix = '_' . $lang;
		if ( substr( $post_id, -strlen( $suffix ) ) === $suffix ) {
			$base = substr( $post_id, 0, -strlen( $suffix ) );
			if ( isset( $pages[ $base ] ) ) {
				return [ 'base' => $base, 'lang' => $lang ];
			}
		}
	}

	return null;
}

/** The post ID that stores the values of one language, or the shared values for ''. */
function yaofp_post_id( $base, $lang ) {
	return '' === $lang ? $base : $base . '_' . $lang;
}

/**
 * The top-level field a (sub) field belongs to: the repeater or group for a sub field, the field itself otherwise.
 * Sub fields follow the language mode of their top-level field, so a row is never mixed from two languages.
 */
function yaofp_top_level_field( $field ) {
	$guard = 0;

	while ( is_array( $field ) && ! empty( $field['parent'] ) && yaofp_is_field_parent( $field['parent'] ) && $guard++ < 20 ) {
		$parent = acf_get_field( $field['parent'] );
		if ( ! $parent ) {
			break;
		}
		$field = $parent;
	}

	return $field;
}

/** Whether a field is nested in a repeater, group or flexible content field. */
function yaofp_is_sub_field( $field ) {
	return is_array( $field ) && ! empty( $field['parent'] ) && yaofp_is_field_parent( $field['parent'] );
}

/** Whether a field's parent is another field (and not a field group). */
function yaofp_is_field_parent( $parent ) {
	if ( is_string( $parent ) && ! is_numeric( $parent ) ) {
		return 0 === strpos( $parent, 'field_' );
	}

	return 'acf-field' === get_post_type( (int) $parent );
}

/**
 * Language mode of a field on an options page: translate, separate or shared.
 *
 * Taken from the field's "Translations" setting, the same one Polylang Pro shows on every ACF field:
 * - sync (Synchronize)                                  → shared: one value for all languages;
 * - ignore                                              → separate: own value per language, no fallback;
 * - translate, translate_once, copy_once (or not set)   → translate: own value, empty falls back to the default language.
 *
 * A field without the setting gets the page default ('yaofp_mode' in acf_add_options_page), then the 'yaofp/default_mode' filter.
 *
 * @param array  $field ACF field.
 * @param string $base  Options page post ID without a language suffix.
 */
function yaofp_field_mode( $field, $base = '' ) {
	$top  = yaofp_top_level_field( $field );
	$mode = '';

	$setting = isset( $top['translations'] ) ? $top['translations'] : '';
	if ( 'sync' === $setting || YAOFP_MODE_SHARED === $setting ) {
		$mode = YAOFP_MODE_SHARED;
	} elseif ( 'ignore' === $setting || YAOFP_MODE_SEPARATE === $setting ) {
		$mode = YAOFP_MODE_SEPARATE;
	} elseif ( in_array( $setting, [ 'translate', 'translate_once', 'copy_once' ], true ) ) {
		$mode = YAOFP_MODE_TRANSLATE;
	}

	if ( '' === $mode ) {
		$pages = yaofp_option_pages();
		$mode  = isset( $pages[ $base ]['yaofp_mode'] ) ? $pages[ $base ]['yaofp_mode'] : '';
	}

	if ( '' === $mode ) {
		$mode = apply_filters( 'yaofp/default_mode', YAOFP_MODE_TRANSLATE );
	}

	/**
	 * Filters the language mode of a field on an options page.
	 *
	 * @param string $mode  translate, separate or shared.
	 * @param array  $field The top-level field.
	 * @param string $base  Options page post ID without a language suffix.
	 */
	$mode = apply_filters( 'yaofp/field_mode', $mode, $top, $base );

	return in_array( $mode, [ YAOFP_MODE_TRANSLATE, YAOFP_MODE_SEPARATE, YAOFP_MODE_SHARED ], true ) ? $mode : YAOFP_MODE_TRANSLATE;
}

/**
 * Whether a loaded value counts as empty for the fallback: null, '', an empty array or an array of empty strings.
 * false and 0 are real values (true/false and number fields), except for fields made of rows,
 * where 0 / false / '' all mean "no rows".
 *
 * @param mixed      $value Loaded value.
 * @param array|null $field ACF field, when known.
 */
function yaofp_is_empty_value( $value, $field = null ) {
	if ( null === $value || '' === $value ) {
		return true;
	}

	$rows = [ 'repeater', 'flexible_content', 'gallery', 'relationship' ];
	if ( is_array( $field ) && isset( $field['type'] ) && in_array( $field['type'], $rows, true ) && empty( $value ) ) {
		return true;
	}

	if ( is_array( $value ) ) {
		foreach ( $value as $item ) {
			if ( ! yaofp_is_empty_value( $item ) ) {
				return false;
			}
		}
		return true;
	}

	return false;
}
