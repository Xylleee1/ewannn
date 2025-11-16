<?php
ob_start();
session_start();
require_once 'includes/db.php';

// --- ROLE CHECK --- //
if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

$role = $_SESSION['role'];

// --- DELETE apparatus (Admins only) --- //
if ($role === 'admin' && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Delete related borrow requests first to avoid FK constraint error
    $conn->query("DELETE FROM borrow_requests WHERE apparatus_id = $id");

    // Then delete the apparatus record
    $conn->query("DELETE FROM apparatus WHERE apparatus_id = $id");

    header("Location: manage_inventory.php");
    exit;
}

require_once 'includes/header.php';

// --- SEARCH & FILTER --- //
$search = $_GET['search'] ?? '';
$filter_category = $_GET['filter_category'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_item_condition = $_GET['filter_item_condition'] ?? '';

$query = "SELECT apparatus_id, name, category, quantity, description, status, `condition`, item_condition FROM apparatus WHERE 1=1";

if ($role === 'student') {
    $query .= " AND status = 'Available'";
}

if (!empty($search)) {
    $query .= " AND name LIKE '%" . $conn->real_escape_string($search) . "%'";
}
if (!empty($filter_category)) {
    $query .= " AND category = '" . $conn->real_escape_string($filter_category) . "'";
}
if (!empty($filter_status) && $role !== 'student') {
    $query .= " AND status = '" . $conn->real_escape_string($filter_status) . "'";
}
if (!empty($filter_item_condition)) {
    $query .= " AND item_condition = '" . $conn->real_escape_string($filter_item_condition) . "'";
}

$query .= " ORDER BY name ASC";

$result = $conn->query($query);
?>

<style>
    *{
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
/* ===== PAGE HEADER ===== */
.page-header {
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 3px solid #FF6F00;
}

.page-header h2 {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 26px;
    margin: 0;
    font-weight: 700;
}

/* ===== INVENTORY CARD ===== */
.inventory-card {
    background: #fff;
    padding: 28px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(255,111,0,0.08);
}

/* ===== HEADER ACTIONS ===== */
.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.header-actions h3 {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ===== BUTTONS ===== */
.btn-add {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    color: white;
    padding: 10px 20px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,111,0,0.35);
}

.btn-reset {
    background: #FF6F00;
    color: white;
    padding: 10px 16px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-reset:hover {
    background: #E65100;
}

/* ===== FILTER FORM ===== */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
    align-items: center;
}

.filter-form input,
.filter-form select {
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 12px;
    font-size: 14px;
    background: #fafafa;
}

.filter-form input { flex: 1; min-width: 200px; }
.filter-form select { min-width: 150px; }

/* ===== TABLE ===== */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: #fff;
}

.table th, .table td {
    padding: 14px 16px;
    text-align: left;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
}

.table tbody tr:hover {
    background: #f5f5f5;
}

/* ===== STATUS BADGES ===== */
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
    text-transform: capitalize;
}

.badge-success { background: #16A34A; color: #fff; }
.badge-warning { background: #FFC107; color: #111827; }
.badge-danger  { background: #E11D48; color: #fff; }
.badge-info    { background: #0EA5E9; color: #fff; }
.badge-violet  { background: #7C3AED; color: #fff; }

/* ===== ITEM CONDITION BADGES ===== */
.item-badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-weight: 600;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.item-badge-new {
    background: #D1FAE5;
    color: #065F46;
    border: 1px solid #10B981;
}

.item-badge-old {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #F59E0B;
}

/* ===== ACTION BUTTONS ===== */
.action-btns {
    display: flex;
    gap: 8px;
    flex-wrap: nowrap;
    justify-content: flex-start;
    align-items: center;
    white-space: nowrap;
}
.btn-edit,
.btn-delete {
    padding: 8px 14px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s;
}

.btn-edit {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    color: white;
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,111,0,0.35);
}

.btn-delete {
    background: #E11D48;
    color: white;
}

.btn-delete:hover {
    background: #C2184B;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}
</style>

<div class="page-header">
    <h2><?= $role === 'student' ? 'Available Apparatus & Reagents' : 'Manage Inventory' ?></h2>
</div>

<div class="inventory-card">
    <div class="header-actions">
        <h3>
            <i class="bi bi-box-seam"></i>
            <?= $role === 'student' ? 'Browse Inventory' : 'Apparatus & Reagents' ?>
        </h3>

        <?php if ($role === 'admin'): ?>
            <a href="manage_inventory_add.php" class="btn-add">
                <i class="bi bi-plus-circle"></i> 
                <span style="font-weight: 300;">Add new item</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter + Search -->
    <form id="filterForm" method="GET" class="filter-form">
        <input type="text" name="search" id="searchInput" placeholder="Search apparatus or reagent..." value="<?= htmlspecialchars($search); ?>">

        <select name="filter_category" id="filterCategory">
            <option value="">All Categories</option>
            <?php
            $categories = ["Beaker","Chemical","Circuit Boards","Cleaning Materials","Computer Parts","Cylinder","Electrical Components","Electronics","Equipment","Flask","Glass Tubing","Glassware","Measuring Device","Microscope","Miscellaneous","Optics","Power Supply","Protective Gear","Reagent","Storage Equipment","Thermal Apparatus","Tools"];
            foreach ($categories as $cat) {
                $selected = ($filter_category == $cat) ? 'selected' : '';
                echo "<option value='$cat' $selected>$cat</option>";
            }
            ?>
        </select>

        <?php if ($role !== 'student'): ?>
            <select name="filter_status" id="filterStatus">
                <option value="">All Status</option>
                <option value="Available" <?= ($filter_status == 'Available') ? 'selected' : ''; ?>>Available</option>
                <option value="Borrowed" <?= ($filter_status == 'Borrowed') ? 'selected' : ''; ?>>Borrowed</option>
            </select>
        <?php endif; ?>

        <select name="filter_item_condition" id="filterItemCondition">
            <option value="">All Items</option>
            <option value="new" <?= ($filter_item_condition == 'new') ? 'selected' : ''; ?>>New Items</option>
            <option value="old" <?= ($filter_item_condition == 'old') ? 'selected' : ''; ?>>Old Items</option>
        </select>

        <a href="manage_inventory.php" class="btn-reset"><i class="bi bi-arrow-clockwise"></i> <span style="font-weight: 300;">Reset</span></a>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Item Type</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <?php if ($role === 'admin'): ?>
                        <th width="180">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['apparatus_id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= htmlspecialchars($row['quantity']) ?></td>
                            <td>
                                <?php 
                                $item_condition = $row['item_condition'] ?? 'new';
                                $badge_class = $item_condition === 'new' ? 'item-badge-new' : 'item-badge-old';
                                $icon = $item_condition === 'new' ? '' : '';
                                ?>
                                <span class="item-badge <?= $badge_class ?>">
                                    <span><?= $icon ?></span>
                                    <span><?= ucfirst($item_condition) ?></span>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= strtolower($row['condition'] ?? 'good') === 'good' ? 'badge-success' : 'badge-warning'; ?>">
                                    <?= htmlspecialchars(ucfirst($row['condition'] ?? 'Good')); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = match(strtolower($row['status'])) {
                                    'pending' => 'badge-warning',
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'released' => 'badge-info',
                                    'returned' => 'badge-violet',
                                    default => 'badge-success',
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($row['status'])); ?></span>
                            </td>
                            <?php if ($role === 'admin'): ?>
                                <td>
                                    <div class="action-btns">
                                        <a href="manage_inventory_edit.php?id=<?= $row['apparatus_id'] ?>" class="btn-edit">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="manage_inventory.php?delete=<?= $row['apparatus_id'] ?>" onclick="return confirm('Are you sure you want to delete this item?');" class="btn-delete">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $role === 'admin' ? '8' : '7' ?>" class="empty-state">
                            No apparatus or reagents found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('#searchInput, #filterCategory, #filterStatus, #filterItemCondition').forEach(input => {
    input.addEventListener('input', () => {
        clearTimeout(window.typingTimer);
        window.typingTimer = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 400);
    });
});
</script>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>