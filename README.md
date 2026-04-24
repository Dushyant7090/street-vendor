# Street Vendor License & Location Management System

A full-stack web application built with **PHP**, **MySQL**, **HTML/CSS/JS** for managing street vendor licenses and vending locations.

## 🚀 Setup Instructions (WAMP Server)

### Step 1: Start WAMP Server
- Launch WAMP and make sure the icon turns **green** (all services running).

### Step 2: Place Project Files
- The project should already be in `C:\wamp64\www\street_vendor\`

### Step 3: Create the Database
1. Open **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **"Import"** tab at the top
3. Click **"Choose File"** and select `C:\wamp64\www\street_vendor\database.sql`
4. Click **"Go"** to import

**Or** create manually:
1. Click **"New"** in the left sidebar
2. Create database named `street_vendor_db`
3. Select it, click **"Import"**, and import the `database.sql` file

### Step 4: Access the Application
Open your browser and go to:
```
http://localhost/street_vendor/
```

### Step 5: Login Credentials

| Role   | Email             | Password  |
|--------|-------------------|-----------|
| Admin  | admin@admin.com   | admin123  |

To create a **Vendor** account, click **"Create Account"** on the login page.

---

## 📁 Project Structure

```
street_vendor/
├── config/
│   └── database.php        # DB connection & helper functions
├── auth/
│   ├── login.php            # Login page
│   ├── signup.php           # Vendor registration
│   └── logout.php           # Session logout
├── admin/
│   ├── dashboard.php        # Admin overview & stats
│   ├── vendors.php          # View all vendors
│   ├── licenses.php         # Manage license applications
│   ├── approve_license.php  # Approve/reject handler
│   ├── locations.php        # View allocated locations
│   ├── allocate_location.php# Assign spots to vendors
│   ├── zones.php            # Manage vending zones
├── vendor/
│   ├── dashboard.php        # Vendor overview
│   ├── apply_license.php    # Submit license application
│   ├── my_licenses.php      # View license status
│   ├── my_location.php      # View allocated spot
│   ├── profile.php          # Edit profile info
│   └── download_license.php # Print/download license PDF
├── assets/
│   ├── css/style.css        # Main stylesheet
│   └── js/main.js           # Client-side JavaScript
├── includes/
│   ├── header.php           # HTML head
│   ├── footer.php           # Footer & JS
│   ├── sidebar.php          # Navigation sidebar
│   └── flash.php            # Flash messages
├── database.sql             # SQL setup script
├── index.php                # Landing page (redirector)
└── README.md                # This file
```

## ✨ Features

- **Auth**: Login/Signup with password hashing, role-based access
- **Vendor**: Apply for license, view status, download PDF, view location, edit profile
- **Admin**: Dashboard with stats, manage vendors/licenses/locations/zones
- **Security**: Prepared statements (SQL injection prevention), session protection, input sanitization
- **UI**: Modern responsive design, sidebar navigation, cards, tables, badges, animations
