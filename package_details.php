<?php
// 1. Start session and include database
session_start();
require_once 'config/db_connect.php';

// IMPORTANT: tell sidebar which menu is active
$page_title = 'Tour Packages';

// 2. Check if package id exists
$package_id = $_GET['id'] ?? null;

if (!$package_id || !is_numeric($package_id)) {
    header("Location: client_dashboard.php");
    exit();
}

try {
    // Fetch Package & Agency Details
    $sql = "SELECT p.*, a.agency_name, a.profile_image_path, a.is_verified 
            FROM packages p
            JOIN agencies a ON p.agency_id = a.agency_id
            WHERE p.package_id = :package_id LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['package_id' => $package_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        header("Location: client_dashboard.php?error=notfound");
        exit();
    }

    if (empty($package['description'])) {
        $package['description'] = "No description provided for this package yet. Contact the agency for more information.";
    }

    // Decode suitable_for JSON
    $suitable_for = [];
    if (!empty($package['suitable_for'])) {
        $suitable_for = json_decode($package['suitable_for'], true);
        // Ensure it's an array (handle legacy data)
        if (!is_array($suitable_for)) {
            $suitable_for = [];
        }
    }

    // --- 1. PACKAGE RATING (Product Score) ---
    // Average of reviews ONLY for this package
    $pkg_rating_sql = "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews 
                        FROM reviews 
                        WHERE package_id = :package_id";
    $pkg_stmt = $pdo->prepare($pkg_rating_sql);
    $pkg_stmt->execute(['package_id' => $package_id]);
    $pkg_data = $pkg_stmt->fetch(PDO::FETCH_ASSOC);

    $pkg_avg_rating = $pkg_data['avg_rating'] !== null ? round($pkg_data['avg_rating'], 1) : "New";
    $pkg_total_reviews = isset($pkg_data['total_reviews']) ? (int)$pkg_data['total_reviews'] : 0;


    // --- 2. AGENCY RATING (Seller Reputation) ---
    // Average of ALL reviews for this agency
    $agency_id = (int)$package['agency_id'];
    $ag_rating_sql = "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews 
                      FROM reviews 
                      WHERE agency_id = :agency_id";
    $ag_stmt = $pdo->prepare($ag_rating_sql);
    $ag_stmt->execute(['agency_id' => $agency_id]);
    $ag_data = $ag_stmt->fetch(PDO::FETCH_ASSOC);

    $agency_avg_rating = $ag_data['avg_rating'] !== null ? round($ag_data['avg_rating'], 1) : "New";
    $agency_total_reviews = isset($ag_data['total_reviews']) ? (int)$ag_data['total_reviews'] : 0;


    // --- 3. FETCH TEXT REVIEWS ---
    // Get actual comments to display at bottom
    $rev_sql = "SELECT r.*, c.name AS client_name, c.avatar_path 
                FROM reviews r
                JOIN clients c ON r.client_id = c.client_id
                WHERE r.package_id = :package_id
                ORDER BY r.review_date DESC";
    $rev_stmt = $pdo->prepare($rev_sql);
    $rev_stmt->execute(['package_id' => $package_id]);
    $client_reviews = $rev_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching package: " . $e->getMessage());
}

// Header + Sidebar
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-container">
    <div class="package-details-layout">

        <div class="package-main-content">
            <div class="package-image-container">
                <img src="img/<?php echo htmlspecialchars($package['image_path']); ?>" 
                     alt="<?php echo htmlspecialchars($package['title']); ?>">
            </div>

            <header class="package-header">
                <span class="package-destination-tag">
                    <?php echo htmlspecialchars($package['destination']); ?>
                </span>
                <h1><?php echo htmlspecialchars($package['title']); ?></h1>

                <div class="package-meta-info">
                    <span><i class="bi bi-clock"></i> <?php echo htmlspecialchars($package['duration_days']); ?> Days</span>

                    <?php if ($pkg_total_reviews > 0): ?>
                        <span>
                            <i class="bi bi-star-fill" style="color: #f59e0b;"></i>
                            <strong><?php echo htmlspecialchars($pkg_avg_rating); ?></strong> 
                            <span style="color: #6b7280; margin-left: 5px;">(<?php echo $pkg_total_reviews; ?> Reviews)</span>
                        </span>
                    <?php else: ?>
                        <span>
                            <i class="bi bi-star" style="color: #ccc;"></i> No reviews yet
                        </span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="package-description">
                <h2>About This Tour</h2>
                <p><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
            </div>

            <div class="package-reviews-section" style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.5rem; margin-bottom: 20px;">Client Reviews</h2>
                
                <?php if (empty($client_reviews)): ?>
                    <p style="color: var(--text-dark-muted); font-style: italic;">No reviews yet for this package.</p>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($client_reviews as $rev): ?>
                            <div class="review-item" style="display: flex; gap: 15px; margin-bottom: 20px; background: #f9fafb; padding: 15px; border-radius: 10px; border: 1px solid #f0f0f0;">
                                <div class="reviewer-avatar">
                                    <?php 
                                    $avatar = !empty($rev['avatar_path']) ? 'uploads/avatars/' . $rev['avatar_path'] : 'img/default_user.png'; 
                                    // Fallback if file doesn't exist
                                    if (!file_exists($avatar)) $avatar = 'img/default_user.png';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="User" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                </div>
                                
                                <div class="review-content">
                                    <h5 style="margin: 0 0 5px 0; font-size: 1rem; font-weight: 600;"><?php echo htmlspecialchars($rev['client_name']); ?></h5>
                                    
                                    <div class="review-stars" style="color: #f59e0b; font-size: 0.85rem; margin-bottom: 8px;">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <?php if($i <= $rev['rating']): ?>
                                                <i class="bi bi-star-fill"></i>
                                            <?php else: ?>
                                                <i class="bi bi-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <span style="color: #9ca3af; font-size: 0.8rem; margin-left: 10px;">
                                            <?php echo date('M d, Y', strtotime($rev['review_date'])); ?>
                                        </span>
                                    </div>
                                    
                                    <p style="margin: 0; color: #4b5563; font-size: 0.95rem; line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars($rev['review_text'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            </div>

        <aside class="package-sidebar">

            <div class="booking-box">
                <div class="booking-price">
                    <span class="price-label">Starting from</span>
                    <span class="price-value">PKR <?php echo number_format($package['price']); ?></span>
                </div>

                <?php if (!empty($suitable_for)) { ?>
                <div class="suitable-tags" style="margin-bottom: 15px;">
                    <strong style="display:block; margin-bottom:5px; font-size:0.9rem;">Suitable For:</strong>
                    <?php foreach ($suitable_for as $type) { ?>
                        <span class="status-badge pending" style="margin-right: 5px; background: #eff6ff; color: #3b82f6; border:none;"><?php echo ucfirst($type); ?></span>
                    <?php } ?>
                </div>
                <?php } ?>

                <form action="booking_step1.php" method="POST">
                    <input type="hidden" name="package_id" value="<?php echo $package['package_id']; ?>">

                    <div class="form-group">
                        <label for="travel_date">Travel Date</label>
                        <input type="date" id="travel_date" name="travel_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="num_travelers">Travelers</label>
                        <input type="number" id="num_travelers" name="num_travelers" class="form-control" value="1" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="trip_type">Traveling As</label>
                        <select name="trip_type" id="trip_type" class="form-control" required>
                            <option value="" disabled selected>-- Select Type --</option>
                            
                            <?php if (in_array("solo", $suitable_for)) { ?>
                                <option value="solo">Solo Traveler</option>
                            <?php } ?>
                            
                            <?php if (in_array("couple", $suitable_for)) { ?>
                                <option value="couple">Couple / Honeymoon</option>
                            <?php } ?>
                            
                            <?php if (in_array("family", $suitable_for)) { ?>
                                <option value="family">Family</option>
                            <?php } ?>
                            
                            <?php if (in_array("friends", $suitable_for)) { ?>
                                <option value="friends">Friends / Group</option>
                            <?php } ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-book-now">Book Now</button>
                </form>
            </div>

            <div class="agency-info-box">
                <h4>Organized by</h4>
                <div class="agency-info-header">
                    <img src="<?php echo htmlspecialchars($package['profile_image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($package['agency_name']); ?>">
                    <div class="agency-name-details">
                        <strong><?php echo htmlspecialchars($package['agency_name']); ?></strong>
                        
                        <?php if($package['is_verified']): ?>
                            <span class="verified-badge-small"><i class="bi bi-patch-check-fill"></i> Verified</span>
                        <?php endif; ?>

                        <div style="font-size: 0.85rem; color: #f59e0b; margin-top: 4px;">
                            <i class="bi bi-star-fill"></i> 
                            <b><?php echo $agency_avg_rating; ?></b> 
                            <span style="color: #6b7280;">(<?php echo $agency_total_reviews; ?> reviews)</span>
                        </div>
                    </div>
                </div>
                <a href="agency_public_profile.php?id=<?php echo $package['agency_id']; ?>" class="btn-contact-agency">
                    View Agency Profile
                </a>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tripSelect   = document.getElementById('trip_type');
    const travelersInp = document.getElementById('num_travelers');

    if (!tripSelect || !travelersInp) return;

    function applyRules() {
        const type = tripSelect.value;

        if (type === 'couple') {
            // Couple: always exactly 2, not editable
            travelersInp.value = 2;
            travelersInp.min = 2;
            travelersInp.readOnly = true;
        } 
        else if (type === 'solo') {
            // NEW: Solo: always exactly 1, not editable (LOCKED)
            travelersInp.value = 1;
            travelersInp.min = 1;
            travelersInp.readOnly = true;
        } 
        else {
            // Other types (Family/Friends): editable, min 1
            travelersInp.readOnly = false;
            travelersInp.min = 1;
            if (!travelersInp.value || parseInt(travelersInp.value, 10) < 1) {
                travelersInp.value = 1;
            }
        }
    }

    // When user changes trip type
    tripSelect.addEventListener('change', applyRules);

    // Extra safety: prevent typing if locked
    travelersInp.addEventListener('input', function () {
        if (tripSelect.value === 'couple') {
            travelersInp.value = 2;
        }
        if (tripSelect.value === 'solo') {
            travelersInp.value = 1;
        }
    });

    // Run once on load
    applyRules();
});
</script>

<?php require_once 'includes/footer.php'; ?>