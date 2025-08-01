// ui.js (refactored)
// Imports and re-exports only
import { renderResults } from './render.js';
import { setupPagination, updatePagination } from './pagination.js';
import { setupViewToggle, isMobile, updateViewForMobile } from './view.js';

export { renderResults, setupPagination, updatePagination, setupViewToggle, isMobile, updateViewForMobile };
