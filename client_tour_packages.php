<?php
// 1. Set the page title
$page_title = 'Tour Packages'; 

// 2. Includes
require_once 'includes/header.php'; 
require_once 'config/db_connect.php'; 

// 3. --- READ FILTERS FROM GET ---
$search        = trim($_GET['search'] ?? '');
$price_sort    = $_GET['price_sort'] ?? '';
$duration_sort = $_GET['duration_sort'] ?? '';

// 4. --- BUILD SQL WITH FILTERS + SORT ---
try {
    $sql = "SELECT p.*, a.agency_name 
            FROM packages p
            JOIN agencies a ON p.agency_id = a.agency_id
            WHERE 1=1";
    $params = [];

    // Search by destination
    if ($search !== '') {
        $sql .= " AND p.destination LIKE :search";
        $params['search'] = '%' . $search . '%';
    }

    // Sorting
    $orderParts = [];

    // Price sort
    if ($price_sort === 'low-high') {
        $orderParts[] = 'p.price ASC';
    } elseif ($price_sort === 'high-low') {
        $orderParts[] = 'p.price DESC';
    }

    // Duration sort
    if ($duration_sort === 'short') {
        $orderParts[] = 'p.duration_days ASC';
    } elseif ($duration_sort === 'long') {
        $orderParts[] = 'p.duration_days DESC';
    }

    // Default order (if nothing selected)
    if (empty($orderParts)) {
        $orderParts[] = 'p.date_posted DESC';
    }

    $sql .= ' ORDER BY ' . implode(', ', $orderParts);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error fetching packages: " . $e->getMessage();
    $packages = []; 
}

// 5. Include the sidebar
require_once 'includes/sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-suitcase-lg"></i> Tour Packages</h1>
        <p>Find and book your next adventure</p>
    </header>

    <!-- FILTER BAR -->
    <form class="filter-bar" method="GET" action="client_tour_packages.php">
        <div class="search-wrapper">
            <i class="bi bi-search"></i>
            <input 
                type="text" 
                name="search"
                placeholder="Search by destination (e.g., Hunza, Skardu)..." 
                value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-wrapper">
            <select name="price_sort">
                <option value="">Sort by Price</option>
                <option value="low-high" <?php echo ($price_sort === 'low-high') ? 'selected' : ''; ?>>
                    Low to High
                </option>
                <option value="high-low" <?php echo ($price_sort === 'high-low') ? 'selected' : ''; ?>>
                    High to Low
                </option>
            </select>
            <select name="duration_sort">
                <option value="">Sort by Duration</option>
                <option value="short" <?php echo ($duration_sort === 'short') ? 'selected' : ''; ?>>
                    Shortest First
                </option>
                <option value="long" <?php echo ($duration_sort === 'long') ? 'selected' : ''; ?>>
                    Longest First
                </option>
            </select>
            <button class="btn-primary-action" style="padding: 10px 15px;" type="submit">
                <i class="bi bi-funnel-fill"></i> Filter
            </button>
        </div>
    </form>

    <div class="package-grid">
        
        <?php if (empty($packages)): ?>
            <p style="grid-column: 1 / -1;">No tour packages match your filters.</p>
        <?php else: ?>
            <?php foreach ($packages as $package): ?>
                <a href="package_details.php?id=<?php echo $package['package_id']; ?>" class="package-card-link">
                    <article class="package-card">
                        <img src="img/<?php echo htmlspecialchars($package['image_path']); ?>" alt="<?php echo htmlspecialchars($package['title']); ?>">
                        
                        <div class="package-details">
                            <h3><?php echo htmlspecialchars($package['title']); ?></h3>
                            <p class="package-info">
                                <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($package['destination']); ?> 
                                | <i class="bi bi-clock-fill"></i> <?php echo htmlspecialchars($package['duration_days']); ?> Days
                            </p>
                            <p class="package-agency">
                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($package['agency_name']); ?>
                            </p>
                            <div class="package-price">PKR <?php echo number_format($package['price']); ?></div>
                        </div>
                        
                        
                    </article>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>
<?php
// 6. Includes the footer
require_once 'includes/footer.php'; 
?>
