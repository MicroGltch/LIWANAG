document.addEventListener("DOMContentLoaded", function () {
    let timer = 60;
    let resendBtn = document.getElementById("resend-otp");
    let otpForm = document.querySelector("form");
    let verifyBtn = document.querySelector("button[name='verify']");
    let otpInputField = document.getElementById("otp-input");
    let otpError = document.getElementById("otp-error");

    function startResendTimer() {
        resendBtn.disabled = true;
        let interval = setInterval(() => {
            timer--;
            resendBtn.innerText = `Resend OTP (${timer}s)`;
            if (timer === 0) {
                clearInterval(interval);
                resendBtn.disabled = false;
                resendBtn.innerText = "Resend OTP";
            }
        }, 1000);
    }

    startResendTimer();

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
            console.log("Response from otpverify.php:", data); // Debugging
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
            } else if (data.trim() === "Incorrect OTP. Please try again.") {
                Swal.fire("Wrong OTP", data, "error");
            } else if (data.trim() === "OTP expired. Please request a new OTP.") {
                Swal.fire("OTP Expired", data, "warning");
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

    resendBtn.addEventListener("click", function() {
        resendBtn.disabled = true;
        resendBtn.innerText = "Resending...";

        fetch("resendotp.php", {
            method: "POST",
        })
      .then(response => response.json())
      .then(data => {
            if (data.status === "success") {
                Swal.fire("OTP Resent", data.message, "success");
                startResendTimer(); 
            } else {
                Swal.fire("Error", data.message, "error");
                resendBtn.disabled = false;
                resendBtn.innerText = "Resend OTP";
            }
        })
      .catch(error => {
            console.error("Fetch error:", error);
            Swal.fire("Error", "Something went wrong. Try again later.", "error");
            resendBtn.disabled = false;
            resendBtn.innerText = "Resend OTP";
        });
    });
});