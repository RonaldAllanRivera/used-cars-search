# Changelog

All notable changes to this project will be documented in this file.

## [1.6.9] - 2025-09-05

### Added
- `uninstall.php` to clean up options (`ucs_options`, `ucs_ai_options`, `ucs_stopwords`), clear AI queue transients, unschedule the cron worker, and drop custom tables (`{prefix}ucs_ai_queue`, `{prefix}ucs_ratings`).

### Improved
- Sorting icons now scale with header text using em units. Theme/plugin-injected sorting graphics are neutralized via scoped CSS to ensure a clean, compact look.
- Results table headers polished to prevent collisions between longer labels (e.g., TRANSMISSION and CATEGORIES):
  - Explicit em-based column widths for these headers
  - Slightly reduced header font size on those columns
  - Wrapping enabled with safe word breaks

### Changed
- Desktop list view column layout refined for readability and width efficiency:
  - Abbreviated labels: TRANSMISSION → TRANS.
  - Removed SUMMARY column for a tighter table footprint
  - Reordered columns to prioritize key info: Title, Price, Mileage, Engine, Trans., Categories, Date, Rating, Comments, Actions
- Actions column simplified to only show the Compare button.

### Docs
- README updated with a new "UI polish (v1.6.9)" section summarizing the changes.
- WordPress.org `readme.txt` added. Docs now include Internationalization (i18n) guidance and WP-CLI command for generating a real POT file.

## [1.6.8] - 2025-09-04

### Added
- Compare page now displays all key car detail fields for each compared vehicle: Year, Make, Model, Trim, Price, Mileage, Engine, Transmission.

### Changed
- Localized the "miles" label on the compare page using the plugin text domain; price and mileage continue to use localized number formatting.

### Docs
- README updated with a new feature bullet and a "Compare Page Details (v1.6.8)" section describing the output and localization.

## [1.6.7] - 2025-09-01

### Changed
- AI-applied posts are now scheduled 24 hours ahead when applying generated content to posts in `draft`, `pending`, or `auto-draft` status.
- Centralized scheduling in `ucs_ai_apply_changes_core()` so Manual Apply, Bulk Generate & Apply, and the background queue all behave consistently.

### Docs
- README updated to document the auto-scheduling behavior and affected workflows.

## [1.6.6] - 2025-08-27

### Added
- AI Settings: optional "Enable Dynamic Sub-headline" toggle and "Homepage URL" to output a first-line H2 anchor link in generated content.
- Core generator: strict JSON output contract for downstream usage (`title`, `content` [HTML-only], `seo_title`, `seo_description`, `seo_keywords`).
- Long-form, conversion-focused sub-headline generation (when enabled) with dynamic copy per post.
 - Settings UI + Sanitizer: New checkbox and URL field added in `includes/admin-ai.php` with proper sanitization and persistence.

### Changed
- Unified manual Generate, Bulk Generate & Apply, and background queue to use `ucs_ai_generate_for_post_core()` for consistent output.
- `ucs_ai_generate_for_post_core()` prompt refactored to enforce a strict HTML-only long-form layout (no Markdown):
  - Optional first line: `<h2><a href="{homepage_url}">{Dynamic Sub-headline}</a></h2>` when enabled
  - Intro paragraph
  - H2 sections: Vehicle Overview, Why It Stands Out, Who It Is For, Performance & Efficiency, Ownership & Reliability
  - H3 "Frequently Asked Questions" with a `<ul>` of 4–5 Q&A items
  - H3 "Summary" with a closing paragraph
  - Ends with 6–12 hashtag lines (each starts with `#`), no labels or counts
- Increased AI `max_tokens` upper cap to 16000 (default remains 1200; configurable in AI Settings).
 - Manual single-post AI Assist now delegates fully to the core generator; Bulk Generate & Apply and the background queue worker also call the same core.
 - Prompt guardrails tightened: HTML-only output, no emojis or boilerplate phrases, sub-headline 6–12 words in Title Case and not a verbatim repeat of the title.

### Docs
- README updated with “HTML-only structured output (v1.6.6)”, dynamic sub-headline settings, unified generation paths, and token caps.
 - Admin "How to Use" reflects updated behavior and settings where applicable.

### Security
- All AI and settings endpoints remain capability + nonce protected; new settings fields are sanitized (`esc_url_raw`, `sanitize_text_field`) before save.

### Known Issues
- Some environments may still see the background AI queue step linger as "Step is still running." Investigation is ongoing (queue locking, transient locks, overlapping cron). Additional logging and safeguards are planned.

## [1.6.5] - 2025-08-19

### Improved
- Admin Make dropdown now auto-includes distinct `ucs_make` values found in existing posts and remains alphabetically sorted. Ensures selections are never empty after CSV imports with new makes.

### Fixed
- Replaced deprecated `get_page_by_title()` in CSV importer with a direct `$wpdb` exact-title lookup to avoid WP 6.2+ deprecation notices.

## [1.6.4] - 2025-08-19

### Added
- CSV Import (Admin): Upload a CSV to create/update posts with car details. Title = `Year Make Model`. Assigns `Make` as Category (creates if missing). Imports `Year, Make, Model, Trim, Mileage, Engine, Transmission, Price` into post meta.

## [1.6.3] - 2025-08-14

### Changed
- Admin queue indicator repositioned to top‑center to avoid overlap with "Screen Options" and "Help" buttons on posts list.
- Indicator now accounts for the `#screen-meta` panel height and repositions on toggle.

## [1.6.2] - 2025-08-14

### Added
- Admin indicator controls: Pause/Resume, Stop (soft stop), and Cancel queued.
- Queue status now returns `paused`, `stopping`, and `canceled` counts.

### Changed
- Worker respects paused state and returns early when paused.
- Worker honors Stop both at start (no-op for that tick) and mid-batch (finishes current item then stops).

### Docs
- README updated to document controls and behavior notes (pause, soft stop, cancel).

## [1.6.1] - 2025-08-14

### Added
- Admin queue indicator displayed at the top-right of WP Admin showing Processing/Waiting with counts; updates every 20 seconds.
- New admin AJAX endpoint `ucs_ai_queue_status` (nonce + capability protected) returning lock state and queue counts.
- Filter `ucs_ai_queue_indicator_enabled` to disable the indicator globally.

### Docs
- README updated with an "Admin status indicator" subsection under the WP‑Cron background queue.

## [1.6.0] - 2025-08-12

### Added
- WP‑Cron background AI queue for unattended generation (custom DB table, minutely worker, concurrency lock, batch processing, bulk enqueue action).
- In‑admin “Background queue (WP‑Cron)” how‑to with Laragon/local, SiteGround, and cPanel instructions.

### Docs
- README overview revamped to emphasize production‑grade architecture and the background AI queue.
- New README section: Unattended Background Queue (WP‑Cron), including shared hosting guides (SiteGround/cPanel).
- AI Settings page now includes background queue usage steps and hosting guidance.

## [1.5.4] - 2025-08-12

### Added
- OpenAI API integration for AI-powered content generation
- Admin settings UI for API key storage and model selection
- Single-post AI Assist meta box for generating and applying AI content
- Bulk AI Assist action for processing multiple posts at once
- Smart prompt system that uses car details to generate relevant content

### Fixed
- Single-post "Apply to Post" button now properly refreshes the page after applying AI-generated content

### Docs
- README updated with comprehensive AI Content Generation documentation
- Roadmap updated to mark OpenAI integration as completed

## [1.5.3] - 2025-08-11

### Added
- In‑admin REST API quick reference on the `Used Cars Search` admin page: dynamic endpoints via `rest_url()`, supported meta keys, Postman steps, and pretty‑printed Create/Update JSON payloads.

### Docs
- README updated with an "In‑Admin Quick Reference" section pointing to the dashboard docs.

## [1.5.2] - 2025-08-11

### Added
- Register post meta for car details (`ucs_year`, `ucs_make`, `ucs_model`, `ucs_trim`, `ucs_price`, `ucs_mileage`, `ucs_engine`, `ucs_transmission`) and SEO fields (`_ucs_seo_title`, `_ucs_seo_description`, `_ucs_seo_keywords`, `_ucs_seo_noindex`) with `show_in_rest` for use via `/wp-json/wp/v2/posts`.
- New docs in README: Postman steps, JSON payload examples, and Make.com setup notes.

### Fixed
- Prevent PHP 8 `ArgumentCountError` in meta sanitization by using wrapper sanitizer callbacks that accept 4 parameters (compatible with WP meta filters).
- Resolve 403 Forbidden when updating protected (underscore) meta via REST by adding an `auth_callback` that checks user capability to edit the post.

## [1.5.1] - 2025-08-11

### Fixed
- Resolved issue where SEO head tags output contained literal "\n" sequences; tags now render with proper newlines and clean markup.

## [1.5.0] - 2025-08-11

### Added
- New SEO module (`includes/seo.php`) with meta box fields: SEO Title, SEO Description, SEO Keywords, and No-index toggle.
- Frontend output: optional `<title>` override, `<meta name="description">`, `<meta name="keywords">`, and robots `noindex,follow` when enabled.
- Conflict detection with Yoast SEO and Rank Math; disables this plugin's SEO output by default when detected.
- Filters:
  - `ucs_seo_enable_output` to enable/disable SEO tag output.
  - `ucs_seo_post_types` to add the SEO meta box to additional post types.

### Changed
- Documentation updated (README) with usage examples and best practices for the SEO module.

## [1.4.2] - 2025-08-06

### Improved
- Grid view now displays all car details (Year, Make, Model, Trim, Price, Mileage, Engine, Transmission) in a labeled vertical list.
- Car details block is hidden if all fields are empty.
- Improved formatting for price and mileage; conditional display of details block.

## [1.4.1] - 2025-08-06

### Added
- **Admin Dashboard Restored:** The admin dashboard/settings page has been restored and fully rebranded from the original plugin.
- **Statistics Widget:** The dashboard now displays published posts, total star ratings, average star rating, and approved comments.
- **Improved Admin UX:** Settings, compare page selection, and "Danger Zone" tools are all available in a unified dashboard interface.

### Changed
- The admin dashboard now mirrors the original plugin's admin interface, but is fully rebranded for Used Cars Search.

## [1.4.0] - 2025-08-01

### Added
- **Admin Dropdown Fields:** Year, Make, and Transmission fields in the car details form are now dropdowns with comprehensive, standardized options for faster, more consistent data entry.

### Improved
- Data entry for car details is now more reliable and less error-prone due to standardized options.

## [1.3.0] - 2025-07-15

### Added
- **Used Cars Comparison Feature**: Users can now select up to four Used Cars Search items from the search results and compare them side-by-side on a dedicated page.
- **Configurable Compare Page**: A new setting in the admin panel allows administrators to select any existing WordPress page to serve as the compare page.
- **Floating Compare Bar**: A responsive, floating bar appears at the bottom of the screen when items are selected, showing the current selections and providing a "Compare" button.

### Changed
- The compare page displays items in a responsive two-column grid for easy viewing on all devices.
- The compare bar is now fully responsive and stacks vertically on smaller screens to ensure all controls are accessible.

### Fixed
- Resolved JavaScript errors that prevented the compare page from loading correctly.
- Fixed PHP `Deprecated` warnings related to passing `null` values to the `round()` function.


## [1.1.0] - 2025-07-15

### Added
- **Compare Feature**: Users can now select up to four Used Cars Search items to compare on a dedicated page.
- A floating compare bar for easy access to selected items and the comparison page.
- New shortcode `[ucs_compare_page]` to render the comparison table.
- Responsive styles for the new comparison table.

### Changed
- Updated the admin dashboard and dashboard widget to include information about the new `[ucs_compare_page]` shortcode.

## [1.0.0] - Initial Release

- Live AJAX search with grid and list views.
- Star rating system.
- Admin management panel for ratings.

All notable changes to this project will be documented here.

---



## [1.2.0] - 2025-07-15

### Added
- **Results Per Page**: Search results now display 12 items per page.
- **Alphabetical Category Sorting**: The category dropdown is now sorted alphabetically by name for easier navigation.

### Fixed
- Resolved JavaScript syntax errors in `search.js` by removing legacy code.
- Restored the backend search logic to ensure accurate whole-word keyword filtering across all posts.

### Improved
- **Performance Boost**: Implemented database-level whole-word search for significantly faster results with large post collections
- **Efficiency**: Replaced memory-intensive PHP filtering with optimized SQL queries for better scalability


---

## [0.1.8] - 2025-07-10

### Added
- **Client-side Sorting**: All table sorting is now instant and handled in the browser for a faster experience.
- **Responsive Table Improvements**: Results table is now fully responsive and left-aligned on all devices.

### Changed
- **No More Unwanted Redirects**: Visiting the homepage no longer adds sort parameters to the URL or triggers auto-scroll.
- **Improved Mobile/Table Alignment**: Table and card layouts are visually consistent and mobile-friendly.

### Fixed
- Fixed auto-scroll to results on page load—now only scrolls on user actions.
- Fixed table alignment and responsiveness on all devices.
- Fixed bugs related to sort state, URL sync, and re-rendering.

---

## [0.1.7] - 2025-07-08

### Added
- **Category Post Counts**: Added post counts next to category names in the dropdown
- **New REST Endpoint**: Added `/popularai/v1/categories` to fetch categories with post counts
- **Improved Pagination**: Enhanced pagination controls with better state management

### Changed
- **UI Improvements**: Updated category dropdown to show post counts
- **Performance**: Optimized category loading with a dedicated endpoint
- **Code Quality**: Refactored JavaScript for better maintainability

### Fixed
- Fixed pagination counter display issues
- Resolved duplicate category loading in the dropdown
- Fixed mobile view toggle behavior

---

## [0.1.6] - 2025-07-08

### Added
- **Responsive Design**: New mobile-friendly card layout for screens ≤800px, with table layout for larger screens in list view.
- **Improved Mobile Experience**: Enhanced readability and touch targets for mobile users with optimized card layout.

### Changed
- Updated mobile breakpoint from 768px to 800px for better compatibility with modern devices.
- Improved table responsiveness with proper column stacking on mobile.

### Technical
- Added CSS media queries for responsive design.
- Removed inline styles in favor of CSS classes for better maintainability.

## [0.1.5] - 2025-07-02

### Added
- Ratings Management Panel (admin): New submenu page listing all posts with title, date, average rating, votes, and comments.
  - AJAX-powered search, pagination, and column sorting—scalable to 30,000+ posts.
  - Sortable columns with color-coded, pointer cursor headers for clear UX.
- Danger Zone admin tools: "Reset All Ratings" and "Delete All Comments" buttons, with confirmation, for safely clearing all test data.
- Plugin styles now correctly loaded in admin via `admin_enqueue_scripts`.

### Improved
- No dependency on jQuery or frameworks, all admin tools remain fast even on large datasets.


## [0.1.4] - 2025-07-02

### Added
- Dashboard Summary Widget: Now visible on both the WordPress Dashboard and the plugin’s admin page.
  - Shows total published posts, total star ratings, average rating, and total approved comments.
  - Includes a usage tip for displaying the search UI with the `[used_cars_search]` shortcode.
- Refactored admin code into a dedicated `admin.php` file for better maintainability.

## [0.1.3] - 2025-07-01

### Added
- Prominent, centered star rating widget on all single post pages with bold header, mouseover highlight, and instant voting.
- Ratings stored in custom table, fetched and rendered via AJAX for all posts.
- Results table now displays average star ratings in yellow, matching frontend design.
- Categories, Comments, Rating, Date, and Link columns are now single-line, never wrapping for a compact look.

### Improved
- All REST API calls now use a robust dynamic base path for full compatibility with subdirectory, multisite, or localhost installs.
- Visual polish for ratings table and single post UX.

### Technical
- Uses `ucs_vars.rest_url` (localized via PHP) in all frontend JS for reliable API routing.

## [0.1.2] - 2025-06-25

### Fixed
- Pagination now works correctly when using whole-word keyword search filtering in PHP.
- REST API returns the real number of results and total pages after filtering.
- Pagination UI is now always accurate for all keyword and category search cases.

### Technical
- Refactored search endpoint to filter matching posts in PHP, then paginate the filtered results before returning to the frontend.


## [0.1.1] - 2025-06-24

### Fixed
- Removed all unsupported SQL REGEXP logic from custom REST search endpoint.
- Switched to native WordPress `'s'` argument for keyword search for full MySQL/MariaDB compatibility.
- Confirmed that keyword search now works on both local and production servers.

### Known Issues
- Pagination does not work correctly in search results (to be fixed in the next session).

## [0.1.0] - 2025-06-23

### Added
- Initial plugin scaffold, shortcode UI, and asset loading (PHP/JS/CSS)
- Custom REST API endpoint: /popularai/v1/search for live post search
- Custom REST API endpoint: /popularai/v1/autosuggest for keyword suggestions (stopwords filtered)
- Vanilla JS (ES6+) + Fetch for all AJAX UI (no jQuery or frameworks)
- Real-time autosuggest for keywords, driven by REST API
- Dynamic category dropdown, populated via WP REST /wp/v2/categories
- Responsive results rendering (title, excerpt, permalink, category, date)
- "No results found" message for empty queries
- Fully dynamic, no page reloads; compatible with all themes/builders

### Technical
- JS dynamically picks up correct REST API base URL for subfolders/multisite
- All assets only load on shortcode pages
- Security: output and REST inputs fully sanitized
