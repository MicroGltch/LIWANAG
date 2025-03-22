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

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('login-form');
    const rememberMeCheckbox = document.getElementById('rememberMe'); 
    const emailInput = document.getElementById('login-email'); 
    const passwordInputRemember = document.getElementById('login-pass'); 

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const formData = new FormData(form);

        fetch(form.action, {
            method: form.method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.redirect) {
                window.location.href = data.redirect;
            } else if (data.sweetalert) {
                let swalOptions = {
                    title: data.sweetalert[0],
                    text: data.sweetalert[1],
                    icon: data.sweetalert[2],
                    confirmButtonColor: '#3085d6'
                };

                if (data.pending) {
                    swalOptions.confirmButtonText = "Verify Now";
                    swalOptions.didClose = () => {
                        window.location.href = "../signupverify/verify.php";
                    };
                }

                if (data.showChangePassword) {
                    Swal.fire({
                        title: 'Enter New Password to Activate your Account',
                        text: 'You are using the default password. Please update it now.',
                        icon: 'warning',
                        confirmButtonColor: '#3085d6'
                    }).then(() => { //Use then instead of didClose.
                        Swal.fire({
                            title: 'Change Password',
                            html: `
                                <p>Please enter your New Password <br>below to activate your account.</p>
                                <input type="password" id="newPassword" class="swal2-input" placeholder="New Password">
                                <input type="password" id="confirmPassword" class="swal2-input" placeholder="Confirm New Password">`,
                            confirmButtonText: 'Submit',
                            focusConfirm: false,
                            preConfirm: () => {
                                const newPassword = Swal.getPopup().querySelector('#newPassword').value;
                                const confirmPassword = Swal.getPopup().querySelector('#confirmPassword').value;
                                if (!newPassword || !confirmPassword) {
                                    Swal.showValidationMessage(`Please enter password and confirm password`);
                                }
                                if (newPassword !== confirmPassword) {
                                    Swal.showValidationMessage(`Passwords do not match`);
                                }
                                return { newPassword: newPassword, confirmPassword: confirmPassword };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch('manageaccount/therapist_newpassword.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `account_ID=${data.account_ID}&new_password=${encodeURIComponent(result.value.newPassword)}&confirm_password=${encodeURIComponent(result.value.confirmPassword)}`,
                                })
                                .then(response => response.json())
                                .then(updateData => {
                                    if (updateData.status === 'success') {
                                        Swal.fire('Password Updated!', '', 'success').then(() => {
                                            window.location.href = 'loginpage.php'; // Redirect to login
                                        });
                                    } else {
                                         Swal.fire('Error!', updateData.message, 'error');
                                    }
                                });
                            }
                        });
                    });
                }

                Swal.fire(swalOptions);
            }
        })
        .catch(error => {
            console.error('Error in login fetch:', error);
            Swal.fire({
                title: 'Error',
                text: 'An error occurred. Please try again later.',
                icon: 'error',
                confirmButtonColor: '#741515'
            });
        });
    });

    // ✅ Remember Me Functionality (Restored)
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

    // ✅ Load Saved Credentials If "Remember Me" was checked
    const isRemembered = localStorage.getItem('remembered');
    if (isRemembered === 'true') {
        rememberMeCheckbox.checked = true;
        emailInput.value = localStorage.getItem('rememberedEmail');
        passwordInputRemember.value = localStorage.getItem('rememberedPassword');
    }
});
