document.addEventListener('DOMContentLoaded', async () => {
    const groupPageName = document.getElementById('group-page-name');
    const groupMembersContainer = document.getElementById('group-members-container');
    const groupFeedContainer = document.getElementById('group-feed-container');
    const pageContainer = document.querySelector('.page-container');

    if (!pageContainer) {
        console.error("Error: page-container element not found.");
        document.body.innerHTML = '<p>Critical page layout element missing. Cannot load group details.</p>';
        return;
    }

    if (groupPageName) groupPageName.textContent = 'Loading Group Details...';
    if (groupMembersContainer) groupMembersContainer.innerHTML = '<h2>Group Members</h2><p>Loading members...</p>';
    if (groupFeedContainer) groupFeedContainer.innerHTML = '<h2>Group Feed</h2><p>Loading feed...</p>';

    const params = new URLSearchParams(window.location.search);
    const groupId = params.get('id');

    if (!groupId) {
        pageContainer.innerHTML = '<h1>Error</h1><p>No group ID specified in the URL.</p><p><a href="index.php" class="back-link">Back to Feed</a></p>';
        if (groupPageName) groupPageName.style.display = 'none';
        return;
    }

    try {
        // Fetch group details (name)
        // Assuming getGroups() is available and returns an array of all groups
        let groupName = `Group ID: ${groupId}`; // Default name
        try {
            const allGroups = await getGroups(); // from api.js
            const currentGroup = allGroups.find(g => g.group_id === groupId);
            if (currentGroup && currentGroup.name) {
                groupName = currentGroup.name;
            }
        } catch (e) {
            console.warn("Could not fetch all groups to determine name, using ID as name.", e);
        }
        if (groupPageName) groupPageName.textContent = groupName;


        // Fetch and display group members
        if (groupMembersContainer) {
            groupMembersContainer.innerHTML = '<h2>Group Members</h2>';
            try {
                // Assuming getGroupMembers(groupId) will be available in api.js
                const members = await getGroupMembers(groupId); // Now calling the actual function

                if (members && members.length > 0) {
                    const ul = document.createElement('ul');
                    ul.className = 'members-list'; // Use class from style.css
                    members.forEach(member => {
                        const li = document.createElement('li');
                        // Ideally, member names would be links to their profiles
                        const memberLink = document.createElement('a');
                        memberLink.href = `profile.php?id=${member.person_id}`;
                        memberLink.textContent = member.name || 'Unknown Member';
                        // Add event listener to use showProfile if api.js makes it global,
                        // or just let it be a normal link for now.
                        // For simplicity, direct link is fine.
                        li.appendChild(memberLink);
                        ul.appendChild(li);
                    });
                    groupMembersContainer.appendChild(ul);
                } else {
                    groupMembersContainer.innerHTML += '<p>No members found for this group.</p>';
                }
            } catch (error) {
                console.error('Error fetching group members:', error);
                groupMembersContainer.innerHTML += `<p>Error loading members: ${error.message}</p>`;
            }
        }

        // Fetch and display group-specific feed
        if (groupFeedContainer) {
            groupFeedContainer.innerHTML = '<h2>Group Feed</h2>';
            try {
                const feedResponse = await getFeed({ groupId: groupId }); // from api.js
                if (feedResponse && feedResponse.threads && feedResponse.threads.length > 0) {
                    feedResponse.threads.forEach(thread => {
                        if (typeof window.renderThread === 'function') {
                            const threadElement = window.renderThread(thread, thread.subject);
                            groupFeedContainer.appendChild(threadElement);
                        } else {
                            const p = document.createElement('p');
                            p.textContent = `Thread: ${thread.subject || 'No Subject'}`;
                            groupFeedContainer.appendChild(p);
                            console.warn('window.renderThread function not found. Displaying basic thread info.');
                        }
                    });
                } else {
                    groupFeedContainer.innerHTML += '<p>No feed items found for this group.</p>';
                }
            } catch (error) {
                console.error('Error fetching group feed:', error);
                groupFeedContainer.innerHTML += `<p>Error loading feed: ${error.message}</p>`;
            }
        }

    } catch (error) {
        console.error('Error displaying group details:', error);
        if (pageContainer) {
            pageContainer.innerHTML = `<h1>Error</h1><p>Could not load group details: ${error.message}</p><p><a href="index.php" class="back-link">Back to Feed</a></p>`;
        }
        if (groupPageName) groupPageName.style.display = 'none';
    }
});
