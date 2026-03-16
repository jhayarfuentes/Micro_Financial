# ❌ Invalid Credentials - Root Cause & Fix

## Problem
All test user accounts show "invalid credentials" when attempting to log in.

## Root Cause
The password hashes in `schema.sql` (line ~308) are **incomplete/invalid placeholders**:
```
$2y$10$N9qo8uLOickgx2ZMRZoMye  ← Only 32 characters (needs 60 for valid bcrypt)
```

Valid bcrypt hashes must be **exactly 60 characters** in the format:
```
$2y$10${22-char-salt}{31-char-hash}  ← Total 60 characters
```

## Solution (Choose One)

### ✅ OPTION 1: Run Auto-Fix Script (Recommended - Quickest)
This will update all user account passwords to valid bcrypt hashes matching the test credentials.

**Step 1:** Open in browser:
```
http://localhost/Micro_Financial/Micro_Financial-main/fix-invalid-passwords.php
```

**You should see:**
```json
{
  "success": true,
  "message": "Password hashes updated",
  "updated": 9,
  "failed": 0
}
```

**Step 2:** Test login with any account:
- Username: `admin` / Password: `Admin@123456`
- Username: `teller` / Password: `Teller@123456`
- etc.

---

### ✅ OPTION 2: Run create-users.php Script
This creates new user accounts with proper bcrypt hashes (only runs if users don't exist).

**Step 1:** Open in browser:
```
http://localhost/Micro_Financial/Micro_Financial-main/create-users.php
```

**Step 2:** Check response for successful creations

**Step 3:** Test login with credentials shown in the response

---

### ✅ OPTION 3: Manual Database Update
If you prefer to update the schema directly:

1. Get the valid hashes:
   ```
   http://localhost/Micro_Financial/Micro_Financial-main/generate-hashes.php
   ```

2. Copy the hashes displayed for each user

3. Update `schema.sql` (line ~307-315) with the valid hashes from the page

4. Re-execute the schema in Supabase

---

## Test Credentials After Fix

After running one of the fix options above, test with:

| Username | Password | Role |
|----------|----------|------|
| admin | Admin@123456 | Admin |
| branch_manager | Manager@123456 | Portfolio Manager |
| kyc_officer | KYC@123456 | KYC Officer |
| loan_officer | Loan@123456 | Loan Officer |
| teller | Teller@123456 | Staff/Teller |
| collector | Collector@123456 | Loan Collector |
| savings_officer | Savings@123456 | Savings Officer |
| compliance_officer | Compliance@123456 | Compliance Officer |
| client_demo | Client@123456 | Client |

---

## What Happens After Login?

Each user should see role-appropriate navigation:

- **Admin** → All sections (Client Services, Institutional Oversight, Staff Operations, System Admin)
- **Portfolio Manager** → Client Services + Institutional Oversight
- **Staff Roles** → Client Services + Staff Operations
- **Client** → Client Portal only

---

## Files Related to Authentication

- `client_login.php` - Login form and UI
- `auth.php` - Login API endpoint (handles password verification)
- `config.php` - Global error handlers and logging
- `Database.php` - Supabase REST API wrapper
- `UserService.php` - User authentication service (calls password_verify())
- `schema.sql` - Database schema with user accounts

---

## Why This Happened

The schema.sql was created with placeholder password hashes as a temporary measure. These need to be replaced with:
1. Either proper valid bcrypt hashes, OR
2. Generated through the PHP password_hash() function

The fix scripts automate this process for you.

---

## Next Steps

1. **Immediate:** Run the auto-fix script above (Option 1)
2. **Test:** Log in with any test account
3. **Verify:** Check that navigation displays correctly for your role
4. **Important:** Change passwords in production (these are test credentials)

