<?php
// ===============================
// client_popular_destinations.php
// ===============================

// 1. Set the page title
$page_title = 'Popular Destinations';

// 2. Includes
require_once 'includes/header.php';
require_once 'config/db_connect.php';
require_once 'includes/sidebar.php';

// 3. --- DATABASE FETCH FOR POPULAR PACKAGES ---
// We treat a package as "popular" if it has at least one booking
// with status 'Completed' or 'Paid' (real trips / paid trips).
try {
    $sql = "
        SELECT 
            p.package_id,
            p.title,
            p.destination,
            p.description,
            p.image_path,
            p.duration_days,
            p.price,
            a.agency_name,
            -- Count only real trips: Completed or Paid
            COALESCE(
                SUM(
                    CASE 
                        WHEN b.booking_status IN ('Completed', 'Paid') 
                        THEN 1 
                        ELSE 0 
                    END
                ), 0
            ) AS trips_booked
        FROM packages p
        JOIN agencies a 
            ON p.agency_id = a.agency_id
        LEFT JOIN bookings b 
            ON b.package_id = p.package_id
        GROUP BY p.package_id
        HAVING trips_booked > 0              -- only show packages that were actually booked
        ORDER BY trips_booked DESC,          -- most booked first
                 p.date_posted DESC          -- then newest first
        LIMIT 6;                            -- top 6 popular packages
    ";

    $stmt = $pdo->query($sql);
    $popular_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error fetching popular destinations: " . $e->getMessage();
    $popular_packages = [];
}
?>

<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-geo-alt"></i> Popular Destinations</h1>
        <p>Most visited places in Pakistan</p>
        <!-- Search bar removed on purpose -->
    </header>

    <!-- Category filter chips removed on purpose -->

    <div class="destination-grid">
        <?php if (empty($popular_packages)): ?>
            <p style="grid-column: 1 / -1;">
                No popular destinations yet. Once clients start completing trips, top destinations will appear here.
            </p>
        <?php else: ?>
            <?php foreach ($popular_packages as $pkg): ?>
                <?php
                    // Dynamic image path (same logic as client_tour_packages.php)
                    $imageFile = !empty($pkg['image_path']) 
                        ? 'img/' . $pkg['image_path'] 
                        : 'img/poe.jpg'; // fallback if no image set

                    // Short description (fallback if null)
                    $desc = $pkg['description'] ?? '';
                    if (strlen($desc) > 140) {
                        $desc = substr($desc, 0, 137) . '...';
                    }
                ?>
                <article class="destination-card">
                    <div class="dest-card-image">
                        <img src="<?php echo htmlspecialchars($imageFile); ?>" 
                             alt="<?php echo htmlspecialchars($pkg['title']); ?>">
                        <div class="dest-card-overlay">
                            <span class="dest-trending-tag">
                                <i class="bi bi-graph-up-arrow"></i> Trending
                            </span>
                            <h3><?php echo htmlspecialchars($pkg['title']); ?></h3>
                            <p><?php echo htmlspecialchars($pkg['destination']); ?></p>
                        </div>
                    </div>

                    <div class="dest-card-info">
                        <p>
                            <?php echo htmlspecialchars(
                                $desc ?: 'Discover this amazing destination with our curated tour package.'
                            ); ?>
                        </p>

                        <div class="dest-card-footer">
                            <span class="dest-rating">
                                <i class="bi bi-star-fill"></i>
                                Top choice • <?php echo (int)$pkg['trips_booked']; ?> bookings
                            </span>
                            <span class="dest-time">
                                Best Time: <b>All Year</b>
                                <!-- You can make this dynamic later if you add a column -->
                            </span>
                        </div>

                        <div class="dest-card-action">
                            <span>
                                <?php echo (int)$pkg['trips_booked']; ?> trips booked
                            </span>
                            <a href="package_details.php?id=<?php echo (int)$pkg['package_id']; ?>" 
                               class="btn-explore">
                                Explore
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// 5. Includes the footer
require_once 'includes/footer.php';
?>
