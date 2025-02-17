document.addEventListener("DOMContentLoaded", function () {
    let timerInterval;
    let resendBtn = document.getElementById("resend-otp");
    let otpForm = document.querySelector("form");
    let verifyBtn = document.querySelector("button[name='verify']");
    let otpInputField = document.getElementById("otp-input");
    let otpError = document.getElementById("otp-error");
    let timerDisplay = document.getElementById('timer'); // Make sure you have this element in your HTML
    let otpExpiryInput = document.createElement('input'); // Create hidden input dynamically
    otpExpiryInput.type = 'hidden';
    otpExpiryInput.id = 'otp-expiry-time';
    otpForm.appendChild(otpExpiryInput); // Add it to the form

    function startTimer(duration) {
        let timer = duration;

        clearInterval(timerInterval);

        timerInterval = setInterval(function () {
            const minutes = Math.floor(timer / 60);
            const seconds = timer % 60;

            timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            if (--timer < 0) {
                clearInterval(timerInterval);
                timerDisplay.textContent = "OTP expired";
                resendBtn.disabled = false;
                resendBtn.innerText = "Resend OTP";
            }
        }, 1000);
    }

    function calculateRemainingTime(otpExpiryTime) {
        const expiryDate = new Date(otpExpiryTime);
        const now = new Date();
        const diffInSeconds = Math.round((expiryDate.getTime() - now.getTime()) / 1000);
        return Math.max(0, diffInSeconds);
    }

    // On page load:
    fetch("resendotp.php?check_expiry=true", {
        method: "GET",
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "success" && data.expiry_time) {
            otpExpiryInput.value = data.expiry_time;
            const remainingTime = calculateRemainingTime(data.expiry_time);
            if (remainingTime > 0) {
                startTimer(remainingTime);
                resendBtn.disabled = true;
                timerDisplay.style.display = 'inline'; // Show timer
            } else {
                resendBtn.disabled = false;
                timerDisplay.style.display = 'none'; // Hide timer
            }
        } else {
            resendBtn.disabled = false;
            timerDisplay.style.display = 'none'; // Hide timer
        }
    })
    .catch(error => {
        console.error("Error fetching expiry time:", error);
        resendBtn.disabled = false;
        timerDisplay.style.display = 'none'; // Hide timer
    });

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
            console.log("Response from otpverify.php:", data);
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

    resendBtn.addEventListener("click", function () {
        resendBtn.disabled = true;
        resendBtn.innerText = "Resending...";

        fetch("resendotp.php", {
            method: "POST",
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success" && data.expiry_time) { // Check for expiry_time
                Swal.fire("OTP Resent", data.message, "success");
                otpExpiryInput.value = data.expiry_time; // Update expiry time
                const remainingTime = calculateRemainingTime(data.expiry_time);
                startTimer(remainingTime);
                timerDisplay.style.display = 'inline'; // Show timer
            } else {
                Swal.fire("Error", data.message || "Failed to resend OTP.", "error"); // Handle potential missing message
                resendBtn.disabled = false;
                resendBtn.innerText = "Resend OTP";
                timerDisplay.style.display = 'none'; // Hide timer
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            Swal.fire("Error", "Something went wrong. Try again later.", "error");
            resendBtn.disabled = false;
            resendBtn.innerText = "Resend OTP";
            timerDisplay.style.display = 'none'; // Hide timer
        });
    });
});