<?php
echo "<h2>Step-by-Step Gmail SMTP Configuration for XAMPP</h2>";

echo "<h3>Step 1: Configure Gmail Account</h3>";
echo "<ol>";
echo "<li>Go to your Gmail account settings</li>";
echo "<li>Enable 2-factor authentication</li>";
echo "<li>Go to: <a href='https://myaccount.google.com/apppasswords' target='_blank'>https://myaccount.google.com/apppasswords</a></li>";
echo "<li>Generate an App Password for 'Mail' on 'Windows Computer'</li>";
echo "<li>Copy the 16-character password (without spaces)</li>";
echo "</ol>";

echo "<h3>Step 2: Configure php.ini</h3>";
echo "<ol>";
echo "<li>Open XAMPP Control Panel</li>";
echo "<li>Click 'Config' for Apache</li>";
echo "<li>Click 'php.ini'</li>";
echo "<li>Search for '[mail function]' section</li>";
echo "<li>Replace these lines:</li>";
echo "<pre>";
echo "SMTP = localhost
smtp_port = 25
sendmail_from =";
echo "</pre>";
echo "<li>With these lines:</li>";
echo "<pre>";
echo "SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = your-email@gmail.com";
echo "</pre>";
echo "<li>Save and restart Apache</li>";
echo "</ol>";

echo "<h3>Step 3: Alternative - Use send_otp_gmail.php</h3>";
echo "<p>If php.ini configuration doesn't work, I can create a Gmail-specific version that doesn't require php.ini changes.</p>";

echo "<h3>Testing Your Configuration</h3>";
echo "<p>After configuring, test with the forgot password feature.</p>";

// Check current configuration
echo "<h3>Current Configuration Check:</h3>";
echo "<p>SMTP: " . ini_get('SMTP') . "</p>";
echo "<p>SMTP Port: " . ini_get('smtp_port') . "</p>";
echo "<p>Sendmail From: " . ini_get('sendmail_from') . "</p>";

if (ini_get('SMTP') === 'smtp.gmail.com' && ini_get('smtp_port') == '587') {
    echo "<p style='color: green;'>✓ SMTP settings look correct!</p>";
} else {
    echo "<p style='color: orange;'>⚠ SMTP settings need to be updated</p>";
}
?>
