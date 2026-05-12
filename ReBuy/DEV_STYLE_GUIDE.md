# ReBuy Development Style Guide

## Table of Contents
1. [Project Structure](#project-structure)
2. [PHP Coding Standards](#php-coding-standards)
3. [CSS Guidelines](#css-guidelines)
4. [JavaScript Standards](#javascript-standards)
5. [Database Standards](#database-standards)
6. [Security Best Practices](#security-best-practices)
7. [File Naming Conventions](#file-naming-conventions)
8. [Code Organization](#code-organization)

## Project Structure

```
ReBuy/
├── users/
│   ├── php/           # PHP backend files
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   ├── sql/           # Database migration files
│   └── uploads/       # User uploaded files
├── assets/            # Static assets (images, icons)
└── rebuy/            # Main application files
```

## PHP Coding Standards

### 1. File Structure
- Start every PHP file with `<?php`
- Use `session_start()` at the beginning of files that require sessions
- Include required files using `include` or `require_once`
- Always check user authentication before accessing protected content

```php
<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
```

### 2. Database Operations
- Use prepared statements to prevent SQL injection
- Always close prepared statements
- Use meaningful variable names

```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
```

### 3. Error Handling
- Validate user inputs before processing
- Use proper error messages
- Handle file uploads securely

```php
$message = trim($_POST['message'] ?? '');
if (empty($message)) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit();
}
```

### 4. AJAX Responses
- Always set proper content-type headers
- Return JSON responses for AJAX requests
- Use consistent response format

```php
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $result]);
```

## CSS Guidelines

### 1. File Organization
- Use descriptive comments to section styles
- Group related styles together
- Use consistent spacing and indentation

```css
/* Main Container */
.dashboard-container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
}
```

### 2. Naming Conventions
- Use kebab-case for class names
- Use descriptive and semantic class names
- Follow BEM methodology when appropriate

```css
.cart-section
.cart-container
.cart-items
.cart-table
```

### 3. Color Scheme
- Primary colors: Gold (#ffd700, #ffed4e)
- Use consistent colors throughout the application
- Define color variables for maintainability

```css
:root {
    --primary-gold: #ffd700;
    --secondary-gold: #ffed4e;
    --text-dark: #333;
    --background-light: #f5f5f5;
}
```

## JavaScript Standards

### 1. Event Handling
- Use `DOMContentLoaded` for DOM-dependent code
- Use proper event listeners
- Handle errors gracefully

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const notificationDropdown = document.querySelector('.notification-dropdown');
    
    if (notificationDropdown) {
        notificationDropdown.addEventListener('click', function() {
            // Handle click event
        });
    }
});
```

### 2. AJAX Requests
- Use XMLHttpRequest or fetch API
- Handle success and error cases
- Update UI appropriately

```javascript
function markNotificationAsRead(notificationId) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'notification_ajax.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            updateNotificationCount();
        }
    };
    xhr.send('action=mark_read&notification_id=' + notificationId);
}
```

### 3. Code Organization
- Use meaningful function names
- Group related functionality
- Add comments for complex logic

```javascript
// Notification Dropdown Functionality
function updateNotificationCount() {
    // Update notification count in UI
}

// Helper functions
function getNotificationIcon(type) {
    const icons = {
        'promo': 'fas fa-tag',
        'message': 'fas fa-envelope'
    };
    return icons[type] || 'fas fa-bell';
}
```

## Database Standards

### 1. Connection
- Use consistent database connection parameters
- Handle connection errors properly
- Use UTF-8 encoding

```php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rebuy";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
```

### 2. Table Structure
- Use descriptive table and column names
- Include `id` as primary key with auto_increment
- Add `created_at` and `updated_at` timestamps
- Use appropriate data types

### 3. Security
- Always validate inputs
- Use prepared statements
- Implement proper user authentication

## Security Best Practices

### 1. Input Validation
- Sanitize all user inputs
- Use `trim()` for string inputs
- Validate file uploads
- Use `intval()` for numeric inputs

### 2. Session Management
- Start sessions at the beginning of files
- Check user authentication before accessing protected resources
- Use proper session timeout

### 3. File Uploads
- Validate file types and sizes
- Use secure file naming
- Store uploads in dedicated directories
- Set proper file permissions

```php
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (in_array($ext, $allowed)) {
    $new_filename = uniqid() . '.' . $ext;
    // Upload file
}
```

## File Naming Conventions

### PHP Files
- Use lowercase with underscores: `message.php`, `user_profile.php`
- Use descriptive names: `notification_functions.php`, `checkout.php`

### CSS Files
- Use lowercase with underscores: `dashboard.css`, `cart.css`
- Match with corresponding PHP files when applicable

### JavaScript Files
- Use lowercase with underscores: `notification.js`, `register.js`
- Use descriptive names for functionality

### SQL Files
- Use descriptive names with version/date: `MIGRATION_ADD_PROFILE_PIC.sql`
- Use snake_case for clarity: `add_cancelled_at_column.sql`

## Code Organization

### 1. Includes and Requires
- Place database connections at the top
- Include function files before usage
- Use relative paths consistently

### 2. Function Organization
- Group related functions in dedicated files
- Use descriptive function names
- Add function documentation

### 3. Comments and Documentation
- Add file-level comments describing purpose
- Comment complex logic
- Use TODO comments for future improvements

```php
/**
 * Handle message sending via AJAX
 * Validates input and stores message in database
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    // Implementation
}
```

## Best Practices Summary

1. **Always validate user inputs**
2. **Use prepared statements for database queries**
3. **Handle errors gracefully**
4. **Use consistent naming conventions**
5. **Write clean, readable code with proper comments**
6. **Follow security best practices**
7. **Organize files logically**
8. **Test thoroughly before deployment**

## Code Review Checklist

- [ ] Input validation implemented
- [ ] Prepared statements used for database queries
- [ ] Session authentication checked
- [ ] Error handling implemented
- [ ] File upload security measures in place
- [ ] Consistent naming conventions followed
- [ ] Proper comments added
- [ ] Code follows established patterns

---

This style guide should be followed by all developers working on the ReBuy project to ensure consistency, security, and maintainability of the codebase.
