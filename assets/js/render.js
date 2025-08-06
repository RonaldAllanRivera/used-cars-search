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
            let html = `<table class="ucs-results-table"><thead><tr><th class="ucs-sort-th" data-sort="title">TITLE <span class="ucs-sort-icon">${sortBy === 'title' ? (sortOrder === 'asc' ? '↑' : '↓') : '↕'}</span></th><th>SUMMARY</th><th>PRICE</th><th>MILEAGE</th><th>ENGINE</th><th>TRANSMISSION</th><th class="ucs-sort-th" data-sort="category">CATEGORIES <span class="ucs-sort-icon">${sortBy === 'category' ? (sortOrder === 'asc' ? '↑' : '↓') : '↕'}</span></th><th class="ucs-sort-th" data-sort="date">DATE <span class="ucs-sort-icon">${sortBy === 'date' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th><th class="ucs-sort-th" data-sort="rating">RATING <span class="ucs-sort-icon">${sortBy === 'rating' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th><th class="ucs-sort-th" data-sort="comments">COMMENTS <span class="ucs-sort-icon">${sortBy === 'comments' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th><th>ACTIONS</th></tr></thead><tbody>`;
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
                        <td data-label="Title"><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></td>
                        <td data-label="Summary">${post.excerpt || '—'}</td>
                        <td data-label="Price">${price ? `$${price}` : '—'}</td>
                        <td data-label="Mileage">${mileage ? `${mileage} miles` : '—'}</td>
                        <td data-label="Engine">${engine || '—'}</td>
                        <td data-label="Transmission">${transmission || '—'}</td>
                        <td data-label="Categories">${categories || '—'}</td>
                        <td data-label="Date">${date}</td>
                        <td data-label="Rating">${rating}</td>
                        <td data-label="Comments" class="ucs-nowrap">${post.comments > 0 ? `<a href="${post.permalink}#comments" class="ucs-comment-link" target="_blank" rel="noopener">${post.comments}</a>` : '—'}</td>
                        <td data-label="Actions" class="ucs-nowrap">
                            <a href="${post.permalink}" class="ucs-button" target="_blank" rel="noopener">View</a>
                            ${post.website ? `<a href="${post.website}" class="ucs-button" target="_blank" rel="noopener nofollow">Website</a>` : ''}
                            <br><br><button class="ucs-compare-btn ucs-button" data-post-id="${post.ID}" data-post-title="${post.title}">Compare</button>
                        </td>
                    </tr>`;
            });
            html += `</tbody></table>`;
            container.innerHTML = html;

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
        const gridItems = sortedPosts.map(post => {
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

            let ratingStars = '';
            if (post.rating > 0) {
                const filledStars = '★'.repeat(Math.round(post.rating));
                const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                ratingStars = `<div class="ucs-rating"><span class="ucs-rating-stars" title="${post.rating} out of 5">${filledStars}${emptyStars}</span><span class="ucs-rating-count">(${post.votes || 0})</span></div>`;
            }
            const categories = post.category ? post.category.split(',').map(cat => `<span class="ucs-category-tag">${cat.trim()}</span>`).join('') : '—';
            return `<div class="ucs-result-item"><h3><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></h3><p>${post.excerpt || 'No description available'}</p><div class="ucs-result-meta"><span>${categories}</span>${ratingStars}${post.comments > 0 ? `<span class="ucs-comment-count">💬 ${post.comments}</span>` : ''}</div>${customFieldsHTML}</div>`;
        }).join('');
        container.innerHTML = `<div class="ucs-results-grid">${gridItems}</div>`;
    }
}
