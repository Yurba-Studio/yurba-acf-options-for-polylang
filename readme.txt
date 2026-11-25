=== Yurba ACF Options for Polylang ===
Contributors: yurbastudio
Tags: acf, polylang, options page, multilingual, translation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Per-language ACF options pages for Polylang, with a per-field choice of what is translated, what falls back and what is shared.

== Description ==

Gives every Polylang language its own values on ACF options pages, and lets you choose per field what is translated, what falls back to the default language and what is the same in every language.

A drop-in replacement for ACF Options for Polylang by Be API: it reads and writes the same data, so switching needs no migration.

Requires Advanced Custom Fields 6.0+ (Pro for options pages) and Polylang (free or Pro).

= Choose how every field behaves =

Pick the field's **Translations** setting in the ACF field group editor:

* **Translate** (default, also *Copy once* / *Translate once*) - own value in every language; while a language is empty, the site shows the default language's value.
* **Ignore** - own value in every language, no fallback.
* **Synchronize** - one value for all languages.

It's the same setting Polylang Pro adds to every ACF field. On free Polylang the plugin adds it. Fields defined in code use `'translations' => 'sync'` and so on. Sub fields follow their top-level field.

= What editors see =

Which language is being edited, above the Update button, and a badge on every field: **All languages**, **This language only** or **Empty = (default language)**.

= Fallback =

An empty translated field falls back to the default language, then to the "All languages" values. Post, term and media IDs in the fallback value are swapped for their translations. The fallback runs on the front end, in AJAX and REST; the admin shows each language's real values.

= For developers =

* `yaofp_get_field_in( $selector, $lang )` - value in a given language.
* `yaofp_switch_to_untranslated()` / `yaofp_restore_current_lang()` - read the "All languages" values.
* `yaofp_copy_to_shared( $field )` / `yaofp_copy_group_to_shared( $group_key )` - after switching fields to Synchronize, copy the default language's values into the shared storage.
* `'yaofp' => false` in `acf_add_options_page()` - keep a page the same in every language.
* `'yaofp_mode' => 'shared'` in `acf_add_options_page()` - default mode for that page.
* Filters: `yaofp/field_mode`, `yaofp/default_mode`, `yaofp/option_pages`, `yaofp/fallback_enabled`, `yaofp/fallback_post_ids`, `yaofp/translate_ids`, `yaofp/lang_attribute`.

== Frequently Asked Questions ==

= How do I switch from ACF Options for Polylang? =

Deactivate it and activate this plugin. The data stays where it is. The two plugins don't run together.

= What changes compared to ACF Options for Polylang? =

Repeaters use their own rows in every language (it always showed the "All languages" rows). Empty values fall back to the default language first. Options pages are matched exactly by post ID, and every language is recognised in a post ID.

== Changelog ==

= 1.0.1 =
* `yaofp_copy_to_shared()` and `yaofp_copy_group_to_shared()`: copy the default language's values into the shared storage after switching fields to Synchronize.

= 1.0.0 =
* First release, compatible with the data of ACF Options for Polylang 2.0.0 by Be API.
* Per-field Translations setting: Translate, Ignore, Synchronize.
* Fallback to the default language, then to "All languages", with post/term/media IDs translated.
* Repeaters show their own rows in every language.
* Options pages are matched exactly; every language is recognised in a post ID.
* Language badges on fields and a language notice on options pages.
