<?php
ob_start();
require_once 'includes/db.php';
require_once 'includes/header.php';

// Predefined list of categories
$categories = [
    "Beaker","Chemical","Circuit Boards","Cleaning Materials","Computer Parts","Cylinder",
    "Electrical Components","Electronics","Equipment","Flask","Glass Tubing","Glassware",
    "Measuring Device","Microscope","Miscellaneous","Optics","Power Supply",
    "Protective Gear","Reagent","Storage Equipment","Thermal Apparatus","Tools"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $condition = $_POST['condition'];
    $status = $_POST['status'];
    $item_condition = $_POST['item_condition']; // 'new' or 'old'

    $stmt = $conn->prepare("INSERT INTO apparatus (name, category, quantity, `condition`, status, item_condition)
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisss", $name, $category, $quantity, $condition, $status, $item_condition);
    $stmt->execute();

    add_log($conn, $_SESSION['user_id'], "Add Apparatus", "Added apparatus: $name (Condition: $item_condition)");
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

.condition-pill input[type="radio"]:checked + label {
    color: #FF6F00;
    font-weight: 700;
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
    <h2><i class="bi bi-plus-circle"></i> Add New Apparatus</h2>

    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-journal-text"></i> Name</label>
            <input type="text" name="name" class="form-control" placeholder="Enter apparatus name" required>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-tags"></i> Category</label>
            <select name="category" class="form-select" required>
                <option value="" disabled selected>Select category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-hash"></i> Quantity</label>
            <input type="number" name="quantity" min="1" class="form-control" placeholder="Enter quantity" required>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-tools"></i> Condition</label>
            <select name="condition" class="form-select" required>
                <option value="" disabled selected>Select condition</option>
                <option value="Good">Good</option>
                <option value="Damaged">Damaged</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label"><i class="bi bi-info-circle"></i> Status</label>
            <select name="status" class="form-select" required>
                <option value="" disabled selected>Select status</option>
                <option value="Available">Available</option>
                <option value="Borrowed">Borrowed</option>
            </select>
        </div>

       <div class="col-12">
    <label class="form-label"><i class="bi bi-box"></i> Item Condition</label>
    <div class="condition-pills">
        <div class="condition-pill" onclick="selectCondition('new')">
            <input type="radio" name="item_condition" value="new" id="condition_new" required>
            <label for="condition_new">
                <span class="icon"><i class="bi bi-plus-circle"></i></span>
                <span class="label">New Item</span>
            </label>
        </div>
        <div class="condition-pill" onclick="selectCondition('old')">
            <input type="radio" name="item_condition" value="old" id="condition_old" required>
            <label for="condition_old">
                <span class="icon"><i class="bi bi-box-seam"></i></span>
                <span class="label">Used/Old Item</span>
            </label>
        </div>
    </div>
</div>


        <div class="col-12 text-center mt-4">
            <button type="submit" class="btn btn-orange px-4 me-2">
                <i class="bi bi-save"></i> Add Apparatus
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