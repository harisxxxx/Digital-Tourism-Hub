<?php
// 1. Set the page title
$page_title = 'Bookings'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- DATABASE FETCH FOR BOOKINGS ---
try {
    // We select bookings and JOIN clients and packages to get their names/titles
    $sql = "SELECT 
                b.booking_id, 
                b.booking_status, 
                b.num_travelers, 
                b.total_price,
                b.trip_type,                         -- NEW: trip type
                c.name AS client_name,
                p.title AS package_title,
                DATE_FORMAT(b.booking_date, '%M %d, %Y') AS formatted_booking_date
            FROM bookings b
            JOIN clients c ON b.client_id = c.client_id
            JOIN packages p ON b.package_id = p.package_id
            WHERE b.agency_id = :agency_id 
            ORDER BY b.booking_date DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['agency_id' => $_SESSION['agency_id']]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error fetching bookings: " . $e->getMessage();
    $bookings = []; 
}

// 4. Include the sidebar
require_once 'includes/agency_sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-calendar-check"></i> Bookings & Orders</h1>
        <p>Review and manage all client bookings for your packages.</p>
    </header>

    <div class="content-card">
        <table class="packages-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Package Booked</th>
                    <th>Booked On</th>
                    <th>Travelers</th>
                    <th>Trip Type</th>         <!-- NEW COLUMN -->
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding: 20px;">
                            You have no active bookings at the moment.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['package_title']); ?></td>
                            <td><?php echo htmlspecialchars($booking['formatted_booking_date']); ?></td>
                            <td><?php echo htmlspecialchars($booking['num_travelers']); ?></td>
                            <td>
                                <?php 
                                    // Fallback in case trip_type is null/empty
                                    echo htmlspecialchars($booking['trip_type'] ?: 'Standard');
                                ?>
                            </td>
                            <td>PKR <?php echo number_format($booking['total_price']); ?></td>
                            <td>
                                <span class="status-badge <?php echo strtolower(htmlspecialchars($booking['booking_status'])); ?>">
                                    <?php echo htmlspecialchars($booking['booking_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="agency_booking_details.php?id=<?php echo $booking['booking_id']; ?>" class="btn-action btn-view" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 5. Includes the footer
require_once 'includes/agency_footer.php'; 
?>
