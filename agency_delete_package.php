<?php
// 1. Start session and include database
session_start();
require_once 'config/db_connect.php';

// 2. Check if the user is a logged-in agency
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

// 3. Get the Package ID from the URL (e.g., ...delete_package.php?id=5)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // If no ID is provided, just go back
    $_SESSION['error_message'] = "Invalid package ID.";
    header("Location: agency_my_packages.php");
    exit();
}

$package_id_to_delete = $_GET['id'];
$agency_id = $_SESSION['agency_id'];

try {
    // 4. SECURITY CHECK: Fetch the package to make sure this agency OWNS it
    $stmt = $pdo->prepare("SELECT agency_id, image_path FROM packages WHERE package_id = :package_id");
    $stmt->execute(['package_id' => $package_id_to_delete]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        // Package doesn't exist
        $_SESSION['error_message'] = "Package not found.";
        header("Location: agency_my_packages.php");
        exit();
    }

    if ($package['agency_id'] !== $agency_id) {
        // This agency does NOT own this package. Permission denied.
        $_SESSION['error_message'] = "You do not have permission to delete this package.";
        header("Location: agency_my_packages.php");
        exit();
    }

    // 5. Delete the Image File from the 'img/' folder
    if (!empty($package['image_path'])) {
        $file_to_delete = 'img/' . $package['image_path'];
        
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete); // Deletes the file
        }
    }

    // 6. Delete the Package from the Database
    $stmt_delete = $pdo->prepare("DELETE FROM packages WHERE package_id = :package_id AND agency_id = :agency_id");
    $stmt_delete->execute([
        'package_id' => $package_id_to_delete,
        'agency_id' => $agency_id
    ]);

    // 7. Redirect back with a success message
    $_SESSION['success_message'] = "Package deleted successfully!";
    header("Location: agency_my_packages.php");
    exit();

} catch (PDOException $e) {
    // Handle any database errors
    $_SESSION['error_message'] = "Database error: Could not delete package. It might be linked to existing bookings.";
    header("Location: agency_my_packages.php");
    exit();
}
?>