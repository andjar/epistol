document.addEventListener('DOMContentLoaded', async () => {
    const profilePageName = document.getElementById('profile-page-name');
    const profileEmailsContainer = document.getElementById('profile-emails-container');
    const profileNotesContainer = document.getElementById('profile-notes-container');
    const feedContainer = document.getElementById('feed-container');
    const pageContainer = document.querySelector('.main-container'); // To display general errors
    const timelineContainer = document.getElementById('timeline-container');
    const timelineHandle = document.getElementById('timeline-handle');
    const timelineBar = document.getElementById('timeline-bar');

    if (!pageContainer) {
        console.error("Error: page-container element not found.");
        document.body.innerHTML = '<p>Critical page layout element missing. Cannot load profile.</p>';
        return;
    }

    // Show a loading message initially in relevant sections
    if (profilePageName) profilePageName.textContent = 'Loading Profile...';
    if (profileEmailsContainer) profileEmailsContainer.innerHTML = '<p>Loading emails...</p>';
    if (profileNotesContainer) profileNotesContainer.innerHTML = '<p>Loading notes...</p>';
    if (feedContainer) feedContainer.innerHTML = '<p>Loading threads...</p>';

    const params = new URLSearchParams(window.location.search);
    const personId = params.get('id');

    if (!personId) {
        pageContainer.innerHTML = '<h1>Error</h1><p>No profile ID specified in the URL.</p><p><a href="index.php" class="back-link">Back to Feed</a></p>';
        if (profilePageName) profilePageName.style.display = 'none'; // Hide loading message
        return;
    }

    // Global variables for timeline
    let allThreads = [];
    let timelineRange = { start: null, end: null };
    let currentTimelinePosition = 0.5; // 0 = oldest, 1 = newest

    try {
        // Assuming getProfile is globally available from api.js
        const profileData = await api.getProfile(personId);

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
                profileData.email_addresses.forEach(emailObj => {
                    const li = document.createElement('li');
                    li.textContent = emailObj.email + (emailObj.is_primary ? ' (Primary)' : '');
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

        await loadFeed(personId);
        initializeTimeline();

    } catch (error) {
        console.error('Error fetching or displaying profile data:', error);
        if (pageContainer) { // Check again in case it was cleared by an earlier error
            pageContainer.innerHTML = `<h1>Error</h1><p>Could not load profile data: ${error.message}</p><p><a href="index.php" class="back-link">Back to Feed</a></p>`;
        }
        if (profilePageName) profilePageName.style.display = 'none';
    }

    async function loadFeed(personId) {
        const feedContainer = document.getElementById('feed-container');
        if (!feedContainer) {
            console.error('Feed container not found!');
            return;
        }
        feedContainer.innerHTML = '<p>Loading feed...</p>';

        try {
            // Use alice_k's user ID (first user created in test data) - same as app.js
            const currentUserId = 1;
            
            // Call getFeed with proper parameters to filter by personId
            const response = await api.getFeed(currentUserId, { personId: personId });

            const threads = response.data ? response.data.threads : response.threads;

            if (!threads || threads.length === 0) {
                feedContainer.innerHTML = `<p>No threads to display for this user.</p>`;
                allThreads = [];
            } else {
                allThreads = threads;
                calculateTimelineRange();
                displayFilteredThreads();
            }
        } catch (error) {
            console.error('Error loading feed:', error);
            feedContainer.innerHTML = `<p>Error loading feed: ${error.message}. Please try again later.</p>`;
        }
    }

    function calculateTimelineRange() {
        if (allThreads.length === 0) return;

        const dates = allThreads.map(thread => new Date(thread.last_reply_time || thread.created_at));
        timelineRange.start = new Date(Math.min(...dates));
        timelineRange.end = new Date(Math.max(...dates));
    }

    function initializeTimeline() {
        if (!timelineHandle || !timelineBar) return;

        updateTimelinePosition();

        // Add timeline interaction
        let isDragging = false;
        
        timelineHandle.addEventListener('mousedown', (e) => {
            isDragging = true;
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const rect = timelineBar.getBoundingClientRect();
            const y = e.clientY - rect.top;
            const newPosition = Math.max(0, Math.min(1, y / rect.height));
            
            currentTimelinePosition = newPosition;
            updateTimelinePosition();
            filterThreadsByTimeline();
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
        });

        // Timeline bar click to jump to position
        timelineBar.addEventListener('click', (e) => {
            const rect = timelineBar.getBoundingClientRect();
            const y = e.clientY - rect.top;
            currentTimelinePosition = Math.max(0, Math.min(1, y / rect.height));
            updateTimelinePosition();
            filterThreadsByTimeline();
        });
    }

    function updateTimelinePosition() {
        if (!timelineHandle || !timelineRange.start || !timelineRange.end) return;

        const percentage = currentTimelinePosition * 100;
        timelineHandle.style.top = `${percentage}%`;

        // Calculate current date based on position
        const timeDiff = timelineRange.end.getTime() - timelineRange.start.getTime();
        const currentTime = timelineRange.start.getTime() + (timeDiff * (1 - currentTimelinePosition));
        const currentDate = new Date(currentTime);

        // Update or create date indicator
        let dateIndicator = timelineHandle.querySelector('.timeline-date');
        if (!dateIndicator) {
            dateIndicator = document.createElement('div');
            dateIndicator.className = 'timeline-date';
            timelineHandle.appendChild(dateIndicator);
        }
        dateIndicator.textContent = currentDate.toLocaleDateString();
    }

    function filterThreadsByTimeline() {
        if (!timelineRange.start || !timelineRange.end || allThreads.length === 0) {
            // If no timeline range or no threads, show all threads
            displayFilteredThreads();
            return;
        }

        const timeDiff = timelineRange.end.getTime() - timelineRange.start.getTime();
        const currentTime = timelineRange.start.getTime() + (timeDiff * (1 - currentTimelinePosition));
        const filterDate = new Date(currentTime);

        // Filter threads based on timeline position
        const filteredThreads = allThreads.filter(thread => {
            const threadDate = new Date(thread.last_reply_time || thread.created_at);
            return threadDate <= filterDate;
        });

        displayFilteredThreads(filteredThreads);
    }

    function displayFilteredThreads(threads = allThreads) {
        const feedContainer = document.getElementById('feed-container');
        if (!feedContainer) return;

        feedContainer.innerHTML = '';
        
        if (threads.length === 0) {
            feedContainer.innerHTML = '<p>No threads to display for this time period.</p>';
            return;
        }

        // Use alice_k's user ID (first user created in test data) - same as app.js
        const currentUserId = 1;

        threads.forEach(thread => {
            const threadElement = window.renderThread(thread, thread.subject, currentUserId);
            feedContainer.appendChild(threadElement);
        });
    }
});
