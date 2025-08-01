# Compare Feature Plan for Popular AI Software Search Plugin

## Objective
Add a "compare" feature to the plugin, allowing users to select multiple AI software entries from the list view and compare them on a dedicated page. The design must use the current CSS and be implemented with vanilla JS and PHP only. Each new file should be under 200 lines.

## Steps

If possible, do not edit any working scripts now, create a new files.

1. **Analyze Existing List View**
   - Locate where the list/table of results is rendered (found in `assets/js/search.js` and `assets/js/render.js`).
   - Identify where to inject the "Add to Compare" button in the action column.

2. **Add Compare Button**
   - Add a button (e.g., "Add to Compare") to each row's action column in the grid view.
   - Use existing button CSS classes for style consistency.
   - Use vanilla JS to manage a compare list (stored in `localStorage` or `sessionStorage`).

3. **Compare Page**
   - Create a new PHP file (e.g., `compare-page.php`) to render the compare page.
   - Register a new shortcode or endpoint for the compare page.
   - On this page, use JS to fetch compare data from storage and render a responsive grid.
   - Use existing grid CSS classes for style consistency.
   - Use vanilla JS to manage a compare list (stored in `localStorage` or `sessionStorage`).
   - Add a "Remove from Compare" button to each row's action column in the grid view.
   - Add a "Clear Compare" button to the compare page.
   - Add a "Compare" button to the compare page that opens a new tab with the compare page.
   - Add a "Compare" button to the compare page that opens a new tab with the compare page.
   - Compare page should have similar layout with the search grid view

4. **JS Logic**
   - Add logic to handle add/remove compare actions and update the compare count.
   - On the compare page, load and display the selected items in a grid layout, using current CSS.
   - Only allow 4 items to be compared at a time.
   - If more than 4 items are added to the compare list, show a message to the user.
   - If less than 4 items are added to the compare list, show a message to the user.
   - 4 becuase you are following the 4 column layout of the search grid view

5. **Testing & Docs**
   - Ensure everything works on desktop and mobile.
   - Update documentation and changelog.
   - update the admin interface to include the compare page information with the shortcodes.
   - Update the plugin's documentation with the new compare feature.
   - Update the readme.md file with the new compare feature.
   - Update the changelog.md file with the new compare feature.

## Files to be Created/Modified
- `assets/js/compare.js` (new, <200 lines): Handles compare logic and page rendering.
- `compare-page.php` (new, <200 lines): Displays compare grid via shortcode.
- Minor edits to `render.js` or `search.js` to add the button.

## Constraints
- Use current CSS classes for all new UI elements.
- JS and PHP files must be under 200 lines each.
- No frameworks; vanilla JS and PHP only.

---

*This plan will be updated as the feature is implemented.*
