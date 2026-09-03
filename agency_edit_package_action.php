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
    $agency_id     = $_SESSION['agency_id'];
    $package_id    = $_POST['package_id'];
    $title         = $_POST['title'];
    $destination   = $_POST['destination'];
    $duration_days = $_POST['duration_days'];
    $price         = $_POST['price'];
    $delete_image  = isset($_POST['delete_image']) ? 1 : 0;
    $description   = trim($_POST['description'] ?? '');

    // NEW: suitable_for & couple_price
    $suitable_for      = $_POST['suitable_for'] ?? [];
    $suitable_for_json = json_encode($suitable_for);

    $couple_price = null;
    if (in_array('couple', $suitable_for) && isset($_POST['couple_price']) && $_POST['couple_price'] !== '') {
        $couple_price = $_POST['couple_price'];
    }

    $new_image_path = null;
    $update_image   = false;

    // Simple validation (same idea as add)
    if (empty($title) || empty($destination) || empty($duration_days) || empty($price) || empty($description)) {
        $_SESSION['error_message'] = "Please fill in all required fields, including the package description.";
        header("Location: agency_edit_package.php?id=" . $package_id);
        exit();
    }

    // 5. --- CHECK OWNERSHIP AND GET OLD DATA ---
    try {
        $stmt_check = $pdo->prepare("SELECT agency_id, image_path FROM packages WHERE package_id = :package_id");
        $stmt_check->execute(['package_id' => $package_id]);
        $package = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$package || $package['agency_id'] !== $agency_id) {
            $_SESSION['error_message'] = "Permission denied.";
            header("Location: agency_my_packages.php");
            exit();
        }
        $current_image_path = $package['image_path'];

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: agency_edit_package.php?id=" . $package_id);
        exit();
    }

    // 6. --- HANDLE FILE UPLOAD / DELETION ---
    
    // Check if a new file is uploaded
    if (isset($_FILES['package_image']) && $_FILES['package_image']['error'] == UPLOAD_ERR_OK) {
        $target_dir     = "img/"; // Save to 'img' folder
        $imageFileType  = strtolower(pathinfo($_FILES["package_image"]["name"], PATHINFO_EXTENSION));
        $file_name      = uniqid('pkg_') . '.' . $imageFileType;
        $target_file    = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["package_image"]["tmp_name"], $target_file)) {
            $new_image_path = $file_name; // Store just the filename
            $update_image   = true;
            
            // Delete old image if it exists
            if ($current_image_path && file_exists('img/' . $current_image_path)) {
                unlink('img/' . $current_image_path);
            }
        }
    } 
    // Check if user wants to delete the image
    elseif ($delete_image) {
        if ($current_image_path && file_exists('img/' . $current_image_path)) {
            unlink('img/' . $current_image_path);
        }
        $new_image_path = NULL; // Set to NULL in database
        $update_image   = true;
    }

    // 7. --- UPDATE THE DATABASE ---
    try {
        if ($update_image) {
            // If image changed, update the image_path column too
            $sql = "UPDATE packages SET 
                        title         = :title, 
                        destination   = :destination, 
                        duration_days = :duration, 
                        price         = :price, 
                        description   = :description,
                        suitable_for  = :suitable_for,
                        couple_price  = :couple_price,
                        image_path    = :image 
                    WHERE package_id = :id AND agency_id = :agency_id";
            $params = [
                'title'         => $title,
                'destination'   => $destination,
                'duration'      => $duration_days,
                'price'         => $price,
                'description'   => $description,
                'suitable_for'  => $suitable_for_json,
                'couple_price'  => $couple_price,
                'image'         => $new_image_path,
                'id'            => $package_id,
                'agency_id'     => $agency_id
            ];
        } else {
            // If no image change, don't update the image_path column
            $sql = "UPDATE packages SET 
                        title         = :title, 
                        destination   = :destination, 
                        duration_days = :duration, 
                        price         = :price,
                        description   = :description,
                        suitable_for  = :suitable_for,
                        couple_price  = :couple_price
                    WHERE package_id = :id AND agency_id = :agency_id";
            $params = [
                'title'         => $title,
                'destination'   => $destination,
                'duration'      => $duration_days,
                'price'         => $price,
                'description'   => $description,
                'suitable_for'  => $suitable_for_json,
                'couple_price'  => $couple_price,
                'id'            => $package_id,
                'agency_id'     => $agency_id
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success_message'] = "Package updated successfully!";
        header("Location: agency_my_packages.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
        header("Location: agency_edit_package.php?id=" . $package_id);
        exit();
    }

} else {
    header("Location: agency_my_packages.php");
    exit();
}
?>
