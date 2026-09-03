<?php
// agency_mark_completed.php
session_start();
require_once 'config/db_connect.php';

// Only logged-in agencies
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

// Booking ID check
if (!isset($_GET['id'])) {
    header("Location: agency_bookings.php");
    exit();
}

$booking_id = $_GET['id'];
$agency_id  = $_SESSION['agency_id'];

try {
    // 1. FIRST, get the client_id AND total_price for this booking
    $sql_get_client = "SELECT client_id, total_price FROM bookings 
                       WHERE booking_id = :booking_id AND agency_id = :agency_id";
    $stmt_get_client = $pdo->prepare($sql_get_client);
    $stmt_get_client->execute(['booking_id' => $booking_id, 'agency_id' => $agency_id]);
    $booking_data = $stmt_get_client->fetch(PDO::FETCH_ASSOC);

    if ($booking_data) {
        $client_id = $booking_data['client_id'];
        $total_price = $booking_data['total_price'];
    } else {
        // Handle case where booking isn't found
        header("Location: agency_bookings.php");
        exit();
    }

    // 2. UPDATE the status to 'Completed'
    // Only allow completing bookings that are currently PAID
    $sql_update = "UPDATE bookings
                   SET booking_status = 'Completed'
                   WHERE booking_id = :booking_id
                     AND agency_id  = :agency_id
                     AND booking_status = 'Paid'";

    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        'booking_id' => $booking_id,
        'agency_id'  => $agency_id
    ]);

    // Check if the update actually happened (row count > 0)
    if ($stmt_update->rowCount() > 0 && $client_id) {

        // ==============================================================
        //  NEW: AWARD POINTS BASED ON TRIP PRICE (10 pts per 1000 PKR)
        // ==============================================================
        $points_earned = floor($total_price / 1000) * 10;

        if ($points_earned > 0) {
            $reason_price = "Trip Completed (Booking #$booking_id)";

            // Update Client Balance
            $sql_update_pts = "UPDATE clients SET loyalty_points = loyalty_points + :pts WHERE client_id = :cid";
            $stmt_pts = $pdo->prepare($sql_update_pts);
            $stmt_pts->execute(['pts' => $points_earned, 'cid' => $client_id]);

            // Log Activity
            $sql_log_pts = "INSERT INTO points_activity (client_id, points_amount, reason, activity_date) 
                            VALUES (:cid, :pts, :reason, NOW())";
            $stmt_log = $pdo->prepare($sql_log_pts);
            $stmt_log->execute(['cid' => $client_id, 'pts' => $points_earned, 'reason' => $reason_price]);
        }
        // ==============================================================

        // ==============================================================
        //  AUTOMATIC MILESTONE REWARD SYSTEM (5, 20, 50 Trips)
        // ==============================================================
        
        // A. Count TOTAL completed trips for this client (including the one just finished)
        $sql_count = "SELECT COUNT(*) FROM bookings 
                      WHERE client_id = :cid 
                      AND booking_status = 'Completed'";
        $stmt_c = $pdo->prepare($sql_count);
        $stmt_c->execute(['cid' => $client_id]);
        $trip_count = (int)$stmt_c->fetchColumn();

        // B. Define Milestones [Trips Needed => Points Reward]
        $milestones = [
            5  => 1000,   // Reward for 5 trips
            20 => 4000,   // Reward for 20 trips
            50 => 10000   // Reward for 50 trips
        ];

        // C. Check if the current count matches a milestone
        if (array_key_exists($trip_count, $milestones)) {
            
            $points_to_give = $milestones[$trip_count];
            $reason_text = "Milestone: Completed $trip_count Trips";

            // D. Check if we already gave points for this specific milestone
            // (Prevents double-rewarding)
            $sql_check = "SELECT 1 FROM points_activity 
                          WHERE client_id = :cid 
                          AND reason = :reason 
                          LIMIT 1";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute(['cid' => $client_id, 'reason' => $reason_text]);

            if (!$stmt_check->fetchColumn()) {
                // E. INSERT THE POINTS
                $sql_reward = "INSERT INTO points_activity (client_id, points_amount, reason, activity_date) 
                               VALUES (:cid, :pts, :reason, NOW())";
                $stmt_reward = $pdo->prepare($sql_reward);
                $stmt_reward->execute([
                    'cid' => $client_id,
                    'pts' => $points_to_give,
                    'reason' => $reason_text
                ]);

                // Also update the main clients table cache
                $sql_update_client = "UPDATE clients 
                                      SET loyalty_points = loyalty_points + :pts 
                                      WHERE client_id = :cid";
                $stmt_client_upd = $pdo->prepare($sql_update_client);
                $stmt_client_upd->execute(['pts' => $points_to_give, 'cid' => $client_id]);
            }
        }
        // ==============================================================
    }

    header("Location: agency_booking_details.php?id=$booking_id&success=completed");
    exit();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>