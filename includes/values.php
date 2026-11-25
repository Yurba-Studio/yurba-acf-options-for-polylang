<?php
/**
 * Routes ACF option values to the right language storage.
 *
 * - ACF post ID: "options" / custom IDs get the current language suffix (ACF does it for "options" itself
 *   through the current_language setting; custom IDs get it here).
 * - Shared fields always read and write the unsuffixed post ID.
 * - Translated fields read their own language and, on the front end, fall back to the default language
 *   (then to the "All languages" values) while empty. Post, term and media IDs are swapped for their
 *   translations on the way.
 * - Sub fields follow their top-level field, so a repeater is never assembled from two languages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stack for yaofp_switch_to_untranslated(): while not empty, options pages read the "All languages" values. */
$GLOBALS['yaofp_untranslated_stack'] = [];

function yaofp_register_value_hooks() {
	add_filter( 'acf/settings/current_language', 'yaofp_acf_current_language', 20 );
	add_filter( 'acf/validate_post_id', 'yaofp_validate_post_id', 10, 2 );
	add_filter( 'acf/pre_load_value', 'yaofp_pre_load_value', 10, 3 );
	add_filter( 'acf/pre_update_value', 'yaofp_pre_update_value', 10, 4 );
	add_filter( 'acf/pre_delete_value', 'yaofp_pre_delete_value', 10, 4 );
	add_filter( 'acf/load_reference', 'yaofp_load_reference', 10, 3 );
}

/**
 * Language ACF appends to the "options" post ID. The default language gets a suffix too (no default_language
 * setting), so "options" without a suffix stays free for the values shared by all languages.
 */
function yaofp_acf_current_language( $language ) {
	return yaofp_current_lang();
}

/** Adds the language suffix to custom options post IDs, and strips it where the page isn't translated. */
function yaofp_validate_post_id( $post_id, $original ) {
	$split = yaofp_split_post_id( $post_id );
	if ( ! $split ) {
		return $post_id;
	}

	$base = $split['base'];

	if ( ! empty( $GLOBALS['yaofp_untranslated_stack'] ) || ! yaofp_is_translated_page( $base ) ) {
		return $base;
	}

	// An explicit language (get_field( 'x', 'options_en_US' )) is kept as is.
	if ( '' !== $split['lang'] ) {
		return $post_id;
	}

	// ACF already appended the language to "options" when it had one; here it's the "All languages" view.
	if ( 'options' === $base ) {
		return $post_id;
	}

	return yaofp_post_id( $base, yaofp_current_lang() );
}

/** Whether a value is being loaded by this plugin (no re-routing for nested loads). */
function yaofp_busy( $set = null ) {
	static $busy = 0;

	if ( true === $set ) {
		$busy++;
	} elseif ( false === $set ) {
		$busy = max( 0, $busy - 1 );
	}

	return $busy > 0;
}

/** Loads a value from a specific post ID without this plugin re-routing it. */
function yaofp_raw_value( $post_id, $field ) {
	yaofp_busy( true );
	$value = acf_get_value( $post_id, $field );
	yaofp_busy( false );

	return $value;
}

/** Whether translated fields fall back to the default language here: on the front end, in AJAX and REST. */
function yaofp_fallback_enabled( $base, $field ) {
	$enabled = ! is_admin() || wp_doing_ajax();

	return (bool) apply_filters( 'yaofp/fallback_enabled', $enabled, $base, $field );
}

function yaofp_pre_load_value( $value, $post_id, $field ) {
	if ( null !== $value || yaofp_busy() || ! is_array( $field ) || yaofp_is_sub_field( $field ) ) {
		return $value;
	}

	$split = yaofp_split_post_id( $post_id );
	if ( ! $split || '' === $split['lang'] || ! yaofp_is_translated_page( $split['base'] ) ) {
		return $value;
	}

	$base = $split['base'];
	$lang = $split['lang'];
	$mode = yaofp_field_mode( $field, $base );

	if ( YAOFP_MODE_SHARED === $mode ) {
		$shared = yaofp_raw_value( $base, $field );

		// A field switched to "shared" may still only have per-language values: use the default language until the shared value is saved.
		if ( yaofp_is_empty_value( $shared, $field ) ) {
			$default = yaofp_default_lang();
			if ( $default ) {
				$from_default = yaofp_raw_value( yaofp_post_id( $base, $default ), $field );
				if ( ! yaofp_is_empty_value( $from_default, $field ) ) {
					return yaofp_translate_ids( $from_default, $field, $lang );
				}
			}
		}

		return $shared;
	}

	if ( YAOFP_MODE_SEPARATE === $mode || ! yaofp_fallback_enabled( $base, $field ) ) {
		return $value;
	}

	$own = yaofp_raw_value( $post_id, $field );
	if ( ! yaofp_is_empty_value( $own, $field ) ) {
		return $own;
	}

	foreach ( yaofp_fallback_post_ids( $base, $lang ) as $fallback_id ) {
		$fallback = yaofp_raw_value( $fallback_id, $field );
		if ( ! yaofp_is_empty_value( $fallback, $field ) ) {
			return yaofp_translate_ids( $fallback, $field, $lang );
		}
	}

	return $own;
}

/**
 * Where an empty translated value is looked up: the default language, then the "All languages" values.
 *
 * @return string[]
 */
function yaofp_fallback_post_ids( $base, $lang ) {
	$ids     = [];
	$default = yaofp_default_lang();

	if ( $default && $default !== $lang ) {
		$ids[] = yaofp_post_id( $base, $default );
	}
	$ids[] = $base;

	return apply_filters( 'yaofp/fallback_post_ids', $ids, $base, $lang );
}

/** Saves shared fields once for all languages. */
function yaofp_pre_update_value( $check, $value, $post_id, $field ) {
	$base = yaofp_shared_target( $check, $post_id, $field );
	if ( null === $base ) {
		return $check;
	}

	yaofp_busy( true );
	$result = acf_update_value( $value, $base, $field );
	yaofp_busy( false );

	return $result;
}

/** Deletes shared fields from the shared storage. */
function yaofp_pre_delete_value( $check, $post_id, $field_name, $field ) {
	$base = yaofp_shared_target( $check, $post_id, $field );
	if ( null === $base ) {
		return $check;
	}

	yaofp_busy( true );
	$result = acf_delete_value( $base, $field );
	yaofp_busy( false );

	return $result;
}

/** The shared post ID a top-level shared field is saved to, or null to let ACF save as usual. */
function yaofp_shared_target( $check, $post_id, $field ) {
	if ( null !== $check || yaofp_busy() || ! is_array( $field ) || yaofp_is_sub_field( $field ) ) {
		return null;
	}

	$split = yaofp_split_post_id( $post_id );
	if ( ! $split || '' === $split['lang'] || ! yaofp_is_translated_page( $split['base'] ) ) {
		return null;
	}

	return YAOFP_MODE_SHARED === yaofp_field_mode( $field, $split['base'] ) ? $split['base'] : null;
}

/**
 * Finds the field key for get_field( 'name', 'options' ) when this language has never been saved:
 * the reference is looked up in the default language, then in the "All languages" values.
 */
function yaofp_load_reference( $reference, $field_name, $post_id ) {
	if ( ! empty( $reference ) ) {
		return $reference;
	}

	$split = yaofp_split_post_id( $post_id );
	if ( ! $split ) {
		return $reference;
	}

	$candidates = '' === $split['lang'] ? [] : yaofp_fallback_post_ids( $split['base'], $split['lang'] );
	if ( '' === $split['lang'] && yaofp_default_lang() ) {
		$candidates[] = yaofp_post_id( $split['base'], yaofp_default_lang() );
	}

	foreach ( $candidates as $candidate ) {
		$found = acf_get_metadata( $candidate, $field_name, true );
		if ( $found ) {
			return $found;
		}
	}

	return $reference;
}

/**
 * Swaps post, term and media IDs in a fallback value for their translations in $lang, when they exist.
 * Works through repeaters, groups and flexible content.
 */
function yaofp_translate_ids( $value, $field, $lang ) {
	if ( ! apply_filters( 'yaofp/translate_ids', true, $field, $lang ) || yaofp_is_empty_value( $value ) ) {
		return $value;
	}

	$slug = yaofp_lang_slug( $lang );
	if ( ! $slug ) {
		return $value;
	}

	switch ( $field['type'] ) {
		case 'post_object':
		case 'relationship':
		case 'page_link':
		case 'image':
		case 'file':
		case 'gallery':
			return yaofp_map_ids( $value, function ( $id ) use ( $slug ) {
				$translated = function_exists( 'pll_get_post' ) ? pll_get_post( $id, $slug ) : 0;
				return $translated ? $translated : $id;
			} );

		case 'taxonomy':
			return yaofp_map_ids( $value, function ( $id ) use ( $slug ) {
				$translated = function_exists( 'pll_get_term' ) ? pll_get_term( $id, $slug ) : 0;
				return $translated ? $translated : $id;
			} );

		case 'repeater':
			if ( is_array( $value ) && ! empty( $field['sub_fields'] ) ) {
				foreach ( $value as $i => $row ) {
					$value[ $i ] = yaofp_translate_row( $row, $field['sub_fields'], $lang );
				}
			}
			return $value;

		case 'group':
			if ( is_array( $value ) && ! empty( $field['sub_fields'] ) ) {
				$value = yaofp_translate_row( $value, $field['sub_fields'], $lang );
			}
			return $value;

		case 'flexible_content':
			if ( is_array( $value ) && ! empty( $field['layouts'] ) ) {
				$layouts = [];
				foreach ( $field['layouts'] as $layout ) {
					$layouts[ $layout['name'] ] = isset( $layout['sub_fields'] ) ? $layout['sub_fields'] : [];
				}
				foreach ( $value as $i => $row ) {
					$name = isset( $row['acf_fc_layout'] ) ? $row['acf_fc_layout'] : '';
					if ( isset( $layouts[ $name ] ) ) {
						$value[ $i ] = yaofp_translate_row( $row, $layouts[ $name ], $lang );
					}
				}
			}
			return $value;
	}

	return $value;
}

/** Translates the IDs in one row of a repeater / group / layout (values keyed by sub field key). */
function yaofp_translate_row( $row, $sub_fields, $lang ) {
	if ( ! is_array( $row ) ) {
		return $row;
	}

	foreach ( $sub_fields as $sub_field ) {
		if ( isset( $sub_field['key'] ) && array_key_exists( $sub_field['key'], $row ) ) {
			$row[ $sub_field['key'] ] = yaofp_translate_ids( $row[ $sub_field['key'] ], $sub_field, $lang );
		}
	}

	return $row;
}

/** Applies $map to one ID or to a list of IDs (numeric strings included). */
function yaofp_map_ids( $value, $map ) {
	if ( is_numeric( $value ) ) {
		return (string) $map( (int) $value );
	}

	if ( is_array( $value ) ) {
		foreach ( $value as $i => $item ) {
			if ( is_numeric( $item ) ) {
				$value[ $i ] = (string) $map( (int) $item );
			}
		}
	}

	return $value;
}

/** Polylang slug for a language given by locale or slug. */
function yaofp_lang_slug( $lang ) {
	if ( 'slug' === yaofp_lang_attribute() ) {
		return $lang;
	}

	if ( function_exists( 'pll_languages_list' ) ) {
		$locales = pll_languages_list( [ 'hide_empty' => false, 'fields' => 'locale' ] );
		$slugs   = pll_languages_list( [ 'hide_empty' => false, 'fields' => 'slug' ] );
		$index   = array_search( $lang, (array) $locales, true );
		if ( false !== $index && isset( $slugs[ $index ] ) ) {
			return $slugs[ $index ];
		}
	}

	return '';
}
