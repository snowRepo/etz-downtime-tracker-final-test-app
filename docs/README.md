# eTranzact Downtime Tracking System

## 📋 Table of Contents

- [Quick Start Guide](#quick-start-guide)
- [Overview](#overview)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
  - [Method 1: XAMPP Installation (Recommended)](#method-1-xampp-installation-recommended-for-windows)
  - [Method 2: Manual Installation](#method-2-manual-installation-advanced-users)
- [Database Schema](#database-schema)
- [Application Structure](#application-structure)
- [User Guide](#user-guide)
- [Developer Guide](#developer-guide)
- [Security Features](#security-features)
- [Activity Logging](#activity-logging-system) ⭐ NEW
- [XAMPP-Specific Troubleshooting](#xampp-specific-troubleshooting)
- [General Troubleshooting](#general-troubleshooting)
- [Additional Documentation](#additional-documentation)

---

## ⚡ Quick Start Guide

**Want to get started right away?** Here's the fastest path:

1. **Install XAMPP** → Download from [apachefriends.org](https://www.apachefriends.org/)
2. **Start Services** → Open XAMPP Control Panel, start Apache & MySQL
3. **Copy Files** → Extract this project to `C:\xampp\htdocs\etz-downtime-tracker-final-test-app`
4. **Install Dependencies** → Run `composer install` in the project folder
5. **Import Database** → Open [localhost/phpmyadmin](http://localhost/phpmyadmin), import `downtimedb.sql`
6. **Configure** → Copy `config.php.example` to `config.php`, set DB credentials (user: `root`, password: empty)
7. **Launch** → Visit [localhost/etz-downtime-tracker-final-test-app](http://localhost/etz-downtime-tracker-final-test-app)

> 📖 **New to XAMPP?** See the detailed [XAMPP Installation Guide](#method-1-xampp-installation-recommended-for-windows) below.

---

## 🎯 Overview

The **eTranzact Downtime Tracking System** is a comprehensive web application designed to monitor, track, and analyze service downtime incidents across multiple companies and services. Built with PHP and MySQL, it provides real-time incident management, detailed analytics, and SLA compliance reporting.

### Key Capabilities

- **Real-time Incident Tracking**: Monitor service outages as they occur
- **Multi-Company Support**: Track incidents across 17+ partner companies
- **Service Coverage**: Monitor 8 critical services including Mobile Money, Fundgate, GHQR, and more
- **Advanced Analytics**: Visualize trends with interactive Chart.js dashboards
- **SLA Reporting**: Generate comprehensive uptime and compliance reports
- **PDF Export**: Export analytics and SLA reports in professional PDF format

---

## ✨ Features

### 1. **Dashboard** (`index.php`)

- **Quick Statistics**: View total, resolved, and pending incidents at a glance
- **Recent Incidents Table**: See the latest 10 incidents with service, affected companies, dates, and status
- **Real-time Updates**: Refresh button to get the latest data
- **Status Badges**: Color-coded badges (Pending, Resolved, Investigating)
- **Dark Mode Support**: Toggle between light and dark themes

### 2. **Incident Reporting** (`report.php`)

- **Multi-Company Selection**: Report incidents affecting multiple companies simultaneously
- **Service Selection**: Choose from 8 predefined services
- **Impact Level Classification**: Low, Medium, High, Critical
- **Root Cause Documentation**: Detailed text field for incident analysis
- **Duplicate Prevention**: Automatic detection of existing incidents
- **CSRF Protection**: Secure form submission with token validation
- **Rate Limiting**: Prevents spam submissions (5 requests per minute)

### 3. **Incident Management** (`incidents.php`)

- **Comprehensive Incident View**: See all incidents grouped by service
- **Status Management**: Update incident status (Pending → Resolved)
- **Update Timeline**: Add and view chronological updates for each incident
- **Company Grouping**: View all affected companies per incident
- **Bulk Updates**: Resolve all incidents for a specific service at once
- **Impact Level Display**: Visual indicators for incident severity

### 4. **Analytics Dashboard** (`analytics.php`)

- **Interactive Charts**:
  - Status Distribution (Doughnut Chart)
  - Incidents by Company (Bar Chart)
  - Monthly Trend Analysis (Line Chart)
  - Impact Level Distribution (Pie Chart)
- **Date Range Filtering**: Analyze data for custom time periods
- **Company Filtering**: Focus on specific company performance
- **PDF Export**: Generate professional analytics reports
- **Responsive Design**: Charts adapt to screen size

### 5. **SLA Reporting** (`sla_report.php`)

- **Uptime Calculation**: Precise uptime percentage tracking
- **Business Hours Support**: Configure business hours per company/service
- **Downtime Analysis**: Detailed breakdown of downtime incidents
- **Target Compliance**: Compare actual vs. target uptime (default 99.99%)
- **Multi-Service Reports**: View SLA compliance across all services
- **Excel Export**: Download SLA data in spreadsheet format
- **PDF Export**: Professional SLA compliance reports

### 6. **Activity Logging** (`admin/activity_logs.php`) ⭐ NEW

- **Comprehensive Audit Trail**: Track all user actions and system events
- **Statistics Dashboard**: View total logs, unique users, top actions, and most active users
- **Advanced Filtering**:
  - Date range filtering (start/end dates)
  - User-specific filtering
  - Action type multi-select (22 action types)
  - Full-text search in descriptions
- **Detailed Log Information**:
  - User identification with avatars
  - Color-coded action types
  - IP address tracking
  - User agent logging
  - JSON metadata for context
- **Detail Modal**: View complete log information including metadata
- **CSV Export**: Download filtered logs for external analysis
- **Pagination**: Configurable results per page (25/50/100)
- **Logged Actions**:
  - Authentication (login/logout/failed attempts)
  - User management (create/update/delete with change tracking)
  - Incident operations (creation/updates)
  - Report exports (with applied filters)

### 7. **Admin Panel** (`admin/`)

- **User Management**: Create, edit, and delete user accounts
- **Role-Based Access**: Admin, User, and Viewer roles
- **Activity Monitoring**: View all system activity logs
- **Dark Mode Support**: Consistent theme across admin pages

---

## 💻 System Requirements

### Server Requirements

- **Web Server**: Apache 2.4+ (with mod_rewrite)
- **PHP**: 7.4 or higher (8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Extensions**:
  - PDO
  - PDO_MySQL
  - mbstring
  - GD (for PDF generation)
  - OpenSSL

### Client Requirements

- Modern web browser (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- JavaScript enabled
- Minimum screen resolution: 1024x768

### Development Tools (Optional)

- Composer (for dependency management)
- Git (for version control)

---

## 🚀 Installation

### Method 1: XAMPP Installation (Recommended for Windows)

This is the easiest way to get started, especially if you're new to PHP development.

#### Step 1: Install XAMPP

1. **Download XAMPP**:
   - Visit [https://www.apachefriends.org/](https://www.apachefriends.org/)
   - Download XAMPP for Windows (PHP 7.4 or higher)
   - Run the installer and follow the installation wizard

2. **Install Components**:
   - Make sure to select **Apache** and **MySQL** during installation
   - Default installation path: `C:\xampp`

3. **Start XAMPP Services**:
   - Open **XAMPP Control Panel** (search for it in Windows Start menu)
   - Click **Start** next to **Apache**
   - Click **Start** next to **MySQL**
   - Both should show green "Running" status

#### Step 2: Download/Clone the Application

1. **Navigate to XAMPP's htdocs folder**:

   ```
   C:\xampp\htdocs\
   ```

2. **Option A - Download ZIP**:
   - Download the project as a ZIP file
   - Extract it to `C:\xampp\htdocs\etz-downtime-tracker-final-test-app`

3. **Option B - Git Clone** (if you have Git installed):
   ```bash
   cd C:\xampp\htdocs
   git clone <repository-url> etz-downtime-tracker-final-test-app
   cd etz-downtime-tracker-final-test-app
   ```

#### Step 3: Install PHP Dependencies

1. **Install Composer** (if not already installed):
   - Download from [https://getcomposer.org/download/](https://getcomposer.org/download/)
   - Run the installer
   - Restart your command prompt/terminal

2. **Install Project Dependencies**:
   - Open Command Prompt or PowerShell
   - Navigate to the project folder:
     ```bash
     cd C:\xampp\htdocs\etz-downtime-tracker-final-test-app
     ```
   - Run Composer:
     ```bash
     composer install
     ```
   - Wait for dependencies to download (this installs the PDF library)

#### Step 4: Create the Database

1. **Open phpMyAdmin**:
   - In your web browser, go to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - You should see the phpMyAdmin interface

2. **Import the Database**:
   - Click on **"New"** in the left sidebar to create a new database
   - Or click on the **"Import"** tab at the top
   - Click **"Choose File"** button
   - Navigate to `C:\xampp\htdocs\etz-downtime-tracker-final-test-app\downtimedb.sql`
   - Select the file and click **"Open"**
   - Scroll down and click **"Import"** button
   - Wait for the success message

3. **Verify Database Creation**:
   - You should see a new database called `downtimedb` in the left sidebar
   - Click on it to expand and verify these tables exist:
     - `companies` (17 companies)
     - `services` (8 services)
     - `issues_reported`
     - `incident_updates`
     - `downtime_incidents`
     - `sla_targets`

#### Step 5: Configure Database Connection

1. **Create Configuration File**:
   - Navigate to `C:\xampp\htdocs\etz-downtime-tracker-final-test-app\`
   - Find the file `config.php.example`
   - Make a copy and rename it to `config.php`

2. **Edit `config.php`**:
   - Open `config.php` in any text editor (Notepad, VS Code, etc.)
   - Update the database credentials:

   ```php
   <?php
   // Database Configuration
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');           // Default XAMPP username
   define('DB_PASS', '');               // Default XAMPP password is empty
   define('DB_NAME', 'downtimedb');     // Database name

   // Application Configuration
   define('APP_ENV', 'development');    // Use 'production' when deploying
   ```

3. **Save the file**

> **Note**: XAMPP's default MySQL username is `root` with an **empty password**. If you've changed your MySQL password, use that instead.

#### Step 6: Access the Application

1. **Open Your Browser**:
   - Navigate to: [http://localhost/etz-downtime-tracker-final-test-app/](http://localhost/etz-downtime-tracker-final-test-app/public)
   - You should see the **Dashboard** page

2. **Verify Installation**:
   - The dashboard should load without errors
   - You should see statistics cards (Total Incidents, Resolved, Pending)
   - The navigation bar should have links to: Dashboard, Report Incident, Incidents, Analytics, SLA Report

#### Step 7: Test the Application

1. **Report a Test Incident**:
   - Click **"Report Incident"** in the navbar
   - Fill in the form:
     - Your Name: `Test User`
     - Service: Select any service (e.g., "Mobile Money")
     - Companies: Check one or more companies
     - Impact Level: Select any level
     - Root Cause: Enter a test description
   - Click **"Submit Report"**
   - You should see a success message

2. **View the Incident**:
   - Click **"Dashboard"** to return to the homepage
   - Your test incident should appear in the "Recent Incidents" table
   - Click **"Incidents"** to see all incidents grouped by service

3. **Check Analytics**:
   - Click **"Analytics"** in the navbar
   - You should see charts displaying your incident data

---

### Method 2: Manual Installation (Advanced Users)

If you're not using XAMPP or prefer a custom setup:

#### Step 1: Prerequisites

Ensure you have:

- Apache 2.4+ or Nginx
- PHP 7.4+ (with PDO, PDO_MySQL, mbstring, GD extensions)
- MySQL 5.7+ or MariaDB 10.3+
- Composer

#### Step 2: Clone/Download the Repository

```bash
git clone <repository-url>
cd etz-downtime-tracker-final-test-app
```

#### Step 3: Install Dependencies

```bash
composer install
```

#### Step 4: Database Setup

1. **Create the Database**:

   ```bash
   mysql -u root -p < downtimedb.sql
   ```

2. **Verify Tables Created**:
   - `companies` - Partner companies
   - `services` - Monitored services
   - `issues_reported` - Incident records
   - `incident_updates` - Update timeline
   - `downtime_incidents` - Detailed downtime tracking
   - `sla_targets` - SLA configuration

#### Step 5: Configure Database Connection

1. **Copy the example config**:

   ```bash
   copy config.php.example config.php
   ```

2. **Edit `config.php`** with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'downtimedb');
   define('APP_ENV', 'production'); // or 'development'
   ```

#### Step 6: Set Permissions

```bash
# Linux/Mac
chmod 644 config.php
chmod 755 includes/

# Windows - Ensure IIS_IUSRS or IUSR has read permissions
```

#### Step 7: Access the Application

Navigate to your configured web server URL (e.g., `http://localhost/etz-downtime-tracker-final-test-app/`)

---

## 🗄️ Database Schema

### Core Tables

#### `companies`

Stores partner company information.

```sql
company_id (PK)    - Auto-increment ID
company_name       - Company name (e.g., "MTN", "AirtelTigo")
category           - Optional categorization
created_at         - Timestamp
updated_at         - Auto-updated timestamp
```

#### `services`

Defines monitored services.

```sql
service_id (PK)    - Auto-increment ID
service_name       - Service name (e.g., "Mobile Money")
created_at         - Timestamp
updated_at         - Auto-updated timestamp
```

#### `issues_reported`

Main incident tracking table.

```sql
issue_id (PK)      - Auto-increment ID
user_name          - Reporter name
service_id (FK)    - References services
company_id (FK)    - References companies
root_cause         - Incident description
status             - ENUM('pending', 'resolved')
impact_level       - ENUM('Low', 'Medium', 'High', 'Critical')
resolved_by        - User who resolved the issue
resolved_at        - Resolution timestamp
created_at         - Report timestamp
updated_at         - Auto-updated timestamp
```

#### `incident_updates`

Timeline of incident updates.

```sql
update_id (PK)     - Auto-increment ID
issue_id (FK)      - References issues_reported
user_name          - Update author
update_text        - Update content
created_at         - Update timestamp
updated_at         - Auto-updated timestamp
```

#### `downtime_incidents`

Detailed downtime tracking for SLA calculations.

```sql
incident_id (PK)       - Auto-increment ID
issue_id (FK)          - References issues_reported
actual_start_time      - Downtime start
actual_end_time        - Downtime end
downtime_minutes       - Auto-calculated duration
is_planned             - Boolean flag
downtime_category      - ENUM('Network', 'Server', 'Maintenance', etc.)
created_at             - Timestamp
updated_at             - Auto-updated timestamp
```

#### `sla_targets`

SLA configuration per company/service.

```sql
target_id (PK)         - Auto-increment ID
company_id (FK)        - References companies (nullable)
service_id (FK)        - References services (nullable)
target_uptime          - Decimal (default 99.99%)
business_hours_start   - Time (default 09:00:00)
business_hours_end     - Time (default 17:00:00)
business_days          - SET('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')
created_at             - Timestamp
updated_at             - Auto-updated timestamp
```

#### `activity_logs` ⭐ NEW

Comprehensive audit trail for all user actions and system events.

```sql
log_id (PK)            - Auto-increment ID (BIGINT)
user_id (FK)           - References users (nullable for system actions)
username               - Username at time of action (preserved even if user deleted)
action_type            - ENUM (22 types: login, logout, user_created, incident_created, etc.)
entity_type            - Type of entity affected (user, incident, etc.)
entity_id              - ID of the affected entity
description            - Human-readable description of the action
ip_address             - IP address of the user (supports IPv4 and IPv6)
user_agent             - Browser/client user agent string
metadata               - JSON field for additional context data
created_at             - Timestamp of when the action occurred
```

**Indexes**: Optimized for performance on user_id, action_type, created_at, entity, and username.

#### `users` ⭐ NEW

User authentication and authorization.

```sql
user_id (PK)           - Auto-increment ID
username               - Unique username
email                  - User email address
password_hash          - Bcrypt hashed password
full_name              - User's full name
role                   - ENUM('admin', 'user', 'viewer')
is_active              - Boolean flag for account status
last_login             - Last login timestamp
created_at             - Account creation timestamp
updated_at             - Auto-updated timestamp
```

### Database Triggers

**`calculate_downtime_minutes`**: Automatically calculates downtime duration when `actual_end_time` is updated.

---

## 📁 Application Structure

```
etz-downtime-tracker-final-test-app/
├── index.php                      # Dashboard homepage
├── report.php                     # Incident reporting form
├── incidents.php                  # Incident management page
├── analytics.php                  # Analytics dashboard
├── sla_report.php                 # SLA compliance reporting
├── export_analytics_pdf.php       # PDF export for analytics
├── export_sla_report.php          # Excel export for SLA
├── export_sla_report_pdf.php      # PDF export for SLA
├── config.php                     # Database configuration (gitignored)
├── config.php.example             # Configuration template
├── downtimedb.sql                 # Database schema and seed data
├── composer.json                  # PHP dependencies
├── composer.lock                  # Locked dependency versions
├── .gitignore                     # Git ignore rules
│
├── includes/                      # Shared components
│   ├── navbar.php                 # Navigation bar with dark mode
│   ├── loading.php                # Loading overlay component
│   ├── pdf_config.php             # PDF generation configuration
│   └── logo1.png                  # Application logo
│
└── vendor/                        # Composer dependencies (gitignored)
    └── ...
```

---

## 📖 User Guide

### Reporting a New Incident

1. Navigate to **Report Incident** from the navbar
2. Fill in the form:
   - **Your Name**: Enter your full name
   - **Service**: Select the affected service
   - **Companies**: Check all affected companies
   - **Impact Level**: Choose severity (Low/Medium/High/Critical)
   - **Root Cause**: Describe the issue in detail
3. Click **Submit Report**
4. Confirmation message appears on success

### Managing Incidents

1. Go to **Incidents** page
2. View all incidents grouped by service
3. **To add an update**:
   - Click "Add Update" on an incident card
   - Enter your name and update text
   - Submit
4. **To resolve an incident**:
   - Click "Resolve Incident"
   - Enter your name
   - Confirm resolution
   - All incidents for that service are marked as resolved

### Viewing Analytics

1. Navigate to **Analytics**
2. **Filter data**:
   - Select company (or "All Companies")
   - Choose date range
   - Click "Apply Filters"
3. **View charts**:
   - Status Distribution
   - Incidents by Company
   - Monthly Trends
   - Impact Levels
4. **Export**: Click "Export PDF" for a report

### Generating SLA Reports

1. Go to **SLA Report**
2. **Configure filters**:
   - Select company
   - Select service
   - Choose date range
3. Click **Generate Report**
4. View:
   - Uptime percentage
   - Total downtime
   - Incident count
   - SLA compliance status
5. **Export options**:
   - Excel: Click "Export to Excel"
   - PDF: Click "Export to PDF"

### Using Dark Mode

1. Click the **moon icon** (🌙) in the navbar
2. Toggle between light and dark themes
3. Preference is saved in browser localStorage

---

## 🛠️ Developer Guide

### Adding a New Service

1. **Database**:

   ```sql
   INSERT INTO services (service_name) VALUES ('New Service Name');
   ```

2. **Update SLA Targets**:
   ```sql
   INSERT INTO sla_targets (company_id, service_id, target_uptime)
   SELECT c.company_id, LAST_INSERT_ID(), 99.99
   FROM companies c WHERE c.company_name != 'All';
   ```

### Adding a New Company

1. **Database**:

   ```sql
   INSERT INTO companies (company_name, category) VALUES ('Company Name', 'Category');
   ```

2. **Update SLA Targets**:
   ```sql
   INSERT INTO sla_targets (company_id, service_id, target_uptime)
   SELECT LAST_INSERT_ID(), s.service_id, 99.99
   FROM services s;
   ```

### Customizing SLA Targets

Edit the `sla_targets` table:

```sql
UPDATE sla_targets
SET target_uptime = 99.95,
    business_hours_start = '08:00:00',
    business_hours_end = '18:00:00',
    business_days = 'Mon,Tue,Wed,Thu,Fri,Sat'
WHERE company_id = 1 AND service_id = 2;
```

### Modifying Chart Colors

Edit the color arrays in `analytics.php`:

```php
$statusColorMap = [
    'pending' => '#f59e0b',    // Yellow
    'resolved' => '#10b981',   // Green
    'investigating' => '#3b82f6' // Blue
];
```

### Adding New Impact Levels

1. **Modify database ENUM**:

   ```sql
   ALTER TABLE issues_reported
   MODIFY impact_level ENUM('Low','Medium','High','Critical','Severe');
   ```

2. **Update PHP code** in `report.php` and `incidents.php` to handle the new level

### Customizing PDF Exports

Edit `includes/pdf_config.php` to modify:

- Page size and orientation
- Fonts and colors
- Header/footer content
- Logo placement

---

## 🔒 Security Features

### Implemented Security Measures

1. **CSRF Protection**
   - Token generation and validation on all forms
   - Prevents cross-site request forgery attacks

2. **SQL Injection Prevention**
   - Prepared statements with PDO
   - Parameter binding for all queries

3. **XSS Protection**
   - `htmlspecialchars()` on all output
   - Content Security Policy headers (recommended to add)

4. **Rate Limiting**
   - Session-based rate limiting (5 requests/minute)
   - Prevents spam and DoS attacks

5. **Input Validation**
   - Server-side validation for all inputs
   - Length limits and type checking
   - Sanitization of user data

6. **Error Handling**
   - Production mode hides detailed errors
   - Development mode shows full error details
   - Error logging for debugging

7. **Session Security**
   - Session-based authentication (can be extended)
   - Secure session configuration recommended

### Recommended Additional Security

1. **HTTPS**: Always use SSL/TLS in production
2. **Database User**: Create a dedicated MySQL user with minimal privileges
3. **File Permissions**: Restrict write access to necessary directories only
4. **Regular Updates**: Keep PHP, MySQL, and dependencies updated
5. **Backup Strategy**: Regular automated database backups

---

## 🔧 XAMPP-Specific Troubleshooting

### Apache Won't Start

**Problem**: Apache shows "Port 80 in use by another application"

**Solutions**:

1. **Check if Skype is using Port 80**:
   - Open Skype → Tools → Options → Advanced → Connection
   - Uncheck "Use port 80 and 443 as alternatives"
   - Restart Skype

2. **Check if IIS is running**:
   - Open Services (Win + R, type `services.msc`)
   - Find "World Wide Web Publishing Service"
   - Right-click → Stop
   - Set Startup type to "Disabled"

3. **Change Apache Port**:
   - Open XAMPP Control Panel
   - Click "Config" next to Apache → "httpd.conf"
   - Find `Listen 80` and change to `Listen 8080`
   - Find `ServerName localhost:80` and change to `ServerName localhost:8080`
   - Save and restart Apache
   - Access app at: `http://localhost:8080/etz-downtime-tracker-final-test-app/`

### MySQL Won't Start

**Problem**: MySQL shows "Port 3306 in use"

**Solutions**:

1. **Check for other MySQL installations**:
   - Open Task Manager (Ctrl + Shift + Esc)
   - Look for `mysqld.exe` processes
   - End any MySQL processes not from XAMPP

2. **Change MySQL Port**:
   - Open XAMPP Control Panel
   - Click "Config" next to MySQL → "my.ini"
   - Find `port=3306` and change to `port=3307`
   - Update `config.php`: `define('DB_HOST', 'localhost:3307');`
   - Save and restart MySQL

### Composer Not Found

**Problem**: `'composer' is not recognized as an internal or external command`

**Solutions**:

1. **Install Composer**:
   - Download from [https://getcomposer.org/download/](https://getcomposer.org/download/)
   - Run the Windows installer
   - Restart Command Prompt/PowerShell

2. **Use XAMPP's PHP**:
   - Add to Windows PATH: `C:\xampp\php`
   - Restart Command Prompt
   - Try `composer install` again

### Database Import Failed

**Problem**: Error importing `downtimedb.sql` in phpMyAdmin

**Solutions**:

1. **File too large**:
   - Edit `php.ini` in XAMPP Control Panel → Config → PHP (php.ini)
   - Find and increase these values:
     ```ini
     upload_max_filesize = 64M
     post_max_size = 64M
     max_execution_time = 300
     ```
   - Restart Apache

2. **Import via Command Line**:
   - Open Command Prompt
   - Navigate to XAMPP's MySQL bin:
     ```bash
     cd C:\xampp\mysql\bin
     ```
   - Run import:
     ```bash
     mysql -u root -p downtimedb < "C:\xampp\htdocs\etz-downtime-tracker-final-test-app\downtimedb.sql"
     ```
   - Press Enter (no password by default)

### Permission Denied Errors

**Problem**: "Permission denied" when accessing files

**Solutions**:

1. **Run XAMPP as Administrator**:
   - Right-click XAMPP Control Panel
   - Select "Run as administrator"

2. **Check File Permissions**:
   - Right-click project folder → Properties → Security
   - Ensure your user account has "Full control"

### Blank Page / White Screen

**Problem**: Application shows blank white page

**Solutions**:

1. **Enable Error Display**:
   - Edit `config.php`:
     ```php
     define('APP_ENV', 'development');
     ```
   - Refresh the page to see actual errors

2. **Check Apache Error Log**:
   - XAMPP Control Panel → Logs → Apache (error.log)
   - Look for PHP errors

3. **Check PHP Extensions**:
   - Open `php.ini` (XAMPP Control Panel → Config → PHP)
   - Ensure these are enabled (remove `;` at start):
     ```ini
     extension=pdo_mysql
     extension=mbstring
     extension=gd
     ```
   - Restart Apache

### Charts Not Showing

**Problem**: Analytics page loads but charts are empty

**Solutions**:

1. **Check Internet Connection**:
   - Charts use CDN for Chart.js library
   - Ensure you have internet access

2. **Check Browser Console**:
   - Press F12 in browser
   - Look for JavaScript errors in Console tab
   - Look for failed network requests in Network tab

### PDF Export Not Working

**Problem**: "Failed to generate PDF" error

**Solutions**:

1. **Verify Composer Dependencies**:

   ```bash
   cd C:\xampp\htdocs\etz-downtime-tracker-final-test-app
   composer install
   ```

2. **Check vendor folder exists**:
   - Verify `C:\xampp\htdocs\etz-downtime-tracker-final-test-app\vendor` folder exists
   - Should contain TCPDF library

3. **Enable GD Extension**:
   - Edit `php.ini`: Remove `;` from `extension=gd`
   - Restart Apache

---

## 🐛 General Troubleshooting

### Common Issues

#### 1. **Database Connection Failed**

**Error**: "Could not connect to database"

**Solutions**:

- Verify database credentials in `config.php`
- Ensure MySQL service is running
- Check database exists: `SHOW DATABASES;`
- Verify user permissions: `GRANT ALL ON downtimedb.* TO 'user'@'localhost';`

#### 2. **Charts Not Displaying**

**Error**: Blank chart areas on analytics page

**Solutions**:

- Check browser console for JavaScript errors
- Verify Chart.js CDN is accessible
- Ensure data is being returned from database queries
- Clear browser cache

#### 3. **PDF Export Not Working**

**Error**: "Failed to generate PDF"

**Solutions**:

- Verify Composer dependencies are installed: `composer install`
- Check PHP GD extension is enabled: `php -m | grep gd`
- Ensure write permissions on temp directory
- Check error logs for specific TCPDF errors

#### 4. **Dark Mode Not Persisting**

**Error**: Theme resets on page reload

**Solutions**:

- Enable localStorage in browser
- Check browser console for JavaScript errors
- Clear browser cache and cookies
- Verify Alpine.js is loading correctly

#### 5. **Duplicate Incident Prevention Not Working**

**Error**: Same incident can be reported multiple times

**Solutions**:

- Check database constraints are in place
- Verify the duplicate check query in `report.php`
- Ensure transaction rollback is working
- Check for race conditions with concurrent submissions

#### 6. **SLA Calculations Incorrect**

**Error**: Uptime percentages don't match expectations

**Solutions**:

- Verify `downtime_incidents` table has correct data
- Check trigger `calculate_downtime_minutes` is active
- Ensure timezone settings are correct
- Review business hours configuration in `sla_targets`

### Debug Mode

Enable detailed error reporting in `config.php`:

```php
define('APP_ENV', 'development');
```

This will display:

- PHP errors and warnings
- SQL query errors
- Stack traces

**⚠️ Never use development mode in production!**

### Logging

Check server error logs:

```bash
# Apache
tail -f /var/log/apache2/error.log

# XAMPP Windows
C:\xampp\apache\logs\error.log

# PHP-FPM
tail -f /var/log/php-fpm/error.log
```

---

## 📞 Support

For issues, questions, or feature requests:

1. Check this documentation
2. Review the troubleshooting section
3. Check application error logs
4. Contact the development team

---

## 📄 License

This application is proprietary software developed for eTranzact. All rights reserved.

---

## 🙏 Credits

**Developed by**: eTranzact Development Team  
**Framework**: Tailwind CSS v3.4.17  
**Charts**: Chart.js v4.4.1  
**Icons**: Font Awesome 6.5.1  
**Fonts**: Inter (Google Fonts)  
**PDF Library**: TCPDF (via Composer)  
**JavaScript**: Alpine.js v3.x

---

**Last Updated**: January 2026  
**Version**: 1.0.0

---

##  Activity Logging System

The application includes a comprehensive activity logging system that tracks all user actions and system events.

### Key Features
- **Comprehensive Audit Trail**: Tracks 22 different action types
- **Advanced Filtering**: Filter by date, user, action type, and search
- **Statistics Dashboard**: View total logs, unique users, and top actions
- **CSV Export**: Download filtered logs for analysis

### Documentation
See **[ACTIVITY_LOGGING.md](ACTIVITY_LOGGING.md)** for complete details.

---

##  Additional Documentation

- **[ACTIVITY_LOGGING.md](ACTIVITY_LOGGING.md)**  NEW - Activity logging guide
- **[TECHNICAL_DOCS.md](TECHNICAL_DOCS.md)** - Technical details
- **[NGROK_SETUP.md](NGROK_SETUP.md)** - Remote access setup

