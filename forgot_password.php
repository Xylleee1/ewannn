<?php
session_start();
require_once('includes/db.php');

$err = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $err = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $err = "Passwords do not match.";
    } else {
        // Check if email exists
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $user_id);
            mysqli_stmt_fetch($stmt);

            // Update password (plain text)
            $update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($update, "si", $new_password, $user_id);
            if (mysqli_stmt_execute($update)) {
                $success = "Password successfully updated. You can now <a href='index.php'>login</a>.";
            } else {
                $err = "Failed to update password. Please try again.";
            }
            mysqli_stmt_close($update);
        } else {
            $err = "No account found with this email.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password - CSM Borrowing System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
    
body { font-family: Arial, sans-serif; background: #fff; display: flex; justify-content: center; align-items: center; min-height: 90vh; padding: 20px; }
.card { background: #fff; width: 90%; max-width: 450px; padding: 40px 35px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); border-top: 6px solid #FF6F00; }
h3 { text-align: center; background: linear-gradient(135deg, #FF6F00, #FFA040); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px; font-size: 25px; }
label { font-weight: 600; display: block; margin-bottom: 8px; color: #333; font-size: 14px; }
input[type="text"], input[type="password"] { width: 95%; padding: 12px 15px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #ddd; font-size: 14px; transition: border-color 0.3s; }
input:focus { outline: none; border-color: #FF6F00; }
button { width: 102%; padding: 13px; background: linear-gradient(135deg, #FF6F00, #FFA040); color: white; border: none; font-weight: 600; border-radius: 6px; cursor: pointer; font-size: 15px; transition: background 0.3s, transform 0.3s; }
button:hover { transform: translateY(-2px); background: linear-gradient(135deg, #E65100, #FFB74D); }
.alert { background: #fff3e0; color: #e65100; border: 1px solid #ffb74d; padding: 12px; text-align: center; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
.success { background: #e6ffed; color: #1a7f37; border: 1px solid #3ddc97; padding: 12px; text-align: center; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
a { color: #FF6F00; text-decoration: none; }
a:hover { color: #FFA040; text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
    <h3><i class="bi bi-key-fill"></i> Reset Password</h3>

    <?php if ($err): ?>
        <div class="alert"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label><i class="bi bi-envelope-fill"></i> WMSU Email</label>
        <input type="text" name="email" placeholder="Enter your WMSU email" required autofocus>

        <label><i class="bi bi-lock-fill"></i> New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>

        <label><i class="bi bi-lock-fill"></i> Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>

        <button type="submit"><i class="bi bi-arrow-repeat"></i> Reset Password</button>
    </form>

    <div style="text-align:center; margin-top:15px;">
        <a href="index.php"><i class="bi bi-box-arrow-in-left"></i> Back to Login</a>
    </div>
</div>
</body>
</html>
