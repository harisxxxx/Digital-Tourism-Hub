<?php
// 1. Start session and include database
session_start();
require_once 'config/db_connect.php';

// 2. Check if the user is a logged-in agency
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

// 3. Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 4. Get data from the form
    $agency_id    = $_SESSION['agency_id'];
    $post_content = $_POST['post_content'] ?? '';

    // --- NEW: Get Optional Fields ---
    // Use trim() and check if empty. If so, set to NULL.
    $destination = !empty(trim($_POST['destination'] ?? '')) ? trim($_POST['destination']) : NULL;
    $duration    = !empty(trim($_POST['duration'] ?? ''))    ? trim($_POST['duration'])    : NULL;
    $price       = !empty(trim($_POST['price'] ?? ''))       ? trim($_POST['price'])       : NULL;

    // NEW: Linked package (optional)
    $package_id = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : NULL;

    $image_path = NULL; 

    // Handle file upload
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == UPLOAD_ERR_OK) {
        $target_dir  = "uploads/agency_posts/"; 
        $file_name   = uniqid() . "-" . basename($_FILES["post_image"]["name"]); 
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["post_image"]["tmp_name"]);
        if ($check !== false) {
            if ($_FILES["post_image"]["size"] > 5000000) { // 5MB
                $_SESSION['error_message'] = "Sorry, your file is too large (max 5MB).";
                header("Location: agency_dashboard.php?error=file_too_large");
                exit();
            }
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
                $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                header("Location: agency_dashboard.php?error=invalid_file_type");
                exit();
            }
            if (move_uploaded_file($_FILES["post_image"]["tmp_name"], $target_file)) {
                $image_path = $target_file; 
            } else {
                $_SESSION['error_message'] = "Sorry, there was an error uploading your file.";
                header("Location: agency_dashboard.php?error=upload_failed");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "File is not an image.";
            header("Location: agency_dashboard.php?error=not_image");
            exit();
        }
    }

    if (empty($post_content)) {
        $_SESSION['error_message'] = "Post content cannot be empty.";
        header("Location: agency_dashboard.php?error=emptycontent");
        exit();
    }

    // 6. Insert into database using PDO (with new package_id column)
    try {
        $sql = "INSERT INTO agency_posts 
                    (agency_id, post_content, post_image_path, package_destination, package_duration, package_price, package_id) 
                VALUES 
                    (:agency_id, :post_content, :image_path, :destination, :duration, :price, :package_id)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            'agency_id'    => $agency_id,
            'post_content' => $post_content,
            'image_path'   => $image_path,
            'destination'  => $destination,
            'duration'     => $duration,
            'price'        => $price,
            'package_id'   => $package_id
        ]);

        $_SESSION['success_message'] = "Post published successfully!";
        header("Location: agency_dashboard.php?success=posted");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
        die("Error: " . $e->getMessage()); 
    }

} else {
    header("Location: agency_dashboard.php");
    exit();
}
?>
