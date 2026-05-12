<?php
session_start();
include 'db.php';

$error = "";
$success = "";

// Check if user is verified via OTP
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } else {
        // Update password in database
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $user_id = $_SESSION['reset_user_id'];
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success = "Password has been reset successfully!";
            
            // Clear session variables
            unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expires'], $_SESSION['reset_user_id']);
            
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error = "Failed to reset password. Please try again.";
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

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            font-size: 28px;
            font-weight: 750;
            color: #2d5016;
            margin-bottom: 10px;
        }

        .login-header p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .success-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon i {
            font-size: 48px;
            color: #4CAF50;
        }

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

        .error-message {
            background: #ffe5e5;
            color: #c33;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .success-message {
            background: #e5ffe5;
            color: #3c3;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .reset-btn {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-bottom: 10px;
        }

        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76,175,80,0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #2d5016;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #f4c430;
        }

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
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2>Reset Password</h2>
        <p>Enter your new password</p>
    </div>

    <?php if (isset($_SESSION['reset_otp'])): ?>
    <div style="background: #f8f9fa; border: 2px dashed #2d5016; border-radius: 10px; padding: 15px; text-align: center; margin-bottom: 20px;">
        <p style="font-size: 14px; color: #666; margin-bottom: 10px;">Your verification OTP:</p>
        <div style="background: #2d5016; color: white; font-size: 20px; font-weight: bold; padding: 10px; border-radius: 5px; letter-spacing: 3px;">
            <?php echo htmlspecialchars($_SESSION['reset_otp']); ?>
        </div>
        <p style="font-size: 12px; color: #666; margin-top: 5px;">Keep this OTP for your reference</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
            <br><small>Redirecting to login page...</small>
        </div>
    <?php else: ?>
        <form method="POST">
            <div class="input-group">
                <i class="fas fa-lock left-icon"></i>
                <input type="password" id="password" name="password" placeholder=" " minlength="8" required>
                <label>New Password</label>
                <i class="fas fa-eye right-icon" onclick="togglePassword()"></i>
            </div>

            <div class="input-group">
                <i class="fas fa-lock left-icon"></i>
                <input type="password" id="confirm_password" name="confirm_password" placeholder=" " minlength="8" required>
                <label>Confirm New Password</label>
                <i class="fas fa-eye right-icon" onclick="toggleConfirmPassword()"></i>
            </div>

            <button type="submit" class="reset-btn">Reset Password</button>
        </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById("password");
    const icon = input.nextElementSibling;
    
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

function toggleConfirmPassword() {
    const input = document.getElementById("confirm_password");
    const icon = input.nextElementSibling;
    
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
</script>

</body>
</html>
