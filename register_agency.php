<?php
// 1. Include the new secure PDO connection
require_once 'config/db_connect.php'; 

$reg_message = '';
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 2. Get form data (trim)
    $agency_name    = trim($_POST['agency_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? ''; // Raw password from form
    $confirm_password = $_POST['confirm_password'] ?? '';
    $business_phone = trim($_POST['business_phone'] ?? '');
    $cnic_number    = trim($_POST['cnic_number'] ?? '');

    // 3. Validate (Option A strict)
    // Agency name: allow letters, numbers, spaces and basic punctuation (3-150 chars)
    if ($agency_name === '' || !preg_match("/^[A-Za-z0-9\s\&\-\']{3,150}$/", $agency_name)) {
        $errors[] = "Agency name is required (3-150 chars). Avoid special symbols.";
    }

    // Email format
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }

    // Phone: Pakistani format
    if ($business_phone === '' || !preg_match('/^(?:\+92|0)3\d{9}$/', $business_phone)) {
        $errors[] = "Please provide a valid Pakistani phone number (03XXXXXXXXX or +923XXXXXXXXX).";
    }

    // CNIC: 13 digits OR XXXXX-XXXXXXX-X
    if ($cnic_number === '' || !preg_match('/^\d{5}-?\d{7}-?\d{1}$/', $cnic_number)) {
        $errors[] = "CNIC must be 13 digits or in format 12345-1234567-1.";
    }

    // Password: min 8 chars + at least one digit
    if (strlen($password) < 8 || !preg_match('/\d/', $password)) {
        $errors[] = "Password must be at least 8 characters and include at least one number.";
    }

    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // 4. SECURELY hash the password (only if no errors so far)
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    // 5. Handle CNIC image upload (kept same, but only proceed if validation passed so far)
    $cnic_image_path = null;
    if (empty($errors) && !empty($_FILES['cnic_image']['name'])) {
        $upload_dir = 'uploads/agency_cnic/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_name = $_FILES['cnic_image']['name'];
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $safe_ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));

        $new_name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;
        $target_path = $upload_dir . $new_name;

        if (move_uploaded_file($_FILES['cnic_image']['tmp_name'], $target_path)) {
            $cnic_image_path = $target_path;
        } else {
            $errors[] = "Failed to upload CNIC image. Please try again.";
        }
    } elseif (empty($errors) && empty($_FILES['cnic_image']['name'])) {
        // CNIC image required in the existing form
        $errors[] = "Please upload your CNIC image (front/back).";
    }

    // 6. If no validation errors, check DB uniqueness (email, cnic)
    if (empty($errors)) {
        try {
            $sql_check = "SELECT agency_id FROM agencies WHERE email = :email LIMIT 1";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute(['email' => $email]);

            if ($stmt_check->fetch()) {
                $errors[] = "This email is already registered. Try logging in.";
            } else {
                // check CNIC uniqueness
                $stmt_cnic = $pdo->prepare("SELECT agency_id FROM agencies WHERE cnic_number = :cnic LIMIT 1");
                $stmt_cnic->execute(['cnic' => $cnic_number]);
                if ($stmt_cnic->fetch()) {
                    $errors[] = "This CNIC is already registered with another agency.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Server error while validating agency details. Please try again later.";
            // error_log($e->getMessage());
        }
    }

    // If errors exist, show them; otherwise insert (your existing insertion logic)
    if (!empty($errors)) {
        $reg_message = "<div class='message error'><ul style='margin:0;padding-left:18px;'>";
        foreach ($errors as $err) {
            $reg_message .= "<li>" . htmlspecialchars($err) . "</li>";
        }
        $reg_message .= "</ul></div>";
    } else {
        try {
            $sql_insert = "INSERT INTO agencies 
                (agency_name, email, password_hash, business_phone, cnic_number, cnic_image_path, is_verified, registration_date) 
                VALUES 
                (:agency_name, :email, :password_hash, :business_phone, :cnic_number, :cnic_image_path, 0, NOW())";
            
            $stmt_insert = $pdo->prepare($sql_insert);
            
            $stmt_insert->execute([
                'agency_name'     => $agency_name,
                'email'           => $email,
                'password_hash'   => $password_hash,
                'business_phone'  => $business_phone,
                'cnic_number'     => $cnic_number,
                'cnic_image_path' => $cnic_image_path
            ]);

            $reg_message = "<p class='message success'><i class='bi bi-check-circle'></i> 
                Agency registration submitted! Your account is pending admin approval. 
                You will be able to log in once your agency is approved.</p>";
        } catch (PDOException $e) {
            $reg_message = "<p class='message error'><i class='bi bi-shield-slash'></i> Database error. Please try again later.</p>";
            // error_log($e->getMessage()); // Optional: for debugging
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agency Registration - Digital Tourism Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        .login-page-bg {
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-secondary));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-modal {
            background: var(--bg-light-1);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 100%;
            position: relative;
        }
        .login-modal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        .login-modal-header i {
            color: var(--blue-primary);
            font-size: 1.5rem;
        }
        .login-modal h2 {
            font-size: 1.6rem;
            color: var(--text-dark);
            margin: 0;
        }
        .login-modal .subtitle {
            font-size: 0.95rem;
            color: var(--text-dark-muted);
            margin-bottom: 25px;
        }
        .login-modal label {
            display: block;
            font-weight: 500;
            color: var(--text-dark);
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .login-modal input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius-md);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .login-modal input:focus {
            border-color: var(--blue-primary);
            outline: none;
        }
        .login-modal button.btn-login {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--blue-primary);
            color: var(--text-light);
            font-weight: 600;
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius-md);
            cursor: pointer;
            margin-top: 30px;
            transition: var(--transition-fast);
        }
        .login-modal button.btn-login:hover {
            background: var(--blue-secondary);
            transform: translateY(-1px);
        }
        .login-modal .demo-note {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-dark-muted);
            margin-top: 20px;
        }
        .login-modal .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-dark-muted);
            cursor: pointer;
        }
        .page-login-buttons {
            position: absolute;
            top: 30px;
            right: 40px;
            display: flex;
            gap: 12px;
        }
        .message {
            padding: 10px;
            border-radius: var(--border-radius-md);
            margin-top: 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message.error {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .message.success {
            background-color: #dcfce7;
            color: #15803d;
        }
        .registration-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
        }
        .registration-link a {
            font-weight: 600;
        }

        /* inline validation styles */
        .field-error { color: #d0342c; font-size: 0.9rem; margin-top: 0.25rem; }
        .invalid { border-color: #d0342c !important; box-shadow: none !important; }
    </style>
</head>
<body>

<div class="login-page-bg">

    <nav class="page-login-buttons">
        <a href="login_client.php" class="btn client">
            <i class="bi bi-person" aria-hidden="true"></i> Client Login
        </a>
        <a href="login_agency.php" class="btn agency">
            <i class="bi bi-building" aria-hidden="true"></i> Agency Login
        </a>
    </nav>

    <div class="login-modal">
        <a href="index.php" class="close-btn" aria-label="Close Registration"><i class="bi bi-x"></i></a>

        <div class="login-modal-header">
            <i class="bi bi-building-add"></i>
            <h2>Agency Registration</h2>
        </div>
        <p class="subtitle">Post your tours and reach thousands of clients!</p>

        <?php echo $reg_message; ?>

        <form id="registerAgencyForm" method="POST" action="register_agency.php" enctype="multipart/form-data" novalidate>
            <label for="agency_name">Agency Name</label>
            <input type="text" id="agency_name" name="agency_name" placeholder="ABC Tours & Travels" required>

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="agency.email@example.com" required>

            <label for="business_phone">Business Phone</label>
            <input type="text" id="business_phone" name="business_phone" placeholder="03XXXXXXXXX" required>

            <label for="cnic_number">CNIC Number</label>
            <input type="text" id="cnic_number" name="cnic_number" placeholder="12345-1234567-1" required>

            <label for="cnic_image">CNIC Image (Front / Back)</label>
            <input type="file" id="cnic_image" name="cnic_image" accept="image/*" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Create a password" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i> Register Agency
            </button>
        </form>

        <p class="registration-link">
            Already registered? <a href="login_agency.php">Login Here</a>
        </p>
    </div>

</div>

<!-- client-side validation + site scripts -->
<script src="scripts.js"></script>
</body>
</html>
