<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epistol - Your Email Feed</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div id="global-loader" class="global-loader" style="display:none;">Loading...</div>

    <?php include 'common/header.php'; ?>

    <div class="main-container">
        <aside id="left-sidebar" class="sidebar">
            <h2>Groups</h2>
            <div id="groups-list-container">
                <!-- Groups will be loaded here -->
            </div>
            <div id="create-group-section">
                <h3>Create New Group</h3>
                <input type="text" id="new-group-name" placeholder="Group Name">
                <button id="create-group-btn">Create Group</button>
            </div>
            <div id="group-filter-section">
                <h3>Filter Feed by Group</h3>
                <select id="group-feed-filter">
                    <option value="">All Groups</option>
                    <!-- Group options will be populated here -->
                </select>
            </div>
            <div id="status-filter-section">
                <h3>Filter Feed by Status</h3>
                <select id="status-feed-filter">
                    <option value="">All Statuses</option>
                    <option value="read">Read</option>
                    <option value="unread">Unread</option>
                    <option value="follow-up">Follow-up</option>
                    <option value="important-info">Important Info</option>
                </select>
            </div>
        </aside>

        <?php include 'common/feed.php'; ?>

        <?php include 'common/timeline.php'; ?>

        <aside id="right-sidebar" class="sidebar">
            <h2>Right Sidebar</h2>
            <p>Placeholder content for the right sidebar. This area can be used for ads, context-sensitive information, or other purposes.</p>
            {_RIGHT_SIDEBAR_CONTENT_PLACEHOLDER_}
        </aside>
    </div>

    <!-- Compose Email Modal -->
    <div id="compose-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-btn" id="close-compose-modal-btn">&times;</span>
            <h2>Compose Email</h2>
            <form id="compose-form">
                <input type="hidden" id="compose-in-reply-to" name="in_reply_to_email_id">
                <div>
                    <label for="compose-to">To:</label>
                    <input type="email" id="compose-to" name="to" required multiple>
                </div>
                <div>
                    <label for="compose-subject">Subject:</label>
                    <input type="text" id="compose-subject" name="subject" required>
                </div>
                <div>
                    <label for="compose-body">Body:</label>
                    <textarea id="compose-body" name="body" rows="10" required></textarea>
                </div>
                <div>
                    <label for="compose-attachments">Attachments:</label>
                    <input type="file" id="compose-attachments" name="attachments[]" multiple>
                </div>
                <div class="form-actions">
                    <button type="submit" id="send-email-btn">Send</button>
                    <button type="button" id="cancel-compose-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Epistol</p>
    </footer>
    <script src="js/api.js?v=<?php echo time(); ?>"></script>
    <script src="js/common.js?v=<?php echo time(); ?>"></script>
    <script src="js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
