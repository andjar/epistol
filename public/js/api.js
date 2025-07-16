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
async sendEmail(emailData) {
    try {
        const response = await fetch('/api/v1/send_email.php', {
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
                post_id: emailId, // API expects post_id
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
}
};

/**
 * Renders an individual email as a "post" card.
 * @param {Object} email - The email data object.
 * @param {string} threadSubject - The subject of the parent thread.
 * @param {number} currentUserId - The ID of the current user.
 * @param {boolean} isFirstInThread - True if this is the first email in the thread.
 * @returns {string} HTML string for the email post.
 */
window.renderEmailAsPost = function(email, threadSubject, currentUserId, isFirstInThread) {
    const isUnread = email.read_receipts && !email.read_receipts.some(r => r.user_id === currentUserId && r.read_at);
    const unreadClass = isUnread ? 'email-unread' : '';
    const firstPostClass = isFirstInThread ? 'is-first-post' : 'is-reply-post'; // Differentiate first vs. replies

    // Format timestamp (simplified)
    const timestamp = new Date(email.timestamp).toLocaleString([], {
        month: 'short', day: 'numeric', year: (new Date(email.timestamp).getFullYear() !== new Date().getFullYear()) ? 'numeric' : undefined,
        hour: 'numeric', minute: '2-digit', hour12: true
    });

    // Simplified recipient display: For "Sender ▸ Recipient", show first recipient or "Group" if applicable
    // This would need more logic if you have group names associated with emails or want to list multiple recipients.
    let recipientDisplay = email.recipients && email.recipients.length > 0 ? email.recipients[0].name : 'Recipients';
    if (email.group_id && email.group_name) { // Assuming group_name is available if group_id is present
        recipientDisplay = email.group_name;
    } else if (email.recipients && email.recipients.length > 1) {
        recipientDisplay = `${email.recipients[0].name} + ${email.recipients.length - 1} more`;
    }

    // User status for this email (placeholder logic)
    // You'd fetch this from email.user_specific_status or similar
    const userStatus = email.user_specific_statuses && email.user_specific_statuses.find(s => s.user_id === currentUserId)?.status || 'default';
    const statusLabels = {
        'read': 'Read',
        'unread': 'Unread',
        'follow-up': 'Follow-up',
        'important-info': 'Important',
        'default': 'Set Status' // Default if no status or unknown
    };
    const currentStatusLabel = statusLabels[userStatus] || 'Set Status';

    // Create a string for all recipients for "Reply All"
    // This needs to be more robust in a real app (e.g., include CCs, filter out current user)
    const allRecipientsForReplyAll = [email.sender_email, ...(email.recipients || []).map(r => r.email)].join(',');

    return `
        <div class="post-card ${unreadClass} ${firstPostClass}" data-email-id="${email.email_id}">
            <div class="post-header">
                <div class="post-avatar">
                    <span>${email.sender_name ? email.sender_name.charAt(0).toUpperCase() : 'S'}</span>
                </div>
                <div class="post-author-meta">
                    <div class="post-author-line">
                        <a href="#" class="author-name" data-person-id="${email.sender_person_id || ''}">${email.sender_name || 'Unknown Sender'}</a>
                        <span class="recipient-separator">▸</span>
                        <span class="recipient-name">${recipientDisplay}</span>
                    </div>
                    <div class="post-timestamp">${timestamp}</div>
                </div>
                <div class="post-options-menu">
                    <button class="options-btn" aria-label="More options">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="20" height="20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                    </button>
                    <!-- Dropdown for status can go here or be integrated with post-actions -->
                </div>
            </div>

            <div class="post-content">
                ${isFirstInThread && threadSubject ? `<h4 class="post-content-subject">${threadSubject}</h4>` : ''}
                ${email.body_html || `<p>${email.body_preview || 'No content'}</p>`}
            </div>

            <div class="post-footer">
                <div class="post-actions">
                    <button class="action-btn reply-to-email-btn" data-email-id="${email.email_id}" data-subject="${email.subject_for_reply || threadSubject}" data-sender="${email.sender_email}">
                        <span>Reply</span>
                    </button>
                    <button class="action-btn reply-all-to-email-btn" data-email-id="${email.email_id}" data-subject="${email.subject_for_reply || threadSubject}" data-sender="${email.sender_email}" data-all-recipients="${allRecipientsForReplyAll}">
                        <span>Reply All</span>
                    </button>
                    <button class="action-btn forward-email-btn" data-email-id="${email.email_id}" data-subject="${email.subject_for_reply || threadSubject}" data-original-sender="${email.sender_name}" data-original-date="${timestamp}" data-original-body="${email.body_preview || email.body_html}">
                        <span>Forward</span>
                    </button>
                </div>
                <div class="post-status-selector-container email-status-container">
                     <span class="current-post-status" style="display:none;">Status: ${userStatus}</span> <!-- Hidden, for app.js logic if needed -->
                     <select class="post-status-select minimalist-select" data-email-id="${email.email_id}">
                        <option value="" ${userStatus === 'default' ? 'selected' : ''} disabled>${currentStatusLabel}</option>
                        <option value="read" ${userStatus === 'read' ? 'selected' : ''}>Read</option>
                        <option value="unread" ${userStatus === 'unread' ? 'selected' : ''}>Unread</option>
                        <option value="follow-up" ${userStatus === 'follow-up' ? 'selected' : ''}>Follow-up</option>
                        <option value="important-info" ${userStatus === 'important-info' ? 'selected' : ''}>Important</option>
                     </select>
                </div>
            </div>
        </div>
    `;
};

/**
 * Renders a single thread object into an HTML element.
 * @param {Object} threadData - The thread data object.
 * @param {string} threadSubject - The subject of the parent thread (often from threadData.subject).
 * @param {number} currentUserId - The ID of the current user.
 * @returns {HTMLElement} A div element representing the thread.
 */
window.renderThread = function(threadData, threadSubject, currentUserId) {
    const threadContainer = document.createElement('div');
    threadContainer.className = 'thread';
    threadContainer.dataset.threadId = threadData.thread_id;

    // Optional: A very muted title for the overall thread, if desired *above* all posts.
    // If the subject is prominent in the first post, this might be redundant.
    // const threadTitleEl = document.createElement('h3');
    // threadTitleEl.className = 'thread-overall-title';
    // threadTitleEl.textContent = threadSubject;
    // threadContainer.appendChild(threadTitleEl);

    const postsContainer = document.createElement('div');
    postsContainer.className = 'posts-container';

    if (threadData.emails && threadData.emails.length > 0) {
        threadData.emails.forEach((email, index) => {
            // Pass threadSubject to be potentially displayed in the first post's content
            const emailPostHTML = window.renderEmailAsPost(email, threadSubject, currentUserId, index === 0);
            postsContainer.innerHTML += emailPostHTML; // Append HTML string
        });
    } else {
        postsContainer.innerHTML = '<p class="no-emails-in-thread">No messages in this conversation.</p>';
    }

    threadContainer.appendChild(postsContainer);
    return threadContainer;
};
