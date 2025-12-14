<?php
// user_notifications.php - User notifications page
require_once 'includes/header.php';
require_once 'includes/notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
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
                    <p class="text-muted">You'll receive notifications about your borrowing requests and activities here.
                    </p>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification):
                                $link = get_notification_link($notification, $_SESSION['role'] ?? '');
                                $link = htmlspecialchars($link);
                                ?>
                                <a href="<?= $link ?>"
                                    class="list-group-item list-group-item-action notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>"
                                    data-id="<?= $notification['notification_id'] ?>"
                                    onclick="markReadAndRedirect(event, <?= $notification['notification_id'] ?>, '<?= $link ?>')">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div class="mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <h6 class="mb-0 notification-title text-dark">
                                                    <?= htmlspecialchars($notification['title']) ?>
                                                </h6>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-primary rounded-pill">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-1 text-muted small mt-1">
                                                <?= htmlspecialchars($notification['message']) ?>
                                            </p>
                                        </div>
                                        <small class="text-muted text-nowrap ms-3">
                                            <?= date('M d, H:i', strtotime($notification['created_at'])) ?>
                                        </small>
                                    </div>
                                </a>
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
    function markReadAndRedirect(event, id, url) {
        // We only prevent default if we need to do async work before redirecting
        // But for better UX, we can let the link work naturally if it's just a simple link
        // However, we want to ensure the read status is marked.
        // So we will prevent default, send request, then redirect.
        if (event) event.preventDefault();

        const formData = new FormData();
        formData.append('notification_id', id);
        // We need csrf token. It should be available in the page or header.
        // If header.php is included, getCsrfToken() might be available if defined there.
        // Or we check for a hidden input.
        const csrfInput = document.getElementById('csrf_token') || document.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            formData.append('csrf_token', csrfInput.value);
        }

        // Optimistic UI update
        const item = event.currentTarget;
        item.classList.remove('unread');
        item.classList.add('read');
        const badge = item.querySelector('.badge');
        if (badge) badge.remove();

        fetch('ajax/mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (url && url !== '#' && url !== '') {
                    window.location.href = url;
                }
            })
            .catch(err => {
                console.error(err);
                if (url && url !== '#' && url !== '') {
                    window.location.href = url;
                }
            });
    }

    function markAllAsRead() {
        if (!confirm('Mark all notifications as read?')) return;

        const csrfInput = document.getElementById('csrf_token') || document.querySelector('input[name="csrf_token"]');
        const formData = new FormData();
        if (csrfInput) formData.append('csrf_token', csrfInput.value);

        fetch('ajax/mark_all_read.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
    }
</script>

<style>
    .notification-item {
        border-left: 4px solid transparent;
        transition: all 0.2s ease;
        text-decoration: none;
        /* remove underline from link */
        cursor: pointer;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
        text-decoration: none;
    }

    .notification-item.unread {
        background-color: #f0f9ff;
        border-left-color: #0ea5e9;
    }

    .notification-item.read {
        background-color: #ffffff;
        color: #6c757d;
    }

    .notification-item.unread .notification-title {
        font-weight: 700;
        color: #000;
    }

    .notification-item.read .notification-title {
        font-weight: 400;
        color: #495057;
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