<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// Access control - Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger text-center mt-5'>Access denied. Admin only.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$message = "";
$msgClass = "";

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];

        if ($role === 'student') {
            $email = preg_replace('/@.+$/', '@wmsu.edu.ph', $email);
        }

        $check = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' OR email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $message = "Username or email already exists.";
            $msgClass = "danger";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssss", $username, $email, $hashed_password, $full_name, $role);

            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);

                // If student, insert into students table
                if ($role === 'student') {
                    $names = explode(' ', $full_name);
                    $first_name = $names[0] ?? '';
                    $last_name = $names[count($names)-1] ?? '';
                    $middle_initial = count($names) > 2 ? $names[1] : '';

                    $stmt2 = mysqli_prepare($conn, "INSERT INTO students (user_id, first_name, last_name, middle_initial) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt2, "isss", $user_id, $first_name, $last_name, $middle_initial);
                    mysqli_stmt_execute($stmt2);
                }

                $message = "User added successfully!";
                $msgClass = "success";
                add_log($conn, $_SESSION['user_id'], "Add User", "Added user: $username");
            } else {
                $message = "Error adding user.";
                $msgClass = "danger";
            }
        }
    }

    // Edit user
    if (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];

        if ($role === 'student') {
            $email = preg_replace('/@.+$/', '@wmsu.edu.ph', $email);
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET username=?, email=?, full_name=?, role=? WHERE user_id=?");
        mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $full_name, $role, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            // Update or insert into students table if student
            if ($role === 'student') {
                $names = explode(' ', $full_name);
                $first_name = $names[0] ?? '';
                $last_name = $names[count($names)-1] ?? '';
                $middle_initial = count($names) > 2 ? $names[1] : '';

                // Check if student exists
                $check_student = mysqli_query($conn, "SELECT * FROM students WHERE user_id = $user_id");
                if (mysqli_num_rows($check_student) > 0) {
                    $stmt2 = mysqli_prepare($conn, "UPDATE students SET first_name=?, last_name=?, middle_initial=? WHERE user_id=?");
                    mysqli_stmt_bind_param($stmt2, "sssi", $first_name, $last_name, $middle_initial, $user_id);
                    mysqli_stmt_execute($stmt2);
                } else {
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO students (user_id, first_name, last_name, middle_initial) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt2, "isss", $user_id, $first_name, $last_name, $middle_initial);
                    mysqli_stmt_execute($stmt2);
                }
            } else {
                // If role changed from student to something else, delete from students
                mysqli_query($conn, "DELETE FROM students WHERE user_id = $user_id");
            }

            $message = "User updated successfully!";
            $msgClass = "success";
            add_log($conn, $_SESSION['user_id'], "Edit User", "Updated user ID: $user_id");
        } else {
            $message = "Error updating user.";
            $msgClass = "danger";
        }
    }

    // Reset password
    if (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id']);
        $new_password = trim($_POST['new_password']);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE user_id=?");
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $message = "Password reset successfully!";
            $msgClass = "success";
            add_log($conn, $_SESSION['user_id'], "Reset Password", "Reset password for user ID: $user_id");
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    if ($user_id == $_SESSION['user_id']) {
        $message = "You cannot delete your own account.";
        $msgClass = "danger";
    } else {
        // Check if user is referenced in borrow_requests
        $check_borrow = mysqli_query($conn, "SELECT * FROM borrow_requests WHERE faculty_id = $user_id OR student_id = $user_id LIMIT 1");
        if (mysqli_num_rows($check_borrow) > 0) {
            $message = "Cannot delete user because they are referenced in borrow requests.";
            $msgClass = "danger";
        } else {
            mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id");
            $message = "User deleted successfully.";
            $msgClass = "success";
            add_log($conn, $_SESSION['user_id'], "Delete User", "Deleted user ID: $user_id");
        }
    }
}

// Fetch all users with students' first, last, MI
$search = $_GET['search'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';

$query = "SELECT u.*, s.first_name, s.last_name, s.middle_initial 
          FROM users u
          LEFT JOIN students s ON u.user_id = s.user_id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (
        u.username LIKE '%$search%' OR 
        u.full_name LIKE '%$search%' OR 
        u.email LIKE '%$search%' OR
        CONCAT(s.last_name, ' ', s.first_name, ' ', s.middle_initial) LIKE '%$search%'
    )";
}

if (!empty($filter_role)) {
    $query .= " AND u.role = '$filter_role'";
}

$query .= " ORDER BY u.user_id DESC";

$users = mysqli_query($conn, $query);

// Get statistics
$total_users = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users"));
$admins = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE role='admin'"));
$faculty = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE role='faculty'"));
$students = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE role='student'"));
$assistants = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE role='assistant'"));
?>

<style>
.page-header { margin-bottom: 28px; padding-bottom: 20px; border-bottom: 3px solid #FF6F00; }
.page-header h2 { font-size: 26px; margin:0; font-weight:700; background:linear-gradient(135deg,#FF6F00,#FFA040); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }

.stat-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; padding:20px; border-radius:12px; border:1px solid #ddd; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.stat-card h3 { font-size:32px; font-weight:700; color:#cc5500; margin:0 0 8px 0; }
.stat-card p { font-size:13px; color:#000; margin:0; }

.users-card { background:#fff; padding:28px; border-radius:14px; box-shadow:0 3px 12px rgba(255,111,0,0.08); }

.header-actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
.header-actions h3 { font-size:20px; font-weight:600; margin:0; color:#111827; }

.btn-add { background:linear-gradient(135deg,#FF6B00,#FF3D00); color:white; padding:10px 20px; border-radius:14px; text-decoration:none; font-weight:600; transition:all 0.3s; border:none; cursor:pointer; }
.btn-add:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(255,111,0,0.35); }

.filter-form { display:flex; gap:12px; margin-bottom:24px; }
.filter-form input, .filter-form select { padding:10px 14px; border:1px solid #ddd; border-radius:12px; font-size:14px; background:#fafafa; }
.filter-form input { flex:1; }
.filter-form select { min-width:150px; }
.btn-reset { background:#FF6F00; color:white; padding:10px 16px; border-radius:12px; text-decoration:none; font-weight:600; border:none; cursor:pointer; }

.table { width:100%; border-collapse:collapse; }
.table thead { background:linear-gradient(135deg,#FF6F00,#FFA040); color:#fff; }
.table th, .table td { padding:14px 16px; text-align:left; font-size:14px; border-bottom:1px solid #f0f0f0; }
.table tbody tr:hover { background:#f5f5f5; }

.badge { padding:6px 12px; border-radius:20px; font-weight:600; font-size:12px; }
.badge-admin { background:#7C3AED; color:#fff; }
.badge-faculty { background:#0EA5E9; color:#fff; }
.badge-student { background:#16A34A; color:#fff; }
.badge-assistant { background:#FFC107; color:#111827; }

.action-btns { display:flex; gap:8px; }
.btn-edit, .btn-delete, .btn-password { padding:8px 14px; border-radius:12px; text-decoration:none; font-weight:600; font-size:13px; transition:all 0.3s; border:none; cursor:pointer; }
.btn-edit { background:linear-gradient(135deg,#FF6B00,#FF3D00); color:white; }
.btn-delete { background:#E11D48; color:white; }
.btn-password { background:#0EA5E9; color:white; }

.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
.modal-content { background:#fff; margin:5% auto; padding:30px; border-radius:12px; width:90%; max-width:600px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.modal-header h3 { color:#FF6F00; margin:0; }
.close { font-size:28px; font-weight:bold; cursor:pointer; color:#000; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; margin-bottom:6px; font-weight:600; color:#333; }
.form-group input, .form-group select { width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; }
</style>

<div class="page-header">
    <h2><i class="bi bi-people"></i> User Management</h2>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msgClass ?> mb-4"><?= $message ?></div>
<?php endif; ?>

<div class="stat-cards">
    <div class="stat-card"><h3><?= $total_users ?></h3><p>Total Users</p></div>
    <div class="stat-card"><h3><?= $admins ?></h3><p>Admins</p></div>
    <div class="stat-card"><h3><?= $faculty ?></h3><p>Faculty</p></div>
    <div class="stat-card"><h3><?= $students ?></h3><p>Students</p></div>
    <div class="stat-card"><h3><?= $assistants ?></h3><p>Assistants</p></div>
</div>

<div class="users-card">
    <div class="header-actions">
        <h3><i class="bi bi-person-lines-fill"></i> System Users</h3>
        <button class="btn-add" onclick="showAddModal()"><i class="bi bi-plus-circle"></i> Add User</button>
    </div>

    <form method="GET" class="filter-form">
        <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
        <select name="filter_role">
            <option value="">All Roles</option>
            <option value="admin" <?= $filter_role=='admin'?'selected':'' ?>>Admin</option>
            <option value="faculty" <?= $filter_role=='faculty'?'selected':'' ?>>Faculty</option>
            <option value="student" <?= $filter_role=='student'?'selected':'' ?>>Student</option>
            <option value="assistant" <?= $filter_role=='assistant'?'selected':'' ?>>Assistant</option>
        </select>
        <button type="submit" class="btn-reset"><i class="bi bi-search"></i> Search</button>
        <a href="manage_users.php" class="btn-reset"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if(mysqli_num_rows($users)>0): ?>
                <?php while($u = mysqli_fetch_assoc($users)): ?>
                <tr>
                    <td><?= $u['user_id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['full_name'] ?: '-') ?></td>
                    <td>
                        <?= htmlspecialchars($u['role']=='student' ? ($u['email'] && str_contains($u['email'], '@wmsu.edu.ph') ? $u['email'] : 'student@wmsu.edu.ph') : ($u['email'] ?: '-')) ?>
                    </td>
                    <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-edit" onclick='showEditModal(<?= json_encode($u) ?>)'><i class="bi bi-pencil"></i> Edit</button>
                            <button class="btn-password" onclick="showPasswordModal(<?= $u['user_id'] ?>)"><i class="bi bi-key"></i> Reset</button>
                            <?php if($u['user_id']!=$_SESSION['user_id']): ?>
                                <a href="?delete=<?= $u['user_id'] ?>" class="btn-delete" onclick="return confirm('Delete this user?')"><i class="bi bi-trash"></i> Delete</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-person-plus"></i> Add New User</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name"></div>
            <div class="form-group"><label>Role</label>
                <select name="role" id="add_role" required onchange="autoAppendEmail('add')">
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                    <option value="assistant">Assistant</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn-add w-100"><i class="bi bi-check-circle"></i> Add User</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-pencil-square"></i> Edit User</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group"><label>Username</label><input type="text" name="username" id="edit_username" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" id="edit_full_name"></div>
            <div class="form-group"><label>Role</label>
                <select name="role" id="edit_role" required onchange="autoAppendEmail('edit')">
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                    <option value="assistant">Assistant</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="edit_user" class="btn-add w-100"><i class="bi bi-save"></i> Save Changes</button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-key"></i> Reset Password</h3>
            <span class="close" onclick="closeModal('passwordModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="user_id" id="password_user_id">
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
            <button type="submit" name="reset_password" class="btn-add w-100"><i class="bi bi-check-circle"></i> Reset Password</button>
        </form>
    </div>
</div>

<script>
function showAddModal() { document.getElementById('addModal').style.display='block'; }
function showEditModal(user) {
    document.getElementById('editModal').style.display='block';
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_role').value = user.role;
}
function showPasswordModal(id) {
    document.getElementById('passwordModal').style.display='block';
    document.getElementById('password_user_id').value = id;
}
function closeModal(modalId) { document.getElementById(modalId).style.display='none'; }

window.onclick = function(e) {
    ['addModal','editModal','passwordModal'].forEach(id=>{
        if(e.target==document.getElementById(id)) document.getElementById(id).style.display='none';
    });
}

function autoAppendEmail(type){
    let roleSelect = document.getElementById(type+'_role');
    let emailInput = type==='add'?document.querySelector('#addModal input[name="email"]'):document.querySelector('#editModal input[name="email"]');
    if(roleSelect.value==='student'){
        let val = emailInput.value.split('@')[0];
        emailInput.value = val+'@wmsu.edu.ph';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
