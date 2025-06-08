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
    <header>
        <h1>Epistol</h1>
        <button id="new-email-btn">New Email</button>
    </header>
    <main class="main-layout"> <!- Added a class for flexbox styling -->
        <aside id="groups-sidebar">
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
        </aside>
        <div id="feed-container">
            <!-- Email threads will be loaded here by JavaScript -->
        </div>
    </main>

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

    <!-- Person Profile Modal -->
    <div id="profile-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-btn" id="close-profile-modal-btn">&times;</span>
            <h2 id="profile-name"></h2>
            <p>Email(s): <span id="profile-emails"></span></p>
            <p>Notes: <span id="profile-notes"></span></p>
            <h3>Associated Threads:</h3>
            <div id="profile-threads-container">
                <!-- Threads will be loaded here by JavaScript -->
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2023 Epistol</p>
    </footer>
    <script src="js/api.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
