<?php
session_start();
require_once 'config/db_connect.php';

// Ensure the user is logged in as an agency
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

$agency_id = $_SESSION['agency_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Collect required fields (server-side sanitization)
    $title          = trim($_POST['title'] ?? '');
    $destination    = trim($_POST['destination'] ?? '');
    $duration_days  = trim($_POST['duration_days'] ?? '');
    $price_raw      = trim($_POST['price'] ?? '');
    $description    = trim($_POST['description'] ?? '');

    // traveler type selection
    $suitable_for = $_POST['suitable_for'] ?? null;

    // Couple price (optional)
    $couple_price_raw = trim($_POST['couple_price'] ?? '');

    // --- SERVER-SIDE VALIDATION per user's rules ---

    // 1) Title: must match pattern: number + optional hyphen/space + day(s) + space + text
    $title_ok = false;
    if ($title !== '') {
        // pattern: allow "10-day ..." or "10 Day ..." or "10-Day ..." (case-insensitive)
        if (preg_match('/^\d+(?:\s*[-]?\s*|\s+)(?:day|days)\b\s+[A-Za-z&\'\-\s]+$/i', $title)) {
            $title_ok = true;
        }
    }
    if (!$title_ok) {
        $_SESSION['error_message'] = "Package title invalid. It must start with duration like '10-day' followed by destination text (example: '10-day Naran Kaghan').";
        header("Location: agency_add_package.php");
        exit();
    }

    // 2) Destination: only letters/spaces/&/'/- (no numbers)
    if ($destination === '' || !preg_match("/^[A-Za-z&'\-\s]{2,}$/", $destination)) {
        $_SESSION['error_message'] = "Destination invalid. Use letters only (no numbers).";
        header("Location: agency_add_package.php");
        exit();
    }

    // 2b) Duration: integer only and >=1
    if ($duration_days === '' || !preg_match('/^\d+$/', $duration_days) || intval($duration_days) < 1) {
        $_SESSION['error_message'] = "Duration invalid. Enter a whole number of days (minimum 1).";
        header("Location: agency_add_package.php");
        exit();
    }
    $duration_days = intval($duration_days);

    // 3) Price positive
    if ($price_raw === '' || !is_numeric($price_raw) || floatval($price_raw) <= 0) {
        $_SESSION['error_message'] = "Base price must be a positive number.";
        header("Location: agency_add_package.php");
        exit();
    }
    $price = floatval($price_raw);

    // 3b) couple price positive if provided
    $couple_price = null;
    if (!empty($couple_price_raw)) {
        if (!is_numeric($couple_price_raw) || floatval($couple_price_raw) <= 0) {
            $_SESSION['error_message'] = "Couple price must be a positive number if provided.";
            header("Location: agency_add_package.php");
            exit();
        }
        $couple_price = floatval($couple_price_raw);
    }

    // 4) Description length >= 20
    if ($description === '' || mb_strlen($description) < 20) {
        $_SESSION['error_message'] = "Description must be at least 20 characters long.";
        header("Location: agency_add_package.php");
        exit();
    }

    // 5) Suitable_for must be present (same as original)
    if (!$suitable_for || !is_array($suitable_for) || count($suitable_for) === 0) {
        $_SESSION['error_message'] = "Please select at least one Suitable For option.";
        header("Location: agency_add_package.php");
        exit();
    }
    $suitable_for_json = json_encode(array_values($suitable_for), JSON_UNESCAPED_UNICODE);

    // --- Handle image upload: LEFT IN PLACE but NOT required ---
    $image_name = null;
    if (isset($_FILES['package_image']) && isset($_FILES['package_image']['error']) && $_FILES['package_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir  = "img/"; // keep same as your original
        // ensure directory exists
        if (!is_dir($target_dir)) {
            @mkdir($target_dir, 0755, true);
        }
        $image_ext   = strtolower(pathinfo($_FILES["package_image"]["name"], PATHINFO_EXTENSION));
        // quick sanity check for extension (not strict mime)
        $allowed_exts = ['jpg','jpeg','png','webp'];
        if (!in_array($image_ext, $allowed_exts)) {
            $_SESSION['error_message'] = "Uploaded image format not allowed. Allowed: jpg, png, webp.";
            header("Location: agency_add_package.php");
            exit();
        }
        $image_name  = uniqid('pkg_') . "." . $image_ext;
        $image_path  = $target_dir . $image_name;
        $check = getimagesize($_FILES["package_image"]["tmp_name"]);
        if ($check === false) {
            $_SESSION['error_message'] = "Uploaded file is not a valid image.";
            header("Location: agency_add_package.php");
            exit();
        }
        if (!move_uploaded_file($_FILES["package_image"]["tmp_name"], $image_path)) {
            $_SESSION['error_message'] = "Failed to upload image.";
            header("Location: agency_add_package.php");
            exit();
        }
    } else {
        // no file uploaded — image_name remains null (ok)
        $image_name = null;
    }

    // --- INSERT INTO DATABASE (PDO) ---
    try {
        $sql = "INSERT INTO packages 
                (agency_id, title, destination, price, duration_days, image_path, description, suitable_for, couple_price)
                VALUES 
                (:agency_id, :title, :destination, :price, :duration_days, :image_path, :description, :suitable_for, :couple_price)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':agency_id'     => $agency_id,
            ':title'         => $title,
            ':destination'   => $destination,
            ':price'         => $price,
            ':duration_days' => $duration_days,
            ':image_path'    => $image_name,  // may be null
            ':description'   => $description,
            ':suitable_for'  => $suitable_for_json,
            ':couple_price'  => $couple_price
        ]);

        $_SESSION['success_message'] = "New package created successfully!";
        header("Location: agency_my_packages.php");
        exit();

    } catch (PDOException $e) {
        // remove uploaded file on DB failure to avoid orphan files
        if (!empty($image_path) && file_exists($image_path)) {
            @unlink($image_path);
        }
        error_log("DB Error (agency_add_package_action): " . $e->getMessage());
        $_SESSION['error_message'] = "Database error while creating package.";
        header("Location: agency_add_package.php");
        exit();
    }
}

header("Location: agency_add_package.php");
exit();
?>
