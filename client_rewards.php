<?php
// client_rewards.php

// 1. Set the page title
$page_title = 'Rewards'; 

// 2. Includes
require_once 'includes/header.php'; 
require_once 'config/db_connect.php'; 

// --- SETTINGS ---
$DAILY_BONUS_POINTS = 20; // how many points per daily sign-in

// --- MESSAGE HANDLING ---
$form_message = '';

try {
    $client_id = $_SESSION['client_id'];
    $client_name = $_SESSION['client_name'] ?? 'User'; // Fallback if name isn't in session

    // 3. --- HANDLE DAILY SIGN-IN FORM SUBMIT ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daily_signin'])) {

        // Check if already claimed today
        $sql_check = "SELECT 1 
                      FROM points_activity 
                      WHERE client_id = :client_id
                        AND reason = 'Daily Sign-In Bonus'
                        AND DATE(activity_date) = CURDATE()
                      LIMIT 1";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute(['client_id' => $client_id]);
        $already_claimed = $stmt_check->fetchColumn();

        if ($already_claimed) {
            $form_message = "<p class='message error'>
                                <i class='bi bi-exclamation-triangle-fill'></i>
                                You have already claimed today’s daily bonus.
                             </p>";
        } else {
            // Insert daily bonus as a new points activity
            $sql_insert = "INSERT INTO points_activity
                              (client_id, points_amount, reason, activity_date)
                           VALUES
                              (:client_id, :points_amount, 'Daily Sign-In Bonus', NOW())";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                'client_id'      => $client_id,
                'points_amount'  => $DAILY_BONUS_POINTS
            ]);

            // Update main client table total (wallet points)
            $pdo->prepare("UPDATE clients SET loyalty_points = loyalty_points + :pts WHERE client_id = :cid")
                ->execute(['pts' => $DAILY_BONUS_POINTS, 'cid' => $client_id]);

            $form_message = "<p class='message success'>
                                <i class='bi bi-check-circle-fill'></i>
                                Daily sign-in bonus claimed! You earned {$DAILY_BONUS_POINTS} points.
                             </p>";
        }
    }

    // 4. --- DATABASE FETCH & SELF-HEALING LOGIC ---

    // A. Calculate the TRUE *wallet* total from activity logs (net points)
    $sql_total = "SELECT COALESCE(SUM(points_amount), 0) AS total_points 
                  FROM points_activity 
                  WHERE client_id = :client_id";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute(['client_id' => $client_id]);
    $wallet_points = (int) $stmt_total->fetchColumn();   // this can go up & down

    // B. Calculate LIFETIME earned points (ignore negative rows)
    $sql_lifetime = "SELECT COALESCE(SUM(
                            CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END
                        ), 0) AS lifetime_points
                     FROM points_activity
                     WHERE client_id = :client_id";
    $stmt_lifetime = $pdo->prepare($sql_lifetime);
    $stmt_lifetime->execute(['client_id' => $client_id]);
    $lifetime_points = (int) $stmt_lifetime->fetchColumn();

    // C. Get cached wallet from clients table and self-heal if needed
    $stmt_cache = $pdo->prepare("SELECT loyalty_points FROM clients WHERE client_id = :cid");
    $stmt_cache->execute(['cid' => $client_id]);
    $cached_points = (int) ($stmt_cache->fetchColumn() ?: 0);

    // If logs & clients table don't match, fix the clients table
    if ($wallet_points !== $cached_points) {
        $pdo->prepare("UPDATE clients SET loyalty_points = :pts WHERE client_id = :cid")
            ->execute(['pts' => $wallet_points, 'cid' => $client_id]);
    }

    // From now on:
    //  - $wallet_points   = current usable balance
    //  - $lifetime_points = total points ever earned (never goes down)
    $available_points = $wallet_points;   // what user can spend
    $total_points     = $lifetime_points; // used for tier calculation

    // D. Get the recent activity
    $sql_activity = "SELECT * FROM points_activity 
                     WHERE client_id = :client_id 
                     ORDER BY activity_date DESC 
                     LIMIT 5";
    $stmt_activity = $pdo->prepare($sql_activity);
    $stmt_activity->execute(['client_id' => $client_id]);
    $activities = $stmt_activity->fetchAll(PDO::FETCH_ASSOC);

    // E. Has user claimed daily bonus today?
    $sql_today = "SELECT 1 
                  FROM points_activity 
                  WHERE client_id = :client_id
                    AND reason = 'Daily Sign-In Bonus'
                    AND DATE(activity_date) = CURDATE()
                  LIMIT 1";
    $stmt_today = $pdo->prepare($sql_today);
    $stmt_today->execute(['client_id' => $client_id]);
    $has_claimed_today = (bool) $stmt_today->fetchColumn();

    // 5. --- TIER CALCULATION LOGIC ---
    // IMPORTANT: use *lifetime_points* so tier never drops when user redeems.
    $current_tier = 'Bronze';
    $points_to_next = 1000;
    $progress_percentage = 0;
    $next_tier_name = 'Silver';
    $tier_color_class = 'tier-bronze'; // Default

    if ($lifetime_points >= 4000) {
        $current_tier = 'Platinum';
        $points_to_next = 0;
        $progress_percentage = 100;
        $tier_color_class = 'tier-platinum';

    } elseif ($lifetime_points >= 2500) {
        $current_tier = 'Gold';
        $points_to_next = 4000 - $lifetime_points;
        $progress_percentage = (($lifetime_points - 2500) / (4000 - 2500)) * 100;
        $next_tier_name = 'Platinum';
        $tier_color_class = 'tier-gold';

    } elseif ($lifetime_points >= 1000) {
        $current_tier = 'Silver';
        $points_to_next = 2500 - $lifetime_points;
        $progress_percentage = (($lifetime_points - 1000) / (2500 - 1000)) * 100;
        $next_tier_name = 'Gold';
        $tier_color_class = 'tier-silver';

    } else {
        // Bronze
        $points_to_next = max(0, 1000 - $lifetime_points);
        $progress_percentage = ($lifetime_points / 1000) * 100;
    }

    // 6. --- DYNAMIC MILESTONE LOGIC (5 -> 20 -> 50) ---
    // A. Count actual completed trips
    $sql_trip_count = "SELECT COUNT(*) FROM bookings 
                       WHERE client_id = :client_id 
                       AND booking_status = 'Completed'";
    $stmt_trip_count = $pdo->prepare($sql_trip_count);
    $stmt_trip_count->execute(['client_id' => $client_id]);
    $completed_trips_count = (int)$stmt_trip_count->fetchColumn();

    // B. Define milestones (Must match the Agency file)
    $milestones = [
        5  => 1000,
        20 => 4000,
        50 => 10000
    ];

    // C. Calculate next target
    $next_milestone_target = 0;
    $next_milestone_points = 0;
    $is_max_level = true;

    foreach ($milestones as $target => $points) {
        if ($completed_trips_count < $target) {
            $next_milestone_target = $target;
            $next_milestone_points = $points;
            $is_max_level = false;
            break; 
        }
    }

    // 7. --- REFERRAL CODE LOGIC ---
    $my_referral_code = '';
    
    // Check DB for code
    $stmt_code = $pdo->prepare("SELECT referral_code FROM clients WHERE client_id = :id");
    $stmt_code->execute(['id' => $client_id]);
    $row_code = $stmt_code->fetch(PDO::FETCH_ASSOC);

    if ($row_code && !empty($row_code['referral_code'])) {
        $my_referral_code = $row_code['referral_code'];
    } else {
        // Generate NEW code: REF-NAME-1234
        $clean_name = strtoupper(preg_replace("/[^A-Za-z0-9]/", '', $client_name));
        $clean_name = substr($clean_name, 0, 4);
        if(strlen($clean_name) < 2) $clean_name = "USER"; // Fallback if name is short
        $rand_num = rand(1000, 9999);
        $new_code = "REF-" . $clean_name . "-" . $rand_num;

        // Save to DB
        $update_code = $pdo->prepare("UPDATE clients SET referral_code = :code WHERE client_id = :id");
        $update_code->execute(['code' => $new_code, 'id' => $client_id]);
        $my_referral_code = $new_code;
    }

} catch (PDOException $e) {
    echo "Error fetching rewards data: " . $e->getMessage();
    $total_points = 0;
    $activities = [];
    $has_claimed_today = false;
    $available_points = 0;
    $lifetime_points = 0;
    $current_tier = 'Bronze';
    $points_to_next = 1000;
    $progress_percentage = 0;
    $next_tier_name = 'Silver';
    $tier_color_class = 'tier-bronze';
}

// 8. Include the sidebar
require_once 'includes/sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-coin"></i> Rewards & Points</h1>
        <p>Earn points with every booking and unlock exclusive rewards</p>
    </header>

    <?php echo $form_message; ?>

    <div class="points-banner <?php echo $tier_color_class; ?>">
        <div class="banner-content">
            <span class="total-points-label">Available Points</span>
            <span class="total-points-value"><?php echo number_format($available_points); ?></span>

            <div style="margin-top:4px; font-size:12px; color:#f0f0f0;">
                Lifetime points: <?php echo number_format($lifetime_points); ?>
            </div>

            <div>
                <span class="membership-badge <?php echo strtolower($current_tier); ?>">
                    <?php echo $current_tier; ?> Member
                </span>
                <?php if ($current_tier != 'Platinum'): ?>
                    <span class="points-to-next">
                        <?php echo number_format($points_to_next); ?> points to <?php echo $next_tier_name; ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo $progress_percentage; ?>%;"></div>
            </div>
            <?php if ($current_tier != 'Platinum'): ?>
                <span class="progress-label">
                    Progress to <?php echo $next_tier_name; ?> Tier: <?php echo floor($progress_percentage); ?>%
                </span>
            <?php else: ?>
                 <span class="progress-label">You have reached the highest tier!</span>
            <?php endif; ?>
        </div>
        <i class="bi bi-award-fill banner-icon"></i>
    </div>

    <div class="rewards-section">
        <h2>Membership Tiers</h2>
        <div class="tier-card-row">
            <div class="tier-card <?php echo ($current_tier == 'Bronze') ? 'active-tier' : ''; ?>">
                <h3>Bronze</h3>
                <span>0+ points</span>
                <ul>
                    <li><i class="bi bi-check-circle-fill"></i> 5% bonus points</li>
                    
                </ul>
                <?php if ($current_tier == 'Bronze') echo '<span class="current-tier-tag">Your Current Tier</span>'; ?>
            </div>
            <div class="tier-card tier-silver <?php echo ($current_tier == 'Silver') ? 'active-tier' : ''; ?>">
                <h3>Silver</h3>
                <span>1000+ points</span>
                <ul>
                    <li><i class="bi bi-check-circle-fill"></i> 10% bonus points</li>

                </ul>
                <?php if ($current_tier == 'Silver') echo '<span class="current-tier-tag">Your Current Tier</span>'; ?>
            </div>
            <div class="tier-card tier-gold <?php echo ($current_tier == 'Gold') ? 'active-tier' : ''; ?>">
                <h3>Gold</h3>
                <span>2500+ points</span>
                <ul>
                    <li><i class="bi bi-check-circle-fill"></i> 15% bonus points</li>

                </ul>
                <?php if ($current_tier == 'Gold') echo '<span class="current-tier-tag">Your Current Tier</span>'; ?>
            </div>
            <div class="tier-card tier-platinum <?php echo ($current_tier == 'Platinum') ? 'active-tier' : ''; ?>">
                <h3>Platinum</h3>
                <span>4000+ points</span>
                <ul>
                    <li><i class="bi bi-check-circle-fill"></i> 20% bonus points</li>

                </ul>
                <?php if ($current_tier == 'Platinum') echo '<span class="current-tier-tag">Your Current Tier</span>'; ?>
            </div>
        </div>
    </div>

    <div class="rewards-section">
        <h2>Ways to Earn Points</h2>
        <div class="reward-grid-small">
            <div class="reward-card-small">
                <i class="bi bi-suitcase-lg reward-icon-small blue"></i>
                <h3>Book a Trip</h3>
                <p>10 pts per PKR 1,000</p>
            </div>
            <div class="reward-card-small">
                <i class="bi bi-pencil-square reward-icon-small yellow"></i>
                <h3>Write a Review</h3>
                <p>100 points</p>
            </div>
            
            <div class="reward-card-small" style="grid-column: span 2;">
                <i class="bi bi-person-plus-fill reward-icon-small green"></i>
                <h3>Refer a Friend</h3>
                <p>Earn 500 points for every friend you invite!</p>
                
                <div style="background: #e8f5e9; border: 1px dashed #2e7d32; padding: 10px; border-radius: 8px; margin-top: 10px; display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-weight: bold; font-family: monospace; font-size: 1.2rem; color: #1b5e20;" id="refCode">
                        <?php echo htmlspecialchars($my_referral_code); ?>
                    </span>
                    <button onclick="copyRefCode()" style="border:none; background:#2e7d32; color:white; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:0.8rem;">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                
                <script>
                function copyRefCode() {
                    var codeText = document.getElementById("refCode").innerText;
                    navigator.clipboard.writeText(codeText);
                    alert("Referral code copied: " + codeText);
                }
                </script>
            </div>
            <div class="reward-card-small">
                <i class="bi bi-calendar-check-fill reward-icon-small purple"></i>
                <?php if (!$is_max_level): ?>
                    <h3>Complete <?php echo $next_milestone_target; ?> Trips</h3>
                    <p><?php echo number_format($next_milestone_points); ?> bonus points</p>
                    
                    <div style="margin-top: 10px; width: 100%;">
                        <small style="color: #666; display: flex; justify-content: space-between;">
                            <span>Progress</span>
                            <span><?php echo $completed_trips_count; ?> / <?php echo $next_milestone_target; ?></span>
                        </small>
                        <div style="background:#e0e0e0; height:6px; border-radius:3px; margin-top:4px; overflow:hidden;">
                            <?php 
                                $trip_pct = ($completed_trips_count / $next_milestone_target) * 100; 
                                if($trip_pct > 100) $trip_pct = 100;
                            ?>
                            <div style="background:#a855f7; height:100%; width:<?php echo $trip_pct; ?>%;"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <h3>All Milestones Met!</h3>
                    <p>You are a legend!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rewards-section">
        <h2>Redeem Your Points</h2>
        <div class="reward-grid-large">
            <div class="reward-card-large icon-card">
                <i class="bi bi-calendar-heart reward-icon-large blue"></i>
                <h3>Daily Sign-In Bonus</h3>
                <p style="margin-bottom: 10px;">
                    Click once per day to receive 
                    <strong><?php echo $DAILY_BONUS_POINTS; ?> points</strong> instantly.
                </p>
                <span class="points-cost yellow">
                    +<?php echo $DAILY_BONUS_POINTS; ?> points / day
                </span>

                <form method="POST" style="width:100%; margin-top:15px;">
                    <input type="hidden" name="daily_signin" value="1">
                    <button type="submit" class="btn-redeem"
                        <?php echo $has_claimed_today ? 'disabled style="opacity:0.6; cursor:default;"' : ''; ?>>
                        <?php echo $has_claimed_today ? "Already Claimed Today" : "Claim Today's Bonus"; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="rewards-section">
        <h2>Recent Points Activity</h2>
        <div class="activity-list">
            <?php if (empty($activities)): ?>
                <div class="activity-item">
                    <i class="bi bi-info-circle"></i>
                    <div class="activity-details">
                        <span>No points activity yet.</span>
                        <span class="activity-date">Book a trip to get started!</span>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <?php if ($activity['points_amount'] > 0): ?>
                            <i class="bi bi-check-circle-fill icon-green"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill icon-red"></i>
                        <?php endif; ?>
                        
                        <div class="activity-details">
                            <span><?php echo htmlspecialchars($activity['reason']); ?></span>
                            <span class="activity-date">
                                <?php echo date('M j, Y', strtotime($activity['activity_date'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($activity['points_amount'] > 0): ?>
                            <span class="activity-points green">
                                +<?php echo $activity['points_amount']; ?> pts
                            </span>
                        <?php else: ?>
                            <span class="activity-points red">
                                <?php echo $activity['points_amount']; ?> pts
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php
// 8. Includes the modal, JavaScript, and closing tags
require_once 'includes/footer.php'; 
?>
