<?php
// 1. Start session and include DB
session_start();
require_once 'config/db_connect.php';

// 2. Only logged-in clients can submit reviews
if (!isset($_SESSION['client_id'])) {
    header("Location: login_client.php");
    exit();
}

// 3. Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: client_my_bookings.php");
    exit();
}

// 4. Get data from form
$booking_id  = $_POST['booking_id'] ?? null;
$package_id  = $_POST['package_id'] ?? null;
$agency_id   = $_POST['agency_id'] ?? null;
$rating      = $_POST['rating'] ?? null;
$review_text = trim($_POST['review_text'] ?? '');
$client_id   = $_SESSION['client_id'];

// 5. Basic validation
if (!$booking_id || !$package_id || !$agency_id || !$rating) {
    $_SESSION['error_message'] = "Missing required review data.";
    header("Location: client_my_bookings.php");
    exit();
}

try {
    // 6. Check booking really belongs to this client and is Completed
    $stmt = $pdo->prepare("
        SELECT booking_id 
        FROM bookings 
        WHERE booking_id = :booking_id 
          AND client_id  = :client_id
          AND booking_status = 'Completed'
    ");
    $stmt->execute([
        'booking_id' => $booking_id,
        'client_id'  => $client_id
    ]);

    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = "This booking is not eligible for a review.";
        header("Location: client_my_bookings.php");
        exit();
    }

    // 7. Make sure this booking does not already have a review
    $stmt_check = $pdo->prepare("
        SELECT review_id 
        FROM reviews 
        WHERE booking_id = :booking_id
    ");
    $stmt_check->execute(['booking_id' => $booking_id]);

    if ($stmt_check->fetch()) {
        $_SESSION['error_message'] = "You have already submitted a review for this booking.";
        header("Location: client_my_bookings.php");
        exit();
    }

    // 8. Insert the review
    $stmt_insert = $pdo->prepare("
        INSERT INTO reviews 
            (booking_id, package_id, client_id, agency_id, rating, review_text, review_date)
        VALUES 
            (:booking_id, :package_id, :client_id, :agency_id, :rating, :review_text, NOW())
    ");

    $stmt_insert->execute([
        'booking_id'  => $booking_id,
        'package_id'  => $package_id,
        'client_id'   => $client_id,
        'agency_id'   => $agency_id,
        'rating'      => $rating,
        'review_text' => $review_text
    ]);

    // ==============================================================
    //  NEW: AWARD 100 POINTS FOR REVIEW
    // ==============================================================
    $points_review = 100;
    $reason_review = "Review Bonus (Booking #$booking_id)";

    // Update Client Balance
    $sql_update_pts = "UPDATE clients SET loyalty_points = loyalty_points + :pts WHERE client_id = :cid";
    $stmt_pts = $pdo->prepare($sql_update_pts);
    $stmt_pts->execute(['pts' => $points_review, 'cid' => $client_id]);

    // Log Activity
    $sql_log_pts = "INSERT INTO points_activity (client_id, points_amount, reason, activity_date) 
                    VALUES (:cid, :pts, :reason, NOW())";
    $stmt_log = $pdo->prepare($sql_log_pts);
    $stmt_log->execute(['cid' => $client_id, 'pts' => $points_review, 'reason' => $reason_review]);
    // ==============================================================

    // 9. Redirect with success message
    $_SESSION['success_message'] = "Thank you! Your review has been submitted and you earned 100 points.";
    header("Location: client_my_bookings.php");
    exit();

} catch (PDOException $e) {
    die("Error saving review: " . $e->getMessage());
}
?>