<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/header.php';

// Access control
if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger text-center mt-5'>Please login to access this page.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

// Get current month/year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Calculate previous and next month
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get all reservations for the month
$first_day = "$year-$month-01";
$last_day = date('Y-m-t', strtotime($first_day));

$reservations = [];
$result = mysqli_query($conn, "
    SELECT br.*, a.name as apparatus_name, u.full_name as student_name
    FROM borrow_requests br
    LEFT JOIN apparatus a ON br.apparatus_id = a.apparatus_id
    LEFT JOIN users u ON br.student_id = u.user_id
    WHERE br.date_needed BETWEEN '$first_day' AND '$last_day'
    AND br.status IN ('pending', 'approved', 'released')
    ORDER BY br.date_needed, br.time_from
");

while ($row = mysqli_fetch_assoc($result)) {
    $date = date('Y-m-d', strtotime($row['date_needed']));
    if (!isset($reservations[$date])) {
        $reservations[$date] = [];
    }
    $reservations[$date][] = $row;
}

// Calendar generation
$first_day_of_month = strtotime($first_day);
$days_in_month = date('t', $first_day_of_month);
$day_of_week = date('w', $first_day_of_month);

$calendar_weeks = [];
$current_week = array_fill(0, $day_of_week, null);

for ($day = 1; $day <= $days_in_month; $day++) {
    $current_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
    $current_week[] = [
        'day' => $day,
        'date' => $current_date,
        'reservations' => $reservations[$current_date] ?? []
    ];
    
    if (count($current_week) == 7) {
        $calendar_weeks[] = $current_week;
        $current_week = [];
    }
}

if (!empty($current_week)) {
    while (count($current_week) < 7) {
        $current_week[] = null;
    }
    $calendar_weeks[] = $current_week;
}

$month_name = date('F', strtotime($first_day));
?>

<style>
*{
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 8px;
    border-bottom: 2px solid #FF6F00;
}
.page-header h2 {
    font-size: 22px;
    font-weight: 700;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.calendar-nav {
    display: flex;
    gap: 10px;
}
.calendar-nav a {
    background: linear-gradient(135deg, #FF6B00, #FF3D00);
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 300;
    font-size: 13px;
    text-decoration: none;
}
.calendar-nav span { font-size: 16px; font-weight: 600; color: #111827; }

.calendar-container {
    background: #fff;
    padding: 16px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(255,111,0,0.08);
}

.calendar {
    width: 100%;
    border-collapse: collapse;
}
.calendar th, .calendar td {
    padding: 6px;
    text-align: center;
    border: 1px solid #e0e0e0;
    vertical-align: top;
    font-size: 12px;
}
.calendar td { height: 80px; width: 14.28%; }
.calendar td.today { background: #fff8e1; }
.calendar td.empty { background: #fafafa; }

.day-number { font-weight: 600; font-size: 14px; margin-bottom: 2px; }

.reservation-item {
    display: block;
    padding: 2px 4px;
    margin-bottom: 2px;
    border-radius: 4px;
    font-size: 10px;
    cursor: pointer;
    transition: all 0.2s;
    color: #fff;
}
.reservation-item:hover { transform: translateX(1px); }
.reservation-item.pending { background: #FFC107; color: #111; }
.reservation-item.approved { background: #16A34A; }
.reservation-item.released { background: #0EA5E9; }

.legend {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    padding: 8px;
    background: #fff8e1;
    border-radius: 6px;
}
.legend-item { display: flex; align-items: center; gap: 4px; font-size: 11px; }
.legend-color { width: 16px; height: 16px; border-radius: 3px; }

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}
.modal-content {
    background: #fff;
    margin: 5% auto;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.modal-header h3 { color: #FF6F00; margin: 0; font-size: 16px; }
.close { font-size: 22px; cursor: pointer; }

.detail-row {
    margin-bottom: 8px;
    padding: 8px;
    background: #f5f5f5;
    border-radius: 6px;
    font-size: 13px;
}
.detail-row strong { display: block; color: #FF6F00; margin-bottom: 2px; }

</style>

<div class="page-header">
    <h2><i class="bi bi-calendar3"></i> Reservation Calendar</h2>
    <div class="calendar-nav">
        <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>">
            <i class="bi bi-chevron-left"></i> Previous
        </a>
        <span><?= $month_name ?> <?= $year ?></span>
        <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>">
            Next <i class="bi bi-chevron-right"></i>
        </a>
        <a href="calendar.php">
            <i class="bi bi-house"></i> Today
        </a>
    </div>
</div>

<div class="calendar-container">
    <table class="calendar">
        <thead>
            <tr>
                <th>Sunday</th>
                <th>Monday</th>
                <th>Tuesday</th>
                <th>Wednesday</th>
                <th>Thursday</th>
                <th>Friday</th>
                <th>Saturday</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($calendar_weeks as $week): ?>
            <tr>
                <?php foreach ($week as $day_data): ?>
                    <?php if ($day_data === null): ?>
                        <td class="empty"></td>
                    <?php else: ?>
                        <?php 
                        $is_today = $day_data['date'] === date('Y-m-d');
                        $class = $is_today ? 'today' : '';
                        ?>
                        <td class="<?= $class ?>">
                            <div class="day-number"><?= $day_data['day'] ?></div>
                            <?php foreach ($day_data['reservations'] as $res): ?>
                                <div class="reservation-item <?= strtolower($res['status']) ?>" 
                                     onclick='showReservation(<?= json_encode($res) ?>)'>
                                    <i class="bi bi-clock"></i> <?= date('h:i A', strtotime($res['time_from'])) ?>
                                    <br><?= htmlspecialchars(substr($res['apparatus_name'], 0, 15)) ?>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="legend">
        <div class="legend-item">
            <div class="legend-color" style="background: #FFC107;"></div>
            <span>Pending</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #16A34A;"></div>
            <span>Approved</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #0EA5E9;"></div>
            <span>Released</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #fff8e1; border: 2px solid #FF6F00;"></div>
            <span>Today</span>
        </div>
    </div>
</div>

<!-- Reservation Details Modal -->
<div id="reservationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="bi bi-info-circle"></i> Reservation Details</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div id="reservationDetails"></div>
    </div>
</div>

<script>
function showReservation(res) {
    const modal = document.getElementById('reservationModal');
    const details = document.getElementById('reservationDetails');
    
    const statusBadge = `<span style="padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; background: ${getStatusColor(res.status)}; color: white;">${res.status}</span>`;
    
    details.innerHTML = `
        <div class="detail-row">
            <strong>Request ID</strong>
            #${res.request_id}
        </div>
        <div class="detail-row">
            <strong>Student</strong>
            ${res.student_name}
        </div>
        <div class="detail-row">
            <strong>Apparatus</strong>
            ${res.apparatus_name}
        </div>
        <div class="detail-row">
            <strong>Quantity</strong>
            ${res.quantity}
        </div>
        <div class="detail-row">
            <strong>Date Needed</strong>
            ${new Date(res.date_needed).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </div>
        <div class="detail-row">
            <strong>Time</strong>
            ${formatTime(res.time_from)} - ${formatTime(res.time_to)}
        </div>
        <div class="detail-row">
            <strong>Room</strong>
            ${res.room || 'N/A'}
        </div>
        <div class="detail-row">
            <strong>Purpose</strong>
            ${res.purpose}
        </div>
        <div class="detail-row">
            <strong>Status</strong>
            ${statusBadge}
        </div>
    `;
    
    modal.style.display = 'block';
}

function getStatusColor(status) {
    const colors = {
        'pending': '#FFC107',
        'approved': '#16A34A',
        'released': '#0EA5E9',
        'returned': '#7C3AED',
        'rejected': '#E11D48'
    };
    return colors[status.toLowerCase()] || '#111827';
}

function formatTime(time) {
    const [h, m] = time.split(':');
    const hour = parseInt(h);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${m} ${ampm}`;
}

function closeModal() {
    document.getElementById('reservationModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('reservationModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>