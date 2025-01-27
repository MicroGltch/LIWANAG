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

// sample ng js sa prev codes ko check mo nlng @lancebagsit pra mas malinis

function validate_signup() {
    var validate_fname = validate_first();
    var validate_lname = validate_last();
    var validate_address = validate_add();
    var validate_city = validate_cityadd();
    var validate_zip = validate_zcode();
    var validate_contact = validate_num();
    var validate_email = validate_emailadd();
    var validate_password = validate_pass();

    if (!validate_fname || !validate_lname || !validate_address || !validate_city ||
        !validate_zip || !validate_contact || !validate_email || !validate_password){
        return false;
    }
    return true;
}

    function validate_first(){
        var fname = document.getElementById("sign-fname");
        var fname_error = document.getElementById("fname-error");
        var error_text = "";

        if(fname.value == ''){
            fname.classList.add("is-invalid");
            error_text = "Enter Firstname";
            fname_error.innerHTML = error_text;
            fname_error.classList.add("invalid-feedback");
            return false;
        }else{
            var nameregex = /^[a-zA-Z- ]+$/;
            if(fname.value.length <=1){
                fname.classList.add("is-invalid");
                error_text = "Firstname too short";
                fname_error.innerHTML = error_text;
                fname_error.classList.add("invalid-feedback");
                return false;
            }else if(!nameregex.test(fname.value)){
                fname.classList.add("is-invalid");
                error_text = "Enter a valid name";
                fname_error.innerHTML = error_text;
                fname_error.classList.add("invalid-feedback");
            }else{
                fname.classList.remove("is-invalid");
                fname_error.innerHTML = "";
                fname_error.classList.remove("invalid-feedback");
                return true;
            }
        }
    }
    
    function validate_last(){
        var lname = document.getElementById("sign-lname");
        var lname_error = document.getElementById("lname-error");
        var error_text = "";

        if(lname.value == ''){
            lname.classList.add("is-invalid");
            error_text = "Enter Lastname";
            lname_error.innerHTML = error_text;
            lname_error.classList.add("invalid-feedback");
            return false;
        }else{
            var nameregex = /^[a-zA-Z- ]+$/;
            if(lname.value.length <=1){
                lname.classList.add("is-invalid");
                error_text = "Lastname too short";
                lname_error.innerHTML = error_text;
                lname_error.classList.add("invalid-feedback");
                return false;
            }else if(!nameregex.test(lname.value)){
                lname.classList.add("is-invalid");
                error_text = "Enter a valid name";
                lname_error.innerHTML = error_text;
                lname_error.classList.add("invalid-feedback");
            }else{
                lname.classList.remove("is-invalid");
                lname_error.innerHTML = "";
                lname_error.classList.remove("invalid-feedback");
                return true;
            }
        }
    }

    function validate_add(){
        var sadd = document.getElementById("sign-add");
        var sadd_error = document.getElementById("add-error");
        var error_text = "";

        if(sadd.value == ''){
            sadd.classList.add("is-invalid");
            error_text = "Enter Address";
            sadd_error.innerHTML = error_text;
            sadd_error.classList.add("invalid-feedback");
            return false;
        }else{
            if(sadd.value.length <=5){
                sadd.classList.add("is-invalid");
                error_text = "Invalid Address";
                sadd_error.innerHTML = error_text;
                sadd_error.classList.add("invalid-feedback");
                return false;
            }else{
                sadd.classList.remove("is-invalid");
                sadd_error.innerHTML = "";
                sadd_error.classList.remove("invalid-feedback");
                return true;
            }
        }
    }

    function validate_cityadd(){
        var city = document.getElementById("sign-city");
        var city_error = document.getElementById("city-error");
        var error_text = "";

        if(city.value == ''){
            city.classList.add("is-invalid");
            error_text = "Enter Address";
            city_error.innerHTML = error_text;
            city_error.classList.add("invalid-feedback");
            return false;
        }else{
            if(city.value.length <=4){
                city.classList.add("is-invalid");
                error_text = "Address too short";
                city_error.innerHTML = error_text;
                city_error.classList.add("invalid-feedback");
                return false;
            }else{
                city.classList.remove("is-invalid");
                city_error.innerHTML = "";
                city_error.classList.remove("invalid-feedback");
                return true;
            }
        }
    }

    function validate_zcode(){
        var zip = document.getElementById("sign-zip");
        var zip_error = document.getElementById("zip-error");
        var error_text = "";

        if(zip.value == ''){
            zip.classList.add("is-invalid");
            error_text = "Enter Zip code";
            zip_error.innerHTML = error_text;
            zip_error.classList.add("invalid-feedback");
            return false;
        }else{
            var numregex = /^[0-9]+$/;
            if(zip.value.length !==4){
                zip.classList.add("is-invalid");
                error_text = "Enter a valid Zip code";
                zip_error.innerHTML = error_text;
                zip_error.classList.add("invalid-feedback");
                return false;
            }else if(!numregex.test(zip.value)){
                zip.classList.add("is-invalid");
                error_text = "Enter a valid Zip code";
                zip_error.innerHTML = error_text;
                zip_error.classList.add("invalid-feedback");
            }else{
                zip.classList.remove("is-invalid");
                zip_error.innerHTML = "";
                zip_error.classList.remove("invalid-feedback");
                return true;
            }
        }
    }

    function validate_num(){
        var number = document.getElementById("sign-contact");
        var number_error = document.getElementById("contact-error");
        var error_text = "";

        if(number.value == ''){
            number.classList.add("is-invalid");
            error_text = "Enter Contact number";
            number_error.innerHTML = error_text;
            number_error.classList.add("invalid-feedback");
            return false;
        }else{
            var numregex = /^[0-9]+$/;
            if(number.value.length !==11){
                number.classList.add("is-invalid");
                error_text = "Enter a valid Contact number";
                number_error.innerHTML = error_text;
                number_error.classList.add("invalid-feedback");
                return false;
            }else if(!numregex.test(number.value)){
                number.classList.add("is-invalid");
                error_text = "Enter a Contact number";
                number_error.innerHTML = error_text;
                number_error.classList.add("invalid-feedback");
            }else{
                number.classList.remove("is-invalid");
                number_error.innerHTML = "";
                number_error.classList.remove("invalid-feedback");
                return true;
            }
        }
    }

    function validate_emailadd(){
        var email = document.getElementById("sign-email");
        var email_error = document.getElementById("email-error");
        var error_text = "";

        if(email.value == ''){
            email.classList.add("is-invalid");
            error_text = "Enter an email";
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

    function validate_pass(){
        var pass = document.getElementById("sign-pass");
        var pass_error = document.getElementById("pass-error");
        var error_text = "";

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
