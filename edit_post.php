<?php
// 1. Start session and include database
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_connect.php';

// 2. Check if the user is a logged-in agency
if (!isset($_SESSION['agency_id'])) {
    header("Location: login_agency.php");
    exit();
}

$agency_id = $_SESSION['agency_id'];
$post_id_to_edit = $_GET['id'] ?? null;
$error_message = '';
$post = null;

// 3. Check for Post ID in URL
if (!$post_id_to_edit || !is_numeric($post_id_to_edit)) {
    header("Location: agency_dashboard.php?error=missing_id");
    exit();
}

// 4. --- HANDLE FORM SUBMISSION (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get data from form
    $post_content = $_POST['post_content'];
    
    // --- NEW: Handle Package Link ---
    // If value is empty string, set to NULL (removes the link)
    $package_id_input = $_POST['package_id'] ?? '';
    $package_id = (!empty($package_id_input)) ? $package_id_input : NULL;

    $new_image_path = null;
    $delete_image = isset($_POST['delete_image']) ? 1 : 0;

    try {
        // Fetch the post again to ensure ownership and get old image path
        $stmt_check = $pdo->prepare("SELECT agency_id, post_image_path FROM agency_posts WHERE post_id = :post_id");
        $stmt_check->execute(['post_id' => $post_id_to_edit]);
        $post_to_update = $stmt_check->fetch();

        if ($post_to_update['agency_id'] !== $agency_id) {
            // Security check failed
            header("Location: agency_dashboard.php?error=permission_denied");
            exit();
        }

        $current_image_path = $post_to_update['post_image_path'];

        // 5. Handle File Upload (if new image is provided)
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/agency_posts/";
            $file_name = uniqid() . "-" . basename($_FILES["post_image"]["name"]);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["post_image"]["tmp_name"], $target_file)) {
                $new_image_path = $target_file; // Set new path
                // Delete old image if it's not the default one
                if ($current_image_path && file_exists($current_image_path)) {
                    unlink($current_image_path);
                }
            }
        } elseif ($delete_image) {
            // 6. Handle Image Deletion
            if ($current_image_path && file_exists($current_image_path)) {
                unlink($current_image_path);
            }
            $new_image_path = NULL; // Set path to NULL in database
        }

        // 7. Update the Database
        // We now include package_id in the UPDATE query
        if ($new_image_path !== null) {
            // Image changed
            $sql = "UPDATE agency_posts 
                    SET post_content = :content, 
                        post_image_path = :image,
                        package_id = :package_id 
                    WHERE post_id = :post_id AND agency_id = :agency_id";
            $params = [
                'content' => $post_content,
                'image' => $new_image_path,
                'package_id' => $package_id, // Added
                'post_id' => $post_id_to_edit,
                'agency_id' => $agency_id
            ];
        } else {
            // Image NOT changed (just text and package link)
            $sql = "UPDATE agency_posts 
                    SET post_content = :content,
                        package_id = :package_id 
                    WHERE post_id = :post_id AND agency_id = :agency_id";
            $params = [
                'content' => $post_content,
                'package_id' => $package_id, // Added
                'post_id' => $post_id_to_edit,
                'agency_id' => $agency_id
            ];
        }
        
        $stmt_update = $pdo->prepare($sql);
        $stmt_update->execute($params);

        // 8. Redirect on success
        header("Location: agency_dashboard.php?success=post_updated");
        exit();

    } catch (PDOException $e) {
        $error_message = "Error updating post: " . $e->getMessage();
    }
}

// 9. --- FETCH POST DATA FOR DISPLAY (GET Request) ---
try {
    $stmt = $pdo->prepare("SELECT * FROM agency_posts WHERE post_id = :post_id AND agency_id = :agency_id");
    $stmt->execute(['post_id' => $post_id_to_edit, 'agency_id' => $agency_id]);
    $post = $stmt->fetch();

    if (!$post) {
        // Post not found or doesn't belong to this agency
        header("Location: agency_dashboard.php?error=not_found_or_permission");
        exit();
    }

    // --- NEW: FETCH PACKAGES FOR DROPDOWN ---
    // We need the list of packages owned by this agency to populate the select box
   
    // FIXED: Changed 'created_at' to 'date_posted'
$stmt_packages = $pdo->prepare("SELECT package_id, title FROM packages WHERE agency_id = :agency_id ORDER BY date_posted DESC");
    $stmt_packages->execute(['agency_id' => $agency_id]);
    $my_packages = $stmt_packages->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// --- Include Header and Sidebar ---
$page_title = 'Edit Post';
require_once 'includes/agency_header.php'; 
require_once 'includes/agency_sidebar.php'; 
?>

<div class="news-feed-section">
    <div class="section-header">
        <h2><i class="bi bi-pencil-square"></i> Edit Post</h2>
    </div>

    <div class="modal-content" style="max-width: 750px; opacity: 1; transform: none; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
        
        <?php if (!empty($error_message)): ?>
            <p class="message error"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <form method="POST" action="edit_post.php?id=<?php echo $post_id_to_edit; ?>" enctype="multipart/form-data">
            
            <div class="post-author-info">
                <span class="post-logo-text"><?php echo htmlspecialchars(substr($agency_name, 0, 1)); ?></span>
                <div class="post-author-details">
                    <span class="post-name"><?php echo htmlspecialchars($agency_name); ?></span>
                    <span class="post-subtext">Editing post...</span>
                </div>
            </div>

            <div class="form-group">
                <label for="post_content">Post Content *</label>
                <textarea id="post_content" name="post_content" rows="5" required><?php echo htmlspecialchars($post['post_content']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="post_image">Upload New Image (Optional)</label>
                
                <?php if (!empty($post['post_image_path'])): ?>
                    <div class="current-image-preview">
                        <img src="<?php echo htmlspecialchars($post['post_image_path']); ?>" alt="Current Image">
                        <label>
                            <input type="checkbox" name="delete_image" value="1"> Remove current image
                        </label>
                    </div>
                <?php endif; ?>

                <div class="file-upload-wrapper">
                    <input type="file" id="post_image" name="post_image" accept="image/*"
                           onchange="document.getElementById('file-name').textContent = this.files[0].name">
                    
                    <button type="button" class="upload-button" onclick="document.getElementById('post_image').click()">
                        <i class="bi bi-upload"></i> Choose New File
                    </button>
                    
                    <span id="file-name" class="file-name">No new file chosen</span>
                </div>
                <small>Uploading a new file will replace the current one.</small>
            </div>

            <div class="optional-section" style="margin-top: 20px; background-color: #f9fafb; padding: 15px; border-radius: 8px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="package_id"><strong>Link to an Existing Package (Optional)</strong></label>
                    <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 8px; font-weight: normal;">
                        Select a package to attach a "View Package" button to this post.
                    </p>
                    
                    <select name="package_id" id="package_id" 
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        
                        <option value="">-- No package (just an update) --</option>
                        
                        <?php foreach ($my_packages as $pkg): ?>
                            <?php 
                                // Check if this package is the one currently linked
                                $selected = ($post['package_id'] == $pkg['package_id']) ? 'selected' : ''; 
                            ?>
                            <option value="<?php echo $pkg['package_id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($pkg['title']); ?>
                            </option>
                        <?php endforeach; ?>
                        
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <a href="agency_dashboard.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-publish"><i class="bi bi-check-lg"></i> Save Changes</button>
            </div>
            
        </form>
    </div>
</div>

<?php
require_once 'includes/agency_footer.php'; 
?>