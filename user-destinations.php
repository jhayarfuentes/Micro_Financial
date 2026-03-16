<?php
/**
 * User Account Navigation Destinations
 * Reference guide for all user roles and their accessible pages
 */

$userDestinations = [
    'Admin' => [
        'role' => 'System Administrator',
        'sections' => ['CT1', 'CT2', 'CT3', 'CT4'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT2 - INSTITUTIONAL OVERSIGHT' => [
                'Loan Portfolio & Risk' => 'loan_portfolio.php',
                'Savings & Collection Monitoring' => 'savings_monitoring.php',
                'Disbursement & Fund Tracker' => 'fund_allocation.php',
                'Compliance & Audit Trail' => 'compliance.php',
                'Reports & Performance' => 'reports.php',
                'User Management & RBAC' => 'user_management.php',
            ],
            'CT3 - STAFF OPERATIONS' => [
                'KYC Verification' => 'kyc_verification.php',
                'Loan Approval Review' => 'loan_approval.php',
                'Loan Collection' => 'loan_collection.php',
                'Savings Management' => 'savings_management.php',
                'Compliance Dashboard' => 'compliance_dashboard.php',
            ],
            'CT4 - SYSTEM ADMINISTRATION' => [
                'User Management' => 'user_management.php',
                'Security & Permissions' => 'user_management.php',
                'Database Management' => 'dashboard.php',
                'System Settings' => 'dashboard.php',
            ]
        ]
    ],
    
    'Portfolio Manager' => [
        'role' => 'Portfolio Manager / Branch Manager',
        'sections' => ['CT1', 'CT2'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT2 - INSTITUTIONAL OVERSIGHT' => [
                'Loan Portfolio & Risk' => 'loan_portfolio.php',
                'Savings & Collection Monitoring' => 'savings_monitoring.php',
                'Disbursement & Fund Tracker' => 'fund_allocation.php',
                'Compliance & Audit Trail' => 'compliance.php',
                'Reports & Performance' => 'reports.php',
            ]
        ]
    ],
    
    'Compliance Officer' => [
        'role' => 'Compliance Officer',
        'sections' => ['CT1', 'CT2', 'CT3'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT2 - INSTITUTIONAL OVERSIGHT' => [
                'Loan Portfolio & Risk' => 'loan_portfolio.php',
                'Savings & Collection Monitoring' => 'savings_monitoring.php',
                'Disbursement & Fund Tracker' => 'fund_allocation.php',
                'Compliance & Audit Trail' => 'compliance.php',
                'Reports & Performance' => 'reports.php',
            ],
            'CT3 - STAFF OPERATIONS' => [
                'KYC Verification' => 'kyc_verification.php',
                'Loan Approval Review' => 'loan_approval.php',
                'Loan Collection' => 'loan_collection.php',
                'Savings Management' => 'savings_management.php',
                'Compliance Dashboard' => 'compliance_dashboard.php',
            ]
        ]
    ],
    
    'KYC Officer' => [
        'role' => 'KYC Officer',
        'sections' => ['CT1', 'CT3'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT3 - STAFF OPERATIONS' => [
                'KYC Verification' => 'kyc_verification.php',
                'Loan Approval Review' => 'loan_approval.php',
                'Loan Collection' => 'loan_collection.php',
                'Savings Management' => 'savings_management.php',
                'Compliance Dashboard' => 'compliance_dashboard.php',
            ]
        ]
    ],
    
    'Loan Officer' => [
        'role' => 'Loan Officer',
        'sections' => ['CT1', 'CT3'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT3 - STAFF OPERATIONS' => [
                'KYC Verification' => 'kyc_verification.php',
                'Loan Approval Review' => 'loan_approval.php',
                'Loan Collection' => 'loan_collection.php',
                'Savings Management' => 'savings_management.php',
                'Compliance Dashboard' => 'compliance_dashboard.php',
            ]
        ]
    ],
    
    'Staff / Teller' => [
        'role' => 'General Staff / Teller',
        'sections' => ['CT1', 'CT3'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT3 - STAFF OPERATIONS' => [
                'KYC Verification' => 'kyc_verification.php',
                'Loan Approval Review' => 'loan_approval.php',
                'Loan Collection' => 'loan_collection.php',
                'Savings Management' => 'savings_management.php',
                'Compliance Dashboard' => 'compliance_dashboard.php',
            ]
        ]
    ],
    
    'Loan Collector' => [
        'role' => 'Loan Collector',
        'sections' => ['CT1', 'CT3'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT3 - STAFF OPERATIONS' => [
                'KYC Verification' => 'kyc_verification.php',
                'Loan Approval Review' => 'loan_approval.php',
                'Loan Collection' => 'loan_collection.php',
                'Savings Management' => 'savings_management.php',
                'Compliance Dashboard' => 'compliance_dashboard.php',
            ]
        ]
    ],
    
    'Savings Officer' => [
        'role' => 'Savings Officer',
        'sections' => ['CT1', 'CT3'],
        'destinations' => [
            'CT1 - CLIENT SERVICES' => [
                'Dashboard' => 'dashboard.php',
                'Client Registration & KYC' => 'client_registration.php',
                'Loan Application & Disbursement' => 'loan_application.php',
                'Loan Repayment & Installments' => 'loan_repayment.php',
                'Savings Account Management' => 'savings_account.php',
                'Group Lending & Solidarity' => 'group_lending.php',
                'Client Self-Service Portal' => 'client_portal.php',
            ],
            'CT3 - STAFF OPERATIONS' => [
                'KYC Verification' => 'kyc_verification.php',
                'Loan Approval Review' => 'loan_approval.php',
                'Loan Collection' => 'loan_collection.php',
                'Savings Management' => 'savings_management.php',
                'Compliance Dashboard' => 'compliance_dashboard.php',
            ]
        ]
    ],
    
    'Client' => [
        'role' => 'Regular Client',
        'sections' => ['Client Portal Only'],
        'destinations' => [
            'MY ACCOUNT' => [
                'My Portal' => 'client_portal.php',
                'My Loans' => 'portal_loans.php',
                'My Repayments' => 'portal_repayments.php',
                'My Savings' => 'portal_savings.php',
                'My KYC Status' => 'portal_kyc.php',
            ]
        ]
    ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>User Navigation Destinations</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .user-card { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3B82F6; }
        .user-card h2 { margin: 0 0 5px 0; color: #0F1E4A; }
        .role { font-size: 12px; color: #666; margin-bottom: 10px; }
        .sections { margin: 10px 0; font-weight: bold; color: #3B82F6; }
        .destinations { margin-left: 20px; }
        .destination { padding: 5px 0; font-size: 14px; }
        .destination strong { color: #0F1E4A; }
        .destination code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>📋 User Account Navigation Destinations</h1>
    
    <?php foreach ($userDestinations as $role => $config): ?>
        <div class="user-card">
            <h2><?php echo $role; ?></h2>
            <div class="role">👤 Role: <strong><?php echo $config['role']; ?></strong></div>
            <div class="sections">📍 Accessible Sections: <?php echo implode(', ', $config['sections']); ?></div>
            
            <div class="destinations">
                <?php foreach ($config['destinations'] as $section => $pages): ?>
                    <div style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 3px;">
                        <strong><?php echo $section; ?></strong>
                        <div style="margin-left: 15px; margin-top: 5px;">
                            <?php foreach ($pages as $label => $file): ?>
                                <div class="destination">
                                    • <strong><?php echo $label; ?></strong> → <code><?php echo $file; ?></code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div style="background: #e8f4f8; padding: 15px; margin-top: 20px; border-radius: 5px;">
        <h3>✅ Summary</h3>
        <ul>
            <li>✅ All 9 user roles have been configured</li>
            <li>✅ Each role has appropriate navigation destinations</li>
            <li>✅ Admin has access to all sections + System Administration (CT4)</li>
            <li>✅ Staff roles have access to Client Services (CT1) + Staff Operations (CT3)</li>
            <li>✅ Portfolio Manager has access to Client Services (CT1) + Institutional Oversight (CT2)</li>
            <li>✅ Compliance Officer has access to all sections (CT1, CT2, CT3)</li>
            <li>✅ Clients have limited access to their personal portal</li>
        </ul>
    </div>
</body>
</html>
