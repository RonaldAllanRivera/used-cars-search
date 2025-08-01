// autosuggest.js
// Handles autosuggest logic for the search input

console.log('autosuggest.js loaded');

export function setupAutosuggest(keywordInput, autosuggestDiv, fetchResults, restUrl) {
    console.log('Setting up autosuggest');
    let autosuggestTimeout = null;
    keywordInput.addEventListener('input', function() {
        clearTimeout(autosuggestTimeout);
        const value = keywordInput.value.trim();
        autosuggestDiv.innerHTML = '';
        if (value.length < 2) return;
        autosuggestTimeout = setTimeout(() => {
            fetch(`${restUrl}popularai/v1/autosuggest?q=${encodeURIComponent(value)}`)
                .then(res => res.json())
                .then(words => {
                    if (!words.length) return;
                    autosuggestDiv.innerHTML = '<ul>' +
                        words.map(word => `<li style="cursor:pointer">${word}</li>`).join('') +
                        '</ul>';
                    autosuggestDiv.querySelectorAll('li').forEach(li => {
                        li.addEventListener('click', () => {
                            keywordInput.value = li.textContent;
                            autosuggestDiv.innerHTML = '';
                            fetchResults(1);
                        });
                    });
                });
        }, 250);
    });

    // Hide autosuggest on Enter
    keywordInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            autosuggestDiv.innerHTML = '';
        }
    });
}
