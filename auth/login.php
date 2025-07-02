<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - EasyRent</title>
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
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
  <div class="relative z-10 w-full max-w-md">
    <div class="glass-card rounded-3xl p-8 shadow-2xl">
      <div class="flex items-center mb-6 space-x-4">
        <div class="logo-container flex-shrink-0">
          <img src="../assets/images/logo.png" alt="EasyRent Logo" class="w-20 h-20 object-contain" />
        </div>
        <div>
          <h1 class="text-4xl font-bold text-white mb-2 tracking-wide">EasyRent</h1>
          <p class="text-blue-100 text-lg">Find your perfect home</p>
        </div>
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
          <a href="#" class="text-sm text-blue-300 hover:text-blue-100 transition-colors font-medium">Forgot password?</a>
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
            <span class="px-4 bg-transparent text-gray-300">New to EasyRent?</span>
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
      <p class="text-blue-100 text-sm">© 2024 EasyRent. Making property rental simple and secure.</p>
    </div>
  </div>

  <script>
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
  </script>
</body>
</html>