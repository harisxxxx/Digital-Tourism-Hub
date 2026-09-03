<?php
// 1. Set the page title
$page_title = 'Confirm Booking'; 

// 2. Includes
session_start();
require_once 'includes/header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- GET DATA FROM PREVIOUS FORM (POST) ---
$package_id    = $_POST['package_id'] ?? null;
$travel_date   = $_POST['travel_date'] ?? null;
$num_travelers = $_POST['num_travelers'] ?? 1;
$trip_type     = $_POST['trip_type'] ?? null; 

if (!$package_id || !$travel_date) {
    header("Location: client_dashboard.php?error=missing_data");
    exit();
}

// 4. --- FETCH PACKAGE + AGENCY DETAILS ---
try {
    $sql = "SELECT p.*, a.agency_name, a.business_phone
            FROM packages p
            JOIN agencies a ON p.agency_id = a.agency_id
            WHERE p.package_id = :package_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['package_id' => $package_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        header("Location: client_dashboard.php?error=notfound");
        exit();
    }
    
    // Base + couple price
    $base_price   = (float)$package['price'];
    $couple_price = isset($package['couple_price']) ? (float)$package['couple_price'] : 0;

    // Default total = per person
    $total_price = $base_price * (int)$num_travelers;

    // If couple booking with 2 travelers and couple_price set, override
    if ($trip_type === 'couple' && (int)$num_travelers === 2 && $couple_price > 0) {
        $total_price = $couple_price;
    }

} catch (PDOException $e) {
    die("Error fetching package details: " . $e->getMessage());
}

// Friendly label for trip type
$trip_type_labels = [
    'solo'    => 'Solo Traveler',
    'family'  => 'Family',
    'friends' => 'Friends Group',
    'couple'  => 'Couple / Honeymoon'
];
$trip_type_display = $trip_type_labels[$trip_type] ?? 'Not specified';

// 4.b --- FETCH CLIENT POINTS ---
$client_id        = $_SESSION['client_id'] ?? null;
$client_phone     = '';
$available_points = 0;
$POINTS_PER_PKR   = 40;

if ($client_id) {
    $stmt_client = $pdo->prepare("SELECT name, email, phone, loyalty_points FROM clients WHERE client_id = :cid");
    $stmt_client->execute(['cid' => $client_id]);
    $client_row = $stmt_client->fetch(PDO::FETCH_ASSOC);

    $client_name  = $client_row['name'] ?? '';
    $client_email = $client_row['email'] ?? '';
    $client_phone = $client_row['phone'] ?? '';
    $available_points = (int)($client_row['loyalty_points'] ?? 0);
}

// Max points user can actually use on this booking
$max_points_usable = min($available_points, $total_price * $POINTS_PER_PKR);
$approx_value_pkr  = intdiv($available_points, $POINTS_PER_PKR);

// 5. Sidebar include
require_once 'includes/sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-check2-circle"></i> Confirm Your Booking</h1>
        <p>Please review the details below before confirming your trip.</p>
    </header>

    <div class="booking-confirmation-layout">
        
        <div class="order-summary-card">
            <h2>Order Summary</h2>
            
            <div class="summary-item main-item">
                <img src="img/<?php echo htmlspecialchars($package['image_path']); ?>" alt="Package Image">
                <div class="item-details">
                    <strong><?php echo htmlspecialchars($package['title']); ?></strong>
                    <span>By <?php echo htmlspecialchars($package['agency_name']); ?></span>
                </div>
            </div>
            
            <div class="summary-item">
                <span><i class="bi bi-calendar3"></i> Travel Date</span>
                <strong><?php echo date('D, M j, Y', strtotime($travel_date)); ?></strong>
            </div>
            
            <div class="summary-item">
                <span><i class="bi bi-people"></i> Travelers</span>
                <strong><?php echo htmlspecialchars($num_travelers); ?> Person(s)</strong>
            </div>

            <div class="summary-item">
                <span><i class="bi bi-suit-heart"></i> Trip Type</span>
                <strong><?php echo htmlspecialchars($trip_type_display); ?></strong>
            </div>
            
            <div class="summary-item price-item">
                <?php if ($trip_type === 'couple' && (int)$num_travelers === 2 && $couple_price > 0): ?>
                    <span>Couple Package Price</span>
                    <strong>PKR <?php echo number_format($couple_price); ?></strong>
                <?php else: ?>
                    <span>Price per person</span>
                    <strong>PKR <?php echo number_format($base_price); ?></strong>
                <?php endif; ?>
            </div>
            
            <div class="summary-total">
                <span>Total Amount</span>
                <strong>PKR <?php echo number_format($total_price); ?></strong>
            </div>
        </div>

        <div class="payment-details-card no-hover-transform">
            <h2>Your Information</h2>
            
            <div class="info-box">
                <strong><?php echo htmlspecialchars($client_name); ?></strong>
                <p><?php echo htmlspecialchars($client_email); ?></p>
                <?php if (!empty($client_phone)) : ?>
                    <p><?php echo htmlspecialchars($client_phone); ?></p>
                <?php endif; ?>
            </div>

            <div class="rewards-box" style="margin-top:15px; padding:15px; border-radius:10px; background:#f4f4ff;">
                <h3 style="margin-top:0; font-size:1rem;">
                    <i class="bi bi-coin"></i> Use Your Points
                </h3>
                <p style="margin-bottom:8px;">
                    You have <strong><?php echo number_format($available_points); ?> pts</strong>
                    (≈ PKR <?php echo number_format($approx_value_pkr); ?>).<br>
                    <small>Conversion rate: <strong>40 points = PKR 1</strong>.</small>
                </p>

                <div style="margin-bottom:10px;">
                    <label for="points_to_use" style="font-size:0.9rem; font-weight:500;">Points to apply on this booking</label>
                    <div style="display:flex; gap:8px; margin-top:4px;">
                        <input 
                            type="number" 
                            id="points_to_use" 
                            name="points_to_use_display"
                            min="0"
                            max="<?php echo (int)$max_points_usable; ?>"
                            step="40"
                            value="0"
                            oninput="updatePointsDiscount()"
                            style="flex:1; padding:8px 10px; border-radius:6px; border:1px solid #ddd;">
                        <button type="button" onclick="useMaxPoints()" 
                                style="border:none; padding:8px 12px; border-radius:6px; background:#2563eb; color:#fff; font-size:0.85rem; cursor:pointer;">
                            Use Max
                        </button>
                    </div>
                    <small style="display:block; margin-top:3px; color:#6b7280;">
                        You can use up to <?php echo number_format($max_points_usable); ?> pts on this booking.
                    </small>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-top:8px;">
                    <span>Points Value:</span>
                    <strong id="discount_value_display">PKR 0</strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.95rem; margin-top:4px;">
                    <span>New Payable Total:</span>
                    <strong id="new_total_display" style="color:#2563eb;">
                        PKR <?php echo number_format($total_price); ?>
                    </strong>
                </div>
            </div>
            <p class="payment-note" style="margin-top:18px;">
                When you click <strong>"Confirm & Book Now"</strong>, you will be asked to select a payment method 
                (Easypaisa / JazzCash / NayaPay) and upload a screenshot of your payment. Your booking will be created
                with status <strong>"Pending Payment"</strong> until the agency verifies your slip.
            </p>

            <form id="bookingForm" action="booking_action.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="package_id" value="<?php echo htmlspecialchars($package['package_id']); ?>">
                <input type="hidden" name="agency_id" value="<?php echo htmlspecialchars($package['agency_id']); ?>">
                <input type="hidden" name="travel_date" value="<?php echo htmlspecialchars($travel_date); ?>">
                <input type="hidden" name="num_travelers" value="<?php echo htmlspecialchars($num_travelers); ?>">
                <input type="hidden" name="total_price" value="<?php echo htmlspecialchars($total_price); ?>">
                <input type="hidden" name="trip_type" value="<?php echo htmlspecialchars($trip_type); ?>">

                <input type="hidden" name="points_used" id="points_used_input" value="0">

                <button type="button" class="btn-book-now" onclick="openPaymentModal()">
                    <i class="bi bi-shield-check"></i> Confirm & Book Now
                </button>
                <a href="package_details.php?id=<?php echo $package['package_id']; ?>" class="btn-cancel">Cancel</a>

                <div id="paymentModal" class="payment-modal-overlay" style="display:none;">
                    <div class="payment-modal">
                        <h3><i class="bi bi-credit-card"></i> Payment Details</h3>
                        <p>Select a payment method and upload your payment slip.</p>

                        <div class="payment-methods">
                            <?php
                                $wallet = !empty($package['business_phone']) 
                                    ? htmlspecialchars($package['business_phone']) 
                                    : '03xx-xxxxxxx';
                            ?>
                            <label>
                                <input type="radio" name="payment_method" value="Easypaisa" required>
                                Easypaisa – <?php echo $wallet; ?>
                            </label>
                            <label>
                                <input type="radio" name="payment_method" value="JazzCash">
                                JazzCash – <?php echo $wallet; ?>
                            </label>
                            <label>
                                <input type="radio" name="payment_method" value="NayaPay">
                                NayaPay – <?php echo $wallet; ?>
                            </label>
                            <p style="font-size: 12px; margin-top:5px;">
                                (This number comes from the agency's business phone in their profile.)
                            </p>
                        </div>

                        <div class="payment-upload">
                            <label for="payment_slip"><strong>Upload Payment Screenshot / Slip</strong></label>
                            <input type="file" name="payment_slip" id="payment_slip" accept="image/*" required>
                            <small>Accepted: JPG, PNG. Max ~2MB (recommended).</small>
                        </div>

                        <div class="payment-modal-actions">
                            <button type="button" class="btn-cancel-modal" onclick="closePaymentModal()">Cancel</button>
                            <button type="submit" class="btn-confirm-modal">
                                <i class="bi bi-check2-circle"></i> Submit & Place Booking
                            </button>
                        </div>
                    </div>
                </div>
                </form>
        </div>
        
    </div>
</div>

<style>
/* === CRITICAL FIX: Disable Transform on Parent Card === */
/* This prevents the modal from being 'trapped' inside the card when hovered */
.payment-details-card:hover {
    transform: none !important;
}

/* Modal Overlay Styles */
.payment-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000; /* High z-index to stay on top */
}

/* Modal Box Styles */
.payment-modal {
    background: #fff;
    padding: 20px 24px;
    max-width: 480px;
    width: 95%;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    position: relative; /* Ensure content stacks correctly */
    z-index: 10001;
}

.payment-modal h3 { margin-top: 0; margin-bottom: 10px; }
.payment-methods label { display: block; margin-bottom: 6px; }
.payment-upload { margin-top: 12px; margin-bottom: 12px; }
.payment-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
.btn-cancel-modal { background: #ccc; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
.btn-confirm-modal { background: #28a745; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
</style>

<script>
const POINTS_PER_PKR    = 40;
const ORIGINAL_TOTAL    = <?php echo (int)$total_price; ?>;
const MAX_POINTS_USABLE = <?php echo (int)$max_points_usable; ?>;

function openPaymentModal() {
    document.getElementById('paymentModal').style.display = 'flex';
}
function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function updatePointsDiscount() {
    const input   = document.getElementById('points_to_use');
    const hidden  = document.getElementById('points_used_input');
    let points    = parseInt(input.value) || 0;

    if (points < 0) points = 0;
    if (points > MAX_POINTS_USABLE) points = MAX_POINTS_USABLE;

    let discount = Math.floor(points / POINTS_PER_PKR);

    if (discount > ORIGINAL_TOTAL) {
        discount = ORIGINAL_TOTAL;
        points   = discount * POINTS_PER_PKR;
    }

    input.value  = points;
    hidden.value = points;

    const newTotal = ORIGINAL_TOTAL - discount;

    document.getElementById('discount_value_display').innerText = 'PKR ' + discount.toLocaleString();
    document.getElementById('new_total_display').innerText      = 'PKR ' + newTotal.toLocaleString();
}

function useMaxPoints() {
    document.getElementById('points_to_use').value = MAX_POINTS_USABLE;
    updatePointsDiscount();
}

document.addEventListener('DOMContentLoaded', updatePointsDiscount);
</script>

<?php
require_once 'includes/footer.php'; 
?>