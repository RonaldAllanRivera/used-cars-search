# Used Cars Search

A **modern, AJAX-powered WordPress plugin** for advanced post searching.
Built for scale, with fast keyword autosuggest, category filtering, sortable results, and robust admin controls.
**No jQuery, no frameworks—just Vanilla JavaScript (ES6+) and the Fetch API for maximum speed and compatibility.**

---

## 🚀 Features

* All car details (Year, Make, Model, Trim, Price, Mileage, Engine, Transmission) are now displayed in the grid view, each on a separate labeled line.
* Car details block is hidden if all fields are empty.
* Price and mileage are formatted for better readability.

* **Software Comparison** - Users can select up to four items and compare them side-by-side on a dedicated, responsive page.
* **Lightning-Fast Search** - Optimized database queries for instant results, even with thousands of posts
* **Precise Whole-Word Matching** - Accurate search results that match your exact terms
* **Smart category filtering** with post counts and real-time UI updates (no page reload)
* **Grid/List view toggle** with responsive design for all screen sizes
* **Sortable, paginated results** (title, category, star rating, comments, date) — Pagination and total count now always match filtered (keyword/category) search!
* **User star ratings** (1-5, per post, via custom widget)
* **Search/list view displays average star ratings as yellow stars and vote count**
* **Threaded comments** via native WP Comments API
* **Shortcode support**
* **Highly Scalable** - Optimized for 50k+ posts with database-level filtering
* **Zero JS conflicts:** all JS and CSS are namespaced, loaded only when needed

---

## 🛠️ Installation & Usage

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate **Used Cars Search** in WP Admin.
3. Add `[used_cars_search]` to any post or page.
4. To enable the compare feature, create a new page (e.g., named "Compare"), add the `[ucs_compare_page]` shortcode to it, and then select this page from the dropdown in the plugin's settings page (**Popular AI Search -> Settings**).

---

## 🛠️ Enhanced Admin Dashboard (v1.4.1)

* The admin dashboard/settings page has been restored and rebranded from the original plugin.
* Now features a statistics widget showing:
  * Published Posts
  * Total Star Ratings
  * Average Star Rating
  * Approved Comments
* Improved admin UX, with settings, compare page selection, and a "Danger Zone" for quick data resets.
* The dashboard now mirrors the original plugin's admin interface, fully rebranded for Used Cars Search.

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
## 🏗️ Roadmap

* [x] Scaffold plugin structure, enqueue Vanilla JS, and register shortcode
* [x] Implement REST API endpoints (search, autosuggest, rating)
* [x] Build Vanilla JS-powered search and results UI
* [x] Integrate star rating and comments
* [x] Add Software Comparison feature
* [x] Build admin settings page
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
