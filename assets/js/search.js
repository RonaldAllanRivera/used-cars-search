document.addEventListener('DOMContentLoaded', function() {
    // Get all relevant elements by ID
    const root = document.getElementById('pais-search-root');
    if (!root) {
        console.error('Root element not found');
        return;
    }

    // Initialize sort parameters
    window.paisLastSortBy = window.paisLastSortBy || 'date';
    window.paisLastSortOrder = window.paisLastSortOrder || 'desc';

    const keywordInput = document.getElementById('pais-keyword');
    const categorySelect = document.getElementById('pais-category');
    const searchBtn = document.getElementById('pais-search-btn');
    const resultsDiv = document.getElementById('pais-results');
    const autosuggestDiv = document.getElementById('pais-autosuggest');

    // Initialize variables
    const state = {
        currentPage: 1,
        maxPages: 1,
        selectedView: 'grid',
        lastData: null
    };

    // --- AUTOSUGGEST ---
    let autosuggestTimeout = null;
    keywordInput.addEventListener('input', function() {
        clearTimeout(autosuggestTimeout);
        const value = keywordInput.value.trim();
        autosuggestDiv.innerHTML = '';
        if (value.length < 2) return;
        autosuggestTimeout = setTimeout(() => {
            fetch(`${pais_vars.rest_url}popularai/v1/autosuggest?q=${encodeURIComponent(value)}`)
                .then(res => res.json())
                .then(words => {
                    if (!words.length) return;
                    autosuggestDiv.innerHTML = '<ul>' +
                        words.map(word => `<li style="cursor:pointer">${word}</li>`).join('') +
                        '</ul>';
                    // Add click events
                    autosuggestDiv.querySelectorAll('li').forEach(li => {
                        li.addEventListener('click', () => {
                            keywordInput.value = li.textContent;
                            autosuggestDiv.innerHTML = '';
                            // fetchResults(1);
                        });
                    });
                });
        }, 250);
    });



    // --- SEARCH ON ENTER KEY ---
    keywordInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            // fetchResults(1);
            autosuggestDiv.innerHTML = '';
        }
    });



    // --- VIEW TOGGLE (Grid/List) ---
    // Insert view toggle UI after the search form
    const viewToggle = document.createElement('div');
    viewToggle.className = 'pais-view-toggle';
    viewToggle.style.display = 'flex';
    viewToggle.style.gap = '0.5rem';
    viewToggle.style.margin = '0.5rem 0';
    viewToggle.innerHTML = `
        <button id="pais-view-grid" class="pais-view-btn active" aria-label="Grid view" title="Grid view">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 4h4v4H4V4zm6 0h4v4h-4V4zm6 0h4v4h-4V4zM4 10h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4zM4 16h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4z"/>
            </svg>
        </button>
        <button id="pais-view-list" class="pais-view-btn" aria-label="List view" title="List view">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
            </svg>
        </button>
    `;
    root.querySelector('#pais-search-form').after(viewToggle);
    
    // Update view toggle handler
    const updateView = (view) => {
        if (window.innerWidth <= 800) return; // Ignore clicks on mobile
        
        state.selectedView = view;
        const gridBtn = document.getElementById('pais-view-grid');
        const listBtn = document.getElementById('pais-view-list');
        
        if (view === 'grid') {
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
        } else {
            listBtn.classList.add('active');
            gridBtn.classList.remove('active');
        }
        
        if (resultsDiv) {
            resultsDiv.setAttribute('data-view', view);
        }
        
        if (state.lastData) {
            renderResults(state.lastData);
        }
    };
    
    // Set up event listeners
    document.getElementById('pais-view-grid').onclick = () => updateView('grid');
    document.getElementById('pais-view-list').onclick = () => updateView('list');

    // --- PAGINATION ---
    // Create pagination elements
    const pagination = document.createElement('div');
    pagination.id = 'pais-pagination';
    pagination.innerHTML = `
        <div class="pais-pagination-inner">
            <button id="pais-prev" class="pais-pagination-btn" disabled aria-label="Previous page" title="Previous page">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
            </button>
            <span id="pais-page-info" class="pais-page-info">Page 1 of 1</span>
            <button id="pais-next" class="pais-pagination-btn" disabled aria-label="Next page" title="Next page">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                </svg>
            </button>
        </div>
    `;
    root.appendChild(pagination);
    
    // Cache pagination elements
    const pageInfo = document.getElementById('pais-page-info');
    const prevBtn = document.getElementById('pais-prev');
    const nextBtn = document.getElementById('pais-next');
    prevBtn.onclick = function() {
        if (state.currentPage > 1) {
            // fetchResults(state.currentPage - 1);
        }
    };
    nextBtn.onclick = function() {
        if (state.currentPage < state.maxPages) {
            // fetchResults(state.currentPage + 1);
        }
    };

    // --- RESULTS RENDERING ---
    
    function isMobile() {
        return window.innerWidth <= 800;
    }
    
    function updateViewForMobile() {
        const isMobileView = isMobile();
        if (isMobileView) {
            state.selectedView = 'grid';
            const gridBtn = document.getElementById('pais-view-grid');
            const listBtn = document.getElementById('pais-view-list');
            if (gridBtn) gridBtn.classList.add('active');
            if (listBtn) listBtn.classList.remove('active');
        }
        return isMobileView;
    }
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (state.lastData) {
                updateViewForMobile();
                renderResults(state.lastData);
            }
        }, 100);
    });
    
    function renderResults(data) {
        if (!data || !data.posts || !data.posts.length) {
            if (resultsDiv) {
                resultsDiv.innerHTML = '<div class="pais-no-results">No results found. Try adjusting your search criteria.</div>';
            }
            return;
        }

        const isMobile = window.innerWidth <= 800;
        
        // Set data-view attribute on results container
        if (resultsDiv) {
            resultsDiv.setAttribute('data-view', state.selectedView);
        }
        
        if (state.selectedView === 'list') {
            if (isMobile) {
                // Mobile list view - 2 column layout
                let html = '<div class="pais-results-list">';
                data.posts.forEach(post => {
                    const categories = post.category ? 
                        post.category.split(',').map(cat => cat.trim()) : [];
                    
                    let rating = '';
                    if (post.rating > 0) {
                        const filledStars = '★'.repeat(Math.round(post.rating));
                        const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                        rating = `
                            <div class="pais-rating">
                                <span class="pais-rating-stars" title="${post.rating} out of 5">
                                    ${filledStars}${emptyStars}
                                </span>
                                <span class="pais-rating-count">(${post.votes || 0})</span>
                            </div>`;
                    } else {
                        rating = '<div class="pais-rating">No ratings</div>';
                    }
                    
                    html += `
                        <div class="pais-mobile-list-item">
                            <h4><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></h4>
                            <p>${post.excerpt || 'No description available'}</p>
                            ${rating}
                            ${categories.length ? `
                                <div class="pais-categories">
                                    ${categories.map(cat => `<span class="pais-category-tag">${cat}</span>`).join('')}
                                </div>` : ''
                            }
                            <div class="pais-actions">
                                <a href="${post.permalink}" class="pais-button" target="_blank" rel="noopener">View</a>
                                ${post.website ? `<a href="${post.website}" class="pais-button" target="_blank" rel="noopener nofollow">Website</a>` : ''}
                                ${post.comments > 0 ? 
                                    `<a href="${post.permalink}#comments" class="pais-comment-link" target="_blank" rel="noopener">💬 ${post.comments}</a>` : 
                                    ''
                                }
                            </div>
                        </div>`;
                });
                html += '</div>';
                resultsDiv.innerHTML = html;
            } else {
                // Desktop table view
                let html = `
                    <table class="pais-results-table">
                        <thead>
                            <tr>
                                <th class="pais-sort-th" data-sort="title">TITLE <span class="pais-sort-icon">↕</span></th>
                                <th>SUMMARY</th>
                                <th class="pais-sort-th" data-sort="category">CATEGORIES <span class="pais-sort-icon">↕</span></th>
                                <th class="pais-sort-th" data-sort="date">DATE <span class="pais-sort-icon">↕</span></th>
                                <th class="pais-sort-th" data-sort="rating">RATING <span class="pais-sort-icon">↕</span></th>
                                <th class="pais-sort-th" data-sort="comments">COMMENTS <span class="pais-sort-icon">↕</span></th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>`;

                data.posts.forEach(post => {
                    const categories = post.category ? 
                        post.category.split(',').map(cat => 
                            `<span class="pais-category-tag">${cat.trim()}</span>`
                        ).join('') : '—';
                    
                    let rating = '—';
                    if (post.rating > 0) {
                        const filledStars = '★'.repeat(Math.round(post.rating));
                        const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                        rating = `
                            <div class="pais-rating">
                                <span class="pais-rating-stars" title="${post.rating} out of 5">
                                    ${filledStars}${emptyStars}
                                </span>
                                <span class="pais-rating-count">(${post.votes || 0})</span>
                            </div>`;
                    }
                    
                    const date = post.date ? new Date(post.date).toLocaleDateString() : '—';
                    
                    html += `
                        <tr>
                            <td><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></td>
                            <td>${post.excerpt || '—'}</td>
                            <td>${categories || '—'}</td>
                            <td>${date}</td>
                            <td>${rating}</td>
                            <td class="pais-nowrap">
                                ${post.comments > 0 ? 
                                    `<a href="${post.permalink}#comments" class="pais-comment-link" target="_blank" rel="noopener">${post.comments}</a>` : 
                                    '—'
                                }
                            </td>
                            <td class="pais-nowrap">
                                <a href="${post.permalink}" class="pais-button" target="_blank" rel="noopener">View</a>
                                ${post.website ? `<a href="${post.website}" class="pais-button" target="_blank" rel="noopener nofollow">Website</a>` : ''}
                            </td>
                        </tr>`;
                });
                
                html += `
                        </tbody>
                    </table>`;
                    
                resultsDiv.innerHTML = html;
                
                // Add sort event listeners
                document.querySelectorAll('.pais-sort-th').forEach(th => {
                    th.addEventListener('click', function() {
                        const sortBy = this.getAttribute('data-sort');
                        let sortOrder = 'asc';
                        
                        if (window.paisLastSortBy === sortBy) {
                            sortOrder = window.paisLastSortOrder === 'asc' ? 'desc' : 'asc';
                        }
                        
                        window.paisLastSortBy = sortBy;
                        window.paisLastSortOrder = sortOrder;
                        
                        // Update sort indicators
                        document.querySelectorAll('.pais-sort-icon').forEach(icon => {
                            icon.textContent = '↕';
                        });
                        
                        const icon = this.querySelector('.pais-sort-icon');
                        if (icon) {
                            icon.textContent = sortOrder === 'asc' ? '↑' : '↓';
                        }
                        
                        // fetchResults(1, sortBy, sortOrder);
                    });
                });
            }
        } else if (state.selectedView === 'grid') {
            // Grid view implementation
            const gridItems = data.posts.map(post => {
                let ratingStars = '';
                if (post.rating > 0) {
                    const fullStars = '★'.repeat(Math.round(post.rating));
                    const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                    ratingStars = `
                        <div class="pais-rating">
                            <span class="pais-rating-stars" title="${post.rating} out of 5">
                                ${fullStars}${emptyStars}
                            </span>
                            <span class="pais-rating-count">(${post.votes || 0})</span>
                        </div>`;
                }
                
                const categories = post.category ? 
                    post.category.split(',').map(cat => 
                        `<span class="pais-category-tag">${cat.trim()}</span>`
                    ).join('') : '—';
                
                return `
                    <div class="pais-result-item">
                        <h3><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></h3>
                        <p>${post.excerpt || 'No description available'}</p>
                        <div class="pais-result-meta">
                            <span>${categories}</span>
                            ${ratingStars}
                            ${post.comments > 0 ? 
                                `<span class="pais-comment-count">💬 ${post.comments}</span>` : 
                                ''
                            }
                        </div>
                    </div>`;
            }).join('');
            
            resultsDiv.innerHTML = `<div class="pais-results-grid">${gridItems}</div>`;
        }
    }

    // --- FETCH RESULTS WITH PAGINATION SUPPORT ---
    // LEGACY SEARCH LOGIC DISABLED - Use main.js + api.js only

});