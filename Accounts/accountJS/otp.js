document.addEventListener("DOMContentLoaded", function () {
    let resendBtn = document.getElementById("resend-otp");
    let otpForm = document.getElementById("otp-form");
    let verifyBtn = document.querySelector("button[name='verify']");
    let otpInputField = document.getElementById("otp-input");
    let otpError = document.getElementById("otp-error");

    otpForm.addEventListener("submit", function (event) {
        event.preventDefault();
        let otpValue = otpInputField.value.trim();
        otpError.textContent = "";
        if (!otpValue) {
            otpError.textContent = "Please enter a valid OTP.";
            return;
        }
        verifyBtn.innerHTML = "Verifying...";
        verifyBtn.disabled = true;

        fetch("otpverify.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `verify=true&otp=${otpValue}`,
        })
        .then(response => response.text())
        .then(data => {
            verifyBtn.innerHTML = "Verify";
            verifyBtn.disabled = false;
            if (data.trim() === "success") {
                Swal.fire({
                    title: "Account Verified!",
                    text: "You are now registered!",
                    icon: "success",
                    confirmButtonText: "OK"
                }).then(() => {
                    window.location.href = "../loginpage.php";
                });
            } else {
                Swal.fire("Error", data, "error");
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            Swal.fire("Error", "Something went wrong. Try again later.", "error");
            verifyBtn.innerHTML = "Verify";
            verifyBtn.disabled = false;
        });
    });

    resendBtn.addEventListener("click", function () {
        resendBtn.disabled = true;
        resendBtn.innerText = "Resending...";
        fetch("resendotp.php", {
            method: "POST",
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                Swal.fire("OTP Resent", data.message, "success");
            } else {
                Swal.fire("Error", data.message || "Failed to resend OTP.", "error");
            }
            resendBtn.disabled = false;
            resendBtn.innerText = "Resend OTP";
        })
        .catch(error => {
            console.error("Fetch error:", error);
            Swal.fire("Error", "Something went wrong. Try again later.", "error");
            resendBtn.disabled = false;
            resendBtn.innerText = "Resend OTP";
        });
    });
});
