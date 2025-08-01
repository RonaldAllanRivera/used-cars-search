// compare.js
// Handles the logic for the new compare feature in Used Cars Search.

document.addEventListener('DOMContentLoaded', () => {
    const MAX_COMPARE_ITEMS = 4;
    let compareItems = [];

    // Create the compare bar container
    const compareBar = document.createElement('div');
    compareBar.id = 'ucs-compare-bar';
    compareBar.className = 'ucs-compare-bar-hidden';
    document.body.appendChild(compareBar);

    function updateCompareBar() {
        if (compareItems.length === 0) {
            compareBar.classList.add('ucs-compare-bar-hidden');
            return;
        }

        const itemsHtml = compareItems.map(item => 
            `<span class="ucs-compare-item">${item.title} <button class="ucs-remove-from-compare" data-post-id="${item.id}">×</button></span>`
        ).join('');

        compareBar.innerHTML = `
            <div class="ucs-compare-bar-content">
                <span class="ucs-compare-title">Compare Vehicles:</span>
                <div class="ucs-compare-items-list">${itemsHtml}</div>
                <div class="ucs-compare-actions">
                    <button id="ucs-compare-action-btn" class="ucs-button">Compare (${compareItems.length})</button>
                    <button id="ucs-clear-compare-btn" class="ucs-button">Clear</button>
                </div>
            </div>
        `;
        compareBar.classList.remove('ucs-compare-bar-hidden');

        // Add event listeners for new buttons in the bar
        document.getElementById('ucs-clear-compare-btn').addEventListener('click', clearCompareList);
        document.getElementById('ucs-compare-action-btn').addEventListener('click', redirectToComparePage);
        document.querySelectorAll('.ucs-remove-from-compare').forEach(button => {
            button.addEventListener('click', (e) => {
                const postId = e.target.getAttribute('data-post-id');
                handleCompareClick(postId, null, e.target);
            });
        });
    }

    function redirectToComparePage() {
        if (compareItems.length > 0) {
            const baseUrl = window.ucs_vars.compare_page_url || '/compare-vehicles/';
            const compareUrl = new URL(baseUrl);
            compareUrl.searchParams.set('compare_ids', compareItems.map(item => item.id).join(','));
            window.location.href = compareUrl.href;
        }
    }

    function handleCompareClick(postId, postTitle, buttonElement) {
        const existingIndex = compareItems.findIndex(item => item.id === postId);

        if (existingIndex > -1) {
            // Remove item
            compareItems.splice(existingIndex, 1);
            if(buttonElement) buttonElement.textContent = 'Compare';
        } else {
            // Add item
            if (compareItems.length >= MAX_COMPARE_ITEMS) {
                alert(`You can only compare up to ${MAX_COMPARE_ITEMS} items.`);
                return;
            }
            compareItems.push({ id: postId, title: postTitle });
            if(buttonElement) buttonElement.textContent = 'Remove';
        }
        
        // Update all buttons for this post ID
        document.querySelectorAll(`.ucs-compare-btn[data-post-id="${postId}"]`).forEach(btn => {
            btn.textContent = existingIndex > -1 ? 'Compare' : 'Remove';
        });

        updateCompareBar();
    }

    function clearCompareList() {
        compareItems = [];
        document.querySelectorAll('.ucs-compare-btn').forEach(button => {
            button.textContent = 'Compare';
        });
        updateCompareBar();
    }

    // Use event delegation on the results container
    const resultsContainer = document.getElementById('ucs-results');
    if (resultsContainer) {
        resultsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('ucs-compare-btn')) {
                const postId = e.target.getAttribute('data-post-id');
                const postTitle = e.target.getAttribute('data-post-title');
                handleCompareClick(postId, postTitle, e.target);
            }
        });
    }
});
