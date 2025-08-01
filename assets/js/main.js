// main.js
// Entry point: wires up modules and initializes the search UI

console.log('main.js is running!');
console.log('pais_vars:', window.pais_vars);

// Debug: Check if modules can be imported
try {
    import('./autosuggest.js').then(module => console.log('autosuggest.js loaded'));
    import('./api.js').then(module => console.log('api.js loaded'));
    import('./ui.js').then(module => console.log('ui.js loaded'));
} catch (e) {
    console.error('Error loading modules:', e);
}

import { setupAutosuggest } from './autosuggest.js';
import { fetchResults, loadCategories } from './api.js';
import { setupViewToggle, renderResults, setupPagination } from './ui.js';

console.log('All imports successful');

document.addEventListener('DOMContentLoaded', async function() {
    const root = document.getElementById('pais-search-root');
    if (!root) return;

    const searchForm = document.getElementById('pais-search-form');
    const keywordInput = document.getElementById('pais-keyword');
    const categorySelect = document.getElementById('pais-category');
    const searchBtn = document.getElementById('pais-search-btn');
    const resultsDiv = document.getElementById('pais-results');
    let paginationDiv = document.getElementById('pais-pagination');
    const autosuggestDiv = document.getElementById('pais-autosuggest');
    const viewToggle = document.getElementById('pais-view-toggle');
    
    // Create pagination container if it doesn't exist
    if (!paginationDiv) {
        paginationDiv = document.createElement('div');
        paginationDiv.id = 'pais-pagination';
        if (resultsDiv && resultsDiv.parentNode) {
            resultsDiv.parentNode.insertBefore(paginationDiv, resultsDiv.nextSibling);
        }
    }
    
    // Initialize state
    const state = {
        currentPage: 1,
        maxPages: 1,
        lastData: null,
        selectedView: 'grid', // default view
        keyword: '',
        category: ''
    };

    // Setup modules
    setupAutosuggest(keywordInput, autosuggestDiv, fetchAndRender, window.pais_vars.rest_url);
    setupViewToggle(root, state, view => {
        renderResults(resultsDiv, state.lastData, state);
    });

    async function fetchAndRender(page = 1, sortBy = null, sortOrder = null) {
        const keyword = keywordInput ? keywordInput.value.trim() : '';
        const category = categorySelect ? categorySelect.value : '';
        
        // Only update sort parameters if they are explicitly provided
        const effectiveSortBy = sortBy !== null ? sortBy : window.paisLastSortBy || 'date';
        const effectiveSortOrder = sortOrder !== null ? sortOrder : window.paisLastSortOrder || 'desc';
        
        // Update state before making the request
        state.currentPage = parseInt(page);
        state.keyword = keyword;
        state.category = category;
        
        // Update sort parameters in state
        window.paisLastSortBy = effectiveSortBy;
        window.paisLastSortOrder = effectiveSortOrder;
        
        if (resultsDiv) {
            resultsDiv.innerHTML = 'Loading...';
        }
        
        try {
            console.log('Fetching results for page:', page, 'sortBy:', effectiveSortBy, 'sortOrder:', effectiveSortOrder);
            const data = await fetchResults({
                page,
                sortBy: effectiveSortBy,
                sortOrder: effectiveSortOrder,
                keyword,
                category,
                restUrl: window.pais_vars.rest_url
            });
            
            console.log('Fetched data:', data);
            
            if (!data) return;
            
            // Update state with response data
            state.currentPage = parseInt(page);
            state.maxPages = data.max_num_pages || 1;
            state.lastData = data;
            
            // Only update URL with sort parameters if they were explicitly provided
            // or if we're not on the first page or have search terms
            const shouldUpdateURL = (sortBy !== null || sortOrder !== null || 
                                  page > 1 || keyword || category);
            
            if (shouldUpdateURL) {
                updateURL(page, keyword, category, effectiveSortBy, effectiveSortOrder);
            }
            
            // Render results with current sort state
            renderResults(resultsDiv, data, state);
            
            // Setup pagination after rendering results
            if (paginationDiv) {
                setupPagination(paginationDiv, state, fetchAndRender);
            }
            
            // Update sort indicators in the UI
            updateSortIndicators(sortBy, sortOrder);
            
            // Only scroll to results if this was triggered by a user action
            // (search, sort, or pagination) and not on initial page load
            const isInitialLoad = !state.keyword && !state.category && state.currentPage === 1;
            if (resultsDiv && !isInitialLoad) {
                resultsDiv.scrollIntoView({ behavior: 'smooth' });
            }
            
        } catch (error) {
            console.error('Failed to fetch results:', error);
            if (resultsDiv) {
                resultsDiv.innerHTML = `
                    <div class="pais-error">
                        Failed to fetch results. Please try again later.
                        <br><small>${error.message || ''}</small>
                    </div>
                `;
            }
        }
    }

    // Load categories with counts
    function loadCategories() {
        if (!categorySelect) return;
        
        fetch(`${window.pais_vars.rest_url}popularai/v1/categories`)
            .then(res => res.json())
            .then(categories => {
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'All Categories';
                categorySelect.innerHTML = '';
                categorySelect.appendChild(defaultOption);
                
                categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.slug;
                    option.textContent = `${cat.name} (${cat.count})`;
                    categorySelect.appendChild(option);
                });
                
                // If there's a category in the URL, select it
                const urlParams = new URLSearchParams(window.location.search);
                const categoryFromUrl = urlParams.get('category');
                if (categoryFromUrl) {
                    categorySelect.value = categoryFromUrl;
                }
            })
            .catch(error => {
                console.error('Error loading categories:', error);
            });
    }
    
    // Initialize categories
    loadCategories();
    
    // Update URL with search parameters and sort
    function updateURL(page, keyword, category, sortBy = window.paisLastSortBy, sortOrder = window.paisLastSortOrder) {
        if (!history.pushState) return;
        
        const url = new URL(window.location);
        
        // Update or remove page parameter
        if (page > 1) {
            url.searchParams.set('paged', page);
        } else {
            url.searchParams.delete('paged');
        }
        
        // Update or remove search keyword
        if (keyword) {
            url.searchParams.set('s', encodeURIComponent(keyword));
        } else {
            url.searchParams.delete('s');
        }
        
        // Update or remove category
        if (category) {
            url.searchParams.set('category', category);
        } else {
            url.searchParams.delete('category');
        }
        
        // Update or remove sort parameters
        if (sortBy && sortOrder) {
            url.searchParams.set('orderby', sortBy);
            url.searchParams.set('order', sortOrder);
        } else {
            url.searchParams.delete('orderby');
            url.searchParams.delete('order');
        }
        
        // Update the URL without reloading the page
        const newUrl = url.toString();
        window.history.pushState({ path: newUrl }, '', newUrl);
    }
    
    // Update sort indicators in the UI
    function updateSortIndicators(sortBy, sortOrder) {
        // Reset all sort icons
        document.querySelectorAll('.pais-sort-icon').forEach(icon => {
            icon.textContent = '↕';
        });
        
        // Update the active sort indicator
        if (sortBy) {
            const activeTh = document.querySelector(`.pais-sort-th[data-sort="${sortBy}"]`);
            if (activeTh) {
                const icon = activeTh.querySelector('.pais-sort-icon');
                if (icon) {
                    icon.textContent = sortOrder === 'asc' ? '↑' : '↓';
                }
            }
        }
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const page = parseInt(urlParams.get('paged')) || 1;
        const keyword = urlParams.get('s') || '';
        const category = urlParams.get('category') || '';
        const orderby = urlParams.get('orderby') || 'date';
        const order = urlParams.get('order') || 'desc';
        
        // Update input values
        if (keywordInput) keywordInput.value = keyword;
        if (categorySelect) categorySelect.value = category;
        
        // Update sort state
        window.paisLastSortBy = orderby;
        window.paisLastSortOrder = order;
        
        // Update UI state
        state.keyword = keyword;
        state.category = category;
        
        // Update sort indicators
        updateSortIndicators(orderby, order);
        
        // Fetch results with current parameters
        fetchAndRender(page, orderby, order);
    });
    
    // Initial load - check URL for parameters
    function initializeFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const page = parseInt(urlParams.get('paged')) || 1;
        const keyword = urlParams.get('s') || '';
        const category = urlParams.get('category') || '';
        
        // Only use sort parameters if they exist in the URL
        const hasSortInUrl = urlParams.has('orderby') || urlParams.has('order');
        const orderby = hasSortInUrl ? urlParams.get('orderby') : null;
        const order = hasSortInUrl ? urlParams.get('order') : null;
        
        // Set input values
        if (keywordInput) keywordInput.value = keyword;
        if (categorySelect) categorySelect.value = category;
        
        // Initialize sort parameters only if they exist in URL
        if (hasSortInUrl) {
            window.paisLastSortBy = orderby;
            window.paisLastSortOrder = order;
        } else {
            // Default sort values (won't be added to URL unless changed by user)
            window.paisLastSortBy = 'date';
            window.paisLastSortOrder = 'desc';
        }
        
        // Update UI state
        state.keyword = keyword;
        state.category = category;
        
        // Setup globals for responsive handling in ui.js
        window.paisSearchState = state;
        window.paisSearchResultsDiv = resultsDiv;
        
        // Set initial view for mobile
        try {
            const gridBtn = document.getElementById('pais-view-grid');
            const listBtn = document.getElementById('pais-view-list');
            if (window.updateViewForMobile) {
                window.updateViewForMobile(state, gridBtn, listBtn);
            } else if (typeof updateViewForMobile === 'function') {
                updateViewForMobile(state, gridBtn, listBtn);
            }
        } catch (e) { 
            console.warn('updateViewForMobile not available yet', e); 
        }

        // Only fetch results if there's a search term, category, or page > 1
        // This prevents the initial load from triggering a search
        if (keyword || category || page > 1 || hasSortInUrl) {
            fetchAndRender(page, orderby, order);
        } else if (resultsDiv) {
            // Clear any loading message if no search is needed
            resultsDiv.innerHTML = '';
        }
    }

    // Initial load
    initializeFromURL();

    // Handle URL parameters and sort state
    function updateSortFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const sortBy = urlParams.get('orderby') || 'date';
        const sortOrder = urlParams.get('order') || 'desc';
        
        if (sortBy && sortOrder) {
            window.paisLastSortBy = sortBy;
            window.paisLastSortOrder = sortOrder;
            
            // Update sort indicators in the UI
            document.querySelectorAll('.pais-sort-icon').forEach(icon => {
                icon.textContent = '↕';
            });
            
            const activeTh = document.querySelector(`.pais-sort-th[data-sort="${sortBy}"]`);
            if (activeTh) {
                const icon = activeTh.querySelector('.pais-sort-icon');
                if (icon) {
                    icon.textContent = sortOrder === 'asc' ? '↑' : '↓';
                }
            }
            
            return { sortBy, sortOrder };
        }
        return null;
    }
    
    // Listen for sort events from render.js
    document.addEventListener('paisSearch', (e) => {
        console.log('paisSearch event received:', e.detail);
        const { page = 1, sortBy, sortOrder } = e.detail;
        
        // Get current search parameters
        const keyword = keywordInput ? keywordInput.value.trim() : '';
        const category = categorySelect ? categorySelect.value : '';
        
        // Update URL with all current parameters
        updateURL(page, keyword, category, sortBy, sortOrder);
        
        // Update sort state and fetch results
        window.paisLastSortBy = sortBy;
        window.paisLastSortOrder = sortOrder;
        
        // Fetch results with all current parameters
        fetchAndRender(page, sortBy, sortOrder);
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const page = parseInt(urlParams.get('paged')) || 1;
        const keyword = urlParams.get('s') || '';
        const category = urlParams.get('category') || '';
        const sortBy = urlParams.get('orderby') || 'date';
        const sortOrder = urlParams.get('order') || 'desc';
        
        // Update input values
        if (keywordInput) keywordInput.value = keyword;
        if (categorySelect) categorySelect.value = category;
        
        // Update sort state
        window.paisLastSortBy = sortBy;
        window.paisLastSortOrder = sortOrder;
        
        // Update UI state
        state.keyword = keyword;
        state.category = category;
        
        // Update sort indicators
        updateSortIndicators(sortBy, sortOrder);
        
        // Fetch results with all current parameters
        fetchAndRender(page, sortBy, sortOrder);
    });
    
    // Initial sort from URL
    updateSortFromURL();

    // Search button
    searchBtn.addEventListener('click', (e) => {
        e.preventDefault();
        fetchAndRender(1, window.paisLastSortBy, window.paisLastSortOrder);
    });
    
    // Category change
    categorySelect.addEventListener('change', () => {
        fetchAndRender(1, window.paisLastSortBy, window.paisLastSortOrder);
    });
    
    // Enter key for search
    keywordInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            fetchAndRender(1, window.paisLastSortBy, window.paisLastSortOrder);
        }
    });

    // Load categories
    try {
        const cats = await loadCategories(pais_vars.rest_url);
        categorySelect.innerHTML = '<option value="">All</option>' +
            cats.map(cat => `<option value="${cat.slug}">${cat.name}</option>`).join('');
    } catch (e) {
        categorySelect.innerHTML = '<option value="">All</option>';
    }

    // Optionally fetch initial results
    fetchAndRender(1);
});
