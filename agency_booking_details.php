<?php
// Page title for header
$page_title = "Booking Details";

// Includes
require_once 'includes/agency_header.php';
require_once 'includes/agency_sidebar.php';
require_once 'config/db_connect.php';

// Booking ID from URL
if (!isset($_GET['id'])) {
    echo "<script>alert('Missing booking ID'); window.location='agency_bookings.php';</script>";
    exit();
}

$booking_id = $_GET['id'];

// Fetch booking details with client + package info
try {
    $sql = "SELECT 
                b.*, 
                c.name AS client_name, 
                c.email AS client_email,
                c.phone AS client_phone,
                p.title AS package_title,
                p.image_path AS package_image
            FROM bookings b
            JOIN clients c ON b.client_id = c.client_id
            JOIN packages p ON b.package_id = p.package_id
            WHERE b.booking_id = :booking_id
              AND b.agency_id = :agency_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'booking_id' => $booking_id,
        'agency_id'  => $_SESSION['agency_id']
    ]);

    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo "<script>alert('Booking not found'); window.location='agency_bookings.php';</script>";
        exit();
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="page-container">

    <header class="page-header">
        <h1><i class="bi bi-receipt"></i> Booking Details</h1>
        <p>Review booking information, payment slip and manage trip status.</p>
    </header>

    <div class="content-card booking-details-card">
        <div class="booking-details-grid">

            <div class="booking-col">
                <section class="details-section">
                    <h2 class="details-title">Client Information</h2>
                    <p><strong>Name:</strong> <?= htmlspecialchars($booking['client_name']); ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($booking['client_email']); ?></p>
                    <?php if (!empty($booking['client_phone'])): ?>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($booking['client_phone']); ?></p>
                    <?php else: ?>
                        <p><strong>Phone:</strong> <em>Not provided</em></p>
                    <?php endif; ?>
                </section>

                <section class="details-section">
                    <h2 class="details-title">Package Details</h2>
                    <img src="img/<?= htmlspecialchars($booking['package_image']); ?>" 
                         class="details-package-image" alt="Package Image">
                    <p><strong>Package:</strong> <?= htmlspecialchars($booking['package_title']); ?></p>
                    <p><strong>Travel Date:</strong> <?= htmlspecialchars($booking['travel_date']); ?></p>
                    <p><strong>Travelers:</strong> <?= htmlspecialchars($booking['num_travelers']); ?></p>
                    <p>
                        <strong>Trip Type:</strong>
                        <?php 
                            if (!empty($booking['trip_type'])) {
                                echo htmlspecialchars(ucfirst($booking['trip_type'])); 
                            } else {
                                echo "Standard"; 
                            }
                        ?>
                    </p>
                    <p><strong>Total Price:</strong> PKR <?= number_format($booking['total_price']); ?></p>
                    <p><strong>Commission:</strong> PKR <?= number_format($booking['commission']); ?></p>
                </section>
            </div>

            <div class="booking-col">
                <section class="details-section">
                    <h2 class="details-title">Payment & Status</h2>
                    <p>
                        <strong>Payment Method:</strong>
                        <?= htmlspecialchars($booking['payment_method'] ?? 'Not Provided'); ?>
                    </p>

                    <p>
                        <strong>Current Status:</strong>
                        <span class="status-badge <?= strtolower($booking['booking_status']); ?>">
                            <?= htmlspecialchars($booking['booking_status']); ?>
                        </span>
                    </p>

                    <?php if (!empty($booking['payment_slip_path'])): ?>
                        <div class="payment-slip-box">
                            <h3 class="details-subtitle">Payment Slip Uploaded:</h3>
                            <a href="<?= htmlspecialchars($booking['payment_slip_path']); ?>" target="_blank">
                                <img src="<?= htmlspecialchars($booking['payment_slip_path']); ?>" 
                                     class="payment-slip-img" alt="Payment Slip">
                            </a>
                        </div>
                    <?php else: ?>
                        <p><i>No payment slip uploaded.</i></p>
                    <?php endif; ?>
                </section>

                <section class="details-section">
                    <div class="action-buttons booking-actions">
                        <?php if ($booking['booking_status'] === 'Pending'): ?>
                            <a href="agency_mark_paid.php?id=<?= $booking_id; ?>" 
                               class="btn-success"
                               onclick="return confirm('Mark this payment as PAID?');">
                                <i class="bi bi-check2-circle"></i> Mark as Paid
                            </a>
                        <?php elseif ($booking['booking_status'] === 'Paid'): ?>
                            <a href="agency_mark_completed.php?id=<?= $booking_id; ?>" 
                               class="btn-completed"
                               onclick="return confirm('Mark this trip as COMPLETED?');">
                                <i class="bi bi-flag"></i> Mark as Completed
                            </a>
                        <?php endif; ?>
                    </div>

                    <a href="agency_bookings.php" class="btn-back">
                        ← Back to Bookings
                    </a>
                </section>
            </div>

        </div>
    </div>

</div>

<?php require_once 'includes/agency_footer.php'; ?>