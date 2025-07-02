<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - EasyRent</title>
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css"
    rel="stylesheet"
  />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <style>
    body {
      background: linear-gradient(
          135deg,
          rgba(0, 0, 0, 0.8),
          rgba(30, 58, 138, 0.9)
        ),
        url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2073&q=80');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
      border-radius: 2rem;
      max-width: 450px;
      width: 100%;
      padding: 2rem 3rem;
      color: white;
    }

    .input-field {
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid transparent;
      transition: all 0.3s ease;
      color: #1f2937;
    }

    .input-field:focus {
      background: rgba(255, 255, 255, 1);
      border-color: #3b82f6;
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(59, 130, 246, 0.2);
      outline: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #1e40af, #3b82f6);
      transition: all 0.3s ease;
      width: 100%;
      padding: 1rem;
      font-weight: 700;
      border-radius: 1rem;
      border: none;
      color: white;
      cursor: pointer;
      font-size: 1.125rem;
      box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    }

    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
    }

    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    label {
      font-weight: 600;
      margin-bottom: 0.5rem;
      display: block;
      color: white;
    }

    .text-center {
      text-align: center;
    }

    .mb-4 {
      margin-bottom: 1rem;
    }

    .mb-6 {
      margin-bottom: 1.5rem;
    }

    .link {
      color: #60a5fa;
      text-decoration: underline;
      cursor: pointer;
    }

    .link:hover {
      color: #2563eb;
    }

    .error-message, .success-message {
      padding: 0.75rem 1rem;
      border-radius: 1rem;
      margin-bottom: 1rem;
      display: none;
    }

    .error-message {
      background-color: rgba(220, 38, 38, 0.2);
      border: 1px solid rgba(220, 38, 38, 0.5);
      color: #fecaca;
    }

    .success-message {
      background-color: rgba(34, 197, 94, 0.2);
      border: 1px solid rgba(34, 197, 94, 0.5);
      color: #bbf7d0;
    }

    .select-field {
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid transparent;
      transition: all 0.3s ease;
      color: #1f2937;
    }

    .select-field:focus {
      background: rgba(255, 255, 255, 1);
      border-color: #3b82f6;
      outline: none;
    }
  </style>
</head>
<body>
  <div class="glass-card">
    <div class="text-center mb-6">
      <h1 class="text-3xl font-bold">Create Account</h1>
      <p class="text-blue-200 text-sm mt-2">Join EasyRent today</p>
    </div>

    <div id="error" class="error-message">
      <div class="flex items-center">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <span id="error-text"></span>
      </div>
    </div>

    <div id="success" class="success-message">
      <div class="flex items-center">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="success-text"></span>
      </div>
    </div>

    <form id="registrationForm" novalidate>
      <div class="mb-4">
        <label for="username">
          <i class="fas fa-user mr-2 text-blue-400"></i>Username
        </label>
        <input
          type="text"
          id="username"
          name="username"
          class="input-field px-4 py-3 rounded-xl w-full"
          placeholder="Enter your username"
          required
          autocomplete="username"
        />
      </div>

      <div class="mb-4">
        <label for="email">
          <i class="fas fa-envelope mr-2 text-blue-400"></i>Email
        </label>
        <input
          type="email"
          id="email"
          name="email"
          class="input-field px-4 py-3 rounded-xl w-full"
          placeholder="Enter your email"
          required
          autocomplete="email"
        />
      </div>

      <div class="mb-4">
        <label for="user_type">
          <i class="fas fa-user-tag mr-2 text-blue-400"></i>Account Type
        </label>
        <select
          id="user_type"
          name="user_type"
          class="select-field px-4 py-3 rounded-xl w-full"
          required
        >
          <option value="tenant">Tenant (Looking for property)</option>
          <option value="landlord">Landlord (Renting out property)</option>
        </select>
      </div>

      <div class="mb-4">
        <label for="password">
          <i class="fas fa-lock mr-2 text-blue-400"></i>Password
        </label>
        <input
          type="password"
          id="password"
          name="password"
          class="input-field px-4 py-3 rounded-xl w-full"
          placeholder="Enter your password"
          required
          autocomplete="new-password"
        />
        <p class="text-xs text-blue-200 mt-1">Must be at least 8 characters with uppercase, lowercase, and number</p>
      </div>

      <div class="mb-6">
        <label for="confirm_password">
          <i class="fas fa-lock mr-2 text-blue-400"></i>Confirm Password
        </label>
        <input
          type="password"
          id="confirm_password"
          name="confirm_password"
          class="input-field px-4 py-3 rounded-xl w-full"
          placeholder="Confirm your password"
          required
          autocomplete="new-password"
        />
      </div>

      <button type="submit" id="submitBtn" class="btn-primary">
        <i class="fas fa-user-plus mr-2"></i>
        <span id="submitText">Create Account</span>
      </button>
    </form>

    <p class="text-center mt-6 text-blue-200 text-sm">
      Already have an account? 
      <a href="login.php" class="link">Sign in here</a>
    </p>
  </div>

  <script>
    const form = document.getElementById('registrationForm');
    const errorDiv = document.getElementById('error');
    const successDiv = document.getElementById('success');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      hideMessages();

      const username = form.username.value.trim();
      const email = form.email.value.trim();
      const password = form.password.value;
      const confirmPassword = form.confirm_password.value;
      const userType = form.user_type.value;

      // Client-side validation
      if (!username) {
        showError('Username is required');
        return;
      }
      if (!email) {
        showError('Email is required');
        return;
      }
      if (!validateEmail(email)) {
        showError('Please enter a valid email');
        return;
      }
      if (password.length < 8) {
        showError('Password must be at least 8 characters long');
        return;
      }
      if (!validatePassword(password)) {
        showError('Password must contain at least one uppercase letter, one lowercase letter, and one number');
        return;
      }
      if (password !== confirmPassword) {
        showError('Passwords do not match');
        return;
      }

      // Show loading state
      submitBtn.disabled = true;
      submitText.textContent = 'Creating Account...';

      // Create FormData and send to PHP backend
      const formData = new FormData(form);
      
      fetch('process_registration.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showSuccess(data.message);
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 2000);
        } else {
          showError(data.message);
          resetSubmitButton();
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showError('Registration failed. Please try again later.');
        resetSubmitButton();
      });
    });

    function resetSubmitButton() {
      submitBtn.disabled = false;
      submitText.textContent = 'Create Account';
    }

    function hideMessages() {
      errorDiv.style.display = 'none';
      successDiv.style.display = 'none';
    }

    function showError(message) {
      document.getElementById('error-text').textContent = message;
      errorDiv.style.display = 'block';
      successDiv.style.display = 'none';
    }

    function showSuccess(message) {
      document.getElementById('success-text').textContent = message;
      successDiv.style.display = 'block';
      errorDiv.style.display = 'none';
    }

    function validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email.toLowerCase());
    }

    function validatePassword(password) {
      // Check for at least one uppercase, one lowercase, and one number
      const hasUpper = /[A-Z]/.test(password);
      const hasLower = /[a-z]/.test(password);
      const hasNumber = /\d/.test(password);
      return hasUpper && hasLower && hasNumber;
    }
  </script>
</body>
</html>