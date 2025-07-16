/**
 * Renders an individual email as a "post" card.
 * @param {Object} email - The email data object.
 * @param {string} threadSubject - The subject of the parent thread.
 * @param {number} currentUserId - The ID of the current user.
 * @param {boolean} isFirstInThread - True if this is the first email in the thread.
 * @returns {string} HTML string for the email post.
 */
window.renderEmailAsPost = function(email, threadSubject, currentUserId, isFirstInThread) {
    const emailStatus = email.status || 'unread';
    const statusClass = `email-${emailStatus}`;
    const firstPostClass = isFirstInThread ? 'is-first-post' : 'is-reply-post';

    // Format timestamp (simplified)
    const timestamp = new Date(email.timestamp).toLocaleString([], {
        month: 'short', day: 'numeric', year: (new Date(email.timestamp).getFullYear() !== new Date().getFullYear()) ? 'numeric' : undefined,
        hour: 'numeric', minute: '2-digit', hour12: true
    });

    // Simplified recipient display: For "Sender ▸ Recipient", show first recipient or "Group" if applicable
    let recipientDisplay = email.recipients && email.recipients.length > 0 ? email.recipients[0].name : 'Recipients';
    if (email.group_id && email.group_name) {
        recipientDisplay = email.group_name;
    } else if (email.recipients && email.recipients.length > 1) {
        recipientDisplay = `${email.recipients[0].name} + ${email.recipients.length - 1} more`;
    }

    // Status labels and indicators
    const statusLabels = {
        'read': 'Read',
        'unread': 'Unread',
        'follow-up': 'Follow-up',
        'important-info': 'Important',
        'sent': 'Sent',
        'default': 'Set Status'
    };
    const currentStatusLabel = statusLabels[emailStatus] || 'Set Status';

    // Create a string for all recipients for "Reply All"
    const allRecipientsForReplyAll = [email.sender_email, ...(email.recipients || []).map(r => r.email)].join(',');

    // Status indicator based on email status
    const statusIndicator = `<span class="mail-status-indicator ${emailStatus}"></span>`;

    return `
        <div class="post-card ${statusClass} ${firstPostClass}" data-email-id="${email.email_id}">
            <div class="post-header">
                <div class="post-avatar">
                    ${email.sender_avatar_url ? 
                        `<img src="${email.sender_avatar_url}" alt="${email.sender_name}" />` : 
                        `<span>${email.sender_name ? email.sender_name.charAt(0).toUpperCase() : 'S'}</span>`
                    }
                </div>
                <div class="post-author-meta">
                    <div class="post-author-line">
                        ${statusIndicator}
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
                </div>
            </div>

            <div class="post-content">
                ${isFirstInThread && threadSubject ? `<h4 class="post-content-subject">${threadSubject}</h4>` : ''}
                ${email.body_html || `<p>${email.body_preview || email.body_text || 'No content'}</p>`}
            </div>

            <div class="post-footer">
                <div class="post-actions">
                    <button class="action-btn reply-to-email-btn" data-email-id="${email.email_id}" data-subject="${email.subject_for_reply || threadSubject}" data-sender="${email.sender_email}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span>Reply</span>
                    </button>
                    <button class="action-btn reply-all-to-email-btn" data-email-id="${email.email_id}" data-subject="${email.subject_for_reply || threadSubject}" data-sender="${email.sender_email}" data-all-recipients="${allRecipientsForReplyAll}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            <path d="M13 8H7l4-4"></path>
                            <path d="M13 16H7l4 4"></path>
                        </svg>
                        <span>Reply All</span>
                    </button>
                    <button class="action-btn forward-email-btn" data-email-id="${email.email_id}" data-subject="${email.subject_for_reply || threadSubject}" data-original-sender="${email.sender_name}" data-original-date="${timestamp}" data-original-body="${email.body_preview || email.body_html}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 17l3 3 3-3"></path>
                            <path d="M12 12l9-9"></path>
                            <path d="M15 3h6v6"></path>
                        </svg>
                        <span>Forward</span>
                    </button>
                </div>
                <div class="post-status-selector-container email-status-container">
                     <span class="current-post-status" style="display:none;">Status: ${emailStatus}</span>
                     <select class="post-status-select minimalist-select" data-email-id="${email.email_id}">
                        <option value="" ${emailStatus === 'default' ? 'selected' : ''} disabled>${currentStatusLabel}</option>
                        <option value="read" ${emailStatus === 'read' ? 'selected' : ''}>Read</option>
                        <option value="unread" ${emailStatus === 'unread' ? 'selected' : ''}>Unread</option>
                        <option value="follow-up" ${emailStatus === 'follow-up' ? 'selected' : ''}>Follow-up</option>
                        <option value="important-info" ${emailStatus === 'important-info' ? 'selected' : ''}>Important</option>
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

    // Calculate unread count
    const unreadCount = threadData.emails ? threadData.emails.filter(email => email.status === 'unread').length : 0;
    
    // Thread header for collapsible functionality
    const threadHeader = document.createElement('div');
    threadHeader.className = 'thread-header';
    
    const threadToggle = document.createElement('button');
    threadToggle.className = 'thread-toggle';
    threadToggle.innerHTML = '▶';
    threadToggle.setAttribute('aria-label', 'Toggle thread');
    
    const threadInfo = document.createElement('div');
    threadInfo.className = 'thread-info';
    
    const threadSubjectEl = document.createElement('div');
    threadSubjectEl.className = 'thread-subject';
    threadSubjectEl.textContent = threadSubject;
    
    const threadMeta = document.createElement('div');
    threadMeta.className = 'thread-meta';
    
    // Participants
    const participantsEl = document.createElement('div');
    participantsEl.className = 'thread-participants';
    
    if (threadData.participants && threadData.participants.length > 0) {
        threadData.participants.slice(0, 3).forEach(participant => {
            const avatar = document.createElement('div');
            avatar.className = 'participant-avatar';
            if (participant.avatar_url) {
                avatar.innerHTML = `<img src="${participant.avatar_url}" alt="${participant.name}" />`;
            } else {
                avatar.textContent = participant.name ? participant.name.charAt(0).toUpperCase() : 'U';
            }
            participantsEl.appendChild(avatar);
        });
        
        if (threadData.participants.length > 3) {
            const moreCount = document.createElement('span');
            moreCount.textContent = `+${threadData.participants.length - 3}`;
            participantsEl.appendChild(moreCount);
        }
    }
    
    // Last activity time
    const lastActivityEl = document.createElement('span');
    lastActivityEl.className = 'thread-last-activity';
    lastActivityEl.textContent = formatRelativeTime(threadData.last_reply_time);
    
    threadMeta.appendChild(participantsEl);
    threadMeta.appendChild(lastActivityEl);
    
    threadInfo.appendChild(threadSubjectEl);
    threadInfo.appendChild(threadMeta);
    
    const threadActions = document.createElement('div');
    threadActions.className = 'thread-actions';
    
    // Unread count badge
    if (unreadCount > 0) {
        const unreadBadge = document.createElement('span');
        unreadBadge.className = 'thread-unread-count';
        unreadBadge.textContent = unreadCount;
        threadActions.appendChild(unreadBadge);
    }
    
    threadHeader.appendChild(threadToggle);
    threadHeader.appendChild(threadInfo);
    threadHeader.appendChild(threadActions);
    
    // Thread posts container
    const postsContainer = document.createElement('div');
    postsContainer.className = 'thread-posts collapsed';

    if (threadData.emails && threadData.emails.length > 0) {
        threadData.emails.forEach((email, index) => {
            // Pass threadSubject to be potentially displayed in the first post's content
            const emailPostHTML = window.renderEmailAsPost(email, threadSubject, currentUserId, index === 0);
            postsContainer.innerHTML += emailPostHTML; // Append HTML string
        });
    } else {
        postsContainer.innerHTML = '<p class="no-emails-in-thread">No messages in this conversation.</p>';
    }

    threadContainer.appendChild(threadHeader);
    threadContainer.appendChild(postsContainer);
    
    // Add click handler for thread toggle
    threadHeader.addEventListener('click', () => {
        const isExpanded = !postsContainer.classList.contains('collapsed');
        
        if (isExpanded) {
            postsContainer.classList.add('collapsed');
            threadToggle.innerHTML = '▶';
            threadToggle.classList.remove('expanded');
        } else {
            postsContainer.classList.remove('collapsed');
            threadToggle.innerHTML = '▼';
            threadToggle.classList.add('expanded');
        }
    });
    
    return threadContainer;
};

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
        const allThreads = Array.from(feedContainer.querySelectorAll('.thread'));
        const visibleCount = Math.round(allThreads.length * (1 - position));

        allThreads.forEach((thread, index) => {
            thread.style.display = index < visibleCount ? 'block' : 'none';
        });
    }
});
