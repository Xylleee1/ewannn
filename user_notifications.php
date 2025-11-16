<?php
// user_notifications.php - User notifications page
require_once 'includes/header.php';
require_once 'includes/notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get notifications
$result = mysqli_query($conn, "
    SELECT SQL_CALC_FOUND_ROWS * FROM user_notifications
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT $offset, $per_page
");

$total_result = mysqli_query($conn, "SELECT FOUND_ROWS() as total");
$total_row = mysqli_fetch_assoc($total_result);
$total_notifications = $total_row['total'];
$total_pages = ceil($total_notifications / $per_page);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Notifications</h2>
                    <p class="text-muted mb-0">Stay updated with your borrowing activities</p>
                </div>
                <?php if (!empty($notifications)): ?>
                    <button class="btn btn-outline-primary" onclick="markAllAsRead()">
                        <i class="bi bi-check-all"></i> Mark All Read
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash display-1 text-muted mb-3"></i>
                    <h4 class="text-muted">No notifications yet</h4>
                    <p class="text-muted">You'll receive notifications about your borrowing requests and activities here.</p>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item notification-item <?= $notification['is_read'] ? '' : 'unread' ?>"
                                     data-id="<?= $notification['notification_id'] ?>">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <h6 class="mb-0 notification-title">
                                                            <?= htmlspecialchars($notification['title']) ?>
                                                        </h6>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="mb-2 text-muted small">
                                                        <?= htmlspecialchars($notification['message']) ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <?= date('M d, Y \a\t H:i', strtotime($notification['created_at'])) ?>
                                                        </small>
                                                        <?php if ($notification['related_id'] && $notification['related_type']): ?>
                                                            <a href="#" class="btn btn-sm btn-outline-primary"
                                                               onclick="viewRelated(<?= $notification['related_id'] ?>, '<?= $notification['related_type'] ?>')">
                                                                View Details
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Notifications pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $total_pages ?>"><?= $total_pages ?></a>
                                </li>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Mark notification as read when clicked
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            if (notificationId) {
                viewNotification(notificationId);
            }
        });
    });
});

// View related item
function viewRelated(relatedId, relatedType) {
    switch (relatedType) {
        case 'borrow_request':
            window.location.href = 'request_tracker.php?request_id=' + relatedId;
            break;
        case 'penalty':
            window.location.href = 'student_penalties.php';
            break;
        default:
            console.log('Unknown related type:', relatedType);
    }
}
</script>

<style>
.notification-item {
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
}

.notification-item:hover {
    background-color: var(--gray-50);
}

.notification-item.unread {
    background: var(--primary-light);
    border-left-color: var(--primary);
}

.notification-item.unread .notification-title {
    font-weight: 600;
}

@media (max-width: 768px) {
    .notification-item {
        padding: 1rem 0.75rem;
    }

    .notification-title {
        font-size: 14px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
