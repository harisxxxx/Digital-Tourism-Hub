<?php
// 1. Start session and include database
session_start();
require_once 'config/db_connect.php';

// 2. Check if the user is a logged-in client
if (!isset($_SESSION['client_id'])) {
    header("Location: login_client.php");
    exit();
}

// 3. Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 4. Get all the data from the form
    $package_id    = $_POST['package_id']    ?? null;
    $agency_id     = $_POST['agency_id']     ?? null;
    $travel_date   = $_POST['travel_date']   ?? null;
    $num_travelers = $_POST['num_travelers'] ?? null;
    $total_price   = $_POST['total_price']   ?? null;   // ORIGINAL total
    $trip_type     = $_POST['trip_type']     ?? null;   // <--- NEW: Capture trip type
    $client_id     = $_SESSION['client_id'];

    // Rewards
    $points_used_raw = isset($_POST['points_used']) ? (int)$_POST['points_used'] : 0;
    $POINTS_PER_PKR  = 40;

    // Payment fields
    $payment_method    = $_POST['payment_method'] ?? null;
    $payment_slip_path = null; // will set after upload

    // 5. Validate data
    if (empty($package_id) || empty($agency_id) || empty($travel_date) || 
        empty($num_travelers) || empty($total_price) || empty($client_id) ||
        empty($payment_method)) {

        header("Location: client_dashboard.php?error=booking_failed");
        exit();
    }

    // 5.1 Handle payment slip upload (simple validation)
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {

        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $original_name = $_FILES['payment_slip']['name'];
        $tmp_name      = $_FILES['payment_slip']['tmp_name'];

        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext)) {
            $upload_dir = 'uploads/payment_slips/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0775, true);
            }

            $new_filename = 'slip_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            $destination  = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $payment_slip_path = $destination;
            }
        }
        // If invalid extension or move failed, we just leave $payment_slip_path as null
    }

    try {

        // --- VALIDATE & APPLY POINTS ON SERVER SIDE ---
        $points_used   = max(0, $points_used_raw);
        $discount_pkr  = 0;

        if ($points_used > 0) {
            // 1) Get client's current points from DB
            $stmt_pts = $pdo->prepare("SELECT loyalty_points FROM clients WHERE client_id = :cid");
            $stmt_pts->execute(['cid' => $client_id]);
            $current_points = (int)($stmt_pts->fetchColumn() ?? 0);

            if ($points_used > $current_points) {
                $points_used = $current_points;
            }

            // 2) Cannot redeem more than booking total value
            $max_points_by_total = (int) floor($total_price * $POINTS_PER_PKR);
            if ($points_used > $max_points_by_total) {
                $points_used = $max_points_by_total;
            }

            // 3) Convert to PKR (round down to multiples of 40)
            $discount_pkr = intdiv($points_used, $POINTS_PER_PKR);
            $points_used  = $discount_pkr * $POINTS_PER_PKR; // align to 40-step

            if ($discount_pkr <= 0) {
                // not enough points to give at least 1 PKR
                $points_used  = 0;
                $discount_pkr = 0;
            }
        }

        // Final amount after points discount
        $final_total = $total_price - $discount_pkr;
        if ($final_total < 0) $final_total = 0;

        // --- FETCH COMMISSION RATE ---
        $stmt_settings = $pdo->query("SELECT default_commission_rate FROM platform_settings WHERE id = 1");
        $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
        $commission_rate = $settings['default_commission_rate'] ?? 10.00;

        // --- CALCULATE COMMISSION ON FINAL AMOUNT ---
        $commission_amount = $final_total * ($commission_rate / 100);

        // 6. Insert into the 'bookings' table
        // UPDATED: Added trip_type to columns and values
        $sql = "INSERT INTO bookings 
                (package_id, client_id, agency_id, travel_date, num_travelers, total_price, commission, booking_status, payment_method, payment_slip_path, trip_type) 
                VALUES 
                (:package_id, :client_id, :agency_id, :travel_date, :num_travelers, :total_price, :commission, :status, :payment_method, :payment_slip_path, :trip_type)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            'package_id'        => $package_id,
            'client_id'         => $client_id,
            'agency_id'         => $agency_id,
            'travel_date'       => $travel_date,
            'num_travelers'     => $num_travelers,
            'total_price'       => $final_total,     // store FINAL amount
            'commission'        => $commission_amount,
            'status'            => 'Pending',        // Pending = Pending Payment
            'payment_method'    => $payment_method,
            'payment_slip_path' => $payment_slip_path,
            'trip_type'         => $trip_type        // <--- NEW: Saving the trip type
        ]);

        $booking_id = $pdo->lastInsertId();

        // --- DEDUCT POINTS + LOG ACTIVITY ---
        if ($points_used > 0 && $discount_pkr > 0) {
            // Deduct from client
            $pdo->prepare("UPDATE clients SET loyalty_points = loyalty_points - :pts WHERE client_id = :cid")
                ->execute(['pts' => $points_used, 'cid' => $client_id]);

            // Log in points_activity
            $reason = "Redeemed {$points_used} points for PKR {$discount_pkr} discount (Booking #{$booking_id})";
            $stmt_pa = $pdo->prepare("
                INSERT INTO points_activity (client_id, points_amount, reason, activity_date) 
                VALUES (:cid, :pts, :reason, NOW())
            ");
            $stmt_pa->execute([
                'cid'    => $client_id,
                'pts'    => -1 * $points_used,
                'reason' => $reason
            ]);
        }

        // 7. Redirect to the "My Bookings" page
        header("Location: client_my_bookings.php?success=booked");
        exit();

    } catch (PDOException $e) {
        die("Error saving booking: " . $e->getMessage());
    }

} else {
    header("Location: client_dashboard.php");
    exit();
}
?>