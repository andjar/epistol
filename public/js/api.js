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
        return []; // Keep current behavior for getFeed
    }
}

/**
 * Sends email data to the backend API.
 * @param {Object} emailData - The email data to send.
 * @param {string[]} emailData.recipients - Array of recipient email addresses.
 * @param {string} emailData.subject - Email subject.
 * @param {string} [emailData.body_html] - HTML body of the email.
 * @param {string} [emailData.body_text] - Plain text body of the email.
 * @param {string|null} [emailData.in_reply_to_email_id] - ID of the email being replied to, if any.
 * @param {Array} [emailData.attachments] - Array of attachment objects (currently not implemented in frontend form).
 * @returns {Promise<Object>} A promise that resolves to the server's response data on success.
 * @throws {Error} Throws an error if the request fails or the server returns an error status.
 */
async function sendEmail(emailData) {
    try {
        const response = await fetch('../api/send_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(emailData),
        });

        const responseData = await response.json(); // Try to parse JSON regardless of response.ok

        if (!response.ok) {
            // Log the detailed error message from the server if available
            const errorMessage = responseData.message || `HTTP error ${response.status}`;
            console.error('Error sending email:', errorMessage, responseData);
            throw new Error(errorMessage);
        }

        return responseData.data; // Assuming server wraps successful response in a "data" object
    } catch (error) {
        console.error('Network error, JSON parsing error, or error thrown from response handling:', error);
        // Re-throw the error so the caller (app.js) can handle it, e.g., by showing a UI message.
        // If it's a generic network error without a specific message, ensure one is provided.
        throw error.message ? error : new Error('Failed to send email due to a network or server issue.');
    }
}
