<?php
session_start();
include 'db.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Check if user is a seller
    $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
    if ($seller_check->num_rows > 0) {
        $seller_stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
        $seller_stmt->bind_param("i", $_SESSION['user_id']);
        $seller_stmt->execute();
        $seller_result = $seller_stmt->get_result();
        $seller_data = $seller_result->fetch_assoc();
        $seller_stmt->close();

        if (isset($seller_data['is_seller']) && $seller_data['is_seller'] == 1) {
            header("Location: seller_profile.php");
            exit();
        }
    }
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

// Check if user just registered and show success message
if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
    $success = "Account created successfully! Please login with your credentials.";
}

// Initialize login attempt tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

// Check if user is locked out
$max_attempts = 3;
$lockout_time = 30; // 30 seconds in seconds

if ($_SESSION['login_attempts'] >= $max_attempts) {
    $time_remaining = $lockout_time - (time() - $_SESSION['last_attempt_time']);
    if ($time_remaining > 0) {
        $error = "Too many failed attempts. Please try again in {$time_remaining} seconds.";
    } else {
        // Reset attempts after lockout period
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check if still locked out
    if ($_SESSION['login_attempts'] >= $max_attempts) {
        $time_remaining = $lockout_time - (time() - $_SESSION['last_attempt_time']);
        if ($time_remaining > 0) {
            $error = "Too many failed attempts. Please try again in {$time_remaining} seconds.";
        } else {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = 0;
        }
    }

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields!";
    } elseif ($_SESSION['login_attempts'] < $max_attempts) {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['password'])) {
                // Successful login - reset attempts and clear registration flag
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = 0;
                unset($_SESSION['just_registered']); // Clear registration flag
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $success = "Login successful! Welcome, " . $_SESSION['username'];

                // Check if user is a seller and redirect accordingly
                $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
                if ($seller_check->num_rows > 0) {
                    $seller_stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
                    $seller_stmt->bind_param("i", $row['id']);
                    $seller_stmt->execute();
                    $seller_result = $seller_stmt->get_result();
                    $seller_data = $seller_result->fetch_assoc();
                    $seller_stmt->close();

                    if (isset($seller_data['is_seller']) && $seller_data['is_seller'] == 1) {
                        // Redirect seller to their store profile
                        header("Location: seller_profile.php");
                        exit();
                    }
                }

                // Redirect regular user to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // Failed login - increment attempts (password incorrect)
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $remaining_attempts = $max_attempts - $_SESSION['login_attempts'];
                $error = "Incorrect password! Attempts remaining: {$remaining_attempts}";
            }
        } else {
            // Failed login - increment attempts (username not found)
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $remaining_attempts = $max_attempts - $_SESSION['login_attempts'];
            $error = "Username not found! Attempts remaining: {$remaining_attempts}";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ReBuy</title>
<link rel="icon" type="image/x-icon" href="../../assets/logo.png">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: url('../../assets/b-g.jpg') no-repeat center left;
    background-size: cover;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 20px;
}

/* CARD */
.login-card {
    width: 410px;
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(15px);
    border-radius: 25px;
    padding: 50px 40px;
    margin-right: 220px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.2);
    animation: fadeIn 0.6s ease;
}

@keyframes fadeIn {
    from {opacity:0; transform:translateY(30px);}
    to {opacity:1; transform:translateY(0);}
}

/* HEADER */
.login-header h2 {
    font-size: 30px;
    font-weight: 750;
    color: #2d5016;
}

.login-header p {
    font-size: 16px;
    font-weight: 600;
    color: #3d3d3d;
    margin-bottom: 30px;
    margin-top: 5px;
}

/* INPUT GROUP */
.input-group {
    position: relative;
    margin-bottom: 22px;
}

.input-group input {
    width: 100%;
    padding: 13px 40px;
    border-radius: 10px;
    border: 1.5px solid #b0b0b0;
    font-size: 14px;
    background: rgba(255,255,255,0.9);
    transition: 0.3s;
}

.input-group input:focus {
    border-color: #2d5016;
    box-shadow: 0 0 0 3px rgba(45,80,22,0.1);
    outline: none;
}

/* ICONS */
.input-group i {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: #b0b0b0;
}

.left-icon {
    left: 12px;
}

.right-icon {
    right: 12px;
    cursor: pointer;
}

/* FLOAT LABEL */
.input-group label {
    position: absolute;
    left: 40px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    color: #676767;
    pointer-events: none;
    transition: 0.3s;
}

.input-group input:focus + label,
.input-group input:not(:placeholder-shown) + label {
    top: -6px;
    font-size: 14px;
    color: #2d5016;
    background: white;
    padding: 0 5px;
}

/* OPTIONS */
.form-options {
    display: flex;
    justify-content: flex-end;
    font-size: 14px;
    margin-bottom: 20px;
}

/* LINK STYLE (NO UNDERLINE) */
.forgot-password,
.signup-link a {
    text-decoration: none;
    color: #2d5016;
    font-weight: 600;
    transition: all 0.3s ease;
}

.forgot-password:hover,
.signup-link a:hover {
    color: #f4c430;
    transform: translateY(-1px);
}

/* BUTTON */
.login-btn {
    width: 100%;
    padding: 14px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #2d5016, #1a3009);
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 10px;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(45,80,22,0.3);
}

/* ERROR */
.error-message {
    background: #ffe5e5;
    color: #c33;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 13px;
}

/* SUCCESS */
.success-message {
    background: #e5ffe5;
    color: #3c3;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 13px;
}

/* ATTEMPT COUNTER */
.attempt-counter {
    background: #fff3cd;
    color: #856404;
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 12px;
    text-align: center;
    border: 1px solid #ffeaa7;
}

.attempt-counter i {
    margin-right: 5px;
}

/* COUNTDOWN TIMER */
.countdown-timer {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 14px;
    text-align: center;
    border: 1px solid #f5c6cb;
    font-weight: bold;
}

.countdown-timer .timer-display {
    font-size: 18px;
    color: #dc3545;
    font-weight: bold;
    margin-top: 5px;
}

.countdown-timer i {
    margin-right: 5px;
}

/* SIGNUP */
.signup-link {
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
}

/* MODAL */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    margin: 10% auto;
    border-radius: 25px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.3);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {transform: translateY(-50px); opacity:0;}
    to {transform: translateY(0); opacity:1;}
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px 15px;
    border-bottom: 1px solid #e0e0e0;
}

.modal-header h3 {
    margin: 0;
    color: #2d5016;
    font-size: 22px;
    font-weight: 700;
}

.close {
    color: #999;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s;
}

.close:hover {
    color: #2d5016;
}

.modal-body {
    padding: 20px 30px 30px;
}

.modal-body p {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.5;
}

.reset-btn {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #2d5016, #1a3009);
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 10px;
}

.reset-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(45,80,22,0.3);
}

.reset-message {
    margin-top: 15px;
    padding: 10px;
    border-radius: 6px;
    font-size: 13px;
    display: none;
}

.reset-message.success {
    background: #e5ffe5;
    color: #3c3;
    display: block;
}

.reset-message.error {
    background: #ffe5e5;
    color: #c33;
    display: block;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    body {
        justify-content: center;
        background-position: center;
    }

    .login-card {
        margin-right: 0;
        width: 100%;
        max-width: 400px;
    }
}
</style>
</head>

<body>

<div class="login-card">

    <div class="login-header">
        <h2>Welcome to ReBuy</h2>
        <p>Login to continue</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

        <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $max_attempts): ?>
        <div class="countdown-timer" id="countdownTimer">
            <i class="fas fa-clock"></i>
            Account temporarily locked
            <div class="timer-display" id="timerDisplay">30</div>
        </div>
        <?php endif; ?>

    <form method="POST">

        <!-- USERNAME -->
        <div class="input-group">
            <i class="fas fa-user left-icon"></i>
            <input type="text" name="username" placeholder=" " required>
            <label>Username</label>
        </div>

        <!-- PASSWORD -->
        <div class="input-group">
            <i class="fas fa-lock left-icon"></i>
            <input type="password" id="password" name="password" placeholder=" " required>
            <label>Password</label>
            <i class="fas fa-eye right-icon" onclick="togglePassword()"></i>
        </div>

        <div class="form-options">
            <a href="#" class="forgot-password" onclick="showForgotPasswordModal(event)">Forgot Password?</a>
        </div>

        <button class="login-btn" <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $max_attempts) echo 'disabled'; ?>>Sign In</button>
    </form>

    <div class="signup-link">
        Don't have an account? <a href="register.php">Sign up</a>
    </div>

</div>

<!-- Forgot Password Modal -->
<div id="forgotPasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reset Password</h3>
            <span class="close" onclick="closeForgotPasswordModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Enter your email address and we'll send you instructions to reset your password.</p>
            <form id="forgotPasswordForm">
                <div class="input-group">
                    <i class="fas fa-envelope left-icon"></i>
                    <input type="email" name="email" placeholder=" " required
                           pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                           title="Please enter a valid email address (e.g., user@example.com)">
                    <label>Email Address</label>
                </div>
                <button type="submit" class="reset-btn">Send Reset Link</button>
            </form>
            <div id="resetMessage" class="reset-message"></div>
        </div>
    </div>
</div>

<script>
// Check if user is already logged in (for cached pages)
document.addEventListener('DOMContentLoaded', function() {
    fetch('check_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.logged_in) {
                if (data.is_seller) {
                    window.location.href = 'seller_profile.php';
                } else {
                    window.location.href = 'dashboard.php';
                }
            }
        })
        .catch(error => console.error('Error checking session:', error));
});

// Prevent back navigation
history.pushState(null, null, location.href);
window.onpopstate = function() {
    history.go(1);
};

function togglePassword() {
    const input = document.getElementById("password");
    const icon = document.querySelector(".right-icon");

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

// Forgot Password Modal Functions
function showForgotPasswordModal(event) {
    event.preventDefault();
    document.getElementById('forgotPasswordModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scroll
    
    // Check if there's an email to resend OTP to
    const urlParams = new URLSearchParams(window.location.search);
    const resendEmail = urlParams.get('resend_otp');
    if (resendEmail) {
        document.querySelector('input[name="email"]').value = decodeURIComponent(resendEmail);
        // Clear the URL parameter
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

function closeForgotPasswordModal() {
    document.getElementById('forgotPasswordModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore background scroll
    document.getElementById('resetMessage').className = 'reset-message';
    document.getElementById('resetMessage').textContent = '';
    document.getElementById('forgotPasswordForm').reset();
}

// Countdown Timer Function
function startCountdown() {
    const timerDisplay = document.getElementById('timerDisplay');
    const countdownTimer = document.getElementById('countdownTimer');
    const loginBtn = document.querySelector('.login-btn');
    
    if (!timerDisplay || !countdownTimer) return;
    
    let timeLeft = 30;
    
    const countdown = setInterval(function() {
        timeLeft--;
        timerDisplay.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(countdown);
            // Hide countdown timer and enable login button
            countdownTimer.style.display = 'none';
            loginBtn.disabled = false;
            // Reload page to reset session
            window.location.reload();
        }
    }, 1000);
}

// Handle forgot password form submission
document.addEventListener('DOMContentLoaded', function() {
    // Start countdown if timer is visible
    const countdownTimer = document.getElementById('countdownTimer');
    if (countdownTimer) {
        startCountdown();
    }
    
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[name="email"]').value;
            const messageDiv = document.getElementById('resetMessage');
            const submitBtn = this.querySelector('.reset-btn');
            
            // Validate email
            if (!email) {
                messageDiv.className = 'reset-message error';
                messageDiv.textContent = 'Please enter your email address.';
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            messageDiv.className = 'reset-message';
            messageDiv.textContent = 'Sending OTP to your email...';
            
            // Send AJAX request to send_otp_simple.php for testing
            fetch('send_otp_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.success) {
                    messageDiv.className = 'reset-message success';
                    messageDiv.textContent = data.message;
                    
                    // If in debug mode, show the OTP prominently
                    if (data.debug_mode && data.otp) {
                        messageDiv.innerHTML = data.message + '<br><strong style="color: #2d5016; font-size: 16px;">Use this OTP to proceed: ' + data.otp + '</strong>';
                    } else if (!data.debug_mode && data.otp) {
                        // Even in production mode, show OTP for development
                        messageDiv.innerHTML = data.message + '<br><small style="color: #666;">Development OTP: ' + data.otp + '</small>';
                    }
                    
                    // Clear form after successful submission
                    this.reset();
                    
                    // Redirect to OTP verification page after 3 seconds (longer for debug mode)
                    setTimeout(function() {
                        window.location.href = 'verify_otp.php';
                    }, data.debug_mode ? 5000 : 2000);
                } else {
                    messageDiv.className = 'reset-message error';
                    messageDiv.textContent = data.message;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                messageDiv.className = 'reset-message error';
                messageDiv.textContent = 'Failed to send OTP. Please try again. Error: ' + error.message;
            })
            .finally(() => {
                // Restore button state
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Reset Link';
            });
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('forgotPasswordModal');
        if (event.target === modal) {
            closeForgotPasswordModal();
        }
    });
    
    // Close modal when pressing Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeForgotPasswordModal();
        }
    });
});
</script>

</body>
</html>