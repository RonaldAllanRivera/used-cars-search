=== Used Cars Search ===
Contributors: ronaldallanrivera
Tags: search, autosuggest, compare, ratings, ai
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.6.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Fast, lightweight search and comparison plugin for used car inventories with autosuggest and ratings.

== Description ==
Used Cars Search is a fast, conflict‑safe search & compare plugin for used‑car inventories. It includes autosuggest, sortable list/grid results, and a polished compare flow.

Key features:
- Autosuggest for quick keyword discovery
- Grid and list views with client‑side sorting (title, category, date, rating, comments)
- Compare up to 4 items on a dedicated page
- User star ratings with averages and vote counts
- CSV import (admin) for seeding content
- Optional AI content generation and a background queue (WP‑Cron)
- Elementor‑friendly output (shortcodes work inside widgets)

= Shortcodes =
- [used_cars_search]
- [ucs_compare_page]

== Installation ==
1. Upload the `used-cars-search` folder to `/wp-content/plugins/`.
2. Activate “Used Cars Search”.
3. Add `[used_cars_search]` to any page.
4. To enable compare: create a page with `[ucs_compare_page]`, then select this page in `Used Cars Search → Settings`.

== Frequently Asked Questions ==
= How do I set the compare page? =
Create a WordPress page with `[ucs_compare_page]` and select it in settings under `Used Cars Search`.

= Does it depend on jQuery? =
No. It uses modern, dependency‑free ES6.

= Can I use it with Elementor? =
Yes. Place the shortcodes in Elementor text/shortcode widgets.

= How do I import items? =
Use the CSV Import screen in the plugin’s admin area. A sample `cars.csv` is included for reference.

== Screenshots ==
1. Search UI with autosuggest
2. Results table with sorting
3. Grid view cards
4. Compare page

== Internationalization ==
Text Domain: used-cars-search
Domain Path: /languages

Generate or refresh the POT file with WP-CLI (run from this plugin folder):

php .\wp-cli.phar i18n make-pot . .\languages\used-cars-search.pot --path="E:\laragon\www\popular-ai-software-search" --slug=used-cars-search --domain=used-cars-search --exclude=wporg-assets,node_modules,vendor,*.map

Or via a global/local wp command if installed:

wp i18n make-pot . .\languages\used-cars-search.pot --path="E:\laragon\www\popular-ai-software-search" --slug=used-cars-search --domain=used-cars-search --exclude=wporg-assets,node_modules,vendor,*.map

== Uninstall ==
When the plugin is deleted, uninstall.php removes:
- Options: ucs_options, ucs_ai_options, ucs_stopwords
- Transients: ucs_ai_worker_lock, ucs_ai_queue_stop
- Cron: unschedules the ucs_ai_queue_worker event
- Tables: drops {prefix}ucs_ai_queue and {prefix}ucs_ratings (if present)

Post meta and content are preserved by default.

== Changelog ==
= 1.6.10 =
- Ratings Management: Fixed sorting for ID, Title, and Date by normalizing the `sort` parameter and mapping to proper `orderby` values in `WP_Query`. Added a numeric PHP fallback for ID to guarantee correct ordering.
- UI: Set consistent 10px font size for Compare buttons across all views.
- AI Scheduling: Enforced 24-hour delay for AI-applied content on drafts/pending using `_ucs_ai_scheduled_gmt` meta and a `wp_insert_post_data` guard. Posts remain `future` until the scheduled time.
- AI Assist (Single Post): Moved panel to main column, enlarged textareas, improved readability.
- AI Assist (SEO): Added server/client fallbacks so SEO Title/Description/Keywords are populated; fixed JSON handling; mirrored values into the Used Cars SEO meta box; also save to Yoast/Rank Math meta keys.
- Security/Hardening: Whitelisted sort keys and strengthened input sanitization in the `ucs_ratings_list` AJAX handler.

= 1.6.9 =
- Sort icons now scale with header text using em units; theme/plugin arrows neutralized by scoped CSS.
- Prevent header collisions for longer labels with em‑based widths and smaller header font with wrapping (TRANS., CATEGORIES).
- Desktop list layout refined: removed SUMMARY; re‑ordered columns → Title, Price, Mileage, Engine, Trans., Categories, Date, Rating, Comments, Actions.
- Abbreviations: TRANSMISSION → TRANS.
- Simplified Actions to focus on the Compare button.
- Added uninstall.php to clean plugin options, transients, cron, and custom tables.
- Docs: Added Internationalization and Uninstall sections.

= 1.6.8 =
- Compare page shows key car details (Year, Make, Model, Trim, Price, Mileage, Engine, Transmission) with localized labels.

== Upgrade Notice ==
= 1.6.9 =
UI polish for sort icons and header sizing with improved column layout. Recommended for a cleaner, compact results table.
