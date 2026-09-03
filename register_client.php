<?php
// register_client.php
require_once 'config/db_connect.php'; 

$reg_message = '';

// === Server-side validation (Option A: strict) ===
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get form data (trim)
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');          // NEW
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $entered_ref_code = isset($_POST['referral_code']) ? trim($_POST['referral_code']) : '';

    // Validation rules (Option A)
    // Name: letters and spaces only, 3-100 chars
    if ($name === '' || !preg_match('/^[A-Za-z\s]{3,100}$/', $name)) {
        $errors[] = "Full name is required and should contain only letters and spaces (3-100 chars).";
    }

    // Email format
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }

    // Phone: Pakistani format 03XXXXXXXXX or +923XXXXXXXXX
    if ($phone === '' || !preg_match('/^(?:\+92|0)3\d{9}$/', $phone)) {
        $errors[] = "Please provide a valid Pakistani phone number (03XXXXXXXXX or +923XXXXXXXXX).";
    }

    // Password: min 8 chars and must contain at least one digit
    if (strlen($password) < 8 || !preg_match('/\d/', $password)) {
        $errors[] = "Password must be at least 8 characters and include at least one number.";
    }

    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // If no validation errors so far, check database constraints (email uniqueness)
    if (empty($errors)) {
        try {
            $sql_check = "SELECT client_id FROM clients WHERE email = :email";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute(['email' => $email]);

            if ($stmt_check->fetch()) {
                $errors[] = "This email is already registered. Try logging in.";
            }
        } catch (PDOException $e) {
            // DB error while validating uniqueness
            $errors[] = "Server error while validating email. Please try again later.";
            // error_log($e->getMessage());
        }
    }

    // If any errors, prepare $reg_message to display and DO NOT proceed with insert
    if (!empty($errors)) {
        $reg_message = "<div class='message error'><ul style='margin:0;padding-left:18px;'>";
        foreach ($errors as $err) {
            $reg_message .= "<li>" . htmlspecialchars($err) . "</li>";
        }
        $reg_message .= "</ul></div>";
    } else {
        // No errors: proceed with original registration flow (referral + insert + rewards)
        // 2. SECURELY hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // --- REFERRAL LOGIC START ---
            $referrer_id = null;

            if (!empty($entered_ref_code)) {
                // Check if the code exists in DB
                $sql_find_ref = "SELECT client_id FROM clients WHERE referral_code = :code LIMIT 1";
                $stmt_find_ref = $pdo->prepare($sql_find_ref);
                $stmt_find_ref->execute(['code' => $entered_ref_code]);
                $referrer_row = $stmt_find_ref->fetch(PDO::FETCH_ASSOC);

                if ($referrer_row) {
                    $referrer_id = $referrer_row['client_id'];
                }
            }
            // --- REFERRAL LOGIC END ---

            // 4. Insert the new client (with referred_by if applicable)
            //    NOTE: phone column added
            $sql_insert = "INSERT INTO clients (name, email, phone, password_hash, referred_by) 
                           VALUES (:name, :email, :phone, :password_hash, :referred_by)";
            $stmt_insert = $pdo->prepare($sql_insert);
            
            $stmt_insert->execute([
                'name'          => $name,
                'email'         => $email,
                'phone'         => $phone,
                'password_hash' => $password_hash,
                'referred_by'   => $referrer_id
            ]);

            // [FIX] GET THE NEW CLIENT ID IMMEDIATELY
            $new_client_id = $pdo->lastInsertId();

            // 5. HANDLE REWARDS (BOTH PARTIES)
            if ($referrer_id) {
                $points_reward = 500;

                // A. Reward the REFERRER (Existing User)
                $reason_ref = "Referral Bonus: Invited " . $name;
                $stmt_reward_ref = $pdo->prepare("INSERT INTO points_activity (client_id, points_amount, reason, activity_date) VALUES (:cid, :pts, :reason, NOW())");
                $stmt_reward_ref->execute(['cid' => $referrer_id, 'pts' => $points_reward, 'reason' => $reason_ref]);

                $pdo->prepare("UPDATE clients SET loyalty_points = loyalty_points + :pts WHERE client_id = :cid")
                    ->execute(['pts' => $points_reward, 'cid' => $referrer_id]);

                // B. Reward the NEW USER (Welcome Bonus)
                $reason_new = "Welcome Bonus: Used Referral Code";
                $stmt_reward_new = $pdo->prepare("INSERT INTO points_activity (client_id, points_amount, reason, activity_date) VALUES (:cid, :pts, :reason, NOW())");
                $stmt_reward_new->execute(['cid' => $new_client_id, 'pts' => $points_reward, 'reason' => $reason_new]);

                $pdo->prepare("UPDATE clients SET loyalty_points = loyalty_points + :pts WHERE client_id = :cid")
                    ->execute(['pts' => $points_reward, 'cid' => $new_client_id]);
                
                $reg_message = "<p class='message success'><i class='bi bi-check-circle'></i> Registration successful! You earned 500 Welcome Points. <a href='login_client.php'>Login</a>.</p>";
            } else {
                $reg_message = "<p class='message success'><i class='bi bi-check-circle'></i> Registration successful! You can now <a href='login_client.php'>Login</a>.</p>";
            }
        } catch (PDOException $e) {
            $reg_message = "<p class='message error'><i class='bi bi-shield-slash'></i> Database error. Please try again later.</p>";
            // error_log($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Registration - Digital Tourism Hub</title>
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
        .message.error { background-color: #fee2e2; color: #b91c1c; }
        .message.success { background-color: #dcfce7; color: #15803d; }
        .registration-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
        }
        .registration-link a { font-weight: 600; }
        .login-modal .close-btn {
    position: absolute;
    top: 15px;
    right: 15px;     /* ← THIS FIXES IT */
    background: none;
    border: none;
    font-size: 1.4rem;
    color: var(--text-dark-muted);
    cursor: pointer;
    transition: 0.2s;
}

.login-modal .close-btn:hover {
    color: var(--blue-primary);
    transform: scale(1.1);
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
            <i class="bi bi-person-plus"></i>
            <h2>Client Registration</h2>
        </div>
        <p class="subtitle">Join now to start planning your AI-powered trip!</p>

        <?php echo $reg_message; ?>
        <form id="registerClientForm" method="POST" action="register_client.php" novalidate>
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" placeholder="Your Full Name" required>

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="your.email@example.com" required>

            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" placeholder="03xx-xxxxxxx" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Create a password" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>

            <label for="referral_code">Referral Code <small>(Optional)</small></label>
            <input type="text" id="referral_code" name="referral_code" placeholder="e.g. REF-HARI-1234">

            <button type="submit" class="btn-login">
                <i class="bi bi-person-add"></i> Create Account
            </button>
        </form>

        <p class="registration-link">
            Already have an account? <a href="login_client.php">Login Here</a>
        </p>
    </div>

</div>

<!-- client-side validation + site scripts -->
<script src="scripts.js"></script>
</body>
</html>
