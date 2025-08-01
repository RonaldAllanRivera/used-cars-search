// view.js
// Handles view toggle and responsive helpers

export function isMobile() {
    return window.innerWidth <= 800;
}

export function updateViewForMobile(state, gridBtn, listBtn) {
    const isMobileView = isMobile();
    if (isMobileView) {
        state.selectedView = 'grid';
        if (gridBtn) gridBtn.classList.add('active');
        if (listBtn) listBtn.classList.remove('active');
    }
    return isMobileView;
}

export function setupViewToggle(root, state, onViewChange) {
    console.log('Setting up view toggle');
    
    // Create container for view toggle if it doesn't exist
    let viewToggle = document.querySelector('.pais-view-toggle');
    
    if (!viewToggle) {
        viewToggle = document.createElement('div');
        viewToggle.className = 'pais-view-toggle';
        viewToggle.style.display = 'flex';
        viewToggle.style.gap = '0.5rem';
        viewToggle.style.margin = '0.5rem 0';
        
        // Try to find the search form or results div to insert after
        const searchForm = document.getElementById('pais-search-form');
        const resultsDiv = document.getElementById('pais-results');
        
        // Insert the toggle after the search form if it exists, otherwise before the results
        if (searchForm) {
            searchForm.parentNode.insertBefore(viewToggle, searchForm.nextSibling);
        } else if (resultsDiv && resultsDiv.previousSibling) {
            resultsDiv.parentNode.insertBefore(viewToggle, resultsDiv);
        } else if (resultsDiv) {
            // If resultsDiv exists but has no previous sibling, insert at the beginning of parent
            resultsDiv.parentNode.insertBefore(viewToggle, resultsDiv.parentNode.firstChild);
        } else {
            // Fallback: append to root
            root.appendChild(viewToggle);
        }
    }
    
    // Set the initial view based on mobile state
    const initialIsMobile = updateViewForMobile(state);
    
    // Set the inner HTML for the view toggle buttons
    viewToggle.innerHTML = `
        <button id="pais-view-grid" class="pais-view-btn ${state.selectedView === 'grid' ? 'active' : ''}" aria-label="Grid view" title="Grid view">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M4 4h4v4H4V4zm6 0h4v4h-4V4zm6 0h4v4h-4V4zM4 10h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4zM4 16h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4z"/>
            </svg>
        </button>
        <button id="pais-view-list" class="pais-view-btn ${state.selectedView === 'list' ? 'active' : ''}" aria-label="List view" title="List view">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
            </svg>
        </button>
    `;
    
    const gridBtn = viewToggle.querySelector('#pais-view-grid');
    const listBtn = viewToggle.querySelector('#pais-view-list');
    
    // Only add event listeners if not on mobile
    if (!initialIsMobile) {
        const updateView = (view) => {
            if (isMobile()) return; // Ignore clicks on mobile
            
            state.selectedView = view;
            if (view === 'grid') {
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
            } else {
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
            }
            onViewChange(view);
        };
        
        gridBtn.addEventListener('click', () => updateView('grid'));
        listBtn.addEventListener('click', () => updateView('list'));
    }
    
    // Set up responsive behavior
    window.addEventListener('resize', function() {
        clearTimeout(window.paisResizeTimeout);
        window.paisResizeTimeout = setTimeout(() => {
            const wasMobile = isMobile();
            const nowMobile = updateViewForMobile(state, gridBtn, listBtn);
            
            // Only trigger re-render if mobile state changed
            if (wasMobile !== nowMobile) {
                onViewChange(state.selectedView);
            }
        }, 100);
    });
}

// Responsive Resize Handler
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
        if (window.paisSearchState && window.paisSearchState.lastData) {
            const gridBtn = document.getElementById('pais-view-grid');
            const listBtn = document.getElementById('pais-view-list');
            updateViewForMobile(window.paisSearchState, gridBtn, listBtn);
            if (window.paisSearchResultsDiv && window.renderResults) {
                window.renderResults(window.paisSearchResultsDiv, window.paisSearchState.lastData, window.paisSearchState);
            }
        }
    }, 150);
});
