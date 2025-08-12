# Used Cars Search

A production‑ready WordPress search and comparison plugin purpose‑built for used‑car inventories.
Lightning‑fast autosuggest, sortable grid/list results, comprehensive car details, and comparison.
SEO‑ready with conflict‑safe meta output and a clean, extensible architecture. Built with Vanilla JS (ES6) — no jQuery.

---

## 🚀 Features

* **AI-Powered Content Generation:** OpenAI integration for auto-generating post titles, content, and SEO fields based on car details. Supports both single-post and bulk operations.
* **SEO Meta Module (conflict‑safe):** Per‑post SEO Title, Description, Keywords, and No‑index with automatic conflict detection for Yoast/Rank Math. Filter‑controlled output and post‑type targeting.
* **Car Details, Done Right:** Grid cards show Year, Make, Model, Trim, Price, Mileage, Engine, Transmission — clearly labeled, formatted, and auto‑hidden when all values are empty.
* **Lightning‑Fast Search & Autosuggest:** Whole‑word matching, fast queries, and smart category filtering with instant UI updates.
* **Grid/List Views + Sorting:** Sort by title, category, rating, comments, and date. Pagination and totals always reflect applied filters.
* **Compare up to 4 Cars:** Dedicated, responsive compare page with easy selection and a floating compare bar.
* **User Ratings:** 1–5 star ratings per post with average and vote counts displayed in results.
* **Admin Productivity:** Dropdowns for Year, Make, Transmission ensure consistent data entry.
* **Zero Dependencies:** Pure ES6 + Fetch API. No jQuery or heavy frameworks.
* **Scalable & Clean:** Namespaced assets, selective loading, and REST endpoints designed for large datasets.
* **Extensible:** Carefully designed hooks/filters and modular PHP includes for safe customization.

---

## 🛠️ Installation & Usage

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate **Used Cars Search** in WP Admin.
3. Add `[used_cars_search]` to any post or page.
4. To enable the compare feature, create a new page (e.g., named "Compare"), add the `[ucs_compare_page]` shortcode to it, and then select this page from the dropdown in the plugin's settings page (**Used Cars Search -> Settings**).

---

## 🛠️ Enhanced Admin Dashboard (v1.4.1)

* The admin dashboard/settings page is included as part of this plugin.
* Now features a statistics widget showing:
  * Published Posts
  * Total Star Ratings
  * Average Star Rating
  * Approved Comments
* Improved admin UX, with settings, compare page selection, and a "Danger Zone" for quick data resets.
* The dashboard provides settings, stats, and management tools designed for Used Cars Search.

## 🔍 SEO Meta (v1.5.0)

* __Fields__: SEO Title, SEO Description, SEO Keywords, No-index toggle.
* __Meta Box__: Shown on `post` by default. Extendable via `ucs_seo_post_types` filter.
* **Output behavior**
  * If no popular SEO plugin is detected, this plugin outputs:
    * `<title>` override (when SEO Title is set)
    * `<meta name="description">`
    * `<meta name="keywords">` (legacy/optional)
    * `<meta name="robots" content="noindex,follow">` when No-index is checked
  * If Yoast SEO or Rank Math is active, output is disabled by default to avoid duplicate tags.
* **Enable/disable output**

```php
// Force-enable or disable this plugin's SEO output
add_filter('ucs_seo_enable_output', function($enabled) {
    return true; // or false
});
```

* **Target other post types**

```php
// Show the SEO meta box on additional post types
add_filter('ucs_seo_post_types', function($types) {
    $types[] = 'page';
    $types[] = 'product';
    return $types;
});
```

> Best practice: If you already use a full SEO suite (Yoast, Rank Math), keep this plugin's SEO output disabled (default) and use it only if you need lightweight, per-post overrides.

## 🔌 REST API: Car Details & SEO (v1.5.2)

* __Exposed Meta__: All car details (`ucs_year`, `ucs_make`, `ucs_model`, `ucs_trim`, `ucs_price`, `ucs_mileage`, `ucs_engine`, `ucs_transmission`) and SEO fields (`_ucs_seo_title`, `_ucs_seo_description`, `_ucs_seo_keywords`, `_ucs_seo_noindex`) are registered with `show_in_rest`.
* __Endpoint__: Core WP posts API: `/wp-json/wp/v2/posts`.
* __Auth__: Use an admin/editor account via Application Passwords (recommended for Postman/Make.com) or cookie auth.

### Read (GET)
```
GET /wp-json/wp/v2/posts/123?_fields=id,title.rendered,meta
```

### Create or Update (POST)
Headers: `Content-Type: application/json` and `Authorization: Basic <username:app_password>`

```json
{
  "title": "2018 Honda Civic LX",
  "status": "publish",
  "meta": {
    "ucs_year": 2018,
    "ucs_make": "Honda",
    "ucs_model": "Civic",
    "ucs_trim": "LX",
    "ucs_price": 12995.0,
    "ucs_mileage": 58432,
    "ucs_engine": "2.0L I4",
    "ucs_transmission": "Automatic",
    "_ucs_seo_title": "2018 Honda Civic LX for sale | DealerName",
    "_ucs_seo_description": "Clean title Civic LX, low miles, great condition.",
    "_ucs_seo_keywords": "Honda Civic 2018",
    "_ucs_seo_noindex": false
  }
}
```

### Postman Quick Steps
* __Auth__: Settings → Authorization → Basic Auth. Username = your WP user. Password = Application Password (Users → Profile → Application Passwords).
* __Create__: POST `.../wp-json/wp/v2/posts` with the JSON body above.
* __Update__: POST `.../wp-json/wp/v2/posts/{id}` with `{ "meta": { ... } }`.
* __Verify__: GET `.../wp-json/wp/v2/posts/{id}?_fields=id,meta`.

### Make.com (Integromat) Quick Steps
* Module: HTTP → Make a request.
* Method: POST. URL: `https://your-site.tld/wp-json/wp/v2/posts` (or `/posts/{id}` to update).
* Auth: Basic → Provide username and Application Password.
* Headers: `Content-Type: application/json`.
* Body: Use the JSON payload above; map Make.com variables (e.g., year, make, price) into `meta` fields.

### In‑Admin Quick Reference (v1.5.3)

You can also find a live REST API quick reference directly inside WordPress Admin:

* **Location**: `Used Cars Search` admin page
* **Includes**: Dynamic endpoint URLs via `rest_url()`, supported meta keys, Postman steps, and pretty‑printed Create/Update JSON payloads
* **Why**: Ensures future maintainers can integrate via Postman/Make.com without leaving WP Admin

## 🚗 Car Details Admin Dropdowns (v1.4.0)

* The admin panel now features dropdowns for Year, Make, and Transmission when editing or adding a car post.
* Year dropdown includes 2025–1980; Make dropdown includes all major car brands; Transmission dropdown includes all common types.
* This ensures data consistency and faster entry for admins.

---

## 🔧 Admin Features

* **Settings Page**: Configure the compare page URL.
* **Ratings Management Panel**: View, search, and sort all posts with average rating, votes, and comments.
* **Danger Zone Tools**: One-click "Reset All Ratings" and "Delete All Comments" for fast cleanup during testing.

---
## 💡 Tech Stack & Philosophy

*   **Frontend**: Vanilla JavaScript (ES6+) and the Fetch API for all UI interactivity. No jQuery or frameworks for maximum speed.
*   **Backend**: WordPress REST API with custom endpoints for search, autosuggest, and ratings.
*   **Performance-focused**: Fast queries, selective asset loading, and optimized for large datasets.

---
## 🤖 AI Content Generation (v1.5.4)

* __OpenAI Integration__: Leverages OpenAI's API to generate high-quality, SEO-optimized content based on car details.
* __Admin Settings__: Secure API key storage and model selection (supports multiple OpenAI models).
* __Single-Post AI Assist__: Meta box on post edit screen with:
  * Generate button to create AI suggestions for title, content, and SEO fields
  * Field selection checkboxes to control which fields to update
  * Apply to Post button that updates the post and refreshes the page automatically
* __Bulk AI Assist__: Generate and apply content to multiple posts at once from the posts list screen.
* __Smart Prompts__: Automatically builds prompts using car details (year, make, model, etc.) for contextually relevant content.
* __Security__: Admin-only access, nonce verification, and capability checks throughout.

> Note: Requires your own OpenAI API key. The plugin includes a test connection feature to verify your API key works before enabling AI features.

## 🏗️ Roadmap

* [x] Scaffold plugin structure, enqueue Vanilla JS, and register shortcode
* [x] Implement REST API endpoints (search, autosuggest, rating)
* [x] Build Vanilla JS-powered search and results UI
* [x] Integrate star rating and comments
* [x] Add Software Comparison feature
* [x] Build admin settings page
* [x] Add OpenAI integration for AI-powered content generation
* [ ] Add Elementor widget support
* [ ] Optimize for large datasets (indexing, caching)
* [ ] Polish, docs, and testing

---

## 🧪 Testing

### Test Plan

#### 1. Keyword Search (Speed and Accuracy)
- Search for `crayo` - Should return "Crayon" results quickly
- Search for `art` - Should only match whole words (not "article" or "chart")

#### 2. Category Filtering
- Select a category (e.g., "Advertising") - Should show all posts in that category
- Search for a term within a selected category - Should only show matching posts from that category

#### 3. Sorting Options
- Test all sort orders with various searches:
  - Newest (default)
  - Title A-Z
  - Title Z-A
  - Most Comments

#### 4. Pagination
- Navigate through multiple pages of results
- Verify page numbers and navigation controls update correctly

#### 5. Autosuggest
- Type slowly in the search box - Should show relevant keyword suggestions
- Clicking a suggestion should perform the search

#### 6. Compare Feature
- Click "Add to Compare" on a few search results. The compare bar should appear at the bottom.
- The selected items should appear in the compare bar.
- Click the "x" on an item in the bar to remove it.
- Click the "Compare (N)" button. It should redirect to the configured compare page.
- The compare page should display the selected items in a two-column grid.
- Test responsiveness: on a small screen, the compare bar should stack vertically, and the compare page grid should become a single column.

#### 7. Edge Cases
- Search for special characters
- Test with very long search terms
- Try searching with no keywords (should return all posts)

---

## 🤝 Credits

Developed by Ronald Allan Rivera
Portfolio: [allanwebdesign.com](https://allanwebdesign.com)
