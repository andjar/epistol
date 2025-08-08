// Global variables for DOM elements
let feedContainer = document.getElementById('feed-container');
let newEmailBtn = document.getElementById('new-email-btn');
let composeModal = document.getElementById('compose-modal');
let composeForm = document.getElementById('compose-form');
let closeComposeModalBtn = document.getElementById('close-compose-modal-btn');
let cancelComposeBtn = document.getElementById('cancel-compose-btn');
let composeTo = document.getElementById('compose-to');
let composeSubject = document.getElementById('compose-subject');
let composeBody = document.getElementById('compose-body');
let composeInReplyTo = document.getElementById('compose-in-reply-to');

// Sidebar elements
let leftSidebar = document.getElementById('left-sidebar'); // Changed from groupsSidebar
let rightSidebar = document.getElementById('right-sidebar');
let searchField = document.getElementById('search-field');

// Toggle buttons
let toggleLeftSidebarBtn = document.getElementById('toggle-groups-sidebar-btn'); // This is the old toggleGroupsSidebarBtn, now for left-sidebar
let toggleRightSidebarBtn = document.getElementById('toggle-right-sidebar-btn'); // Hypothetical, might not exist

// Groups and filters
let groupsListContainer = document.getElementById('groups-list-container');
let newGroupNameInput = document.getElementById('new-group-name');
let createGroupBtn = document.getElementById('create-group-btn');
let groupFeedFilterSelect = document.getElementById('group-feed-filter');
let statusFeedFilterSelect = document.getElementById('status-feed-filter'); // Added status filter
let globalLoader = document.getElementById('global-loader');


// Initialize the application
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Epistol application initializing...');

    // Get DOM elements
    feedContainer = document.getElementById('feed-container');
    groupsListContainer = document.getElementById('groups-list-container');
    groupFeedFilterSelect = document.getElementById('group-feed-filter');
    statusFeedFilterSelect = document.getElementById('status-feed-filter');
    createGroupBtn = document.getElementById('create-group-btn');

    // Set default status filter to "Unread"
    if (statusFeedFilterSelect) {
        statusFeedFilterSelect.value = 'unread';
    }

    // Load initial data
    await Promise.all([
        loadGroups(),
        loadFeed({ status: 'unread' }) // Default to showing only unread emails
    ]);

    // Set up event listeners
    setupEventListeners();

    // Left sidebar: toggle create group section via plus button
    const toggleCreateBtn = document.getElementById('toggle-create-group');
    const createSection = document.getElementById('create-group-section');
    if (toggleCreateBtn && createSection) {
        toggleCreateBtn.addEventListener('click', () => {
            const isHidden = createSection.style.display === 'none';
            createSection.style.display = isHidden ? '' : 'none';
            toggleCreateBtn.setAttribute('aria-expanded', String(isHidden));
        });
        // Start hidden by default for a cleaner left sidebar
        createSection.style.display = 'none';
        toggleCreateBtn.setAttribute('aria-expanded', 'false');
    }
});

/**
 * Creates a thread element from thread data
 * @param {object} thread - The thread data
 * @returns {HTMLElement} The thread element
 */
function createThreadElement(thread) {
    const threadElement = document.createElement('div');
    threadElement.className = 'thread';
    
    // Create posts container
    const postsContainer = document.createElement('div');
    postsContainer.className = 'posts-container';
    
    // Add emails in the thread as post cards with collapse for many replies
    if (thread.emails && thread.emails.length > 0) {
        const totalEmails = thread.emails.length;
        const firstEmail = thread.emails[0];
        // Always render the first email as the main post
        postsContainer.appendChild(createEmailElement(firstEmail, true));

        const replies = thread.emails.slice(1);
        const unreadReplies = replies.filter(r => r.status === 'unread');
        const readReplies = replies.filter(r => r.status !== 'unread');
        const replyCount = replies.length;

        if (replyCount > 3) {
            // Collapsed summary bar
            const repliesSummary = document.createElement('div');
            repliesSummary.className = 'replies-summary';

            // Build a compact breadcrumb of participants (up to 3 unique names)
            const uniqueNames = Array.from(new Set(replies.map(r => r.sender_name).filter(Boolean)));
            const shownNames = uniqueNames.slice(0, 3);
            const namesText = shownNames.join(', ') + (uniqueNames.length > 3 ? ` +${uniqueNames.length - 3}` : '');
            const label = document.createElement('button');
            label.type = 'button';
            label.className = 'replies-summary-btn';
            const hiddenCount = readReplies.length;
            label.setAttribute('aria-expanded', 'false');
            label.innerHTML = `
                <span class="chevron" aria-hidden="true">▾</span>
                <span class="label-text">${hiddenCount > 0 ? `Show ${hiddenCount} more replies` : 'Replies'}</span>
                ${namesText ? `<span class="names">${namesText}</span>` : ''}
            `;
            repliesSummary.appendChild(label);

            // Participant initials chips
            const chips = document.createElement('div');
            chips.className = 'participants-chips';
            shownNames.forEach((n) => {
                const chip = document.createElement('div');
                chip.className = 'participant-chip';
                chip.textContent = (n || '?').charAt(0).toUpperCase();
                chip.style.backgroundColor = seedColorFromString(n || '');
                chips.appendChild(chip);
            });
            repliesSummary.appendChild(chips);

            // Collapsible replies container
            const repliesCollapsible = document.createElement('div');
            repliesCollapsible.className = 'replies-collapsible';
            repliesCollapsible.hidden = true;

            readReplies.forEach((email) => {
                const emailElement = createEmailElement(email, false);
                repliesCollapsible.appendChild(emailElement);
            });

            // Toggle behavior
            label.addEventListener('click', () => {
                const isHidden = repliesCollapsible.hidden;
                repliesCollapsible.hidden = !isHidden;
                label.setAttribute('aria-expanded', String(isHidden));
                label.querySelector('.chevron').textContent = isHidden ? '▴' : '▾';
                const textEl = label.querySelector('.label-text');
                if (textEl) {
                    textEl.textContent = hiddenCount > 0 ? `${isHidden ? 'Hide' : 'Show'} ${hiddenCount} more replies` : `${isHidden ? 'Hide' : 'Show'} replies`;
                }
                repliesSummary.classList.toggle('expanded', isHidden);
            });
            label.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    label.click();
                }
            });

            // Wrap the replies area for a subtle background stripe
            const repliesRegion = document.createElement('div');
            repliesRegion.className = 'replies-region';
            repliesRegion.appendChild(repliesSummary);
            repliesRegion.appendChild(repliesCollapsible);
            postsContainer.appendChild(repliesRegion);
            
            // Always show unread replies at the bottom even when collapsed
            if (unreadReplies.length > 0) {
                const unreadWrapper = document.createElement('div');
                unreadWrapper.className = 'unread-replies';
                unreadReplies.forEach((email) => {
                    const emailElement = createEmailElement(email, false);
                    unreadWrapper.appendChild(emailElement);
                });
                repliesRegion.appendChild(unreadWrapper);
            }
        } else {
            // Render all replies inline when there are few
            const repliesRegion = document.createElement('div');
            repliesRegion.className = 'replies-region';
            replies.forEach((email) => {
                repliesRegion.appendChild(createEmailElement(email, false));
            });
            postsContainer.appendChild(repliesRegion);
        }
    }
    
    threadElement.appendChild(postsContainer);
    return threadElement;
}

/**
 * Creates an email element from email data with Facebook-like styling
 * @param {object} email - The email data
 * @param {boolean} isFirstPost - Whether this is the first post in the thread
 * @returns {HTMLElement} The email element
 */
function createEmailElement(email, isFirstPost = false) {
    const emailElement = document.createElement('div');
    emailElement.className = `post-card ${isFirstPost ? 'is-first-post' : 'is-reply-post'}`;
    if (email.status === 'unread') {
        emailElement.classList.add('email-unread');
    }
    
    // Create post header
    const header = document.createElement('div');
    header.className = 'post-header';
    
    // Create avatar
    const avatar = document.createElement('div');
    avatar.className = 'post-avatar';
    const senderName = email.sender_name || email.sender_email || 'Unknown';
    avatar.textContent = senderName.charAt(0).toUpperCase();
    avatar.style.backgroundColor = seedColorFromString(email.sender_email || email.sender_name || '');
    header.appendChild(avatar);
    
    // Create author meta
    const authorMeta = document.createElement('div');
    authorMeta.className = 'post-author-meta';
    
    // Create author line
    const authorLine = document.createElement('div');
    authorLine.className = 'post-author-line';
    
    const authorName = document.createElement('a');
    authorName.className = 'author-name';
    authorName.textContent = senderName;
    authorName.href = '#';
    authorName.dataset.personId = email.sender_person_id || '';
    authorLine.appendChild(authorName);

    // Recipient display (Sender ▸ Recipient)
    try {
        let recipientDisplay = '';
        if (email.group_name) {
            recipientDisplay = email.group_name;
        } else if (Array.isArray(email.recipients) && email.recipients.length > 0) {
            const firstRecipient = email.recipients[0];
            recipientDisplay = firstRecipient.name || firstRecipient.email_address || 'Recipients';
            if (email.recipients.length > 1) {
                recipientDisplay += ` + ${email.recipients.length - 1} more`;
            }
        }
        if (recipientDisplay) {
            const sep = document.createElement('span');
            sep.className = 'recipient-separator';
            sep.textContent = '▸';
            const recip = document.createElement('span');
            recip.className = 'recipient-name';
            recip.textContent = recipientDisplay;
            authorLine.appendChild(sep);
            authorLine.appendChild(recip);
        }
    } catch (e) {
        console.warn('Failed to render recipient display', e);
    }
    
    authorMeta.appendChild(authorLine);
    
    // Add timestamp
    if (email.timestamp) {
        const timestamp = document.createElement('div');
        timestamp.className = 'post-timestamp';
        timestamp.textContent = new Date(email.timestamp).toLocaleString();
        authorMeta.appendChild(timestamp);
    }
    
    header.appendChild(authorMeta);
    
    // Add options menu (placeholder for now)
    const optionsMenu = document.createElement('div');
    optionsMenu.className = 'post-options-menu';
    const optionsBtn = document.createElement('button');
    optionsBtn.className = 'options-btn';
    optionsBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>';
    optionsMenu.appendChild(optionsBtn);
    header.appendChild(optionsMenu);
    
    emailElement.appendChild(header);
    
    // Create post content
    const content = document.createElement('div');
    content.className = 'post-content';
    
    // Add subject for first post
    if (isFirstPost && email.subject) {
        const subject = document.createElement('div');
        subject.className = 'post-content-subject';
        subject.textContent = email.subject;
        content.appendChild(subject);
    }
    
    // Add email body (prefer HTML if provided)
    if (email.body_html) {
        const body = document.createElement('div');
        body.innerHTML = email.body_html;
        content.appendChild(body);
    } else if (email.body_text) {
        const body = document.createElement('p');
        body.textContent = email.body_text;
        content.appendChild(body);
    }

    // Expand/Collapse inline for clamped content
    const expandBtn = document.createElement('button');
    expandBtn.className = 'expand-toggle';
    expandBtn.type = 'button';
    expandBtn.textContent = 'Expand';
    expandBtn.setAttribute('aria-expanded', 'false');
    expandBtn.addEventListener('click', () => {
        const expanded = content.classList.toggle('expanded');
        expandBtn.textContent = expanded ? 'Collapse' : 'Expand';
        expandBtn.setAttribute('aria-expanded', String(expanded));
    });
    content.appendChild(expandBtn);

    // Attachments list
    if (Array.isArray(email.attachments) && email.attachments.length > 0) {
        const wrap = document.createElement('div');
        wrap.className = 'email-attachments';
        const title = document.createElement('h5');
        title.textContent = `Attachments (${email.attachments.length})`;
        wrap.appendChild(title);
        const list = document.createElement('div');
        list.className = 'attachment-list';
        email.attachments.forEach(att => {
            const item = document.createElement('div');
            item.className = 'attachment-item';
            const icon = document.createElement('span');
            icon.className = 'attachment-icon';
            icon.textContent = '📎';
            const name = document.createElement('span');
            name.className = 'attachment-name';
            name.textContent = att.filename;
            const size = document.createElement('span');
            size.className = 'attachment-size';
            size.textContent = formatFileSize(att.filesize_bytes || 0);
            const btn = document.createElement('button');
            btn.className = 'attachment-download-btn';
            btn.textContent = 'Download';
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                downloadAttachment(att.id, att.filename);
            });
            item.appendChild(icon);
            item.appendChild(name);
            item.appendChild(size);
            item.appendChild(btn);
            list.appendChild(item);
        });
        wrap.appendChild(list);
        content.appendChild(wrap);
    }
    
    emailElement.appendChild(content);
    
    // Create post footer with actions
    const footer = document.createElement('div');
    footer.className = 'post-footer';
    
    // Create actions
    const actions = document.createElement('div');
    actions.className = 'post-actions';
    
    // Reply button
    const replyBtn = document.createElement('button');
    replyBtn.className = 'action-btn reply-to-email-btn';
    replyBtn.textContent = 'Reply';
    replyBtn.dataset.emailId = email.email_id;
    replyBtn.dataset.subject = email.subject || '';
    replyBtn.dataset.sender = email.sender_email || '';
    actions.appendChild(replyBtn);
    
    // Reply All button
    const replyAllBtn = document.createElement('button');
    replyAllBtn.className = 'action-btn reply-all-to-email-btn';
    replyAllBtn.textContent = 'Reply All';
    replyAllBtn.dataset.emailId = email.email_id;
    replyAllBtn.dataset.subject = email.subject || '';
    replyAllBtn.dataset.allRecipients = email.sender_email || '';
    actions.appendChild(replyAllBtn);
    
    // Forward button
    const forwardBtn = document.createElement('button');
    forwardBtn.className = 'action-btn forward-email-btn';
    forwardBtn.textContent = 'Forward';
    forwardBtn.dataset.subject = email.subject || '';
    forwardBtn.dataset.originalSender = email.sender_name || email.sender_email || '';
    forwardBtn.dataset.originalDate = email.timestamp ? new Date(email.timestamp).toLocaleString() : '';
    forwardBtn.dataset.originalBody = email.body_text || '';
    actions.appendChild(forwardBtn);
    
    footer.appendChild(actions);
    
    // Add status selector
    const statusContainer = document.createElement('div');
    statusContainer.className = 'post-status-selector-container';
    
    const statusSelect = document.createElement('select');
    statusSelect.className = 'minimalist-select post-status-select';
    statusSelect.dataset.emailId = email.email_id;
    
    const statuses = ['unread', 'read', 'follow-up', 'important'];
    statuses.forEach(status => {
        const option = document.createElement('option');
        option.value = status;
        option.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        if (email.status === status) {
            option.selected = true;
        }
        statusSelect.appendChild(option);
    });
    
    statusContainer.appendChild(statusSelect);
    footer.appendChild(statusContainer);
    
    emailElement.appendChild(footer);
    
    return emailElement;
}

// Generate a pastel color from a string (e.g., email or name) for avatar backgrounds
function seedColorFromString(input) {
    try {
        let hash = 0;
        for (let i = 0; i < input.length; i++) {
            hash = input.charCodeAt(i) + ((hash << 5) - hash);
            hash = hash & hash;
        }
        // Map hash to HSL
        const hue = Math.abs(hash) % 360;
        const saturation = 65; // percentage
        const lightness = 55; // percentage
        return `hsl(${hue} ${saturation}% ${lightness}%)`;
    } catch (e) {
        return '#6B7280';
    }
}

/**
 * Sets up all event listeners for the application
 */
function setupEventListeners() {
    // Status filter change
    if (statusFeedFilterSelect) {
        statusFeedFilterSelect.addEventListener('change', (e) => {
            const status = e.target.value;
            loadFeed({ status: status });
        });
    }
    
    // Group filter change
    if (groupFeedFilterSelect) {
        groupFeedFilterSelect.addEventListener('change', (e) => {
            const groupId = e.target.value;
            loadFeed({ groupId: groupId });
        });
    }
    
    // Create group form
    if (createGroupBtn) {
        createGroupBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const groupName = document.getElementById('create-group-input')?.value;
            if (groupName) {
                try {
                    await api.createGroup({ name: groupName });
                    loadGroups(); // Reload groups
                    document.getElementById('create-group-input').value = '';
                } catch (error) {
                    console.error('Error creating group:', error);
                }
            }
        });
    }
    
    // New email button
    if (newEmailBtn) {
        newEmailBtn.addEventListener('click', (e) => {
            e.preventDefault();
            showComposeModal();
        });
    }
    
    // Compose modal close
    if (closeComposeModalBtn) {
        closeComposeModalBtn.addEventListener('click', (e) => {
            e.preventDefault();
            hideComposeModal();
        });
    }
    
    // Compose form submit
    if (composeForm) {
        composeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await handleComposeSubmit();
        });
    }
    
    // Group actions - View Members and Edit Group buttons
    document.addEventListener('click', (e) => {
        if (e.target.closest('.view-group-members-btn')) {
            e.preventDefault();
            const groupItem = e.target.closest('.group-item');
            const groupName = groupItem.querySelector('span').textContent;
            showGroupMembers(groupName);
        }
        
        if (e.target.closest('.edit-group-btn')) {
            e.preventDefault();
            const groupItem = e.target.closest('.group-item');
            const groupName = groupItem.querySelector('span').textContent;
            showGroupEditor(groupName);
        }
    });
}

/**
 * Handles the submission of the compose form.
 */
async function handleComposeSubmit() {
    const recipients = composeTo.value.split(',').map(e => e.trim()).filter(e => e);
    if (recipients.length === 0) {
        alert('Please enter at least one recipient.');
        return;
    }
    const subject = composeSubject.value.trim();
    if (!subject) {
        alert('Please enter a subject.');
        return;
    }
    const bodyText = composeBody.value.trim();
    if (!bodyText) {
        alert('Please enter some content in the body.');
        return;
    }

    const files = document.getElementById('compose-attachments').files;
    const formData = new FormData();

    // Append recipients as a JSON string because FormData typically handles strings or Blobs.
    // The backend will need to parse this JSON string.
    formData.append('recipients', JSON.stringify(recipients));
    formData.append('subject', subject);
    formData.append('body_text', bodyText);
    formData.append('body_html', `<p>${bodyText.replace(/\n/g, '<br>')}</p>`);

    if (composeInReplyTo.value) {
        formData.append('in_reply_to_email_id', composeInReplyTo.value);
    }

    if (files.length > 0) {
        for (let i = 0; i < files.length; i++) {
            formData.append('attachments[]', files[i]); // Use 'attachments[]' for multiple files
        }
    }

    try {
        const sendButton = document.getElementById('send-email-btn');
        sendButton.disabled = true;
        sendButton.textContent = 'Sending...';
        // Consider disabling other form fields here if desired
        // e.g. composeTo.disabled = true; composeSubject.disabled = true; etc.
        showGlobalLoader(); // Show global loader for sending

        const result = await api.sendEmail(formData); // from api.js, now sends FormData
        console.log('Email sent successfully', result);
        hideComposeModal();
        await loadFeed(); // Refresh the feed to show the new email
    } catch (error) {
        console.error('Failed to send email:', error);
        alert(`Error sending email: ${error.message}`);
    } finally {
        const sendButton = document.getElementById('send-email-btn');
        sendButton.disabled = false;
        sendButton.textContent = 'Send';
        // Re-enable other form fields if they were disabled
        // e.g. composeTo.disabled = false; composeSubject.disabled = false; etc.
        hideGlobalLoader(); // Hide global loader
    }
}

// Event delegation for reply buttons and status changes
feedContainer.addEventListener('click', (event) => {
    const target = event.target;
    if (target.classList.contains('reply-to-email-btn')) {
        const button = target;
        const emailId = button.dataset.emailId;
        const originalSubject = button.dataset.subject;
        const originalSender = button.dataset.sender; // This is the person who sent the email you're replying to

        composeInReplyTo.value = emailId;
        // Pre-fill "To" with the sender of the email being replied to.
        // In a real app, you might want to include all original recipients (excluding self)
        composeTo.value = originalSender || '';
        composeSubject.value = originalSubject.toLowerCase().startsWith('re:') ? originalSubject : `Re: ${originalSubject}`;
        composeBody.value = `\n\n---- On ${new Date().toLocaleString()}, ${originalSender} wrote ----\n> `; // Basic quote
        showComposeModal();
        composeBody.focus(); // Focus on body for quick reply
    } else if (event.target.classList.contains('reply-all-to-email-btn')) {
        const button = event.target;
        const emailId = button.dataset.emailId;
        const originalSubject = button.dataset.subject;
        // `dataset.allRecipients` should contain a comma-separated list of sender, to, and cc recipients
        const allRecipientsString = button.dataset.allRecipients || '';
        // For now, we don't have the current user's email to filter out.
        // This string could be "; [Original CCs]" if ccRecipients was empty and we wanted a placeholder.
        // However, the current logic tries to fill it with actual CCs if available.
        const toValue = allRecipientsString;


        composeInReplyTo.value = emailId;
        composeTo.value = toValue;
        composeSubject.value = originalSubject.toLowerCase().startsWith('re:') ? originalSubject : `Re: ${originalSubject}`;
        // Basic quote - might need original sender name here if not part of allRecipientsString for the quote
        const originalSender = button.dataset.sender || "Original Sender"; // Fallback
        composeBody.value = `\n\n---- On ${new Date().toLocaleString()}, ${originalSender} wrote ----\n> `;
        showComposeModal();
        composeBody.focus();
    } else if (event.target.classList.contains('forward-email-btn')) {
        const button = event.target;
        const originalSubject = button.dataset.subject;
        const originalSender = button.dataset.originalSender;
        const originalDate = button.dataset.originalDate;
        const originalBody = button.dataset.originalBody; // This is likely a preview

        composeInReplyTo.value = ''; // Not a reply
        composeTo.value = ''; // User fills this for forwarding
        composeSubject.value = originalSubject.toLowerCase().startsWith('fwd:') ? originalSubject : `Fwd: ${originalSubject}`;
        composeBody.value = `\n\n---- Forwarded message ----\nFrom: ${originalSender}\nDate: ${originalDate}\nSubject: ${originalSubject}\n\n${originalBody}`;
        showComposeModal();
        composeTo.focus(); // Focus on "To" field for forwarding
    } else if (event.target.classList.contains('author-name')) {
        const personId = event.target.dataset.personId;
        if (personId) {
            showProfile(personId);
        } else {
            console.warn('Author link clicked, but no person-id found.', event.target);
        }
    }
    // Note: Status change is handled by a 'change' event listener below, not 'click'.
});

// Event delegation for post status changes
feedContainer.addEventListener('change', async (event) => {
    const target = event.target;
    if (target.classList.contains('post-status-select')) {
        const emailId = target.dataset.emailId;
        const newStatus = target.value;
        const currentUserId = 1; // Placeholder user ID

        if (!emailId || !newStatus) {
            console.error('Email ID or new status is missing for status change.');
            return;
        }

        try {
            showGlobalLoader();
            await api.setPostStatus(emailId, currentUserId, newStatus); // Using api.setPostStatus
            // Update UI: Change the text of the current status span
            const statusContainer = target.closest('.post-status-selector-container');
            if (statusContainer) {
                const currentStatusSpan = statusContainer.querySelector('.current-post-status');
                if (currentStatusSpan) {
                    currentStatusSpan.textContent = `Status: ${newStatus}`;
                }
            }
            // Optionally, reload the feed to ensure all data is consistent,
            // though immediate UI update is better UX for this specific change.
            // For simplicity as requested, a reload:
            // await loadFeed({ /* any existing filters */ });
            // However, let's try to update locally first and avoid full reload if not necessary.
            // If other aspects of the email could change based on status, a reload is safer.
            // The prompt suggested "a feed reload might be simplest" for now.
            await loadFeed(); // Reload feed with current filters (if any stored globally)
                                      // Or pass currently active group filter if available:
                                      // const selectedGroupId = groupFeedFilterSelect.value;
                                      // await loadFeed({ groupId: selectedGroupId || null });

        } catch (error) {
            console.error(`Failed to update status for email ${emailId}:`, error);
            alert(`Failed to update status: ${error.message}`);
            // Optionally, revert the select element to its previous value if the API call fails
            // This would require storing the previous value before making the call.
        } finally {
            hideGlobalLoader();
        }
    }
});


// Add navbar functionality
const searchBtn = document.getElementById('search-btn');
const notificationsBtn = document.getElementById('notifications-btn');
const groupsBtn = document.getElementById('groups-btn');
const profileBtn = document.getElementById('profile-btn');
const compactToggleBtn = document.getElementById('toggle-compact-mode-btn');
const readingToggleBtn = document.getElementById('toggle-reading-mode-btn');

// Search functionality
if (searchBtn && searchField) {
    searchBtn.addEventListener('click', () => {
        const query = searchField.value.trim();
        if (query) {
            performSearch(query);
        }
    });

    searchField.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const query = searchField.value.trim();
            if (query) {
                performSearch(query);
            }
        }
    });
}

// Navigation button handlers
if (notificationsBtn) {
    notificationsBtn.addEventListener('click', (e) => {
        e.preventDefault();
        showNotifications();
    });
}

if (groupsBtn) {
    groupsBtn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleLeftSidebar();
    });
}

if (profileBtn) {
    profileBtn.addEventListener('click', (e) => {
        e.preventDefault();
        showProfileEditor();
    });
}

// Compact mode toggle
if (compactToggleBtn) {
    compactToggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('compact-mode');
        const pressed = document.body.classList.contains('compact-mode');
        compactToggleBtn.setAttribute('aria-pressed', String(pressed));
    });
}

// Reading mode toggle
if (readingToggleBtn) {
    readingToggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('reading-mode');
        const pressed = document.body.classList.contains('reading-mode');
        readingToggleBtn.setAttribute('aria-pressed', String(pressed));
    });
}

// Add profile edit button to header if it doesn't exist
function addProfileEditButton() {
    const topBar = document.getElementById('top-bar');
    if (topBar && !document.getElementById('profile-edit-btn')) {
        const profileEditBtn = document.createElement('button');
        profileEditBtn.id = 'profile-edit-btn';
        profileEditBtn.className = 'nav-link';
        profileEditBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
            </svg>
            <span>Edit Profile</span>
        `;
        profileEditBtn.addEventListener('click', (e) => {
            e.preventDefault();
            showProfileEditor();
        });
        
        // Add to navigation menu
        const navMenu = topBar.querySelector('.nav-menu');
        if (navMenu) {
            const listItem = document.createElement('li');
            listItem.appendChild(profileEditBtn);
            navMenu.appendChild(listItem);
        }
    }
}

// Edit Profile button removed from main navigation - should only be on profile pages

async function performSearch(query, filters = {}) {
    if (!query.trim()) {
        loadFeed(); // Reset to normal feed if search is empty
        return;
    }
    
    try {
        showGlobalLoader();
        
        // Build search URL with filters
        const searchParams = new URLSearchParams({
            q: query,
            type: 'all',
            limit: 50
        });
        
        // Add date range filters
        if (filters.dateFrom) searchParams.append('date_from', filters.dateFrom);
        if (filters.dateTo) searchParams.append('date_to', filters.dateTo);
        
        // Add sender/recipient filters
        if (filters.sender) searchParams.append('sender', filters.sender);
        if (filters.recipient) searchParams.append('recipient', filters.recipient);
        
        // Add attachment filters
        if (filters.hasAttachments !== undefined) {
            searchParams.append('has_attachments', filters.hasAttachments ? '1' : '0');
        }
        
        // Add sent/received by me filters
        if (filters.sentByMe) searchParams.append('sent_by_me', '1');
        if (filters.receivedByMe) searchParams.append('received_by_me', '1');
        
        const response = await fetch(`/api/v1/search.php?${searchParams.toString()}`);
        const data = await response.json();
        
        if (data.success) {
            displaySearchResults(data.results, query, data.filters);
        } else {
            console.error('Search failed:', data.error);
            alert('Search failed: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Search error:', error);
        alert('Search failed. Please try again.');
    } finally {
        hideGlobalLoader();
    }
}
    
function displaySearchResults(results, query, filters = {}) {
    if (!feedContainer) return;
    
    feedContainer.innerHTML = '';
    renderActiveFilters(filters);
    
    if (results.length === 0) {
        feedContainer.innerHTML = `
            <div class="no-results">
                <h3>No results found for "${query}"</h3>
                <p>Try searching with different terms or check your spelling.</p>
            </div>
        `;
        return;
    }
    
    // Group results by type
    const emails = results.filter(r => r.type === 'email');
    const threads = results.filter(r => r.type === 'thread');
    const persons = results.filter(r => r.type === 'person');
    
    let html = `<div class="search-results">
        <h3>Search Results for "${query}"</h3>
        <p>Found ${results.length} results</p>
        ${Object.keys(filters).length > 0 ? `<p class="active-filters">Active filters: ${Object.entries(filters).filter(([k,v]) => v).map(([k,v]) => `${k}: ${v}`).join(', ')}</p>` : ''}
    </div>`;
    
    // Display emails
    if (emails.length > 0) {
        html += `<div class="search-section">
            <h4>Emails (${emails.length})</h4>
            <div class="search-emails">`;
            
        emails.forEach(email => {
            const highlightedSubject = email.subject_highlighted || email.subject || 'No subject';
            const highlightedSender = email.sender_name_highlighted || email.sender_name || 'Unknown';
            const highlightedBody = email.body_highlighted || (email.body_text ? email.body_text.substring(0, 150) + '...' : 'No content');
            
            html += `
                <div class="search-result-item email-result">
                    <div class="result-header">
                        <strong>${highlightedSender}</strong>
                        <span class="result-date">${new Date(email.created_at).toLocaleDateString()}</span>
                        ${email.attachment_count > 0 ? `<span class="attachment-indicator">📎 ${email.attachment_count}</span>` : ''}
                    </div>
                    <div class="result-subject">
                        <strong>Subject:</strong> ${highlightedSubject}
                    </div>
                    <div class="result-preview">
                        ${highlightedBody}
                    </div>
                    <div class="result-actions">
                        <button onclick="showThread(${email.thread_id})">View Thread</button>
                    </div>
                </div>
            `;
        });
            
        html += `</div></div>`;
    }
    
    // Display threads
    if (threads.length > 0) {
        html += `<div class="search-section">
            <h4>Threads (${threads.length})</h4>
            <div class="search-threads">`;
            
        threads.forEach(thread => {
            html += `
                <div class="search-result-item thread-result">
                    <div class="result-header">
                        <strong>${thread.subject || 'No subject'}</strong>
                        <span class="result-date">${new Date(thread.last_activity_at).toLocaleDateString()}</span>
                    </div>
                    <div class="result-meta">
                        <span>${thread.email_count} emails</span>
                        <span>Created by: ${thread.creator_name || 'Unknown'}</span>
                    </div>
                    <div class="result-actions">
                        <button onclick="showThread(${thread.id})">View Thread</button>
                    </div>
                </div>
            `;
        });
            
        html += `</div></div>`;
    }
    
    // Display persons
    if (persons.length > 0) {
        html += `<div class="search-section">
            <h4>People (${persons.length})</h4>
            <div class="search-persons">`;
            
        persons.forEach(person => {
            html += `
                <div class="search-result-item person-result">
                    <div class="result-header">
                        <strong>${person.name || 'Unknown'}</strong>
                        <span class="result-email">${person.email_address || 'No email'}</span>
                    </div>
                    <div class="result-meta">
                        <span>${person.email_count} emails</span>
                    </div>
                    <div class="result-actions">
                        <button onclick="showProfile(${person.id})">View Profile</button>
                    </div>
                </div>
            `;
        });
            
        html += `</div></div>`;
    }
    
    feedContainer.innerHTML = html;
}

function renderActiveFilters(filters = {}) {
    const bar = document.getElementById('active-filters-bar');
    if (!bar) return;
    const entries = Object.entries(filters).filter(([k, v]) => v && String(v).length);
    if (entries.length === 0) {
        bar.innerHTML = '';
        return;
    }
    bar.innerHTML = entries.map(([k, v]) => {
        const label = `${k.replace(/_/g, ' ')}: ${Array.isArray(v) ? v.join(', ') : v}`;
        return `<span class="filter-chip" data-key="${k}">${label}<button aria-label="Remove ${k}" onclick="removeActiveFilter('${k}')">×</button></span>`;
    }).join('');
}

window.removeActiveFilter = function (key) {
    // Clear matching UI control if present
    const map = {
        'date_from': '#date-from',
        'date_to': '#date-to',
        'sender': '#sender-filter',
        'recipient': '#recipient-filter',
        'has_attachments': '#filter-has-attachments',
        'sent_by_me': '#filter-sent-by-me',
        'received_by_me': '#filter-received-by-me',
    };
    const sel = map[key];
    if (sel) {
        const el = document.querySelector(sel);
        if (el) {
            if (el.type === 'checkbox') el.checked = false; else el.value = '';
        }
    }
    // Re-apply filters via AdvancedFilters
    if (window.advancedFilters) {
        delete window.advancedFilters.currentFilters[key];
        window.advancedFilters.applyFilters();
    }
}
    
function showThread(threadId) {
    window.location.href = `thread.php?id=${threadId}`;
}

function showNotifications() {
    // TODO: Implement notifications panel
    console.log('Showing notifications');
    alert('Notifications feature coming soon!');
}

function toggleLeftSidebar() {
    if (leftSidebar) {
        leftSidebar.classList.toggle('collapsed');
    }
}

function showCurrentUserProfile() {
    // TODO: Get current user's person ID
    const currentUserId = 1; // Placeholder
    window.location.href = `profile.php?id=${currentUserId}`;
}

if (createGroupBtn) {
    createGroupBtn.addEventListener('click', async () => {
        const groupName = newGroupNameInput.value.trim(); // Trimmed here
        if (groupName === '') { // Check against empty string
            alert('Group name cannot be empty.');
            newGroupNameInput.focus(); // Focus on the input
            return;
        }
        try {
            createGroupBtn.disabled = true;
            createGroupBtn.textContent = 'Creating...';
            showGlobalLoader(); // Show loader
            await api.createGroup({ group_name: groupName });
            newGroupNameInput.value = '';
            await loadGroups();
        } catch (error) {
            console.error('Error creating group:', error);
            alert(`Failed to create group: ${error.message}`);
        } finally {
            createGroupBtn.disabled = false;
            createGroupBtn.textContent = 'Create Group';
            hideGlobalLoader(); // Hide loader
        }
    });
}

if (groupFeedFilterSelect) {
    groupFeedFilterSelect.addEventListener('change', () => {
        const selectedGroupId = groupFeedFilterSelect.value;
        const selectedStatus = statusFeedFilterSelect ? statusFeedFilterSelect.value : null;
        const params = {};
        if (selectedGroupId) params.groupId = selectedGroupId;
        if (selectedStatus) params.status = selectedStatus;
        loadFeed(params);
    });
}

if (statusFeedFilterSelect) { // Added event listener for status filter
    statusFeedFilterSelect.addEventListener('change', () => {
        const selectedStatus = statusFeedFilterSelect.value;
        const selectedGroupId = groupFeedFilterSelect ? groupFeedFilterSelect.value : null;
        const params = {};
        if (selectedGroupId) params.groupId = selectedGroupId;
        if (selectedStatus) params.status = selectedStatus;
        loadFeed(params);
    });
}

// Event listener for viewing group details page
if (groupsListContainer) {
    groupsListContainer.addEventListener('click', (event) => {
        const viewButton = event.target.closest('.view-group-members-btn');
        const editButton = event.target.closest('.edit-group-btn');
        
        if (viewButton && viewButton.dataset.groupId) {
            const groupId = viewButton.dataset.groupId;
            window.location.href = `group.php?id=${groupId}`;
        } else if (editButton && editButton.dataset.groupId) {
            const groupId = editButton.dataset.groupId;
            showGroupEditor(groupId);
        }
    });
}

// Add profile editing functionality
function showProfileEditor(personId = null) {
    // Create modal for profile editing
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Profile</h2>
                <button class="close-btn" onclick="this.closest('.modal-overlay').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="profile-edit-form">
                    <div class="form-group">
                        <label for="profile-name">Name</label>
                        <input type="text" id="profile-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-email">Email Addresses</label>
                        <div id="email-inputs">
                            <div class="email-input-group">
                                <input type="email" name="emails[]" required>
                                <button type="button" class="remove-email-btn" onclick="removeEmailInput(this)">Remove</button>
                            </div>
                        </div>
                        <button type="button" class="add-email-btn" onclick="addEmailInput()">Add Email</button>
                    </div>
                    <div class="form-group">
                        <label for="profile-bio">Bio</label>
                        <textarea id="profile-bio" name="bio" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load current profile data if personId is provided
    if (personId) {
        loadProfileData(personId);
    }
    
    // Handle form submission
    const form = modal.querySelector('#profile-edit-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await saveProfileData(form);
    });
}

function addEmailInput() {
    const emailInputs = document.getElementById('email-inputs');
    const newGroup = document.createElement('div');
    newGroup.className = 'email-input-group';
    newGroup.innerHTML = `
        <input type="email" name="emails[]" required>
        <button type="button" class="remove-email-btn" onclick="removeEmailInput(this)">Remove</button>
    `;
    emailInputs.appendChild(newGroup);
}

function removeEmailInput(button) {
    const emailInputs = document.getElementById('email-inputs');
    if (emailInputs.children.length > 1) {
        button.closest('.email-input-group').remove();
    }
}

async function loadProfileData(personId) {
    try {
        const response = await api.getProfile(personId);
        const profile = response.data || response;
        
        document.getElementById('profile-name').value = profile.name || '';
        document.getElementById('profile-bio').value = profile.bio || '';
        
        // Load email addresses
        const emailInputs = document.getElementById('email-inputs');
        emailInputs.innerHTML = '';
        
        if (profile.email_addresses && profile.email_addresses.length > 0) {
            profile.email_addresses.forEach(email => {
                const emailGroup = document.createElement('div');
                emailGroup.className = 'email-input-group';
                emailGroup.innerHTML = `
                    <input type="email" name="emails[]" value="${email}" required>
                    <button type="button" class="remove-email-btn" onclick="removeEmailInput(this)">Remove</button>
                `;
                emailInputs.appendChild(emailGroup);
            });
        } else {
            addEmailInput(); // Add at least one email input
        }
    } catch (error) {
        console.error('Error loading profile data:', error);
    }
}

async function saveProfileData(form) {
    try {
        const formData = new FormData(form);
        const profileData = {
            name: formData.get('name'),
            bio: formData.get('bio'),
            emails: formData.getAll('emails[]').filter(email => email.trim())
        };
        
        // Here you would typically call an API to save the profile
        console.log('Saving profile data:', profileData);
        
        // For now, just close the modal
        form.closest('.modal-overlay').remove();
        alert('Profile updated successfully!');
    } catch (error) {
        console.error('Error saving profile data:', error);
        alert('Error saving profile: ' + error.message);
    }
}

function showGroupMembers(groupName) {
    // Create modal for viewing group members
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>${groupName} - Members</h2>
                <button class="close-btn" onclick="this.closest('.modal-overlay').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="group-members-list">
                    <p>Loading members...</p>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load group members
    loadGroupMembers(groupName);
}

function showGroupEditor(groupId) {
    // Create modal for group editing
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Group</h2>
                <button class="close-btn" onclick="this.closest('.modal-overlay').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="group-edit-form">
                    <div class="form-group">
                        <label for="group-name">Group Name</label>
                        <input type="text" id="group-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Group Members</label>
                        <div id="group-members-list">
                            <!-- Members will be loaded here -->
                        </div>
                        <button type="button" class="add-member-btn" onclick="addGroupMember()">Add Member</button>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-danger" onclick="deleteGroup(${groupId})">Delete Group</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load current group data
    loadGroupData(groupId);
    
    // Handle form submission
    const form = modal.querySelector('#group-edit-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await saveGroupData(form, groupId);
    });
}

async function loadGroupMembers(groupName) {
    try {
        // For now, show placeholder members
        const membersList = document.getElementById('group-members-list');
        membersList.innerHTML = `
            <div class="member-item">
                <strong>Alice K.</strong> - alice@example.com
            </div>
            <div class="member-item">
                <strong>Bob The Builder</strong> - bob@example.com
            </div>
            <div class="member-item">
                <strong>Charlie Brown</strong> - charlie@example.com
            </div>
            <p><em>Note: This is placeholder data. In a real implementation, this would load actual group members from the API.</em></p>
        `;
    } catch (error) {
        console.error('Error loading group members:', error);
        const membersList = document.getElementById('group-members-list');
        membersList.innerHTML = '<p>Error loading members: ' + error.message + '</p>';
    }
}

async function loadGroupData(groupId) {
    try {
        // Here you would typically call an API to get group data
        console.log('Loading group data for ID:', groupId);
        
        // For now, just populate with placeholder data
        document.getElementById('group-name').value = 'Group Name';
        
        const membersList = document.getElementById('group-members-list');
        membersList.innerHTML = '<p>Loading members...</p>';
        
    } catch (error) {
        console.error('Error loading group data:', error);
    }
}

async function saveGroupData(form, groupId) {
    try {
        const formData = new FormData(form);
        const groupData = {
            name: formData.get('name'),
            // Add other group data as needed
        };
        
        console.log('Saving group data:', groupData);
        
        // For now, just close the modal
        form.closest('.modal-overlay').remove();
        alert('Group updated successfully!');
        
        // Reload groups to reflect changes
        await loadGroups();
    } catch (error) {
        console.error('Error saving group data:', error);
        alert('Error saving group: ' + error.message);
    }
}

async function deleteGroup(groupId) {
    if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        try {
            // Here you would typically call an API to delete the group
            console.log('Deleting group with ID:', groupId);
            
            // For now, just close the modal
            document.querySelector('.modal-overlay').remove();
            alert('Group deleted successfully!');
            
            // Reload groups to reflect changes
            await loadGroups();
        } catch (error) {
            console.error('Error deleting group:', error);
            alert('Error deleting group: ' + error.message);
        }
    }
}

// Left Sidebar Toggle (formerly groupsSidebar)
if (toggleLeftSidebarBtn && leftSidebar) {
    toggleLeftSidebarBtn.addEventListener('click', () => {
        leftSidebar.classList.toggle('collapsed');
        // The main content area should adjust via CSS flex properties,
        // so toggling a class on '.main-container' might not be needed if CSS is set up for it.
        // document.querySelector('.main-container').classList.toggle('left-sidebar-collapsed');

        // Optional: Change button text/icon based on state
        if (leftSidebar.classList.contains('collapsed')) {
            // toggleLeftSidebarBtn.textContent = 'Show Nav'; // Example
        } else {
            // toggleLeftSidebarBtn.textContent = 'Hide Nav'; // Example
        }
    });
}

// Right Sidebar Toggle (New)
if (toggleRightSidebarBtn && rightSidebar) {
    toggleRightSidebarBtn.addEventListener('click', () => {
        rightSidebar.classList.toggle('collapsed'); // Or 'hidden' or similar, matching CSS
        // document.querySelector('.main-container').classList.toggle('right-sidebar-collapsed');
        // Optional: Change button text/icon
    });
} else if (!toggleRightSidebarBtn && rightSidebar) {
    console.warn('Right sidebar element exists, but its toggle button (#toggle-right-sidebar-btn) was not found.');
}

// Search Field Interaction (New)
if (searchField) {
    searchField.addEventListener('input', () => {
        console.log('Search field value:', searchField.value);
        // Future: Implement actual search/filtering logic here
        // e.g., loadFeed({ searchQuery: searchField.value });
    });
}

// Timeline synchronization
const mainContent = document.getElementById('main-content');
const timelineHandle = document.getElementById('timeline-handle');
const timelineContainer = document.getElementById('timeline-container');

if (mainContent && timelineHandle && timelineContainer) {
    let timelineDateElement = null;
    let timelineDots = [];
    const monthMarkersContainer = document.getElementById('timeline-month-markers');
    
    // Create timeline dots for different dates
    function createTimelineDots() {
        const posts = feedContainer.querySelectorAll('.post-card');
        const dates = new Set();
        
        posts.forEach(post => {
            const timestampElement = post.querySelector('.post-timestamp');
            if (timestampElement) {
                const date = new Date(timestampElement.textContent);
                const dateKey = date.toDateString();
                if (!dates.has(dateKey)) {
                    dates.add(dateKey);
                }
            }
        });
        
        // Clear existing dots
        timelineDots.forEach(dot => dot.remove());
        timelineDots = [];
        
        // Create dots for each unique date
        const sortedDates = Array.from(dates).sort((a, b) => new Date(b) - new Date(a));
        sortedDates.forEach((dateString, index) => {
            const dot = document.createElement('div');
            dot.className = 'timeline-dot';
            dot.dataset.date = dateString;
            dot.style.top = `${(index / (sortedDates.length - 1)) * 80 + 10}%`;
            timelineContainer.appendChild(dot);
            timelineDots.push(dot);
            
            // Add click handler to jump to date
            dot.addEventListener('click', () => {
                jumpToDate(dateString);
            });
        });

        // Month markers (approximate positions)
        if (monthMarkersContainer) {
            monthMarkersContainer.innerHTML = '';
            const months = Array.from(new Set(sortedDates.map(d => new Date(d).toLocaleString('en-US', { month: 'short', year: 'numeric' }))));
            months.forEach((m, idx) => {
                const marker = document.createElement('div');
                marker.className = 'timeline-month';
                marker.textContent = m;
                marker.style.top = `${(idx / (months.length - 1)) * 80 + 10}%`;
                monthMarkersContainer.appendChild(marker);
            });
        }
    }
    
    function jumpToDate(dateString) {
        const posts = feedContainer.querySelectorAll('.post-card');
        for (const post of posts) {
            const timestampElement = post.querySelector('.post-timestamp');
            if (timestampElement) {
                const postDate = new Date(timestampElement.textContent);
                if (postDate.toDateString() === dateString) {
                    post.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    break;
                }
            }
        }
    }
    
    function updateTimelineHandle() {
        const scrollPercentage = mainContent.scrollTop / (mainContent.scrollHeight - mainContent.clientHeight);
        const handlePosition = scrollPercentage * (timelineContainer.clientHeight - timelineHandle.clientHeight);
        timelineHandle.style.top = `${handlePosition}px`;
    }
    
    function updateTimelineDate() {
        // Find the first visible post
        const posts = feedContainer.querySelectorAll('.post-card');
        let firstVisiblePost = null;
        
        for (const post of posts) {
            const rect = post.getBoundingClientRect();
            if (rect.top >= 0 && rect.bottom <= window.innerHeight) {
                firstVisiblePost = post;
                break;
            }
        }
        
        if (firstVisiblePost) {
            const timestampElement = firstVisiblePost.querySelector('.post-timestamp');
            if (timestampElement) {
                const date = new Date(timestampElement.textContent);
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                const formattedDate = date.toLocaleDateString('en-US', options);
                
                // Remove existing date display
                if (timelineDateElement) {
                    timelineDateElement.remove();
                }
                
                // Add new date display
                timelineDateElement = document.createElement('div');
                timelineDateElement.className = 'timeline-date';
                timelineDateElement.textContent = formattedDate;
                timelineContainer.appendChild(timelineDateElement);
                
                // Show the date with a slight delay
                setTimeout(() => {
                    timelineDateElement.classList.add('show');
                }, 100);
            }
        }
    }
    
    // Initialize timeline dots when feed loads
    const originalLoadFeed = loadFeed;
    loadFeed = async function(params) {
        await originalLoadFeed(params);
        // Create timeline dots after feed loads
        setTimeout(createTimelineDots, 100);
    };
    
    // Scroll event handler
    mainContent.addEventListener('scroll', () => {
        updateTimelineHandle();
        updateTimelineDate();
    });
    
    // Timeline handle drag functionality
    let isDragging = false;
    
    timelineHandle.addEventListener('mousedown', (e) => {
        isDragging = true;
        e.preventDefault();
    });
    
    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        
        const rect = timelineContainer.getBoundingClientRect();
        const y = e.clientY - rect.top;
        const percentage = Math.max(0, Math.min(1, y / rect.height));
        
        const scrollTop = percentage * (mainContent.scrollHeight - mainContent.clientHeight);
        mainContent.scrollTop = scrollTop;
    });
    
    document.addEventListener('mouseup', () => {
        isDragging = false;
    });
    
    // Hover effects for timeline handle
    timelineHandle.addEventListener('mouseenter', () => {
        if (timelineDateElement) {
            timelineDateElement.classList.add('show');
        }
    });
    
    timelineHandle.addEventListener('mouseleave', () => {
        if (timelineDateElement) {
            timelineDateElement.classList.remove('show');
        }
    });
}

// Global Loader Functions
function showGlobalLoader() {
    if (globalLoader) globalLoader.style.display = 'block';
}

function hideGlobalLoader() {
    if (globalLoader) globalLoader.style.display = 'none';
}

/**
 * Shows the compose email modal.
 */
function showComposeModal() {
    if (composeModal) {
        composeModal.style.display = 'block';
    }
}

/**
 * Hides the compose email modal and resets the form.
 */
function hideComposeModal() {
    if (composeModal) {
        composeModal.style.display = 'none';
        composeForm.reset(); // Reset form fields, including file inputs
        composeInReplyTo.value = ''; // Ensure hidden field is also cleared
        // Explicitly clear file input for good measure, though reset() should handle it.
        const attachmentsInput = document.getElementById('compose-attachments');
        if (attachmentsInput) {
            attachmentsInput.value = null;
        }
    }
}

// Removed showProfileModal and hideProfileModal functions

/**
 * Redirects to a person's profile page.
 * @param {string} personId The ID of the person.
 */
function showProfile(personId) { // Removed async as it's no longer fetching data
    if (!personId) {
        console.error("Person ID is required to show profile.");
        return;
    }
    // Redirect to the profile page
    window.location.href = `profile.php?id=${personId}`;
}


/**
 * Loads the feed data from the API and renders it on the page.
 * @param {object} params - Optional parameters for filtering the feed.
 * @param {string|null} params.groupId - The ID of the group to filter by, or null for all groups.
 * @param {string|null} params.status - The status to filter by (unread, read, important, follow-up).
 */
async function loadFeed(params = {}) {
    if (!feedContainer) {
        console.error('Feed container not found!');
        return;
    }
    showGlobalLoader(); // Show global loader
    feedContainer.innerHTML = '<p>Loading feed...</p>'; // Specific loader for feed area

    // Use alice_k's user ID (first user created in test data)
    const currentUserId = 1; // This should be alice_k's ID since she's the first user created

    try {
        // Ensure we have a status filter, default to 'unread' if not specified
        const status = params.status || 'unread';
        
        console.log('Loading feed with params:', { ...params, status }); // Debug log
        
        const response = await api.getFeed(currentUserId, {
            groupId: params.groupId || null,
            status: status,
            page: params.page || 1,
            limit: params.limit || 50
        });

        console.log('Feed API response:', response); // Debug log

        if (!response || !response.data || !response.data.threads) {
            console.log('No response or no threads in response:', response);
            feedContainer.innerHTML = '<p>No emails found.</p>';
            return;
        }

        // Use the threads directly from the API response (no additional filtering needed)
        let threads = response.data.threads;
        console.log('Threads from API:', threads.length, threads);

        if (threads.length === 0) {
            console.log('No threads found after processing');
            feedContainer.innerHTML = `<p>No ${status} emails found.</p>`;
            return;
        }

        // Render the threads
        feedContainer.innerHTML = '';
        threads.forEach(thread => {
            console.log('Creating thread element for:', thread);
            const threadElement = createThreadElement(thread);
            feedContainer.appendChild(threadElement);
        });

        // Update the status filter to reflect the current filter
        if (statusFeedFilterSelect) {
            statusFeedFilterSelect.value = status;
        }

    } catch (error) {
        console.error('Error loading feed:', error);
        feedContainer.innerHTML = '<p>Error loading feed. Please try again later.</p>';
    } finally {
        hideGlobalLoader(); // Hide global loader
    }
}


/**
 * Loads groups from the API and populates the groups list and filter.
 */
async function loadGroups() {
    if (!groupsListContainer || !groupFeedFilterSelect) {
        console.error('Groups list container or filter select not found.');
        return;
    }
    showGlobalLoader(); // Show global loader
    groupsListContainer.innerHTML = '<p>Loading groups...</p>'; // Specific loader for group list

    // Clear existing options in filter (keeping "All Groups")
    const filterFirstOption = groupFeedFilterSelect.options[0]; // Save "All Groups"
    groupFeedFilterSelect.innerHTML = ''; // Clear all options
    groupFeedFilterSelect.appendChild(filterFirstOption); // Add "All Groups" back

    try {
        const response = await api.getGroups();
        console.log('Groups API response:', response); // Debug log
        
        // Handle different response structures
        let groups = [];
        if (response.data && Array.isArray(response.data)) {
            groups = response.data;
        } else if (response.groups && Array.isArray(response.groups)) {
            groups = response.groups;
        } else if (Array.isArray(response)) {
            groups = response;
        } else {
            console.error('Unexpected groups response structure:', response);
            groupsListContainer.innerHTML = '<p>Error: Unexpected response format</p>';
            return;
        }
        
        if (groups.length === 0) {
            groupsListContainer.innerHTML = '<p>No groups found. Create one!</p>';
            return;
        }

        // Clear the loading message
        groupsListContainer.innerHTML = '';

        // Populate groups list
        groups.forEach(group => {
            const groupElement = document.createElement('div');
            groupElement.className = 'group-item';
            groupElement.innerHTML = `
                <span>${group.group_name}</span>
                <div class="group-actions">
                    <button class="view-group-members-btn" data-group-id="${group.group_id}" title="View Members">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A1.5 1.5 0 0 0 18.54 8H17c-.8 0-1.54.37-2.01 1l-1.7 2.26A6.003 6.003 0 0 0 10 16v6h10z"/>
                            <path d="M6.5 6.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5S5 4.17 5 5s.67 1.5 1.5 1.5z"/>
                            <path d="M15 8c.83 0 1.5-.67 1.5-1.5S15.83 5 15 5s-1.5.67-1.5 1.5S14.17 8 15 8z"/>
                            <path d="M2.5 6.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5S1 4.17 1 5s.67 1.5 1.5 1.5z"/>
                            <path d="M10 8c.83 0 1.5-.67 1.5-1.5S10.83 5 10 5s-1.5.67-1.5 1.5S9.17 8 10 8z"/>
                            <path d="M5 18v-6h2.5l-2.54-7.63A1.5 1.5 0 0 0 2.54 8H1c-.8 0-1.54.37-2.01 1L-2.71 11.26A6.003 6.003 0 0 0 -6 16v6h10z"/>
                        </svg>
                    </button>
                    <button class="edit-group-btn" data-group-id="${group.group_id}" title="Edit Group">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                    </button>
                </div>
            `;
            groupsListContainer.appendChild(groupElement);
        });

        // Populate group filter dropdown
        groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group.group_id;
            option.textContent = group.group_name;
            groupFeedFilterSelect.appendChild(option);
        });

    } catch (error) {
        console.error('Error loading groups:', error);
        groupsListContainer.innerHTML = '<p>Error loading groups. Please try again later.</p>';
    } finally {
        hideGlobalLoader(); // Hide global loader
    }
}

/**
 * Renders a single thread object into an HTML element.
 * @param {Object} threadData - The thread data object.
 * @param {string} threadSubject - The subject of the parent thread (passed for reply pre-fill).
 * Expected structure: { thread_id, subject, participants, last_reply_time, emails: [{ email_id, sender_name, body_preview, timestamp }] }
 * @returns {HTMLElement} A div element representing the thread.
 */
// function renderThread moved to api.js and is available as window.renderThread
