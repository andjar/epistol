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
    <header style="background-color: #007bff; color: white; padding: 10px 20px; text-align: center;">
        <h1>Group Details Page</h1>
    </header>

    <div class="page-container">
        <p><a href="index.php" class="back-link">Back to Feed</a></p>

        <?php if ($group_id): ?>
            <h1 id="group-page-name">Group Information for ID: <?php echo htmlspecialchars($group_id); ?></h1>

            <div id="group-members-container">
                <h2>Group Members</h2>
                <ul class="members-list">
                    <li>Member 1 (Placeholder)</li>
                    <li>Member 2 (Placeholder)</li>
                    <li>Member 3 (Placeholder)</li>
                </ul>
            </div>

            <div id="group-feed-container">
                <h2>Group-Specific Feed / Discussions</h2>
                <p>Content specific to this group will appear here. (Placeholder)</p>
                <ul>
                    <li>Discussion Topic 1 (Placeholder)</li>
                    <li>Announcement: Meeting Next Week (Placeholder)</li>
                </ul>
            </div>
        <?php else: ?>
            <h1>Group Not Found</h1>
            <p>The requested group could not be found or no ID was provided.</p>
        <?php endif; ?>
    </div>

    <footer style="text-align: center; padding: 20px; margin-top: 30px; font-size: 0.9em; color: #666; background-color: #f0f0f0;">
        <p>&copy; <?php echo date("Y"); ?> Epistol</p>
    </footer>
    <script src="js/api.js"></script>
    <script src="js/group.js" defer></script>
</body>
</html>
