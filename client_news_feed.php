<?php
// 1. Set the page title
$page_title = 'News Feed'; 

// 2. Includes session_start, PHP logic, and the HTML <head>
require_once 'includes/header.php'; 

// 3. Includes the new secure PDO connection
require_once 'config/db_connect.php'; 
?>

<?php
// 4. Includes the dynamic Left Navigation
require_once 'includes/sidebar.php'; 
?>
    
<?php
// --- DATABASE FETCH FOR NEWS FEED POSTS ---
try {
    // UPDATED SQL: specific column 'profile_image_path' is now selected from agencies table
    $sql = "SELECT p.*, a.agency_name, a.is_verified, a.profile_image_path 
            FROM agency_posts p
            JOIN agencies a ON p.agency_id = a.agency_id
            ORDER BY p.post_timestamp DESC"; 

    $stmt = $pdo->query($sql);
    $posts = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "Error fetching posts: " . $e->getMessage();
    $posts = []; 
}
?>

<div class="news-feed-container">
    <header class="feed-header">
        <h1><i class="bi bi-file-text"></i> News Feed</h1>
        <p>See the latest updates and offers from travel agencies</p>
    </header>

    <div class="feed-content">
        
        <?php if (empty($posts)): ?>
            <div class="no-posts-card">
                <i class="bi bi-calendar-x"></i>
                <h3>No agency posts yet</h3>
                <p>Check back later for new tours and updates!</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <div class="post-header">
                        
                        <?php if (!empty($post['profile_image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($post['profile_image_path']); ?>" 
                                 alt="Agency Logo" 
                                 style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 12px;">
                        <?php else: ?>
                            <span class="post-logo-text">
                                <?php echo htmlspecialchars(substr($post['agency_name'], 0, 1)); ?>
                            </span>
                        <?php endif; ?>

                        <span class="post-name"><?php echo htmlspecialchars($post['agency_name']); ?></span>
                        
                        <?php if ($post['is_verified']): ?>
                            <span class="verified-badge">Verified</span>
                        <?php endif; ?>
                        
                        <span class="post-time">
                            <?php echo date('M j, Y \a\t g:ia', strtotime($post['post_timestamp'])); ?>
                        </span>
                    </div>

                    <p class="post-text">
                        <?php echo nl2br(htmlspecialchars($post['post_content'])); ?>
                    </p>

                    <a href="#" class="post-read-more">Read more</a>
                    
                    <?php if (!empty($post['post_image_path'])): ?>
                        <div class="post-image-container">
                            <img src="<?php echo htmlspecialchars($post['post_image_path']); ?>" alt="Post Image" class="post-image">
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($post['package_id'])): ?>
                        <div class="post-actions">
                            <a href="package_details.php?id=<?php echo (int)$post['package_id']; ?>" class="btn-view-package">
                                <i class="bi bi-box-arrow-up-right"></i> View Package
                            </a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
        
    </div>
</div>


<script>
    document.addEventListener('click', function (e) {
        if (e.target.matches('.post-read-more')) {
            e.preventDefault();

            const btn  = e.target;
            const card = btn.closest('.post-card');
            const text = card.querySelector('.post-text');

            text.classList.toggle('expanded');

            if (text.classList.contains('expanded')) {
                btn.textContent = 'Show less';
            } else {
                btn.textContent = 'Read more';
            }
        }
    });
</script>

<?php
// 5. Includes the footer
require_once 'includes/footer.php'; 
?>