<?php
session_start();
require_once 'config/db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: agency_bookings.php");
    exit();
}

$booking_id = $_GET['id'];

try {
    $sql = "UPDATE bookings
            SET booking_status = 'Paid'
            WHERE booking_id = :booking_id
              AND agency_id = :agency_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'booking_id' => $booking_id,
        'agency_id'  => $_SESSION['agency_id']
    ]);

    header("Location: agency_booking_details.php?id=$booking_id&success=paid");
    exit();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
