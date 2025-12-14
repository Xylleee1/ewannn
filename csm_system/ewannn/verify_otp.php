<?php
session_start();
require_once('includes/db.php');

$email = $_GET['email'] ?? '';
$err = "";

if (empty($email)) {
    // Fallback if session has email or just redirect to forgot password
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);

    // Verify OTP
    $stmt = mysqli_prepare($conn, "SELECT id, expiry FROM password_resets WHERE email = ? AND otp = ? AND is_verified = 0 ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($res)) {
        // Check expiry
        if (strtotime($row['expiry']) > time()) {
            // Valid
            $_SESSION['reset_authorized'] = true;
            $_SESSION['reset_email'] = $email;

            // Mark as verified (optional, or just delete later)
            $update = mysqli_prepare($conn, "UPDATE password_resets SET is_verified = 1 WHERE id = ?");
            mysqli_stmt_bind_param($update, "i", $row['id']);
            mysqli_stmt_execute($update);

            header("Location: change_password.php");
            exit();
        } else {
            $err = "OTP has expired. Please request a new one.";
        }
    } else {
        $err = "Invalid Verification Code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Verify Code - CSM Borrowing System</title>
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

        p.desc {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 20px;
            letter-spacing: 5px;
            text-align: center;
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
    </style>
</head>

<body>
    <div class="card">
        <h3>Enter Verification Code</h3>
        <p class="desc">A 6-digit code has been sent to <strong><?= htmlspecialchars($email) ?></strong></p>

        <?php if ($err): ?>
            <div class="alert"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>Verification Code</label>
            <input type="text" name="otp" placeholder="000000" maxlength="6" required autofocus autocomplete="off">
            <button type="submit">Verify Code</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="forgot_password.php" style="color: #666; text-decoration: none; font-size: 13px;">Resend Code</a>
        </div>
    </div>
</body>

</html>