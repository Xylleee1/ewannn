<?php
require_once 'includes/db.php';

$id = intval($_GET['id'] ?? 0);
$result = $conn->query("SELECT * FROM apparatus WHERE apparatus_id = $id");

if (!$result || $result->num_rows === 0) {
    echo "<div class='container mt-5 text-center text-danger'>
            <h4>⚠️ Apparatus not found.</h4>
          </div>";
    require_once 'includes/footer.php';
    exit;
}

$row = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $condition = $_POST['condition'];
    $status = $_POST['status'];
    $item_condition = $_POST['item_condition'];

    $stmt = $conn->prepare("UPDATE apparatus 
                            SET name = ?, category = ?, quantity = ?, `condition` = ?, status = ?, item_condition = ? 
                            WHERE apparatus_id = ?");
    $stmt->bind_param("ssisssi", $name, $category, $quantity, $condition, $status, $item_condition, $id);
    $stmt->execute();

    add_log($conn, $_SESSION['user_id'], "Edit Apparatus", "Updated apparatus ID: $id");
    header("Location: manage_inventory.php");
    exit;
}
?>

<style>
.form-container {
    max-width: 750px;
    margin: 60px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    padding: 40px 45px;
    border-top: 6px solid #FF6F00;
}

.form-container h2 {
    text-align: center;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
    margin-bottom: 30px;
}

label.form-label {
    font-weight: 600;
    color: #333;
}

.form-control, .form-select {
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    transition: border-color 0.3s;
}

.form-control:focus, .form-select:focus {
    border-color: #FF6F00;
    box-shadow: none;
}

.btn {
    padding: 10px 20px;
    font-weight: 600;
    border-radius: 6px;
    transition: all 0.3s;
}

.btn-orange {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    border: none;
    color: #fff;
}

.btn-orange:hover {
    background: linear-gradient(135deg, #E65100, #FFB74D);
    transform: translateY(-2px);
}

.btn-outline-secondary {
    border: 1px solid #FF6F00;
    color: #FF6F00;
    transition: all 0.3s;
}

.btn-outline-secondary:hover {
    background: #FF6F00;
    color: #fff;
    transform: translateY(-2px);
}

.bi {
    margin-right: 6px;
}

.condition-pills {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.condition-pill {
    flex: 1;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.condition-pill input[type="radio"] {
    display: none;
}

.condition-pill:hover {
    border-color: #FF6F00;
    background: #fff8e1;
}

.condition-pill.selected {
    border-color: #FF6F00;
    background: #fff8e1;
}

.condition-pill .icon {
    font-size: 32px;
    display: block;
    margin-bottom: 8px;
}

.condition-pill .label {
    font-weight: 600;
    font-size: 14px;
}
</style>

<div class="form-container">
    <h2><i class="bi bi-pencil-square"></i> Edit Apparatus</h2>

    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-journal-text"></i> Name</label>
            <input type="text" name="name" class="form-control" 
                   value="<?= htmlspecialchars($row['name']); ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-tags"></i> Category</label>
            <input type="text" name="category" class="form-control" 
                   value="<?= htmlspecialchars($row['category']); ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-hash"></i> Quantity</label>
            <input type="number" name="quantity" class="form-control" min="1"
                   value="<?= htmlspecialchars($row['quantity']); ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-tools"></i> Condition</label>
            <select name="condition" class="form-select" required>
                <option value="Good" <?= ($row['condition'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                <option value="Damaged" <?= ($row['condition'] === 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-info-circle"></i> Status</label>
            <select name="status" class="form-select" required>
                <option value="Available" <?= ($row['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                <option value="Borrowed" <?= ($row['status'] === 'Borrowed') ? 'selected' : ''; ?>>Borrowed</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label"><i class="bi bi-box"></i> Item Condition</label>
            <div class="condition-pills">
                <div class="condition-pill <?= ($row['item_condition'] === 'new') ? 'selected' : ''; ?>" onclick="selectCondition('new')">
                    <input type="radio" name="item_condition" value="new" id="condition_new" 
                           <?= ($row['item_condition'] === 'new') ? 'checked' : ''; ?> required>
                    <label for="condition_new">
                        <span class="icon">🆕</span>
                        <span class="label">New Item</span>
                    </label>
                </div>
                <div class="condition-pill <?= ($row['item_condition'] === 'old') ? 'selected' : ''; ?>" onclick="selectCondition('old')">
                    <input type="radio" name="item_condition" value="old" id="condition_old" 
                           <?= ($row['item_condition'] === 'old') ? 'checked' : ''; ?> required>
                    <label for="condition_old">
                        <span class="icon">📦</span>
                        <span class="label">Used/Old Item</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="col-12 text-center mt-4">
            <button type="submit" class="btn btn-orange px-4 me-2">
                <i class="bi bi-save"></i> Save Changes
            </button>
            <a href="manage_inventory.php" class="btn btn-outline-secondary px-4">
                <i class="bi bi-x-circle"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
function selectCondition(type) {
    document.querySelectorAll('.condition-pill').forEach(pill => {
        pill.classList.remove('selected');
    });
    
    const radio = document.getElementById('condition_' + type);
    radio.checked = true;
    radio.parentElement.parentElement.classList.add('selected');
}
</script>

<?php require_once 'includes/footer.php'; ?>