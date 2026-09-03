<?php
// 1. Set the page title
$page_title = 'Write Review'; 

// 2. Includes
require_once 'includes/header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- GET BOOKING ID AND CHECK OWNERSHIP ---
$booking_id = $_GET['id'] ?? null;
$client_id = $_SESSION['client_id'];

if (!$booking_id || !is_numeric($booking_id)) {
    header("Location: client_my_bookings.php");
    exit();
}

try {
    // 4. Fetch the booking to ensure the client owns it and it's completed
    $sql = "SELECT b.booking_id, b.package_id, b.agency_id, p.title AS package_title, p.image_path
            FROM bookings b
            JOIN packages p ON b.package_id = p.package_id
            WHERE b.booking_id = :booking_id 
            AND b.client_id = :client_id 
            AND b.booking_status = 'Completed'";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['booking_id' => $booking_id, 'client_id' => $client_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        // If booking not found, not completed, or not owned by this client
        $_SESSION['error_message'] = "This booking is not eligible for a review.";
        header("Location: client_my_bookings.php");
        exit();
    }
    
    // Check if a review already exists
    $stmt_check = $pdo->prepare("SELECT review_id FROM reviews WHERE booking_id = :booking_id");
    $stmt_check->execute(['booking_id' => $booking_id]);
    if ($stmt_check->fetch()) {
         $_SESSION['error_message'] = "You have already submitted a review for this booking.";
         header("Location: client_my_bookings.php");
         exit();
    }


} catch (PDOException $e) {
    die("Error fetching booking details: " . $e->getMessage());
}

// 5. Include the sidebar
require_once 'includes/sidebar.php'; 
?>
    
<div class="page-container">
    <header class.page-header">
        <h1><i class="bi bi-pencil-square"></i> Write a Review</h1>
        <p>Share your experience for "<?php echo htmlspecialchars($booking['package_title']); ?>"</p>
    </header>

    <div class="content-card review-form-container">
        
        <form action="review_action.php" method="POST" class="review-form">
            
            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
            <input type="hidden" name="package_id" value="<?php echo $booking['package_id']; ?>">
            <input type="hidden" name="agency_id" value="<?php echo $booking['agency_id']; ?>">

            <div class="form-group">
                <label>Your Rating *</label>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 stars"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars"><i class="bi bi-star-fill"></i></label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star"><i class="bi bi-star-fill"></i></label>
                </div>
            </div>

            <div class="form-group">
                <label for="review_text">Your Review (Optional)</label>
                <textarea id="review_text" name="review_text" rows="6" placeholder="Tell us about your experience, the guide, the service, and the highlights of your trip..."></textarea>
            </div>

            <div class="form-actions form-actions-full">
                <a href="client_my_bookings.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-publish"><i class="bi bi-check-lg"></i> Submit Review</button>
            </div>

        </form>
    </div>
</div>

<?php
// 6. Includes the footer
require_once 'includes/footer.php'; 
?>