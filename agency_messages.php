<?php
// 1. Set the page title
$page_title = 'Client Messages'; 

// 2. Includes
require_once 'includes/agency_header.php'; 

// 3. --- DATABASE FETCH FOR MESSAGES ---
try {
    $sql = "SELECT * FROM client_messages WHERE agency_id = :agency_id ORDER BY is_read ASC, sent_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['agency_id' => $_SESSION['agency_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error fetching messages: " . $e->getMessage();
    $messages = []; 
}

// 4. Include the sidebar
require_once 'includes/agency_sidebar.php'; 
?>
    
<div class="page-container">
    <header class="page-header">
        <h1><i class="bi bi-chat-dots-fill"></i> Client Messages</h1>
        <p>Review and respond to inquiries from your clients.</p>
    </header>

    <div class="message-inbox">
        <?php if (empty($messages)): ?>
            <div class="message-item-empty">
                <i class="bi bi-chat-dots"></i>
                You have no client messages yet.
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <a href="mark_as_read.php?id=<?php echo $msg['message_id']; ?>" class="message-item-link">
                    <div class="message-item <?php echo $msg['is_read'] ? '' : 'unread'; ?>">
                        <div class="message-sender">
                            <span class="sender-avatar"><?php echo htmlspecialchars(substr($msg['client_name'], 0, 1)); ?></span>
                            <div class="sender-info">
                                <strong><?php echo htmlspecialchars($msg['client_name']); ?></strong>
                                <span><?php echo htmlspecialchars($msg['client_email']); ?></span>
                            </div>
                        </div>
                        <div class="message-content">
                            <span class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></span>
                            <p><?php echo htmlspecialchars($msg['message_content']); ?></p>
                        </div>
                        <div class="message-meta">
                            <span><?php echo date('M j, Y', strtotime($msg['sent_at'])); ?></span>
                            <?php if(!$msg['is_read']): ?>
                                <span class_="unread-dot"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// 5. Includes the footer
require_once 'includes/agency_footer.php'; 
?>