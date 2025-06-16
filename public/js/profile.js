document.addEventListener('DOMContentLoaded', async () => {
    const profilePageName = document.getElementById('profile-page-name');
    const profileEmailsContainer = document.getElementById('profile-emails-container');
    const profileNotesContainer = document.getElementById('profile-notes-container');
    const profileThreadsContainer = document.getElementById('profile-threads-container');
    const pageContainer = document.querySelector('.page-container'); // To display general errors

    if (!pageContainer) {
        console.error("Error: page-container element not found.");
        document.body.innerHTML = '<p>Critical page layout element missing. Cannot load profile.</p>';
        return;
    }

    // Show a loading message initially in relevant sections
    if (profilePageName) profilePageName.textContent = 'Loading Profile...';
    if (profileEmailsContainer) profileEmailsContainer.innerHTML = '<p>Loading emails...</p>';
    if (profileNotesContainer) profileNotesContainer.innerHTML = '<p>Loading notes...</p>';
    if (profileThreadsContainer) profileThreadsContainer.innerHTML = '<p>Loading threads...</p>';

    const params = new URLSearchParams(window.location.search);
    const personId = params.get('id');

    if (!personId) {
        pageContainer.innerHTML = '<h1>Error</h1><p>No profile ID specified in the URL.</p><p><a href="index.php" class="back-link">Back to Feed</a></p>';
        if (profilePageName) profilePageName.style.display = 'none'; // Hide loading message
        return;
    }

    try {
        // Assuming getProfile is globally available from api.js
        const profileData = await getProfile(personId);

        if (!profileData) {
            pageContainer.innerHTML = `<h1>Error</h1><p>Profile not found for ID: ${personId}.</p><p><a href="index.php" class="back-link">Back to Feed</a></p>`;
            if (profilePageName) profilePageName.style.display = 'none';
            return;
        }

        if (profilePageName) {
            profilePageName.textContent = profileData.name ? `Profile: ${profileData.name}` : 'Profile';
        }

        if (profileEmailsContainer) {
            profileEmailsContainer.innerHTML = '<h2>Emails</h2>';
            if (profileData.email_addresses && profileData.email_addresses.length > 0) {
                const emailList = document.createElement('ul');
                profileData.email_addresses.forEach(email => {
                    const li = document.createElement('li');
                    li.textContent = email;
                    emailList.appendChild(li);
                });
                profileEmailsContainer.appendChild(emailList);
            } else {
                profileEmailsContainer.innerHTML += '<p>No email addresses found for this profile.</p>';
            }
        }

        if (profileNotesContainer) {
            profileNotesContainer.innerHTML = '<h2>Notes</h2>';
            profileNotesContainer.innerHTML += `<p>${profileData.notes || 'No notes available for this profile.'}</p>`;
        }

        if (profileThreadsContainer) {
            profileThreadsContainer.innerHTML = '<h2>Correspondence</h2>'; // Changed title

            if (profileData.threads && profileData.threads.length > 0) {
                profileData.threads.forEach(thread => {
                    const threadItem = document.createElement('div');
                    threadItem.className = 'thread-item mb-4 p-3 border rounded shadow-sm';

                    const threadSubject = document.createElement('h3');
                    threadSubject.className = 'text-xl font-semibold mb-2';
                    threadSubject.textContent = thread.subject || 'No Subject';
                    threadItem.appendChild(threadSubject);

                    if (thread.emails && thread.emails.length > 0) {
                        thread.emails.forEach(email => {
                            const emailItem = document.createElement('div');
                            emailItem.className = 'email-item mb-3 p-2 border-t';
                            if (email.parent_email_id) {
                                emailItem.classList.add('ml-4'); // Indent replies
                            }

                            let emailInfo = `<p class="text-sm text-gray-600"><strong>Date:</strong> ${new Date(email.created_at).toLocaleString()}</p>`;
                            emailInfo += `<p class="text-sm"><strong>From:</strong> ${email.sender.name} (${email.sender.email})</p>`;

                            const toRecipients = email.recipients.filter(r => r.type === 'to');
                            const ccRecipients = email.recipients.filter(r => r.type === 'cc');
                            const bccRecipients = email.recipients.filter(r => r.type === 'bcc');

                            if (toRecipients.length > 0) {
                                emailInfo += `<p class="text-sm"><strong>To:</strong> ${toRecipients.map(r => `${r.name} (${r.email})`).join(', ')}</p>`;
                            }
                            if (ccRecipients.length > 0) {
                                emailInfo += `<p class="text-sm"><strong>Cc:</strong> ${ccRecipients.map(r => `${r.name} (${r.email})`).join(', ')}</p>`;
                            }
                            if (bccRecipients.length > 0) { // Displaying BCC for completeness, though typically hidden
                                emailInfo += `<p class="text-sm"><strong>Bcc:</strong> ${bccRecipients.map(r => `${r.name} (${r.email})`).join(', ')}</p>`;
                            }

                            emailInfo += `<p class="text-sm"><strong>Subject:</strong> ${email.subject || '(No Subject)'}</p>`;

                            const bodySnippet = document.createElement('p');
                            bodySnippet.className = 'email-body-snippet text-sm mt-1 italic';
                            // Create a snippet: first 100 chars of body_text or full if shorter
                            bodySnippet.textContent = email.body_text ? (email.body_text.substring(0, 150) + (email.body_text.length > 150 ? '...' : '')) : '(No body text)';

                            emailItem.innerHTML = emailInfo;
                            emailItem.appendChild(bodySnippet);
                            threadItem.appendChild(emailItem);
                        });
                    } else {
                        const noEmailsMessage = document.createElement('p');
                        noEmailsMessage.textContent = 'No emails in this thread.';
                        noEmailsMessage.className = 'text-sm text-gray-500';
                        threadItem.appendChild(noEmailsMessage);
                    }
                    profileThreadsContainer.appendChild(threadItem);
                });
            } else {
                profileThreadsContainer.innerHTML += '<p>No correspondence found for this person.</p>';
            }
        }

    } catch (error) {
        console.error('Error fetching or displaying profile data:', error);
        if (pageContainer) { // Check again in case it was cleared by an earlier error
            pageContainer.innerHTML = `<h1>Error</h1><p>Could not load profile data: ${error.message}</p><p><a href="index.php" class="back-link">Back to Feed</a></p>`;
        }
        if (profilePageName) profilePageName.style.display = 'none';
    }
});
