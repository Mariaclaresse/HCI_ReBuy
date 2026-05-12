<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
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
    
    // Try to send email using PHP mail with proper headers
    $to = $email;
    $subject = "ReBuy - Password Reset OTP";
    
    $message = "
    <html>
    <head>
        <title>Password Reset OTP</title>
    </head>
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
            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='font-size: 12px; color: #666; text-align: center;'>This is an automated message from ReBuy. Please do not reply to this email.</p>
        </div>
    </body>
    </html>
    ";
    
    // Set headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@rebuy.com" . "\r\n";
    
    // Try to send email
    $email_sent = mail($to, $subject, $message, $headers);
    
    if ($email_sent) {
        echo json_encode([
            'success' => true, 
            'message' => 'OTP has been sent to your email address',
            'otp' => $otp,
            'debug_mode' => false
        ]);
    } else {
        // Fallback: show OTP on screen with clear instructions
        echo json_encode([
            'success' => true, 
            'message' => 'Email not configured - OTP displayed below',
            'otp' => $otp,
            'debug_mode' => true,
            'instructions' => 'To enable email sending, configure XAMPP SMTP settings or use development mode'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
