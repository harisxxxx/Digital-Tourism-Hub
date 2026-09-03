<?php
// 1. Set the page title
$page_title = 'Revenue'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- DATABASE FETCH FOR REVENUE & COMMISSION ---
try {
    $agency_id = $_SESSION['agency_id'];

    // Query 1: Get Revenue Stats AND Commission Stats
    // FIX APPLIED: Added "AND commission_paid = 0" to the commission calculation.
    // Also included 'Confirmed' bookings so it matches the Admin's invoice total exactly.
    $sql_stats = "SELECT 
                    SUM(CASE WHEN booking_status = 'Completed' THEN total_price ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN booking_status = 'Pending' THEN total_price ELSE 0 END) as pending_revenue,
                    SUM(CASE WHEN (booking_status = 'Completed' OR booking_status = 'Confirmed') AND commission_paid = 0 THEN commission ELSE 0 END) as total_commission_due,
                    COUNT(booking_id) as total_bookings
                  FROM bookings 
                  WHERE agency_id = :agency_id";
    
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute(['agency_id' => $agency_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    $total_revenue    = $stats['total_revenue'] ?? 0;
    $pending_revenue  = $stats['pending_revenue'] ?? 0;
    $total_commission = $stats['total_commission_due'] ?? 0; // Now updates to 0 when Admin marks paid
    $total_bookings   = $stats['total_bookings'] ?? 0;

    // Query 2: Get Recent Completed Transactions
    $sql_recent = "SELECT 
                        b.total_price,
                        DATE_FORMAT(b.booking_date, '%M %d, %Y') AS formatted_booking_date,
                        c.name AS client_name,
                        p.title AS package_title
                   FROM bookings b
                   JOIN clients c ON b.client_id = c.client_id
                   JOIN packages p ON b.package_id = p.package_id
                   WHERE b.agency_id = :agency_id 
                     AND b.booking_status = 'Completed'
                   ORDER BY b.booking_date DESC
                   LIMIT 5"; // Get last 5 transactions
            
    $stmt_recent = $pdo->prepare($sql_recent);
    $stmt_recent->execute(['agency_id' => $agency_id]);
    $recent_transactions = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error fetching revenue: " . $e->getMessage();
    $bookings            = [];
    $total_revenue       = 0;
    $pending_revenue     = 0;
    $total_commission    = 0;
    $total_bookings      = 0;
    $recent_transactions = [];
}

// 4. Include the sidebar
require_once 'includes/agency_sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-wallet2"></i> Revenue</h1>
        <p>Track your earnings, commission dues, and transaction history.</p>
    </header>

    <div class="stats-card-row">
        <div class="stat-card-item green">
            <i class="bi bi-cash-stack"></i>
            <span class="stat-label">Total Completed Revenue</span>
            <span class="stat-value">PKR <?php echo number_format($total_revenue, 2); ?></span>
        </div>

        <div class="stat-card-item yellow">
            <i class="bi bi-clock-history"></i>
            <span class="stat-label">Pending Revenue</span>
            <span class="stat-value">PKR <?php echo number_format($pending_revenue, 2); ?></span>
        </div>

        <div class="stat-card-item red">
            <i class="bi bi-arrow-down-circle"></i>
            <span class="stat-label">Commission Payable</span>
            <span class="stat-value">PKR <?php echo number_format($total_commission, 2); ?></span>
        </div>

        <div class="stat-card-item blue">
            <i class="bi bi-journal-check"></i>
            <span class="stat-label">Total Bookings</span>
            <span class="stat-value"><?php echo $total_bookings; ?></span>
        </div>
    </div>

    <div class="bookings-section">
        <h2>Recent Transactions (Confirmed)</h2>
        <div class="content-card">
            <table class="packages-table">
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Package</th>
                        <th>Date</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_transactions)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding: 20px;">You have no confirmed transactions yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $txn): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($txn['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($txn['package_title']); ?></td>
                                <td><?php echo htmlspecialchars($txn['formatted_booking_date']); ?></td>
                                <td>PKR <?php echo number_format($txn['total_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// 5. Includes the footer
require_once 'includes/agency_footer.php'; 
?>