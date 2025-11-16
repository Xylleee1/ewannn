<?php
// includes/header.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get unread notification count for logged-in user
$unread_notifications = 0;
if (isset($_SESSION['user_id'])) {
    $unread_notifications = get_unread_notification_count($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CSM Borrowing System</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
/* ========== MODERN MINIMAL DESIGN SYSTEM ========== */
:root {
  --primary: #FF6600;
  --primary-dark: #E65100;
  --primary-light: #FFE0CC;
  --gray-50: #FAFAFA;
  --gray-100: #F5F5F5;
  --gray-200: #EEEEEE;
  --gray-300: #E0E0E0;
  --gray-700: #616161;
  --gray-900: #212121;
  --radius: 8px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
  --shadow-md: 0 2px 6px rgba(0,0,0,0.08);
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 14px;
  color: var(--gray-900);
  background: var(--gray-50);
  line-height: 1.6;
}

/* ========== NAVBAR ========== */
.navbar {
  position: fixed;
  top: 0;
  width: 100%;
  z-index: 1030;
  background: white;
  border-bottom: 1px solid var(--gray-200);
  box-shadow: var(--shadow-sm);
  padding: 0.75rem 1.5rem;
  height: 64px;
  display: flex;
  align-items: center;
}

.navbar-brand {
  display: flex;
  align-items: center;
  font-weight: 600;
  font-size: 18px;
  color: var(--primary);
  text-decoration: none;
  gap: 10px;
}

.navbar-brand img {
  height: 36px;
  width: auto;
  border-radius: 6px;
}

.navbar-nav {
  display: flex;
  align-items: center;
  gap: 4px;
  list-style: none;
  margin: 0;
}

.navbar .nav-link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  color: var(--gray-700);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  border-radius: var(--radius);
  transition: all 0.2s ease;
}

.navbar .nav-link:hover {
  background: var(--gray-100);
  color: var(--primary);
}

.navbar .nav-link i {
  font-size: 16px;
}

/* Dropdown */
.dropdown {
  position: relative;
}

.dropdown-toggle::after {
  content: none;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 8px;
  background: white;
  border-radius: var(--radius);
  box-shadow: var(--shadow-md);
  padding: 8px;
  min-width: 200px;
  display: none;
  border: 1px solid var(--gray-200);
}


.dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  color: var(--gray-700);
  text-decoration: none;
  font-size: 14px;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.dropdown-item:hover {
  background: var(--gray-100);
  color: var(--primary);
}

.dropdown-divider {
  height: 1px;
  background: var(--gray-200);
  margin: 8px 0;
  border: none;
}

.text-danger {
  color: #F44336 !important;
}

/* ========== MAIN CONTENT ========== */
main.main-content {
  padding: 88px 24px 40px;
  min-height: 100vh;
  max-width: 1400px;
  margin: 0 auto;
}

/* ========== FOOTER ========== */
.footer {
  width: 100%;
  background: white;
  border-top: 1px solid var(--gray-200);
  color: var(--gray-700);
  text-align: center;
  padding: 16px 0;
  font-size: 13px;
  margin-top: auto;
}

/* ========== NOTIFICATION STYLES ========== */
.notification-item.unread {
  background: var(--primary-light);
  border-left: 3px solid var(--primary);
}

.notification-item.unread .notification-title {
  font-weight: 600;
}

.notification-link {
  padding: 12px 8px !important;
  margin: 0 !important;
  border-radius: 6px !important;
}

.notification-link:hover {
  background: var(--gray-100) !important;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .navbar {
    padding: 0.75rem 1rem;
  }

  .navbar-brand {
    font-size: 16px;
  }

  .navbar-brand img {
    height: 32px;
  }

  .navbar .nav-link {
    padding: 6px 10px;
    font-size: 13px;
  }

  .navbar .nav-link span {
    display: none;
  }

  main.main-content {
    padding: 80px 16px 24px;
  }
}
</style>
</head>

<body>

<!-- ========== NAVBAR ========== -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">
      <img src="assets/image.png" alt="Logo">
      <span>CSM-ABS</span>
    </a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
<?php if (isset($_SESSION['user_id'])): ?>

    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'assistant'): ?>
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-house"></i> <span>Dashboard</span></a></li>
        <li class="nav-item"><a class="nav-link" href="manage_inventory.php"><i class="bi bi-box"></i> <span>Inventory</span></a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="bi bi-arrow-left-right"></i> <span>Transactions</span></a></li>
        <li class="nav-item"><a class="nav-link" href="penalties.php"><i class="bi bi-exclamation-triangle"></i> <span>Penalties</span></a></li>
        <li class="nav-item"><a class="nav-link" href="view_requests.php"><i class="bi bi-list-check"></i> <span>Requests</span></a></li>
        <li class="nav-item"><a class="nav-link" href="calendar.php"><i class="bi bi-calendar3"></i> <span>Calendar</span></a></li>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'student'): ?>
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-house"></i> <span>Dashboard</span></a></li>
        <li class="nav-item"><a class="nav-link" href="borrow_request.php"><i class="bi bi-pencil-square"></i> <span>Borrow</span></a></li>
        <li class="nav-item"><a class="nav-link" href="request_tracker.php"><i class="bi bi-list-task"></i> <span>Track</span></a></li>
        <li class="nav-item"><a class="nav-link" href="student_penalties.php"><i class="bi bi-exclamation-circle"></i> <span>Penalties</span></a></li>
        <li class="nav-item"><a class="nav-link" href="calendar.php"><i class="bi bi-calendar3"></i> <span>Calendar</span></a></li>
        <li class="nav-item"><a class="nav-link" href="manage_inventory.php"><i class="bi bi-box"></i> <span>Inventory</span></a></li>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'faculty'): ?>
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-house"></i> <span>Dashboard</span></a></li>
        <li class="nav-item"><a class="nav-link" href="view_requests.php"><i class="bi bi-check-circle"></i> <span>Approvals</span></a></li>
        <li class="nav-item"><a class="nav-link" href="calendar.php"><i class="bi bi-calendar3"></i> <span>Calendar</span></a></li>
        <li class="nav-item"><a class="nav-link" href="manage_inventory.php"><i class="bi bi-box"></i> <span>Inventory</span></a></li>
    <?php endif; ?>

    <!-- Notification Bell -->
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bell"></i>
            <?php if ($unread_notifications > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 10px;">
                    <?= $unread_notifications > 99 ? '99+' : $unread_notifications ?>
                </span>
            <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="notificationMenu" style="min-width: 350px; max-width: 400px;">
            <li class="dropdown-header d-flex justify-content-between align-items-center">
                <strong>Notifications</strong>
                <?php if ($unread_notifications > 0): ?>
                    <a href="#" class="text-primary text-decoration-none small" onclick="markAllAsRead()">Mark all read</a>
                <?php endif; ?>
            </li>
            <li><hr class="dropdown-divider"></li>
            <div id="notification-list" style="max-height: 300px; overflow-y: auto;">
                <?php
                $notifications = get_user_notifications($_SESSION['user_id'], 10);
                if (empty($notifications)): ?>
                    <li class="text-center py-3 text-muted">
                        <i class="bi bi-bell-slash"></i><br>
                        <small>No notifications yet</small>
                    </li>
                <?php else:
                    foreach ($notifications as $notification): ?>
                        <li class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>" data-id="<?= $notification['notification_id'] ?>">
                            <a class="dropdown-item notification-link" href="#" onclick="viewNotification(<?= $notification['notification_id'] ?>)">
                                <div class="d-flex">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <strong class="notification-title" style="font-size: 13px; color: var(--gray-900);">
                                                <?= htmlspecialchars($notification['title']) ?>
                                            </strong>
                                            <small class="text-muted ms-2" style="font-size: 11px; white-space: nowrap;">
                                                <?= date('M d, H:i', strtotime($notification['created_at'])) ?>
                                            </small>
                                        </div>
                                        <p class="mb-1" style="font-size: 12px; color: var(--gray-700); line-height: 1.3;">
                                            <?= htmlspecialchars(substr($notification['message'], 0, 100)) . (strlen($notification['message']) > 100 ? '...' : '') ?>
                                        </p>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary" style="font-size: 10px;">New</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($notifications)): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-center text-primary" href="user_notifications.php">View All Notifications</a></li>
            <?php endif; ?>
        </ul>
    </li>

    <li class="nav-item dropdown">
  <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="bi bi-person-circle"></i>
    <span><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?></span>
    <i class="bi bi-chevron-down small ms-1"></i>
  </a>
  <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="userMenu">
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <li><a class="dropdown-item" href="manage_users.php"><i class="bi bi-people"></i> Manage Users</a></li>
      <li><a class="dropdown-item" href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
      <li><a class="dropdown-item" href="notifications.php"><i class="bi bi-bell"></i> Notifications</a></li>
      <li><a class="dropdown-item" href="bulk_operations.php"><i class="bi bi-layers"></i> Bulk Operations</a></li>
      <li><a class="dropdown-item" href="activity_logs.php"><i class="bi bi-activity"></i> Activity Logs</a></li>
      <li><hr class="dropdown-divider"></li>
    <?php endif; ?>
    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
  </ul>
</li>


<?php else: ?>
    <li class="nav-item">
        <a class="nav-link" href="index.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
    </li>
<?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- ========== MAIN CONTENT START ========== -->
<main class="main-content">