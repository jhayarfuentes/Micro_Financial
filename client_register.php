<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Registration — Microfinancial Management System</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>

/* ── Variables ── */
:root {
  --navy:         #0f246c;
  --navy-dark:    #0f3f28;
  --navy-mid:     #168a41;
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
html, body { min-height: 100%; font-family: 'DM Sans', sans-serif; background: var(--bg); }
a { text-decoration: none; color: inherit; }
input, select, button { font-family: inherit; }

/* ── Page Layout ── */
.page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 380px 1fr;
}

/* ── Left Panel ── */
.panel-left {
  background: linear-gradient(145deg, var(--green-dark) 0%, var(--green) 60%, var(--green-light) 100%);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 3rem 2.5rem;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow: hidden;
}

.panel-left::before {
  content: '';
  position: absolute;
  top: -100px; right: -100px;
  width: 380px; height: 380px;
  background: radial-gradient(circle, rgba(59,130,246,0.16) 0%, transparent 70%);
  pointer-events: none;
}

.panel-left::after {
  content: '';
  position: absolute;
  bottom: -80px; left: -60px;
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(30,58,154,0.45) 0%, transparent 70%);
  pointer-events: none;
}

.brand {
  display: flex;
  align-items: center;
  gap: 13px;
  position: relative;
  z-index: 1;
}

.brand-icon {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-400));
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(59,130,246,0.45);
  flex-shrink: 0;
}

.brand-icon i { font-size: 21px; color: #fff; }

.brand-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 12.5px;
  font-weight: 700;
  letter-spacing: 0.12em;
  color: #fff;
  text-transform: uppercase;
  line-height: 1.3;
}

.brand-tagline {
  font-size: 10px;
  color: rgba(255,255,255,0.42);
  margin-top: 1px;
}

/* Steps panel */
.steps-panel {
  position: relative;
  z-index: 1;
}

.steps-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.4rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.25;
  letter-spacing: -0.01em;
  margin-bottom: 0.5rem;
}

.steps-title span {
  background: linear-gradient(90deg, var(--blue-400), var(--blue-light));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.steps-desc {
  font-size: 0.82rem;
  color: rgba(255,255,255,0.45);
  line-height: 1.65;
  margin-bottom: 2rem;
}

/* Step List */
.step-list {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.step-item {
  display: flex;
  gap: 14px;
  position: relative;
}

/* Connecting line */
.step-item:not(:last-child) .step-line {
  position: absolute;
  left: 16px;
  top: 36px;
  width: 2px;
  height: calc(100% - 4px);
  background: rgba(255,255,255,0.1);
}

.step-dot-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 32px;
}

.step-dot {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
  border: 2px solid rgba(255,255,255,0.15);
  background: rgba(255,255,255,0.06);
  color: rgba(255,255,255,0.5);
  transition: all 0.3s;
}

.step-dot.active {
  background: linear-gradient(135deg, var(--blue-600), var(--blue-400));
  border-color: var(--blue-400);
  color: #fff;
  box-shadow: 0 0 0 4px rgba(59,130,246,0.2);
}

.step-dot.done {
  background: rgba(34,197,94,0.15);
  border-color: rgba(34,197,94,0.4);
  color: var(--green);
}

.step-body {
  padding: 4px 0 22px;
}

.step-label {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.85);
  line-height: 1.3;
  margin-bottom: 2px;
}

.step-sublabel {
  font-size: 11px;
  color: rgba(255,255,255,0.35);
}

.panel-footer {
  font-size: 10.5px;
  color: rgba(255,255,255,0.22);
  position: relative;
  z-index: 1;
}

/* ── Right Panel (Scrollable) ── */
.panel-right {
  background: var(--bg);
  padding: 2rem;
  position: relative;
  overflow-y: auto;
}

.panel-right::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image: radial-gradient(rgba(59,130,246,0.05) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
  z-index: 0;
}

/* Content wrapper */
.form-wrap {
  max-width: 680px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
  animation: slideUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.reg-header {
  margin-bottom: 2.5rem;
}

.reg-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: rgba(59,130,246,0.08);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 5px 13px;
  font-size: 11px;
  font-weight: 600;
  color: var(--blue-600);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 1rem;
}

.reg-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.7rem;
  font-weight: 700;
  color: var(--text-900);
  line-height: 1.2;
  letter-spacing: -0.02em;
  margin-bottom: 6px;
}

.reg-subtitle {
  font-size: 0.88rem;
  color: var(--text-400);
  line-height: 1.6;
}

/* Progress Bar */
.progress-bar-outer {
  height: 5px;
  background: var(--border);
  border-radius: 99px;
  margin-bottom: 2.5rem;
  overflow: hidden;
}

.progress-bar-inner {
  height: 100%;
  background: linear-gradient(90deg, var(--blue-700), var(--blue-400));
  border-radius: 99px;
  width: 33%;
  transition: width 0.4s ease;
}

/* Section Card */
.section-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: 0 2px 16px rgba(59,130,246,0.07);
  margin-bottom: 1.5rem;
  overflow: hidden;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 1.1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: rgba(240,244,255,0.5);
}

.section-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  background: rgba(59,130,246,0.1);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.section-icon i { font-size: 18px; color: var(--blue-500); }

.section-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--text-900);
}

.section-sub {
  font-size: 0.76rem;
  color: var(--text-400);
  margin-top: 1px;
}

.section-body {
  padding: 1.5rem;
}

/* Grid layouts */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }

@media (max-width: 600px) {
  .grid-2, .grid-3 { grid-template-columns: 1fr; }
}

/* Form Group */
.form-group { display: flex; flex-direction: column; }
.form-group.span-2 { grid-column: span 2; }

.form-label {
  display: block;
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--text-600);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 6px;
}

.form-label .req {
  color: var(--red);
  margin-left: 2px;
}

.input-wrap { position: relative; }

.input-icon {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 17px;
  color: var(--text-400);
  pointer-events: none;
  transition: color 0.2s;
}

.form-input,
.form-select {
  width: 100%;
  padding: 10px 13px 10px 40px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  background: var(--bg);
  font-size: 0.85rem;
  color: var(--text-900);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  appearance: none;
}

.form-input.no-icon,
.form-select.no-icon {
  padding-left: 13px;
}

.form-input::placeholder { color: var(--text-400); }

.form-input:focus,
.form-select:focus {
  border-color: var(--blue-500);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.input-wrap:focus-within .input-icon { color: var(--blue-500); }

/* Select arrow */
.select-wrap { position: relative; }
.select-arrow {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 16px;
  color: var(--text-400);
  pointer-events: none;
}

/* Password toggle */
.input-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-400);
  font-size: 17px;
  padding: 2px;
  line-height: 1;
  transition: color 0.2s;
}

.input-toggle:hover { color: var(--blue-500); }
.form-input.has-toggle { padding-right: 40px; }

/* Hint text */
.form-hint {
  font-size: 0.72rem;
  color: var(--text-400);
  margin-top: 4px;
  line-height: 1.4;
}

/* Password strength */
.strength-bar {
  display: flex;
  gap: 4px;
  margin-top: 6px;
}

.strength-seg {
  height: 4px;
  flex: 1;
  border-radius: 99px;
  background: var(--border);
  transition: background 0.3s;
}

.strength-seg.weak   { background: var(--red); }
.strength-seg.fair   { background: #f97316; }
.strength-seg.good   { background: #eab308; }
.strength-seg.strong { background: var(--green); }

.strength-label {
  font-size: 0.7rem;
  color: var(--text-400);
  margin-top: 4px;
}

/* Checkbox */
.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 0.82rem;
  color: var(--text-600);
  cursor: pointer;
  user-select: none;
  line-height: 1.5;
}

.checkbox-label input[type="checkbox"] { display: none; }

.checkbox-box {
  width: 17px; height: 17px;
  border: 1.5px solid var(--border);
  border-radius: 5px;
  background: var(--bg);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
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

.terms-link {
  color: var(--blue-600);
  font-weight: 600;
}

/* Bottom Actions */
.form-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 0.5rem;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 11px 20px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-600);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-back:hover {
  border-color: rgba(59,130,246,0.3);
  color: var(--blue-600);
  background: rgba(59,130,246,0.04);
}

.btn-submit {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  padding: 13px 24px;
  background: linear-gradient(135deg, var(--green-700), var(--green));
  color: #160a0a;
  border: none;
  border-radius: 10px;
  font-family: 'Space Grotesk', sans-serif;
  font-size: 0.92rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  cursor: pointer;
  box-shadow: 0 4px 18px rgba(34,197,94,0.35);
  transition: all 0.2s;
}

.btn-submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 7px 26px rgba(34,197,94,0.46);
}

.btn-submit i { font-size: 17px; }

/* Login prompt */
.login-prompt {
  text-align: center;
  font-size: 0.83rem;
  color: var(--text-400);
  margin-top: 1.5rem;
}

.login-prompt a {
  font-weight: 700;
  color: var(--blue-600);
}

.login-prompt a:hover { color: var(--blue-700); }

/* Security note */
.security-note {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(59,130,246,0.04);
  border: 1px solid rgba(59,130,246,0.1);
  border-radius: 10px;
  padding: 10px 13px;
  font-size: 0.74rem;
  color: var(--text-400);
  margin-top: 1.25rem;
  line-height: 1.5;
}

.security-note i { font-size: 15px; color: var(--blue-400); flex-shrink: 0; }

/* Responsive */
@media (max-width: 860px) {
  .page { grid-template-columns: 1fr; }
  .panel-left { display: none; }
  .panel-right { padding: 2rem 1.25rem; }
}

</style>
</head>
<body>

<div class="page">

  <!-- ── Left Panel ── -->
  <div class="panel-left">
    <div class="grid-lines"></div>

    <div class="brand">
      <div class="brand-icon"><i class='bx bx-building-house'></i></div>
      <div>
        <div class="brand-name">MFS · Core Transaction</div>
        <div class="brand-tagline">Microfinancial Management System</div>
      </div>
    </div>

    <div class="steps-panel">
      <div class="steps-title">Create your<br><span>client account</span></div>
      <div class="steps-desc">Complete all sections below to register and gain access to your personal financial portal.</div>

      <div class="step-list">

        <div class="step-item">
          <div class="step-dot-wrap">
            <div class="step-dot active" id="dot-1">1</div>
            <div class="step-line"></div>
          </div>
          <div class="step-body">
            <div class="step-label">Personal Information</div>
            <div class="step-sublabel">Name, birthdate, gender, address</div>
          </div>
        </div>

        <div class="step-item">
          <div class="step-dot-wrap">
            <div class="step-dot" id="dot-2">2</div>
            <div class="step-line"></div>
          </div>
          <div class="step-body">
            <div class="step-label">Contact Details</div>
            <div class="step-sublabel">Phone number, email address</div>
          </div>
        </div>

        <div class="step-item">
          <div class="step-dot-wrap">
            <div class="step-dot" id="dot-3">3</div>
          </div>
          <div class="step-body">
            <div class="step-label">Account Credentials</div>
            <div class="step-sublabel">Create your secure password</div>
          </div>
        </div>

      </div>
    </div>

    <div class="panel-footer">© 2025 Microfinancial Management System. All rights reserved.</div>
  </div>

  <!-- ── Right Panel ── -->
  <div class="panel-right">
    <div class="form-wrap">

      <!-- Header -->
      <div class="reg-header">
        <div class="reg-eyebrow"><i class='bx bx-user-plus'></i> New Client Registration</div>
        <h1 class="reg-title">Tell us about yourself</h1>
        <p class="reg-subtitle">Please fill out all required fields accurately. This information will be used for your KYC verification.</p>
      </div>

      <!-- Progress -->
      <div class="progress-bar-outer">
        <div class="progress-bar-inner" id="progressBar" style="width:33%"></div>
      </div>

      <!-- Form -->
      <form action="#" method="POST" autocomplete="on" id="regForm">

        <!-- ── Section 1: Personal Information ── -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-icon"><i class='bx bx-user'></i></div>
            <div>
              <div class="section-title">Personal Information</div>
              <div class="section-sub">Basic details required for registration</div>
            </div>
          </div>
          <div class="section-body">
            <div class="grid-3" style="margin-bottom:1rem;">
              <!-- First Name -->
              <div class="form-group">
                <label class="form-label">First Name <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-user input-icon'></i>
                  <input type="text" class="form-input" name="first_name" placeholder="Juan" required>
                </div>
              </div>
              <!-- Middle Name -->
              <div class="form-group">
                <label class="form-label">Middle Name</label>
                <div class="input-wrap">
                  <i class='bx bx-user input-icon'></i>
                  <input type="text" class="form-input" name="middle_name" placeholder="Santos">
                </div>
              </div>
              <!-- Last Name -->
              <div class="form-group">
                <label class="form-label">Last Name <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-user input-icon'></i>
                  <input type="text" class="form-input" name="last_name" placeholder="Dela Cruz" required>
                </div>
              </div>
            </div>

            <div class="grid-3">
              <!-- Date of Birth -->
              <div class="form-group">
                <label class="form-label">Date of Birth <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-calendar input-icon'></i>
                  <input type="date" class="form-input" name="dob" required>
                </div>
              </div>
              <!-- Gender -->
              <div class="form-group">
                <label class="form-label">Gender <span class="req">*</span></label>
                <div class="select-wrap">
                  <div class="input-wrap">
                    <i class='bx bx-male-female input-icon'></i>
                    <select class="form-select" name="gender" required>
                      <option value="" disabled selected>Select gender</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                      <option value="prefer_not">Prefer not to say</option>
                    </select>
                  </div>
                  <i class='bx bx-chevron-down select-arrow'></i>
                </div>
              </div>
              <!-- Civil Status -->
              <div class="form-group">
                <label class="form-label">Civil Status <span class="req">*</span></label>
                <div class="select-wrap">
                  <div class="input-wrap">
                    <i class='bx bx-heart input-icon'></i>
                    <select class="form-select" name="civil_status" required>
                      <option value="" disabled selected>Select status</option>
                      <option value="single">Single</option>
                      <option value="married">Married</option>
                      <option value="widowed">Widowed</option>
                      <option value="separated">Separated</option>
                    </select>
                  </div>
                  <i class='bx bx-chevron-down select-arrow'></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Section 2: Address ── -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-icon"><i class='bx bx-map-pin'></i></div>
            <div>
              <div class="section-title">Home Address</div>
              <div class="section-sub">Residential address for records</div>
            </div>
          </div>
          <div class="section-body">
            <div class="form-group" style="margin-bottom:1rem;">
              <label class="form-label">Street Address <span class="req">*</span></label>
              <div class="input-wrap">
                <i class='bx bx-home input-icon'></i>
                <input type="text" class="form-input" name="street" placeholder="123 Sampaguita Street, Brgy. San Jose" required>
              </div>
            </div>
            <div class="grid-3">
              <div class="form-group">
                <label class="form-label">City / Municipality <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-buildings input-icon'></i>
                  <input type="text" class="form-input" name="city" placeholder="Quezon City" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Province <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-map input-icon'></i>
                  <input type="text" class="form-input" name="province" placeholder="Metro Manila" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">ZIP Code <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-code-block input-icon'></i>
                  <input type="text" class="form-input" name="zip" placeholder="1100" maxlength="10" required>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Section 3: Contact Details ── -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-icon"><i class='bx bx-phone'></i></div>
            <div>
              <div class="section-title">Contact Details</div>
              <div class="section-sub">How we can reach you</div>
            </div>
          </div>
          <div class="section-body">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Mobile Number <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-mobile-alt input-icon'></i>
                  <input type="tel" class="form-input" name="mobile" placeholder="09XX XXX XXXX" required>
                </div>
                <span class="form-hint">PH mobile number format</span>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-envelope input-icon'></i>
                  <input type="email" class="form-input" name="email" placeholder="juan@email.com" required>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Section 4: Account Credentials ── -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-icon"><i class='bx bx-lock-alt'></i></div>
            <div>
              <div class="section-title">Account Credentials</div>
              <div class="section-sub">Set up a secure password for portal access</div>
            </div>
          </div>
          <div class="section-body">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Password <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-lock-alt input-icon'></i>
                  <input
                    type="password"
                    class="form-input has-toggle"
                    name="password"
                    id="reg_password"
                    placeholder="Create a strong password"
                    required
                    oninput="checkStrength(this.value)"
                  >
                  <button type="button" class="input-toggle" onclick="togglePassword('reg_password', this)">
                    <i class='bx bx-show'></i>
                  </button>
                </div>
                <!-- Strength Bar -->
                <div class="strength-bar" id="strengthBar">
                  <div class="strength-seg" id="seg1"></div>
                  <div class="strength-seg" id="seg2"></div>
                  <div class="strength-seg" id="seg3"></div>
                  <div class="strength-seg" id="seg4"></div>
                </div>
                <span class="strength-label" id="strengthLabel">Enter a password</span>
              </div>
              <div class="form-group">
                <label class="form-label">Confirm Password <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class='bx bx-lock-open-alt input-icon'></i>
                  <input
                    type="password"
                    class="form-input has-toggle"
                    name="confirm_password"
                    id="confirm_password"
                    placeholder="Re-enter your password"
                    required
                  >
                  <button type="button" class="input-toggle" onclick="togglePassword('confirm_password', this)">
                    <i class='bx bx-show'></i>
                  </button>
                </div>
                <span class="form-hint">Both passwords must match</span>
              </div>
            </div>
            <div style="margin-top:1.25rem;">
              <label class="checkbox-label">
                <input type="checkbox" name="agree_terms" required>
                <span class="checkbox-box"></span>
                I have read and agree to the <a href="#" class="terms-link">Terms and Conditions</a> and <a href="#" class="terms-link">Privacy Policy</a> of the Microfinancial Management System.
              </label>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <a href="client_login.php" class="btn-back">
            <i class='bx bx-arrow-back'></i>
            Back to Login
          </a>
          <button type="submit" class="btn-submit">
            <i class='bx bx-user-check'></i>
            Create My Account
          </button>
        </div>

        <!-- Login Prompt -->
        <div class="login-prompt">
          Already have an account? <a href="client_login.php">Sign in here &rarr;</a>
        </div>

        <!-- Security Note -->
        <div class="security-note">
          <i class='bx bx-shield-check'></i>
          Your personal data is protected under our privacy policy. All information submitted is encrypted and will only be used for identity verification and account management purposes.
        </div>

      </form>
    </div>
  </div>

</div>

<script>
/* ── Password Toggle ── */
function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  input.type = input.type === 'password' ? 'text' : 'password';
  icon.classList.toggle('bx-show');
  icon.classList.toggle('bx-hide');
}

/* ── Password Strength ── */
function checkStrength(val) {
  const segs   = [1,2,3,4].map(n => document.getElementById('seg'+n));
  const label  = document.getElementById('strengthLabel');
  const levels = ['weak','fair','good','strong'];
  const msgs   = ['Too weak','Fair','Good','Strong'];

  let score = 0;
  if (val.length >= 8)            score++;
  if (/[A-Z]/.test(val))          score++;
  if (/[0-9]/.test(val))          score++;
  if (/[^A-Za-z0-9]/.test(val))   score++;

  segs.forEach((s, i) => {
    s.className = 'strength-seg';
    if (i < score) s.classList.add(levels[score - 1]);
  });

  label.textContent = val.length === 0 ? 'Enter a password' : msgs[score - 1] || 'Too weak';
}

/* ── Step dot progress (visual only) ── */
const sections = document.querySelectorAll('.section-card');
const bar      = document.getElementById('progressBar');

function updateProgress() {
  const inputs = document.querySelectorAll('#regForm input[required], #regForm select[required]');
  let filled = 0;
  inputs.forEach(i => { if (i.value.trim()) filled++; });
  const pct = Math.round((filled / inputs.length) * 100);
  bar.style.width = pct + '%';

  // Update dots
  if (pct >= 30)  { document.getElementById('dot-1').classList.add('done'); document.getElementById('dot-2').classList.add('active'); }
  if (pct >= 65)  { document.getElementById('dot-2').classList.add('done'); document.getElementById('dot-3').classList.add('active'); }
}

document.querySelectorAll('#regForm input, #regForm select').forEach(el => {
  el.addEventListener('input', updateProgress);
  el.addEventListener('change', updateProgress);
});

/* ── Form Submission Handler ── */
document.getElementById('regForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const form = e.target;
  const submitBtn = form.querySelector('.btn-submit');
  const originalText = submitBtn.innerHTML;
  
  try {
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Creating Account...';
    
    // Collect form data
    const formData = {
      first_name: form.querySelector('input[name="first_name"]')?.value || '',
      middle_name: form.querySelector('input[name="middle_name"]')?.value || '',
      last_name: form.querySelector('input[name="last_name"]')?.value || '',
      email: form.querySelector('input[name="email"]')?.value || '',
      mobile: form.querySelector('input[name="mobile"]')?.value || '',
      password: form.querySelector('input[name="password"]')?.value || '',
      gender: form.querySelector('select[name="gender"]')?.value || '',
      date_of_birth: form.querySelector('input[name="dob"]')?.value || '',
      civil_status: form.querySelector('select[name="civil_status"]')?.value || '',
      street: form.querySelector('input[name="street"]')?.value || '',
      city: form.querySelector('input[name="city"]')?.value || '',
      province: form.querySelector('input[name="province"]')?.value || '',
      zip: form.querySelector('input[name="zip"]')?.value || ''
    };
    
    // Validate required fields
    const requiredFields = ['first_name', 'last_name', 'email', 'mobile', 'password', 'gender', 'date_of_birth', 'street', 'city', 'province', 'zip'];
    const missingFields = requiredFields.filter(field => !formData[field] || formData[field].trim() === '');
    if (missingFields.length > 0) {
      throw new Error('Please fill in all required fields: ' + missingFields.join(', '));
    }
    
    // Validate passwords match
    const confirmPassword = form.querySelector('input[name="confirm_password"]')?.value;
    if (!confirmPassword) {
      throw new Error('Please confirm your password');
    }
    if (formData.password !== confirmPassword) {
      throw new Error('Passwords do not match');
    }
    
    // Validate password strength
    if (formData.password.length < 8) {
      throw new Error('Password must be at least 8 characters');
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(formData.email)) {
      throw new Error('Please enter a valid email address');
    }
    
    // Send registration request
    const response = await fetch('auth.php?action=client_register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formData)
    });
    
    const result = await response.json();
    
    if (response.ok || response.status === 201) {
      // Success - go straight to client portal (skip session diagnostic)
      alert('Account created successfully! Welcome to Microfinancial Management System.\n\nYour role should be: Client');
      
      // Log the response for debugging
      console.log('Registration response:', result);
      console.log('User role:', result.data?.user?.role_name);
      
      // Directly redirect to client portal
      window.location.href = 'client_portal.php';
    } else {
      // Extract error message from response
      const errorMessage = result.message || result.error || 'Registration failed';
      throw new Error(errorMessage);
    }
  } catch (error) {
    console.error('Registration error:', error);
    
    // Show more helpful error message
    let userMessage = error.message;
    if (error.message && error.message.includes('Cannot read')) {
      userMessage = 'One or more form fields are missing or improperly configured. Please reload the page and try again.';
    }
    
    alert('Registration Error: ' + userMessage);
    
    // Restore button state
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
  }
});
</script>

</body>
</html>