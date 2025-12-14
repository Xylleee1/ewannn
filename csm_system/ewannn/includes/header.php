<?php
// includes/header.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Get active page for highlighting
$active = basename($_SERVER['PHP_SELF']);

// Get unread notification count for logged-in user
$unread_notifications = 0;
if (isset($_SESSION['user_id'])) {
  $unread_notifications = get_unread_notification_count($_SESSION['user_id']);

  // FIX: Ensure full_name is always available; fallback to email if needed
  if (empty($_SESSION['full_name']) || empty($_SESSION['email'])) {
    $uid_safe = intval($_SESSION['user_id']);
    $u_query = mysqli_query($conn, "SELECT full_name, email FROM users WHERE user_id = $uid_safe");
    if ($u_row = mysqli_fetch_assoc($u_query)) {
      if (empty($_SESSION['full_name']))
        $_SESSION['full_name'] = $u_row['full_name'];
      if (empty($_SESSION['email']))
        $_SESSION['email'] = $u_row['email'];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="<?= generate_csrf_token() ?>">
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
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 2px 6px rgba(0, 0, 0, 0.08);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      overflow-y: scroll;
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
      height: 70px;
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

    /* Active Link Style */
    .text-orange {
      color: #ff6600 !important;
    }

    /* Dropdown */
    .dropdown {
      position: relative;
    }

    .dropdown-toggle::after {
      content: none;
    }

    /* Fix: Remove conflicting display/position properties to let Bootstrap handle toggle/popping */
    .dropdown-menu {
      margin-top: 10px;
      background: white;
      border-radius: var(--radius);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      padding: 0;
      min-width: 350px; /* Notification dropdown width */
      max-width: 400px;
      border: 1px solid var(--gray-200);
      z-index: 9999 !important;
      overflow: hidden;
    }

    /* FIX: Dynamic width for user dropdown based on name length */
    .dropdown-menu.user-compact {
      min-width: 100%;  /* Match width of the parent (name toggle) */
      width: max-content; /* Ensure it expands if content is wider than name */
      max-width: none;
      padding: 6px 0 !important;
    }

    .dropdown-menu.user-compact .dropdown-item {
      padding: 8px 16px !important;
      font-size: 14px !important;
      gap: 8px;
      white-space: nowrap; /* Prevent text wrapping */
    }

    .dropdown-menu.user-compact .dropdown-item i {
      font-size: 15px;
    }

    .dropdown-menu.user-compact .dropdown-divider {
      margin: 6px 0;
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
    .notification-list-item {
      padding: 0;
      margin: 0;
      position: relative; /* For absolute positioning of action buttons */
    }
    
    .notification-list-item:hover .notification-actions {
        opacity: 1;
    }

    /* Make the link behave like the container */
    a.notification-link-item {
      display: flex;
      padding: 12px 16px;
      padding-right: 40px; /* Make space for delete button */
      text-decoration: none;
      color: inherit;
      width: 100%;
      align-items: flex-start;
      border-bottom: 1px solid var(--gray-200);
      cursor: pointer;
      transition: background-color 0.2s ease;
    }

    a.notification-link-item:hover {
      background-color: var(--gray-100);
    }

    /* Flex layout for content */
    .notification-content {
      flex: 1;
      min-width: 0;
      /* Critical for text overflow */
      margin-right: 12px;
    }

    .notification-title {
      font-weight: 600;
      font-size: 13px;
      color: var(--gray-900);
      margin-bottom: 2px;
      word-break: break-word;
      /* Prevent overlap */
      white-space: normal;
    }

    .notification-message {
      font-size: 12px;
      color: var(--gray-700);
      line-height: 1.3;
      word-break: break-word;
      /* Prevent overlap */
      white-space: normal;
      margin: 0;
    }

    .notification-time {
      white-space: nowrap;
      font-size: 11px;
      color: #999;
      flex-shrink: 0;
      margin-top: 4px;
    }

    /* Read vs Unread States */
    .notification-item.unread a.notification-link-item {
      background: #ffe6d5;
      /* Orange tint */
    }

    .notification-item.read a.notification-link-item {
      background: #f1f1f1;
      /* Grey tint */
      color: #6c757d;
    }

    .notification-item.unread .notification-title {
      font-weight: 700;
    }

    .notification-toggle {
      cursor: pointer;
    }

    .notification-scroll {
      max-height: 350px;
      /* Requirement: 350px */
      overflow-y: auto;
      overflow-x: hidden;
    }

    .notification-scroll::-webkit-scrollbar {
      width: 6px;
    }

    .notification-scroll::-webkit-scrollbar-track {
      background: var(--gray-100);
      border-radius: 3px;
    }

    .notification-scroll::-webkit-scrollbar-thumb {
      background: var(--gray-300);
      border-radius: 3px;
    }

    .notification-scroll::-webkit-scrollbar-thumb:hover {
      background: var(--gray-700);
    }
    
    /* Notification Actions (Delete btn) */
    .notification-actions {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0; /* Hidden by default */
        transition: opacity 0.2s ease;
        z-index: 10;
    }
    
    /* Show on mobile by default or adjust UX as needed */
    @media (max-width: 768px) {
        .notification-actions {
            opacity: 1; 
        }
    }
    
    .btn-delete-notif {
        border: none;
        background: transparent;
        color: #999;
        cursor: pointer;
        padding: 5px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .btn-delete-notif:hover {
        color: #F44336;
        background: rgba(244, 67, 54, 0.1);
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
  <script>
    function getCsrfToken() {
      return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    // Helper to handle redirection locally if AJAX fails or returns no URL
    function redirectBasedOnType(type, relatedId) {
      type = type.trim(); // Ensure no whitespace
      if (type === 'borrow_request') window.location.href = 'borrow_request_view.php?id=' + relatedId;
      else if (type === 'approval') window.location.href = 'dashboard.php#approvals';
      else if (type === 'user') window.location.href = 'manage_users.php';
      else if (type === 'apparatus') window.location.href = 'inventory.php'; // Wraps manage_inventory
      else if (type === 'penalty') window.location.href = 'student_penalties.php';
      else if (type === 'transaction') window.location.href = 'transactions.php';
      else window.location.href = 'user_notifications.php'; // True fallback (accessible to all)
    }

    function markAllAsRead(event) {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }

      const formData = new FormData();
      formData.append('csrf_token', getCsrfToken());

      fetch('ajax/mark_all_read.php', { /* Requirement: mark_all_read.php */
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Remove unread count badge
            const badges = document.querySelectorAll('.badge.bg-danger');
            badges.forEach(el => el.remove());

            // Remove unread class and style from items
            const items = document.querySelectorAll('.notification-item');
            items.forEach(el => {
              el.classList.remove('unread');
              el.classList.add('read');
              // Force remove inline styles if any and set explicit read style fallback
              el.style.background = '#f1f1f1';
              const title = el.querySelector('.notification-title');
              if (title) title.style.fontWeight = 'normal';
            });

            // Also update link items inside
            const links = document.querySelectorAll('a.notification-link-item');
            links.forEach(el => {
              el.style.background = '#f1f1f1';
              el.style.color = '#6c757d';
            });


            // Remove "New" badges
            const newBadges = document.querySelectorAll('.notification-item .badge.bg-primary');
            newBadges.forEach(el => el.remove());

            // Remove "Mark all read" button
            const markBtn = document.querySelector('.btn-mark-all');
            if (markBtn) markBtn.remove();
          }
        })
        .catch(error => console.error('Error:', error));
    }
    
    function deleteNotification(event, id) {
        if (event) {
            event.preventDefault();
            event.stopPropagation(); // Stop click from triggering viewNotification
        }
        
        if(!confirm('Delete this notification?')) return;
        
        const formData = new FormData();
        formData.append('notification_id', id);
        formData.append('csrf_token', getCsrfToken());
        
        fetch('ajax/delete_notification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = document.querySelector(`.notification-item[data-id="${id}"]`);
                if (item) item.remove();
                
                // Update Badge Count if needed (optional implementation)
                // location.reload(); // Simple way to sync count, or implement dynamic counter
            } else {
                alert('Failed to delete notification');
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    function deleteAllNotifications(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        if(!confirm('Are you sure you want to delete ALL notifications? This cannot be undone.')) return;
        
        const formData = new FormData();
        formData.append('csrf_token', getCsrfToken());
        
        fetch('ajax/delete_all_notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('notification-list');
                if(list) {
                    list.innerHTML = '<li class="text-center py-3 text-muted"><i class="bi bi-bell-slash"></i><br><small>No notifications yet</small></li>';
                }
                // Update badge
                const badges = document.querySelectorAll('.badge.bg-danger');
                badges.forEach(el => el.remove());
            } else {
                alert('Failed to delete notifications');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function viewNotification(event, id, url) {
      // Prevent standard navigation first
      if (event) event.preventDefault();
      
      // If clicked on delete button, do nothing (handled by stopPropagation, but double check)
      if (event.target.closest('.btn-delete-notif')) return;

      // Optimistically update UI
      const item = document.querySelector(`.notification-item[data-id="${id}"]`);
      if (item) {
        item.classList.remove('unread');
        item.classList.add('read');
        const badge = item.querySelector('.badge.bg-primary');
        if (badge) badge.remove();
        const linkElem = item.querySelector('a.notification-link-item');
        if (linkElem) {
          // Optional: visual indication that it's being processed
        }
      }

      // Mark as read via AJAX
      const formData = new FormData();
      formData.append('notification_id', id);
      formData.append('csrf_token', getCsrfToken());

      fetch('ajax/mark_notification_read.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          // If successful, we could log it or whatever
        })
        .catch(error => {
          console.error('Error:', error);
        })
        .finally(() => {
          // ALWAYS redirect if a URL is present
          if (url && url !== '#' && url !== 'null' && url !== '') {
            window.location.href = url;
          }
        });
    }
  </script>
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
              <li class="nav-item"><a class="nav-link <?= $active == 'dashboard.php' ? 'text-orange fw-bold' : '' ?>"
                  href="dashboard.php"><i class="bi bi-house"></i>
                  <span>Dashboard</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'manage_inventory.php' ? 'text-orange fw-bold' : '' ?>"
                  href="manage_inventory.php"><i class="bi bi-box"></i>
                  <span>Inventory</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'transactions.php' ? 'text-orange fw-bold' : '' ?>"
                  href="transactions.php"><i class="bi bi-arrow-left-right"></i>
                  <span>Transactions</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'penalties.php' ? 'text-orange fw-bold' : '' ?>"
                  href="penalties.php"><i class="bi bi-exclamation-triangle"></i>
                  <span>Penalties</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'view_requests.php' ? 'text-orange fw-bold' : '' ?>"
                  href="view_requests.php"><i class="bi bi-list-check"></i>
                  <span>Requests</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'calendar.php' ? 'text-orange fw-bold' : '' ?>"
                  href="calendar.php"><i class="bi bi-calendar3"></i>
                  <span>Calendar</span></a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'student'): ?>
              <li class="nav-item"><a class="nav-link <?= $active == 'dashboard.php' ? 'text-orange fw-bold' : '' ?>"
                  href="dashboard.php"><i class="bi bi-house"></i>
                  <span>Dashboard</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'borrow_request.php' ? 'text-orange fw-bold' : '' ?>"
                  href="borrow_request.php"><i class="bi bi-pencil-square"></i>
                  <span>Borrow</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'request_tracker.php' ? 'text-orange fw-bold' : '' ?>"
                  href="request_tracker.php"><i class="bi bi-list-task"></i>
                  <span>Track</span></a></li>
              <li class="nav-item"><a
                  class="nav-link <?= $active == 'student_penalties.php' ? 'text-orange fw-bold' : '' ?>"
                  href="student_penalties.php"><i class="bi bi-exclamation-circle"></i>
                  <span>Penalties</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'calendar.php' ? 'text-orange fw-bold' : '' ?>"
                  href="calendar.php"><i class="bi bi-calendar3"></i>
                  <span>Calendar</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'manage_inventory.php' ? 'text-orange fw-bold' : '' ?>"
                  href="manage_inventory.php"><i class="bi bi-box"></i>
                  <span>Inventory</span></a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'faculty'): ?>
              <li class="nav-item"><a class="nav-link <?= $active == 'dashboard.php' ? 'text-orange fw-bold' : '' ?>"
                  href="dashboard.php"><i class="bi bi-house"></i>
                  <span>Dashboard</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'view_requests.php' ? 'text-orange fw-bold' : '' ?>"
                  href="view_requests.php"><i class="bi bi-check-circle"></i>
                  <span>Approvals</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'calendar.php' ? 'text-orange fw-bold' : '' ?>"
                  href="calendar.php"><i class="bi bi-calendar3"></i>
                  <span>Calendar</span></a></li>
              <li class="nav-item"><a class="nav-link <?= $active == 'manage_inventory.php' ? 'text-orange fw-bold' : '' ?>"
                  href="manage_inventory.php"><i class="bi bi-box"></i>
                  <span>Inventory</span></a></li>
            <?php endif; ?>

            <!-- Notification Bell -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationMenu" role="button"
                data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <?php if ($unread_notifications > 0): ?>
                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                    style="font-size: 10px;">
                    <?= $unread_notifications > 99 ? '99+' : $unread_notifications ?>
                  </span>
                <?php endif; ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationMenu">
                <?php $notifications = get_user_notifications($_SESSION['user_id'], 10); ?>
                <li class="dropdown-header px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-gray-900">Notifications</strong>
                         <?php if (!empty($notifications) || $unread_notifications > 0): ?>
                            <div class="d-flex gap-2 align-items-center">
                                <button class="btn btn-sm btn-link text-decoration-none small fw-medium p-0 btn-mark-all text-nowrap"
                                onclick="markAllAsRead(event)" style="font-size:12px;">Mark all read</button>
                                
                                <div class="vr"></div>
                                
                                <button class="btn btn-sm text-danger text-decoration-none small fw-medium p-0 text-nowrap"
                                onclick="deleteAllNotifications(event)" style="font-size:12px;" title="Delete All">
                                    <i class="bi bi-trash"></i> All
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
                <li>
                  <hr class="dropdown-divider my-1">
                </li>
                <div id="notification-list" class="notification-scroll">
                  <?php if (empty($notifications)): ?>
                    <li class="text-center py-3 text-muted">
                      <i class="bi bi-bell-slash"></i><br>
                      <small>No notifications yet</small>
                    </li>
                  <?php else:
                    foreach ($notifications as $notification):
                      // Fallback logic for links if empty
                      $link = !empty($notification['link']) ? $notification['link'] : '';
                      if (empty($link)) {
                        switch ($notification['related_type']) {
                          case 'borrow_request':
                          case 'approval':
                            $link = ($_SESSION['role'] === 'student') ? 'request_tracker.php?id=' . $notification['related_id'] : 'view_requests.php?id=' . $notification['related_id'];
                            break;
                          case 'user':
                            $link = 'manage_users.php';
                            break;
                          case 'apparatus':
                            $link = 'manage_inventory.php';
                            break;
                          case 'penalty':
                            $link = ($_SESSION['role'] === 'student') ? 'student_penalties.php' : 'penalties.php';
                            break;
                          case 'transaction':
                            $link = ($_SESSION['role'] === 'student') ? 'request_tracker.php' : 'transactions.php';
                            break;
                          default:
                            $link = '#';
                        }
                      }
                      $link = htmlspecialchars($link, ENT_QUOTES);
                      ?>
                      <li class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?> notification-list-item"
                        data-id="<?= $notification['notification_id'] ?>">
                        <div class="notification-actions">
                            <button class="btn-delete-notif" onclick="deleteNotification(event, <?= $notification['notification_id'] ?>)" title="Delete Notification">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <a class="notification-link-item" href="<?= $link ?>"
                          onclick="viewNotification(event, <?= $notification['notification_id'] ?>, '<?= $link ?>')">
                          <div class="notification-content">
                            <div class="notification-title">
                              <?= htmlspecialchars($notification['title']) ?>
                            </div>
                            <p class="notification-message">
                              <?= htmlspecialchars(substr($notification['message'], 0, 100)) . (strlen($notification['message']) > 100 ? '...' : '') ?>
                            </p>
                            <div class="notification-time">
                                <?= date('M d, H:i', strtotime($notification['created_at'])) ?>
                            </div>
                          </div>
                          <div class="d-flex flex-column align-items-end">
                            <?php if (!$notification['is_read']): ?>
                              <span class="badge bg-primary mt-1" style="font-size: 9px;">New</span>
                            <?php endif; ?>
                          </div>
                        </a>
                      </li>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <?php if (!empty($notifications)): ?>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                  <li><a class="dropdown-item text-center text-primary" href="user_notifications.php">View All
                      Notifications</a></li>
                <?php endif; ?>
              </ul>
            </li>

            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <!-- FIX: Display full name or email specifically -->
                <span><?= htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : (!empty($_SESSION['email']) ? $_SESSION['email'] : ($_SESSION['username'] ?? 'User'))) ?></span>
                <i class="bi bi-chevron-down small ms-1"></i>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 user-compact" aria-labelledby="userMenu">

                <?php if ($_SESSION['role'] === 'admin'): ?>
                  <li><a class="dropdown-item" href="manage_users.php"><i class="bi bi-people"></i> Manage Users</a></li>
                  <li><a class="dropdown-item" href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
                  <li><a class="dropdown-item" href="notifications.php"><i class="bi bi-bell"></i> Notifications</a></li>
                  <li><a class="dropdown-item" href="bulk_operations.php"><i class="bi bi-layers"></i> Bulk Operations</a>
                  </li>
                  <li><a class="dropdown-item" href="activity_logs.php"><i class="bi bi-activity"></i> Activity Logs</a>
                  </li>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                <?php endif; ?>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i>
                    Logout</a></li>
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