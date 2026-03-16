# Navigation Filtering Troubleshooting Guide

## Quick Test Steps

### 1. **Check Your Session Role**
Visit this page while logged in as a client:
- **URL**: `http://localhost/Micro_Financial/Micro_Financial-main/debug-navigation.php`

This page will show you:
- ✅ What role your session has
- ✅ Which filtering condition is being matched
- ✅ How many navigation sections you should see
- ✅ Which sections should be visible

### 2. **Expected Behavior by Role**

| Role | Should See | Sections |
|------|-----------|----------|
| **Client** | Client portal only | 1: MY ACCOUNT |
| **Admin/Administrator** | Everything | 4: CT1, CT2, CT3, CT4 |
| **Portfolio Manager** | Management sections | 2: CT1, CT2 |
| **Compliance Officer** | Oversight sections | 3: CT1, CT2, CT3 |
| **Staff/Officers** | Operations sections | 2: CT1, CT3 |

### 3. **Clear Your Browser Cache**
If sections don't disappear after login, clear your browser cache:
- **Chrome**: Ctrl+Shift+Delete → Clear Browsing Data → Cache
- **Firefox**: Ctrl+Shift+Delete → Cookies and Site Data → Clear
- **Edge**: Ctrl+Shift+Delete → Cookies → Clear

Then refresh the page: **Ctrl+F5** (hard refresh)

### 4. **Check the Error Logs**
Look at your PHP error log:
- Location: `C:/xampp/logs/php_error.log`
- This will show detailed logs about which role was detected and which sections were assigned

### 5. **Test the Complete Flow**

#### Test as Client:
1. Go to: `http://localhost/Micro_Financial/Micro_Financial-main/client_register.php`
2. Register a new account (e.g., `testclient@test.com`)
3. Should be logged in automatically and redirected to `client_portal.php`
4. Check sidebar - should show **ONLY** "MY ACCOUNT" section
5. Visit `debug-navigation.php` - should show "1 section: MY ACCOUNT"

#### Test as Admin:
1. Go to: `http://localhost/Micro_Financial/Micro_Financial-main/auth.php`
2. Login with admin credentials
3. Should be logged in and see all sections in sidebar
4. Visit `debug-navigation.php` - should show "4 sections: CT1, CT2, CT3, CT4"

### 6. **If Still Not Working**

#### Check #1: Session Not Persisting
If `debug-navigation.php` shows "NOT SET" for the role:
- Session is not being saved
- Login might not be completing properly
- Check browser's "Application" → "Cookies" → Look for `PHPSESSID`

#### Check #2: Wrong Role Value
If the role shown is not matching expectations:
- The database might have a different role name than code expects
- Example: Database might have "CLIENT" but code checks for "Client"
- The debug page will show the exact value being compared

#### Check #3: Sections Still Show
If sidebar shows all 4 sections even though debug page shows "1 section":
- This is a **browser cache issue**
- Clear cache and hard refresh (Ctrl+F5)
- Try in an Incognito/Private window

#### Check #4: Default to Staff Role
If debug page shows "2 sections: CT1, CT3" instead of expected role:
- The role value doesn't match any of the specific conditions
- The fallback "Staff" role is being used
- Check the exact role value shown and compare with expected values

### 7. **Debug Variables Available**

These are logged to `C:/xampp/logs/php_error.log`:

```
========== NAVIGATION FILTERING DEBUG ==========
Raw session role: '[value from database]'
Trimmed userRole: '[normalized value]'
Role length: [number]
Role bytes: [hex representation]
all_nav_items keys: [list of available sections]

Condition tests:
  - is_client: TRUE/FALSE
  - is_admin: TRUE/FALSE
  - is_admin_alt: TRUE/FALSE
  - is_portfolio: TRUE/FALSE
  - is_compliance: TRUE/FALSE

✓ MATCHED: [which condition matched]

Final nav_items section count: [number]
Final nav_items sections: [list]
========== END FILTERING ==========
```

### 8. **Common Issues & Solutions**

**Issue: All sections appear for Clients**
- **Likely Cause**: Session role not set as "Client"
- **Solution**: 
  1. Check auth.php line 217: Should set `$_SESSION['role'] = 'Client'`
  2. Visit debug-navigation.php to see what role is actually set
  3. Clear cache and hard refresh

**Issue: Role shows but sections don't match**
- **Likely Cause**: Role string has extra whitespace or different case
- **Solution**:
  1. The debug page shows the hex bytes – look for unexpected characters
  2. layout.php uses `strcasecmp()` which is case-insensitive, so "CLIENT", "client", "Client" all work

**Issue: See "Staff" role when should be "Client"**
- **Likely Cause**: Role value in session is something other than "Client"
- **Solution**:
  1. Check database's users table – what role was assigned?
  2. Check the SQL INSERT statement in UserService.php
  3. Ensure Client role exists in user_roles table

## Files Involved

| File | Purpose | Key Lines |
|------|---------|-----------|
| `layout.php` | Main sidebar & nav filtering | 80-148 |
| `auth.php` | Login & registration | 71, 217 |
| `debug-navigation.php` | Test page you just created | - |
| `UserService.php` | Creates users with roles | - |

## Next Steps

1. **Go to**: `debug-navigation.php`
2. **Log in** as different users or after registration
3. **Take a screenshot** of what you see
4. **Check the error log**: `C:/xampp/logs/php_error.log`
5. **Report the exact values** you see in the debug page

This will help identify exactly where the filtering is breaking down!
