<?php
// Email Configuration Instructions for XAMPP
// This file shows how to configure email sending in XAMPP

/*
OPTION 1: Configure PHP mail() in XAMPP
=====================================

1. Open XAMPP Control Panel
2. Click "Config" for Apache
3. Select "php.ini"
4. Find and modify these settings:

[mail function]
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = your-email@gmail.com

5. Also find and uncomment:
sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"

6. Open C:\xampp\sendmail\sendmail.ini
7. Configure these settings:

smtp_server=smtp.gmail.com
smtp_port=587
smtp_ssl=auto
auth_username=your-email@gmail.com
auth_password=your-app-password
pop3_server=
pop3_username=
pop3_password=

force_sender=your-email@gmail.com

NOTE: For Gmail, you need to:
- Enable 2-factor authentication
- Generate an App Password (not your regular password)
- Go to: https://myaccount.google.com/apppasswords

*/

echo "<h2>Email Configuration for XAMPP</h2>";
echo "<h3>Option 1: PHP mail() Configuration</h3>";
echo "<p>Follow the instructions in the comments above to configure XAMPP to send emails.</p>";
echo "<h3>Option 2: Use PHPMailer (Recommended)</h3>";
echo "<p>Download PHPMailer and use the send_otp_phpmailer.php file instead.</p>";
echo "<h3>Option 3: Use Development Mode</h3>";
echo "<p>Currently using send_otp_simple.php which shows OTP on screen for testing.</p>";

// Test email configuration
if (function_exists('mail')) {
    echo "<p style='color: green;'>✓ mail() function is available</p>";
    
    // Test sending email
    $test_sent = mail('test@example.com', 'Test Subject', 'Test Message');
    if ($test_sent) {
        echo "<p style='color: green;'>✓ Test email sent successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Test email failed - configure SMTP settings</p>";
    }
} else {
    echo "<p style='color: red;'>✗ mail() function is not available</p>";
}
?>
