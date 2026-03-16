<?php
require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasRole('Client')) {
    die('Access Denied: Client role required');
}

// Get KYC data from database
$kycData = null;
$verificationTimeline = [];

try {
    // Get current user from session
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $db = service('database');
        
        // Fetch client data with KYC status
        $query = "SELECT * FROM kyc_verification WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
        $kycData = $db->fetchOne($query, [$userId]);
        
        // Fetch all KYC records for timeline
        $query = "SELECT * FROM kyc_verification WHERE user_id = ? ORDER BY created_at DESC";
        $verificationTimeline = $db->fetchAll($query, [$userId]) ?? [];
    }
} catch (Exception $e) {
    error_log("KYC fetch error: " . $e->getMessage());
}

// Determine KYC status
$kycStatus = $kycData['kyc_status'] ?? 'pending';
$statusColor = 'orange';
$statusIcon = 'bx-time';
$statusText = 'Pending Review';

if ($kycStatus === 'verified') {
    $statusColor = 'green';
    $statusIcon = 'bx-shield-check';
    $statusText = 'Verified';
} elseif ($kycStatus === 'rejected') {
    $statusColor = 'red';
    $statusIcon = 'bx-x-circle';
    $statusText = 'Rejected';
}

// Calculate completion percentage
$completionPercent = 0;
if ($kycData) {
    $requiredFields = ['full_name', 'id_type', 'id_number', 'date_of_birth'];
    $completedFields = 0;
    foreach ($requiredFields as $field) {
        if (!empty($kycData[$field])) {
            $completedFields++;
        }
    }
    $completionPercent = round(($completedFields / count($requiredFields)) * 100);
}

// Prepare variables for display
$updatedDate = isset($kycData['updated_at']) ? date('M d, Y', strtotime($kycData['updated_at'])) : 'Never';
$nextStepsText = ($kycStatus === 'pending' ? 'Awaiting submission' : ($kycStatus === 'verified' ? 'Complete!' : 'Needs update'));
$stepClass2 = ($completionPercent > 0 ? 'in-progress' : 'pending');
$stepClass3 = ($kycStatus === 'verified' ? 'completed' : 'pending');

// Prepare requirement card data
$requirementFullName = (!empty($kycData['full_name']) ? htmlspecialchars($kycData['full_name']) : 'Not provided');
$requirementFullNameStatus = (!empty($kycData['full_name']) ? '<span class="badge badge-success"><i class="bx bx-check"></i> Provided</span>' : '<span class="badge badge-gray">Required</span>');
$requirementFullNameClass = (!empty($kycData['full_name']) ? 'completed' : '');

$requirementIdType = (!empty($kycData['id_type']) ? htmlspecialchars($kycData['id_type']) : 'Not provided');
$requirementIdTypeStatus = (!empty($kycData['id_type']) ? '<span class="badge badge-success"><i class="bx bx-check"></i> Provided</span>' : '<span class="badge badge-gray">Required</span>');
$requirementIdTypeClass = (!empty($kycData['id_type']) ? 'completed' : '');

$requirementIdNumber = (!empty($kycData['id_number']) ? htmlspecialchars($kycData['id_number']) : 'Not provided');
$requirementIdNumberStatus = (!empty($kycData['id_number']) ? '<span class="badge badge-success"><i class="bx bx-check"></i> Provided</span>' : '<span class="badge badge-gray">Required</span>');
$requirementIdNumberClass = (!empty($kycData['id_number']) ? 'completed' : '');

$requirementDOB = (!empty($kycData['date_of_birth']) ? date('M d, Y', strtotime($kycData['date_of_birth'])) : 'Not provided');
$requirementDOBStatus = (!empty($kycData['date_of_birth']) ? '<span class="badge badge-success"><i class="bx bx-check"></i> Provided</span>' : '<span class="badge badge-gray">Required</span>');
$requirementDOBClass = (!empty($kycData['date_of_birth']) ? 'completed' : '');

// Build timeline HTML
$timelineHtml = '';
if (count($verificationTimeline) > 0) {
    foreach ($verificationTimeline as $record) {
        $status = $record['kyc_status'] ?? 'pending';
        $date = isset($record['updated_at']) ? date('M d, Y · g:i A', strtotime($record['updated_at'])) : 'N/A';
        $statusLabel = ucfirst($status);
        $timeline_icon = $status === 'verified' ? 'bx-shield-check' : ($status === 'rejected' ? 'bx-x-circle' : 'bx-time');
        $timeline_color = $status === 'verified' ? 'green' : ($status === 'rejected' ? 'red' : 'orange');
        $comment = (!empty($record['comment']) ? '<p class="timeline-comment">' . htmlspecialchars($record['comment']) . '</p>' : '');
        
        $timelineHtml .= "
          <div class=\"timeline-item timeline-{$timeline_color}\">
            <div class=\"timeline-marker\">
              <i class='bx {$timeline_icon}'></i>
            </div>
            <div class=\"timeline-content\">
              <div class=\"timeline-header\">
                <h4>Verification {$statusLabel}</h4>
                <span class=\"timeline-date\">{$date}</span>
              </div>
              {$comment}
            </div>
          </div>
        ";
    }
}

$timelineSection = '';
if (count($verificationTimeline) > 0) {
    $timelineSection = "
  <div class=\"kyc-section\">
    <div class=\"section-header\">
      <h2><i class='bx bx-time'></i> Verification Timeline</h2>
    </div>
    <div class=\"timeline\">
      {$timelineHtml}
    </div>
  </div>";
}

$page_content = <<<'HTML'
<div class="page-content">

  <!-- Page Header with Status Banner -->
  <div class="kyc-header">
    <div class="kyc-header-left">
      <div class="kyc-header-icon">
        <i class='bx bx-id-card'></i>
      </div>
      <div class="kyc-header-text">
        <h1>KYC Identity Verification</h1>
        <p>Complete your Know Your Customer verification to unlock full platform features</p>
      </div>
    </div>
    <div class="kyc-status-badge kyc-status-STATUSCOLOR">
      <i class='bx STATUSICON'></i>
      <span>STATUSTEXT</span>
    </div>
  </div>

  <!-- Progress Overview -->
  <div class="kyc-progress-card">
    <div class="progress-header">
      <h3>Verification Progress</h3>
      <span class="progress-percent">COMPLETIONPERCENT%</span>
    </div>
    <div class="progress-bar-wrapper">
      <div class="progress-bar">
        <div class="progress-fill" style="width: COMPLETIONPERCENT%"></div>
      </div>
    </div>
    <div class="progress-steps">
      <div class="step completed">
        <div class="step-circle">✓</div>
        <div class="step-text">Account Created</div>
      </div>
      <div class="step STEPCLASS2">
        <div class="step-circle">2</div>
        <div class="step-text">Submit Documents</div>
      </div>
      <div class="step STEPCLASS3">
        <div class="step-circle">3</div>
        <div class="step-text">Verification</div>
      </div>
      <div class="step STEPCLASS3">
        <div class="step-circle">4</div>
        <div class="step-text">Approved</div>
      </div>
    </div>
  </div>

  <!-- Stats Grid -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-file'></i></div>
      <div class="stat-info">
        <div class="stat-label">Documents Submitted</div>
        <div class="stat-value">COMPLETIONPERCENT%</div>
        <div class="stat-sub">Form completion</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-shield-check'></i></div>
      <div class="stat-info">
        <div class="stat-label">Verification Status</div>
        <div class="stat-value" style="color: var(--STATUSCOLOR);">
          <i class='bx STATUSICON'></i>
        </div>
        <div class="stat-sub">STATUSTEXT</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-time'></i></div>
      <div class="stat-info">
        <div class="stat-label">Updated</div>
        <div class="stat-value" style="font-size: 0.9rem;">
          UPDATEDDATE
        </div>
        <div class="stat-sub">Last modification</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-info-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Next Steps</div>
        <div class="stat-value" style="font-size: 0.85rem;">
          NEXTSTEPSTEXT
        </div>
        <div class="stat-sub">Action needed</div>
      </div>
    </div>
  </div>

  <!-- Required Information Section -->
  <div class="kyc-section">
    <div class="section-header">
      <h2><i class='bx bx-check-double'></i> Required Information</h2>
    </div>
    <div class="requirement-cards">
      <div class="requirement-card REQUIREMENTFULLNAMECLASS">
        <div class="requirement-icon">
          <i class='bx bx-user'></i>
        </div>
        <div class="requirement-content">
          <h4>Full Name</h4>
          <p>REQUIREMENTFULLNAME</p>
        </div>
        <div class="requirement-status">
          REQUIREMENTFULLNAMESTATUS
        </div>
      </div>

      <div class="requirement-card REQUIREMENTIDTYPECLASS">
        <div class="requirement-icon">
          <i class='bx bx-id-card'></i>
        </div>
        <div class="requirement-content">
          <h4>ID Type</h4>
          <p>REQUIREMENTIDTYPE</p>
        </div>
        <div class="requirement-status">
          REQUIREMENTIDTYPESTATUS
        </div>
      </div>

      <div class="requirement-card REQUIREMENTIDNUMBERCLASS">
        <div class="requirement-icon">
          <i class='bx bx-barcode'></i>
        </div>
        <div class="requirement-content">
          <h4>ID Number</h4>
          <p>REQUIREMENTIDNUMBER</p>
        </div>
        <div class="requirement-status">
          REQUIREMENTIDNUMBERSTATUS
        </div>
      </div>

      <div class="requirement-card REQUIREMENTDOBCLASS">
        <div class="requirement-icon">
          <i class='bx bx-calendar'></i>
        </div>
        <div class="requirement-content">
          <h4>Date of Birth</h4>
          <p>REQUIREMENTDOB</p>
        </div>
        <div class="requirement-status">
          REQUIREMENTDOBSTATUS
        </div>
      </div>
    </div>
    <div class="section-action">
      <a href="portal_kyc_edit.php" class="btn-primary">
        <i class='bx bx-edit'></i> Edit Information
      </a>
    </div>
  </div>

  TIMELINESECTIONPLACEHOLDER

  <!-- Information Cards -->
  <div class="kyc-section">
    <div class="section-header">
      <h2><i class='bx bx-info-circle'></i> Information & Help</h2>
    </div>
    <div class="info-grid">
      <div class="info-card">
        <div class="info-icon" style="background:rgba(59,130,246,0.1);">
          <i class='bx bxs-shield' style="color:var(--blue-600);"></i>
        </div>
        <div class="info-content">
          <h4>Why KYC?</h4>
          <p>Know Your Customer (KYC) verification helps us comply with regulatory requirements and protect your account from fraud.</p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon" style="background:rgba(34,197,94,0.1);">
          <i class='bx bxs-lock' style="color:var(--green);"></i>
        </div>
        <div class="info-content">
          <h4>Data Security</h4>
          <p>Your personal information is encrypted and stored securely. We never share your data with third parties without consent.</p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-icon" style="background:rgba(249,115,22,0.1);">
          <i class='bx bxs-time-five' style="color:var(--orange);"></i>
        </div>
        <div class="info-content">
          <h4>Processing Time</h4>
          <p>Verification typically takes 1-3 business days. You'll receive an email notification once your documents are approved.</p>
        </div>
      </div>
    </div>
  </div>

</div>
HTML;

// Replace placeholders with actual values
$page_content = str_replace('STATUSCOLOR', $statusColor, $page_content);
$page_content = str_replace('STATUSICON', $statusIcon, $page_content);
$page_content = str_replace('STATUSTEXT', $statusText, $page_content);
$page_content = str_replace('COMPLETIONPERCENT', $completionPercent, $page_content);
$page_content = str_replace('STEPCLASS2', $stepClass2, $page_content);
$page_content = str_replace('STEPCLASS3', $stepClass3, $page_content);
$page_content = str_replace('UPDATEDDATE', $updatedDate, $page_content);
$page_content = str_replace('NEXTSTEPSTEXT', $nextStepsText, $page_content);
$page_content = str_replace('REQUIREMENTFULLNAME', $requirementFullName, $page_content);
$page_content = str_replace('REQUIREMENTFULLNAMESTATUS', $requirementFullNameStatus, $page_content);
$page_content = str_replace('REQUIREMENTFULLNAMECLASS', $requirementFullNameClass, $page_content);
$page_content = str_replace('REQUIREMENTIDTYPE', $requirementIdType, $page_content);
$page_content = str_replace('REQUIREMENTIDTYPESTATUS', $requirementIdTypeStatus, $page_content);
$page_content = str_replace('REQUIREMENTIDTYPECLASS', $requirementIdTypeClass, $page_content);
$page_content = str_replace('REQUIREMENTIDNUMBER', $requirementIdNumber, $page_content);
$page_content = str_replace('REQUIREMENTIDNUMBERSTATUS', $requirementIdNumberStatus, $page_content);
$page_content = str_replace('REQUIREMENTIDNUMBERCLASS', $requirementIdNumberClass, $page_content);
$page_content = str_replace('REQUIREMENTDOB', $requirementDOB, $page_content);
$page_content = str_replace('REQUIREMENTDOBSTATUS', $requirementDOBStatus, $page_content);
$page_content = str_replace('REQUIREMENTDOBCLASS', $requirementDOBClass, $page_content);
$page_content = str_replace('TIMELINESECTIONPLACEHOLDER', $timelineSection, $page_content);

include 'layout.php';
?>
?>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #0f246c;
  --blue-500: #3B82F6;
  --blue-600: #2563EB;
  --blue-700: #1E40AF;
  --blue-light: #93C5FD;
  --green: #22c55e;
  --red: #ef4444;
  --orange: #f97316;
  --teal: #14b8a6;
  --gray: #64748b;
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

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text-900);
}

a {
  text-decoration: none;
  color: inherit;
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

/* KYC Header */
.kyc-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 2rem;
  gap: 2rem;
  flex-wrap: wrap;
}

.kyc-header-left {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  flex: 1;
  min-width: 300px;
}

.kyc-header-icon {
  width: 60px;
  height: 60px;
  border-radius: 16px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-500));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 20px rgba(59, 130, 246, 0.35);
}

.kyc-header-icon i {
  font-size: 32px;
  color: #fff;
}

.kyc-header-text h1 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-900);
  line-height: 1.2;
}

.kyc-header-text p {
  font-size: 0.85rem;
  color: var(--text-600);
  margin-top: 6px;
}

.kyc-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.kyc-status-badge i {
  font-size: 20px;
}

.kyc-status-green {
  background: rgba(34, 197, 94, 0.1);
  color: #16a34a;
  border: 1px solid rgba(34, 197, 94, 0.2);
}

.kyc-status-orange {
  background: rgba(249, 115, 22, 0.1);
  color: #c2410c;
  border: 1px solid rgba(249, 115, 22, 0.2);
}

.kyc-status-red {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Progress Card */
.kyc-progress-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--card-shadow);
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.progress-header h3 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-900);
}

.progress-percent {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--blue-600);
}

.progress-bar-wrapper {
  margin-bottom: 2rem;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: rgba(59, 130, 246, 0.1);
  border-radius: 10px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--blue-500), var(--blue-600));
  border-radius: 10px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 0 10px rgba(59, 130, 246, 0.4);
}

.progress-steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 1.5rem;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
  opacity: 0.5;
  transition: opacity 0.3s ease;
}

.step.completed,
.step.in-progress {
  opacity: 1;
}

.step-circle {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  background: rgba(59, 130, 246, 0.1);
  color: var(--blue-600);
  transition: all 0.3s ease;
}

.step.completed .step-circle {
  background: var(--green);
  color: #fff;
  box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.step.in-progress .step-circle {
  background: var(--blue-600);
  color: #fff;
  box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.1);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% {
    box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.1);
  }
  50% {
    box-shadow: 0 0 0 12px rgba(59, 130, 246, 0.05);
  }
}

.step-text {
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-600);
}

/* Stats Grid */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

@media (max-width: 1200px) {
  .stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .stat-grid {
    grid-template-columns: 1fr;
  }
}

.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1.25rem 1.4rem;
  box-shadow: var(--card-shadow);
  display: flex;
  align-items: center;
  gap: 14px;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 28px rgba(59, 130, 246, 0.15);
  border-color: var(--blue-600);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.blue {
  background: rgba(59, 130, 246, 0.12);
}

.stat-icon.blue i {
  color: var(--blue-500);
  font-size: 22px;
}

.stat-icon.green {
  background: rgba(34, 197, 94, 0.12);
}

.stat-icon.green i {
  color: var(--green);
  font-size: 22px;
}

.stat-icon.orange {
  background: rgba(249, 115, 22, 0.12);
}

.stat-icon.orange i {
  color: var(--orange);
  font-size: 22px;
}

.stat-icon.teal {
  background: rgba(20, 184, 166, 0.12);
}

.stat-icon.teal i {
  color: var(--teal);
  font-size: 22px;
}

.stat-info {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-400);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 4px;
}

.stat-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-900);
  display: flex;
  align-items: center;
  gap: 4px;
}

.stat-sub {
  font-size: 0.75rem;
  color: var(--text-400);
  margin-top: 4px;
}

/* KYC Sections */
.kyc-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--card-shadow);
  animation: slideIn 0.5s ease both;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--border);
}

.section-header h2 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-900);
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-header i {
  font-size: 20px;
  color: var(--blue-600);
}

/* Requirement Cards */
.requirement-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.requirement-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 1.5rem;
  border: 2px solid var(--border);
  border-radius: 12px;
  background: #f8f9ff;
  transition: all 0.3s ease;
}

.requirement-card.completed {
  background: rgba(34, 197, 94, 0.05);
  border-color: rgba(34, 197, 94, 0.3);
}

.requirement-card:hover {
  border-color: var(--blue-600);
  transform: translateY(-2px);
}

.requirement-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: rgba(59, 130, 246, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.requirement-icon i {
  font-size: 20px;
  color: var(--blue-600);
}

.requirement-card.completed .requirement-icon {
  background: rgba(34, 197, 94, 0.15);
}

.requirement-card.completed .requirement-icon i {
  color: var(--green);
}

.requirement-content {
  flex: 1;
  min-width: 0;
}

.requirement-content h4 {
  font-weight: 600;
  color: var(--text-900);
  margin-bottom: 4px;
  font-size: 0.95rem;
}

.requirement-content p {
  font-size: 0.85rem;
  color: var(--text-600);
  word-break: break-word;
}

.requirement-status {
  display: flex;
  align-items: center;
  margin-left: auto;
  min-width: fit-content;
}

.section-action {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border);
}

/* Buttons */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-500));
  color: #fff;
  font-weight: 600;
  border-radius: 10px;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
  font-size: 0.95rem;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

/* Timeline */
.timeline {
  position: relative;
  padding-left: 3rem;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 8px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: linear-gradient(to bottom, var(--blue-600), var(--green), var(--orange));
}

.timeline-item {
  position: relative;
  margin-bottom: 1.5rem;
  animation: slideIn 0.5s ease both;
}

.timeline-marker {
  position: absolute;
  left: -3rem;
  top: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface);
  border: 3px solid;
  z-index: 1;
  transition: all 0.3s ease;
}

.timeline-green .timeline-marker {
  border-color: var(--green);
  color: var(--green);
  background: rgba(34, 197, 94, 0.1);
}

.timeline-orange .timeline-marker {
  border-color: var(--orange);
  color: var(--orange);
  background: rgba(249, 115, 22, 0.1);
}

.timeline-red .timeline-marker {
  border-color: var(--red);
  color: var(--red);
  background: rgba(239, 68, 68, 0.1);
}

.timeline-marker i {
  font-size: 16px;
}

.timeline-content {
  padding: 1rem;
  background: #f8f9ff;
  border-left: 3px solid;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.timeline-green .timeline-content {
  border-left-color: var(--green);
  background: rgba(34, 197, 94, 0.05);
}

.timeline-orange .timeline-content {
  border-left-color: var(--orange);
  background: rgba(249, 115, 22, 0.05);
}

.timeline-red .timeline-content {
  border-left-color: var(--red);
  background: rgba(239, 68, 68, 0.05);
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.timeline-header h4 {
  font-weight: 600;
  color: var(--text-900);
  font-size: 0.95rem;
}

.timeline-date {
  font-size: 0.75rem;
  color: var(--text-400);
  font-weight: 500;
}

.timeline-comment {
  font-size: 0.85rem;
  color: var(--text-600);
  margin-top: 8px;
  padding: 8px;
  background: rgba(59, 130, 246, 0.05);
  border-radius: 6px;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
}

.info-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 1.5rem;
  background: #f8f9ff;
  border-radius: 12px;
  border: 1px solid var(--border);
  transition: all 0.3s ease;
}

.info-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(59, 130, 246, 0.12);
  border-color: rgba(59, 130, 246, 0.3);
}

.info-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-icon i {
  font-size: 24px;
}

.info-content h4 {
  font-weight: 600;
  color: var(--text-900);
  margin-bottom: 8px;
  font-size: 0.95rem;
}

.info-content p {
  font-size: 0.85rem;
  color: var(--text-600);
  line-height: 1.6;
}

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  white-space: nowrap;
}

.badge-success {
  background: rgba(34, 197, 94, 0.1);
  color: #16a34a;
}

.badge-gray {
  background: rgba(100, 116, 139, 0.1);
  color: #475569;
}

.badge-green {
  background: rgba(34, 197, 94, 0.1);
  color: #16a34a;
}

.badge-orange {
  background: rgba(249, 115, 22, 0.1);
  color: #c2410c;
}

.badge-red {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

/* Responsive */
@media (max-width: 768px) {
  .kyc-header {
    flex-direction: column;
  }

  .kyc-header-left {
    flex-direction: column;
    align-items: flex-start;
  }

  .kyc-status-badge {
    align-self: flex-start;
  }

  .requirement-cards {
    grid-template-columns: 1fr;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .timeline {
    padding-left: 2rem;
  }

  .timeline-marker {
    left: -2.4rem;
  }

  .progress-steps {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>