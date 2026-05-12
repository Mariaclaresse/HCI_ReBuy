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
    
    // Try to send email using Gmail SMTP directly
    $email_sent = false;
    
    // Gmail configuration (update these with your credentials)
    $gmail_username = 'your-email@gmail.com';
    $gmail_password = 'your-app-password'; // Use App Password, not regular password
    
    // Only attempt email if credentials are configured
    if ($gmail_username !== 'your-email@gmail.com' && $gmail_password !== 'your-app-password') {
        try {
            // Create transport
            $transport = (new Swift_SmtpTransport('smtp.gmail.com', 587, 'tls'))
                ->setUsername($gmail_username)
                ->setPassword($gmail_password);
            
            // Create mailer
            $mailer = new Swift_Mailer($transport);
            
            // Create message
            $message = (new Swift_Message('ReBuy - Password Reset OTP'))
                ->setFrom(['noreply@rebuy.com' => 'ReBuy'])
                ->setTo([$email])
                ->setBody('
                <html>
                <head><title>Password Reset OTP</title></head>
                <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
                    <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                        <h2 style="color: #2d5016; text-align: center;">ReBuy Password Reset</h2>
                        <p>Hello,</p>
                        <p>You have requested to reset your password. Use the following One-Time Password (OTP) to proceed:</p>
                        <div style="background-color: #2d5016; color: white; font-size: 24px; font-weight: bold; text-align: center; padding: 20px; border-radius: 5px; margin: 20px 0; letter-spacing: 5px;">
                            ' . $otp . '
                        </div>
                        <p><strong>This OTP will expire in 15 minutes.</strong></p>
                        <p>If you didn\'t request this password reset, please ignore this email.</p>
                    </div>
                </body>
                </html>', 'text/html');
            
            // Send email
            $result = $mailer->send($message);
            $email_sent = $result > 0;
            
        } catch (Exception $e) {
            $email_sent = false;
            error_log("Gmail SMTP Error: " . $e->getMessage());
        }
    }
    
    if ($email_sent) {
        echo json_encode([
            'success' => true, 
            'message' => 'OTP has been sent to your email address',
            'otp' => $otp,
            'debug_mode' => false
        ]);
    } else {
        // Fallback: show OTP on screen
        echo json_encode([
            'success' => true, 
            'message' => 'Email not configured - OTP displayed for testing',
            'otp' => $otp,
            'debug_mode' => true
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
