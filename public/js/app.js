// DOM Elements
const feedContainer = document.getElementById('feed-container');
const newEmailBtn = document.getElementById('new-email-btn');
const composeModal = document.getElementById('compose-modal');
const composeForm = document.getElementById('compose-form');
const closeComposeModalBtn = document.getElementById('close-compose-modal-btn');
const cancelComposeBtn = document.getElementById('cancel-compose-btn');
const composeTo = document.getElementById('compose-to');
const composeSubject = document.getElementById('compose-subject');
const composeBody = document.getElementById('compose-body');
const composeInReplyTo = document.getElementById('compose-in-reply-to');

// Profile Modal DOM Elements
const profileModal = document.getElementById('profile-modal');
const closeProfileModalBtn = document.getElementById('close-profile-modal-btn');
const profileName = document.getElementById('profile-name');
const profileEmails = document.getElementById('profile-emails');
const profileNotes = document.getElementById('profile-notes');
const profileThreadsContainer = document.getElementById('profile-threads-container');

// Groups Management DOM Elements
const groupsSidebar = document.getElementById('groups-sidebar');
const groupsListContainer = document.getElementById('groups-list-container');
const newGroupNameInput = document.getElementById('new-group-name');
const createGroupBtn = document.getElementById('create-group-btn');
const groupFeedFilterSelect = document.getElementById('group-feed-filter');
const globalLoader = document.getElementById('global-loader'); // Added global loader


document.addEventListener('DOMContentLoaded', () => {
    const criticalElements = [
        feedContainer, newEmailBtn, composeModal, composeForm, closeComposeModalBtn, cancelComposeBtn,
        profileModal, closeProfileModalBtn, profileName, profileEmails, profileNotes, profileThreadsContainer,
        groupsSidebar, groupsListContainer, newGroupNameInput, createGroupBtn, groupFeedFilterSelect,
        globalLoader // Check for global loader
    ];

    if (criticalElements.some(el => !el)) {
        console.error('One or more critical UI elements are missing from the DOM.');
        if(newEmailBtn) newEmailBtn.disabled = true;
        // Optionally hide the main content area if critical parts are missing
        const mainLayout = document.querySelector('.main-layout');
        if (mainLayout) mainLayout.style.display = 'none';
        // Show a more prominent error to the user
        document.body.innerHTML = '<p style="color: red; text-align: center; padding: 20px;">Application Error: Essential UI components are missing. Please contact support.</p>';
        return;
    }

    loadFeed();
    loadGroups(); // Load groups on page load
    initializeEventListeners();
});

/**
 * Initializes all event listeners for the application.
 */
function initializeEventListeners() {
    newEmailBtn.addEventListener('click', () => {
        composeInReplyTo.value = ''; // Clear any previous reply ID
        composeTo.value = '';
        composeSubject.value = '';
        composeBody.value = '';
        showComposeModal();
    });

    closeComposeModalBtn.addEventListener('click', hideComposeModal);
    cancelComposeBtn.addEventListener('click', hideComposeModal);

    composeForm.addEventListener('submit', async (event) => {
        event.preventDefault();
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

            const result = await sendEmail(formData); // from api.js, now sends FormData
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
    });

    // Event delegation for reply buttons
    feedContainer.addEventListener('click', (event) => {
        if (event.target.classList.contains('reply-to-email-btn')) {
            const button = event.target;
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
        } else if (event.target.classList.contains('sender-link')) {
            const personId = event.target.dataset.personId;
            if (personId) {
                showProfile(personId);
            } else {
                console.warn('Sender link clicked, but no person-id found.', event.target);
            }
        }
    });

    if (closeProfileModalBtn) {
        closeProfileModalBtn.addEventListener('click', hideProfileModal);
    }

    // Optional: Close modal if user clicks outside of modal-content
    if (profileModal) {
        profileModal.addEventListener('click', (event) => {
            if (event.target === profileModal) { // Check if the click is on the modal backdrop
                hideProfileModal();
            }
        });
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
                await createGroup(groupName);
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
            loadFeed({ groupId: selectedGroupId || null }); // Pass null or empty to load all
        });
    }
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

/**
 * Shows the profile modal.
 */
function showProfileModal() {
    if (profileModal) {
        profileModal.style.display = 'block';
    }
}

/**
 * Hides the profile modal.
 */
function hideProfileModal() {
    if (profileModal) {
        profileModal.style.display = 'none';
        // Optionally clear content if it's sensitive or large
        profileName.textContent = '';
        profileEmails.textContent = '';
        profileNotes.textContent = '';
        profileThreadsContainer.innerHTML = '';
    }
}

/**
 * Fetches and displays a person's profile.
 * @param {string} personId The ID of the person.
 */
async function showProfile(personId) {
    if (!profileModal || !profileName || !profileEmails || !profileNotes || !profileThreadsContainer) {
        console.error("Profile modal elements not found.");
        return;
    }

    showProfileModal();
    // Clear previous content and show loading message within modal elements
    profileName.textContent = 'Loading...';
    profileEmails.textContent = 'Loading...';
    profileNotes.textContent = 'Loading...';
    profileThreadsContainer.innerHTML = '<p>Loading threads...</p>';
    showGlobalLoader(); // Also show global loader for profile fetching

    try {
        const profileData = await getProfile(personId);

        profileName.textContent = profileData.name || 'N/A';
        profileEmails.textContent = profileData.email_addresses ? profileData.email_addresses.join(', ') : 'N/A';
        profileNotes.textContent = profileData.notes || ''; // Show notes if available, otherwise empty

        if (profileData.threads && profileData.threads.length > 0) {
            profileData.threads.forEach(thread => {
                const threadSummaryDiv = document.createElement('div');
                threadSummaryDiv.className = 'profile-thread-summary';
                // Keep it simple: Subject and Last Reply Time
                const subjectP = document.createElement('p');
                subjectP.textContent = `Subject: ${thread.subject || 'No Subject'}`;
                threadSummaryDiv.appendChild(subjectP);

                if (thread.last_reply_time) {
                    const lastReplyP = document.createElement('p');
                    lastReplyP.textContent = `Last Reply: ${new Date(thread.last_reply_time).toLocaleString()}`;
                    threadSummaryDiv.appendChild(lastReplyP);
                }
                // Potentially add a link to the thread itself if desired in future
                // const link = document.createElement('a');
                // link.href = `#thread-${thread.thread_id}`; // Example link
                // link.textContent = "View Thread";
                // threadSummaryDiv.appendChild(link);
                profileThreadsContainer.appendChild(threadSummaryDiv);
            });
        } else {
            profileThreadsContainer.innerHTML = '<p>No associated threads found.</p>';
        }

    } catch (error) {
        console.error('Error fetching profile:', error);
        profileName.textContent = 'Error loading profile.';
        profileThreadsContainer.innerHTML = `<p>Could not load profile data: ${error.message}</p>`;
    } finally {
        hideGlobalLoader(); // Hide global loader
    }
}


/**
 * Loads the feed data from the API and renders it on the page.
 * @param {object} params - Optional parameters for filtering the feed.
 * @param {string|null} params.groupId - The ID of the group to filter by, or null for all groups.
 */
async function loadFeed(params = {}) {
    if (!feedContainer) {
        console.error('Feed container not found!');
        return;
    }
    showGlobalLoader(); // Show global loader
    feedContainer.innerHTML = '<p>Loading feed...</p>'; // Specific loader for feed area

    try {
        const response = await getFeed(params);
        const threads = response.threads;

        if (!threads || threads.length === 0) {
            const filterMessage = params.groupId ? ` for group ID ${params.groupId}` : '';
            feedContainer.innerHTML = `<p>No threads to display${filterMessage}.</p>`;
        } else {
            feedContainer.innerHTML = ''; // Clear "Loading feed..." message
            threads.forEach(thread => {
                const threadElement = renderThread(thread, thread.subject);
                feedContainer.appendChild(threadElement);
            });
        }
    } catch (error) {
        console.error('Error loading feed:', error);
        feedContainer.innerHTML = `<p>Error loading feed: ${error.message}. Please try again later.</p>`;
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
        const groups = await getGroups();

        groupsListContainer.innerHTML = '';
        if (!groups || groups.length === 0) {
            groupsListContainer.innerHTML = '<p>No groups found. Create one!</p>';
        } else {
            groups.forEach(group => {
            // Populate groups list
            const groupDiv = document.createElement('div');
            groupDiv.className = 'group-item';
            groupDiv.textContent = group.name; // Display group name

            const viewButton = document.createElement('button');
            viewButton.className = 'view-group-members-btn';
            viewButton.textContent = 'View';
            viewButton.dataset.groupId = group.group_id;
            // TODO: Add event listener for viewButton if/when functionality is implemented
            // viewButton.addEventListener('click', () => showGroupMembers(group.group_id));
            groupDiv.appendChild(viewButton);
            groupsListContainer.appendChild(groupDiv);

            // Populate group filter select
            const option = document.createElement('option');
            option.value = group.group_id;
            option.textContent = group.name;
                groupFeedFilterSelect.appendChild(option);
            });
        }
    } catch (error)
        console.error('Error loading groups:', error);
        groupsListContainer.innerHTML = `<p>Error loading groups: ${error.message}</p>`;
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
function renderThread(threadData, threadSubject) { // threadSubject added as parameter
    const threadDiv = document.createElement('div');
    threadDiv.className = 'thread';
    threadDiv.dataset.threadId = threadData.thread_id;

    const subjectH2 = document.createElement('h2');
    subjectH2.className = 'thread-subject';
    subjectH2.textContent = threadData.subject || 'No Subject'; // Use threadData.subject for display
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

            // Read/Unread status
            if (email.hasOwnProperty('is_read') && !email.is_read) {
                emailDiv.classList.add('email-unread');
            }
            // If no is_read property, add a comment for future API integration.
            // else if (!email.hasOwnProperty('is_read')) {
            // console.log("Email object does not have 'is_read' property. API might need update.");
            // }


            const senderP = document.createElement('p');
            senderP.className = 'email-sender';
            // Wrap sender name in a link/span for profile popup
            const senderNameSpan = document.createElement('span');
            senderNameSpan.className = 'sender-link';
            senderNameSpan.textContent = email.sender_name || 'Unknown Sender';
            if (email.sender_person_id) {
                senderNameSpan.dataset.personId = email.sender_person_id;
            } else {
                // Add a comment that sender_person_id is missing for this email
                // console.log("sender_person_id missing for email:", email.email_id);
                senderNameSpan.classList.add('no-profile'); // Add class to style differently if no ID
            }
            senderP.appendChild(document.createTextNode('From: '));
            senderP.appendChild(senderNameSpan);
            emailDiv.appendChild(senderP);

            // Placeholder for To, CC, BCC - assuming API might provide these on individual email objects in the future
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
            // BCC typically not shown to other recipients, but if API provides it for the user's own sent items:
            // if (email.bcc_recipients && email.bcc_recipients.length > 0) {
            // const bccP = document.createElement('p');
            // bccP.className = 'email-recipients-bcc';
            // bccP.textContent = `BCC: ${email.bcc_recipients.join(', ')}`;
            // emailDiv.appendChild(bccP);
            // }


            const previewP = document.createElement('p');
            previewP.className = 'email-preview';
            previewP.textContent = email.body_preview || 'No preview available.';
            emailDiv.appendChild(previewP);

            if (email.timestamp) {
                const timestampP = document.createElement('p');
                timestampP.className = 'email-timestamp';
                // Full timestamp format e.g., "Jan 15, 2024, 10:30 AM"
                timestampP.textContent = new Date(email.timestamp).toLocaleString(undefined, {
                    year: 'numeric', month: 'short', day: 'numeric',
                    hour: 'numeric', minute: '2-digit', hour12: true
                });
                emailDiv.appendChild(timestampP);
            }

            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'email-actions';

            // Reply button
            const replyBtn = document.createElement('button');
            replyBtn.className = 'reply-to-email-btn';
            replyBtn.textContent = 'Reply';
            replyBtn.dataset.emailId = email.email_id;
            replyBtn.dataset.subject = threadData.subject || 'No Subject';
            replyBtn.dataset.sender = email.sender_name || '';
            // Store full recipient list if available for reply all, defaulting to sender
            replyBtn.dataset.toRecipients = email.to_recipients ? email.to_recipients.join(',') : (email.sender_name || '');
            replyBtn.dataset.ccRecipients = email.cc_recipients ? email.cc_recipients.join(',') : '';
            actionsDiv.appendChild(replyBtn);

            // Reply All button
            const replyAllBtn = document.createElement('button');
            replyAllBtn.className = 'reply-all-to-email-btn';
            replyAllBtn.textContent = 'Reply All';
            replyAllBtn.dataset.emailId = email.email_id;
            replyAllBtn.dataset.subject = threadData.subject || 'No Subject';
            replyAllBtn.dataset.sender = email.sender_name || '';
             // For Reply All, gather To and CC recipients. BCC are usually not included in a "Reply All".
            const allRecipients = [];
            if (email.sender_name) allRecipients.push(email.sender_name); // Original sender
            if (email.to_recipients) allRecipients.push(...email.to_recipients);
            if (email.cc_recipients) allRecipients.push(...email.cc_recipients);
            // Filter out duplicates and current user (if known - not implemented here)
            const uniqueRecipients = [...new Set(allRecipients)];
            replyAllBtn.dataset.allRecipients = uniqueRecipients.join(',');
            actionsDiv.appendChild(replyAllBtn);

            // Forward button
            const forwardBtn = document.createElement('button');
            forwardBtn.className = 'forward-email-btn';
            forwardBtn.textContent = 'Forward';
            forwardBtn.dataset.emailId = email.email_id; // Useful for fetching full body if needed
            forwardBtn.dataset.subject = threadData.subject || 'No Subject';
            forwardBtn.dataset.originalSender = email.sender_name || 'Unknown Sender';
            forwardBtn.dataset.originalDate = email.timestamp ? new Date(email.timestamp).toLocaleString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            }) : 'Unknown Date';
            // Storing the preview here, ideally, you'd fetch the full body for forwarding.
            forwardBtn.dataset.originalBody = email.body_preview || 'No preview available.';
            actionsDiv.appendChild(forwardBtn);

            emailDiv.appendChild(actionsDiv);

            // Display Attachments if any
            if (email.attachments && email.attachments.length > 0) {
                const attachmentsListDiv = document.createElement('div');
                attachmentsListDiv.className = 'email-attachments-list';

                const heading = document.createElement('h4'); // Or <p><strong>Attachments:</strong></p>
                heading.textContent = 'Attachments:';
                attachmentsListDiv.appendChild(heading);

                email.attachments.forEach(attachment => {
                    const link = document.createElement('a');
                    link.href = attachment.url || (attachment.file_id ? `/api/download_attachment.php?file_id=${attachment.file_id}` : '#');
                    link.textContent = attachment.filename;
                    // Add download attribute if it's a direct URL or if server sets Content-Disposition
                    if (attachment.url || attachment.direct_url) { // Assuming direct_url means it might be a direct link
                        link.setAttribute('download', attachment.filename);
                    }
                    link.target = '_blank'; // Open in new tab
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
}
