<?php
// 1. Set the page title
$page_title = 'Edit Package'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- GET PACKAGE ID AND CHECK OWNERSHIP ---
$package_id = $_GET['id'] ?? null;
$agency_id = $_SESSION['agency_id'];

if (!$package_id) {
    header("Location: agency_my_packages.php");
    exit();
}

try {
    $sql = "SELECT * FROM packages WHERE package_id = :package_id AND agency_id = :agency_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['package_id' => $package_id, 'agency_id' => $agency_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);

    // If package not found or doesn't belong to this agency, redirect
    if (!$package) {
        $_SESSION['error_message'] = "Package not found or you do not have permission to edit it.";
        header("Location: agency_my_packages.php");
        exit();
    }

} catch (PDOException $e) {
    die("Error fetching package: " . $e->getMessage());
}

// --- NEW: decode suitable_for + couple_price for editing ---
$suitable_for = [];
if (!empty($package['suitable_for'])) {
    $suitable_for = json_decode($package['suitable_for'], true);
}
$couple_price = $package['couple_price'] ?? '';

// 4. --- MESSAGE HANDLING ---
$form_message = '';
if (isset($_SESSION['success_message'])) {
    $form_message = "<p class='message success'><i class='bi bi-check-circle-fill'></i> " . $_SESSION['success_message'] . "</p>";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $form_message = "<p class='message error'><i class='bi bi-exclamation-triangle-fill'></i> " . $_SESSION['error_message'] . "</p>";
    unset($_SESSION['error_message']);
}

// 5. Include the sidebar
require_once 'includes/agency_sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-pencil-square"></i> Edit Package</h1>
        <p>Update the details for your tour package.</p>
    </header>

    <div class="content-card">
        
        <?php echo $form_message; ?>

        <form action="agency_edit_package_action.php" method="POST" enctype="multipart/form-data" class="package-form">
            
            <input type="hidden" name="package_id" value="<?php echo $package['package_id']; ?>">

            <div class="form-group">
                <label for="title">Package Title *</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($package['title']); ?>" required>
            </div>

            <div class="form-group">
                <label for="package_image">Change Package Image (Optional)</label>
                
                <div class="current-image-preview">
                    <img src="img/<?php echo htmlspecialchars($package['image_path']); ?>" alt="Current Package Image">
                    <label>
                        <input type="checkbox" name="delete_image" value="1"> Remove current image
                    </label>
                </div>
                
                <div class="file-upload-wrapper">
                    <input type="file" id="package_image" name="package_image" accept="image/*">
                    <button type="button" class="upload-button"><i class="bi bi-upload"></i> Choose New File</button>
                    <span id="file-name" class="file-name">No new file chosen</span>
                </div>
                <small>Uploading a new file will replace the current one.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="destination">Destination *</label>
                    <input type="text" id="destination" name="destination" value="<?php echo htmlspecialchars($package['destination']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="duration_days">Duration (in days) *</label>
                    <input type="number" id="duration_days" name="duration_days" value="<?php echo htmlspecialchars($package['duration_days']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="price">Price (PKR) *</label>
                <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($package['price']); ?>" step="100" required>
            </div>

            <!-- NEW: Suitable For + Couple Price -->
            <div class="form-group suitable-for-group">
                <label>Suitable For *</label>
                <div class="suitable-for-options">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="suitable_for[]" value="solo"
                               <?php if (in_array('solo', $suitable_for)) echo 'checked'; ?>>
                        Solo
                    </label>

                    <label class="checkbox-inline">
                        <input type="checkbox" name="suitable_for[]" value="family"
                               <?php if (in_array('family', $suitable_for)) echo 'checked'; ?>>
                        Family
                    </label>

                    <label class="checkbox-inline">
                        <input type="checkbox" name="suitable_for[]" value="friends"
                               <?php if (in_array('friends', $suitable_for)) echo 'checked'; ?>>
                        Friends / Group
                    </label>

                    <label class="checkbox-inline">
                        <input type="checkbox" id="suitable_couple"
                               name="suitable_for[]" value="couple"
                               <?php if (in_array('couple', $suitable_for)) echo 'checked'; ?>>
                        Couple / Honeymoon
                    </label>
                </div>
                <small>Select who this package is designed for.</small>
            </div>

            <div class="form-group" id="couple_price_group"
                 style="<?php echo in_array('couple', $suitable_for) ? '' : 'display:none;'; ?>">
                <label for="couple_price">Couple Package Price (PKR)</label>
                <input type="number"
                       id="couple_price"
                       name="couple_price"
                       placeholder="e.g., 120000"
                       step="100"
                       value="<?php echo htmlspecialchars($couple_price); ?>">
                <small>If you offer a special couple / honeymoon package, set its total price here.</small>
            </div>

            <!-- NEW: Package Description -->
            <div class="form-group package-description-group">
                <label for="description">Package Description *</label>
                <textarea id="description" name="description" rows="6" required><?php 
                    echo htmlspecialchars($package['description'] ?? ''); 
                ?></textarea>
                <small>Update the itinerary, highlights, inclusions, exclusions, and important notes.</small>
            </div>

            <div class="form-actions form-actions-full">
                <a href="agency_my_packages.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-publish"><i class="bi bi-check-lg"></i> Save Changes</button>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const coupleCheckbox = document.getElementById('suitable_couple');
    const couplePriceGrp = document.getElementById('couple_price_group');

    if (!coupleCheckbox || !couplePriceGrp) return;

    function toggleCouplePrice() {
        if (coupleCheckbox.checked) {
            couplePriceGrp.style.display = '';
        } else {
            couplePriceGrp.style.display = 'none';
        }
    }

    coupleCheckbox.addEventListener('change', toggleCouplePrice);
    toggleCouplePrice(); // run on page load
});
</script>

<?php
// 6. Includes the footer
require_once 'includes/agency_footer.php'; 
?>
