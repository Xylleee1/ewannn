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
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $err = "Invalid security token. Please try again.";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (empty($username) || empty($password)) {
            $err = "Please enter both username and password.";
        } else {
            $is_exception = ($username === 'admin' || 
                           $username === 'faculty1' ||
                           $username === 'admin@csm.edu.ph');

            $is_wmsu_email = strpos($username, '@wmsu.edu.ph') !== false;
            
            // Admin/Faculty login
            if ($is_exception) {
                $stmt = mysqli_prepare($conn, "SELECT user_id, username, password, role, full_name, email FROM users WHERE username = ?");
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    mysqli_stmt_bind_result($stmt, $user_id, $db_username, $db_password, $role, $full_name, $email);
                    mysqli_stmt_fetch($stmt);
                    mysqli_stmt_close($stmt);
                    
                    // Verify password with automatic rehashing
                    $password_check = verify_password($password, $db_password);
                    
                    if ($password_check['valid']) {
                        // Rehash password if needed (for legacy passwords)
                        if ($password_check['needs_rehash']) {
                            rehash_user_password($conn, $user_id, $password);
                        }
                        
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $db_username;
                        $_SESSION['full_name'] = $full_name ?: $db_username;
                        $_SESSION['role'] = $role;
                        $_SESSION['email'] = $email;
                        
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        add_log($conn, $user_id, "Login", "Admin or faculty login successful.");
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $err = "Invalid password.";
                    }
                } else {
                    mysqli_stmt_close($stmt);
                    $err = "User not found.";
                }
            }
            // Student login with WMSU email
            elseif ($is_wmsu_email) {
                $stmt = mysqli_prepare($conn, "SELECT user_id, username, password, role, full_name, email FROM users WHERE email = ?");
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    mysqli_stmt_bind_result($stmt, $user_id, $db_username, $db_password, $role, $full_name, $email);
                    mysqli_stmt_fetch($stmt);
                    mysqli_stmt_close($stmt);
                    
                    // For existing WMSU accounts, verify password
                    if (!empty($db_password)) {
                        $password_check = verify_password($password, $db_password);
                        
                        if (!$password_check['valid']) {
                            $err = "Invalid password for this WMSU account.";
                        } else {
                            // Rehash if needed
                            if ($password_check['needs_rehash']) {
                                rehash_user_password($conn, $user_id, $password);
                            }
                            
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['username'] = $db_username;
                            $_SESSION['full_name'] = $full_name ?: $username;
                            $_SESSION['role'] = $role;
                            $_SESSION['email'] = $email;
                            
                            session_regenerate_id(true);
                            add_log($conn, $user_id, "Login", "WMSU student login.");
                            header("Location: dashboard.php");
                            exit();
                        }
                    } else {
                        // Account exists but has no password (shouldn't happen, but handle it)
                        $err = "Account configuration error. Please contact administrator.";
                    }
                } else {
                    mysqli_stmt_close($stmt);
                    
                    // Auto-register new WMSU student
                    $role = "student";
                    $full_name = "";
                    $username_part = explode("@", $username)[0];
                    $hashed_password = hash_password($password);
                    
                    $insert = mysqli_prepare($conn,
                        "INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($insert, "sssss", $username_part, $hashed_password, $role, $full_name, $username);
                    
                    if (mysqli_stmt_execute($insert)) {
                        $user_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($insert);
                        
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username_part;
                        $_SESSION['full_name'] = $username_part;
                        $_SESSION['role'] = $role;
                        $_SESSION['email'] = $username;
                        
                        session_regenerate_id(true);
                        add_log($conn, $user_id, "Login", "New WMSU student auto-registered.");
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        mysqli_stmt_close($insert);
                        $err = "Registration failed. Please try again.";
                    }
                }
            } else {
                $err = "Access denied. Only @wmsu.edu.ph emails or authorized users can log in.";
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
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
    background: linear-gradient(135deg, #FF6F00 0%, #FFA040 100%);
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
    padding: 40px 30px;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

.logo {
    text-align: center;
    margin-bottom: 30px;
}

.logo img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 15px;
    box-shadow: 0 4px 15px rgba(255,111,0,0.3);
}

h3 {
    text-align: center;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
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
    padding: 14px 18px;
    margin-bottom: 20px;
    border-radius: 10px;
    border: 2px solid #e0e0e0;
    font-size: 15px;
    transition: all 0.3s;
}

input[type="text"]:focus, input[type="password"]:focus {
    outline: none;
    border-color: #FF6F00;
    box-shadow: 0 0 0 3px rgba(255,111,0,0.1);
}

button {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #FF6F00, #FFA040);
    color: white;
    border: none;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

button:hover { 
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255,111,0,0.4);
}

button:active {
    transform: translateY(0);
}

.alert {
    background: #fff3e0;
    color: #e65100;
    border: 1px solid #ffb74d;
    border-left: 4px solid #ff6f00;
    padding: 14px 18px;
    text-align: center;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    animation: shake 0.5s;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.info-box {
    background: #fff8e1;
    border-left: 4px solid #FF6F00;
    padding: 15px;
    margin-top: 25px;
    border-radius: 8px;
    font-size: 13px;
    color: #555;
    line-height: 1.6;
}

.bi { margin-right: 8px; }

.forgot-password {
    text-align: right;
    margin: -10px 0 20px 0;
}

.forgot-password a {
    color: #FF6F00;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.forgot-password a:hover {
    text-decoration: underline;
}
</style>
</head>

<body>
<div class="card">
    <div class="logo">
        <img src="assets/image.png" alt="CSM Logo">
    </div>
    
    <h3><i class="bi bi-box-seam"></i>CSM Borrowing</h3>
    <p class="subtitle">College of Science and Mathematics</p>

    <?php if ($err): ?>
        <div class="alert"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert"><i class="bi bi-clock-history"></i> Your session has expired. Please login again.</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['security'])): ?>
        <div class="alert"><i class="bi bi-shield-exclamation"></i> Security error detected. Please login again.</div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <label><i class="bi bi-envelope-fill"></i> Email or Username</label>
        <input type="text" name="username" placeholder="Enter your WMSU email or username" required autofocus>

        <label><i class="bi bi-lock-fill"></i> Password</label>
        <input type="password" name="password" placeholder="Enter your password" required>
        
        <div class="forgot-password">
            <a href="forgot_password.php"><i class="bi bi-question-circle"></i> Forgot Password?</a>
        </div>

        <button type="submit" name="login"><i class="bi bi-box-arrow-in-right"></i> Login</button>
    </form>

    <div class="info-box">
        <i class="bi bi-info-circle-fill"></i>
        <strong>Note:</strong> Students with <strong>@wmsu.edu.ph</strong> emails are auto-registered on first login.
    </div>
</div>
</body>
</html>