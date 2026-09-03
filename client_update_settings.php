<?php
// client_update_settings.php

session_start();
require_once 'config/db_connect.php';

// Must be logged in
if (!isset($_SESSION['client_id'])) {
    header("Location: login_client.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// Grab form data
$client_name      = trim($_POST['client_name'] ?? '');
$client_phone     = trim($_POST['client_phone'] ?? ''); // <--- NEW: Get Phone Number
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

try {
    // Basic validation for name
    if ($client_name === '') {
        $_SESSION['settings_error'] = "Name cannot be empty.";
        header("Location: client_dashboard.php");
        exit();
    }

    // --- Fetch current client row (for password + existing avatar) ---
    $sqlClient = "SELECT password_hash, avatar_path FROM clients WHERE client_id = :client_id";
    $stmtClient = $pdo->prepare($sqlClient);
    $stmtClient->execute(['client_id' => $client_id]);
    $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);

    if (!$clientRow) {
        $_SESSION['settings_error'] = "Client not found.";
        header("Location: client_dashboard.php");
        exit();
    }

    $currentAvatar = $clientRow['avatar_path'] ?? null;

    // ----------------------------------------------------
    // 1) HANDLE AVATAR UPLOAD (if any)
    // ----------------------------------------------------
    // This is the filename that will be stored in DB (e.g. "client_3_...jpg")
    $avatar_filename = $currentAvatar; // default: keep old one

    if (!empty($_FILES['profile_avatar']['name'])) {
        $file      = $_FILES['profile_avatar'];
        $tmpPath   = $file['tmp_name'];
        $origName  = $file['name'];
        $fileSize  = $file['size'];
        $errorCode = $file['error'];

        if ($errorCode === UPLOAD_ERR_OK) {

            // Limit = 2 MB
            if ($fileSize > 2 * 1024 * 1024) {
                $_SESSION['settings_error'] = "Profile picture is too large. Max size is 2 MB.";
                header("Location: client_dashboard.php");
                exit();
            }

            // Validate extension
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed)) {
                $_SESSION['settings_error'] = "Only JPG and PNG images are allowed.";
                header("Location: client_dashboard.php");
                exit();
            }

            // Prepare upload folder
            $uploadDir = __DIR__ . '/uploads/avatars';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique file name
            $newFileName = 'client_' . $client_id . '_' . time() . '.' . $ext;
            $destPath    = $uploadDir . '/' . $newFileName;

            if (!move_uploaded_file($tmpPath, $destPath)) {
                $_SESSION['settings_error'] = "Failed to upload profile picture.";
                header("Location: client_dashboard.php");
                exit();
            }

            // Optionally delete old avatar if exists
            if ($currentAvatar) {
                $oldPath = $uploadDir . '/' . $currentAvatar;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            // Save new filename (only the file name for DB)
            $avatar_filename = $newFileName;

        } else {
            $_SESSION['settings_error'] = "Error uploading profile picture.";
            header("Location: client_dashboard.php");
            exit();
        }
    }

    // ----------------------------------------------------
    // 2) HANDLE PASSWORD CHANGE (optional)
    // ----------------------------------------------------
    $change_password = ($current_password !== '' || $new_password !== '' || $confirm_password !== '');

    if ($change_password) {
        // All password fields must be filled
        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $_SESSION['settings_error'] = "To change your password, fill in all password fields.";
            header("Location: client_dashboard.php");
            exit();
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['settings_error'] = "New password and confirm password do not match.";
            header("Location: client_dashboard.php");
            exit();
        }

        // Verify current password
        $password_hash = $clientRow['password_hash'];

        if (!password_verify($current_password, $password_hash)) {
            $_SESSION['settings_error'] = "Current password is incorrect.";
            header("Location: client_dashboard.php");
            exit();
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // --- UPDATED SQL: Includes phone ---
        $update_sql = "UPDATE clients
                       SET name = :name,
                           phone = :phone,
                           password_hash = :password_hash,
                           avatar_path = :avatar_path
                       WHERE client_id = :client_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            'name'          => $client_name,
            'phone'         => $client_phone, // <--- Add phone to parameters
            'password_hash' => $new_hash,
            'avatar_path'   => $avatar_filename,
            'client_id'     => $client_id
        ]);

        $_SESSION['settings_success'] = "Profile, password, and picture updated successfully.";
    } else {
        // Only update name + phone + avatar (No password change)
        
        // --- UPDATED SQL: Includes phone ---
        $update_sql = "UPDATE clients
                       SET name = :name,
                           phone = :phone,
                           avatar_path = :avatar_path
                       WHERE client_id = :client_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            'name'        => $client_name,
            'phone'       => $client_phone, // <--- Add phone to parameters
            'avatar_path' => $avatar_filename,
            'client_id'   => $client_id
        ]);

        $_SESSION['settings_success'] = "Profile updated successfully.";
    }

    // ----------------------------------------------------
    // 3) KEEP SESSION IN SYNC (used by header.php)
    // ----------------------------------------------------
    $_SESSION['client_name'] = $client_name;
    
    // Note: We don't typically store phone in session, but you can if needed.
    // The dashboard page will fetch it fresh from DB anyway.

    if ($avatar_filename) {
        // Session needs the RELATIVE WEB PATH so <img src="..."> works
        $_SESSION['client_avatar_path'] = 'uploads/avatars/' . $avatar_filename;
    } else {
        $_SESSION['client_avatar_path'] = null;
    }

    header("Location: client_dashboard.php");
    exit();

} catch (PDOException $e) {
    error_log("Error updating client settings: " . $e->getMessage());
    $_SESSION['settings_error'] = "Something went wrong while saving your settings.";
    header("Location: client_dashboard.php");
    exit();
}
?>