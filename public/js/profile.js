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
            profileThreadsContainer.innerHTML = '<h2>Associated Threads</h2>';
            if (profileData.threads && profileData.threads.length > 0) {
                profileData.threads.forEach(thread => {
                    // Assuming window.renderThread is available from api.js
                    if (typeof window.renderThread === 'function') {
                        const threadElement = window.renderThread(thread, thread.subject);
                        profileThreadsContainer.appendChild(threadElement);
                    } else {
                        // Fallback if renderThread is not available
                        const p = document.createElement('p');
                        p.textContent = `Thread: ${thread.subject || 'No Subject'}`;
                        profileThreadsContainer.appendChild(p);
                        console.warn('window.renderThread function not found. Displaying basic thread info.');
                    }
                });
            } else {
                profileThreadsContainer.innerHTML += '<p>No associated threads found.</p>';
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
