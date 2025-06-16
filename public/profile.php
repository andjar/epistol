<?php
require_once '../src/db.php'; // Adjust path as necessary
require_once '../src/helpers.php'; // Adjust path as necessary

$profile_id = $_GET['id'] ?? null;

if (!$profile_id) {
    // Option 1: Display an error message
    // die("Error: Profile ID is missing.");

    // Option 2: Redirect to homepage (or an error page)
    header("Location: index.php?error=missing_profile_id");
    exit;
}

// At this point, $profile_id is available.
// In a real application, you would fetch profile data from the database using $profile_id.
// For now, we'll just use it to potentially display the ID.

$page_title = "User Profile"; // Default title

// Example: Fetch basic profile data (replace with actual DB query)
// $stmt = $pdo->prepare("SELECT name FROM People WHERE person_id = ?");
// $stmt->execute([$profile_id]);
// $profile = $stmt->fetch(PDO::FETCH_ASSOC);
// if ($profile && $profile['name']) {
//     $page_title = htmlspecialchars($profile['name']) . " - Profile";
// } else {
//     $page_title = "Profile Not Found";
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
    <header style="background-color: #007bff; color: white; padding: 10px 20px; text-align: center;">
        <h1>User Profile Page</h1>
    </header>

    <div class="page-container">
        <p><a href="index.php" class="back-link">Back to Feed</a></p>

        <?php if ($profile_id): ?>
            <h1 id="profile-page-name">Profile for ID: <?php echo htmlspecialchars($profile_id); ?></h1>

            <div id="profile-emails-container">
                <h2>Emails</h2>
                <p>john.doe@example.com, jane.doe@example.com (Placeholder)</p>
            </div>

            <div id="profile-notes-container">
                <h2>Notes</h2>
                <p>This is a placeholder for user notes. (Placeholder)</p>
            </div>

            <div id="profile-threads-container">
                <!-- Content will be dynamically injected by profile.js -->
                <p>Loading correspondence...</p>
            </div>
        <?php else: ?>
            <h1>Profile Not Found</h1>
            <p>The requested profile could not be found or no ID was provided.</p>
        <?php endif; ?>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 30px; font-size: 0.9em; color: #666; background-color: #f0f0f0;">
        <p>&copy; <?php echo date("Y"); ?> Epistol</p>
    </footer>
    <script src="js/api.js"></script>
    <script src="js/profile.js" defer></script>
</body>
</html>
