<?php
// 1. Set the page title
$page_title = 'Ratings'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- DATABASE FETCH FOR REVIEWS & STATS ---
try {
    $agency_id = $_SESSION['agency_id'];

    // First, get all reviews for the list
    $sql_reviews = "SELECT 
                        r.rating, 
                        r.review_text,
                        DATE_FORMAT(r.review_date, '%M %d, %Y') AS formatted_review_date,
                        c.name AS client_name,
                        p.title AS package_title
                    FROM reviews r
                    JOIN clients c ON r.client_id = c.client_id
                    JOIN packages p ON r.package_id = p.package_id
                    WHERE r.agency_id = :agency_id 
                    ORDER BY r.review_date DESC";
            
    $stmt_reviews = $pdo->prepare($sql_reviews);
    $stmt_reviews->execute(['agency_id' => $agency_id]);
    $reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);

    // Second, get the rating statistics
    $sql_stats = "SELECT 
                    AVG(rating) as avg_rating, 
                    COUNT(review_id) as total_reviews 
                  FROM reviews 
                  WHERE agency_id = :agency_id";
    
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute(['agency_id' => $agency_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // Assign stats to variables
    $avg_rating = $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : 'N/A';
    $total_reviews = $stats['total_reviews'];

} catch (PDOException $e) {
    echo "Error fetching ratings: " . $e->getMessage();
    $reviews = [];
    $avg_rating = 'N/A';
    $total_reviews = 0;
}

// 4. Include the sidebar
require_once 'includes/agency_sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-star"></i> My Ratings</h1>
        <p>Review your overall performance and client feedback.</p>
    </header>

    <div class="rating-summary-card">
        <div class="rating-stat-box">
            <span class="rating-stat-value"><i class="bi bi-star-fill"></i> <?php echo $avg_rating; ?></span>
            <span class="rating-stat-label">Overall Rating</span>
        </div>
        <div class="rating-stat-box">
            <span class="rating-stat-value"><?php echo $total_reviews; ?></span>
            <span class="rating-stat-label">Total Reviews</span>
        </div>
    </div>

    <div class="reviews-container">
        <h2 class="section-title">All Reviews</h2>
        <?php if (empty($reviews)): ?>
            <div class="content-card" style="padding: 40px; text-align: center;">
                <h3>No reviews yet</h3>
                <p>When clients leave reviews, they will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <article class="review-card">
                    <div class="review-header">
                        <span class="review-rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi <?php echo ($i <= $review['rating']) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <span class="review-date"><?php echo htmlspecialchars($review['formatted_review_date']); ?></span>
                    </div>
                    <p class="review-text">
                        <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                    </p>
                    <div class="review-footer">
                        <span class="review-client"><?php echo htmlspecialchars($review['client_name']); ?></span>
                        <span class="review-package">on "<?php echo htmlspecialchars($review['package_title']); ?>"</span>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// 5. Includes the footer
require_once 'includes/agency_footer.php'; 
?>