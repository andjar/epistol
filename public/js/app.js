document.addEventListener('DOMContentLoaded', () => {
    loadFeed();
});

/**
 * Loads the feed data from the API and renders it on the page.
 */
async function loadFeed() {
    const feedContainer = document.getElementById('feed-container');
    if (!feedContainer) {
        console.error('Feed container not found!');
        return;
    }

    // Clear previous content (e.g., loading message)
    feedContainer.innerHTML = '<p>Loading feed...</p>';

    try {
        const threads = await getFeed(); // Assumes getFeed is globally available from api.js

        if (!threads || threads.length === 0) {
            feedContainer.innerHTML = '<p>No threads to display.</p>';
            return;
        }

        feedContainer.innerHTML = ''; // Clear "Loading feed..." message

        threads.forEach(thread => {
            const threadElement = renderThread(thread);
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
 * Expected structure: { thread_id, subject, participants, last_reply_time, emails: [{ email_id, sender_name, body_preview, timestamp }] }
 * @returns {HTMLElement} A div element representing the thread.
 */
function renderThread(threadData) {
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
        // Basic date formatting, consider using a library for more complex needs
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
