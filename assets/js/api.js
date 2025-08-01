// api.js
// Handles API calls for fetching results and categories

console.log('api.js loaded');

export async function fetchResults({
    page = 1,
    sortBy = 'date',
    sortOrder = 'desc',
    keyword = '',
    category = '',
    restUrl,
}) {
    console.log('fetchResults called with:', { 
        page, 
        sortBy, 
        sortOrder, 
        keyword, 
        category, 
        restUrl 
    });
    
    // Build query parameters
    const params = new URLSearchParams();
    params.append('page', page);
    
    // Only include sort parameters if they're provided
    if (sortBy) {
        params.append('orderby', sortBy);
    }
    if (sortOrder) {
        params.append('order', sortOrder);
    }
    if (keyword) {
        params.append('keyword', keyword);
    }
    if (category) {
        params.append('category', category);
    }
    
    const url = `${restUrl}popularai/v1/search?${params.toString()}`;
    console.log('Fetching from URL:', url);
    console.log('Query parameters:', params.toString());

    try {
        const response = await fetch(url);
        console.log('Response status:', response.status, response.statusText);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Error:', {
                status: response.status,
                statusText: response.statusText,
                error: errorText
            });
            throw new Error(`API request failed: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();
        console.log('API Response:', data);
        return data;
    } catch (error) {
        console.error('Error in fetchResults:', error);
        throw error; // Re-throw to be handled by the caller
    }
}

export async function loadCategories(restUrl) {
    const url = `${restUrl}popularai/v1/categories`;
    const res = await fetch(url);
    if (!res.ok) throw new Error('Failed to fetch categories');
    return res.json();
}
