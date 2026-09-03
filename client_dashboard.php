<?php
// 1. Includes session_start, all PHP logic, and the HTML <head>
require_once 'includes/header.php'; 

// 2. Includes the new secure PDO connection
require_once 'config/db_connect.php'; 

// =================================================================
// 3. FETCH CLIENT DETAILS (Fixed Lifetime Tier Logic)
// =================================================================
$client_phone = ''; 
$loyalty_points = 0;
$trips_completed = 0;
$current_tier = 'Silver'; // Default fallback

if (isset($_SESSION['client_id'])) {
    try {
        // A. Get Basic Client Info (Wallet Balance, Name, Phone)
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
        $stmt->execute([$_SESSION['client_id']]);
        $clientRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($clientRow) {
            $client_phone   = $clientRow['phone']; 
            $client_name    = $clientRow['name'];  
            $client_email   = $clientRow['email']; 
            $loyalty_points = $clientRow['loyalty_points']; // This is your SPENDABLE balance
        }

        // B. Calculate "Lifetime Points" for Tier Status
        // We sum up only the POSITIVE points from history to see total earned ever.
        // This ensures spending points does NOT downgrade your Tier.
        $stmtLifetime = $pdo->prepare("SELECT SUM(points_amount) FROM points_activity WHERE client_id = ? AND points_amount > 0");
        $stmtLifetime->execute([$_SESSION['client_id']]);
        $lifetime_points = $stmtLifetime->fetchColumn(); 
        
        // Fallback: If no history, use current balance or 0
        if (!$lifetime_points) {
            $lifetime_points = $loyalty_points; 
        }

        // C. Determine Tier based on LIFETIME earnings
        if ($lifetime_points >= 2000) { 
            $current_tier = 'Platinum'; 
        } elseif ($lifetime_points >= 1000) { 
            $current_tier = 'Gold'; 
        } else { 
            $current_tier = 'Silver'; 
        }
        
        // D. Count trips for the profile modal stats
        $stmtTrips = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = ? AND booking_status = 'Completed'");
        $stmtTrips->execute([$_SESSION['client_id']]);
        $trips_completed = $stmtTrips->fetchColumn();

    } catch (PDOException $e) {
        // Silently handle error (or log it)
    }
}
// =================================================================

// 4. Includes the fixed Left Navigation
require_once 'includes/sidebar.php'; 
?>
    
    <header class="dashboard-header">
        <div class="header-actions">
            <div class="profile" onclick="openModal()">
                <div class="profile-icon">
                    <?php if (!empty($_SESSION['client_avatar_path'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['client_avatar_path']); ?>" alt="Avatar">
                    <?php else: ?>
                        <i class="bi bi-person"></i>
                    <?php endif; ?>
                </div>
                <?php echo htmlspecialchars($client_name ?? 'Client'); ?>
            </div>
        </div>
    </header>

    <?php 
    // Display Success/Error Messages from Settings Update
    if (isset($_SESSION['settings_success'])) {
        echo '<div style="margin: 20px 60px 0 60px;" class="message success"><i class="bi bi-check-circle-fill"></i> ' . $_SESSION['settings_success'] . '</div>';
        unset($_SESSION['settings_success']);
    }
    if (isset($_SESSION['settings_error'])) {
        echo '<div style="margin: 20px 60px 0 60px;" class="message error"><i class="bi bi-exclamation-triangle-fill"></i> ' . $_SESSION['settings_error'] . '</div>';
        unset($_SESSION['settings_error']);
    }
    ?>

    <section class="ai-planner-section" style="min-height: 80vh; display: flex; flex-direction: column; justify-content: center;">
        <h1>Discover Pakistan with AI-Powered Trip Planning</h1>
        <div class="tagline">
            <span><i class="bi bi-check-circle-fill"></i> Verified Travel Agencies</span>
            <span><i class="bi bi-check-circle-fill"></i> AI Personalized Itineraries</span>
            <span><i class="bi bi-check-circle-fill"></i> Earn Loyalty Points</span>
        </div>

        <form action="client_ai_results.php" method="GET" class="trip-planner-form" autocomplete="off" id="tripPlannerForm">
            <div class="form-group">
                <label for="destination">Destination</label>
                <input type="text" id="destination" name="destination" placeholder="Hunza, Swat, Murree... " required>
            </div>
            <div class="form-group">
                <label for="duration">Trip Duration (Days)</label>
                <input type="number" id="duration" name="duration" placeholder="e.g. 5" required>
            </div>
            <div class="form-group">
                <label for="travelers">Number of Travelers</label>
                <input type="number" id="travelers" name="travelers" placeholder="e.g. 2" required>
            </div>

            <div class="form-group">
                <label for="trip_type">Traveling As</label>
                <select id="trip_type" name="trip_type">
                    <option value="">Any Trip Type</option>
                    <option value="solo">Solo</option>
                    <option value="couple">Couple / Honeymoon</option>
                    <option value="family">Family</option>
                    <option value="friends">Friends / Group</option>
                </select>
            </div>
            <button type="submit">
                <i class="bi bi-magic"></i> Generate My Trip
            </button>
        </form>
    </section>

</div>

<?php
require_once 'includes/footer.php'; // Includes the modal and JavaScript
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tripTypeSelect = document.getElementById('trip_type');
    const travelersInput = document.getElementById('travelers');

    function applyRules() {
        const type = tripTypeSelect.value;

        if (type === 'couple') {
            travelersInput.value = 2;
            travelersInput.min = 2;
            travelersInput.readOnly = true;
        } else {
            travelersInput.readOnly = false;
            travelersInput.min = 1;

            // Ensure minimum value
            if (!travelersInput.value || parseInt(travelersInput.value, 10) < 1) {
                travelersInput.value = 1;
            }
        }
    }

    // Run once on page load
    applyRules();

    // Apply rules every time trip type changes
    tripTypeSelect.addEventListener('change', applyRules);

    // Prevent user editing if couple is selected
    travelersInput.addEventListener('input', function () {
        if (tripTypeSelect.value === 'couple') {
            travelersInput.value = 2;
        }
    });
});
</script>