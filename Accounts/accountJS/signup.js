function togglePassword() {
    const passwordField = document.getElementById("password");
    const togglePasswordBtn = document.getElementById("togglePasswordIcon");

    if (passwordField.type === "password") {
        passwordField.type = "text";
        togglePasswordBtn.classList.remove("fa-eye");
        togglePasswordBtn.classList.add("fa-eye-slash");
    } else {
        passwordField.type = "password";
        togglePasswordBtn.classList.remove("fa-eye-slash");
        togglePasswordBtn.classList.add("fa-eye");
    }
}

function toggleConfirmPassword() {
    const confirmPasswordField = document.getElementById("confirmPassword");
    const toggleConfirmPasswordBtn = document.getElementById("toggleConfirmPasswordIcon");

    if (confirmPasswordField.type === "password") {
        confirmPasswordField.type = "text";
        toggleConfirmPasswordBtn.classList.remove("fa-eye");
        toggleConfirmPasswordBtn.classList.add("fa-eye-slash");
    } else {
        confirmPasswordField.type = "password";
        toggleConfirmPasswordBtn.classList.remove("fa-eye-slash");
        toggleConfirmPasswordBtn.classList.add("fa-eye");
    }
}

document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("signupvalidate").addEventListener("submit", function (event) {

        let valid = true;

        // First Name Validation
        let firstName = document.getElementById("firstName").value.trim();
        let firstNameError = document.getElementById("firstNameError");
        let nameRegex = /^[A-Za-z]{2,30}$/;
        if (!nameRegex.test(firstName)) {
            firstNameError.textContent = "Only letters allowed (2-30 characters).";
            valid = false;
        } else {
            firstNameError.textContent = "";
        }

        // Last Name Validation
        let lastName = document.getElementById("lastName").value.trim();
        let lastNameError = document.getElementById("lastNameError");
        if (!nameRegex.test(lastName)) {
            lastNameError.textContent = "Only letters allowed (2-30 characters).";
            valid = false;
        } else {
            lastNameError.textContent = "";
        }

        // Email Validation
        let email = document.getElementById("email").value.trim();
        let emailError = document.getElementById("emailError");
        let emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!emailRegex.test(email)) {
            emailError.textContent = "Invalid email format.";
            valid = false;
        } else {
            emailError.textContent = "";
        }

        // Password Validation
        let password = document.getElementById("password").value;
        let passwordError = document.getElementById("passwordError");
        let passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\-_])[A-Za-z\d@$!%*?&\-_]{8,20}$/
        if (!passwordRegex.test(password)) {
            passwordError.textContent = "Password must be 8-20 chars, with uppercase, lowercase, number, and special char.";
            valid = false;
        } else {
            passwordError.textContent = "";
        }

        // Confirm Password Validation
        let confirmPassword = document.getElementById("confirmPassword").value;
        let confirmPasswordError = document.getElementById("confirmPasswordError");
        if (confirmPassword !== password) {
            confirmPasswordError.textContent = "Passwords do not match.";
            valid = false;
        } else {
            confirmPasswordError.textContent = "";
        }

        // Mobile Number Validation
        let mobileNumber = document.getElementById("mobileNumber").value;
        let mobileNumberError = document.getElementById("mobileNumberError");
        let mobileRegex = /^\d{10,15}$/;
        if (!mobileRegex.test(mobileNumber)) {
            mobileNumberError.textContent = "Phone number must be 10-15 digits.";
            valid = false;
        } else {
            mobileNumberError.textContent = "";
        }

        // Address Validation
        let address = document.getElementById("address").value;
        let addressError = document.getElementById("addressError");
        if (address.length < 5) {
            addressError.textContent = "Address must be at least 5 characters.";
            valid = false;
        } else {
            addressError.textContent = "";
        }
        

        if (!valid) {
            event.preventDefault();
        }
    });
});