
//Sample login validate

function validate_login() {
    var validate_email = validate_emailadd();
    var validate_pass = validate_password();

    if (!validate_email || !validate_pass) {
        return false;
    }
    return true;
}


    function validate_emailadd(){
        var email = document.getElementById("login-email");
        var email_error = document.getElementById("email-error");

        if(email.value == ''){
            email.classList.add("is-invalid");
            error_text = "Enter email";
            email_error.innerHTML = error_text;
            email_error.classList.add("invalid-feedback");
            return false;
        }else{
            var emailregex = /^\S+@\S+\.\S+$/;
            if(!emailregex.test(email.value)){
                email.classList.add("is-invalid");
                error_text = "Enter a valid email";
                email_error.innerHTML = error_text;
                email_error.classList.add("invalid-feedback");
                return false;
            }else{
                email.classList.remove("is-invalid");
                email_error.innerHTML = "";
                email_error.classList.remove("invalid-feedback");
                return true;
            }
        }
    }

    function validate_password(){
        var pass = document.getElementById("login-pass");
        var pass_error = document.getElementById("pass-error");

        if(pass.value == ''){
            pass.classList.add("is-invalid");
            error_text = "Enter password";
            pass_error.innerHTML = error_text;
            pass_error.classList.add("invalid-feedback");
            return false;
        }else{
            pass.classList.remove("is-invalid");
            pass_error.innerHTML = "";
            pass_error.classList.remove("invalid-feedback");
            return true;
        }
    }