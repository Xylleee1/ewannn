<?php
session_start();
require_once('includes/db.php');
require_once('includes/email_notifications.php');

$err = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $err = "Please enter your email address.";
    } elseif (!str_ends_with($email, '@wmsu.edu.ph')) {
        $err = "Only @wmsu.edu.ph emails are allowed.";
    } else {
        // Check if email exists
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($res)) {
            // Generate OTP
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Store OTP
            $insert = mysqli_prepare($conn, "INSERT INTO password_resets (email, otp, expiry) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($insert, "sss", $email, $otp, $expiry);

            if (mysqli_stmt_execute($insert)) {
                // Send Email
                if (send_otp_email($email, $otp)) {
                    // Redirect to verification page
                    // Encode email to pass it safely
                    header("Location: verify_otp.php?email=" . urlencode($email));
                    exit();
                } else {
                    $err = "Failed to send OTP. Please check your email configuration.";
                }
            } else {
                $err = "System error. Please try again later.";
            }
        } else {
            // Security: generic message or specific? 
            // Requirement says "Validate that the email exists in the system".
            // Usually for security we say "If the email exists..." to prevent enumeration, 
            // but user prompt specifically asked to "Validate that the email exists".
            $err = "No account found with this WMSU email address.";
        }
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
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffb74d;
            padding: 12px;
            text-align: center;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        a {
            color: #FF6F00;
            text-decoration: none;
            font-size: 14px;
        }

        a:hover {
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h3>Forgot Password?</h3>
        <p class="desc">Enter your WMSU email address to receive a verification code.</p>

        <?php if ($err): ?>
            <div class="alert"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label><i class="bi bi-envelope-fill"></i> WMSU Email</label>
            <input type="text" name="email" placeholder="example@wmsu.edu.ph" required autofocus>
            <button type="submit">Send Verification Code</button>
        </form>

        <div class="back-link">
            <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>

</html>