<?php
session_start();
include 'db.php';

$error = "";
$success = "";

// Check if user just created an account and hasn't logged out yet
if (isset($_SESSION['just_registered']) && $_SESSION['just_registered'] === true) {
    // Redirect to login page with a message
    header("Location: login.php?registered=true");
    exit();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $age = $_POST['age'] ?? '';
    $purok_street = $_POST['purok_street'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $municipality_city = $_POST['municipality_city'] ?? '';
    $province = $_POST['province'] ?? '';
    $country = $_POST['country'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
        $error = "All required fields must be filled!";
    } elseif (!preg_match('/^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/', $first_name)) {
        $error = "First Name must start with a capital letter followed by lowercase letters (spaces allowed between words)!";
    } elseif (!preg_match('/^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/', $last_name)) {
        $error = "Last Name must start with a capital letter followed by lowercase letters (spaces allowed between words)!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
        $error = "Only Gmail email addresses are allowed (e.g., user@gmail.com)!";
    } elseif (strlen($username) !== 6) {
        $error = "Username must be exactly 6 characters!";
    } elseif (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?])[a-zA-Z\d!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]{8,10}$/', $password)) {
        $error = "Password must be 8-10 characters with mix of letters, numbers, and special characters!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (!preg_match('/^\d+$/', $zip_code)) {
        $error = "Zip Code must contain numbers only!";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($stmt) {
                $stmt->close();
            }

            if ($result->num_rows > 0) {
                $error = "Username already exists!";
            } else {
                // Check if email exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if ($stmt === false) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($stmt) {
                        $stmt->close();
                    }

                    if ($result->num_rows > 0) {
                        $error = "Email already exists!";
                    } else {
                        // Insert new user
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, gender, birthdate, age, purok_street, barangay, municipality_city, province, country, zip_code, username, email, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        if ($stmt === false) {
                            $error = "Database error: " . $conn->error;
                        } else {
                            $stmt->bind_param("ssssssssssssss", $first_name, $last_name, $gender, $birthdate, $age, $purok_street, $barangay, $municipality_city, $province, $country, $zip_code, $username, $email, $hashed_password);
                            
                            if ($stmt->execute()) {
                                // Set flag to prevent immediate re-registration
                                $_SESSION['just_registered'] = true;
                                header("Location: login.php?registered=true");
                                exit;
                            } else {
                                $error = "Error creating account: " . $stmt->error;
                            }
                            if ($stmt) {
                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
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
            width: 680px;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            padding: 50px 40px;
            margin-right: 70px;
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
            font-size: 15px;
            font-weight: 600;
            color: #5d5d5d;
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

        .input-group select {
            width: 100%;
            padding: 13px 40px;
            border-radius: 10px;
            border: 1.5px solid #b0b0b0;
            font-size: 14px;
            background: rgba(255,255,255,0.9);
            transition: 0.3s;
            appearance: none;
            cursor: pointer;
        }

        .input-group input:focus, .input-group select:focus {
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
        .input-group input:not(:placeholder-shown) + label,
        .input-group select:focus + label,
        .input-group select:valid + label {
            top: -6px;
            font-size: 14px;
            color: #2d5016;
            background: white;
            padding: 0 5px;
        }

        /* Ensure select element placeholder behavior */
        .input-group select option[value=""] {
            display: none;
        }

        /* Fix select floating label */
        .input-group select:not(:valid) + label {
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #676767;
            background: transparent;
            padding: 0;
        }

        /* FORM ROW */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 22px;
        }

        .form-row .input-group {
            margin-bottom: 0;
        }

        /* BUTTON */
        .register-btn {
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

        .register-btn:hover {
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

        /* REAL-TIME VALIDATION STYLES */
        .input-group input.error {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .input-group input.valid {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .field-error {
            color: #dc3545;
            font-size: 11px;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }

        .field-error.show {
            display: block;
        }

        /* REQUIRED ASTERISK */
        .required-asterisk {
            color: #dc3545;
            font-weight: bold;
            margin-left: 3px;
            transition: opacity 0.3s;
        }

        .required-asterisk.hidden {
            opacity: 0;
            display: none;
        }

        /* DIVIDER */
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #999;
            font-size: 12px;
        }

        /* LINK STYLE (NO UNDERLINE) */
        .login-link a {
            text-decoration: none;
            color: #2d5016;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: #f4c430;
            transform: translateY(-1px);
        }

        .signup-link {
            text-align: center;
            margin-top: 40px;
        }

        .signup-link a {
            text-decoration: none;
            color: #2d5016;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .signup-link a:hover {
            color: #f4c430;
            transform: translateY(-1px);
        }

        /* STEP NAVIGATION */
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            display: flex;
            align-items: center;
            margin: 0 10px;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
        }

        .step.active .step-number {
            background: #2d5016;
            color: white;
        }

        .step.completed .step-number {
            background: #4CAF50;
            color: white;
        }

        .step-line {
            width: 40px;
            height: 2px;
            background: #e0e0e0;
            margin: 0 5px;
        }

        .step.completed + .step-line {
            background: #4CAF50;
        }

        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        .step-title {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #2d5016;
            margin-bottom: 20px;
        }

        .step-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .step-buttons:has(button:only-child) {
            justify-content: flex-end;
        }

        .step-btn {
            padding: 10px 50px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .step-btn.prev {
            background: #e0e0e0;
            color: #666;
        }

        .step-btn.prev:hover {
            background: #d0d0d0;
        }

        .step-btn.next {
            background: linear-gradient(135deg, #2d5016, #1a3009);
            color: white;
        }

        .step-btn.next:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(45,80,22,0.3);
        }

        .step-btn.submit {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .step-btn.submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(76,175,80,0.3);
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
                padding: 40px 30px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-row .input-group {
                margin-bottom: 22px;
            }
        }
    </style>
</head>
<body>

<div class="login-card">

    <div class="login-header">
        <h2>Create Account</h2>
        <p>Join ReBuy today</p>
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

    <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="step-number">1</div>
            </div>
            <div class="step-line"></div>
            <div class="step" id="step2">
                <div class="step-number">2</div>
            </div>
            <div class="step-line"></div>
            <div class="step" id="step3">
                <div class="step-number">3</div>
            </div>
        </div>

        <form method="POST" id="registrationForm">

            <!-- Step 1: Personal Information -->
            <div class="step-content active" id="step1-content">
                <div class="step-title">Personal Information</div>
                
                <!-- NAME ROW -->
        <div class="form-row">
            <div class="input-group">
                <i class="fas fa-user left-icon"></i>
                <input type="text" name="first_name" placeholder=" " required>
                <label>First Name<span class="required-asterisk">*</span></label>
                <div class="field-error" id="first_name_error"></div>
            </div>
            <div class="input-group">
                <i class="fas fa-user left-icon"></i>
                <input type="text" name="last_name" placeholder=" " required>
                <label>Last Name<span class="required-asterisk">*</span></label>
                <div class="field-error" id="last_name_error"></div>
            </div>
        </div>

        <!-- PERSONAL INFO ROW -->
        <div class="form-row">
            <div class="input-group">
    <i class="fas fa-venus-mars left-icon"></i>

    <select name="gender" required>
        <option value="" selected disabled hidden></option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>
    </select>

    <label>Select Gender<span class="required-asterisk">*</span></label>
</div>
            <div class="input-group">
                <i class="fas fa-calendar left-icon"></i>
                <input type="date" name="birthdate" placeholder=" " required>
                <label>Birthdate<span class="required-asterisk">*</span></label>
            </div>
        </div>

        <!-- AGE -->
        <div class="input-group">
            <i class="fas fa-hourglass-half left-icon"></i>
            <input type="number" name="age" placeholder=" " min="1" max="120" required>
            <label>Age<span class="required-asterisk">*</span></label>
        </div>

        <div class="step-buttons">
            <button type="button" class="step-btn next" onclick="nextStep(1)">Next</button>
        </div>
    </div>

    <!-- Step 2: Address Information -->
    <div class="step-content" id="step2-content">
        <div class="step-title">Address Information</div>
        
        <!-- ADDRESS ROW 1 -->
        <div class="form-row">
            <div class="input-group">
                <i class="fas fa-home left-icon"></i>
                <input type="text" name="purok_street" placeholder=" " required>
                <label>Purok/Street<span class="required-asterisk">*</span></label>
            </div>
            <div class="input-group">
                <i class="fas fa-map-marker-alt left-icon"></i>
                <input type="text" name="barangay" placeholder=" " required>
                <label>Barangay<span class="required-asterisk">*</span></label>
            </div>
        </div>

        <!-- ADDRESS ROW 2 -->
        <div class="form-row">
            <div class="input-group">
                <i class="fas fa-city left-icon"></i>
                <input type="text" name="municipality_city" placeholder=" " required>
                <label>Municipality/City<span class="required-asterisk">*</span></label>
            </div>
            <div class="input-group">
                <i class="fas fa-map left-icon"></i>
                <input type="text" name="province" placeholder=" " required>
                <label>Province<span class="required-asterisk">*</span></label>
            </div>
        </div>

        <!-- ADDRESS ROW 3 -->
        <div class="form-row">
            <div class="input-group">
                <i class="fas fa-globe left-icon"></i>
                <input type="text" name="country" placeholder=" " required>
                <label>Country<span class="required-asterisk">*</span></label>
            </div>
            <div class="input-group">
                <i class="fas fa-mail-bulk left-icon"></i>
                <input type="text" name="zip_code" placeholder=" " maxlength="10" required>
                <label>Zip Code<span class="required-asterisk">*</span></label>
                <div class="field-error" id="zip_code_error"></div>
            </div>
        </div>

        <div class="step-buttons">
            <button type="button" class="step-btn prev" onclick="prevStep(2)">Previous</button>
            <button type="button" class="step-btn next" onclick="nextStep(2)">Next</button>
        </div>
    </div>

    <!-- Step 3: Account Information -->
    <div class="step-content" id="step3-content">
        <div class="step-title">Account Information</div>
        
        <!-- EMAIL -->
        <div class="input-group">
            <i class="fas fa-envelope left-icon"></i>
            <input type="email" name="email" placeholder=" " required 
                   pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                   title="Please enter a valid email address (e.g., user@example.com)">
            <label>Email<span class="required-asterisk">*</span></label>
            <div class="field-error" id="email_error"></div>
        </div>

        <!-- USERNAME -->
        <div class="input-group">
            <i class="fas fa-user left-icon"></i>
            <input type="text" name="username" placeholder=" " minlength="6" maxlength="6" required>
            <label>Username<span class="required-asterisk">*</span></label>
            <div class="field-error" id="username_error"></div>
        </div>

        <!-- PASSWORD ROW -->
        <div class="form-row">
            <div class="input-group">
                <i class="fas fa-lock left-icon"></i>
                <input type="password" id="password" name="password" placeholder=" " minlength="8" maxlength="10" required>
                <label>Password<span class="required-asterisk">*</span></label>
                <i class="fas fa-eye right-icon" onclick="togglePassword()"></i>
                <div class="field-error" id="password_error"></div>
            </div>
            <div class="input-group">
                <i class="fas fa-lock left-icon"></i>
                <input type="password" id="confirm_password" name="confirm_password" placeholder=" " minlength="8" maxlength="10" required>
                <label>Confirm Password<span class="required-asterisk">*</span></label>
                <i class="fas fa-eye right-icon" onclick="toggleConfirmPassword()"></i>
                <div class="field-error" id="confirm_password_error"></div>
            </div>
        </div>

        <div class="step-buttons">
            <button type="button" class="step-btn prev" onclick="prevStep(3)">Previous</button>
            <button type="submit" class="step-btn submit">Create Account</button>
        </div>
    </div>

        </form>

    <div class="signup-link">
        Already have an account? <a href="login.php">Sign In Here</a>
    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById("password");
    const icon = document.querySelector(".input-group:nth-child(4) .right-icon");

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
    const icon = document.querySelector(".input-group:nth-child(5) .right-icon");

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

function validateStep(stepNumber) {
    const stepContent = document.getElementById('step' + stepNumber + '-content');
    const requiredInputs = stepContent.querySelectorAll('input[required], select[required]');
    let isValid = true;

    requiredInputs.forEach(function(input) {
        // Check if field is empty
        if (input.value.trim() === '') {
            isValid = false;
            input.classList.add('error');
        } else {
            // Check if field has validation error
            if (input.classList.contains('error')) {
                isValid = false;
            }
        }
    });

    return isValid;
}

function saveFormData() {
    const form = document.getElementById('registrationForm');
    const formData = new FormData(form);
    const data = {};

    formData.forEach((value, key) => {
        data[key] = value;
    });

    localStorage.setItem('registrationFormData', JSON.stringify(data));
    localStorage.setItem('currentStep', document.querySelector('.step-content.active').id);
}

function loadFormData() {
    const savedData = localStorage.getItem('registrationFormData');
    const savedStep = localStorage.getItem('currentStep');

    if (savedData) {
        const data = JSON.parse(savedData);
        const form = document.getElementById('registrationForm');

        for (const key in data) {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = data[key];
                // Trigger validation and asterisk toggle
                input.dispatchEvent(new Event('input'));
                input.dispatchEvent(new Event('change'));
                // Ensure asterisk is hidden for valid inputs
                if (input.value.trim() !== '') {
                    input.classList.add('valid');
                    toggleAsterisk(input);
                }
            }
        }
    }

    if (savedStep) {
        // Hide all steps
        document.querySelectorAll('.step-content').forEach(function(content) {
            content.classList.remove('active');
        });
        document.querySelectorAll('.step').forEach(function(step) {
            step.classList.remove('active', 'completed');
        });

        // Show saved step
        const stepNum = savedStep.replace('step', '').replace('-content', '');
        document.getElementById(savedStep).classList.add('active');
        document.getElementById('step' + stepNum).classList.add('active');

        // Mark previous steps as completed
        for (let i = 1; i < stepNum; i++) {
            document.getElementById('step' + i).classList.add('completed');
        }
    }
}

function clearFormData() {
    localStorage.removeItem('registrationFormData');
    localStorage.removeItem('currentStep');
}

function nextStep(currentStep) {
    // Validate current step before proceeding
    if (!validateStep(currentStep)) {
        return; // Don't proceed if validation fails
    }

    // Save form data before proceeding
    saveFormData();

    // Hide current step
    document.getElementById('step' + currentStep + '-content').classList.remove('active');
    document.getElementById('step' + currentStep).classList.remove('active');
    document.getElementById('step' + currentStep).classList.add('completed');

    // Show next step
    const nextStepNum = currentStep + 1;
    document.getElementById('step' + nextStepNum + '-content').classList.add('active');
    document.getElementById('step' + nextStepNum).classList.add('active');
}

function prevStep(currentStep) {
    // Save form data before going back
    saveFormData();

    // Hide current step
    document.getElementById('step' + currentStep + '-content').classList.remove('active');
    document.getElementById('step' + currentStep).classList.remove('active');

    // Show previous step
    const prevStepNum = currentStep - 1;
    document.getElementById('step' + prevStepNum + '-content').classList.add('active');
    document.getElementById('step' + prevStepNum).classList.add('active');
    document.getElementById('step' + prevStepNum).classList.remove('completed');
}

// Calculate age based on birthdate
function calculateAge() {
    const birthdateInput = document.querySelector('input[name="birthdate"]');
    const ageInput = document.querySelector('input[name="age"]');
    
    if (birthdateInput.value) {
        const birthdate = new Date(birthdateInput.value);
        const today = new Date();
        
        let age = today.getFullYear() - birthdate.getFullYear();
        const monthDiff = today.getMonth() - birthdate.getMonth();
        const dayDiff = today.getDate() - birthdate.getDate();
        
        // Adjust age if birthday hasn't occurred yet this year
        if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
            age--;
        }
        
        // Ensure age is not negative
        if (age >= 0) {
            ageInput.value = age;
        } else {
            ageInput.value = '';
        }
    } else {
        ageInput.value = '';
    }
}

// Real-time validation functions
function validateFirstName(input) {
    const firstName = input.value.trim();
    const errorDiv = document.getElementById('first_name_error');

    if (firstName === '') {
        input.classList.remove('error', 'valid');
        errorDiv.classList.remove('show');
        return true; // Allow empty for now, will be validated on submit
    }

    const namePattern = /^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/;
    if (!namePattern.test(firstName)) {
        input.classList.add('error');
        input.classList.remove('valid');
        errorDiv.textContent = 'First Name must start with a capital letter followed by lowercase letters (spaces allowed between words)!';
        errorDiv.classList.add('show');
        toggleAsterisk(input);
        return false;
    } else {
        input.classList.remove('error');
        input.classList.add('valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true;
    }
}

function validateLastName(input) {
    const lastName = input.value.trim();
    const errorDiv = document.getElementById('last_name_error');

    if (lastName === '') {
        input.classList.remove('error', 'valid');
        errorDiv.classList.remove('show');
        return true; // Allow empty for now, will be validated on submit
    }

    const namePattern = /^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/;
    if (!namePattern.test(lastName)) {
        input.classList.add('error');
        input.classList.remove('valid');
        errorDiv.textContent = 'Last Name must start with a capital letter followed by lowercase letters (spaces allowed between words)!';
        errorDiv.classList.add('show');
        toggleAsterisk(input);
        return false;
    } else {
        input.classList.remove('error');
        input.classList.add('valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true;
    }
}

function validateEmail(input) {
    const email = input.value.trim();
    const errorDiv = document.getElementById('email_error');
    
    if (email === '') {
        input.classList.remove('error', 'valid');
        errorDiv.classList.remove('show');
        return true; // Allow empty for now, will be validated on submit
    }
    
    const gmailPattern = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
    if (!gmailPattern.test(email)) {
        input.classList.add('error');
        input.classList.remove('valid');
        errorDiv.textContent = 'Only Gmail email addresses are allowed (e.g., user@gmail.com)!';
        errorDiv.classList.add('show');
        toggleAsterisk(input);
        return false;
    } else {
        input.classList.remove('error');
        input.classList.add('valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true;
    }
}

function validateUsername(input) {
    const username = input.value.trim();
    const errorDiv = document.getElementById('username_error');
    
    if (username === '') {
        input.classList.remove('error', 'valid');
        errorDiv.classList.remove('show');
        return true; // Allow empty for now, will be validated on submit
    }
    
    if (username.length !== 6) {
        input.classList.add('error');
        input.classList.remove('valid');
        errorDiv.textContent = 'Username must be exactly 6 characters!';
        errorDiv.classList.add('show');
        toggleAsterisk(input);
        return false;
    } else {
        input.classList.remove('error');
        input.classList.add('valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true;
    }
}

function validatePassword(input) {
    const password = input.value;
    const errorDiv = document.getElementById('password_error');
    
    if (password === '') {
        input.classList.remove('error', 'valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true; // Allow empty for now, will be validated on submit
    }
    
    const passwordPattern = /^(?=.*[a-zA-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?])[a-zA-Z\d!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]{8,10}$/;
    if (!passwordPattern.test(password)) {
        input.classList.add('error');
        input.classList.remove('valid');
        errorDiv.textContent = 'Password must be 8-10 characters with mix of letters, numbers, and special characters!';
        errorDiv.classList.add('show');
        toggleAsterisk(input);
        return false;
    } else {
        input.classList.remove('error');
        input.classList.add('valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true;
    }
}

function validateConfirmPassword(input) {
    const confirmPassword = input.value;
    const password = document.querySelector('input[name="password"]').value;
    const errorDiv = document.getElementById('confirm_password_error');
    
    if (confirmPassword === '') {
        input.classList.remove('error', 'valid');
        errorDiv.classList.remove('show');
        return true; // Allow empty for now, will be validated on submit
    }
    
    if (confirmPassword !== password) {
        input.classList.add('error');
        input.classList.remove('valid');
        errorDiv.textContent = 'Passwords do not match!';
        errorDiv.classList.add('show');
        toggleAsterisk(input);
        return false;
    } else {
        input.classList.remove('error');
        input.classList.add('valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true;
    }
}

function validateZipCode(input) {
    const zipCode = input.value.trim();
    const errorDiv = document.getElementById('zip_code_error');
    
    if (zipCode === '') {
        input.classList.remove('error', 'valid');
        errorDiv.classList.remove('show');
        return true; // Allow empty for now, will be validated on submit
    }
    
    const zipPattern = /^\d+$/;
    if (!zipPattern.test(zipCode)) {
        input.classList.add('error');
        input.classList.remove('valid');
        errorDiv.textContent = 'Zip Code must contain numbers only!';
        errorDiv.classList.add('show');
        toggleAsterisk(input);
        return false;
    } else {
        input.classList.remove('error');
        input.classList.add('valid');
        errorDiv.classList.remove('show');
        toggleAsterisk(input);
        return true;
    }
}

// Toggle required asterisk visibility based on valid input
function toggleAsterisk(input) {
    const asterisk = input.parentElement.querySelector('.required-asterisk');
    if (asterisk) {
        const isValid = input.classList.contains('valid');
        if (isValid && input.value.trim() !== '') {
            asterisk.classList.add('hidden');
        } else {
            asterisk.classList.remove('hidden');
        }
    }
}

// Add event listeners for real-time validation
document.addEventListener('DOMContentLoaded', function() {
    // Load saved form data
    loadFormData();

    // Clear form data on successful submission
    const form = document.getElementById('registrationForm');
    if (form) {
        form.addEventListener('submit', function() {
            clearFormData();
        });
    }

    // Add asterisk toggle for all required inputs
    const requiredInputs = document.querySelectorAll('input[required], select[required]');
    requiredInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            toggleAsterisk(this);
            // For fields without specific validation, add valid class if not empty
            if (!this.classList.contains('error') && this.value.trim() !== '') {
                this.classList.add('valid');
                toggleAsterisk(this);
            }
            // Auto-save form data on input
            saveFormData();
        });
        input.addEventListener('change', function() {
            toggleAsterisk(this);
            // For fields without specific validation, add valid class if not empty
            if (!this.classList.contains('error') && this.value.trim() !== '') {
                this.classList.add('valid');
                toggleAsterisk(this);
            }
            // Auto-save form data on change
            saveFormData();
        });
    });

    // Auto-save all form inputs (including non-required)
    const allInputs = document.querySelectorAll('input, select');
    allInputs.forEach(function(input) {
        input.addEventListener('input', saveFormData);
        input.addEventListener('change', saveFormData);
    });

    // Birthdate age calculation
    const birthdateInput = document.querySelector('input[name="birthdate"]');
    if (birthdateInput) {
        birthdateInput.addEventListener('change', calculateAge);
        birthdateInput.addEventListener('blur', calculateAge);
    }
    
    // Real-time validation for First Name
    const firstNameInput = document.querySelector('input[name="first_name"]');
    if (firstNameInput) {
        firstNameInput.addEventListener('input', function() {
            validateFirstName(this);
        });
        firstNameInput.addEventListener('blur', function() {
            validateFirstName(this);
        });
    }
    
    // Real-time validation for Last Name
    const lastNameInput = document.querySelector('input[name="last_name"]');
    if (lastNameInput) {
        lastNameInput.addEventListener('input', function() {
            validateLastName(this);
        });
        lastNameInput.addEventListener('blur', function() {
            validateLastName(this);
        });
    }
    
    // Real-time validation for Email
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            validateEmail(this);
        });
        emailInput.addEventListener('blur', function() {
            validateEmail(this);
        });
    }
    
    // Real-time validation for Username
    const usernameInput = document.querySelector('input[name="username"]');
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            validateUsername(this);
        });
        usernameInput.addEventListener('blur', function() {
            validateUsername(this);
        });
    }
    
    // Real-time validation for Password
    const passwordInput = document.querySelector('input[name="password"]');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            validatePassword(this);
            // Also validate confirm password if it has value
            const confirmInput = document.querySelector('input[name="confirm_password"]');
            if (confirmInput.value) {
                validateConfirmPassword(confirmInput);
            }
        });
        passwordInput.addEventListener('blur', function() {
            validatePassword(this);
        });
    }
    
    // Real-time validation for Confirm Password
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            validateConfirmPassword(this);
        });
        confirmPasswordInput.addEventListener('blur', function() {
            validateConfirmPassword(this);
        });
    }
    
    // Real-time validation for Zip Code
    const zipCodeInput = document.querySelector('input[name="zip_code"]');
    if (zipCodeInput) {
        zipCodeInput.addEventListener('input', function() {
            validateZipCode(this);
        });
        zipCodeInput.addEventListener('blur', function() {
            validateZipCode(this);
        });
    }
});
</script>

</body>
</html>