<?php
session_start();
include('includes/db.php');

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm']);

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            $hashed = md5($password);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'student')");

            $stmt->bind_param("sss", $name, $email, $hashed);

            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $name;
                $_SESSION['role'] = 'student';
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - CSM Borrowing System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    margin: 0;
    font-family: Arial, sans-serif;
}
.card {
    width: 380px;
    padding: 25px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
}
.step { display: none; }
.step.active { display: block; }
h3 { font-weight: 700; color: #007bff; }
.text-error {
    color: red;
    font-size: 14px;
    margin-top: -5px;
    margin-bottom: 10px;
}
.btn {
    font-weight: bold;
}
</style>
</head>

<body>
<div class="card">
    <h3 class="text-center mb-3">Student Registration</h3>

    <?php if (!empty($error)): ?>
        <p class="text-error text-center"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" id="registerForm" novalidate>
        <!-- Step 1 -->
        <div class="step active" id="step1">
            <h6 class="fw-bold mb-3 text-secondary">Step 1: Personal Details</h6>
            <div class="mb-3">
                <input type="text" name="name" id="name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="mb-3">
                <input type="email" name="email" id="email" class="form-control" placeholder="Email (e.g. student@csm.edu)" required>
            </div>
            <button type="button" class="btn btn-primary w-100" onclick="nextStep(2)">Next</button>
        </div>

        <!-- Step 2 -->
        <div class="step" id="step2">
            <h6 class="fw-bold mb-3 text-secondary">Step 2: Create Password</h6>
            <div class="mb-2">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
            </div>
            <div class="mb-3">
                <input type="password" name="confirm" id="confirm" class="form-control" placeholder="Confirm Password" required>
                <p id="matchError" class="text-error"></p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary w-50" onclick="nextStep(1)">Back</button>
                <button type="button" class="btn btn-primary w-50" onclick="nextStep(3)">Next</button>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="step" id="step3">
            <h6 class="fw-bold mb-3 text-secondary">Step 3: Confirm Details</h6>
            <p><strong>Name:</strong> <span id="confirmName"></span></p>
            <p><strong>Email:</strong> <span id="confirmEmail"></span></p>
            <p><strong>Password:</strong> <span id="confirmPassword"></span></p>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary w-50" onclick="nextStep(2)">Back</button>
                <button type="submit" name="final_submit" class="btn btn-success w-50">Register</button>
            </div>
        </div>
    </form>

    <div class="text-center mt-3">
        <a href="index.php" class="text-decoration-none small">Already have an account? Login here</a>
    </div>
</div>

<script>
function nextStep(step) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
    const confirm = document.getElementById('confirm').value.trim();
    const matchError = document.getElementById('matchError');

    // Password validation (real-time)
    if (step === 3) {
        if (!password || !confirm) {
            matchError.textContent = "Please fill in both password fields.";
            return;
        } else if (password !== confirm) {
            matchError.textContent = "Passwords do not match.";
            return;
        } else {
            matchError.textContent = "";
        }
    }

    if (step === 2 && (!name || !email)) {
        alert("Please fill in all fields before proceeding.");
        return;
    }

    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + step).classList.add('active');

    if (step === 3) {
        document.getElementById('confirmName').textContent = name;
        document.getElementById('confirmEmail').textContent = email;
        document.getElementById('confirmPassword').textContent = '*'.repeat(password.length);
    }
}

// Realtime password match check
document.getElementById('confirm').addEventListener('input', function() {
    const pass = document.getElementById('password').value.trim();
    const conf = this.value.trim();
    const matchError = document.getElementById('matchError');

    if (!conf) {
        matchError.textContent = "";
    } else if (pass !== conf) {
        matchError.textContent = "Passwords do not match.";
    } else {
        matchError.textContent = "";
    }
});
</script>

</body>
</html>
