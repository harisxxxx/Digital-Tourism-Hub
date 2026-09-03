<?php
// 1. Set the page title
$page_title = 'My Tour Packages'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- DATABASE FETCH FOR PACKAGES ---
try {
    // Select all packages that belong to this logged-in agency
    $sql = "SELECT * FROM packages WHERE agency_id = :agency_id ORDER BY date_posted DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['agency_id' => $_SESSION['agency_id']]);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error fetching packages: " . $e->getMessage();
    $packages = []; // Set packages to an empty array so the page doesn't crash
}

// 4. Include the sidebar
require_once 'includes/agency_sidebar.php'; 

// 5. --- NEW: MESSAGE HANDLING ---
// Check for success or error messages from other pages (like delete_post.php)
$form_message = '';
if (isset($_SESSION['success_message'])) {
    $form_message = "<p class='message success'><i class='bi bi-check-circle-fill'></i> " . $_SESSION['success_message'] . "</p>";
    unset($_SESSION['success_message']); // Clear message after displaying
}
if (isset($_SESSION['error_message'])) {
    $form_message = "<p class='message error'><i class='bi bi-exclamation-triangle-fill'></i> " . $_SESSION['error_message'] . "</p>";
    unset($_SESSION['error_message']); // Clear message after displaying
}
// --- END: MESSAGE HANDLING ---
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-box-seam"></i> My Tour Packages</h1>
        <p>Manage all your travel packages in one place.</p>
        <a href="agency_add_package.php" class="btn-primary-action"><i class="bi bi-plus-lg"></i> Add New Package</a>
    </header>

    <?php echo $form_message; ?>

    <div class="content-card">
        <table class="packages-table">
            <thead>
                <tr>
                    <th>Package Title</th>
                    <th>Destination</th>
                    <th>Duration</th>
                    <th>Price (PKR)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($packages)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 20px;">You have not created any packages yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                        <tr>
                            <td>
                                <div class="package-title-cell">
                                    <img src="img/<?php echo htmlspecialchars($package['image_path']); ?>" alt="Package Image" class="package-table-img">
                                    <span><?php echo htmlspecialchars($package['title']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($package['destination']); ?></td>
                            <td><?php echo htmlspecialchars($package['duration_days']); ?> Days</td>
                            <td><?php echo number_format($package['price']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="agency_edit_package.php?id=<?php echo $package['package_id']; ?>" class="btn-action btn-edit"><i class="bi bi-pencil"></i></a>
                                    <a href="agency_delete_package.php?id=<?php echo $package['package_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this package?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 6. Includes the footer
require_once 'includes/agency_footer.php'; 
?>