<?php
// Handle form submission
$submissionMessage = '';
$submissionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new Exception('User not authenticated');
        }
        
        // Get form data
        $fullName = $_POST['full_name'] ?? '';
        $idType = $_POST['id_type'] ?? '';
        $idNumber = $_POST['id_number'] ?? '';
        $dateOfBirth = $_POST['date_of_birth'] ?? '';
        $address = $_POST['address'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        // Validation
        if (empty($fullName) || empty($idType) || empty($idNumber) || empty($dateOfBirth)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Update KYC record
        require_once __DIR__ . '/init.php';
        
        $query = "UPDATE kyc_verification SET 
            full_name = ?, 
            id_type = ?, 
            id_number = ?, 
            date_of_birth = ?, 
            address = ?, 
            phone = ?,
            updated_at = NOW()
            WHERE user_id = ?";
            
        $GLOBALS['db']->execute($query, [
            $fullName, $idType, $idNumber, $dateOfBirth, $address, $phone, $userId
        ]);
        
        $submissionMessage = 'Your KYC information has been successfully updated!';
    } catch (Exception $e) {
        $submissionError = $e->getMessage();
        error_log("KYC update error: " . $e->getMessage());
    }
}

// Fetch existing KYC data
$kycData = [];
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    try {
        require_once __DIR__ . '/init.php';
        $query = "SELECT * FROM kyc_verification WHERE user_id = ? LIMIT 1";
        $kycData = $GLOBALS['db']->fetchOne($query, [$userId]) ?? [];
    } catch (Exception $e) {
        error_log("KYC fetch error: " . $e->getMessage());
    }
}

// Prepare form values to avoid concatenation inside heredoc
$fullNameValue = htmlspecialchars($kycData['full_name'] ?? '');
$dateOfBirthValue = htmlspecialchars($kycData['date_of_birth'] ?? '');
$phoneValue = htmlspecialchars($kycData['phone'] ?? '');
$addressValue = htmlspecialchars($kycData['address'] ?? '');
$idNumberValue = htmlspecialchars($kycData['id_number'] ?? '');
$currentIdType = $kycData['id_type'] ?? '';

// Build select options
$selectOptions = '';
$idTypes = ['National ID', 'Passport', 'Driver License', 'UMID', 'PRC ID', 'Voter ID'];
foreach ($idTypes as $type) {
    $selected = ($currentIdType === $type) ? 'selected' : '';
    $selectOptions .= "<option value=\"{$type}\" {$selected}>{$type}</option>\n";
}

// Build success alert
$successAlert = '';
if ($submissionMessage) {
    $successAlert = <<<'ALERT'
<div class="alert alert-success">
  <div class="alert-icon"><i class='bx bx-check-circle'></i></div>
  <div class="alert-content">
    <h4>Success</h4>
    <p>MESSAGEHOLDER</p>
  </div>
</div>
ALERT;
    $successAlert = str_replace('MESSAGEHOLDER', $submissionMessage, $successAlert);
}

// Build error alert
$errorAlert = '';
if ($submissionError) {
    $errorAlert = <<<'ALERT'
<div class="alert alert-error">
  <div class="alert-icon"><i class='bx bx-x-circle'></i></div>
  <div class="alert-content">
    <h4>Error</h4>
    <p>ERRORHOLDER</p>
  </div>
</div>
ALERT;
    $errorAlert = str_replace('ERRORHOLDER', $submissionError, $errorAlert);
}

$page_content = <<<'HTML'

<div class="page-content">

  <div class="kyc-edit-header">
    <a href="portal_kyc.php" class="back-link">
      <i class='bx bx-arrow-back'></i> Back to KYC Status
    </a>
    <div class="kyc-edit-title">
      <h1>Edit KYC Information</h1>
      <p>Update your identity verification details</p>
    </div>
  </div>

  ALERTSPLACEHOLDER

  <div class="kyc-form-card">
    <form method="POST" class="kyc-form">

      <!-- Basic Information -->
      <div class="form-section">
        <div class="section-title">
          <i class='bx bx-user'></i>
          <h2>Personal Information</h2>
        </div>

        <div class="form-group">
          <label for="full_name">Full Name *</label>
          <input 
            type="text" 
            id="full_name" 
            name="full_name" 
            class="form-input" 
            value="FULLNAMEVALUE"
            placeholder="Enter your full name"
            required
          >
          <small>Your full legal name as it appears on your ID</small>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="date_of_birth">Date of Birth *</label>
            <input 
              type="date" 
              id="date_of_birth" 
              name="date_of_birth" 
              class="form-input" 
              value="DATEOFBIRTHVALUE"
              required
            >
          </div>

          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input 
              type="tel" 
              id="phone" 
              name="phone" 
              class="form-input" 
              value="PHONEVALUE"
              placeholder="+63 9xx xxx xxxx"
            >
          </div>
        </div>

        <div class="form-group">
          <label for="address">Address</label>
          <textarea 
            id="address" 
            name="address" 
            class="form-input form-textarea" 
            placeholder="Enter your complete address"
          >ADDRESSVALUE</textarea>
        </div>
      </div>

      <!-- Identification -->
      <div class="form-section">
        <div class="section-title">
          <i class='bx bx-id-card'></i>
          <h2>Identification Details</h2>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="id_type">ID Type *</label>
            <select id="id_type" name="id_type" class="form-input" required>
              <option value="">Select ID Type</option>
              SELECTOPTIONS
            </select>
          </div>

          <div class="form-group">
            <label for="id_number">ID Number *</label>
            <input 
              type="text" 
              id="id_number" 
              name="id_number" 
              class="form-input" 
              value="IDNUMBERVALUE"
              placeholder="Enter your ID number"
              required
            >
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <a href="portal_kyc.php" class="btn-secondary">
          <i class='bx bx-x'></i> Cancel
        </a>
        <button type="submit" class="btn-primary">
          <i class='bx bx-save'></i> Save Changes
        </button>
      </div>

    </form>
  </div>

  <!-- Information Card -->
  <div class="kyc-info-box">
    <div class="info-header">
      <i class='bx bx-info-circle'></i>
      <h3>Information</h3>
    </div>
    <ul class="info-list">
      <li>All fields marked with * are required</li>
      <li>Your information will be encrypted and stored securely</li>
      <li>Verification typically takes 1-3 business days</li>
      <li>You'll receive email notification once verified</li>
    </ul>
  </div>

</div>

HTML;

// Replace placeholders with actual values
$page_content = str_replace('ALERTSPLACEHOLDER', $successAlert . $errorAlert, $page_content);
$page_content = str_replace('FULLNAMEVALUE', $fullNameValue, $page_content);
$page_content = str_replace('DATEOFBIRTHVALUE', $dateOfBirthValue, $page_content);
$page_content = str_replace('PHONEVALUE', $phoneValue, $page_content);
$page_content = str_replace('ADDRESSVALUE', $addressValue, $page_content);
$page_content = str_replace('IDNUMBERVALUE', $idNumberValue, $page_content);
$page_content = str_replace('SELECTOPTIONS', $selectOptions, $page_content);

include 'layout.php';
include 'layout.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
  --blue-500: #3B82F6;
  --blue-600: #2563EB;
  --blue-700: #1E40AF;
  --green: #22c55e;
  --red: #ef4444;
  --orange: #f97316;
  --bg: #F0F4FF;
  --surface: #FFFFFF;
  --border: rgba(59, 130, 246, 0.14);
  --text-900: #0F1E4A;
  --text-600: #4B5E8A;
  --text-400: #8EA0C4;
  --radius: 14px;
  --card-shadow: 0 2px 16px rgba(59, 130, 246, 0.08);
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

.page-content {
  padding: 1.5rem;
  animation: fadeIn 0.4s ease both;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Back Link */
.kyc-edit-header {
  margin-bottom: 2rem;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--blue-600);
  font-weight: 600;
  font-size: 0.9rem;
  padding: 8px 12px;
  border-radius: 8px;
  transition: all 0.3s ease;
  margin-bottom: 1rem;
}

.back-link:hover {
  background: rgba(59, 130, 246, 0.1);
  color: var(--blue-700);
}

.kyc-edit-title h1 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-900);
  margin-bottom: 4px;
}

.kyc-edit-title p {
  font-size: 0.9rem;
  color: var(--text-600);
}

/* Alerts */
.alert {
  display: flex;
  gap: 12px;
  padding: 1rem 1.5rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  animation: slideIn 0.4s ease both;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.alert-success {
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #16a34a;
}

.alert-error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #dc2626;
}

.alert-icon {
  font-size: 20px;
  flex-shrink: 0;
  margin-top: 2px;
}

.alert-content h4 {
  font-weight: 600;
  margin-bottom: 2px;
  font-size: 0.95rem;
}

.alert-content p {
  font-size: 0.85rem;
}

/* Form Card */
.kyc-form-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--card-shadow);
}

.kyc-form {
  display: flex;
  flex-direction: column;
  gap: 2.5rem;
}

/* Form Sections */
.form-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--border);
}

.section-title i {
  font-size: 20px;
  color: var(--blue-600);
}

.section-title h2 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-900);
}

/* Form Groups */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-weight: 600;
  color: var(--text-900);
  font-size: 0.9rem;
}

.form-input {
  padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.9rem;
  color: var(--text-900);
  background: #f8f9ff;
  transition: all 0.3s ease;
}

.form-input:focus {
  outline: none;
  border-color: var(--blue-600);
  background: var(--surface);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
  font-family: inherit;
}

.form-group small {
  font-size: 0.75rem;
  color: var(--text-400);
  line-height: 1.4;
}

/* Form Row */
.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}

/* Form Actions */
.form-actions {
  display: flex;
  gap: 1rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border);
  justify-content: flex-end;
}

.btn-primary,
.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  font-weight: 600;
  border-radius: 10px;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
  font-size: 0.95rem;
}

.btn-primary {
  background: linear-gradient(135deg, var(--blue-600), var(--blue-500));
  color: #fff;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.btn-secondary {
  background: transparent;
  color: var(--text-600);
  border: 1px solid var(--border);
}

.btn-secondary:hover {
  background: rgba(59, 130, 246, 0.05);
  border-color: var(--blue-600);
  color: var(--blue-600);
}

/* Info Box */
.kyc-info-box {
  background: rgba(59, 130, 246, 0.05);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1.5rem;
  border-left: 4px solid var(--blue-600);
}

.info-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 1rem;
}

.info-header i {
  font-size: 18px;
  color: var(--blue-600);
}

.info-header h3 {
  font-weight: 600;
  color: var(--text-900);
  font-size: 0.95rem;
}

.info-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-list li {
  font-size: 0.85rem;
  color: var(--text-600);
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.info-list li::before {
  content: '✓';
  color: var(--green);
  font-weight: 600;
  flex-shrink: 0;
  margin-top: 2px;
}

/* Responsive */
@media (max-width: 768px) {
  .kyc-form-card {
    padding: 1.5rem;
  }

  .form-actions {
    flex-direction: column;
    justify-content: stretch;
  }

  .form-actions button,
  .form-actions a {
    width: 100%;
    justify-content: center;
  }
}
</style>
