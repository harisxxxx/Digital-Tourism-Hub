<?php
// 1. Include the new secure PDO connection
require_once 'config/db_connect.php'; 

// 2. Start the session
session_start();

$login_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 3. Get data from form
        $email = $_POST['email'];
        $password = $_POST['password'];

        // 4. Prepare the SQL statement (include is_verified)
        $sql = "SELECT agency_id, agency_name, password_hash, email, is_verified 
                FROM agencies 
                WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        
        // 5. Execute the statement
        $stmt->execute(['email' => $email]);
        
        // 6. Fetch the agency
        $agency = $stmt->fetch();

        // 7. SECURELY verify the password
        if ($agency && password_verify($password, $agency['password_hash'])) {

            // Check approval
            if ((int)$agency['is_verified'] !== 1) {
                $login_message = "<p class='message error'><i class='bi bi-hourglass-split'></i> 
                    Your agency account is pending approval by the admin. 
                    You will be able to log in once it is approved.</p>";
            } else {
                // Approved: proceed with login
                session_regenerate_id(true); 
                
                $_SESSION['agency_id']    = $agency['agency_id'];
                $_SESSION['agency_name']  = $agency['agency_name'];
                $_SESSION['agency_email'] = $agency['email'];
                
                header("Location: agency_dashboard.php");
                exit();
            }
            
        } else {
            // Invalid email or password
            $login_message = "<p class='message error'><i class='bi bi-x-octagon'></i> Invalid Email or Password.</p>";
        }

    } catch (PDOException $e) {
        // Handle database errors
        $login_message = "<p class='message error'><i class='bi bi-shield-slash'></i> Database error. Please try again later.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agency Login - Digital Tourism Hub</title>
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
        .login-modal input[type="email"],
        .login-modal input[type="password"] {
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
        <a href="index.php" class="close-btn" aria-label="Close Login"><i class="bi bi-x"></i></a>

        <div class="login-modal-header">
            <i class="bi bi-arrow-right"></i>
            <h2>Agency Login</h2>
        </div>
        <p class="subtitle">Enter your credentials to access your dashboard</p>

        <?php echo $login_message; ?>

        <form method="POST" action="login_agency.php">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="your.email@example.com" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>

            <button type="submit" class="btn-login">
                <i class="bi bi-arrow-right"></i> Login to Your Account
            </button>
        </form>

        <p class="registration-link">
            Not yet registered? <a href="register_agency.php">Register Now</a>
        </p>
    </div>

</div>

</body>
</html>
