# LifeLink – Blood Donor Finder System
## Complete Setup Guide for XAMPP

---

## 📁 Project Structure

```
blood-donor-system/
├── index.php                   ← Landing page
├── config/
│   ├── db.php                  ← Database connection (PDO)
│   └── app.php                 ← App constants & session init
├── includes/
│   ├── helpers.php             ← Shared utility functions
│   ├── header.php              ← HTML <head> + navbar
│   └── footer.php              ← HTML footer
├── auth/
│   ├── login.php               ← Login page
│   ├── register.php            ← Registration (donor/patient/hospital)
│   └── logout.php              ← Session destroy
├── donor/
│   ├── dashboard.php           ← Donor home with stats, availability toggle
│   ├── profile.php             ← Edit donor profile
│   └── notifications.php       ← Emergency alert inbox
├── patient/
│   ├── search.php              ← Search donors by blood group + city
│   └── emergency.php           ← Emergency SOS form
├── hospital/
│   ├── dashboard.php           ← Hospital home + donations table
│   └── add_donation.php        ← Record a donation event
├── api/
│   ├── update_availability.php ← AJAX: toggle donor availability
│   ├── respond_notification.php← AJAX: accept/reject SOS alert
│   ├── confirm_donation.php    ← AJAX: hospital confirms donation
│   └── poll_notifications.php  ← AJAX: badge count polling
├── assets/
│   ├── css/main.css            ← Full stylesheet
│   └── js/main.js              ← Client-side JS
└── database/
    └── blood_donor.sql         ← Schema + sample data
```

---

## ⚙️ Step-by-Step Setup

### 1. Install XAMPP
Download from https://www.apachefriends.org and install.
Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 2. Copy project files
Place the entire `blood-donor-system/` folder inside:
```
C:\xampp\htdocs\blood-donor-system\        (Windows)
/Applications/XAMPP/htdocs/blood-donor-system/  (macOS)
```

### 3. Import the database
1. Open your browser → http://localhost/phpmyadmin
2. Click **"New"** in the left panel
3. Create database named: `blood_donor_system`
4. Click the database → **Import** tab
5. Choose file: `database/blood_donor.sql`
6. Click **Go**

### 4. Configure database credentials
Edit `config/db.php`:
```php
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password (blank for default XAMPP)
```

### 5. Open the app
Visit: http://localhost/blood-donor-system

---

## 🔑 Demo Accounts

All demo accounts use password: **`password`**

| Role     | Email                       | Password   |
|----------|-----------------------------|------------|
| Donor    | rahul@example.com           | password   |
| Donor    | priya@example.com           | password   |
| Donor    | amit@example.com            | password   |
| Patient  | karan@example.com           | password   |
| Hospital | cityhospital@example.com    | password   |
| Hospital | apollo@example.com          | password   |

> **Note:** The sample data uses Laravel's default bcrypt hash for "password".  
> Run this one-time fix in phpMyAdmin if login fails:
> ```sql
> UPDATE users SET password = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B9zeBaV' WHERE 1;
> ```
> That hash corresponds to the string `password`.

---

## 🧪 Testing the System

### Test Donor Flow
1. Login as `rahul@example.com`
2. See dashboard with stats, reliability score, donation history
3. Toggle availability (Available Now / Later / Not Available)
4. Go to **Alerts** page to see/respond to emergency notifications

### Test Patient / Hospital Flow
1. Login as `karan@example.com` (patient) or `cityhospital@example.com` (hospital)
2. Go to **Find Donors** → search by blood group and city
3. Go to **Emergency SOS** → fill form → submit
4. Log back in as a donor to see the alert appear in their Notifications tab

### Test Hospital Confirmation
1. Login as `cityhospital@example.com`
2. Click **+ Record Donation** → select donor + date → submit
3. Click **Confirm** on the pending donation
4. Check donor dashboard – score and donation count should update

---

## 🔄 Feature Summary

| Feature                        | Status |
|-------------------------------|--------|
| Register / Login (all roles)  | ✅     |
| CSRF protection               | ✅     |
| Password hashing (bcrypt)     | ✅     |
| Donor availability toggle     | ✅ AJAX|
| 90-day eligibility check      | ✅     |
| Donor profile + edit          | ✅     |
| Reliability score             | ✅     |
| Search by blood group + city  | ✅     |
| Live filter (JS)              | ✅     |
| Emergency SOS                 | ✅     |
| Haversine GPS radius matching | ✅     |
| Notification system           | ✅     |
| Accept / Reject SOS (AJAX)    | ✅ AJAX|
| Real-time badge polling       | ✅     |
| Hospital donation records     | ✅     |
| Donation confirmation (AJAX)  | ✅ AJAX|
| Score update on confirmation  | ✅     |
| Prepared statements (PDO)     | ✅     |
| Input validation              | ✅     |
| Session management            | ✅     |
| GPS auto-detect               | ✅     |

---

## 🛡️ Security Notes

- All DB queries use **PDO prepared statements** (no SQL injection)
- Passwords hashed with **bcrypt** (`PASSWORD_BCRYPT`)
- **CSRF tokens** on all POST forms and AJAX calls
- Session cookies are `httponly` (no JS access)
- Input is sanitized before output with `htmlspecialchars`

---

## 🚀 Production Checklist

- [ ] Set `DB_PASS` to a strong password
- [ ] Set cookie `secure => true` in `config/app.php` (HTTPS)
- [ ] Replace simulated email with PHPMailer + real SMTP
- [ ] Add rate limiting to SOS endpoint
- [ ] Enable PHP error logging (disable display_errors)
- [ ] Use environment variables instead of hardcoded DB credentials
