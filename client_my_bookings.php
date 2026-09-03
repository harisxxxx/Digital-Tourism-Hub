<?php
// 1. Set the page title
$page_title = 'My Bookings'; 

// 2. Includes (FIXED to use your original file names)
require_once 'includes/header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- DATABASE FETCH FOR BOOKINGS & STATS ---
try {
    $client_id = $_SESSION['client_id'];

    // Query 1: Get Stats
    $sql_stats = "SELECT 
                    COUNT(CASE WHEN booking_status = 'Completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN booking_status = 'Confirmed' OR booking_status = 'Pending' OR booking_status = 'Paid' THEN 1 END) as upcoming,
                    SUM(CASE WHEN booking_status = 'Completed' THEN total_price ELSE 0 END) as total_spent
                  FROM bookings 
                  WHERE client_id = :client_id";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute(['client_id' => $client_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    $completed_count = $stats['completed'] ?? 0;
    $upcoming_count = $stats['upcoming'] ?? 0;
    $total_spent = $stats['total_spent'] ?? 0;
    // If loyalty points value isn't set elsewhere, ensure a default 0 to avoid undefined notices
    $loyalty_points = $loyalty_points ?? 0;
    
    // Query 2: Get Upcoming Trips (Pending, Paid or Confirmed)
    // NOTE: include 'Paid' so when agency clicks "Mark as Paid" the booking remains visible here with a Paid badge
    $sql_upcoming = "SELECT 
                        b.*, 
                        p.title AS package_title, 
                        p.image_path,
                        a.agency_name,
                        DATE_FORMAT(b.travel_date, '%M %d - %Y') AS formatted_travel_date
                   FROM bookings b
                   JOIN packages p ON b.package_id = p.package_id
                   JOIN agencies a ON b.agency_id = a.agency_id
                   WHERE b.client_id = :client_id 
                     AND (b.booking_status = 'Pending' OR b.booking_status = 'Paid' OR b.booking_status = 'Confirmed')
                   ORDER BY b.travel_date ASC";
    $stmt_upcoming = $pdo->prepare($sql_upcoming);
    $stmt_upcoming->execute(['client_id' => $client_id]);
    $upcoming_trips = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);

    // Query 3: Get Past Trips (Completed)
     $sql_past = "SELECT 
                        b.*, 
                        p.title AS package_title,
                        DATE_FORMAT(b.travel_date, '%M %Y') AS formatted_travel_date
                   FROM bookings b
                   JOIN packages p ON b.package_id = p.package_id
                   WHERE b.client_id = :client_id AND b.booking_status = 'Completed'
                   ORDER BY b.travel_date DESC";
    $stmt_past = $pdo->prepare($sql_past);
    $stmt_past->execute(['client_id' => $client_id]);
    $past_trips = $stmt_past->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    echo "Error fetching bookings: " . $e->getMessage();
    $upcoming_trips = [];
    $past_trips = [];
    $completed_count = 0;
    $upcoming_count = 0;
    $total_spent = 0;
}

// 4. --- MESSAGE HANDLING ---
$form_message = '';
if (isset($_GET['success']) && $_GET['success'] == 'booked') {
    $form_message = "<p class='message success'><i class='bi bi-check-circle-fill'></i> Booking successful! Your request is now pending confirmation from the agency.</p>";
}

// 5. Includes (FIXED to use your original file names)
require_once 'includes/sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-journal-bookmark"></i> My Bookings</h1>
        <p>Manage your trip bookings and reservations</p>
        <a href="client_tour_packages.php" class="btn-primary-action"><i class="bi bi-plus-lg"></i> New Booking</a>
    </header>

    <?php echo $form_message; ?>

    <div class="stats-card-row">
        <div class="stat-card-item green">
            <i class="bi bi-check-circle"></i>
            <span class="stat-label">Completed</span>
            <span class="stat-value"><?php echo $completed_count; ?></span>
        </div>
        <div class="stat-card-item blue">
            <i class="bi bi-calendar-event"></i>
            <span class="stat-label">Upcoming</span>
            <span class="stat-value"><?php echo $upcoming_count; ?></span>
        </div>
        <div class="stat-card-item yellow">
            <i class="bi bi-star-fill"></i>
            <span class="stat-label">Points Earned</span>
            <span class="stat-value"><?php echo number_format($loyalty_points); ?></span>
        </div>
        <div class="stat-card-item purple">
            <i class="bi bi-cash-coin"></i>
            <span class="stat-label">Total Spent</span>
            <span class="stat-value">PKR <?php echo number_format($total_spent); ?></span>
        </div>
    </div>

    <div class="bookings-section">
        <h2>Upcoming Trips</h2>
        
        <?php if (empty($upcoming_trips)): ?>
            <p>You have no upcoming trips planned. <a href="client_tour_packages.php">Why not book one?</a></p>
        <?php else: ?>
            <?php foreach ($upcoming_trips as $trip): ?>
                <article class="booking-card no-image">
                    <div class="booking-details">
                        <span class="booking-title"><?php echo htmlspecialchars($trip['package_title']); ?></span>
                        <span class="booking-agency"><?php echo htmlspecialchars($trip['agency_name']); ?></span>
                        <div class="booking-info">
                            <div>
                                <span class="info-label">Travel Date</span>
                                <span class="info-value"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($trip['formatted_travel_date']); ?></span>
                            </div>
                            <div>
                                <span class="info-label">Travelers</span>
                                <span class="info-value"><i class="bi bi-people"></i> <?php echo htmlspecialchars($trip['num_travelers']); ?> people</span>
                            </div>
                            <div>
                                <span class="info-label">Total Amount</span>
                                <span class="info-value"><i class="bi bi-currency-dollar"></i> PKR <?php echo number_format($trip['total_price']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="booking-actions">
                        <?php
                            // Render proper badge for Pending / Paid / Confirmed
                            $status = $trip['booking_status'];
                            if ($status === 'Pending'): ?>
                                <span class="status-badge pending">
                                    <i class="bi bi-clock-history"></i> Pending Payment
                                </span>
                        <?php elseif ($status === 'Paid'): ?>
                                <span class="status-badge paid">
                                    <i class="bi bi-check-circle"></i> Paid
                                </span>
                        <?php elseif ($status === 'Confirmed'): ?>
                                <span class="status-badge confirmed">
                                    <i class="bi bi-check-circle"></i> Confirmed
                                </span>
                        <?php else: // fallback - show raw status ?>
                                <span class="status-badge">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                        <?php endif; ?>
                    </div>

                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="bookings-section">
        <h2>Past Trips</h2>
        
        <?php if (empty($past_trips)): ?>
            <p>You have no completed trips yet.</p>
        <?php else: ?>
            <?php foreach ($past_trips as $trip): ?>
                <?php $trip_points = round($trip['total_price'] / 100); ?>
                <article class="past-trip-card">
                    <div class="past-trip-main">
                        <i class="bi bi-check-circle-fill"></i>

                        <span class="past-trip-title">
                            <?php echo htmlspecialchars($trip['package_title']); ?>
                        </span>

                        <span class="past-trip-date">
                            • <?php echo htmlspecialchars($trip['formatted_travel_date']); ?>
                        </span>

                        <span class="past-trip-points">
                            • +<?php echo number_format($trip_points); ?> pts
                        </span>

                        <span class="past-trip-price">
                            • PKR <?php echo number_format($trip['total_price']); ?>
                        </span>
                    </div>

                    <div class="past-trip-actions">
                        <a href="client_write_review.php?id=<?php echo $trip['booking_id']; ?>" class="btn-write-review">
                            <i class="bi bi-pencil-square"></i> Write Review
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
// 5. Includes the modal, JavaScript, and closing tags
require_once 'includes/footer.php'; 
?>
