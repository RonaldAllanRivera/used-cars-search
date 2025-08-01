// pagination.js
// Handles pagination logic and UI

let pageInfo, prevBtn, nextBtn, pagination;

export function setupPagination(container, state, onPageChange) {
    // Create pagination elements if they don't exist
    if (!pagination) {
        pagination = document.createElement('div');
        pagination.id = 'pais-pagination';
        container.appendChild(pagination);
    }
    // Always update the inner HTML to ensure we have the latest state
    pagination.innerHTML = `
        <div class="pais-pagination-inner">
            <button id="pais-prev" class="pais-pagination-btn" disabled aria-label="Previous page" title="Previous page">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
            </button>
            <span id="pais-page-info" class="pais-page-info">Page ${state.currentPage} of ${state.maxPages}</span>
            <button id="pais-next" class="pais-pagination-btn" disabled aria-label="Next page" title="Next page">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                </svg>
            </button>
        </div>
    `;
    // Cache elements
    pageInfo = document.getElementById('pais-page-info');
    prevBtn = document.getElementById('pais-prev');
    nextBtn = document.getElementById('pais-next');
    // Add event listeners if elements exist
    if (prevBtn) {
        prevBtn.onclick = function(e) {
            e.preventDefault();
            if (state.currentPage > 1) {
                onPageChange(state.currentPage - 1);
            }
        };
    }
    if (nextBtn) {
        nextBtn.onclick = function(e) {
            e.preventDefault();
            if (state.currentPage < state.maxPages) {
                onPageChange(state.currentPage + 1);
            }
        };
    }
    // Update button states
    updatePagination(state);
}

export function updatePagination(state) {
    if (!pageInfo || !prevBtn || !nextBtn) return;
    // Update page info
    if (pageInfo) {
        pageInfo.textContent = `Page ${state.currentPage} of ${state.maxPages}`;
    }
    // Update button states
    if (prevBtn) {
        prevBtn.disabled = state.currentPage <= 1;
    }
    if (nextBtn) {
        nextBtn.disabled = state.currentPage >= state.maxPages;
    }
    // Show/hide pagination based on number of pages
    if (pagination) {
        pagination.style.display = state.maxPages > 1 ? 'block' : 'none';
    }
}
