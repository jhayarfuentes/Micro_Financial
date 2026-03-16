# Schema Migration: Fix Clients Table

## Problem
The clients table schema doesn't match what the application is trying to insert:

**What schema currently has:**
- client_id, first_name, last_name, gender, birth_date, contact_number, email, address

**What application tries to send:**
- first_name, last_name, gender, date_of_birth, contact_number, email, street_address, city, province, zip_code, client_status, kyc_status, user_id

## Root Cause of HTTP 400 Error
Supabase is rejecting the insert because the field names don't match the schema. That's why we get "Database request failed with status 400".

## Solution: Two Options

### Option A: Update Supabase Schema (RECOMMENDED)

Go to Supabase dashboard and execute this SQL in SQL Editor:

```sql
-- Drop and recreate clients table with correct schema
DROP TABLE IF EXISTS clients CASCADE;

CREATE TABLE IF NOT EXISTS clients (
    client_id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender VARCHAR(20),
    date_of_birth DATE,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    street_address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    zip_code VARCHAR(20),
    client_status VARCHAR(50) DEFAULT 'pending',
    kyc_status VARCHAR(50) DEFAULT 'pending',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX idx_clients_user ON clients(user_id);
CREATE INDEX idx_clients_email ON clients(email);
CREATE INDEX idx_clients_status ON clients(client_status);
CREATE INDEX idx_clients_kyc ON clients(kyc_status);
```

**Steps:**
1. Go to https://supabase.com/dashboard
2. Select your project: `lvvfsgkxpulbpwrpyhuf`
3. Click "SQL Editor" on the left
4. Click "New Query"
5. Paste the SQL above
6. Click "Run"
7. Wait for "Query execution completed successfully"

### Option B: Update ClientService to Match Old Schema

If you can't modify Supabase schema, update `ClientService.php` to send only fields that exist:

```php
$clientData = [
    'first_name' => $data['first_name'],
    'last_name' => $data['last_name'],
    'gender' => $data['gender'] ?? null,
    'birth_date' => $data['date_of_birth'] ?? null,  // Map to birth_date
    'contact_number' => $data['contact_number'],
    'email' => $data['email'],
    'address' => $this->buildAddress($data),  // Combine fields
    'client_status' => 'pending'
];

// Don't send: kyc_status, user_id, date_of_birth, street_address, city, province, zip_code
```

Then store user_id separately in a clients_users linking table.

## Recommended Action

**Go with Option A** - it's cleaner and supports all the fields the application needs.

After updating Supabase schema:
1. Refresh browser
2. Try registering a new account
3. Should work now! ✓

## What Changed

- ✅ user_id: Links client to user account
- ✅ date_of_birth: Changed from birth_date
- ✅ Separate address fields: street_address, city, province, zip_code (instead of single address field)
- ✅ kyc_status: Track KYC verification status directly on client record
- ✅ updated_at: Track when client info was last modified

## Files Updated

- `schema.sql` - Schema updated locally (needs to be applied to Supabase)
- `Database.php` - Added better error logging to show actual Supabase error messages
- `ClientService.php` - Unchanged (already sends correct field names)

## Verification

After applying the schema:
1. Try registration again - should succeed
2. Check logs for "Inserting into table 'clients'" to see what fields are sent
3. New clients should appear with user_id linked
