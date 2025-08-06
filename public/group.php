<?php
require_once '../src/db.php'; // Adjust path as necessary
require_once '../src/helpers.php'; // Adjust path as necessary

$group_id = $_GET['id'] ?? null;

if (!$group_id) {
    // Option 1: Display an error message
    // die("Error: Group ID is missing.");

    // Option 2: Redirect to homepage (or an error page)
    header("Location: index.php?error=missing_group_id");
    exit;
}

// At this point, $group_id is available.
// In a real application, you would fetch group data from the database using $group_id.
// For now, we'll just use it to potentially display the ID.

$page_title = "Group Details"; // Default title

// Example: Fetch basic group data (replace with actual DB query)
// $stmt = $pdo->prepare("SELECT name FROM Groups WHERE group_id = ?");
// $stmt->execute([$group_id]);
// $group = $stmt->fetch(PDO::FETCH_ASSOC);
// if ($group && $group['name']) {
//     $page_title = htmlspecialchars($group['name']) . " - Group";
// } else {
//     $page_title = "Group Not Found";
// }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Top Bar -->
    <header id="top-bar">
        <div class="logo-area">
            <button class="sidebar-toggle-btn" onclick="toggleLeftSidebar()">☰</button>
            <h1><a href="index.php">Epistol</a></h1>
        </div>
        
        <div class="search-area">
            <input type="text" id="search-field" placeholder="Search emails..." />
            <button class="search-btn">
                <img src="/images/icons/search.svg" alt="Search" />
            </button>
        </div>
        
        <nav class="navigation-menus">
            <ul class="nav-menu">
                <li>
                    <a href="index.php">
                        <img src="/images/icons/home.svg" alt="Home" />
                        <span>Home</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showNotifications()">
                        <img src="/images/icons/notifications.svg" alt="Notifications" />
                        <span>Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showGroups()">
                        <img src="/images/icons/groups.svg" alt="Groups" />
                        <span>Groups</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showCurrentUserProfile()">
                        <img src="/images/icons/profile.svg" alt="Profile" />
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <button class="new-email-btn" onclick="showComposeModal()">
            <img src="/images/icons/compose.svg" alt="New Email" />
            <span>New Email</span>
        </button>
        
        <button class="sidebar-toggle-btn" onclick="toggleRightSidebar()">☰</button>
    </header>

    <div class="main-container">
        <!-- Left Sidebar -->
        <aside id="left-sidebar" class="sidebar">
            <h2>Groups</h2>
            <div id="groups-list-container">
                <p>Loading groups...</p>
            </div>
            
            <div class="create-group-section">
                <h3>Create New Group</h3>
                <input type="text" id="create-group-input" placeholder="Group Name" />
                <button id="create-group-btn">Create Group</button>
            </div>
            
            <div class="filter-section">
                <h3>Filter Feed by Group</h3>
                <select id="group-feed-filter">
                    <option value="">All Groups</option>
                </select>
            </div>
            
            <div class="filter-section">
                <h3>Filter Feed by Status</h3>
                <select id="status-feed-filter">
                    <option value="">All Statuses</option>
                    <option value="read">Read</option>
                    <option value="unread">Unread</option>
                    <option value="follow-up">Follow-up</option>
                    <option value="important">Important Info</option>
                </select>
            </div>
        </aside>

        <!-- Main Content -->
        <main id="main-content">
            <div class="page-container">
                <div class="page-header">
                    <a href="index.php" class="back-link">← Back to Feed</a>
                    <h1 id="group-page-name">Group Information for ID: <?php echo htmlspecialchars($group_id); ?></h1>
                </div>

                <?php if ($group_id): ?>
                    <div class="group-content">
                        <div id="group-members-container" class="content-section">
                            <h2>Group Members</h2>
                            <div class="members-list">
                                <div class="member-item">
                                    <strong>Alice K.</strong> - alice@example.com
                                </div>
                                <div class="member-item">
                                    <strong>Bob The Builder</strong> - bob@example.com
                                </div>
                                <div class="member-item">
                                    <strong>Charlie Brown</strong> - charlie@example.com
                                </div>
                            </div>
                        </div>

                        <div id="group-feed-container" class="content-section">
                            <h2>Group-Specific Feed</h2>
                            <div id="feed-container">
                                <p>No emails found for this group.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        <h1>Group Not Found</h1>
                        <p>The requested group could not be found or no ID was provided.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside id="right-sidebar" class="sidebar">
            <h2>Advanced Filters</h2>
            <div class="filter-section">
                <h3>🔍 Search & Keywords</h3>
                <input type="text" placeholder="Search in subject, body, sender..." />
                <div class="filter-options">
                    <label><input type="checkbox" checked /> Subject</label>
                    <label><input type="checkbox" checked /> Body</label>
                    <label><input type="checkbox" checked /> Sender</label>
                </div>
            </div>
            
            <div class="filter-section">
                <h3>👤 People</h3>
                <input type="text" placeholder="Filter by sender..." />
                <input type="text" placeholder="Filter by recipient..." />
                <div class="filter-options">
                    <label><input type="checkbox" /> Sent by me</label>
                    <label><input type="checkbox" /> Received by me</label>
                </div>
            </div>
            
            <div class="filter-section">
                <h3>📎 Attachments</h3>
                <div class="filter-options">
                    <label><input type="checkbox" /> Has attachments</label>
                    <label><input type="checkbox" /> No attachments</label>
                </div>
                <div class="file-type-filters">
                    <h4>File Types:</h4>
                    <label><input type="checkbox" /> PDF</label>
                    <label><input type="checkbox" /> Word/Excel</label>
                    <label><input type="checkbox" /> Images</label>
                    <label><input type="checkbox" /> Video</label>
                    <label><input type="checkbox" /> Audio</label>
                    <label><input type="checkbox" /> Archives</label>
                </div>
            </div>
            
            <div class="filter-section">
                <h3>📅 Date Range</h3>
                <input type="date" />
                <input type="date" />
                <div class="quick-dates">
                    <button class="quick-date-btn">Today</button>
                    <button class="quick-date-btn">This Week</button>
                    <button class="quick-date-btn">This Month</button>
                    <button class="quick-date-btn">Last 3 Months</button>
                </div>
            </div>
            
            <div class="filter-section">
                <h3>📏 Size</h3>
                <div class="filter-options">
                    <label><input type="checkbox" /> Small (< 1MB)</label>
                    <label><input type="checkbox" /> Medium (1-10MB)</label>
                    <label><input type="checkbox" /> Large (> 10MB)</label>
                </div>
            </div>
            
            <div class="filter-actions">
                <button class="primary-btn">Apply Filters</button>
                <button class="secondary-btn">Clear All</button>
                <button class="small-btn">Save Preset</button>
            </div>
            
            <div class="filter-section">
                <h3>💾 Saved Presets</h3>
                <select>
                    <option>Select a preset...</option>
                </select>
                <div class="filter-actions">
                    <button class="small-btn">Load</button>
                    <button class="small-btn danger">Delete</button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Timeline -->
    <div id="timeline-container">
        <div id="timeline-bar">
            <div id="timeline-handle"></div>
            <div id="timeline-date"></div>
        </div>
    </div>

    <!-- Global Loader -->
    <div id="global-loader" class="global-loader" style="display: none;">
        <div class="loader-spinner"></div>
        <p>Loading...</p>
    </div>

    <footer>
        <p>&copy; 2025 Epistol</p>
    </footer>

    <script src="js/api.js"></script>
    <script src="js/common.js"></script>
    <script src="js/group.js" defer></script>
    <script>
        // Sidebar toggling functions for group page
        function toggleLeftSidebar() {
            const leftSidebar = document.getElementById('left-sidebar');
            if (leftSidebar) {
                leftSidebar.classList.toggle('collapsed');
            }
        }

        function toggleRightSidebar() {
            const rightSidebar = document.getElementById('right-sidebar');
            if (rightSidebar) {
                rightSidebar.classList.toggle('collapsed');
            }
        }

        // Navigation functions for group page
        function showNotifications() {
            alert('Notifications feature coming soon!');
        }

        function showGroups() {
            alert('Groups feature coming soon!');
        }

        function showCurrentUserProfile() {
            window.location.href = 'profile.php?id=1'; // Assuming current user ID is 1
        }

        function showComposeModal() {
            alert('Compose modal coming soon!');
        }
    </script>
</body>
</html>
