<?php
// return_apparatus.php
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Fetch requests that are currently borrowed or approved and not returned
$sql = "SELECT br.request_id, br.student_id, u.full_name AS student_name, 
               br.apparatus_id, a.name AS apparatus_name, br.date_needed, 
               br.time_from, br.time_to, br.status
        FROM borrow_requests br
        JOIN users u ON br.student_id = u.user_id
        JOIN apparatus a ON br.apparatus_id = a.apparatus_id
        WHERE br.status IN ('Approved','Borrowed')
        ORDER BY br.date_needed DESC";
$result = $conn->query($sql);
?>

<?php include 'includes/header.php'; ?>

<style>
  *{
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
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
    font-weight: 700;
    margin: 0;
}

.table thead {
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: #fff;
}
.table td, .table th { vertical-align: middle; }
.btn-success { background: linear-gradient(135deg, #FF6F00, #FFA040); border: none; }
.btn-success:hover { background: linear-gradient(135deg, #E65100, #FFB74D); }
.modal-header { background: linear-gradient(135deg, #FF6F00, #FFA040); color: #fff; }
.modal-content { border-radius: 12px; }
</style>

<div class="container my-4">
    <div class="page-header">
        <h2>Mark Apparatus Return</h2>
        <p>Process returns for borrowed apparatus.</p>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Apparatus</th>
                    <th>Date & Time Needed</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['request_id']) ?></td>
                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['apparatus_name']) ?></td>
                    <td><?= date('M d, Y', strtotime($row['date_needed'])) ?> <?= htmlspecialchars($row['time_from'] . ' - ' . $row['time_to']) ?></td>
                    <td><?= ucfirst(strtolower($row['status'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#returnModal<?= $row['request_id'] ?>">Mark Return</button>
                    </td>
                </tr>

                <!-- Return Modal -->
                <div class="modal fade" id="returnModal<?= $row['request_id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <form method="post" action="process_return.php" class="modal-content">
                      <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Return Request #<?= $row['request_id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Condition on Return</label>
                          <select name="condition_on_return" class="form-select" required>
                            <option value="Good">Good</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Lost">Lost</option>
                            <option value="Other">Other</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Remarks (Optional)</label>
                          <textarea name="remarks" class="form-control" rows="3" placeholder="Add any comments..."></textarea>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Confirm Return</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>

                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-info">No borrow requests pending return.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
