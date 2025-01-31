function validatePassword() {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmPassword").value;
    const errorField = document.getElementById("passwordError");
    const lengthErrorField = document.getElementById("passwordLengthError");

    if (password.length < 8) {
        lengthErrorField.innerHTML = "Password should be at least 8 characters long.";
        return false;
    } else {
        lengthErrorField.innerHTML = "";
    }

    if (password !== confirmPassword) {
        errorField.innerHTML = "Passwords do not match!";
        return false;
    } else {
        errorField.innerHTML = "";
        return true;
    }
}

function validateMobileNumber() {
    const mobileNumber = document.getElementById("mobileNumber").value;
    const mobileNumberError = document.getElementById("mobileNumberError");
    const pattern = /^\d+$/;

    if (!pattern.test(mobileNumber)) {
        mobileNumberError.innerHTML = "Please enter a valid mobile number with digits only.";
        return false;
    } else {
        mobileNumberError.innerHTML = "";
        return true;
    }
}

function togglePassword() {
    const passwordField = document.getElementById("password");
    const togglePasswordBtn = document.getElementById("togglePassword");

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
    const toggleConfirmPasswordBtn = document.getElementById("toggleConfirmPassword");

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