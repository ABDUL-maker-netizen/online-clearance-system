<?php
// session_start(); // uncomment if needed
include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/auth.php';

studentAuth();

/* SECURITY CHECK */
if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['student_id'];

/* =========================
   MARK AS READ (ONLY ON VIEW)
========================= */
mysqli_query($conn,
    "UPDATE notifications
     SET status='read'
     WHERE user_id='$user_id'
       AND status='unread'"
);

/* =========================
   FETCH NOTIFICATIONS
========================= */
$query = mysqli_query($conn,
    "SELECT *
     FROM notifications
     WHERE user_id='$user_id'
     ORDER BY id DESC"
);
?>

<!-- =========================================================
     NOTIFICATIONS PAGE CONTENT - UPDATED WITH MATCHING DESIGN
     This is included inside dashboard.php
========================================================= -->

<style>
/* Matching design styles */
.notifications-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 20px;
}

/* Page Header */
.page-header-notifications {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    margin-top: 8px;
}

.page-header-notifications .header-icon {
    width: 48px;
    height: 48px;
    border-radius: 9999px;
    background-color: rgba(30, 64, 175, 0.1);
    color: #1e40af;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-notifications .header-icon i {
    font-size: 2rem;
    line-height: 1;
}

.page-header-notifications h1 {
    font-size: 32px;
    font-weight: 700;
    line-height: 40px;
    letter-spacing: -0.02em;
    color: #111827;
    margin: 0;
    font-family: 'Hanken Grotesk', sans-serif;
}

.page-header-notifications p {
    font-size: 16px;
    font-weight: 400;
    line-height: 24px;
    color: #4b5563;
    margin-top: 4px;
    margin-bottom: 0;
}

/* Notification Item */
.notification-item {
    background: #ffffff;
    border-radius: 0.5rem;
    padding: 20px 24px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    border-left: 4px solid #1e40af;
    display: flex;
    gap: 16px;
    align-items: flex-start;
    transition: box-shadow 0.2s ease, transform 0.15s ease;
}

.notification-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}

.notification-item .icon-wrapper {
    margin-top: 2px;
    flex-shrink: 0;
    line-height: 1;
}

.notification-item .icon-wrapper i {
    font-size: 1.5rem;
}

.notification-item .content {
    flex: 1;
    min-width: 0;
}

.notification-item .content .message {
    font-size: 16px;
    font-weight: 500;
    line-height: 24px;
    color: #111827;
    margin-bottom: 4px;
    word-break: break-word;
    font-family: 'Hanken Grotesk', sans-serif;
}

.notification-item .content .timestamp {
    font-size: 14px;
    font-weight: 400;
    line-height: 20px;
    color: #4b5563;
    margin: 0;
}

/* Unread notification */
.notification-item.unread {
    border-left-color: #1e40af;
    background-color: #f8f9ff;
}

.notification-item.unread .icon-wrapper i {
    color: #1e40af;
}

/* Read notification */
.notification-item.read {
    border-left-color: #006c49;
}

.notification-item.read .icon-wrapper i {
    color: #006c49;
}

/* Empty state */
.empty-state {
    background: #ffffff;
    border-radius: 1rem;
    padding: 48px 32px;
    text-align: center;
    border: 2px dashed #e5e7eb;
}

.empty-state .empty-icon {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 12px;
    display: block;
}

.empty-state h5 {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.empty-state p {
    color: #4b5563;
    font-size: 14px;
    margin-bottom: 0;
}

/* Back button */
.btn-back {
    background-color: #f3f4f6;
    border: none;
    color: #111827;
    font-weight: 500;
    padding: 10px 24px;
    border-radius: 0.5rem;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-family: 'Hanken Grotesk', sans-serif;
    margin-top: 24px;
}

.btn-back:hover {
    background-color: #e5e7eb;
    color: #111827;
    text-decoration: none;
}

/* Responsive */
@media (max-width: 768px) {
    .notifications-page {
        padding: 12px;
    }
    
    .page-header-notifications {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .page-header-notifications h1 {
        font-size: 24px;
        line-height: 32px;
    }
    
    .page-header-notifications p {
        font-size: 14px;
    }
    
    .notification-item {
        padding: 16px 18px;
        gap: 12px;
    }
    
    .notification-item .content .message {
        font-size: 14px;
        line-height: 20px;
    }
    
    .notification-item .content .timestamp {
        font-size: 12px;
    }
    
    .notification-item .icon-wrapper i {
        font-size: 1.25rem;
    }
    
    .empty-state {
        padding: 32px 20px;
    }
    
    .empty-state .empty-icon {
        font-size: 2.5rem;
    }
    
    .btn-back {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .page-header-notifications h1 {
        font-size: 20px;
    }
    
    .page-header-notifications .header-icon {
        width: 40px;
        height: 40px;
    }
    
    .page-header-notifications .header-icon i {
        font-size: 1.5rem;
    }
    
    .notification-item {
        padding: 14px 16px;
        gap: 10px;
    }
    
    .notification-item .content .message {
        font-size: 13px;
        line-height: 18px;
    }
    
    .notification-item .content .timestamp {
        font-size: 11px;
    }
    
    .notification-item .icon-wrapper i {
        font-size: 1.1rem;
    }
}

@media (max-width: 400px) {
    .page-header-notifications h1 {
        font-size: 18px;
    }
    
    .notification-item {
        padding: 12px 14px;
        gap: 8px;
    }
    
    .notification-item .content .message {
        font-size: 12px;
    }
    
    .empty-state h5 {
        font-size: 16px;
    }
}

/* Breadcrumb */
.breadcrumb-custom {
    color: #4b5563;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.breadcrumb-custom a {
    color: #1e40af;
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-custom a:hover {
    text-decoration: underline;
}

.breadcrumb-custom i {
    margin: 0 4px;
}

/* Notification count badge */
.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #dc2626;
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    width: 20px;
    height: 20px;
    border-radius: 9999px;
    margin-left: 6px;
    font-family: 'Hanken Grotesk', sans-serif;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-item {
    animation: slideIn 0.3s ease forwards;
}

.notification-item:nth-child(1) { animation-delay: 0.05s; }
.notification-item:nth-child(2) { animation-delay: 0.10s; }
.notification-item:nth-child(3) { animation-delay: 0.15s; }
.notification-item:nth-child(4) { animation-delay: 0.20s; }
.notification-item:nth-child(5) { animation-delay: 0.25s; }
.notification-item:nth-child(6) { animation-delay: 0.30s; }
.notification-item:nth-child(7) { animation-delay: 0.35s; }
.notification-item:nth-child(8) { animation-delay: 0.40s; }
.notification-item:nth-child(9) { animation-delay: 0.45s; }
.notification-item:nth-child(10) { animation-delay: 0.50s; }
</style>

<div class="notifications-page">

    <!-- Page Header -->
    <div class="page-header-notifications">
        <div class="header-icon">
            <i class="bi bi-bell-fill"></i>
        </div>
        <div>
            <h1>Your Notifications</h1>
            <p>Stay updated on your clearance status and approvals.</p>
        </div>
    </div>

    <!-- Notifications List -->
    <div style="display:flex; flex-direction:column; gap:12px;">

        <?php if (mysqli_num_rows($query) > 0): ?>

            <?php while ($row = mysqli_fetch_assoc($query)): ?>

                <?php
                    $is_unread = ($row['status'] == 'unread');
                    $notification_class = $is_unread ? 'unread' : 'read';
                    $icon_name = $is_unread ? 'bi-info-circle-fill' : 'bi-check-circle-fill';
                ?>

                <div class="notification-item <?php echo $notification_class; ?>">
                    <div class="icon-wrapper">
                        <i class="bi <?php echo $icon_name; ?>"></i>
                    </div>
                    <div class="content">
                        <p class="message">
                            <?php echo htmlspecialchars($row['message']); ?>
                        </p>
                        <p class="timestamp">
                            <i class="bi bi-clock" style="font-size:12px; margin-right:4px;"></i>
                            <?php echo htmlspecialchars($row['created_at']); ?>
                        </p>
                    </div>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <!-- Empty state -->
            <div class="empty-state">
                <i class="bi bi-bell-slash empty-icon"></i>
                <h5>No notifications</h5>
                <p>You're all caught up! No new notifications at this time.</p>
            </div>

        <?php endif; ?>

    </div>

    <!-- Back to Dashboard -->
    <a href="dashboard.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

</div>