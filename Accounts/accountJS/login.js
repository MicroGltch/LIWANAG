function togglePassword() {
    var passwordInput = document.getElementById("login-pass");
    var toggleIcon = document.getElementById("togglePasswordIcon");

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        toggleIcon.classList.remove("fa-eye");
        toggleIcon.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        toggleIcon.classList.remove("fa-eye-slash");
        toggleIcon.classList.add("fa-eye");
    }
}

function validate_login() {
    var isEmailValid = validate_emailadd();
    var isPasswordValid = validate_password();

    return isEmailValid && isPasswordValid; 
}

function validate_emailadd() {
    var email = document.getElementById("login-email");
    var email_error = document.getElementById("email-error");

    if (email.value.trim() === '') { 
        email.classList.add("is-invalid");
        email_error.textContent = "Enter email"; 
        return false;
    } else {
        var emailregex = /^\S+@\S+\.\S+$/;
        if (!emailregex.test(email.value)) {
            email.classList.add("is-invalid");
            email_error.textContent = "Enter a valid email";
            return false;
        } else {
            email.classList.remove("is-invalid");
            email_error.textContent = "";
            return true;
        }
    }
}

function validate_password() {
    var pass = document.getElementById("login-pass");
    var pass_error = document.getElementById("pass-error");

    if (pass.value.trim() === '') {
        pass.classList.add("is-invalid");
        pass_error.textContent = "Enter password";
        return false;
    } else {
        pass.classList.remove("is-invalid");
        pass_error.textContent = "";
        return true;
    }
}

