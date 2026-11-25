# Yurba ACF Options for Polylang

Per-language ACF options pages for Polylang, with a per-field choice of what is translated, what falls back to the default language and what is the same in every language.

A drop-in replacement for [ACF Options for Polylang](https://github.com/BeAPI/acf-options-for-polylang) by Be API: it reads and writes the same data, so switching needs no migration. Thanks to Be API for the original plugin and its storage format.

Requires [Advanced Custom Fields](https://www.advancedcustomfields.com/) 6.0+ (Pro for options pages) and [Polylang](https://polylang.pro/) (free or Pro).

## Choose how every field behaves

Open a field in the ACF field group editor and pick its **Translations** setting:

| Setting | On options pages |
| --- | --- |
| **Translate** / *Copy once* / *Translate once* (default) | Own value in every language. While a language is empty, the site shows the default language's value. |
| **Ignore** | Own value in every language, no fallback. Empty stays empty. |
| **Synchronize** | One value for all languages. Saving it in any language changes it everywhere. |

It's the same **Translations** setting Polylang Pro already adds to every ACF field, so on Polylang Pro there's nothing new to learn. On free Polylang the plugin adds the setting itself.

Fields defined in code use the same key:

```php
[
	'key'          => 'field_logo',
	'name'         => 'logo',
	'type'         => 'image',
	'translations' => 'sync', // same logo in every language
]
```

Sub fields of a repeater, group or flexible content follow their top-level field, so a list is never put together from two languages.

## What editors see

- Above the **Update** button: which language's values are being edited, or *all languages*.
- Next to every field label: **All languages** for synchronized fields, **This language only** for ignored ones, and **Empty = French** (the default language) for translated ones.

Switch the language with Polylang's switcher in the admin bar. *All languages* edits the values used where a language has nothing of its own.

## Fallback

A translated field that is empty in the current language falls back, in order, to:

1. the default language;
2. the *All languages* values.

Post, page, term and media IDs in a fallback value (post object, relationship, page link, taxonomy, image, file, gallery - also inside repeaters, groups and flexible content) are swapped for their translations when they exist.

The fallback runs on the front end, in AJAX and in REST. In the admin every language shows its real values, so it's clear what still needs translating.

## A whole page in one language

Keep an options page the same in every language:

```php
acf_add_options_page( [
	'page_title' => 'Integrations',
	'menu_slug'  => 'integrations',
	'yaofp'      => false, // not translated
] );
```

Or change the default for the fields of one page that have no **Translations** setting:

```php
acf_add_options_page( [
	'page_title' => 'Footer',
	'menu_slug'  => 'footer',
	'yaofp_mode' => 'shared', // translate | separate | shared
] );
```

## Code

```php
// Value in a specific language, whatever language is current.
$title = yaofp_get_field_in( 'footer_title', 'en_US' );

// Read the "All languages" values.
yaofp_switch_to_untranslated();
$logo = get_field( 'logo', 'options' );
yaofp_restore_current_lang();

// translate | separate | shared
$mode = yaofp_get_field_mode( 'footer_title' );
```

### Switching a field to Synchronize

A synchronized field reads the *All languages* storage. If it already holds an older value, copy the default language's value into it once:

```php
// One field.
yaofp_copy_to_shared( 'logo', 'options' );

// Every synchronized field of a field group, e.g. in a one-time migration.
yaofp_copy_group_to_shared( 'group_theme_settings', 'options' );
```

While the *All languages* storage is empty, a synchronized field shows the default language's value anyway.

`bea_aofp_switch_to_untranslated()` and `bea_aofp_restore_current_lang()` from the original plugin keep working.

### Filters

| Filter | Use |
| --- | --- |
| `yaofp/field_mode` | Change a field's mode: `( $mode, $field, $post_id )`. |
| `yaofp/default_mode` | Mode for fields without a **Translations** setting. `translate` by default. |
| `yaofp/option_pages` | Options pages (post ID => settings) that get per-language values. |
| `yaofp/is_translated_page` | Turn translation off for one options page: `( $translated, $post_id )`. |
| `yaofp/fallback_enabled` | Where the fallback runs: `( $enabled, $post_id, $field )`. |
| `yaofp/fallback_post_ids` | Where an empty value is looked up: `( $post_ids, $post_id, $lang )`. |
| `yaofp/translate_ids` | Turn off ID translation in fallback values. |
| `yaofp/lang_attribute` | Storage suffix: `locale` (en_US, default) or `slug` (en). |

The suffix can also be set with `define( 'YAOFP_LANG_ATTRIBUTE', 'slug' );`. The original plugin's `BEA_ACF_OPTIONS_FOR_POLYLANG_LANG_ATTRIBUTE` constant and `bea.aofp.excluded_post_ids` filter are honoured.

## Switching from the original plugin

1. Install this plugin.
2. Deactivate **ACF Options for Polylang** (this plugin won't run alongside it and says so in the admin).
3. Activate **Yurba ACF Options for Polylang**.

The values stay where they are: `options_{locale}` / `{post_id}_{locale}` for each language and `options` / `{post_id}` for all languages.

### What's different

- **Repeaters** use their own rows in every language. The original always showed the *All languages* rows on the front end.
- **Empty values** fall back to the default language first, then to *All languages*. The original only used *All languages*.
- **Options pages** are matched exactly by their post ID. The original treated any ID containing the word `option` as an options page.
- **Every language** is recognised in a post ID, not only the current one.
- **Fields switched to Synchronize** keep showing the default language's value until the shared value is first saved.

## How it works

- `options` gets the language suffix from ACF itself (through the `current_language` setting); custom post IDs get it from `acf/validate_post_id`. The default language is suffixed too, so the unsuffixed ID is free for *All languages*.
- `acf/pre_load_value` sends synchronized fields to the unsuffixed ID and loads the fallback for empty translated fields.
- `acf/pre_update_value` / `acf/pre_delete_value` save synchronized fields once, to the unsuffixed ID.
- `acf/load_reference` finds the field key in the default language when a language was never saved.
