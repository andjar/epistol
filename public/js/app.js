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

// DOM Elements for new layout
const leftSidebar = document.getElementById('left-sidebar'); // Changed from groupsSidebar
const rightSidebar = document.getElementById('right-sidebar');
const searchField = document.getElementById('search-field');
// Potentially add toggle buttons for sidebars if they exist in HTML
const toggleLeftSidebarBtn = document.getElementById('toggle-groups-sidebar-btn'); // This is the old toggleGroupsSidebarBtn, now for left-sidebar
const toggleRightSidebarBtn = document.getElementById('toggle-right-sidebar-btn'); // Hypothetical, might not exist

// Groups Management DOM Elements (mostly within leftSidebar now)
const groupsListContainer = document.getElementById('groups-list-container');
const newGroupNameInput = document.getElementById('new-group-name');
const createGroupBtn = document.getElementById('create-group-btn');
const groupFeedFilterSelect = document.getElementById('group-feed-filter');
const statusFeedFilterSelect = document.getElementById('status-feed-filter'); // Added status filter
const globalLoader = document.getElementById('global-loader');


document.addEventListener('DOMContentLoaded', () => {
    const criticalElements = [
        feedContainer, newEmailBtn, composeModal, composeForm, closeComposeModalBtn, cancelComposeBtn,
        leftSidebar, // Changed from groupsSidebar
        // toggleLeftSidebarBtn is the same as old toggleGroupsSidebarBtn, ensure it's handled
        groupsListContainer, newGroupNameInput, createGroupBtn,
        groupFeedFilterSelect, statusFeedFilterSelect,
        globalLoader, searchField // Added searchField
        // Optional: rightSidebar, toggleRightSidebarBtn if they become essential
    ];
     // Check toggleLeftSidebarBtn separately as it's the same as the old toggleGroupsSidebarBtn
    if (!toggleLeftSidebarBtn) console.warn('toggleLeftSidebarBtn (formerly toggleGroupsSidebarBtn) is missing. Left sidebar may not be collapsible.');


    if (criticalElements.some(el => !el)) {
        criticalElements.forEach((el, index) => {
            if (!el) {
                const elementName = [
                    'feedContainer', 'newEmailBtn', 'composeModal', 'composeForm', 'closeComposeModalBtn', 'cancelComposeBtn',
                    'leftSidebar',
                    'groupsListContainer', 'newGroupNameInput', 'createGroupBtn',
                    'groupFeedFilterSelect', 'statusFeedFilterSelect',
                    'globalLoader', 'searchField'
                ][index];
                console.error(`Critical element: ${elementName} (at index ${index}) is missing from the DOM.`);
            }
        });
        console.error('One or more critical UI elements are missing. Application functionality may be compromised.');
        if (newEmailBtn) newEmailBtn.disabled = true;
        const mainContainer = document.querySelector('.main-container'); // Updated selector
        if (mainContainer) mainContainer.style.display = 'none';
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
    });

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
        } else if (event.target.classList.contains('sender-link')) {
            const personId = event.target.dataset.personId;
            if (personId) {
                showProfile(personId);
            } else {
                console.warn('Sender link clicked, but no person-id found.', target);
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
                const statusContainer = target.closest('.email-status-container');
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


    // Removed profile modal event listeners

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
                await api.createGroup({ name: groupName });
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
            if (viewButton && viewButton.dataset.groupId) {
                const groupId = viewButton.dataset.groupId;
                window.location.href = `group.php?id=${groupId}`;
            }
        });
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
        // Ensure params is an object.
        const effectiveParams = { ...params };

        // Get selected group and status for the API call
        const selectedGroupId = groupFeedFilterSelect ? groupFeedFilterSelect.value : null;
        const selectedStatus = statusFeedFilterSelect ? statusFeedFilterSelect.value : null;

        if (selectedGroupId) {
            effectiveParams.groupId = selectedGroupId;
        }
        if (selectedStatus) {
            effectiveParams.status = selectedStatus;
        }


        // getFeed now expects userId as the first argument.
        const response = await api.getFeed(currentUserId, effectiveParams); // Using api.getFeed

        const threads = response.data ? response.data.threads : response.threads;

        if (!threads || threads.length === 0) {
            let filterMessage = '';
            if (effectiveParams.groupId && effectiveParams.status) {
                filterMessage = ` for group ID ${effectiveParams.groupId} and status '${effectiveParams.status}'`;
            } else if (effectiveParams.groupId) {
                filterMessage = ` for group ID ${effectiveParams.groupId}`;
            } else if (effectiveParams.status) {
                filterMessage = ` for status '${effectiveParams.status}'`;
            }
            feedContainer.innerHTML = `<p>No threads to display${filterMessage}.</p>`;
        } else {
            feedContainer.innerHTML = ''; // Clear "Loading feed..." message
            threads.forEach(thread => {
                // Pass currentUserId to renderThread
                const threadElement = window.renderThread(thread, thread.subject, currentUserId);
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
        const response = await api.getGroups();
        const groups = response.data ? response.data.groups : response.groups;

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
    } catch (error) { // Fixed missing opening curly brace for catch
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
// function renderThread moved to api.js and is available as window.renderThread
