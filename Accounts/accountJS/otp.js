document.addEventListener("DOMContentLoaded", function () {
    let otpForm = document.getElementById("otp-form");
    let otpInputField = document.getElementById("otp-input");
    let otpError = document.getElementById("otp-error");
    let verifyBtn = document.querySelector("button[name='verify']"); // Get the button

    otpForm.addEventListener("submit", function (event) {
        event.preventDefault();

        let otpValue = otpInputField.value.trim();
        otpError.textContent = "";

        if (!otpValue) {
            otpError.textContent = "Please enter a valid OTP.";
            return;
        }

        verifyBtn.innerHTML = "Verifying..."; // Update button text
        verifyBtn.disabled = true; // Disable button

        fetch("otpverify.php", {  // This is the crucial line: sending to otpverify.php
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `verify=true&otp=${otpValue}`, // Sending the OTP
        })
        .then(response => response.text())
        .then(data => {
            console.log("Response from otpverify.php:", data); // Debugging
            verifyBtn.innerHTML = "Verify"; // Reset button text
            verifyBtn.disabled = false; // Enable button

            if (data.trim() === "success") {
                alert("Success!");
                window.location.href = "../loginpage.php";
            } else {
                alert("Error: " + data);
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            alert("An error occurred.");
            verifyBtn.innerHTML = "Verify"; // Reset button text
            verifyBtn.disabled = false; // Enable button
        });
    });
});