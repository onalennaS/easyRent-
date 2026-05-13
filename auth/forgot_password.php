<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - L&T Connect</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <style>
    body {
      background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(30, 58, 138, 0.9)),
        url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2073&q=80');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 100vh;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
    }

    .input-field {
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid transparent;
      transition: all 0.3s ease;
    }

    .input-field:focus {
      background: rgba(255, 255, 255, 1);
      border-color: #3b82f6;
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(59, 130, 246, 0.2);
    }

    .btn-primary {
      background: linear-gradient(135deg, #1e40af, #3b82f6);
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
    }

    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    /* Modal styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      backdrop-filter: blur(5px);
    }
    
    .modal-content {
      width: 90%;
      max-width: 500px;
      animation: modalFadeIn 0.3s ease-out;
    }
    
    @keyframes modalFadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .code-input-container {
      display: flex;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }
    
    .code-input {
      width: 50px;
      height: 60px;
      text-align: center;
      font-size: 24px;
      font-weight: bold;
      border-radius: 12px;
      border: 2px solid rgba(59, 130, 246, 0.3);
      background: rgba(255, 255, 255, 0.95);
    }
    
    .code-input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
      outline: none;
    }
    
    .resend-link {
      color: #93c5fd;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .resend-link:hover {
      color: #3b82f6;
      text-decoration: underline;
    }
    
    .hidden {
      display: none;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
  <div class="relative z-10 w-full max-w-md">
    <div class="glass-card rounded-3xl p-8 shadow-2xl">
      <div class="flex flex-col items-center mb-6 text-center">
        <img src="../logo.png" alt="L&T Connect" class="w-24 h-24 object-contain mb-4" />
        <p class="text-blue-100 text-lg">Find your perfect home</p>
      </div>

      <div id="error-message" class="hidden bg-red-500 bg-opacity-20 border border-red-500 text-red-100 p-4 mb-6 rounded-xl">
        <div class="flex items-center">
          <i class="fas fa-exclamation-circle mr-3"></i>
          <span id="error-text"></span>
        </div>
      </div>

      <div id="success-message" class="hidden bg-green-500 bg-opacity-20 border border-green-500 text-green-100 p-4 mb-6 rounded-xl">
        <div class="flex items-center">
          <i class="fas fa-check-circle mr-3"></i>
          <span id="success-text"></span>
        </div>
      </div>

      <form id="loginForm" class="space-y-6">
        <div class="space-y-2">
          <label for="email" class="block text-sm font-semibold text-white">
            <i class="fas fa-envelope mr-2 text-blue-400"></i>Email Address
          </label>
          <input type="email" id="email" name="email" required class="input-field w-full px-4 py-4 rounded-xl focus:outline-none text-gray-800 font-medium" placeholder="Enter your email address" />
        </div>

        <div class="space-y-2">
          <label for="password" class="block text-sm font-semibold text-white">
            <i class="fas fa-lock mr-2 text-blue-400"></i>Password
          </label>
          <div class="relative">
            <input type="password" id="password" name="password" required class="input-field w-full px-4 py-4 rounded-xl focus:outline-none text-gray-800 font-medium pr-12" placeholder="Enter your password" />
            <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-600 hover:text-blue-600 transition-colors">
              <i class="fas fa-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <label class="flex items-center cursor-pointer">
            <input type="checkbox" name="remember_me" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" />
            <span class="ml-3 text-sm text-blue-100">Remember me</span>
          </label>
          <a href="#" id="forgotPasswordLink" class="text-sm text-blue-300 hover:text-blue-100 transition-colors font-medium">Forgot password?</a>
        </div>

        <button type="submit" id="submitBtn" class="btn-primary w-full text-white py-4 px-6 rounded-xl font-bold text-lg shadow-lg">
          <i class="fas fa-sign-in-alt mr-2"></i>
          <span id="submitText">Sign In</span>
        </button>

        <div class="relative my-8">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-400 opacity-30"></div>
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-4 bg-transparent text-gray-300">New to L&T Connect?</span>
          </div>
        </div>

        <div class="text-center mt-6">
          <p class="text-blue-200 text-sm">
            Don't have an account? 
            <a href="register.php" class="text-blue-400 hover:text-blue-600 font-semibold underline focus:outline-none">
              Create an account
            </a>
          </p>
        </div>
      </form>
    </div>

    <div class="text-center mt-8">
      <p class="text-blue-100 text-sm">© 2024 L&T Connect. Making property rental simple and secure.</p>
    </div>
  </div>

  <!-- Forgot Password Modal -->
  <div id="forgotPasswordModal" class="modal-overlay hidden">
    <div class="modal-content">
      <div class="glass-card rounded-3xl p-8 shadow-2xl relative">
        <button id="closeModal" class="absolute top-4 right-4 text-white text-xl hover:text-blue-300 transition-colors">
          <i class="fas fa-times"></i>
        </button>
        
        <h2 class="text-2xl font-bold text-white mb-2 text-center">Reset Your Password</h2>
        <p class="text-blue-100 text-center mb-6">We'll send a verification code to your email</p>
        
        <!-- Step 1: Email Input -->
        <div id="step1">
          <div class="space-y-4">
            <div>
              <label for="resetEmail" class="block text-sm font-semibold text-white">
                <i class="fas fa-envelope mr-2 text-blue-400"></i>Email Address
              </label>
              <input type="email" id="resetEmail" class="input-field w-full px-4 py-4 rounded-xl focus:outline-none text-gray-800 font-medium" placeholder="Enter your email address" />
            </div>
            
            <button id="sendCodeBtn" class="btn-primary w-full text-white py-4 px-6 rounded-xl font-bold text-lg shadow-lg">
              <i class="fas fa-paper-plane mr-2"></i>
              Send Verification Code
            </button>
          </div>
        </div>
        
        <!-- Step 2: Code Verification -->
        <div id="step2" class="hidden">
          <div class="space-y-4">
            <p class="text-blue-100 text-center">Enter the 6-digit code sent to <span id="emailDisplay" class="font-semibold"></span></p>
            
            <div class="code-input-container">
              <input type="text" maxlength="1" class="code-input" oninput="moveToNext(this)" onkeyup="moveToPrevious(event, this)" />
              <input type="text" maxlength="1" class="code-input" oninput="moveToNext(this)" onkeyup="moveToPrevious(event, this)" />
              <input type="text" maxlength="1" class="code-input" oninput="moveToNext(this)" onkeyup="moveToPrevious(event, this)" />
              <input type="text" maxlength="1" class="code-input" oninput="moveToNext(this)" onkeyup="moveToPrevious(event, this)" />
              <input type="text" maxlength="1" class="code-input" oninput="moveToNext(this)" onkeyup="moveToPrevious(event, this)" />
              <input type="text" maxlength="1" class="code-input" oninput="moveToNext(this)" onkeyup="moveToPrevious(event, this)" />
            </div>
            
            <div class="text-center">
              <p class="text-blue-100">Didn't receive the code? 
                <a id="resendCode" class="resend-link">Resend code</a>
              </p>
              <p id="countdown" class="text-blue-200 text-sm mt-1">Resend available in 01:00</p>
            </div>
            
            <button id="verifyCodeBtn" class="btn-primary w-full text-white py-4 px-6 rounded-xl font-bold text-lg shadow-lg" disabled>
              <i class="fas fa-check-circle mr-2"></i>
              Verify Code
            </button>
          </div>
        </div>
        
        <!-- Step 3: New Password -->
        <div id="step3" class="hidden">
          <div class="space-y-4">
            <div>
              <label for="newPassword" class="block text-sm font-semibold text-white">
                <i class="fas fa-lock mr-2 text-blue-400"></i>New Password
              </label>
              <div class="relative">
                <input type="password" id="newPassword" class="input-field w-full px-4 py-4 rounded-xl focus:outline-none text-gray-800 font-medium pr-12" placeholder="Enter new password" />
                <button type="button" onclick="toggleNewPassword()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-600 hover:text-blue-600 transition-colors">
                  <i class="fas fa-eye" id="toggleNewPasswordIcon"></i>
                </button>
              </div>
            </div>
            
            <div>
              <label for="confirmPassword" class="block text-sm font-semibold text-white">
                <i class="fas fa-lock mr-2 text-blue-400"></i>Confirm Password
              </label>
              <div class="relative">
                <input type="password" id="confirmPassword" class="input-field w-full px-4 py-4 rounded-xl focus:outline-none text-gray-800 font-medium pr-12" placeholder="Confirm new password" />
                <button type="button" onclick="toggleConfirmPassword()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-600 hover:text-blue-600 transition-colors">
                  <i class="fas fa-eye" id="toggleConfirmPasswordIcon"></i>
                </button>
              </div>
            </div>
            
            <button id="resetPasswordBtn" class="btn-primary w-full text-white py-4 px-6 rounded-xl font-bold text-lg shadow-lg">
              <i class="fas fa-sync-alt mr-2"></i>
              Reset Password
            </button>
          </div>
        </div>
        
        <!-- Success Message -->
        <div id="resetSuccess" class="hidden text-center py-8">
          <div class="mb-6">
            <i class="fas fa-check-circle text-green-400 text-5xl"></i>
          </div>
          <h3 class="text-xl font-bold text-white mb-2">Password Reset Successful!</h3>
          <p class="text-blue-100">Your password has been updated successfully. You can now login with your new password.</p>
          
          <button id="goToLogin" class="btn-primary mt-6 text-white py-3 px-6 rounded-xl font-bold shadow-lg">
            <i class="fas fa-sign-in-alt mr-2"></i>
            Go to Login
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Password visibility toggle functions
    function togglePassword() {
      const passwordField = document.getElementById('password');
      const toggleIcon = document.getElementById('toggleIcon');
      if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }
    
    function toggleNewPassword() {
      const passwordField = document.getElementById('newPassword');
      const toggleIcon = document.getElementById('toggleNewPasswordIcon');
      if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }
    
    function toggleConfirmPassword() {
      const passwordField = document.getElementById('confirmPassword');
      const toggleIcon = document.getElementById('toggleConfirmPasswordIcon');
      if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }

    // Login form submission
    document.getElementById('loginForm').addEventListener('submit', function (e) {
      e.preventDefault();
      
      const submitBtn = document.getElementById('submitBtn');
      const submitText = document.getElementById('submitText');
      const formData = new FormData(this);
      
      // Show loading state
      submitBtn.disabled = true;
      submitText.textContent = 'Signing In...';
      hideMessages();
      
      // Send AJAX request to PHP backend
      fetch('process_login.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showSuccess(data.message);
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 1500);
        } else {
          showError(data.message);
          resetSubmitButton();
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showError('An error occurred. Please try again.');
        resetSubmitButton();
      });
    });

    function resetSubmitButton() {
      const submitBtn = document.getElementById('submitBtn');
      const submitText = document.getElementById('submitText');
      submitBtn.disabled = false;
      submitText.textContent = 'Sign In';
    }

    function hideMessages() {
      document.getElementById('error-message').classList.add('hidden');
      document.getElementById('success-message').classList.add('hidden');
    }

    function showError(message) {
      document.getElementById('error-text').textContent = message;
      document.getElementById('error-message').classList.remove('hidden');
      document.getElementById('success-message').classList.add('hidden');
    }

    function showSuccess(message) {
      document.getElementById('success-text').textContent = message;
      document.getElementById('success-message').classList.remove('hidden');
      document.getElementById('error-message').classList.add('hidden');
    }
    
    // Forgot password functionality
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const closeModal = document.getElementById('closeModal');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    const resetSuccess = document.getElementById('resetSuccess');
    const sendCodeBtn = document.getElementById('sendCodeBtn');
    const verifyCodeBtn = document.getElementById('verifyCodeBtn');
    const resetPasswordBtn = document.getElementById('resetPasswordBtn');
    const resendCode = document.getElementById('resendCode');
    const countdown = document.getElementById('countdown');
    const emailDisplay = document.getElementById('emailDisplay');
    const goToLogin = document.getElementById('goToLogin');
    
    let countdownTimer;
    let countdownTime = 60;
    
    // Open modal when Forgot Password is clicked
    forgotPasswordLink.addEventListener('click', function(e) {
      e.preventDefault();
      forgotPasswordModal.classList.remove('hidden');
      resetForgotPasswordFlow();
    });
    
    // Close modal
    closeModal.addEventListener('click', function() {
      forgotPasswordModal.classList.add('hidden');
      clearInterval(countdownTimer);
    });
    
    // Start the forgot password process
    sendCodeBtn.addEventListener('click', function() {
      const email = document.getElementById('resetEmail').value;
      
      if (!validateEmail(email)) {
        showModalError('Please enter a valid email address');
        return;
      }
      
      // Show loading state
      sendCodeBtn.disabled = true;
      sendCodeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
      
      // Simulate API call to send verification code
      setTimeout(() => {
        // In a real application, you would make an AJAX call to your server
        // to send the verification code to the user's email
        simulateSendVerificationCode(email);
      }, 1500);
    });
    
    // Verify code button
    verifyCodeBtn.addEventListener('click', function() {
      const code = getEnteredCode();
      
      // Simulate code verification
      verifyCodeBtn.disabled = true;
      verifyCodeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Verifying...';
      
      setTimeout(() => {
        // In a real application, you would verify the code with your server
        if (code === '123456') { // Default code for demo
          step2.classList.add('hidden');
          step3.classList.remove('hidden');
        } else {
          showModalError('Invalid verification code. Please try again.');
          verifyCodeBtn.disabled = false;
          verifyCodeBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Verify Code';
        }
      }, 1500);
    });
    
    // Resend code
    resendCode.addEventListener('click', function() {
      if (resendCode.classList.contains('disabled')) return;
      
      const email = document.getElementById('resetEmail').value;
      resendCode.classList.add('disabled');
      startCountdown();
      
      // Simulate resending code
      simulateSendVerificationCode(email);
    });
    
    // Reset password
    resetPasswordBtn.addEventListener('click', function() {
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      
      if (newPassword.length < 6) {
        showModalError('Password must be at least 6 characters long');
        return;
      }
      
      if (newPassword !== confirmPassword) {
        showModalError('Passwords do not match');
        return;
      }
      
      // Show loading state
      resetPasswordBtn.disabled = true;
      resetPasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resetting...';
      
      // Simulate password reset API call
      setTimeout(() => {
        step3.classList.add('hidden');
        resetSuccess.classList.remove('hidden');
      }, 1500);
    });
    
    // Go to login after successful reset
    goToLogin.addEventListener('click', function() {
      forgotPasswordModal.classList.add('hidden');
      showSuccess('Your password has been reset successfully. You can now login with your new password.');
    });
    
    // Helper functions
    function validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    }
    
    function simulateSendVerificationCode(email) {
      // This is a simulation - in a real app, you'd call your backend
      console.log(`Simulating sending verification code to ${email}`);
      
      // Show success message (in a real app, this would come from the server)
      setTimeout(() => {
        step1.classList.add('hidden');
        step2.classList.remove('hidden');
        emailDisplay.textContent = email;
        startCountdown();
        
        // Reset button state
        sendCodeBtn.disabled = false;
        sendCodeBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Send Verification Code';
      }, 1500);
    }
    
    function startCountdown() {
      countdownTime = 60;
      resendCode.classList.add('disabled');
      resendCode.style.pointerEvents = 'none';
      resendCode.style.opacity = '0.5';
      
      countdown.textContent = `Resend available in 01:00`;
      
      clearInterval(countdownTimer);
      countdownTimer = setInterval(() => {
        countdownTime--;
        
        const minutes = Math.floor(countdownTime / 60);
        const seconds = countdownTime % 60;
        
        countdown.textContent = `Resend available in ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (countdownTime <= 0) {
          clearInterval(countdownTimer);
          resendCode.classList.remove('disabled');
          resendCode.style.pointerEvents = 'auto';
          resendCode.style.opacity = '1';
          countdown.textContent = '';
        }
      }, 1000);
    }
    
    function getEnteredCode() {
      const inputs = document.querySelectorAll('.code-input');
      let code = '';
      inputs.forEach(input => {
        code += input.value;
      });
      return code;
    }
    
    function moveToNext(input) {
      if (input.value.length >= input.maxLength) {
        let next = input.nextElementSibling;
        while (next) {
          if (next.classList.contains('code-input')) {
            next.focus();
            break;
          }
          next = next.nextElementSibling;
        }
      }
      
      // Enable verify button if all fields are filled
      const allFilled = Array.from(document.querySelectorAll('.code-input')).every(i => i.value !== '');
      verifyCodeBtn.disabled = !allFilled;
    }
    
    function moveToPrevious(e, input) {
      if (e.key === 'Backspace' && input.value === '') {
        let prev = input.previousElementSibling;
        while (prev) {
          if (prev.classList.contains('code-input')) {
            prev.focus();
            break;
          }
          prev = prev.previousElementSibling;
        }
      }
    }
    
    function showModalError(message) {
      // Create or show error message in modal
      let errorDiv = document.querySelector('.modal-error');
      if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'modal-error bg-red-500 bg-opacity-20 border border-red-500 text-red-100 p-4 mb-6 rounded-xl';
        forgotPasswordModal.querySelector('.glass-card').insertBefore(errorDiv, forgotPasswordModal.querySelector('.glass-card').firstChild);
      }
      
      errorDiv.innerHTML = `
        <div class="flex items-center">
          <i class="fas fa-exclamation-circle mr-3"></i>
          <span>${message}</span>
        </div>
      `;
      errorDiv.classList.remove('hidden');
      
      // Auto hide after 5 seconds
      setTimeout(() => {
        errorDiv.classList.add('hidden');
      }, 5000);
    }
    
    function resetForgotPasswordFlow() {
      // Reset all steps
      step1.classList.remove('hidden');
      step2.classList.add('hidden');
      step3.classList.add('hidden');
      resetSuccess.classList.add('hidden');
      
      // Clear inputs
      document.getElementById('resetEmail').value = '';
      document.querySelectorAll('.code-input').forEach(input => input.value = '');
      document.getElementById('newPassword').value = '';
      document.getElementById('confirmPassword').value = '';
      
      // Reset buttons
      sendCodeBtn.disabled = false;
      sendCodeBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Send Verification Code';
      verifyCodeBtn.disabled = true;
      verifyCodeBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Verify Code';
      resetPasswordBtn.disabled = false;
      resetPasswordBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Reset Password';
      
      // Clear any errors
      const errorDiv = document.querySelector('.modal-error');
      if (errorDiv) errorDiv.classList.add('hidden');
      
      // Clear countdown
      clearInterval(countdownTimer);
      countdown.textContent = '';
      resendCode.classList.remove('disabled');
      resendCode.style.pointerEvents = 'auto';
      resendCode.style.opacity = '1';
    }
  </script>
</body>
</html>