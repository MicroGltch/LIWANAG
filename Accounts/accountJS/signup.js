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
    // Attach a blur event listener to reformat the mobile number when focus is lost.
    let mobileNumberInput = document.getElementById("mobileNumber");
    mobileNumberInput.addEventListener("blur", function() {
        let phone = this.value.trim();
        if (phone.length > 0) {
            // If it starts with "0", replace it with "+63"
            if (phone.startsWith("0")) {
                phone = "+63" + phone.substring(1);
            } else if (!phone.startsWith("+63")) {
                // Otherwise, if it doesn't already start with +63, prepend +63.
                phone = "+63" + phone;
            }
            this.value = phone;
        }
    });

    document.getElementById("signupvalidate").addEventListener("submit", function (event) {
        let valid = true;

        // First Name Validation
        let firstName = document.getElementById("firstName").value.trim();
        let firstNameError = document.getElementById("firstNameError");
        let nameRegex = /^[A-Za-z]+( [A-Za-z]+)+$/;
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
        let passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\-_])[A-Za-z\d@$!%*?&\-_]{8,20}$/;
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
        let mobileNumber = mobileNumberInput.value.trim();
        let mobileNumberError = document.getElementById("mobileNumberError");

        // Reformat the mobile number if necessary
        if (mobileNumber.length > 0) {
            if (mobileNumber.startsWith("0")) {
                mobileNumber = "+63" + mobileNumber.substring(1);
                mobileNumberInput.value = mobileNumber;
            } else if (!mobileNumber.startsWith("+63")) {
                mobileNumber = "+63" + mobileNumber;
                mobileNumberInput.value = mobileNumber;
            }
        }

        // Validate: must be "+63" followed by exactly 10 digits.
        let mobileRegex = /^\+63\d{10}$/;
        if (!mobileRegex.test(mobileNumber)) {
            mobileNumberError.textContent = "Phone number must be in the format +63XXXXXXXXXX.";
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
            return false; 
        }
    });
});
