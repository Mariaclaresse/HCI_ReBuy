<?php
session_start();
// Include PHPMailer (you need to download this first)
// Download from: https://github.com/PHPMailer/PHPMailer
// Extract to: vendor/PHPMailer/

// Uncomment these lines after downloading PHPMailer
/*
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';
*/

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }
    
    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(0, 999999));
    
    // Store OTP in session
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_otp_expires'] = time() + (15 * 60);
    $_SESSION['reset_user_id'] = 1; // For testing
    
    // Try to send email with PHPMailer
    $email_sent = false;
    
    /*
    // Uncomment this section after downloading PHPMailer
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;                    // Disable verbose debug output
        $mail->isSMTP();                                        // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                   // Set the SMTP server
        $mail->SMTPAuth   = true;                               // Enable SMTP authentication
        $mail->Username   = 'your-email@gmail.com';             // SMTP username
        $mail->Password   = 'your-app-password';                // SMTP password (App Password for Gmail)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;     // Enable TLS encryption
        $mail->Port       = 587;                                // TCP port
        
        // Recipients
        $mail->setFrom('noreply@rebuy.com', 'ReBuy');
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'ReBuy - Password Reset OTP';
        $mail->Body    = "
        <html>
        <head><title>Password Reset OTP</title></head>
        <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
                <h2 style='color: #2d5016; text-align: center;'>ReBuy Password Reset</h2>
                <p>Hello,</p>
                <p>You have requested to reset your password. Use the following One-Time Password (OTP) to proceed:</p>
                <div style='background-color: #2d5016; color: white; font-size: 24px; font-weight: bold; text-align: center; padding: 20px; border-radius: 5px; margin: 20px 0; letter-spacing: 5px;'>
                    {$otp}
                </div>
                <p><strong>This OTP will expire in 15 minutes.</strong></p>
                <p>If you didn't request this password reset, please ignore this email.</p>
            </div>
        </body>
        </html>";
        
        $mail->send();
        $email_sent = true;
        
    } catch (Exception $e) {
        $email_sent = false;
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
    }
    */
    
    // For now, simulate email sending
    $email_sent = false;
    
    if ($email_sent) {
        echo json_encode([
            'success' => true, 
            'message' => 'OTP has been sent to your email address',
            'otp' => $otp,
            'debug_mode' => false
        ]);
    } else {
        // Fallback to showing OTP on screen
        echo json_encode([
            'success' => true, 
            'message' => 'OTP generated (email not configured - showing on screen)',
            'otp' => $otp,
            'debug_mode' => true
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
