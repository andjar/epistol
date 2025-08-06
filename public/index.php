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
            <h2>Advanced Filters</h2>
            
            <!-- Search Filters -->
            <div class="filter-section">
                <h3>🔍 Search & Keywords</h3>
                <input type="text" id="keyword-search" placeholder="Search in subject, body, sender...">
                <div class="filter-options">
                    <label><input type="checkbox" id="search-subject" checked> Subject</label>
                    <label><input type="checkbox" id="search-body" checked> Body</label>
                    <label><input type="checkbox" id="search-sender" checked> Sender</label>
                </div>
            </div>

            <!-- Sender/Recipient Filters -->
            <div class="filter-section">
                <h3>👤 People</h3>
                <input type="text" id="sender-filter" placeholder="Filter by sender...">
                <input type="text" id="recipient-filter" placeholder="Filter by recipient...">
                <div class="filter-options">
                    <label><input type="checkbox" id="filter-sent-by-me"> Sent by me</label>
                    <label><input type="checkbox" id="filter-received-by-me"> Received by me</label>
                </div>
            </div>

            <!-- File Type Filters -->
            <div class="filter-section">
                <h3>📎 Attachments</h3>
                <div class="filter-options">
                    <label><input type="checkbox" id="filter-has-attachments"> Has attachments</label>
                    <label><input type="checkbox" id="filter-no-attachments"> No attachments</label>
                </div>
                <div class="file-type-filters">
                    <h4>File Types:</h4>
                    <label><input type="checkbox" id="filter-pdf"> PDF</label>
                    <label><input type="checkbox" id="filter-doc"> Word/Excel</label>
                    <label><input type="checkbox" id="filter-image"> Images</label>
                    <label><input type="checkbox" id="filter-video"> Video</label>
                    <label><input type="checkbox" id="filter-audio"> Audio</label>
                    <label><input type="checkbox" id="filter-archive"> Archives</label>
                </div>
            </div>

            <!-- Date Range Filters -->
            <div class="filter-section">
                <h3>📅 Date Range</h3>
                <input type="date" id="date-from" placeholder="From date">
                <input type="date" id="date-to" placeholder="To date">
                <div class="quick-dates">
                    <button class="quick-date-btn" data-days="1">Today</button>
                    <button class="quick-date-btn" data-days="7">This Week</button>
                    <button class="quick-date-btn" data-days="30">This Month</button>
                    <button class="quick-date-btn" data-days="90">Last 3 Months</button>
                </div>
            </div>

            <!-- Size Filters -->
            <div class="filter-section">
                <h3>📏 Size</h3>
                <div class="size-filters">
                    <label><input type="checkbox" id="filter-small"> Small (< 1MB)</label>
                    <label><input type="checkbox" id="filter-medium"> Medium (1-10MB)</label>
                    <label><input type="checkbox" id="filter-large"> Large (> 10MB)</label>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="filter-actions">
                <button id="apply-filters-btn" class="primary-btn">Apply Filters</button>
                <button id="clear-filters-btn" class="secondary-btn">Clear All</button>
                <button id="save-filter-preset-btn" class="secondary-btn">Save Preset</button>
            </div>

            <!-- Saved Presets -->
            <div class="filter-section">
                <h3>💾 Saved Presets</h3>
                <select id="filter-presets">
                    <option value="">Select a preset...</option>
                </select>
                <button id="load-preset-btn" class="small-btn">Load</button>
                <button id="delete-preset-btn" class="small-btn danger">Delete</button>
            </div>
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
    <script src="js/filters.js?v=<?php echo time(); ?>"></script>
</body>
</html>
