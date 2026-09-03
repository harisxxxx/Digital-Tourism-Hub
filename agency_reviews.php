<?php
// 1. Set the page title
$page_title = 'Reviews'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- DATABASE FETCH FOR REVIEWS ---
try {
    // We select reviews and JOIN clients and packages
    $sql = "SELECT 
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
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['agency_id' => $_SESSION['agency_id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error fetching reviews: " . $e->getMessage();
    $reviews = []; 
}

// 4. Include the sidebar
require_once 'includes/agency_sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-people"></i> Customer Reviews</h1>
        <p>See what clients are saying about your services.</p>
    </header>

    <div class="reviews-container">
        <?php if (empty($reviews)): ?>
            <div class="content-card" style="padding: 40px; text-align: center;">
                <h3>No reviews yet</h3>
                <p>When clients book and complete a tour, they will be able to leave a review here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <article class="review-card">
                    <div class="review-header">
                        <span class="review-rating-stars">
                            <?php 
                            // Display 5 stars, filling them based on rating
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
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