<?php
session_start();
require_once 'config/db_connect.php';

// Check if agency is logged in
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $agency_id = $_SESSION['agency_id'];
    $agency_name = $_POST['agency_name'];
    $business_phone = $_POST['business_phone'];
    $new_image_path = null;

    // --- 1. Handle File Upload (if a new file is provided) ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        
        // --- Get current image path to delete old one ---
        $stmt_old = $pdo->prepare("SELECT profile_image_path FROM agencies WHERE agency_id = :id");
        $stmt_old->execute(['id' => $agency_id]);
        $old_image_path = $stmt_old->fetchColumn();

        // --- File Upload Logic ---
        $target_dir = "uploads/agency_avatars/"; // MAKE SURE THIS FOLDER EXISTS!
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = uniqid() . "-" . basename($_FILES["profile_image"]["name"]);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Basic validation
        $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $new_image_path = $target_file; // Set new path

                // --- Delete old profile picture (if it's not the default one) ---
                if ($old_image_path && $old_image_path != 'img/agency_profile_placeholder.jpg' && file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
        }
    }

    // --- 2. Update Database ---
    try {
        if ($new_image_path) {
            // If new image was uploaded, update all fields
            $sql = "UPDATE agencies SET agency_name = :name, business_phone = :phone, profile_image_path = :image WHERE agency_id = :id";
            $params = [
                'name' => $agency_name,
                'phone' => $business_phone,
                'image' => $new_image_path,
                'id' => $agency_id
            ];
        } else {
            // If no new image, update only text fields
            $sql = "UPDATE agencies SET agency_name = :name, business_phone = :phone WHERE agency_id = :id";
            $params = [
                'name' => $agency_name,
                'phone' => $business_phone,
                'id' => $agency_id
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // --- 3. Update Session Variables ---
        // This makes the changes appear immediately on the page
        $_SESSION['agency_name'] = $agency_name;
        if ($new_image_path) {
            // We need to update the header's $agency_profile_image, but that's set in header.php
            // The safest way is to just let the header re-fetch it on next load.
        }

        // --- 4. Redirect Back ---
        header("Location: agency_dashboard.php?success=profile_updated");
        exit();

    } catch (PDOException $e) {
        die("Error updating profile: " . $e->getMessage());
    }

} else {
    header("Location: agency_dashboard.php");
    exit();
}
?>