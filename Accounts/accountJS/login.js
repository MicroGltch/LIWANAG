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

// Remember Me Functionality
const rememberMeCheckbox = document.getElementById('rememberMe');
const emailInput = document.getElementById('form-stacked-text'); // Correct ID
const passwordInputRemember = document.getElementById('login-pass'); // Correct ID


rememberMeCheckbox.addEventListener('change', function () {
    if (this.checked) {
        localStorage.setItem('rememberedEmail', emailInput.value);
        localStorage.setItem('rememberedPassword', passwordInputRemember.value);
        localStorage.setItem('remembered', 'true');
    } else {
        localStorage.removeItem('rememberedEmail');
        localStorage.removeItem('rememberedPassword');
        localStorage.removeItem('remembered');
    }
});

document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('login-form');
    const rememberMeCheckbox = document.getElementById('rememberMe'); // Get checkbox element
    const emailInput = document.getElementById('login-email'); // Get email input
    const passwordInputRemember = document.getElementById('passwordInputRemember'); // Get password input

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const formData = new FormData(form);

        fetch(form.action, {
            method: form.method,
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else if (data.sweetalert) {
                    let swalOptions = {
                        title: data.sweetalert[0],
                        text: data.sweetalert[1],
                        icon: data.sweetalert[2],
                        confirmButtonColor: '#3085d6', // Or your preferred color
                        didClose: () => {
                            if (data.sweetalert[0] === "Pending Account") {
                                window.location.href = "../Accounts/signupverify/verify.php";
                            }
                        }
                    };

                    // Conditionally add the HTML content and custom button
                    if (data.sweetalert[4]) { // Check the flag from PHP
                        swalOptions.html = data.sweetalert[3];
                        swalOptions.buttons = {
                            verify: {
                                text: 'Verify Now',
                                className: 'uk-button uk-button-primary',
                                action: function () {
                                    window.location.href = "../Accounts/signupverify/verify.php";
                                }
                            }
                        };
                    }

                    Swal.fire(swalOptions);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred. Please try again later.',
                    icon: 'error',
                    confirmButtonColor: '#741515'
                });
            });
    });

    const isRemembered = localStorage.getItem('remembered');

    if (isRemembered === 'true') {
        rememberMeCheckbox.checked = true;
        emailInput.value = localStorage.getItem('rememberedEmail');
        passwordInputRemember.value = localStorage.getItem('rememberedPassword');
    }
});