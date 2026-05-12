<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    
    // Check database connection
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Email address not found in our records']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(0, 999999));
    
    // Store OTP in session with expiration time (15 minutes)
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_otp_expires'] = time() + (15 * 60); // 15 minutes
    $_SESSION['reset_user_id'] = $user['id'];
    
    // Try to send email (but don't fail if it doesn't work)
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
            <p>Hello {$user['username']},</p>
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
    
    // For development: show OTP on screen if email fails
    if (!$email_sent) {
        // In development mode, we can show the OTP for testing
        // In production, you might want to log this instead
        error_log("OTP for {$email}: {$otp}");
        
        // Return success anyway since OTP is stored in session
        echo json_encode([
            'success' => true, 
            'message' => 'OTP has been generated. For development, OTP is: ' . $otp,
            'otp' => $otp,
            'debug_mode' => true
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'OTP has been sent to your email address',
            'otp' => $otp,
            'debug_mode' => false
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
