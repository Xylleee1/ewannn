<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo "<div class='container mt-5'><div class='alert alert-warning text-center'>
            <i class='bi bi-exclamation-triangle-fill fs-1'></i><br>
            <h4>Access Restricted</h4>
            <p>You can only view notifications related to your own requests. Please check your <a href='request_tracker.php'>Request Tracker</a>.</p>
          </div></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}



$msg = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request'])) {
    $student_id = $_SESSION['user_id'];
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
    $success_items = [];

    // Check if apparatus_items exists and is an array
    if (isset($_POST['apparatus_items']) && is_array($_POST['apparatus_items'])) {
        foreach ($_POST['apparatus_items'] as $item) {
            $apparatus_id = intval($item['apparatus_id']);
            $quantity = intval($item['quantity']);
            $concentration = isset($item['concentration']) ? trim($item['concentration']) : 'N/A';

            // Skip empty items
            if ($apparatus_id <= 0 || $quantity <= 0) {
                continue;
            }

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
                (student_id, faculty_id, apparatus_id, quantity, concentration, date_requested, date_needed, time_from, time_to, subject, schedule, room, purpose, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'iiissssssssss', $student_id, $faculty_id, $apparatus_id, $quantity, $concentration, $date_requested, $date_needed, $time_from, $time_to, $subject, $schedule, $room, $purpose);

            if (mysqli_stmt_execute($stmt)) {
                $request_id = mysqli_insert_id($conn);
                $success_count++;
                $success_items[] = $row['name'];

                // Log the action
                add_log($conn, $student_id, "Borrow Request", "Requested {$row['name']} (Qty: $quantity)");

                // Send notification to faculty
                $notification_title = "New Borrow Request";
                $notification_message = $_SESSION['full_name'] . " has requested {$row['name']} (Qty: $quantity) for $subject.";
                create_notification($faculty_id, $notification_title, $notification_message, 'info', $request_id, 'borrow_request');

                // Send notification to all admins/assistants
                $admins = mysqli_query($conn, "SELECT user_id FROM users WHERE role IN ('admin', 'assistant')");
                while ($admin = mysqli_fetch_assoc($admins)) {
                    create_notification(
                        $admin['user_id'],
                        'New Borrow Request',
                        "A new borrow request has been submitted by {$_SESSION['full_name']} for {$row['name']} (Qty: $quantity).",
                        'info',
                        $request_id,
                        'borrow_request'
                    );
                }
            } else {
                $error_count++;
                $errors[] = "Failed to request {$row['name']}: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $errors[] = "No apparatus items selected.";
        $error_count++;
    }

    // Build message
    if ($success_count > 0) {
        $msg = "✓ Successfully submitted $success_count request(s) for: " . implode(", ", $success_items) . ". Please wait for instructor approval.";
        $msgClass = "success";
    }

    if ($error_count > 0) {
        if ($success_count > 0) {
            $msg .= " However, " . $error_count . " request(s) failed: " . implode("; ", $errors);
            $msgClass = "warning";
        } else {
            $msg = "✗ " . $error_count . " request(s) failed: " . implode("; ", $errors);
            $msgClass = "error";
        }
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
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
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
        position: relative;
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
        background: #D1FAE5;
        color: #065F46;
        border-left: 5px solid #10B981;
    }

    .alert.warning {
        background: #FEF3C7;
        color: #92400E;
        border-left: 5px solid #F59E0B;
    }

    .alert.error {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 5px solid #DC2626;
    }

    .alert .close-btn {
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        opacity: 0.7;
        color: inherit;
    }

    .alert .close-btn:hover {
        opacity: 1;
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
        box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.1);
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
        box-shadow: 0 8px 25px rgba(255, 107, 0, 0.4);
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

    .apparatus-list {
        border: 2px dashed #d0d0d0;
        border-radius: 14px;
        padding: 25px;
        margin-bottom: 24px;
        background: #fafafa;
        min-height: 120px;
    }

    .apparatus-item {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
        /* Aligns inputs and button to bottom */
        margin-bottom: 18px;
        padding: 18px;
        background: #fff;
        border-radius: 10px;
        border: 2px solid #e5e5e5;
        transition: all 0.3s ease;
        animation: fadeIn 0.3s ease-out;
    }

    .apparatus-item>div.field-group {
        flex: 1;
        min-width: 200px;
        transition: all 0.3s ease;
    }

    /* Remove bottom margin from inputs inside the apparatus item for better alignment */
    .apparatus-item .field-group input,
    .apparatus-item .field-group select {
        margin-bottom: 0;
    }

    .apparatus-item>div.field-group-small {
        flex: 0 0 auto;
        /* Fixed width removed, let content decide */
    }

    .info-message {
        width: 100%;
        margin-top: 15px;
        font-size: 0.9em;
        padding: 8px 12px;
        background-color: #f0f9ff;
        border-left: 3px solid #0ea5e9;
        border-radius: 4px;
        color: #0284c7;
        display: none;
        /* Hidden by default */
    }

    .error-message {
        color: #dc2626;
        font-size: 0.85em;
        margin-top: 4px;
        display: none;
    }

    .concentration-wrapper {
        display: none;
        /* Hidden by default, shown via JS */
    }

    .apparatus-item:hover {
        border-color: #FFB380;
        box-shadow: 0 4px 15px rgba(255, 107, 0, 0.1);
    }

    .btn-remove {
        background: #E11D48;
        color: white;
        border: none;
        padding: 12px 18px;
        /* Matches input height roughly */
        height: 52px;
        /* Force height to match standard inputs */
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: auto;
        min-width: 52px;
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
        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
    }

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

    .helper-text {
        font-size: 12px;
        color: #666;
        margin-top: -18px;
        margin-bottom: 20px;
        font-style: italic;
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
</style>

<div class="container-form mb-5">
    <h2><i class="bi bi-beaker"></i> CHEMICAL REQUISITION FORM</h2>
    <p class="subtitle">College of Science and Mathematics</p>

    <?php if ($msg): ?>
        <div class="alert <?= $msgClass ?>" id="alertBanner">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="close-btn" onclick="closeAlert()">&times;</button>
        </div>
    <?php endif; ?>

    <form method="POST" id="borrowForm" novalidate>
        <div class="form-row">
            <div class="form-group">
                <label><i class="bi bi-calendar-check"></i> Date Requested</label>
                <input type="date" name="date_requested" value="<?= date('Y-m-d'); ?>" required readonly>
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
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="bi bi-calendar-event"></i> Date Needed</label>
                <input type="date" name="date_needed" min="<?= date('Y-m-d', strtotime('+1 day')); ?>" required>
            </div>

            <div class="form-group">
                <label><i class="bi bi-clock"></i> Time From</label>
                <input type="time" name="time_from" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><i class="bi bi-clock-history"></i> Time To</label>
                <input type="time" name="time_to" required>
            </div>

            <div class="form-group">
                <label><i class="bi bi-journal-text"></i> Subject</label>
                <input type="text" name="subject" placeholder="e.g., Chemistry 101" required maxlength="100">
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

        <label><i class="bi bi-box-seam"></i> Apparatus/Reagents to Borrow</label>
        <div class="items-counter">
            <i class="bi bi-list-check"></i> Items Selected: <span id="itemCount">1</span>
        </div>
        <div class="apparatus-list" id="apparatusList">
            <div class="apparatus-item" data-index="0">
                <div class="field-group">
                    <label style="margin-bottom: 6px;">Select Item *</label>
                    <select name="apparatus_items[0][apparatus_id]" class="apparatus-select" required
                        onchange="handleApparatusChange(this)">
                        <option value="">-- Select Item --</option>
                        <?php
                        mysqli_data_seek($apparatus, 0);
                        while ($a = mysqli_fetch_assoc($apparatus)):
                            ?>
                            <option value="<?= $a['apparatus_id'] ?>" data-quantity="<?= $a['quantity'] ?>"
                                data-category="<?= htmlspecialchars($a['category']) ?>">
                                <?= htmlspecialchars($a['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="field-group concentration-wrapper">
                    <label style="margin-bottom: 6px;" class="concentration-label">Size / Concentration</label>
                    <select name="apparatus_items[0][concentration]" class="concentration-select">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div class="field-group">
                    <label style="margin-bottom: 6px;">Quantity *</label>
                    <input type="number" name="apparatus_items[0][quantity]" min="1" placeholder="Qty"
                        class="quantity-input" required oninput="handleQuantityChange(this)">
                    <div class="error-message"></div>
                </div>

                <div class="field-group-small">
                    <button type="button" class="btn-remove" onclick="removeItem(this)" style="display: none;">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>

                <div class="info-message"></div>
            </div>
        </div>
        <button type="button" class="btn-add-item" onclick="addApparatusItem()">
            <i class="bi bi-plus-circle"></i> Add Another Item
        </button>

        <label><i class="bi bi-pencil-square"></i> Purpose / Activity</label>
        <textarea name="purpose" required
            placeholder="Describe the purpose or activity for this request (minimum 20 characters)" minlength="20"
            maxlength="500"></textarea>
        <p class="helper-text">Character count: <span id="charCount">0</span>/500</p>

        <!-- Request Summary -->
        <div class="request-summary" id="requestSummary">
            <h3><i class="bi bi-clipboard-check"></i> Request Summary</h3>
            <div id="summaryContent"></div>
        </div>

        <button name="request" type="submit" id="submitBtn" onclick="return validateForm()">
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

    let itemCount = 1;

    // Close alert banner
    function closeAlert() {
        const alert = document.getElementById('alertBanner');
        if (alert) {
            alert.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => alert.remove(), 300);
        }
    }

    // Auto-close alert after 5 seconds
    <?php if ($msg): ?>
        setTimeout(closeAlert, 5000);
    <?php endif; ?>

    // Add apparatus item
    function addApparatusItem() {
        const list = document.getElementById('apparatusList');
        const newItem = document.createElement('div');
        newItem.className = 'apparatus-item';
        newItem.setAttribute('data-index', itemCount);
        newItem.innerHTML = `
        <div class="field-group">
            <label style="margin-bottom: 6px;">Select Item *</label>
            <select name="apparatus_items[${itemCount}][apparatus_id]" class="apparatus-select" required onchange="handleApparatusChange(this)">
                <option value="">-- Select Item --</option>
                <?php
                mysqli_data_seek($apparatus, 0);
                while ($a = mysqli_fetch_assoc($apparatus)):
                    ?>
                    <option value="<?= $a['apparatus_id'] ?>" 
                            data-quantity="<?= $a['quantity'] ?>" 
                            data-category="<?= htmlspecialchars($a['category']) ?>">
                        <?= htmlspecialchars($a['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="field-group concentration-wrapper">
            <label style="margin-bottom: 6px;" class="concentration-label">Size / Concentration</label>
            <select name="apparatus_items[${itemCount}][concentration]" class="concentration-select">
                <option value="">-- Select --</option>
            </select>
        </div>

        <div class="field-group">
            <label style="margin-bottom: 6px;">Quantity *</label>
            <input type="number" name="apparatus_items[${itemCount}][quantity]" min="1" placeholder="Qty" class="quantity-input" required oninput="handleQuantityChange(this)">
            <div class="error-message"></div>
        </div>

        <div class="field-group-small">
             <button type="button" class="btn-remove" onclick="removeItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        
        <div class="info-message"></div>
    `;
        list.appendChild(newItem);
        itemCount++;
        updateRemoveButtons();
        updateItemCount();
    }

    // Handle Apparatus Change
    function handleApparatusChange(selectElement) {
        const itemContainer = selectElement.closest('.apparatus-item');
        const selectedOption = selectElement.options[selectElement.selectedIndex];

        if (!selectedOption.value) {
            // Reset if no selection
            resetItemFields(itemContainer);
            return;
        }

        const category = selectedOption.getAttribute('data-category');
        const quantityAvailable = parseInt(selectedOption.getAttribute('data-quantity')) || 0;
        const itemName = selectedOption.text.trim();

        // Elements
        const concentrationWrapper = itemContainer.querySelector('.concentration-wrapper');
        const concentrationSelect = itemContainer.querySelector('.concentration-select');
        const concentrationLabel = itemContainer.querySelector('.concentration-label');
        const quantityInput = itemContainer.querySelector('.quantity-input');
        const infoMessage = itemContainer.querySelector('.info-message');

        // Logic to determine if size/concentration is needed
        let needsExtraField = false;
        let optionsToUse = [];
        let labelText = '';

        if (['Reagent', 'Chemical'].includes(category)) {
            needsExtraField = true;
            labelText = 'Concentration *';
            optionsToUse = concentrationOptions;
        } else if (category === 'Flask' || itemName.toLowerCase().includes('flask')) {
            needsExtraField = true;
            labelText = 'Flask Size *';
            optionsToUse = flaskSizes;
        } else if (category === 'Beaker' || itemName.toLowerCase().includes('beaker')) {
            needsExtraField = true;
            labelText = 'Beaker Size *';
            optionsToUse = beakerSizes;
        } else if (['Graduated Cylinder', 'Test Tube'].includes(category) || itemName.toLowerCase().includes('cylinder') || itemName.toLowerCase().includes('tube')) {
            // Generic size bucket for others if applicable, or custom check
            needsExtraField = true;
            labelText = 'Size *';
            optionsToUse = sizeOptions;
        }

        // Reset and Hide Extra Field First
        concentrationWrapper.style.display = 'none';
        concentrationSelect.required = false;
        concentrationSelect.innerHTML = '<option value="">-- Select --</option>';

        let sizeSetText = '';

        if (needsExtraField) {
            concentrationWrapper.style.display = 'block'; // Show middle box
            concentrationLabel.textContent = labelText;
            concentrationSelect.required = true;

            // Populate Options
            let matchFound = false;
            let matchedValue = '';

            optionsToUse.forEach(optVal => {
                const opt = document.createElement('option');
                opt.value = optVal;
                opt.textContent = optVal;

                // Auto-select logic
                // Escape special chars for regex
                const escapedOption = optVal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                // Regex: looks for the size value as a whole word in the item name
                const regex = new RegExp(`\\b${escapedOption}\\b`, 'i');

                if (regex.test(itemName)) {
                    opt.selected = true;
                    matchedValue = optVal;
                    matchFound = true;
                }
                concentrationSelect.appendChild(opt);
            });

            if (matchFound) {
                sizeSetText = ` Size set to ${matchedValue}.`;
            }
        }

        // Auto-fill Quantity
        if (!quantityInput.value || quantityInput.value <= 0) {
            quantityInput.value = 1;
        }

        // Update Info Message
        infoMessage.style.display = 'block';
        infoMessage.innerHTML = `Selected apparatus: <strong>${itemName}</strong>.${sizeSetText} Quantity available: <strong>${quantityAvailable}</strong>.`;

        // Store max quantity for validation
        quantityInput.dataset.max = quantityAvailable;

        // Trigger validation in case quantity is already invalid
        validateQuantity(quantityInput);

        updateSummary();
    }

    function resetItemFields(itemContainer) {
        itemContainer.querySelector('.concentration-wrapper').style.display = 'none';
        itemContainer.querySelector('.concentration-select').required = false;
        itemContainer.querySelector('.info-message').style.display = 'none';
        itemContainer.querySelector('.quantity-input').value = '';
        updateSummary();
    }

    function handleQuantityChange(input) {
        validateQuantity(input);
        updateSummary();
    }

    function validateQuantity(input) {
        const itemContainer = input.closest('.apparatus-item');
        const max = parseInt(input.dataset.max) || 0;
        const current = parseInt(input.value) || 0;
        const errorMsg = itemContainer.querySelector('.error-message');

        if (current > max) {
            errorMsg.textContent = "The quantity you entered exceeds available stock. Please adjust.";
            errorMsg.style.display = 'block';
            input.setCustomValidity("Quantity exceeds available stock.");
            input.style.borderColor = '#dc2626';
        } else {
            errorMsg.style.display = 'none';
            input.setCustomValidity("");
            input.style.borderColor = '#e0e0e0';
        }
    }

    function validateForm() {
        // Final check before submit
        const selects = document.querySelectorAll('.apparatus-select');
        let isValid = true;
        let hasSelection = false;

        selects.forEach(select => {
            if (select.value) hasSelection = true;
        });

        if (!hasSelection) {
            alert("Please select an apparatus before submitting your request.");
            return false;
        }

        // Check for any invalid inputs
        const inputs = document.querySelectorAll('.quantity-input');
        inputs.forEach(input => {
            if (!input.checkValidity()) {
                isValid = false;
                // Focus on the first invalid input
                input.reportValidity();
            }
        });

        return isValid;
    }

    // Character counter
    const purposeTextarea = document.querySelector('textarea[name="purpose"]');
    const charCount = document.getElementById('charCount');
    purposeTextarea.addEventListener('input', function () {
        charCount.textContent = this.value.length;
        updateSummary();
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

            if (select.value && quantityInput.value) {
                const itemName = select.options[select.selectedIndex].text.split(' (Available')[0];
                let concentration = concentrationSelect.value || 'N/A';

                const quantity = quantityInput.value;
                selectedItems.push({ name: itemName, concentration: concentration, quantity: quantity });
                hasData = true;
            }
        });

        if (selectedItems.length > 0) {
            html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-box-seam"></i> Items Requested:</span><span class="summary-value"><strong>' + selectedItems.length + '</strong></span></div>';
            selectedItems.forEach((item, index) => {
                html += `<div class="summary-item" style="padding-left: 20px; font-size: 13px;">
                <span class="summary-label">${index + 1}. ${item.name}</span>
                <span class="summary-value">${item.concentration} × ${item.quantity}</span>
            </div>`;
            });
        }

        const faculty = document.querySelector('select[name="faculty_id"]');
        if (faculty.value) {
            html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-person-badge"></i> Instructor:</span><span class="summary-value">' + faculty.options[faculty.selectedIndex].text + '</span></div>';
            hasData = true;
        }

        const schedule = document.getElementById('schedule').value;
        if (schedule) {
            html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-calendar3"></i> Schedule:</span><span class="summary-value">' + schedule + '</span></div>';
            hasData = true;
        }

        const room = document.querySelector('select[name="room"]').value;
        if (room) {
            html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-building"></i> Room:</span><span class="summary-value">' + room + '</span></div>';
            hasData = true;
        }

        const subject = document.querySelector('input[name="subject"]').value;
        if (subject) {
            html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-journal-text"></i> Subject:</span><span class="summary-value">' + subject + '</span></div>';
            hasData = true;
        }

        const purpose = document.querySelector('textarea[name="purpose"]').value;
        if (purpose && purpose.length >= 20) {
            const truncatedPurpose = purpose.length > 80 ? purpose.substring(0, 80) + '...' : purpose;
            html += '<div class="summary-item"><span class="summary-label"><i class="bi bi-pencil-square"></i> Purpose:</span><span class="summary-value" style="font-size: 13px;">' + truncatedPurpose + '</span></div>';
            hasData = true;
        }

        summaryContent.innerHTML = html;
        if (hasData) {
            summary.classList.add('show');
        } else {
            summary.classList.remove('show');
        }
    }

    // Event listeners for schedule update
    const exDateNeeded = document.querySelector('input[name="date_needed"]');
    if (exDateNeeded) exDateNeeded.addEventListener('change', updateSchedule);

    const exTimeFrom = document.querySelector('input[name="time_from"]');
    if (exTimeFrom) exTimeFrom.addEventListener('input', updateSchedule);

    const exTimeTo = document.querySelector('input[name="time_to"]');
    if (exTimeTo) exTimeTo.addEventListener('input', updateSchedule);

    // Event listeners for summary update
    const exFaculty = document.querySelector('select[name="faculty_id"]');
    if (exFaculty) exFaculty.addEventListener('change', updateSummary);

    const exRoom = document.querySelector('select[name="room"]');
    if (exRoom) exRoom.addEventListener('change', updateSummary);

    const exSubject = document.querySelector('input[name="subject"]');
    if (exSubject) exSubject.addEventListener('input', updateSummary);

    // Initial setup for the first item (if any) - no need to auto-trigger since value is empty by default,
    // but good practice to ensure handlers are ready.
    const firstSelect = document.querySelector('.apparatus-select');
    if (firstSelect && firstSelect.value) {
        handleApparatusChange(firstSelect);
    }

    // Initialize
    updateItemCount();
    updateSchedule();
    updateSummary();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>