<?php
// 1. Set the page title
$page_title = 'Settings'; 

// 2. Includes
require_once 'includes/agency_header.php'; 
require_once 'config/db_connect.php'; 
require_once 'includes/agency_sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-gear"></i> Agency Settings</h1>
        <p>Manage your account, password, and notification preferences.</p>
    </header>

    <div class="content-card placeholder-card">
        <i class="bi bi-tools"></i>
        <h2>Feature Coming Soon</h2>
        <p>This section is under construction. You will soon be able to manage your agency profile, update your password, and set notification preferences here.</p>
    </div>
</div>

<?php
// 5. Includes the footer
require_once 'includes/agency_footer.php'; 
?>