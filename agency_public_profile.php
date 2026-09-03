<?php
// agency_public_profile.php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/header.php';

// 1. Get Agency ID
$agency_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($agency_id <= 0) {
    echo "<div class='page-container'><p class='message error'>Agency not found.</p></div>";
    require_once 'includes/footer.php';
    exit();
}

try {
    // 2. Fetch Agency Details
    $stmt = $pdo->prepare("SELECT * FROM agencies WHERE agency_id = :id");
    $stmt->execute(['id' => $agency_id]);
    $agency = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agency) {
        echo "<div class='page-container'><p class='message error'>Agency not found.</p></div>";
        require_once 'includes/footer.php';
        exit();
    }

    // 3. Fetch Agency Stats (Rating & Reviews)
    $stmt_stats = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(review_id) as total_reviews 
                                 FROM reviews WHERE agency_id = :id");
    $stmt_stats->execute(['id' => $agency_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    $avg_rating = $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : "New";
    $total_reviews = $stats['total_reviews'];

    // 4. Fetch Active Packages by this Agency
    $stmt_pkgs = $pdo->prepare("SELECT * FROM packages WHERE agency_id = :id ORDER BY date_posted DESC");
    $stmt_pkgs->execute(['id' => $agency_id]);
    $packages = $stmt_pkgs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch All Reviews for this Agency
    $stmt_revs = $pdo->prepare("
        SELECT r.*, c.name as client_name, c.avatar_path, p.title as package_title 
        FROM reviews r 
        JOIN clients c ON r.client_id = c.client_id 
        JOIN packages p ON r.package_id = p.package_id
        WHERE r.agency_id = :id 
        ORDER BY r.review_date DESC LIMIT 10
    ");
    $stmt_revs->execute(['id' => $agency_id]);
    $reviews = $stmt_revs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="css/agency_profile.css">

<div class="page-container">
    
    <div class="agency-profile-header">
        <div class="agency-logo-container">
            <img src="<?php echo !empty($agency['profile_image_path']) ? htmlspecialchars($agency['profile_image_path']) : 'img/agency_profile_placeholder.jpg'; ?>" 
                 alt="Logo" 
                 class="agency-logo-img">
        </div>
        
        <div class="agency-info-content">
            <div class="agency-name-row">
                <h1><?php echo htmlspecialchars($agency['agency_name']); ?></h1>
                <?php if($agency['is_verified']): ?>
                    <span class="verified-badge-large" title="Verified Agency">
                        <i class="bi bi-patch-check-fill"></i>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="agency-meta-row">
                <span class="agency-meta-item">
                    <i class="bi bi-calendar3"></i> 
                    Member since <?php echo date('M Y', strtotime($agency['registration_date'])); ?>
                </span>
                <?php if(!empty($agency['business_phone'])): ?>
                <span class="agency-meta-item">
                    <i class="bi bi-telephone"></i> 
                    <?php echo htmlspecialchars($agency['business_phone']); ?>
                </span>
                <?php endif; ?>
                <span class="agency-meta-item">
                    <i class="bi bi-envelope"></i> 
                    <?php echo htmlspecialchars($agency['email']); ?>
                </span>
            </div>
        </div>
        
        <div class="agency-stats-box">
            <div class="stat-rating-large">
                <i class="bi bi-star-fill"></i> <?php echo $avg_rating; ?><span>/5</span>
            </div>
            <div class="stat-review-count">
                Based on <?php echo $total_reviews; ?> Reviews
            </div>
        </div>
    </div>

    <div class="agency-profile-content">
        
        <h2 class="profile-section-title">
            <i class="bi bi-box-seam"></i> Active Tour Packages (<?php echo count($packages); ?>)
        </h2>
        
        <?php if(empty($packages)): ?>
            <p class="message info">This agency has no active packages at the moment.</p>
        <?php else: ?>
            <div class="agency-packages-grid">
                <?php foreach($packages as $pkg): ?>
                    <a href="package_details.php?id=<?php echo $pkg['package_id']; ?>" class="package-card-link">
                        <div class="agency-package-card">
                            <div class="pkg-img-wrap">
                                <img src="img/<?php echo htmlspecialchars($pkg['image_path']); ?>" alt="Package Image">
                            </div>
                            <div class="pkg-content">
                                <h3 class="pkg-title"><?php echo htmlspecialchars($pkg['title']); ?></h3>
                                <div class="pkg-footer">
                                    <span class="pkg-days">
                                        <i class="bi bi-clock"></i> <?php echo $pkg['duration_days']; ?> Days
                                    </span>
                                    <span class="pkg-price">
                                        PKR <?php echo number_format($pkg['price']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="profile-section-title" style="margin-top: 60px;">
            <i class="bi bi-star"></i> Client Feedback
        </h2>
        
        <?php if(empty($reviews)): ?>
            <p class="message info">No reviews available for this agency yet.</p>
        <?php else: ?>
            <div class="agency-reviews-list">
                <?php foreach($reviews as $rev): ?>
                    <div class="agency-review-card">
                        <div class="review-avatar">
                            <?php 
                            $avatar = !empty($rev['avatar_path']) ? 'uploads/avatars/'.$rev['avatar_path'] : 'img/default_user.png';
                            if (!file_exists($avatar)) $avatar = 'img/default_user.png';
                            ?>
                            <img src="<?php echo htmlspecialchars($avatar); ?>" 
                                 class="review-avatar-img" alt="User">
                        </div>
                        
                        <div class="review-body">
                            <div class="review-header">
                                <h4 class="reviewer-name"><?php echo htmlspecialchars($rev['client_name']); ?></h4>
                                <span class="review-date"><?php echo date('M d, Y', strtotime($rev['review_date'])); ?></span>
                            </div>
                            
                            <div class="review-stars">
                                <?php 
                                for($i=1; $i<=5; $i++) {
                                    if($i <= $rev['rating']) {
                                        echo '<i class="bi bi-star-fill"></i>';
                                    } else {
                                        echo '<i class="bi bi-star"></i>'; // empty star
                                    }
                                }
                                ?>
                                <span class="review-context">on "<?php echo htmlspecialchars($rev['package_title']); ?>"</span>
                            </div>
                            
                            <p class="review-text"><?php echo nl2br(htmlspecialchars($rev['review_text'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>