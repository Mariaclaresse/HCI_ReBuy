document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const steps = document.querySelectorAll('.form-step');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');
    const birthdateInput = document.getElementById('birthdate');
    const ageInput = document.getElementById('age');
    const togglePasswordBtns = document.querySelectorAll('.toggle-password');

    let currentStep = 0;

    // Auto calculate age from birthdate
    birthdateInput.addEventListener('change', function() {
        calculateAge();
    });

    function calculateAge() {
        const birthdateValue = birthdateInput.value;
        
        if (!birthdateValue) {
            ageInput.value = '';
            return;
        }

        const birthdate = new Date(birthdateValue);
        const today = new Date();
        
        let age = today.getFullYear() - birthdate.getFullYear();
        const monthDiff = today.getMonth() - birthdate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
            age--;
        }
        
        ageInput.value = age >= 0 ? age : '';
    }

    // Next button click
    nextBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (validateStep(currentStep)) {
            steps[currentStep].classList.remove('active');
            currentStep++;
            steps[currentStep].classList.add('active');
            window.scrollTo(0, 0);
        }
    });

    // Back button click
    backBtn.addEventListener('click', function(e) {
        e.preventDefault();
        steps[currentStep].classList.remove('active');
        currentStep--;
        steps[currentStep].classList.add('active');
        window.scrollTo(0, 0);
    });

    // Toggle password visibility
    togglePasswordBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const passwordField = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Validate step
    function validateStep(step) {
        const inputs = steps[step].querySelectorAll('[required]');
        for (let input of inputs) {
            if (!input.value) {
                alert('Please fill all required fields!');
                return false;
            }
        }
        return true;
    }
});