<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Login — Microfinancial Management System</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>

/* ── Variables ── */
:root {
  --navy:         #0f246c;
  --navy-dark:    #0f3f28;
  --navy-mid:     #168a41;
  --navy-light:   #24d49a;
  --blue-400:     #60a5fa;
  --blue-500:     #3B82F6;
  --blue-600:     #2563EB;
  --blue-700:     #1E40AF;
  --blue-light:   #93C5FD;
  --green:        #22c55e;
  --green-dark:   #1e7d41;
  --green-light:  #4ade80;
  --bg:           #e8fff0;
  --surface:      #FFFFFF;
  --border:       rgba(59,130,246,0.15);
  --text-900:     #0F1E4A;
  --text-600:     #4B5E8A;
  --text-400:     #8EA0C4;
  --red:          #ef4444;
  --radius:       16px;
  --card-shadow:  0 8px 40px rgba(15,36,108,0.13);
}

/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: 'DM Sans', sans-serif; }
a { text-decoration: none; color: inherit; }
input, button { font-family: inherit; }

/* ── Page Layout ── */
.page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}

/* ── Left Panel ── */
.panel-left {
  background: linear-gradient(145deg, var(--green-dark) 0%, var(--green) 55%, var(--green-light) 100%);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 3rem;
  position: relative;
  overflow: hidden;
}

/* Decorative circles */
.panel-left::before {
  content: '';
  position: absolute;
  top: -120px; right: -120px;
  width: 420px; height: 420px;
  background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, transparent 70%);
  pointer-events: none;
}

.panel-left::after {
  content: '';
  position: absolute;
  bottom: -100px; left: -80px;
  width: 360px; height: 360px;
  background: radial-gradient(circle, rgba(30,58,154,0.45) 0%, transparent 70%);
  pointer-events: none;
}

/* Brand */
.brand {
  display: flex;
  align-items: center;
  gap: 14px;
  position: relative;
  z-index: 1;
}

.brand-icon {
  width: 44px; height: 44px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-400));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 18px rgba(59,130,246,0.45);
  flex-shrink: 0;
}

.brand-icon i { font-size: 22px; color: #fff; }

.brand-text {
  line-height: 1.25;
}

.brand-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.12em;
  color: #fff;
  text-transform: uppercase;
}

.brand-tagline {
  font-size: 10px;
  color: rgba(255,255,255,0.45);
  letter-spacing: 0.04em;
  margin-top: 2px;
}

/* Hero Text */
.hero {
  position: relative;
  z-index: 1;
}

.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: rgba(34,197,94,0.20);
  border: 1px solid rgba(34,197,94,0.45);
  border-radius: 20px;
  padding: 5px 13px;
  font-size: 11px;
  font-weight: 600;
  color: #e9fdf1;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin: 1.5rem 0 1.5rem 0;
}

.hero-eyebrow i { font-size: 13px; }

.hero-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 2.5rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.15;
  margin-bottom: 1rem;
  letter-spacing: -0.02em;
}

.hero-title span {
  background: linear-gradient(90deg, var(--green-light), var(--green));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-desc {
  font-size: 0.92rem;
  color: rgba(255,255,255,0.55);
  line-height: 1.7;
  max-width: 380px;
}

/* Feature Pills */
.feature-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  position: relative;
  z-index: 1;
  margin-top: 2.5rem;
}

.feature-pill {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 12px 16px;
  backdrop-filter: blur(8px);
}

.feature-pill-icon {
  width: 34px; height: 34px;
  border-radius: 9px;
  background: rgba(34,197,94,0.24);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.feature-pill-icon i { font-size: 17px; color: rgba(255,255,255,0.95); }

.feature-pill-text {
  font-size: 13px;
  font-weight: 500;
  color: rgba(255,255,255,0.82);
  line-height: 1.3;
}

.feature-pill-sub {
  font-size: 11px;
  color: rgba(255,255,255,0.38);
  margin-top: 1px;
}

/* Footer note */
.panel-footer {
  font-size: 11px;
  text-align: center;
  margin: 3rem 0 0 0;
  color: rgba(255,255,255,0.25);
  position: relative;
  z-index: 1;
}

/* ── Right Panel ── */
.panel-right {
  background: var(--bg);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 3rem 2rem;
  position: relative;
}

/* Subtle background pattern */
.panel-right::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(59,130,246,0.06) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

/* Form Card */
.form-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 22px;
  box-shadow: var(--card-shadow);
  padding: 2.8rem 2.5rem;
  width: 100%;
  max-width: 440px;
  position: relative;
  z-index: 1;
  animation: slideUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(22px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Form Header */
.form-header {
  margin-bottom: 2rem;
}

.form-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-900);
  line-height: 1.2;
  margin-bottom: 6px;
}

.form-subtitle {
  font-size: 0.85rem;
  color: var(--text-400);
  line-height: 1.5;
}

/* Form Fields */
.form-group {
  margin-bottom: 1.25rem;
}

.form-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-600);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 7px;
}

.input-wrap {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  color: var(--text-400);
  pointer-events: none;
  transition: color 0.2s;
}

.form-input {
  width: 100%;
  padding: 11px 14px 11px 42px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  background: var(--bg);
  font-size: 0.88rem;
  color: var(--text-900);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

.form-input::placeholder { color: var(--text-400); }

.form-input:focus {
  border-color: var(--blue-500);
  background: #fff;
  box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
}

.form-input:focus + .input-icon,
.input-wrap:focus-within .input-icon {
  color: var(--blue-500);
}

/* Password toggle */
.input-toggle {
  position: absolute;
  right: 13px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-400);
  font-size: 18px;
  padding: 2px;
  transition: color 0.2s;
  line-height: 1;
}

.input-toggle:hover { color: var(--blue-500); }

.form-input.has-toggle { padding-right: 42px; }

/* Row */
.form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  gap: 12px;
}

/* Checkbox */
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  color: var(--text-600);
  cursor: pointer;
  user-select: none;
}

.checkbox-label input[type="checkbox"] { display: none; }

.checkbox-box {
  width: 17px; height: 17px;
  border: 1.5px solid var(--border);
  border-radius: 5px;
  background: var(--bg);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}

.checkbox-label input:checked ~ .checkbox-box {
  background: var(--blue-600);
  border-color: var(--blue-600);
}

.checkbox-label input:checked ~ .checkbox-box::after {
  content: '';
  width: 9px; height: 5px;
  border-left: 2px solid #fff;
  border-bottom: 2px solid #fff;
  transform: rotate(-45deg) translateY(-1px);
  display: block;
}

/* Forgot link */
.forgot-link {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--blue-600);
  transition: color 0.15s;
  white-space: nowrap;
}

.forgot-link:hover { color: var(--blue-700); }

/* Submit Button */
.btn-submit {
  width: 100%;
  padding: 13px;
  background: linear-gradient(135deg, var(--green-700), var(--green));
  color: #060202;
  border: none;
  border-radius: 11px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  box-shadow: 0 4px 18px rgba(34,197,94,0.35);
  transition: all 0.2s;
  margin-bottom: 1.5rem;
}

.btn-submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 28px rgba(34,197,94,0.42);
}

.btn-submit:active { transform: translateY(0); }
.btn-submit i { font-size: 18px; }

/* Divider */
.divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 1.5rem;
  font-size: 0.78rem;
  color: var(--text-400);
  font-weight: 500;
}

.divider::before, .divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

/* Register Prompt */
.register-prompt {
  text-align: center;
  font-size: 0.84rem;
  color: var(--text-400);
}

.register-prompt a {
  font-weight: 700;
  color: var(--blue-600);
  transition: color 0.15s;
}

.register-prompt a:hover { color: var(--blue-700); }


@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.7); }
  50%       { box-shadow: 0 0 0 4px rgba(34,197,94,0); }
}

/* Security Note */
.security-note {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(59,130,246,0.05);
  border: 1px solid rgba(59,130,246,0.12);
  border-radius: 10px;
  padding: 10px 13px;
  font-size: 0.76rem;
  color: var(--text-400);
  margin-top: 1.25rem;
}

.security-note i { font-size: 15px; color: var(--blue-400); flex-shrink: 0; }

/* Responsive */
@media (max-width: 900px) {
  .page { grid-template-columns: 1fr; }
  .panel-left { display: none; }
  .panel-right { padding: 2rem 1.25rem; min-height: 100vh; }
}

</style>
</head>
<body>

<div class="page">

  <!-- ── Left Panel ── -->
  <div class="panel-left">
    <div class="grid-lines"></div>

    <!-- Brand -->
    <div class="brand">
      <div class="brand-icon"><i class='bx bx-building-house'></i></div>
      <div class="brand-text">
        <div class="brand-name">MFS · Core Transaction</div>
        <div class="brand-tagline">Microfinancial Management System</div>
      </div>
    </div>

    <!-- Hero -->
    <div class="hero">
      <div class="hero-eyebrow">
        <i class='bx bx-shield-check'></i>
        Secure Client Portal
      </div>
      <h1 class="hero-title">
        Manage your<br><span>finances</span> with<br>confidence.
      </h1>
      <p class="hero-desc">
        Access your loan accounts, savings balance, repayment schedules, and KYC status — all in one secure place, available anytime.
      </p>

      <!-- Features -->
      <div class="feature-list">
        <div class="feature-pill">
          <div class="feature-pill-icon"><i class='bx bx-money'></i></div>
          <div>
            <div class="feature-pill-text">Loan Tracking</div>
            <div class="feature-pill-sub">Monitor active loans & upcoming payments</div>
          </div>
        </div>
        <div class="feature-pill">
          <div class="feature-pill-icon"><i class='bx bx-wallet'></i></div>
          <div>
            <div class="feature-pill-text">Savings Overview</div>
            <div class="feature-pill-sub">View balances and transaction history</div>
          </div>
        </div>
        <div class="feature-pill">
          <div class="feature-pill-icon"><i class='bx bx-id-card'></i></div>
          <div>
            <div class="feature-pill-text">KYC Status</div>
            <div class="feature-pill-sub">Track your identity verification progress</div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel-footer">© 2025 Microfinancial Management System. All rights reserved.</div>
  </div>

  <!-- ── Right Panel ── -->
  <div class="panel-right">
    <div class="form-card">

      <!-- Header -->
      <div class="form-header">
        <div class="form-title">Welcome back</div>
        <div class="form-subtitle">Sign in to your user account to continue.</div>
      </div>

      <!-- Form -->
      <form id="loginForm" method="POST" autocomplete="on">

        <!-- Username / Email -->
        <div class="form-group">
          <label class="form-label" for="username">Username or Email</label>
          <div class="input-wrap">
            <i class='bx bx-user input-icon'></i>
            <input
              type="text"
              id="username"
              name="username"
              class="form-input"
              placeholder="e.g. jdoe@example.com"
              autocomplete="username"
              required
            >
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrap">
            <i class='bx bx-lock-alt input-icon'></i>
            <input
              type="password"
              id="password"
              name="password"
              class="form-input has-toggle"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            >
            <button type="button" class="input-toggle" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
              <i class='bx bx-show'></i>
            </button>
          </div>
        </div>

        <!-- Remember & Forgot -->
        <div class="form-row">
          <label class="checkbox-label">
            <input type="checkbox" name="remember">
            <span class="checkbox-box"></span>
            Remember me
          </label>
          <a href="client_forgot.php" class="forgot-link">Forgot password?</a>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn-submit">
          <i class='bx bx-log-in-circle'></i>
          Sign In to Portal
        </button>

        <!-- Divider -->
        <div class="divider">Don't have an account?</div>

        <!-- Register Prompt -->
        <div class="register-prompt">
          <a href="client_register.php">Create your client account &rarr;</a>
        </div>

      </form>

      <!-- Security Note -->
      <div class="security-note">
        <i class='bx bx-lock'></i>
        Your connection is encrypted. We never share your personal data with third parties.
      </div>

    </div>
  </div>

</div>

<script>
function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('bx-show', 'bx-hide');
  } else {
    input.type = 'password';
    icon.classList.replace('bx-hide', 'bx-show');
  }
}

/* ── Client Login Handler ── */
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const form = e.target;
  const submitBtn = form.querySelector('.btn-submit');
  const originalText = submitBtn.innerHTML;
  
  try {
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Signing In...';
    
    // Prepare login data
    const loginData = {
      username: form.querySelector('input[name="username"]').value,
      password: form.querySelector('input[name="password"]').value
    };
    
    // Validate inputs
    if (!loginData.username || !loginData.password) {
      throw new Error('Please enter both client ID and password');
    }
    
    // Send login request
    const response = await fetch('auth.php?action=login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(loginData)
    });
    
    // Get raw response text first
    const responseText = await response.text();
    console.log('Raw response:', responseText);
    
    // Try to parse as JSON
    let result;
    try {
      result = responseText ? JSON.parse(responseText) : { status: response.status, success: false, message: 'Empty response from server' };
    } catch (parseError) {
      console.error('JSON parse error:', parseError);
      console.error('Response text was:', responseText);
      throw new Error('Server returned invalid JSON: ' + (responseText ? responseText.substring(0, 100) : 'Empty response'));
    }
    
    if (response.ok) {
      // Success - redirect based on user role
      const role = result.data?.user?.role_name || 'Client';
      
      let redirectPage = 'dashboard.php'; // Default for staff
      
      if (role === 'Client') {
        redirectPage = 'client_portal.php';
      } else if (role === 'Admin' || role === 'Administrator') {
        redirectPage = 'user_management.php';
      } else if (role === 'Portfolio Manager') {
        redirectPage = 'loan_portfolio.php';
      } else if (role === 'Compliance Officer') {
        redirectPage = 'compliance_dashboard.php';
      } else if (role === 'Loan Officer') {
        redirectPage = 'loan_approval.php';
      } else if (role === 'KYC Officer') {
        redirectPage = 'kyc_verification.php';
      } else if (role === 'Loan Collector') {
        redirectPage = 'loan_collection.php';
      } else if (role === 'Savings Officer') {
        redirectPage = 'savings_management.php';
      } else if (role === 'Staff') {
        redirectPage = 'dashboard.php';
      }
      
      window.location.href = redirectPage;
    } else {
      throw new Error(result.message || 'Login failed');
    }
  } catch (error) {
    console.error('Login error:', error);
    alert('Login Error: ' + error.message);
    
    // Restore button state
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
  }
});
</script>

</body>
</html>