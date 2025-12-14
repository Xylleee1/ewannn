<?php
session_start();
require_once('includes/db.php');

if (!isset($_SESSION['reset_authorized']) || !$_SESSION['reset_authorized'] || empty($_SESSION['reset_email'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['reset_email'];
$err = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($new) < 8) {
        $err = "Password must be at least 8 characters long.";
    } elseif ($new !== $confirm) {
        $err = "Passwords do not match.";
    } else {
        // Prevent reuse (check against old password if you want, usually optional but recommended)
        // Here we just update.

        $hashed = password_hash($new, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "ss", $hashed, $email);

        if (mysqli_stmt_execute($stmt)) {
            // Clear session
            unset($_SESSION['reset_authorized']);
            unset($_SESSION['reset_email']);

            // Clean up old tokens
            $clean = mysqli_prepare($conn, "DELETE FROM password_resets WHERE email = ?");
            mysqli_stmt_bind_param($clean, "s", $email);
            mysqli_stmt_execute($clean);

            $success = "Password updated successfully!";
        } else {
            $err = "Failed to update password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Change Password - CSM Borrowing System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 90vh;
            padding: 20px;
        }

        .card {
            background: #fff;
            width: 90%;
            max-width: 450px;
            padding: 40px 35px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-top: 6px solid #FF6F00;
        }

        h3 {
            text-align: center;
            color: #FF6F00;
            margin-bottom: 10px;
            font-size: 24px;
            font-weight: 700;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 14px;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #FF6F00;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #FF6F00;
            color: white;
            border: none;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            transition: background 0.2s;
        }

        button:hover {
            background: #E65100;
        }

        .alert {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px;
            text-align: center;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success-msg {
            text-align: center;
            padding: 20px;
        }

        .success-icon {
            font-size: 48px;
            color: #16a34a;
            margin-bottom: 15px;
            display: block;
        }
    </style>
</head>

<body>
    <div class="card">
        <?php if ($success): ?>
            <div class="success-msg">
                <i class="bi bi-check-circle-fill success-icon"></i>
                <h3>Success!</h3>
                <p style="color: #666; margin-bottom: 20px;"><?= $success ?></p>
                <a href="index.php"
                    style="display: block; width: 100%; padding: 12px; background: #FF6F00; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">Go
                    to Login</a>
            </div>
        <?php else: ?>
            <h3>Set New Password</h3>

            <?php if ($err): ?>
                <div class="alert"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Min. 8 characters" required>

                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required>

                <button type="submit">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>