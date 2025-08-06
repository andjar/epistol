const api = {
/**
 * Fetches the email feed data from the backend API.
 * @param {object} [params={}] - Optional parameters for filtering the feed.
 * @param {number} userId - The ID of the current user.
 * @param {object} [params={}] - Optional parameters for filtering the feed.
 * @param {string|null} [params.groupId] - The ID of the group to filter by.
 * @param {number} [params.page] - Page number for pagination.
 * @param {number} [params.limit] - Items per page for pagination.
 * @returns {Promise<Object>} A promise that resolves to the feed data (e.g., { threads: [...] }).
 *                            Returns an empty object or throws an error in case of failure.
 */
async getFeed(userId, params = {}) {
    if (!userId) {
        console.error('getFeed requires a userId.');
        throw new Error('User ID is required to fetch feed.');
    }
    let url = `/api/v1/get_feed.php?user_id=${encodeURIComponent(userId)}`;
    if (params.groupId) {
        url += `&group_id=${encodeURIComponent(params.groupId)}`;
    }
    if (params.status) {
        url += `&status=${encodeURIComponent(params.status)}`;
    }
    if (params.personId) {
        url += `&person_id=${encodeURIComponent(params.personId)}`;
    }
    if (params.page) {
        url += `&page=${encodeURIComponent(params.page)}`;
    }
    if (params.limit) {
        url += `&limit=${encodeURIComponent(params.limit)}`;
    }

    try {
        const response = await fetch(url);
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error fetching feed:', response.status, response.statusText, errorText);
            throw new Error(`Failed to fetch feed: ${response.status} ${response.statusText}`);
        }
        const data = await response.json();
        return data; // Expects { threads: [...] }
    } catch (error) {
        console.error('Network error or JSON parsing error fetching feed:', error);
        throw error; // Re-throw to be handled by caller
    }
},

/**
 * Fetches a single thread's data from the backend API.
 * @param {string} threadId - The ID of the thread to fetch.
 * @param {number} userId - The ID of the current user.
 * @returns {Promise<Object>} A promise that resolves to the thread data.
 * @throws {Error} If the request fails or userId/threadId is missing.
 */
async getThread(threadId, userId) {
    if (!threadId) {
        console.error('getThread requires a threadId.');
        throw new Error('Thread ID is required to fetch thread details.');
    }
    if (!userId) {
        console.error('getThread requires a userId.');
        throw new Error('User ID is required to fetch thread details.');
    }

    const url = `/api/v1/get_thread.php?thread_id=${encodeURIComponent(threadId)}&user_id=${encodeURIComponent(userId)}`;

    try {
        const response = await fetch(url);
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error fetching thread:', response.status, response.statusText, errorText);
            throw new Error(`Failed to fetch thread: ${response.status} ${response.statusText}`);
        }
        const data = await response.json();
        return data; // Expects { id, subject, participants, emails: [...] }
    } catch (error) {
        console.error('Network error or JSON parsing error fetching thread:', error);
        throw error;
    }
},

/**
 * Creates a new group.
 * @param {Object} groupData - The group data to send.
 * @param {string} groupData.name - The name of the group.
 * @returns {Promise<Object>} A promise that resolves to the server's response data on success.
 * @throws {Error} Throws an error if the request fails or the server returns an error status.
 */
async createGroup(groupData) {
    try {
        const response = await fetch('/api/v1/create_group.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(groupData),
        });

        const responseData = await response.json();

        if (!response.ok) {
            const errorMessage = responseData.message || `HTTP error ${response.status}`;
            console.error('Error creating group:', errorMessage, responseData);
            throw new Error(errorMessage);
        }

        return responseData.data;
    } catch (error) {
        console.error('Network error, JSON parsing error, or error thrown from response handling:', error);
        throw error.message ? error : new Error('Failed to create group due to a network or server issue.');
    }
},


/**
 * Sends email data to the backend API.
 * @param {FormData|Object} emailData - The email data to send. Can be FormData (for file uploads) or JSON object.
 * @param {string[]} [emailData.recipients] - Array of recipient email addresses (for JSON).
 * @param {string} [emailData.subject] - Email subject (for JSON).
 * @param {string} [emailData.body_html] - HTML body of the email (for JSON).
 * @param {string} [emailData.body_text] - Plain text body of the email (for JSON).
 * @param {string|null} [emailData.in_reply_to_email_id] - ID of the email being replied to, if any (for JSON).
 * @param {Array} [emailData.attachments] - Array of attachment objects (for JSON).
 * @returns {Promise<Object>} A promise that resolves to the server's response data on success.
 * @throws {Error} Throws an error if the request fails or the server returns an error status.
 */
async sendEmail(emailData) {
    try {
        let headers = {};
        let body;
        
        // Check if this is FormData (for file uploads)
        if (emailData instanceof FormData) {
            // Don't set Content-Type for FormData - let the browser set it with boundary
            body = emailData;
        } else {
            // JSON request
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(emailData);
        }
        
        const response = await fetch('/api/v1/send_email.php', {
            method: 'POST',
            headers: headers,
            body: body,
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
},

/**
 * Fetches a person's profile data from the API.
 * @param {string} personId The ID of the person.
 * @returns {Promise<Object>} A promise that resolves to the profile data.
 * @throws {Error} If the request fails.
 */
async getProfile(personId) {
    if (!personId) {
        throw new Error("Person ID is required to fetch profile.");
    }
    try {
        const response = await fetch(`/api/v1/get_profile.php?person_id=${encodeURIComponent(personId)}`);
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error fetching profile:', response.status, response.statusText, errorText);
            throw new Error(`Failed to fetch profile: ${response.status} ${response.statusText}`);
        }
        const data = await response.json();
        return data.data; // Return the data property from the response
    } catch (error) {
        console.error('Network error or JSON parsing error fetching profile:', error);
        throw error;
    }
},

/**
 * Fetches all groups from the API.
 * @returns {Promise<Array<Object>>} A promise that resolves to an array of group objects.
 * @throws {Error} If the request fails.
 */
async getGroups() {
    try {
        const response = await fetch('/api/v1/get_groups.php');
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error fetching groups:', response.status, response.statusText, errorText);
            throw new Error(`Failed to fetch groups: ${response.status} ${response.statusText}`);
        }
        // Assuming the API returns an object like { groups: [...] } or just [...]
        // Let's assume it returns an array directly for now, as used in app.js and group.js
        const data = await response.json();
        return data.data ? data.data.groups : data.groups; // Handle both response formats
    } catch (error) {
        console.error('Network error or JSON parsing error fetching groups:', error);
        throw error;
    }
},

/**
 * Fetches members of a specific group from the API.
 * @param {string} groupId The ID of the group.
 * @returns {Promise<Array<Object>>} A promise that resolves to an array of member objects.
 * @throws {Error} If the request fails.
 */
async getGroupMembers(groupId) {
    if (!groupId) {
        throw new Error("Group ID is required to fetch group members.");
    }
    try {
        const response = await fetch(`/api/v1/get_group_members.php?group_id=${encodeURIComponent(groupId)}`);
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error fetching group members:', response.status, response.statusText, errorText);
            throw new Error(`Failed to fetch group members: ${response.status} ${response.statusText}`);
        }
        // Assuming the API returns an object like { members: [...] } or just [...]
        // Let's assume it returns an array of members directly.
        const data = await response.json();
        return data.data ? data.data.members : data.members; // Handle both response formats
    } catch (error) {
        console.error('Network error or JSON parsing error fetching group members:', error);
        throw error;
    }
},


/**
 * Sets the status for a specific post (email).
 * @param {string} emailId - The ID of the email/post.
 * @param {number} userId - The ID of the user.
 * @param {string} status - The new status to set (e.g., 'read', 'follow-up').
 * @returns {Promise<Object>} A promise that resolves to the server's response.
 * @throws {Error} If the request fails.
 */
async setPostStatus(emailId, userId, status) {
    if (!emailId || !userId || !status) {
        console.error('setPostStatus requires emailId, userId, and status.');
        throw new Error('Missing parameters for setting post status.');
    }

    try {
        const response = await fetch('/api/v1/set_post_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email_id: emailId, // API expects email_id
                user_id: userId,
                status: status,
            }),
        });

        const responseData = await response.json();

        if (!response.ok) {
            const errorMessage = responseData.error || `HTTP error ${response.status}`;
            console.error('Error setting post status:', errorMessage, responseData);
            throw new Error(errorMessage);
        }
        console.log('Post status updated successfully:', responseData);
        return responseData; // Should include { success: true, message: "..." }
    } catch (error) {
        console.error('Network error or JSON parsing error setting post status:', error);
        throw error.message ? error : new Error('Failed to set post status due to a network or server issue.');
    }
},

/**
 * Updates a user's profile information.
 * @param {string} personId - The ID of the person to update.
 * @param {Object} profileData - The profile data to update.
 * @param {string} profileData.name - The person's name.
 * @param {Array} profileData.email_addresses - Array of email addresses.
 * @returns {Promise<Object>} A promise that resolves to the server's response.
 * @throws {Error} If the request fails.
 */
async updateProfile(personId, profileData) {
    if (!personId || !profileData) {
        console.error('updateProfile requires personId and profileData.');
        throw new Error('Missing parameters for updating profile.');
    }

    try {
        const response = await fetch('/api/v1/update_profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                person_id: personId,
                name: profileData.name,
                email_addresses: profileData.email_addresses
            }),
        });

        const responseData = await response.json();

        if (!response.ok) {
            const errorMessage = responseData.error || `HTTP error ${response.status}`;
            console.error('Error updating profile:', errorMessage, responseData);
            throw new Error(errorMessage);
        }
        console.log('Profile updated successfully:', responseData);
        return responseData;
    } catch (error) {
        console.error('Network error or JSON parsing error updating profile:', error);
        throw error.message ? error : new Error('Failed to update profile due to a network or server issue.');
    }
}
};

