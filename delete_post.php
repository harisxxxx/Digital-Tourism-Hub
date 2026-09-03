<?php
// 1. Start session and include database
session_start();
require_once 'config/db_connect.php';

// 2. Check if the user is a logged-in agency
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

// 3. Get the Post ID from the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // If no ID is provided, just go back
    header("Location: agency_dashboard.php?error=missing_id");
    exit();
}

$post_id_to_delete = $_GET['id'];
$agency_id = $_SESSION['agency_id'];

try {
    // 4. SECURITY CHECK: Fetch the post to make sure the logged-in agency OWNS it
    $stmt = $pdo->prepare("SELECT agency_id, post_image_path FROM agency_posts WHERE post_id = :post_id");
    $stmt->execute(['post_id' => $post_id_to_delete]);
    $post = $stmt->fetch();

    if (!$post) {
        // Post doesn't exist
        header("Location: agency_dashboard.php?error=not_found");
        exit();
    }

    if ($post['agency_id'] !== $agency_id) {
        // This agency does NOT own this post. Permission denied.
        header("Location: agency_dashboard.php?error=permission_denied");
        exit();
    }

    // 5. Delete the Image File from the server (if one exists)
    if (!empty($post['post_image_path']) && file_exists($post['post_image_path'])) {
        // file_exists() checks if the file is actually on the server
        // unlink() deletes the file
        unlink($post['post_image_path']);
    }

    // 6. Delete the Post from the Database
    $stmt_delete = $pdo->prepare("DELETE FROM agency_posts WHERE post_id = :post_id");
    $stmt_delete->execute(['post_id' => $post_id_to_delete]);

    // 7. Redirect back with a success message
    $_SESSION['success_message'] = "Post deleted successfully!";
    header("Location: agency_dashboard.php?success=deleted");
    exit();

} catch (PDOException $e) {
    // Handle any database errors
    $_SESSION['error_message'] = "Database error: Could not delete post.";
    header("Location: agency_dashboard.php?error=db_error");
    exit();
}
?>