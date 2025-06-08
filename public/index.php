<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epistol - Your Email Feed</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>Epistol</h1>
        <button id="new-email-btn">New Email</button>
    </header>
    <main>
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
                <div class="form-actions">
                    <button type="submit" id="send-email-btn">Send</button>
                    <button type="button" id="cancel-compose-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2023 Epistol</p>
    </footer>
    <script src="js/api.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
