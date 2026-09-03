<?php
$page_title = 'AI Trip Results';
require_once 'includes/header.php';
require_once 'config/db_connect.php';
require_once 'includes/sidebar.php';

// --- 1. GET INPUTS ---
$destination = $_GET['destination'] ?? '';
$duration    = isset($_GET['duration']) ? (int)$_GET['duration'] : 0;
$travelers   = isset($_GET['travelers']) ? (int)$_GET['travelers'] : 1;
$season      = $_GET['season'] ?? '';
$trip_type   = $_GET['trip_type'] ?? '';

// --- 2. SMART MATCHING LOGIC (The "AI" Part) ---
try {
    // Base query: destination + duration range
    $sql = "SELECT p.*, a.agency_name 
            FROM packages p
            JOIN agencies a ON p.agency_id = a.agency_id
            WHERE p.destination LIKE :destination
              AND p.duration_days BETWEEN :min_days AND :max_days";

    $params = [
        'destination' => '%' . $destination . '%',
        'min_days'    => $duration - 2,
        'max_days'    => $duration + 2
    ];

    // If user chose a specific trip type (solo/couple/family/friends),
    // prefer packages whose suitable_for JSON contains that type.
    if ($trip_type !== '') {
        $sql .= " AND p.suitable_for LIKE :trip_type";
        // Match JSON like ["solo","family"] -> we look for "trip_type"
        $params['trip_type'] = '%"' . $trip_type . '"%';
    }

    $sql .= " ORDER BY p.price ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If strict match fails, try broad match: only destination (and optional trip_type)
    if (empty($matches)) {
        $sql_broad = "SELECT p.*, a.agency_name 
                      FROM packages p
                      JOIN agencies a ON p.agency_id = a.agency_id
                      WHERE p.destination LIKE :destination";

        $params_broad = [
            'destination' => '%' . $destination . '%'
        ];

        if ($trip_type !== '') {
            $sql_broad .= " AND p.suitable_for LIKE :trip_type";
            $params_broad['trip_type'] = '%"' . $trip_type . '"%';
        }

        $sql_broad .= " ORDER BY p.price ASC";

        $stmt_broad = $pdo->prepare($sql_broad);
        $stmt_broad->execute($params_broad);
        $matches = $stmt_broad->fetchAll(PDO::FETCH_ASSOC);
        $is_broad_match = true; // Flag to tell user we widened the search
    } else {
        $is_broad_match = false;
    }

} catch (PDOException $e) {
    $matches = [];
    $error = "Search error: " . $e->getMessage();
}

// Helper for nice label
function pretty_trip_type($type) {
    switch ($type) {
        case 'solo':   return 'Solo';
        case 'couple': return 'Couple / Honeymoon';
        case 'family': return 'Family';
        case 'friends':return 'Friends / Group';
        default:       return ucfirst($type);
    }
}
?>

<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-stars"></i> Your Personalized Itinerary</h1>
        <p>
            Based on your preference: 
            <strong><?php echo htmlspecialchars($destination); ?></strong> 
            for <strong><?php echo htmlspecialchars($duration); ?> days</strong>
            <?php if ($trip_type !== ''): ?>
                , traveling as <strong><?php echo htmlspecialchars(pretty_trip_type($trip_type)); ?></strong>
            <?php endif; ?>.
        </p>
    </header>

    <div class="ai-results-container">
        
        <?php if (empty($matches)): ?>
            <div class="no-posts-card">
                <i class="bi bi-emoji-frown"></i>
                <h3>No exact packages found</h3>
                <p>We couldn't find a package for <strong><?php echo htmlspecialchars($destination); ?></strong> matching your duration.</p>
                <a href="client_tour_packages.php" class="btn-primary-action" style="margin-top:15px;">Browse All Packages</a>
            </div>
        
        <?php else: ?>
            <?php if ($is_broad_match): ?>
                <div class="alert-box" style="background: #e0f2fe; color: #0369a1; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bae6fd;">
                    <i class="bi bi-info-circle-fill"></i> We couldn't find an exact match for <?php echo $duration; ?> days, but here are other top-rated options for <strong><?php echo htmlspecialchars($destination); ?></strong>!
                </div>
            <?php else: ?>
                <div class="alert-box" style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                    <i class="bi bi-check-circle-fill"></i> Great news! We found perfect matches for your trip.
                </div>
            <?php endif; ?>

            <div class="package-grid">
                <?php foreach ($matches as $package): ?>
                    <?php
                        // Decode suitable_for to show tags
                        $tags = [];
                        if (!empty($package['suitable_for'])) {
                            $decoded = json_decode($package['suitable_for'], true);
                            if (is_array($decoded)) {
                                $tags = $decoded;
                            }
                        }
                    ?>
                    <a href="package_details.php?id=<?php echo $package['package_id']; ?>" class="package-card-link">
                        <article class="package-card">
                            <img src="img/<?php echo htmlspecialchars($package['image_path']); ?>" alt="<?php echo htmlspecialchars($package['title']); ?>">
                            
                            <div class="package-details">
                                <h3><?php echo htmlspecialchars($package['title']); ?></h3>
                                <p class="package-info">
                                    <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($package['destination']); ?> 
                                    | <i class="bi bi-clock-fill"></i> <?php echo htmlspecialchars($package['duration_days']); ?> Days
                                </p>
                                <p class="package-agency">
                                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($package['agency_name']); ?>
                                </p>

                                <!-- NEW: Suitable For tags -->
                                <?php if (!empty($tags)): ?>
                                    <p style="margin:6px 0 4px;">
                                        <?php foreach ($tags as $t): ?>
                                            <span class="tag <?php echo htmlspecialchars($t); ?>" style="display:inline-block; padding:2px 8px; border-radius:999px; background:#eef2ff; color:#4f46e5; font-size:0.75rem; margin-right:4px; margin-bottom:2px;">
                                                <?php echo htmlspecialchars(pretty_trip_type($t)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>

                                <div class="package-price">
                                    PKR <?php echo number_format($package['price']); ?>
                                    <span style="display:block; font-size:0.8rem; color:#666; font-weight:400;">
                                        (Total for <?php echo $travelers; ?>: PKR <?php echo number_format($package['price'] * max($travelers,1)); ?>)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="overlay-label">AI Recommended</div>
                        </article>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php
require_once 'includes/footer.php'; 
?>
