<?php
session_start();
include 'db.php';

$error = "";
$success = "";

// Check if OTP session exists
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_otp'])) {
    header("Location: login.php");
    exit;
}

// Check if OTP has expired
if (time() > $_SESSION['reset_otp_expires']) {
    unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expires'], $_SESSION['reset_user_id']);
    $error = "OTP has expired. Please request a new one.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['verify_otp'])) {
        $entered_otp = $_POST['otp'] ?? '';
        
        if (empty($entered_otp)) {
            $error = "Please enter the OTP";
        } elseif ($entered_otp !== $_SESSION['reset_otp']) {
            $error = "Invalid OTP. Please try again.";
        } elseif (time() > $_SESSION['reset_otp_expires']) {
            $error = "OTP has expired. Please request a new one.";
            unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expires'], $_SESSION['reset_user_id']);
        } else {
            // OTP is valid, redirect to reset password page
            header("Location: reset_password.php");
            exit;
        }
    } elseif (isset($_POST['resend_otp'])) {
        // Resend OTP
        header("Location: login.php?resend_otp=" . urlencode($_SESSION['reset_email']));
        exit;
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

        .otp-display {
            background: #f8f9fa;
            border: 2px dashed #2d5016;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .otp-display i {
            font-size: 48px;
            color: #2d5016;
            margin-bottom: 10px;
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
            font-size: 18px;
            text-align: center;
            letter-spacing: 8px;
            background: rgba(255,255,255,0.9);
            transition: 0.3s;
        }

        .input-group input:focus {
            border-color: #2d5016;
            box-shadow: 0 0 0 3px rgba(45,80,22,0.1);
            outline: none;
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

        .verify-btn {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #2d5016, #1a3009);
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-bottom: 10px;
        }

        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(45,80,22,0.3);
        }

        .resend-btn {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #2d5016;
            background: transparent;
            color: #2d5016;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .resend-btn:hover {
            background: #2d5016;
            color: white;
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
        <h2>Verify OTP</h2>
        <p>Enter the 6-digit code sent to your email</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="otp-display">
        <i class="fas fa-envelope"></i>
        <p>OTP sent to: <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong></p>
        <?php if (isset($_SESSION['reset_otp'])): ?>
        <div style="background: #2d5016; color: white; font-size: 20px; font-weight: bold; padding: 10px; border-radius: 5px; margin: 10px 0; letter-spacing: 3px;">
            Your OTP: <?php echo htmlspecialchars($_SESSION['reset_otp']); ?>
        </div>
        <p style="font-size: 12px; color: #666; margin-top: 5px;">You can use this OTP to proceed</p>
        <?php endif; ?>
    </div>

    <form method="POST">
        <div class="input-group">
            <i class="fas fa-key left-icon"></i>
            <input type="text" name="otp" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
        </div>

        <button type="submit" name="verify_otp" class="verify-btn">Verify OTP</button>
        <button type="submit" name="resend_otp" class="resend-btn">Resend OTP</button>
    </form>

    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>

</div>

</body>
</html>
