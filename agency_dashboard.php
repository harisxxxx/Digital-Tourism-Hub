<?php
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// Fetch posts from the database (UPDATED: Now fetching profile_image_path)
try {
    $stmt = $pdo->prepare("SELECT ap.post_id, ap.agency_id, ap.post_content, ap.post_image_path, ap.post_timestamp, 
                                  a.agency_name, a.profile_image_path 
                            FROM agency_posts ap
                            JOIN agencies a ON ap.agency_id = a.agency_id
                            ORDER BY ap.post_timestamp DESC"); 
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $posts = []; 
    error_log("Error fetching agency posts: " . $e->getMessage());
}

require_once 'includes/agency_sidebar.php'; 
?>

    <section class="agency-hero">
        <div class="hero-content">
            <span class="verified-tag"><i class="bi bi-patch-check-fill"></i> Verified Agency</span>
            <h1>Welcome back, <?php echo htmlspecialchars($agency_name); ?>!</h1>
            <p>Manage your tours, engage with clients, and grow your business</p>
        </div>
        <div class="agency-stats">
            <div class="stat-box">
                <span class="stat-value"><i class="bi bi-star-fill"></i> <?php echo number_format($agency_rating, 1); ?></span>
                <span class="stat-label"><?php echo number_format($agency_reviews); ?> reviews</span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><i class="bi bi-rocket-takeoff-fill"></i> <?php echo number_format($trips_completed); ?></span>
                <span class="stat-label">trips completed</span>
            </div>
        </div>
    </section>

    <section class="news-feed-section">
        <div class="section-header">
            <h2><i class="bi bi-file-text"></i> News Feed</h2>
            <p>Share updates with your clients</p>
            
            <button class="btn btn-create-post" onclick="openPostModal()">
                <i class="bi bi-plus-lg"></i> Create Post
            </button>
            
        </div>

        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
            <article class="post-card">
                <div class="post-header">
                    <?php if (!empty($post['profile_image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($post['profile_image_path']); ?>" 
                             alt="Profile" 
                             style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid #e5e7eb;">
                    <?php else: ?>
                        <span class="post-logo-text"><?php echo htmlspecialchars(substr($post['agency_name'], 0, 1)); ?></span>
                    <?php endif; ?>

                    <span class="post-name"><?php echo htmlspecialchars($post['agency_name']); ?></span>
                    
                    <?php if ($post['agency_id'] === $_SESSION['agency_id']): ?>
                        <span class="verified-badge">You</span>
                    <?php endif; ?>
                    
                    <span class="post-time"><?php echo time_elapsed_string($post['post_timestamp']); ?></span>

                    <?php if ($post['agency_id'] === $_SESSION['agency_id']): ?>
                        <div class="post-options">
                            <button class="post-options-btn" onclick="togglePostMenu(<?php echo $post['post_id']; ?>)">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <div class="post-options-menu" id="post-menu-<?php echo $post['post_id']; ?>">
                                <a href="edit_post.php?id=<?php echo $post['post_id']; ?>"><i class="bi bi-pencil"></i> Edit Post</a>
                                <a href="delete_post.php?id=<?php echo $post['post_id']; ?>" onclick="return confirm('Are you sure you want to delete this post?');" class="delete-option">
                                    <i class="bi bi-trash"></i> Delete Post
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                <p class="post-text">
                    <?php echo nl2br(htmlspecialchars($post['post_content'])); ?>
                </p>
                <?php if (!empty($post['post_image_path'])): ?>
                    <div class="post-image-container">
                        <img src="<?php echo htmlspecialchars($post['post_image_path']); ?>" alt="Post Image" class="post-image">
                    </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-posts-card">
                <i class="bi bi-exclamation-circle"></i>
                <h3>No posts yet!</h3>
                <p>Start sharing your amazing tour packages and updates.</p>
            </div>
        <?php endif; ?>
        
    </section>

<?php
require_once 'includes/agency_footer.php'; 
?>

<?php
// Function to get time elapsed string
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>