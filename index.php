<?php
session_start();
require_once('includes/db.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $is_exception = ($username === 'admin' || $username === 'faculty1');
    $is_wmsu_email = strpos($username, '@wmsu.edu.ph') !== false;

//admin login
    if ($is_exception) {
        $stmt = mysqli_prepare($conn, "SELECT user_id, username, password, role, full_name, email 
                                       FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $user_id, $db_username, $db_password, $role, $full_name, $email);
            mysqli_stmt_fetch($stmt);

            if ($password === $db_password) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $db_username;
                $_SESSION['full_name'] = $full_name ?: $db_username;
                $_SESSION['role'] = $role;

                add_log($conn, $user_id, "Login", "Admin or faculty login successful.");
                header("Location: dashboard.php");
                exit();
            } else {
                $err = "Invalid password.";
            }
        } else {
            $err = "User not found.";
        }

        mysqli_stmt_close($stmt);
    }

//student log in email
    elseif ($is_wmsu_email) {
        $stmt = mysqli_prepare($conn, "SELECT user_id, username, password, role, full_name, email 
                                       FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $user_id, $db_username, $db_password, $role, $full_name, $email);
            mysqli_stmt_fetch($stmt);

            if (!empty($db_password) && $password !== $db_password) {
                $err = "Invalid password for this WMSU account.";
            } else {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $db_username;
                $_SESSION['full_name'] = $full_name ?: $username;
                $_SESSION['role'] = $role;

                add_log($conn, $user_id, "Login", "WMSU student login.");
                header("Location: dashboard.php");
                exit();
            }
        } else {
 // Auto-register new WMSU student
            $role = "student";
            $full_name = "";
            $username_part = explode("@", $username)[0];

            $insert = mysqli_prepare($conn, "INSERT INTO users (username, password, role, full_name, email) 
                                             VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert, "sssss", $username_part, $password, $role, $full_name, $username);
            mysqli_stmt_execute($insert);
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insert);

            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username_part;
            $_SESSION['full_name'] = $username_part;
            $_SESSION['role'] = $role;

            add_log($conn, $user_id, "Login", "New WMSU student auto-registered.");
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $err = "Access denied. Only @wmsu.edu.ph emails or authorized users can log in.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Login - CSM Borrowing System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
* { box-sizing: border-box; font-family: Arial, sans-serif; }
body {
    margin: 0;
    background: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}

.card {
    background: #fff;
    width: 100%;
    max-width: 450px;
    padding: 40px 20px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    border-top: 6px solid #FF6F00;
}

h3 {
    text-align: center;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    font-size: 25px;
}

.subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 30px;
    font-size: 14px;
}

label {
    font-weight: 600;
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-size: 14px;
}

input[type="text"], input[type="password"] {
    width: 100%;
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 6px;
    border: 1px solid #ddd;
    font-size: 14px;
    transition: border-color 0.3s;
}

input[type="text"]:focus, input[type="password"]:focus {
    outline: none;
    border-color: #FF6F00;
}

button {
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: white;
    border: none;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    transition: background 0.3s, transform 0.3s;
}

button:hover { 
    transform: translateY(-2px);
    background: linear-gradient(135deg, #E65100, #FFB74D);
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

.info-box {
    background: #fff8e1;
    border-left: 4px solid #FF6F00;
    padding: 12px;
    margin-top: 20px;
    border-radius: 4px;
    font-size: 13px;
    color: #555;
}

.bi { margin-right: 6px; }
</style>
</head>

<body>
<div class="card">
    <h3><i class="bi bi-box-seam"></i> CSM Borrowing System</h3>
    <p class="subtitle">College of Science and Mathematics</p>

    <?php if ($err): ?>
        <div class="alert"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>  
    <form method="POST" action="">
        <label><i class="bi bi-envelope-fill"></i> Email or Username</label>
        <input type="text" name="username" placeholder="Enter your WMSU email or admin username" required autofocus>

        <label><i class="bi bi-lock-fill"></i> Password</label>
        <input type="password" name="password" placeholder="Enter your password">
        
    <div style="text-align: right; margin-bottom: 20px;">
        <a href="forgot_password.php" style="font-size: 13px; color: #FF6F00; text-decoration: none;">
            <i class="bi bi-question-circle"></i> Forgot Password?
        </a>
    </div>

        <button type="submit" name="login"><i class="bi bi-box-arrow-in-right"></i> Login</button>
    </form>

    <div class="info-box">
        <i class="bi bi-info-circle-fill"></i>
        <strong>Note:</strong> Students with <strong>@wmsu.edu.ph</strong> emails are auto-registered on first login.
        Their entered password becomes their permanent one.<br>
    </div>
</div>
</body>
</html>
