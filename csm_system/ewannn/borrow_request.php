<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo "<p class='text-center mt-5'>Access denied.</p>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$msg = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request'])) {
    $student_id = $_SESSION['user_id'];
    $apparatus_items = $_POST['apparatus_items'];
    $date_requested = $_POST['date_requested'];
    $date_needed = $_POST['date_needed'];
    $time_from = $_POST['time_from'];
    $time_to = $_POST['time_to'];
    $subject = trim($_POST['subject']);
    $schedule = trim($_POST['schedule']);
    $room = trim($_POST['room']);
    $purpose = trim($_POST['purpose']);
    $faculty_id = intval($_POST['faculty_id']);

    $success_count = 0;
    $error_count = 0;
    $errors = [];

    foreach ($apparatus_items as $item) {
        $apparatus_id = intval($item['apparatus_id']);
        $quantity = intval($item['quantity']);
        $concentration = trim($item['concentration']);

        // Check availability
        $check = mysqli_query($conn, "SELECT quantity, name FROM apparatus WHERE apparatus_id = $apparatus_id");
        $row = mysqli_fetch_assoc($check);

        if (!$row) {
            $errors[] = "Apparatus ID $apparatus_id not found.";
            $error_count++;
            continue;
        }

        if ($quantity > $row['quantity']) {
            $errors[] = "{$row['name']}: Requested $quantity exceeds available stock ({$row['quantity']}).";
            $error_count++;
            continue;
        }

        // Insert request
        $stmt = mysqli_prepare($conn, "INSERT INTO borrow_requests 
            (student_id, faculty_id, apparatus_id, quantity, concentration, date_requested, date_needed, time_from, time_to, subject, schedule, room, purpose) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiissssssssss', $student_id, $faculty_id, $apparatus_id, $quantity, $concentration, $date_requested, $date_needed, $time_from, $time_to, $subject, $schedule, $room, $purpose);

        if (mysqli_stmt_execute($stmt)) {
            $success_count++;
        } else {
            $error_count++;
            $errors[] = "Failed to request {$row['name']}.";
        }
    }
    // After line where you insert the borrow request
if (mysqli_stmt_execute($stmt)) {
    $request_id = mysqli_insert_id($conn);
    $success_count++;
    
    // Send email notification
    notify_request_submitted($conn, $request_id);
}

    if ($success_count > 0) {
        $msg = "Successfully submitted $success_count request(s)!";
        $msgClass = "success";
        add_log($conn, $student_id, "Borrow Request", "Requested $success_count apparatus item(s)");

        // Send notification to faculty
        $faculty_name = '';
        mysqli_data_seek($faculties, 0);
        while ($f = mysqli_fetch_assoc($faculties)) {
            if ($f['user_id'] == $faculty_id) {
                $faculty_name = $f['full_name'];
                break;
            }
        }

        if ($faculty_name) {
            $notification_title = "New Borrow Request";
            $notification_message = "You have a new borrow request from " . $_SESSION['full_name'] . " for $success_count item(s).";
            add_notification($faculty_id, $notification_title, $notification_message, 'borrow_request', null);
        }
    }

    if ($error_count > 0) {
        $msg .= " " . $error_count . " request(s) failed: " . implode("; ", $errors);
        $msgClass = "warning";
    }
}

$apparatus = mysqli_query($conn, "SELECT apparatus_id, name, quantity, category FROM apparatus WHERE quantity > 0 ORDER BY name");
$faculties = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE role = 'faculty' ORDER BY full_name");
?>

<style>
* {
    box-sizing: border-box;
}

.container-form {
    background: #F5F5F5;
    max-width: 1100px;
    margin: 40px auto;
    padding: 40px 45px;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}

.container-form h2 {
    text-align: center;
    color: #FF6B00;
    margin-bottom: 10px;
    font-size: 32px;
    font-weight: bold;
}

.container-form .subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 40px;
    font-size: 16px;
}

.alert {
    padding: 18px 22px;
    border-radius: 12px;
    margin-bottom: 30px;
    font-size: 15px;
    font-weight: 500;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert.success {
    background: #16A34A;
    color: white;
    border-left: 5px solid #FF6B00;
}

.alert.warning {
    background: #FFC107;
    color: #111827;
    border-left: 5px solid #FF6B00;
}

.alert.error {
    background: #E11D48;
    color: white;
    border-left: 5px solid #FF6B00;
}

form label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #111827;
    font-size: 14px;
}

form label i {
    margin-right: 6px;
    color: #FF6B00;
}

form select,
form input[type="text"],
form input[type="number"],
form input[type="date"],
form input[type="time"],
form textarea {
    width: 100%;
    padding: 14px 18px;
    margin-bottom: 24px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 14px;
    background-color: #fff;
    transition: all 0.3s ease;
    font-family: inherit;
}

form select:focus,
form input:focus,
form textarea:focus {
    outline: none;
    border-color: #FF6B00;
    background-color: #fff;
    box-shadow: 0 0 0 4px rgba(255,107,0,0.1);
}

form select:hover,
form input:hover,
form textarea:hover {
    border-color: #FFB380;
}

form textarea {
    min-height: 120px;
    resize: vertical;
}

form button {
    display: block;
    width: 100%;
    background: linear-gradient(135deg, #FF6B00 0%, #FF3D00 100%);
    color: white;
    border: none;
    padding: 16px;
    font-size: 17px;
    font-weight: 700;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

form button:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255,107,0,0.4);
}

form button:active {
    transform: translateY(-1px);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 0;
}

/* Multi-apparatus section */
.apparatus-list {
    border: 2px dashed #d0d0d0;
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 24px;
    background: #fafafa;
    min-height: 120px;
}

.apparatus-item {
    display: grid;
    grid-template-columns: 2.5fr 1.5fr 1fr auto;
    gap: 15px;
    align-items: end;
    margin-bottom: 18px;
    padding: 18px;
    background: #fff;
    border-radius: 10px;
    border: 2px solid #e5e5e5;
    transition: all 0.3s ease;
    animation: fadeIn 0.3s ease-out;
}

.concentration-wrapper {
    display: flex;
    flex-direction: column;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.apparatus-item:hover {
    border-color: #FFB380;
    box-shadow: 0 4px 15px rgba(255,107,0,0.1);
}

.apparatus-item:last-child {
    margin-bottom: 0;
}

.item-number {
    position: absolute;
    top: -10px;
    left: 10px;
    background: #FF6B00;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.btn-remove {
    background: #E11D48;
    color: white;
    border: none;
    padding: 12px 18px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-remove:hover {
    background: #BE123C;
    transform: scale(1.05);
}

.btn-add-item {
    background: #16A34A;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    margin-top: 12px;
    transition: all 0.3s ease;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-add-item:hover {
    background: #15803D;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(22,163,74,0.3);
}

.btn-add-item i {
    font-size: 16px;
}

/* Summary Section */
.request-summary {
    background: linear-gradient(135deg, #FFF5EB 0%, #FFE8D1 100%);
    border: 2px solid #FFB380;
    border-radius: 14px;
    padding: 20px 25px;
    margin-bottom: 25px;
    display: none;
}

.request-summary.show {
    display: block;
    animation: slideDown 0.3s ease-out;
}

.request-summary h3 {
    color: #FF6B00;
    font-size: 18px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #FFD8B8;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 600;
    color: #333;
}

.summary-value {
    color: #666;
}

/* Validation feedback */
.invalid-feedback {
    color: #E11D48;
    font-size: 13px;
    margin-top: -18px;
    margin-bottom: 15px;
    display: none;
}

.invalid-feedback.show {
    display: block;
}

input.error,
select.error,
textarea.error {
    border-color: #E11D48;
}

/* Helper text */
.helper-text {
    font-size: 12px;
    color: #666;
    margin-top: -18px;
    margin-bottom: 20px;
    font-style: italic;
}

/* Loading spinner */
.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #FF6B00;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    display: inline-block;
    margin-left: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .apparatus-item {
        grid-template-columns: 1fr;
    }
    
    .container-form {
        padding: 25px 20px;
        margin: 20px 15px;
    }
}

/* Tooltip */
.tooltip-icon {
    display: inline-block;
    width: 18px;
    height: 18px;
    background: #FF6B00;
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 18px;
    font-size: 12px;
    cursor: help;
    margin-left: 5px;
}

.tooltip-icon:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    margin-left: -50px;
    margin-top: 25px;
}

/* Item counter */
.items-counter {
    background: #FF6B00;
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 15px;
}
</style>

<div class="container-form mb-5">
    <h2><i class="bi bi-beaker"></i> CHEMICAL REQUISITION FORM</h2>
    <p class="subtitle">College of Science and Mathematics</p>

    <?php if ($msg): ?>
        <div class="alert <?= $msgClass ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" id="borrowForm" novalidate>
        <div class="form-row">
            <div class="form-group">
                <label><i class="bi bi-calendar-check"></i> Date Requested</label>
                <input type="date" name="date_requested" value="<?= date('Y-m-d'); ?>" required readonly>
                <div class="invalid-feedback">Please select a date</div>
            </div>

            <div class="form-group">
                <label><i class="bi bi-person-badge"></i> Instructor for Approval</label>
                <select name="faculty_id" required>
                    <option value="">-- Select Instructor --</option>
                    <?php 
                    mysqli_data_seek($faculties, 0);
                    while ($f = mysqli_fetch_assoc($faculties)): 
                    ?>
                        <option value="<?= $f['user_id'] ?>"><?= htmlspecialchars($f['full_name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="invalid-feedback">Please select an instructor</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="bi bi-calendar-event"></i> Date Needed</label>
                <input type="date" name="date_needed" min="<?= date('Y-m-d', strtotime('+1 day')); ?>" required>
                <div class="invalid-feedback">Please select a valid date (at least 1 day in advance)</div>
            </div>

            <div class="form-group">
                <label><i class="bi bi-clock"></i> Time From</label>
                <input type="time" name="time_from" required>
                <div class="invalid-feedback">Please select a start time</div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="bi bi-clock-history"></i> Time To</label>
                <input type="time" name="time_to" required>
                <div class="invalid-feedback">End time must be after start time</div>
            </div>

            <div class="form-group">
                <label><i class="bi bi-journal-text"></i> Subject</label>
                <input type="text" name="subject" placeholder="e.g., Chemistry 101" required maxlength="100">
                <div class="invalid-feedback">Please enter the subject</div>
            </div>
        </div>

        <label><i class="bi bi-calendar3"></i> Schedule (Auto-filled)</label>
        <input type="text" name="schedule" id="schedule" readonly required style="background-color:#f3f3f3;">
        <p class="helper-text">This field is automatically generated based on date and time selections</p>

        <label><i class="bi bi-building"></i> Room / Laboratory</label>
        <select name="room" required>
            <option value="">-- Select Room --</option>
            <optgroup label="Lab Rooms">
                <option value="CSM 101">CSM 101</option>
                <option value="CSM 103">CSM 103</option>
                <option value="MS 301">MS 301</option>
                <option value="CSM 104">CSM 104</option>
                <option value="MS 101">MS 101</option>
            </optgroup>
            <optgroup label="Lecture Rooms">
                <option value="CSM 206">CSM 206</option>
                <option value="CSM 202">CSM 202</option>
                <option value="MS 302">MS 302</option>
                <option value="CSM 11">CSM 11</option>
            </optgroup>
        </select>
        <div class="invalid-feedback">Please select a room</div>

        <label>
            <i class="bi bi-box-seam"></i> Apparatus/Reagents to Borrow
            <span class="tooltip-icon" data-tooltip="Select items, specify concentration/size, and quantity">?</span>
        </label>
        <div class="items-counter">
            <i class="bi bi-list-check"></i> Items Selected: <span id="itemCount">1</span>
        </div>
        <div class="apparatus-list" id="apparatusList">
            <div class="apparatus-item" data-index="0">
                <div>
                    <label style="margin-bottom: 6px;">Select Item *</label>
                    <select name="apparatus_items[0][apparatus_id]" class="apparatus-select" required>
                        <option value="">-- Select Item --</option>
                        <?php
                        mysqli_data_seek($apparatus, 0);
                        while ($a = mysqli_fetch_assoc($apparatus)):
                        ?>
                            <option value="<?= $a['apparatus_id'] ?>" 
                                    data-quantity="<?= $a['quantity'] ?>" 
                                    data-category="<?= htmlspecialchars($a['category']) ?>">
                                <?= htmlspecialchars($a['name']) ?> (Available: <?= $a['quantity'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="concentration-wrapper">
                    <label style="margin-bottom: 6px; display: none;" class="concentration-label"></label>
                    <select name="apparatus_items[0][concentration]" class="concentration-select" style="display:none;">
                        <option value="">-- Select --</option>
                    </select>
                </div>
                <div>
                    <label style="margin-bottom: 6px;">Quantity *</label>
                    <input type="number" name="apparatus_items[0][quantity]" min="1" placeholder="Qty" class="quantity-input" required>
                </div>
                <button type="button" class="btn-remove" onclick="removeItem(this)" style="display: none;">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
        <button type="button" class="btn-add-item" onclick="addApparatusItem()">
            <i class="bi bi-plus-circle"></i> Add Another Item
        </button>

        <label><i class="bi bi-pencil-square"></i> Purpose / Activity</label>
        <textarea name="purpose" required placeholder="Describe the purpose or activity for this request (minimum 20 characters)" minlength="20" maxlength="500"></textarea>
        <p class="helper-text">Character count: <span id="charCount">0</span>/500</p>
        <div class="invalid-feedback">Please provide a detailed purpose (minimum 20 characters)</div>

        <!-- Request Summary -->
        <div class="request-summary" id="requestSummary">
            <h3><i class="bi bi-clipboard-check"></i> Request Summary</h3>
            <div id="summaryContent"></div>
        </div>

        <button name="request" type="submit" id="submitBtn">
            <i class="bi bi-check-circle"></i> Submit Request
        </button>
    </form>
</div>

<script>
// Define options arrays by category
const concentrationOptions = ['0.1M', '0.5M', '1M', '2M', '5M', '10M', 'Saturated', 'Dilute', 'Concentrated', 'Stock Solution'];
const sizeOptions = ['10 mL', '25 mL', '50 mL', '100 mL', '250 mL', '500 mL', '1 L', '2 L', '5 L'];
const flaskSizes = ['50 mL', '100 mL', '250 mL', '500 mL', '1 L', '2 L'];
const beakerSizes = ['50 mL', '100 mL', '250 mL', '400 mL', '500 mL', '600 mL', '1 L', '2 L'];
const testTubeSizes = ['10 mm × 75 mm', '13 mm × 100 mm', '16 mm × 125 mm', '18 mm × 150 mm', '20 mm × 200 mm'];
const microscopeMagnification = ['4×', '10×', '40×', '100×', '400×', '1000×'];
const thermometerRange = ['-10°C to 110°C', '0°C to 100°C', '0°C to 200°C', '0°C to 360°C', '-20°C to 150°C'];
const balanceCapacity = ['100g', '200g', '500g', '1kg', '2kg', '5kg'];
const cylinderSizes = ['10 mL', '25 mL', '50 mL', '100 mL', '250 mL', '500 mL', '1000 mL'];
const tubingDiameter = ['3 mm', '5 mm', '6 mm', '8 mm', '10 mm', '12 mm'];
const goggleTypes = ['Standard', 'Anti-Fog', 'UV Protection', 'Chemical Splash', 'Impact Resistant'];
const gloveTypes = ['Nitrile', 'Latex', 'Neoprene', 'Butyl', 'PVC', 'Disposable'];
const gloveSizes = ['Small', 'Medium', 'Large', 'X-Large'];
const burnerTypes = ['Single Flame', 'Double Flame', 'Micro Burner'];
const stirrerSpeed = ['Low (100-300 RPM)', 'Medium (300-800 RPM)', 'High (800-1500 RPM)', 'Variable'];
const hotPlateTemp = ['Up to 100°C', 'Up to 200°C', 'Up to 300°C', 'Up to 400°C'];

let itemCount = 1;

// Add apparatus item
function addApparatusItem() {
    const list = document.getElementById('apparatusList');
    const newItem = document.createElement('div');
    newItem.className = 'apparatus-item';
    newItem.setAttribute('data-index', itemCount);
    newItem.innerHTML = `
        <div>
            <label style="margin-bottom: 6px;">Select Item *</label>
            <select name="apparatus_items[${itemCount}][apparatus_id]" class="apparatus-select" required>
                <option value="">-- Select Item --</option>
                <?php 
                mysqli_data_seek($apparatus, 0);
                while ($a = mysqli_fetch_assoc($apparatus)): 
                ?>
                    <option value="<?= $a['apparatus_id'] ?>" 
                            data-quantity="<?= $a['quantity'] ?>" 
                            data-category="<?= htmlspecialchars($a['category']) ?>">
                        <?= htmlspecialchars($a['name']) ?> (Available: <?= $a['quantity'] ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="concentration-wrapper">
            <label style="margin-bottom: 6px; display: none;" class="concentration-label"></label>
            <select name="apparatus_items[${itemCount}][concentration]" class="concentration-select" style="display:none;">
                <option value="">-- Select --</option>
            </select>
        </div>
        <div>
            <label style="margin-bottom: 6px;">Quantity *</label>
            <input type="number" name="apparatus_items[${itemCount}][quantity]" min="1" placeholder="Qty" class="quantity-input" required>
        </div>
        <button type="button" class="btn-remove" onclick="removeItem(this)">
            <i class="bi bi-trash"></i> Remove
        </button>
    `;
    list.appendChild(newItem);
    itemCount++;
    updateRemoveButtons();
    updateItemCount();
}

// Remove item
function removeItem(btn) {
    btn.closest('.apparatus-item').remove();
    updateRemoveButtons();
    updateItemCount();
    updateSummary();
}

// Update remove buttons visibility
function updateRemoveButtons() {
    const items = document.querySelectorAll('.apparatus-item');
    items.forEach((item, index) => {
        const removeBtn = item.querySelector('.btn-remove');
        if (items.length === 1) {
            removeBtn.style.display = 'none';
        } else {
            removeBtn.style.display = 'block';
        }
    });
}

// Update item counter
function updateItemCount() {
    const count = document.querySelectorAll('.apparatus-item').length;
    document.getElementById('itemCount').textContent = count;
}

// Auto-fill schedule
function updateSchedule() {
    const dateNeeded = document.querySelector('input[name="date_needed"]').value;
    const timeFrom = document.querySelector('input[name="time_from"]').value;
    const timeTo = document.querySelector('input[name="time_to"]').value;
    const scheduleField = document.getElementById('schedule');

    if (dateNeeded && timeFrom && timeTo) {
        const dateObj = new Date(dateNeeded);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long', 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });

        const formatTime = (t) => {
            let [h, m] = t.split(':');
            h = parseInt(h);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ampm}`;
        };

        const scheduleText = `${formattedDate} from ${formatTime(timeFrom)} to ${formatTime(timeTo)}`;
        scheduleField.value = scheduleText;
    } else {
        scheduleField.value = '';
    }
    updateSummary();
}

// Update concentration/size dropdown based on category
document.addEventListener('change', function(e){
    if(e.target.classList.contains('apparatus-select')){
        const select = e.target;
        const selectedOption = select.options[select.selectedIndex];
        const category = selectedOption.getAttribute('data-category');
        const itemName = selectedOption.text.toLowerCase();
        const concentrationWrapper = select.closest('.apparatus-item').querySelector('.concentration-wrapper');
        const concentrationSelect = concentrationWrapper.querySelector('.concentration-select');
        const label = concentrationWrapper.querySelector('.concentration-label');

        // Clear existing options
        concentrationSelect.innerHTML = '<option value="">-- Select --</option>';
        
        let optionsToUse = [];
        let labelText = '';
        let needsSelection = false;

        // Determine which options to show based on category and item name
        if(category === 'Reagent' || category === 'Chemical'){
            labelText = 'Concentration *';
            optionsToUse = concentrationOptions;
            needsSelection = true;
        } else if(category === 'Flask'){
            labelText = 'Flask Size *';
            optionsToUse = flaskSizes;
            needsSelection = true;
        } else if(category === 'Beaker'){
            labelText = 'Beaker Size *';
            optionsToUse = beakerSizes;
            needsSelection = true;
        } else if(category === 'Glassware' && itemName.includes('test tube') && !itemName.includes('rack')){
            labelText = 'Test Tube Size *';
            optionsToUse = testTubeSizes;
            needsSelection = true;
        } else if(category === 'Glassware' && itemName.includes('rack')){
            labelText = 'Capacity *';
            optionsToUse = ['6 tubes', '12 tubes', '24 tubes', '40 tubes', '50 tubes'];
            needsSelection = true;
        } else if(category === 'Glass Tubing'){
            labelText = 'Diameter *';
            optionsToUse = tubingDiameter;
            needsSelection = true;
        } else if(category === 'Microscope'){
            labelText = 'Magnification *';
            optionsToUse = microscopeMagnification;
            needsSelection = true;
        } else if(category === 'Measuring Device' && itemName.includes('thermometer')){
            labelText = 'Temperature Range *';
            optionsToUse = thermometerRange;
            needsSelection = true;
        } else if(category === 'Measuring Device' && itemName.includes('balance')){
            labelText = 'Capacity *';
            optionsToUse = balanceCapacity;
            needsSelection = true;
        } else if(category === 'Measuring Device' && itemName.includes('cylinder')){
            labelText = 'Cylinder Size *';
            optionsToUse = cylinderSizes;
            needsSelection = true;
        } else if(category === 'Thermal Apparatus' && itemName.includes('burner')){
            labelText = 'Burner Type *';
            optionsToUse = burnerTypes;
            needsSelection = true;
        } else if(category === 'Thermal Apparatus' && itemName.includes('lamp')){
            labelText = 'Fuel Capacity *';
            optionsToUse = ['50 mL', '100 mL', '150 mL', '250 mL'];
            needsSelection = true;
        } else if(category === 'Equipment' && itemName.includes('stirrer')){
            labelText = 'Speed Range *';
            optionsToUse = stirrerSpeed;
            needsSelection = true;
        } else if(category === 'Equipment' && itemName.includes('hot plate')){
            labelText = 'Temperature Range *';
            optionsToUse = hotPlateTemp;
            needsSelection = true;
        } else if(category === 'Protective Gear' && itemName.includes('goggle')){
            labelText = 'Goggle Type *';
            optionsToUse = goggleTypes;
            needsSelection = true;
        } else if(category === 'Protective Gear' && itemName.includes('glove')){
            labelText = 'Glove Type *';
            optionsToUse = gloveTypes;
            needsSelection = true;
        } else if(category){
            // For items that don't need size/concentration, hide the field
            label.textContent = '';
            label.style.display = 'none';
            concentrationSelect.style.display = 'none';
            concentrationSelect.required = false;
            concentrationSelect.value = 'N/A'; // Set default value
            updateSummary();
            return;
        } else {
            // Hide if no category selected
            label.textContent = '';
            label.style.display = 'none';
            concentrationSelect.style.display = 'none';
            concentrationSelect.required = false;
            concentrationSelect.value = '';
            updateSummary();
            return;
        }

        if(needsSelection) {
            // Populate options
            label.textContent = labelText;
            label.style.display = 'block';
            optionsToUse.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option;
                opt.textContent = option;
                concentrationSelect.appendChild(opt);
            });
            concentrationSelect.style.display = 'block';
            concentrationSelect.required = true;
            concentrationSelect.classList.remove('error');
        }
        
        updateSummary();
    }
});

// Quantity validation
document.addEventListener('input', function(e) {
    if(e.target.classList.contains('quantity-input')) {
        const item = e.target.closest('.apparatus-item');
        const apparatusSelect = item.querySelector('.apparatus-select');
        const selectedOption = apparatusSelect.options[apparatusSelect.selectedIndex];
        const maxQuantity = parseInt(selectedOption.getAttribute('data-quantity') || 0);
        const quantity = parseInt(e.target.value);
        
        if(quantity > maxQuantity) {
            e.target.setCustomValidity(`Maximum available quantity is ${maxQuantity}`);
            e.target.classList.add('error');
        } else {
            e.target.setCustomValidity('');
            e.target.classList.remove('error');
        }
        updateSummary();
    }
});

// Character counter for purpose
const purposeTextarea = document.querySelector('textarea[name="purpose"]');
const charCount = document.getElementById('charCount');
purposeTextarea.addEventListener('input', function() {
    charCount.textContent = this.value.length;
    updateSummary();
});

// Time validation
document.querySelector('input[name="time_to"]').addEventListener('change', function() {
    const timeFrom = document.querySelector('input[name="time_from"]').value;
    const timeTo = this.value;
    
    if(timeFrom && timeTo && timeTo <= timeFrom) {
        this.setCustomValidity('End time must be after start time');
        this.classList.add('error');
    } else {
        this.setCustomValidity('');
        this.classList.remove('error');
    }
});

// Update request summary
function updateSummary() {
    const form = document.getElementById('borrowForm');
    const summary = document.getElementById('requestSummary');
    const summaryContent = document.getElementById('summaryContent');
    
    let html = '';
    let hasData = false;
    
    // Get selected items
    const items = document.querySelectorAll('.apparatus-item');
    let selectedItems = [];
    items.forEach(item => {
        const select = item.querySelector('.apparatus-select');
        const concentrationSelect = item.querySelector('.concentration-select');
        const quantityInput = item.querySelector('.quantity-input');
        
        if(select.value && quantityInput.value) {
            const itemName = select.options[select.selectedIndex].text.split(' (Available')[0];
            let concentration = concentrationSelect.value || 'N/A';
            
            const quantity = quantityInput.value;
            selectedItems.push({name: itemName, concentration: concentration, quantity: quantity});
            hasData = true;
        }
    });
    
    if(selectedItems.length > 0) {
        html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-box-seam"></i> Items Requested:</span><span class="summary-value"><strong>' + selectedItems.length + '</strong></span></div>';
        selectedItems.forEach((item, index) => {
            html += `<div class="summary-item" style="padding-left: 20px; font-size: 13px;">
                <span class="summary-label">${index + 1}. ${item.name}</span>
                <span class="summary-value">${item.concentration} × ${item.quantity}</span>
            </div>`;
        });
    }
    
    const faculty = document.querySelector('select[name="faculty_id"]');
    if(faculty.value) {
        html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-person-badge"></i> Instructor:</span><span class="summary-value">' + faculty.options[faculty.selectedIndex].text + '</span></div>';
        hasData = true;
    }
    
    const schedule = document.getElementById('schedule').value;
    if(schedule) {
        html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-calendar3"></i> Schedule:</span><span class="summary-value">' + schedule + '</span></div>';
        hasData = true;
    }
    
    const room = document.querySelector('select[name="room"]').value;
    if(room) {
        html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-building"></i> Room:</span><span class="summary-value">' + room + '</span></div>';
        hasData = true;
    }
    
    const subject = document.querySelector('input[name="subject"]').value;
    if(subject) {
        html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-journal-text"></i> Subject:</span><span class="summary-value">' + subject + '</span></div>';
        hasData = true;
    }
    
    const purpose = document.querySelector('textarea[name="purpose"]').value;
    if(purpose && purpose.length >= 20) {
        const truncatedPurpose = purpose.length > 80 ? purpose.substring(0, 80) + '...' : purpose;
        html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-pencil-square"></i> Purpose:</span><span class="summary-value" style="font-size: 13px;">' + truncatedPurpose + '</span></div>';
        hasData = true;
    }
    
    summaryContent.innerHTML = html;
    if(hasData) {
        summary.classList.add('show');
    } else {
        summary.classList.remove('show');
    }
}

// Form validation on submit
document.getElementById('borrowForm').addEventListener('submit', function(e) {
    const form = this;
    let isValid = true;
    
    // Check all required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if(!field.value || field.value === '') {
            field.classList.add('error');
            const feedback = field.nextElementSibling;
            if(feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.classList.add('show');
            }
            isValid = false;
        } else {
            field.classList.remove('error');
            const feedback = field.nextElementSibling;
            if(feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.classList.remove('show');
            }
        }
    });
    
    // Validate that at least one apparatus is fully selected
    const items = document.querySelectorAll('.apparatus-item');
    let hasValidItem = false;
    items.forEach(item => {
        const apparatusSelect = item.querySelector('.apparatus-select');
        const concentrationSelect = item.querySelector('.concentration-select');
        const quantityInput = item.querySelector('.quantity-input');
        
        if(apparatusSelect.value && quantityInput.value) {
            // Check if concentration/size is required and filled
            if(concentrationSelect.style.display !== 'none' && concentrationSelect.offsetParent !== null) {
                if(!concentrationSelect.value) {
                    concentrationSelect.classList.add('error');
                    isValid = false;
                } else {
                    hasValidItem = true;
                }
            } else {
                hasValidItem = true;
            }
        }
    });
    
    if(!hasValidItem) {
        alert('Please complete all fields for at least one apparatus item (Item, Size/Concentration, and Quantity).');
        isValid = false;
    }
    
    // Validate time range
    const timeFrom = document.querySelector('input[name="time_from"]').value;
    const timeTo = document.querySelector('input[name="time_to"]').value;
    if(timeFrom && timeTo && timeTo <= timeFrom) {
        alert('End time must be after start time. Please check your time range.');
        document.querySelector('input[name="time_to"]').classList.add('error');
        isValid = false;
    }
    
    // Validate date is at least 1 day in advance
    const dateNeeded = new Date(document.querySelector('input[name="date_needed"]').value);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 0, 0);
    
    if(dateNeeded < tomorrow) {
        alert('Date needed must be at least 1 day in advance for processing.');
        document.querySelector('input[name="date_needed"]').classList.add('error');
        isValid = false;
    }
    
    // Validate purpose length
    const purpose = document.querySelector('textarea[name="purpose"]').value;
    if(purpose.length < 20) {
        alert('Purpose must be at least 20 characters long.');
        isValid = false;
    }
    
    if(!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = form.querySelector('.error');
        if(firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    } else {
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting... <span class="spinner"></span>';
        submitBtn.disabled = true;
    }
});

// Real-time validation for fields
document.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
    field.addEventListener('blur', function() {
        if(!this.value || this.value === '') {
            this.classList.add('error');
            const feedback = this.nextElementSibling;
            if(feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.classList.add('show');
            }
        } else {
            this.classList.remove('error');
            const feedback = this.nextElementSibling;
            if(feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.classList.remove('show');
            }
        }
    });
    
    field.addEventListener('input', function() {
        if(this.value && this.value !== '') {
            this.classList.remove('error');
            const feedback = this.nextElementSibling;
            if(feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.classList.remove('show');
            }
        }
    });
});

// Validate date needed (at least 1 day advance)
document.querySelector('input[name="date_needed"]').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 0, 0);
    
    if(selectedDate < tomorrow) {
        this.setCustomValidity('Date must be at least 1 day in advance');
        this.classList.add('error');
        alert('Please select a date at least 1 day in advance for processing.');
    } else {
        this.setCustomValidity('');
        this.classList.remove('error');
    }
});

// Event listeners for schedule update
document.querySelector('input[name="date_needed"]').addEventListener('change', updateSchedule);
document.querySelector('input[name="time_from"]').addEventListener('input', updateSchedule);
document.querySelector('input[name="time_to"]').addEventListener('input', updateSchedule);

// Event listeners for summary update
document.querySelector('select[name="faculty_id"]').addEventListener('change', updateSummary);
document.querySelector('select[name="room"]').addEventListener('change', updateSummary);
document.querySelector('input[name="subject"]').addEventListener('input', updateSummary);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit form
    if((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        document.getElementById('submitBtn').click();
    }
    
    // Ctrl/Cmd + Plus to add new item
    if((e.ctrlKey || e.metaKey) && e.key === '+') {
        e.preventDefault();
        addApparatusItem();
    }
});

// Warn user about unsaved changes
let formModified = false;
document.getElementById('borrowForm').addEventListener('input', function() {
    formModified = true;
});

window.addEventListener('beforeunload', function(e) {
    if(formModified) {
        e.preventDefault();
        e.returnValue = '';
    }
});

document.getElementById('borrowForm').addEventListener('submit', function() {
    formModified = false;
});

// Auto-save to localStorage (draft functionality)
function saveDraft() {
    const formData = new FormData(document.getElementById('borrowForm'));
    const draft = {};
    for(let [key, value] of formData.entries()) {
        draft[key] = value;
    }
    localStorage.setItem('requisition_draft', JSON.stringify(draft));
    console.log('Draft saved');
}

function loadDraft() {
    const draft = localStorage.getItem('requisition_draft');
    if(draft) {
        const data = JSON.parse(draft);
        const form = document.getElementById('borrowForm');
        
        if(confirm('A draft was found. Would you like to load it?')) {
            for(let key in data) {
                const field = form.querySelector(`[name="${key}"]`);
                if(field) {
                    field.value = data[key];
                }
            }
            updateSchedule();
            updateSummary();
        }
    }
}

// Auto-save every 30 seconds
setInterval(saveDraft, 30000);

// Load draft on page load
window.addEventListener('load', loadDraft);

// Clear draft on successful submit
document.getElementById('borrowForm').addEventListener('submit', function() {
    localStorage.removeItem('requisition_draft');
});

// Duplicate prevention
let submitting = false;
document.getElementById('borrowForm').addEventListener('submit', function(e) {
    if(submitting) {
        e.preventDefault();
        return false;
    }
    submitting = true;
});

// Print functionality
function printForm() {
    window.print();
}

// Export to PDF (browser print dialog)
function exportPDF() {
    const summary = document.getElementById('requestSummary');
    summary.classList.add('show');
    updateSummary();
    setTimeout(() => {
        window.print();
    }, 500);
}

// Initialize
updateItemCount();
updateSchedule();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>