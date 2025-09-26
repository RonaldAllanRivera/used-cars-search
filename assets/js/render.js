// render.js
// Handles rendering of used cars search results (list, grid)

// Helper function to sort posts
function sortPosts(posts, sortBy, sortOrder = 'asc') {
    return [...posts].sort((a, b) => {
        let valA, valB;
        
        switch (sortBy) {
            case 'title':
                valA = a.title?.toLowerCase() || '';
                valB = b.title?.toLowerCase() || '';
                break;
                
            case 'category':
                valA = a.category?.toLowerCase() || '';
                valB = b.category?.toLowerCase() || '';
                break;
                
            case 'date':
                valA = new Date(a.date || 0).getTime();
                valB = new Date(b.date || 0).getTime();
                return sortOrder === 'asc' ? valA - valB : valB - valA;
                
            case 'rating':
                valA = parseFloat(a.rating) || 0;
                valB = parseFloat(b.rating) || 0;
                return sortOrder === 'asc' ? valA - valB : valB - valA;
                
            case 'comments':
                valA = parseInt(a.comments) || 0;
                valB = parseInt(b.comments) || 0;
                return sortOrder === 'asc' ? valA - valB : valB - valA;
                
            default:
                return 0;
        }
        
        if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
        return 0;
    });
}

// Store the original posts to prevent data loss during re-renders
let originalPosts = [];

// Store the current sort state
let currentSortState = {
    sortBy: 'date',
    sortOrder: 'desc'
};

// Injects high-priority CSS to neutralize theme sorting icons and constrain icon sizes
function injectSortIconFixStyles() {
    if (document.getElementById('ucs-sort-icon-fix')) return;
    const css = `
    /* Keep header background subtle */
    #ucs-search-root .ucs-results-table thead { background-color: #f8fafc !important; }

    /* Remove theme/plugin sort backgrounds and pseudo icons in headers */
    #ucs-search-root .ucs-results-table thead th { background-image: none !important; }
    #ucs-search-root .ucs-results-table thead th::before,
    #ucs-search-root .ucs-results-table thead th::after,
    #ucs-search-root .ucs-results-table thead th a::before,
    #ucs-search-root .ucs-results-table thead th a::after,
    #ucs-search-root .ucs-results-table thead th span::before,
    #ucs-search-root .ucs-results-table thead th span::after { content: none !important; display: none !important; }

    /* Widespread sorting class patterns */
    #ucs-search-root .ucs-results-table thead th.sorting,
    #ucs-search-root .ucs-results-table thead th.sorting_asc,
    #ucs-search-root .ucs-results-table thead th.sorting_desc,
    #ucs-search-root .ucs-results-table thead th[class*="sorting"],
    #ucs-search-root .ucs-results-table thead th.header,
    #ucs-search-root .ucs-results-table thead th.headerSortUp,
    #ucs-search-root .ucs-results-table thead th.headerSortDown,
    #ucs-search-root .ucs-results-table thead th.tablesorter-header,
    #ucs-search-root .ucs-results-table thead th.tablesorter-headerAsc,
    #ucs-search-root .ucs-results-table thead th.tablesorter-headerDesc { background: none !important; background-image: none !important; }

    /* Any sort-related child element: no background, size relative to text */
    #ucs-search-root .ucs-results-table thead th [class*="sort"],
    #ucs-search-root .ucs-results-table thead th [class^="sort"] {
      display: inline-block !important;
      width: 1em !important; height: 1em !important; max-width: 1em !important; max-height: 1em !important;
      margin-left: 0.35em !important; vertical-align: middle !important; background: transparent !important; background-image: none !important;
      border: 0 !important; box-shadow: none !important;
      font-size: 1em !important; line-height: 1em !important;
    }
    #ucs-search-root .ucs-results-table thead th [class*="sort"]::before,
    #ucs-search-root .ucs-results-table thead th [class*="sort"]::after { content: none !important; display: none !important; }

    /* Our own icon span sized with text */
    #ucs-search-root .ucs-results-table thead th .ucs-sort-icon { display: inline-block !important; width: 1em !important; height: 1em !important; font-size: 1em !important; line-height: 1em !important; margin-left: 0.35em !important; opacity: .85 !important; }
    `;
    const style = document.createElement('style');
    style.id = 'ucs-sort-icon-fix';
    style.textContent = css;
    document.head.appendChild(style);
}

function formatNumber(value, decimals = 0) {
    if (value === null || value === undefined || value === '') return '';
    let num = Number(value);
    if (isNaN(num)) return value;
    return num.toLocaleString(undefined, {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
}

export function renderResults(container, data, state) {
    console.log('Rendering results with data:', data);
    
    // Always use the latest posts from API response
    const postsToRender = (data?.posts && data.posts.length > 0) ? data.posts : [];
    
    if (!postsToRender.length) {
        container.innerHTML = '<div class="ucs-no-results">No results found. Try adjusting your search criteria.</div>';
        return;
    }
    
    // Get current sort state
    const sortBy = window.ucsLastSortBy || currentSortState.sortBy;
    const sortOrder = window.ucsLastSortOrder || currentSortState.sortOrder;
    
    // Update current sort state
    currentSortState = { sortBy, sortOrder };
    
    // Sort the posts
    const sortedPosts = sortPosts(postsToRender, sortBy, sortOrder);
    
    const isMobile = window.innerWidth <= 800;
    container.setAttribute('data-view', state.selectedView);
    
    if (state.selectedView === 'list') {
        if (isMobile) {
            let html = '<div class="ucs-results-list">';
            sortedPosts.forEach(post => {
                const { year, make, model, trim, price, mileage, engine, transmission } = post.custom_fields || {};
                const customFieldsHTML = `
                    <div class="ucs-custom-fields">
                        ${price ? `<span class="ucs-price-tag">$${price}</span>` : ''}
                        ${mileage ? `<span class="ucs-mileage-tag">${mileage} miles</span>` : ''}
                        ${year && make && model ? `<span class="ucs-car-info">${year} ${make} ${model} ${trim || ''}</span>` : ''}
                        ${engine ? `<span class="ucs-engine-tag">${engine}</span>` : ''}
                        ${transmission ? `<span class="ucs-transmission-tag">${transmission}</span>` : ''}
                    </div>
                `;

                const categories = post.category ? post.category.split(',').map(cat => cat.trim()) : [];
                let rating = '';
                if (post.rating > 0) {
                    const filledStars = '★'.repeat(Math.round(post.rating));
                    const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                    rating = `<div class="ucs-rating"><span class="ucs-rating-stars" title="${post.rating} out of 5">${filledStars}${emptyStars}</span><span class="ucs-rating-count">(${post.votes || 0})</span></div>`;
                } else {
                    rating = '<div class="ucs-rating">No ratings</div>';
                }
                html += `<div class="ucs-mobile-list-item"><h4><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></h4><p>${post.excerpt || 'No description available'}</p>${rating}${categories.length ? `<div class="ucs-categories">${categories.map(cat => `<span class="ucs-category-tag">${cat}</span>`).join('')}</div>` : ''}${customFieldsHTML}<div class="ucs-actions"><a href="${post.permalink}" class="ucs-button" target="_blank" rel="noopener">View</a>${post.website ? `<a href="${post.website}" class="ucs-button" target="_blank" rel="noopener nofollow">Website</a>` : ''}<button class="ucs-compare-btn ucs-button" data-post-id="${post.ID}" data-post-title="${post.title}">Compare</button>${post.comments > 0 ? `<a href="${post.permalink}#comments" class="ucs-comment-link" target="_blank" rel="noopener">💬 ${post.comments}</a>` : ''}</div></div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            // Get column settings
            const enabledColumns = window.ucs_vars?.enabled_columns || {
                title: true, price: true, mileage: true, engine: true, transmission: true,
                categories: true, date: true, rating: true, comments: true, actions: true
            };
            
            // Build table header based on enabled columns
            let headerHTML = '<tr>';
            if (enabledColumns.title) {
                headerHTML += `<th class="ucs-sort-th" data-sort="title">TITLE <span class="ucs-sort-icon">${sortBy === 'title' ? (sortOrder === 'asc' ? '↑' : '↓') : '↕'}</span></th>`;
            }
            if (enabledColumns.price) headerHTML += '<th>PRICE</th>';
            if (enabledColumns.mileage) headerHTML += '<th>MILEAGE</th>';
            if (enabledColumns.engine) headerHTML += '<th>ENGINE</th>';
            if (enabledColumns.transmission) headerHTML += '<th>TRANS.</th>';
            if (enabledColumns.categories) {
                headerHTML += `<th class="ucs-sort-th" data-sort="category">CATEGORIES <span class="ucs-sort-icon">${sortBy === 'category' ? (sortOrder === 'asc' ? '↑' : '↓') : '↕'}</span></th>`;
            }
            if (enabledColumns.date) {
                headerHTML += `<th class="ucs-sort-th" data-sort="date">DATE <span class="ucs-sort-icon">${sortBy === 'date' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th>`;
            }
            if (enabledColumns.rating) {
                headerHTML += `<th class="ucs-sort-th" data-sort="rating">RATING <span class="ucs-sort-icon">${sortBy === 'rating' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th>`;
            }
            if (enabledColumns.comments) {
                headerHTML += `<th class="ucs-sort-th" data-sort="comments">COMMENTS <span class="ucs-sort-icon">${sortBy === 'comments' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th>`;
            }
            if (enabledColumns.actions) headerHTML += '<th>ACTIONS</th>';
            headerHTML += '</tr>';
            
            let html = `<table class="ucs-results-table"><thead>${headerHTML}</thead><tbody>`;
            sortedPosts.forEach(post => {
                const { price, mileage, engine, transmission } = post.custom_fields || {};
                const categories = post.category ? 
                    post.category.split(',').map(cat => {
                        const trimmedCat = cat.trim();
                        return `<span class="ucs-category-tag" data-category="${encodeURIComponent(trimmedCat)}">${trimmedCat}</span>`;
                    }).join('') : '—';
                
                let rating = '—';
                if (post.rating > 0) {
                    const filledStars = '★'.repeat(Math.round(post.rating));
                    const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                    rating = `<div class="ucs-rating"><span class="ucs-rating-stars" title="${post.rating} out of 5">${filledStars}${emptyStars}</span><span class="ucs-rating-count">(${post.votes || 0})</span></div>`;
                }
                const date = post.date ? new Date(post.date).toLocaleDateString() : '—';
                html += `
                    <tr>
                        ${enabledColumns.title ? `<td data-label="Title"><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></td>` : ''}
                        ${enabledColumns.price ? `<td data-label="Price">${price ? `$${formatNumber(price, 2)}` : '\u2014'}</td>` : ''}
                        ${enabledColumns.mileage ? `<td data-label="Mileage">${mileage ? `${formatNumber(mileage, 0)} miles` : '\u2014'}</td>` : ''}
                        ${enabledColumns.engine ? `<td data-label="Engine">${engine || '—'}</td>` : ''}
                        ${enabledColumns.transmission ? `<td data-label="Transmission">${transmission || '—'}</td>` : ''}
                        ${enabledColumns.categories ? `<td data-label="Categories">${categories || '—'}</td>` : ''}
                        ${enabledColumns.date ? `<td data-label="Date">${date}</td>` : ''}
                        ${enabledColumns.rating ? `<td data-label="Rating">${rating}</td>` : ''}
                        ${enabledColumns.comments ? `<td data-label="Comments" class="ucs-nowrap">${post.comments > 0 ? `<a href="${post.permalink}#comments" class="ucs-comment-link" target="_blank" rel="noopener">${post.comments}</a>` : '—'}</td>` : ''}
                        ${enabledColumns.actions ? `<td data-label="Actions" class="ucs-nowrap">
                            <button class="ucs-compare-btn ucs-button" data-post-id="${post.ID}" data-post-title="${post.title}">Compare</button>
                        </td>` : ''}
                    </tr>`;
            });
            html += `</tbody></table>`;
            container.innerHTML = html;

            // Ensure our small, neutral sorting icons override any theme styles
            injectSortIconFixStyles();

            // Add sort event listeners
            document.querySelectorAll('.ucs-sort-th').forEach(th => {
                th.style.cursor = 'pointer';
                th.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const sortBy = this.getAttribute('data-sort');
                    let sortOrder = 'asc';
                    
                    // Toggle sort order if clicking the same column
                    if (window.ucsLastSortBy === sortBy) {
                        sortOrder = window.ucsLastSortOrder === 'asc' ? 'desc' : 'asc';
                    }
                    
                    // Update sort state
                    window.ucsLastSortBy = sortBy;
                    window.ucsLastSortOrder = sortOrder;
                    
                    // Re-render the results with the new sort order
                    renderResults(container, data, state);
                });
            });

            // Add click handler for category tags
            document.querySelectorAll('.ucs-category-tag').forEach(tag => {
                tag.addEventListener('click', function(e) {
                    e.preventDefault();
                    const category = this.getAttribute('data-category');
                    if (category) {
                        const categorySelect = document.getElementById('ucs-category');
                        if (categorySelect) {
                            categorySelect.value = category;
                            const event = new Event('change');
                            categorySelect.dispatchEvent(event);
                        }
                    }
                });
            });

            if (window.ucsLastSortBy) {
                const th = container.querySelector(`.ucs-sort-th[data-sort=\"${window.ucsLastSortBy}\"]`);
                if (th) {
                    const icon = th.querySelector('.ucs-sort-icon');
                    if (icon) {
                        icon.textContent = window.ucsLastSortOrder === 'asc' ? '↑' : '↓';
                    }
                }
            }
        }
    } else {
        // Grid view implementation - original design
        const enabledGridFields = window.ucs_vars?.enabled_grid_fields || {
            year: true, make: true, model: true, trim: true, price: true, mileage: true,
            engine: true, transmission: true, rating: true, comments: true
        };
        
        const gridItems = sortedPosts.map(post => {
            const { year, make, model, trim, price, mileage, engine, transmission } = post.custom_fields || {};
            
            // Build custom fields HTML based on enabled fields
            let customFieldsHTML = '';
            const hasAnyEnabledField = enabledGridFields.year || enabledGridFields.make || enabledGridFields.model || 
                                   enabledGridFields.trim || enabledGridFields.price || enabledGridFields.mileage ||
                                   enabledGridFields.engine || enabledGridFields.transmission;
            
            if (hasAnyEnabledField) {
                customFieldsHTML = '<div class="ucs-custom-fields" style="margin-bottom:8px;">';
                if (enabledGridFields.year) customFieldsHTML += `<div><strong>YEAR:</strong> ${year || '—'}</div>`;
                if (enabledGridFields.make) customFieldsHTML += `<div><strong>MAKE:</strong> ${make || '—'}</div>`;
                if (enabledGridFields.model) customFieldsHTML += `<div><strong>MODEL:</strong> ${model || '—'}</div>`;
                if (enabledGridFields.trim) customFieldsHTML += `<div><strong>TRIM:</strong> ${trim || '—'}</div>`;
                if (enabledGridFields.price) customFieldsHTML += `<div><strong>PRICE:</strong> ${price ? `$${formatNumber(price, 2)}` : '—'}</div>`;
                if (enabledGridFields.mileage) customFieldsHTML += `<div><strong>MILEAGE:</strong> ${mileage ? `${formatNumber(mileage, 0)} miles` : '—'}</div>`;
                if (enabledGridFields.engine) customFieldsHTML += `<div><strong>ENGINE:</strong> ${engine || '—'}</div>`;
                if (enabledGridFields.transmission) customFieldsHTML += `<div><strong>TRANSMISSION:</strong> ${transmission || '—'}</div>`;
                customFieldsHTML += '</div>';
            }

            let ratingStars = '';
            if (enabledGridFields.rating && post.rating > 0) {
                const filledStars = '★'.repeat(Math.round(post.rating));
                const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                ratingStars = `<div class="ucs-rating"><span class="ucs-rating-stars" title="${post.rating} out of 5">${filledStars}${emptyStars}</span><span class="ucs-rating-count">(${post.votes || 0})</span></div>`;
            }
            const categories = post.category ? post.category.split(',').map(cat => `<span class="ucs-category-tag">${cat.trim()}</span>`).join('') : '—';
            
            // Build meta HTML based on enabled fields
            let metaHTML = '<div class="ucs-result-meta">';
            metaHTML += `<span>${categories}</span>`;
            if (enabledGridFields.rating) metaHTML += ratingStars;
            if (enabledGridFields.comments && post.comments > 0) metaHTML += `<span class="ucs-comment-count">💬 ${post.comments}</span>`;
            metaHTML += '</div>';
            
            return `<div class="ucs-result-item"><h3><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></h3><p>${post.excerpt || 'No description available'}</p>${customFieldsHTML}${metaHTML}</div>`;
        }).join('');
        container.innerHTML = `<div class="ucs-results-grid">${gridItems}</div>`;
    }
}
