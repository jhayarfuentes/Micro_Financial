# 📊 MICRO_FINANCIAL DATABASE SETUP

## ✅ Connection Configured

Your Supabase PostgreSQL database connection has been configured:

- **Host:** db.lvvfsgkxpulbpwrpyhuf.supabase.co
- **Port:** 5432
- **Database:** postgres
- **User:** postgres
- **Password:** [Configured]

## 🚀 Next Step: Create Database Tables

Your **schema.sql** file contains all 21 tables and 24 indexes needed.

### Option 1: Supabase Web Editor (Recommended ⭐)

1. Go to https://app.supabase.com
2. Login to your account
3. Select project: **lvvfsgkxpulbpwrpyhuf**
4. In left sidebar, click: **SQL Editor**
5. Click: **New Query**
6. Open [schema.sql](schema.sql) and copy ALL contents
7. Paste into the SQL editor
8. Click: **Run** (or Ctrl+Enter)
9. ✅ All tables will be created!

### Option 2: PostgreSQL Client (psql)

If you have PostgreSQL installed, run:

```bash
set PGPASSWORD=Root@040_103
psql -h db.lvvfsgkxpulbpwrpyhuf.supabase.co -p 5432 -U postgres -d postgres -f schema.sql
```

### Option 3: PHP CLI

A PHP setup script is available (requires PHP pgsql extension):

```bash
php setup.php
```

---

## 📋 Database Schema Summary

### 21 Tables Created

#### User Management
- `user_roles` - Role definitions (Admin, Client, Loan Officer, etc.)
- `users` - User accounts with role assignments

#### Client Management
- `clients` - Client profiles
- `kyc_verification` - Know Your Customer verification documents

#### Loan Products
- `loan_applications` - Loan application records
- `loan` - Active loan accounts
- `disbursement` - Loan disbursement transactions
- `installments` - Loan repayment schedule

#### Savings Products
- `savings_accounts` - Client savings accounts
- `savings_transactions` - Deposit/withdrawal transactions

#### Collections & Repayment
- `repayments` - Payment transactions
- `lending_groups` - Group lending programs
- `group_members` - Group membership records

#### institutional Oversight
- `loan_portfolio` - Portfolio analytics
- `savings_collection_monitoring` - Collection metrics
- `fund_allocation` - Fund flow tracking
- `disbursement_tracker` - Disbursement monitoring

#### Compliance & Auditing
- `audit_trail` - System activity logs
- `compliance_audit` - Compliance audit records
- `reports` - Generated reports
- `performance_dashboards` - KPI dashboards

---

## ✨ After Setup Complete

Once all tables are created:

1. Your application will be ready to use
2. Run the application at: http://localhost/Micro_Financial/Micro_Financial-main/dashboard.php
3. Login with credentials created during onboarding
4. Test client registration on: http://localhost/Micro_Financial/Micro_Financial-main/client_register.php

---

## 🔐 Security Notes

- Database password is stored in [config.php](config.php)
- Passwords should not be committed to version control
- Use environment variables in production
- Enable SSL for database connections in production

---

## 📞 Troubleshooting

**Error: "Connection refused"**
- Verify your internet connection
- Check Supabase project is active
- Verify credentials in config.php

**Error: "Permission denied"**
- Confirm you're using postgres user credentials
- Check if the database password is correct

**Tables not created?**
- Try Option 1 (Web Editor) - most reliable
- Check for SQL syntax errors
- Review Supabase logs for details

---

**Status:** ✅ Ready for Database Schema Setup
