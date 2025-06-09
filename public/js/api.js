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
        return data; // Expects { name, email_addresses, notes, threads: [...] }
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
        return data; // Expects [{ group_id, name }, ...]
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
        return data; // Expects [{ person_id, name }, ...]
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
 * Renders a single thread object into an HTML element.
 * This function is now globally available via window.renderThread
 * @param {Object} threadData - The thread data object.
 * @param {string} threadSubject - The subject of the parent thread (passed for reply pre-fill).
 * Expected structure: { thread_id, subject, participants, last_reply_time, emails: [{ email_id, sender_name, body_preview, timestamp, status, sender_person_id, to_recipients, cc_recipients, attachments }] }
 * @param {number} currentUserId - The ID of the currently logged-in user, needed for status changes.
 * @returns {HTMLElement} A div element representing the thread.
 */
window.renderThread = function(threadData, threadSubject, currentUserId) {
    const threadDiv = document.createElement('div');
    threadDiv.className = 'thread';
    threadDiv.dataset.threadId = threadData.thread_id;

    const subjectH2 = document.createElement('h2');
    subjectH2.className = 'thread-subject';
    subjectH2.textContent = threadData.subject || 'No Subject';
    threadDiv.appendChild(subjectH2);

    if (threadData.participants && threadData.participants.length > 0) {
        const participantsP = document.createElement('p');
        participantsP.className = 'thread-participants';
        participantsP.textContent = 'Participants: ' + threadData.participants.join(', ');
        threadDiv.appendChild(participantsP);
    }

    if (threadData.last_reply_time) {
        const lastReplyP = document.createElement('p');
        lastReplyP.className = 'thread-last-reply';
        lastReplyP.textContent = 'Last reply: ' + new Date(threadData.last_reply_time).toLocaleString();
        threadDiv.appendChild(lastReplyP);
    }

    const emailsDiv = document.createElement('div');
    emailsDiv.className = 'thread-emails';

    if (threadData.emails && threadData.emails.length > 0) {
        threadData.emails.forEach(email => {
            const emailDiv = document.createElement('div');
            emailDiv.className = 'email-summary';
            emailDiv.dataset.emailId = email.email_id;

            // Consider null or undefined status as 'unread' for robustness
            if (email.status === 'unread' || email.status === null || typeof email.status === 'undefined') {
                emailDiv.classList.add('email-unread');
            } else {
                emailDiv.classList.remove('email-unread');
            }

            const senderP = document.createElement('p');
            senderP.className = 'email-sender';
            const senderNameSpan = document.createElement('span');
            senderNameSpan.className = 'sender-link';
            senderNameSpan.textContent = email.sender_name || 'Unknown Sender';
            if (email.sender_person_id) {
                senderNameSpan.dataset.personId = email.sender_person_id;
            } else {
                senderNameSpan.classList.add('no-profile');
            }
            senderP.appendChild(document.createTextNode('From: '));
            senderP.appendChild(senderNameSpan);
            emailDiv.appendChild(senderP);

            if (email.to_recipients && email.to_recipients.length > 0) {
                const toP = document.createElement('p');
                toP.className = 'email-recipients-to';
                toP.textContent = `To: ${email.to_recipients.join(', ')}`;
                emailDiv.appendChild(toP);
            }
            if (email.cc_recipients && email.cc_recipients.length > 0) {
                const ccP = document.createElement('p');
                ccP.className = 'email-recipients-cc';
                ccP.textContent = `CC: ${email.cc_recipients.join(', ')}`;
                emailDiv.appendChild(ccP);
            }

            const previewP = document.createElement('p');
            previewP.className = 'email-preview';
            previewP.textContent = email.body_preview || 'No preview available.';
            emailDiv.appendChild(previewP);

            if (email.timestamp) {
                const timestampP = document.createElement('p');
                timestampP.className = 'email-timestamp';
                timestampP.textContent = new Date(email.timestamp).toLocaleString(undefined, {
                    year: 'numeric', month: 'short', day: 'numeric',
                    hour: 'numeric', minute: '2-digit', hour12: true
                });
                emailDiv.appendChild(timestampP);
            }

            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'email-actions';

            const replyBtn = document.createElement('button');
            replyBtn.className = 'reply-to-email-btn';
            replyBtn.textContent = 'Reply';
            replyBtn.dataset.emailId = email.email_id;
            replyBtn.dataset.subject = threadData.subject || 'No Subject';
            replyBtn.dataset.sender = email.sender_name || '';
            replyBtn.dataset.toRecipients = email.to_recipients ? email.to_recipients.join(',') : (email.sender_name || '');
            replyBtn.dataset.ccRecipients = email.cc_recipients ? email.cc_recipients.join(',') : '';
            actionsDiv.appendChild(replyBtn);

            const replyAllBtn = document.createElement('button');
            replyAllBtn.className = 'reply-all-to-email-btn';
            replyAllBtn.textContent = 'Reply All';
            replyAllBtn.dataset.emailId = email.email_id;
            replyAllBtn.dataset.subject = threadData.subject || 'No Subject';
            replyAllBtn.dataset.sender = email.sender_name || '';
            const allRecipients = [];
            if (email.sender_name) allRecipients.push(email.sender_name);
            if (email.to_recipients) allRecipients.push(...email.to_recipients);
            if (email.cc_recipients) allRecipients.push(...email.cc_recipients);
            const uniqueRecipients = [...new Set(allRecipients)];
            replyAllBtn.dataset.allRecipients = uniqueRecipients.join(',');
            actionsDiv.appendChild(replyAllBtn);

            const forwardBtn = document.createElement('button');
            forwardBtn.className = 'forward-email-btn';
            forwardBtn.textContent = 'Forward';
            forwardBtn.dataset.emailId = email.email_id;
            forwardBtn.dataset.subject = threadData.subject || 'No Subject';
            forwardBtn.dataset.originalSender = email.sender_name || 'Unknown Sender';
            forwardBtn.dataset.originalDate = email.timestamp ? new Date(email.timestamp).toLocaleString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            }) : 'Unknown Date';
            forwardBtn.dataset.originalBody = email.body_preview || 'No preview available.';
            actionsDiv.appendChild(forwardBtn);

            emailDiv.appendChild(actionsDiv);

            // Display post status and controls
            const statusDiv = document.createElement('div');
            statusDiv.className = 'email-status-container';

            const currentStatusSpan = document.createElement('span');
            currentStatusSpan.className = 'current-post-status';
            currentStatusSpan.textContent = `Status: ${email.status || 'unread'}`;
            statusDiv.appendChild(currentStatusSpan);

            const statusSelect = document.createElement('select');
            statusSelect.className = 'post-status-select';
            statusSelect.dataset.emailId = email.email_id;
            // currentUserId will be added to dataset in app.js event listener if needed, or passed to setPostStatus directly

            const statuses = ['read', 'follow-up', 'important-info', 'unread']; // 'unread' can be a way to reset or explicit state
            statuses.forEach(statusValue => {
                const option = document.createElement('option');
                option.value = statusValue;
                option.textContent = statusValue.charAt(0).toUpperCase() + statusValue.slice(1);
                if (statusValue === (email.status || 'unread')) {
                    option.selected = true;
                }
                statusSelect.appendChild(option);
            });
            statusDiv.appendChild(statusSelect);
            emailDiv.appendChild(statusDiv);


            if (email.attachments && email.attachments.length > 0) {
                const attachmentsListDiv = document.createElement('div');
                attachmentsListDiv.className = 'email-attachments-list';
                const heading = document.createElement('h4');
                heading.textContent = 'Attachments:';
                attachmentsListDiv.appendChild(heading);
                email.attachments.forEach(attachment => {
                    const link = document.createElement('a');
                    link.href = attachment.url || (attachment.file_id ? `/api/v1/download_attachment.php?file_id=${attachment.file_id}` : '#');
                    link.textContent = attachment.filename;
                    if (attachment.url || attachment.direct_url) {
                        link.setAttribute('download', attachment.filename);
                    }
                    link.target = '_blank';
                    attachmentsListDiv.appendChild(link);
                });
                emailDiv.appendChild(attachmentsListDiv);
            }
            emailsDiv.appendChild(emailDiv);
        });
    } else {
        const noEmailsP = document.createElement('p');
        noEmailsP.textContent = 'No emails in this thread yet.';
        emailsDiv.appendChild(noEmailsP);
    }
    threadDiv.appendChild(emailsDiv);
    return threadDiv;
};
