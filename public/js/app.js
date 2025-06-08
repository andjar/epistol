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


document.addEventListener('DOMContentLoaded', () => {
    if (!feedContainer || !newEmailBtn || !composeModal || !composeForm || !closeComposeModalBtn || !cancelComposeBtn) {
        console.error('One or more critical UI elements for composing emails are missing from the DOM.');
        // Optionally, disable compose functionality or show a permanent error to the user.
        if(newEmailBtn) newEmailBtn.disabled = true;
        return;
    }
    loadFeed();
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

        const emailData = {
            recipients: recipients,
            subject: subject,
            body_text: bodyText,
            body_html: `<p>${bodyText.replace(/\n/g, '<br>')}</p>`, // Simple conversion
            in_reply_to_email_id: composeInReplyTo.value || null,
            // attachments: [] // Attachments not implemented in this phase
        };

        try {
            document.getElementById('send-email-btn').disabled = true;
            document.getElementById('send-email-btn').textContent = 'Sending...';

            const result = await sendEmail(emailData); // from api.js
            console.log('Email sent successfully', result);
            hideComposeModal();
            await loadFeed(); // Refresh the feed to show the new email
        } catch (error) {
            console.error('Failed to send email:', error);
            alert(`Error sending email: ${error.message}`);
        } finally {
            document.getElementById('send-email-btn').disabled = false;
            document.getElementById('send-email-btn').textContent = 'Send';
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
        }
    });
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
        composeForm.reset(); // Reset form fields
        composeInReplyTo.value = ''; // Ensure hidden field is also cleared
    }
}


/**
 * Loads the feed data from the API and renders it on the page.
 */
async function loadFeed() {
    // feedContainer is now a global const
    if (!feedContainer) {
        console.error('Feed container not found!');
        return;
    }

    // Clear previous content (e.g., loading message)
    feedContainer.innerHTML = '<p>Loading feed...</p>';

    try {
        // getFeed() now returns an object like { threads: [], pagination: {} }
        const response = await getFeed(); // from api.js
        const threads = response.threads; // Extract threads array

        if (!threads || threads.length === 0) {
            feedContainer.innerHTML = '<p>No threads to display.</p>';
            return;
        }

        feedContainer.innerHTML = ''; // Clear "Loading feed..." message

        // Note: response.pagination is available here if needed for UI later
        threads.forEach(thread => {
            const threadElement = renderThread(thread, threadData.subject); // Pass thread subject for replies
            feedContainer.appendChild(threadElement);
        });
    } catch (error) {
        console.error('Error loading feed:', error);
        feedContainer.innerHTML = '<p>Error loading feed. Please try again later.</p>';
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

            const senderP = document.createElement('p');
            senderP.className = 'email-sender';
            senderP.textContent = email.sender_name || 'Unknown Sender';
            emailDiv.appendChild(senderP);

            const previewP = document.createElement('p');
            previewP.className = 'email-preview';
            previewP.textContent = email.body_preview || 'No preview available.';
            emailDiv.appendChild(previewP);

            if (email.timestamp) {
                const timestampP = document.createElement('p');
                timestampP.className = 'email-timestamp';
                timestampP.textContent = new Date(email.timestamp).toLocaleString();
                emailDiv.appendChild(timestampP);
            }

            // Add Reply button to each email
            const replyBtn = document.createElement('button');
            replyBtn.className = 'reply-to-email-btn';
            replyBtn.textContent = 'Reply';
            replyBtn.dataset.emailId = email.email_id;
            // Use threadData.subject for the "Re:" part, as email.subject might not exist if it's not fetched per email
            replyBtn.dataset.subject = threadData.subject || 'No Subject';
            replyBtn.dataset.sender = email.sender_name || ''; // Sender of this specific email
            emailDiv.appendChild(replyBtn);

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
