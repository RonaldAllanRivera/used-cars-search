// render.js
// Handles rendering of search results (list, grid)

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
        container.innerHTML = '<div class="pais-no-results">No results found. Try adjusting your search criteria.</div>';
        return;
    }
    
    // Get current sort state
    const sortBy = window.paisLastSortBy || currentSortState.sortBy;
    const sortOrder = window.paisLastSortOrder || currentSortState.sortOrder;
    
    // Update current sort state
    currentSortState = { sortBy, sortOrder };
    
    // Sort the posts
    const sortedPosts = sortPosts(postsToRender, sortBy, sortOrder);
    
    const isMobile = window.innerWidth <= 800;
    container.setAttribute('data-view', state.selectedView);
    
    if (state.selectedView === 'list') {
        if (isMobile) {
            let html = '<div class="pais-results-list">';
            sortedPosts.forEach(post => {
                const categories = post.category ? post.category.split(',').map(cat => cat.trim()) : [];
                let rating = '';
                if (post.rating > 0) {
                    const filledStars = '★'.repeat(Math.round(post.rating));
                    const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                    rating = `<div class=\"pais-rating\"><span class=\"pais-rating-stars\" title=\"${post.rating} out of 5\">${filledStars}${emptyStars}</span><span class=\"pais-rating-count\">(${post.votes || 0})</span></div>`;
                } else {
                    rating = '<div class="pais-rating">No ratings</div>';
                }
                html += `<div class=\"pais-mobile-list-item\"><h4><a href=\"${post.permalink}\" target=\"_blank\" rel=\"noopener\">${post.title}</a></h4><p>${post.excerpt || 'No description available'}</p>${rating}${categories.length ? `<div class=\"pais-categories\">${categories.map(cat => `<span class=\"pais-category-tag\">${cat}</span>`).join('')}</div>` : ''}<div class=\"pais-actions\"><a href=\"${post.permalink}\" class=\"pais-button\" target=\"_blank\" rel=\"noopener\">View</a>${post.website ? `<a href=\"${post.website}\" class=\"pais-button\" target=\"_blank\" rel=\"noopener nofollow\">Website</a>` : ''}<button class=\"pais-compare-btn pais-button\" data-post-id=\"${post.ID}\" data-post-title=\"${post.title}\">Compare</button>${post.comments > 0 ? `<a href=\"${post.permalink}#comments\" class=\"pais-comment-link\" target=\"_blank\" rel=\"noopener\">💬 ${post.comments}</a>` : ''}</div></div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            let html = `<table class="pais-results-table"><thead><tr><th class="pais-sort-th" data-sort="title">TITLE <span class="pais-sort-icon">${sortBy === 'title' ? (sortOrder === 'asc' ? '↑' : '↓') : '↕'}</span></th><th>SUMMARY</th><th class="pais-sort-th" data-sort="category">CATEGORIES <span class="pais-sort-icon">${sortBy === 'category' ? (sortOrder === 'asc' ? '↑' : '↓') : '↕'}</span></th><th class="pais-sort-th" data-sort="date">DATE <span class="pais-sort-icon">${sortBy === 'date' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th><th class="pais-sort-th" data-sort="rating">RATING <span class="pais-sort-icon">${sortBy === 'rating' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th><th class="pais-sort-th" data-sort="comments">COMMENTS <span class="pais-sort-icon">${sortBy === 'comments' ? (sortOrder === 'desc' ? '↓' : '↑') : '↕'}</span></th><th>ACTIONS</th></tr></thead><tbody>`;
            sortedPosts.forEach(post => {
                const categories = post.category ? 
                    post.category.split(',').map(cat => {
                        const trimmedCat = cat.trim();
                        return `<span class="pais-category-tag" data-category="${encodeURIComponent(trimmedCat)}">${trimmedCat}</span>`;
                    }).join('') : '—';
                
                let rating = '—';
                if (post.rating > 0) {
                    const filledStars = '★'.repeat(Math.round(post.rating));
                    const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                    rating = `<div class="pais-rating"><span class="pais-rating-stars" title="${post.rating} out of 5">${filledStars}${emptyStars}</span><span class="pais-rating-count">(${post.votes || 0})</span></div>`;
                }
                const date = post.date ? new Date(post.date).toLocaleDateString() : '—';
                html += `
                    <tr>
                        <td data-label="Title"><a href="${post.permalink}" target="_blank" rel="noopener">${post.title}</a></td>
                        <td data-label="Summary">${post.excerpt || '—'}</td>
                        <td data-label="Categories">${categories || '—'}</td>
                        <td data-label="Date">${date}</td>
                        <td data-label="Rating">${rating}</td>
                        <td data-label="Comments" class="pais-nowrap">${post.comments > 0 ? `<a href="${post.permalink}#comments" class="pais-comment-link" target="_blank" rel="noopener">${post.comments}</a>` : '—'}</td>
                        <td data-label="Actions" class="pais-nowrap">
                            <a href="${post.permalink}" class="pais-button" target="_blank" rel="noopener">View</a>
                            ${post.website ? `<a href="${post.website}" class="pais-button" target="_blank" rel="noopener nofollow">Website</a>` : ''}
                            <button class="pais-compare-btn pais-button" data-post-id="${post.ID}" data-post-title="${post.title}">Compare</button>
                        </td>
                    </tr>`;
            });
            html += `</tbody></table>`;
            container.innerHTML = html;

            // Add sort event listeners
            document.querySelectorAll('.pais-sort-th').forEach(th => {
                th.style.cursor = 'pointer';
                th.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const sortBy = this.getAttribute('data-sort');
                    let sortOrder = 'asc';
                    
                    // Toggle sort order if clicking the same column
                    if (window.paisLastSortBy === sortBy) {
                        sortOrder = window.paisLastSortOrder === 'asc' ? 'desc' : 'asc';
                    }
                    
                    // Update sort state
                    window.paisLastSortBy = sortBy;
                    window.paisLastSortOrder = sortOrder;
                    
                    // Re-render the results with the new sort order
                    renderResults(container, data, state);
                });
            });

            // Add click handler for category tags
            document.querySelectorAll('.pais-category-tag').forEach(tag => {
                tag.addEventListener('click', function(e) {
                    e.preventDefault();
                    const category = this.getAttribute('data-category');
                    if (category) {
                        const categorySelect = document.getElementById('pais-category');
                        if (categorySelect) {
                            categorySelect.value = category;
                            const event = new Event('change');
                            categorySelect.dispatchEvent(event);
                        }
                    }
                });
            });

            if (window.paisLastSortBy) {
                const th = container.querySelector(`.pais-sort-th[data-sort=\"${window.paisLastSortBy}\"]`);
                if (th) {
                    const icon = th.querySelector('.pais-sort-icon');
                    if (icon) {
                        icon.textContent = window.paisLastSortOrder === 'asc' ? '↑' : '↓';
                    }
                }
            }
        }
    } else {
        // Grid view implementation - original design
        const gridItems = sortedPosts.map(post => {
            let ratingStars = '';
            if (post.rating > 0) {
                const fullStars = '★'.repeat(Math.round(post.rating));
                const emptyStars = '☆'.repeat(5 - Math.round(post.rating));
                ratingStars = `<div class=\"pais-rating\"><span class=\"pais-rating-stars\" title=\"${post.rating} out of 5\">${fullStars}${emptyStars}</span><span class=\"pais-rating-count\">(${post.votes || 0})</span></div>`;
            }
            const categories = post.category ? post.category.split(',').map(cat => `<span class=\"pais-category-tag\">${cat.trim()}</span>`).join('') : '—';
            return `<div class=\"pais-result-item\"><h3><a href=\"${post.permalink}\" target=\"_blank\" rel=\"noopener\">${post.title}</a></h3><p>${post.excerpt || 'No description available'}</p><div class=\"pais-result-meta\"><span>${categories}</span>${ratingStars}${post.comments > 0 ? `<span class=\"pais-comment-count\">💬 ${post.comments}</span>` : ''}</div></div>`;
        }).join('');
        container.innerHTML = `<div class=\"pais-results-grid\">${gridItems}</div>`;
    }
}
