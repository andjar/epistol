/**
 * Fetches the email feed data from the backend API.
 * @returns {Promise<Array<Object>>} A promise that resolves to an array of thread objects.
 *                                   Returns an empty array in case of an error.
 */
async function getFeed() {
    try {
        const response = await fetch('../api/get_feed.php');
        if (!response.ok) {
            console.error('Error fetching feed:', response.status, response.statusText);
            // It might be better to throw an error here and let the caller handle it
            // For now, returning an empty array to prevent breaking the app.
            return [];
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Network error or JSON parsing error:', error);
        // Return empty array or re-throw, depending on desired error handling strategy
        return [];
    }
}
