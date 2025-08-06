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

    // User status for this email
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
                ${email.attachments && email.attachments.length > 0 ? `
                    <div class="email-attachments">
                        <h5>Attachments (${email.attachments.length})</h5>
                        <div class="attachment-list">
                            ${email.attachments.map(attachment => `
                                <div class="attachment-item">
                                    <span class="attachment-icon">📎</span>
                                    <span class="attachment-name">${attachment.filename}</span>
                                    <span class="attachment-size">${formatFileSize(attachment.filesize_bytes)}</span>
                                    <button class="attachment-download-btn" onclick="downloadAttachment(${attachment.id}, '${attachment.filename}')">
                                        Download
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
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

/**
 * Formats a file size in bytes to a human-readable string
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Downloads an attachment
 */
function downloadAttachment(attachmentId, filename) {
    // Create a temporary link to download the attachment
    const link = document.createElement('a');
    link.href = `api/v1/download_attachment.php?id=${attachmentId}`;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Formats a timestamp into a relative time string (e.g., "2 hours ago")
 */
function formatRelativeTime(timestamp) {
    const now = new Date();
    const date = new Date(timestamp);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    
    return date.toLocaleDateString();
}

document.addEventListener('DOMContentLoaded', () => {
    // Timeline Interaction
    const timelineHandle = document.getElementById('timeline-handle');
    const timelineBar = document.getElementById('timeline-bar');
    const feedContainer = document.getElementById('feed-container');

    if (timelineHandle && timelineBar) {
        let isDragging = false;

        timelineHandle.addEventListener('mousedown', (e) => {
            isDragging = true;
            timelineHandle.style.cursor = 'ns-resize';
            document.body.style.cursor = 'ns-resize'; // Change cursor for the whole page
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                timelineHandle.style.cursor = 'ns-resize';
                document.body.style.cursor = 'default';
                // Reload feed based on the new handle position
                const handlePosition = parseFloat(timelineHandle.style.top) / 100;
                filterFeedByTimeline(handlePosition);
            }
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const timelineRect = timelineBar.getBoundingClientRect();
            let newY = e.clientY - timelineRect.top;

            // Constrain the handle within the timeline bar
            if (newY < 0) newY = 0;
            if (newY > timelineRect.height) newY = timelineRect.height;

            timelineHandle.style.top = `${newY}px`;
        });
    }

    function filterFeedByTimeline(position) {
        // Get all threads
        const allThreads = Array.from(feedContainer.querySelectorAll('.thread'));
        
        if (allThreads.length === 0) return;
        
        // Get thread dates for filtering
        const threadDates = allThreads.map(thread => {
            const threadId = thread.dataset.threadId;
            // Try to find the timestamp in the thread's post timestamps
            const timeElements = thread.querySelectorAll('.post-timestamp');
            if (timeElements.length > 0) {
                // Use the first timestamp we find
                const timeText = timeElements[0].textContent;
                // Parse the timestamp - this is a simplified approach
                const date = new Date(timeText);
                return { thread, date: date.getTime() };
            }
            // Fallback to current date if no timestamp found
            return { thread, date: Date.now() };
        });
        
        // Sort by date (newest first)
        threadDates.sort((a, b) => b.date - a.date);
        
        // Calculate how many threads to show based on position
        // position 0 = oldest only, position 1 = all threads
        const visibleCount = Math.max(1, Math.round(threadDates.length * (1 - position)));
        
        // Show/hide threads
        threadDates.forEach((item, index) => {
            item.thread.style.display = index < visibleCount ? 'block' : 'none';
        });
    }
});
