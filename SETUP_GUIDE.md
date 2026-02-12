# 📖 AttendEase Setup Guide for Localhost Demo
## Academy of St. Joseph, Claveria Cagayan Inc. - Attendance Management System

### 🎯 Welcome!
This guide will help you set up the AttendEase Attendance Management System on your computer for demonstration purposes. **No programming knowledge required!** Just follow each step carefully.

---

## 📋 What You'll Need

Before starting, make sure you have:
- A computer with Windows, Mac, or Linux
- At least 500 MB of free disk space
- Internet connection (for downloading software)
- Administrator access on your computer
- 30-45 minutes of your time

---

## 🚀 Step-by-Step Setup Instructions

### Step 1: Download and Install XAMPP

XAMPP is free software that includes everything needed to run the attendance system on your computer.

#### 1.1 Download XAMPP

1. **Open your web browser** (Chrome, Firefox, Edge, etc.)
2. **Go to**: https://www.apachefriends.org/
3. **Click** the big download button for your operating system:
   - For Windows: Click "XAMPP for Windows"
   - For Mac: Click "XAMPP for OS X"
   - For Linux: Click "XAMPP for Linux"
4. **Choose the latest version** (recommended: PHP 8.0 or higher)
5. **Wait for the download to complete** (file size: approximately 150-200 MB)

#### 1.2 Install XAMPP

**For Windows:**
1. **Locate** the downloaded file (usually in your Downloads folder)
   - File name will be something like: `xampp-windows-x64-8.2.12-0-VS16-installer.exe`
2. **Double-click** the installer file
3. **Click "Yes"** when Windows asks for permission
4. **Follow the installation wizard:**
   - Click "Next" on the welcome screen
   - **Select components**: Make sure these are checked:
     - ✅ Apache
     - ✅ MySQL
     - ✅ PHP
     - ✅ phpMyAdmin
     - (You can uncheck others like FileZilla, Mercury, etc.)
   - Click "Next"
5. **Choose installation folder**: 
   - Default is: `C:\xampp`
   - **Important**: Remember this location! We'll need it later.
   - Click "Next"
6. **Language**: Select your preferred language
7. **Click "Next"** and then **"Finish"** to complete installation

**For Mac:**
1. **Open** the downloaded .dmg file
2. **Drag** the XAMPP folder to Applications
3. **Right-click** and select "Open" (if security warning appears)
4. **Follow** the installation wizard

**For Linux:**
1. **Open Terminal**
2. **Navigate** to Downloads: `cd ~/Downloads`
3. **Make executable**: `chmod +x xampp-linux-*-installer.run`
4. **Run installer**: `sudo ./xampp-linux-*-installer.run`
5. **Follow** the installation prompts

---

### Step 2: Start XAMPP Services

Now we need to start the web server and database.

#### 2.1 Open XAMPP Control Panel

**For Windows:**
1. **Click** the Windows Start button
2. **Type** "XAMPP Control Panel"
3. **Click** on "XAMPP Control Panel" to open it
   - Or go to `C:\xampp\xampp-control.exe` and double-click it

**For Mac:**
1. **Open** Applications folder
2. **Open** XAMPP folder
3. **Double-click** "manager-osx"

**For Linux:**
1. **Open Terminal**
2. **Type**: `sudo /opt/lampp/manager-linux.run` (or `manager-linux-x64.run`)

#### 2.2 Start Required Services

In the XAMPP Control Panel, you'll see a list of services:

1. **Find the "Apache" row**
   - Click the **"Start"** button next to Apache
   - Wait until it turns green and shows "Running"
   
2. **Find the "MySQL" row**
   - Click the **"Start"** button next to MySQL
   - Wait until it turns green and shows "Running"

**✅ Success!** Both Apache and MySQL should now show green "Running" status.

**⚠️ Troubleshooting:** If you see an error:
- **Port already in use**: Another program (like Skype) might be using port 80 or 3306
  - Close other applications and try again
  - Or change the port numbers in XAMPP Config
- **Firewall warning**: Click "Allow Access" when Windows Firewall asks

---

### Step 3: Download the Attendance System

#### 3.1 Get the Project Files

**Option A: Download as ZIP (Easiest)**
1. **Go to**: https://github.com/TsmHabib03/Academy-of-St.Joseph-Claveria-Cagayan-Inc.-Attendance-Checker
2. **Click** the green "Code" button
3. **Click** "Download ZIP"
4. **Wait** for download to complete
5. **Extract the ZIP file**:
   - Right-click the downloaded ZIP file
   - Select "Extract All..."
   - Choose a location (like Desktop or Downloads)
   - Click "Extract"

**Option B: Clone with Git (If you have Git installed)**
1. **Open** Command Prompt (Windows) or Terminal (Mac/Linux)
2. **Type**: 
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/TsmHabib03/Academy-of-St.Joseph-Claveria-Cagayan-Inc.-Attendance-Checker.git
   ```

#### 3.2 Move Files to XAMPP

1. **Open** the extracted folder
   - Find the folder named `Academy-of-St.Joseph-Claveria-Cagayan-Inc.-Attendance-Checker-main`
   
2. **Copy the entire folder**:
   - Right-click on the folder
   - Select "Copy"

3. **Navigate to XAMPP htdocs folder**:
   - **Windows**: Open `C:\xampp\htdocs`
   - **Mac**: Open `/Applications/XAMPP/htdocs`
   - **Linux**: Open `/opt/lampp/htdocs`

4. **Paste the folder**:
   - Right-click in the htdocs folder
   - Select "Paste"

5. **Rename the folder** (optional but recommended):
   - Right-click the pasted folder
   - Select "Rename"
   - Rename it to something shorter like: `attendease`
   - This makes it easier to access in the browser

**✅ Your project files are now in:** `C:\xampp\htdocs\attendease\`

---

### Step 4: Create the Database

Now we'll create the database where all student and attendance data will be stored.

#### 4.1 Open phpMyAdmin

1. **Open your web browser**
2. **Type in the address bar**: `http://localhost/phpmyadmin`
3. **Press Enter**
4. **You should see** the phpMyAdmin interface (a blue and white page)

**⚠️ Can't access phpMyAdmin?**
- Make sure Apache and MySQL are running in XAMPP Control Panel
- Try typing: `http://127.0.0.1/phpmyadmin` instead

#### 4.2 Create New Database

1. **Click** the "New" button on the left sidebar
2. **In the "Database name" field**, type: `asj_attendease_db`
   - ⚠️ **Important**: Type it exactly as shown, including underscores
3. **From the "Collation" dropdown**, select: `utf8mb4_unicode_ci`
4. **Click** the "Create" button
5. **You should see** a success message: "Database asj_attendease_db has been created"

#### 4.3 Import Database Structure

Now we'll import the tables and data into the database.

1. **Click** on the database name `asj_attendease_db` in the left sidebar
   - It should now be highlighted
   
2. **Click** the "Import" tab at the top
   
3. **Click** the "Choose File" button
   
4. **Navigate to** the project folder:
   - Go to `C:\xampp\htdocs\attendease\database\`
   - Find the file: `asj_attendease_db.sql`
   - Select it and click "Open"

5. **Scroll down** and click the "Import" button at the bottom
   
6. **Wait** for the import to complete (it may take 10-30 seconds)
   
7. **Look for** a success message:
   - "Import has been successfully finished"
   - "X queries executed"

8. **Verify the import**:
   - Click on `asj_attendease_db` in the left sidebar
   - You should see several tables listed:
     - admin_activity_log
     - admin_users
     - attendance
     - sections
     - students
   - ✅ **Success!** Your database is ready.

---

### Step 5: Configure Database Connection

We need to tell the system how to connect to your database.

#### 5.1 Open Configuration File

1. **Navigate to**: `C:\xampp\htdocs\attendease\config\`

2. **Find** the file: `db_config.php`

3. **Open it with a text editor**:
   - **Windows**: Right-click → Open with → Notepad
   - **Mac**: Right-click → Open with → TextEdit
   - Or use any text editor like Notepad++, VS Code, Sublime Text

#### 5.2 Update Database Credentials

You'll see code that looks like this:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'muning0328');
define('DB_NAME', 'asj_attendease_db');
```

**Update the values:**

1. **DB_HOST**: Should be `localhost` ✅ (don't change this)
2. **DB_USER**: Should be `root` ✅ (don't change this - XAMPP default)
3. **DB_PASS**: Change this based on your XAMPP setup:
   - **Default XAMPP**: Leave it as empty: `define('DB_PASS', '');`
   - **If you set a password**: Put your password between the quotes
   - **Current file shows**: `'muning0328'` - change to empty `''` if you didn't set a password
4. **DB_NAME**: Should be `asj_attendease_db` ✅ (matches what we created)

**Example of corrected configuration:**
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // ← Empty for default XAMPP
define('DB_NAME', 'asj_attendease_db');
```

#### 5.3 Save the File

1. **Click** File → Save (or press Ctrl+S / Cmd+S)
2. **Close** the text editor

---

### Step 6: Create QR Codes Directory

The system needs a folder to store generated QR codes.

#### 6.1 Check Uploads Folder

1. **Navigate to**: `C:\xampp\htdocs\attendease\uploads\`
2. **Check if** there's a folder named `qrcodes`
   - If it exists: ✅ Great! Skip to Step 7
   - If it doesn't exist: Continue to Step 6.2

#### 6.2 Create QR Codes Folder

1. **Right-click** inside the `uploads` folder
2. **Select** "New" → "Folder"
3. **Name it**: `qrcodes`
4. **Press Enter**

**✅ Your folder structure should be:**
```
C:\xampp\htdocs\attendease\
├── admin/
├── api/
├── config/
├── uploads/
│   └── qrcodes/    ← This folder
└── ... other folders
```

---

### Step 7: Access the Attendance System

Everything is set up! Let's test the system.

#### 7.1 Open the Main Page

1. **Open your web browser**
2. **Type in the address bar**: `http://localhost/attendease/`
   - Replace `attendease` with your folder name if you named it differently
3. **Press Enter**

**✅ You should see** the AttendEase home page with:
- School logo/branding
- Navigation menu
- Welcome message
- Features section

**⚠️ If you see an error:**
- Check that Apache is running in XAMPP Control Panel
- Verify the folder name matches the URL
- Check that files are in the correct location

#### 7.2 Test the Admin Panel

1. **In the browser**, go to: `http://localhost/attendease/admin/login.php`

2. **You'll see** the admin login page

3. **Enter the default credentials**:
   - **Username**: `admin`
   - **Password**: `admin123456`

4. **Click** the "Login" button

5. **You should see** the admin dashboard with:
   - Today's attendance statistics
   - Charts and graphs
   - Navigation menu
   - Recent activity

**✅ Congratulations! The system is working!**

---

## 🎯 What You Can Do Now

### For Testing the System:

1. **Register a Test Student**:
   - Go to: `http://localhost/attendease/register_student.php`
   - Fill in student information
   - Submit to generate QR code

2. **Mark Attendance**:
   - Go to: `http://localhost/attendease/scan_attendance.php`
   - Use QR code scanner (requires camera)
   - Or use manual attendance from admin panel

3. **View Reports**:
   - Login to admin panel
   - Go to "Attendance Reports"
   - Generate and export reports

4. **Manage Students**:
   - Login to admin panel
   - Go to "Manage Students"
   - Add, edit, or delete students

5. **Manage Sections**:
   - Login to admin panel
   - Go to "Manage Sections"
   - Create class sections

---

## ⚠️ Important Security Notes

### For Demo/Testing on Localhost:
- ✅ Default password is fine for local testing
- ✅ Empty database password is normal for XAMPP

### Before Going to Production:
1. **Change Admin Password**:
   - Login to admin panel
   - Change password immediately
   - Use a strong password (12+ characters, mix of letters/numbers/symbols)

2. **Set MySQL Root Password**:
   - Open XAMPP Control Panel
   - Click "Shell" button
   - Type: `mysqladmin -u root password newpassword`
   - Update `db_config.php` with the new password

3. **Use HTTPS**:
   - Camera features require HTTPS in production
   - Get an SSL certificate
   - Configure Apache for HTTPS

4. **Secure File Permissions**:
   - Restrict access to config files
   - Set proper folder permissions

---

## 🔧 Common Issues and Solutions

### Issue 1: "Cannot connect to database"
**Solution:**
- Check MySQL is running in XAMPP Control Panel
- Verify `db_config.php` credentials are correct
- Make sure database name matches: `asj_attendease_db`

### Issue 2: "Page not found (404 error)"
**Solution:**
- Check Apache is running in XAMPP Control Panel
- Verify URL matches your folder name
- Check files are in `C:\xampp\htdocs\attendease\`

### Issue 3: "QR codes not generating"
**Solution:**
- Check `uploads/qrcodes/` folder exists
- Verify folder has write permissions
- Try creating a test file in the folder to check permissions

### Issue 4: "Camera not working in QR scanner"
**Solution:**
- Allow camera permissions in browser
- Use Chrome or Firefox (recommended)
- For testing, use manual attendance instead
- Note: HTTPS required for camera in production (not localhost)

### Issue 5: "Blank white page"
**Solution:**
- Check PHP error logs in `C:\xampp\php\logs\`
- Enable error display by adding to top of PHP files:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```
- Check file permissions

### Issue 6: "Apache won't start"
**Solution:**
- **Port 80 conflict**: 
  - Close Skype or other programs using port 80
  - Change Apache port to 8080 in XAMPP Config
  - Access via: `http://localhost:8080/attendease/`
- **IIS conflict** (Windows):
  - Disable IIS in Windows Features
  - Or change Apache port

### Issue 7: "MySQL won't start"
**Solution:**
- **Port 3306 conflict**:
  - Close other MySQL/MariaDB installations
  - Change MySQL port in XAMPP Config
- **Previous instance running**:
  - Open Task Manager
  - End any mysql.exe processes
  - Try starting MySQL again

---

## 📞 Getting Help

### Documentation:
- **Main README**: See `README.md` for detailed technical documentation
- **Database Schema**: Check `database/` folder for SQL files
- **Code Documentation**: Comments in PHP files

### Support:
- **Email**: attendease08@gmail.com
- **GitHub Issues**: Report bugs or request features
- **XAMPP Documentation**: https://www.apachefriends.org/faq.html

---

## 🔄 Updating the System

If you receive updates to the system:

1. **Backup your database**:
   - Open phpMyAdmin
   - Select `asj_attendease_db`
   - Click "Export" tab
   - Click "Export" button
   - Save the .sql file

2. **Backup your files**:
   - Copy `C:\xampp\htdocs\attendease\` to a safe location

3. **Download new version**:
   - Get updated files
   - Replace old files (except config files)

4. **Update database** (if needed):
   - Check for new .sql files in `database/` folder
   - Import them in phpMyAdmin

5. **Test the system**:
   - Check admin panel
   - Test key features
   - Verify data is intact

---

## ✅ Quick Setup Checklist

Use this checklist to track your progress:

- [ ] Downloaded XAMPP
- [ ] Installed XAMPP
- [ ] Started Apache service
- [ ] Started MySQL service
- [ ] Downloaded attendance system files
- [ ] Copied files to htdocs folder
- [ ] Opened phpMyAdmin
- [ ] Created database `asj_attendease_db`
- [ ] Imported SQL file
- [ ] Edited `db_config.php` with correct credentials
- [ ] Created `uploads/qrcodes/` folder
- [ ] Accessed system at `http://localhost/attendease/`
- [ ] Logged into admin panel with default credentials
- [ ] Changed admin password (recommended)
- [ ] Tested student registration
- [ ] Tested attendance marking
- [ ] System ready for demo!

---

## 🎓 Next Steps

Now that your system is running:

1. **Explore the Features**:
   - Register students
   - Create sections
   - Mark attendance
   - Generate reports

2. **Customize**:
   - Update school information
   - Add school logo
   - Customize colors/branding

3. **Train Users**:
   - Show teachers how to use the system
   - Demonstrate QR code scanning
   - Explain report generation

4. **Plan Production Deployment**:
   - Choose hosting provider
   - Plan data migration
   - Set up SSL certificate
   - Configure backups

---

## 📝 System URLs Reference

Save these URLs for quick access:

| Page | URL |
|------|-----|
| **Main Page** | `http://localhost/attendease/` |
| **Admin Login** | `http://localhost/attendease/admin/login.php` |
| **Admin Dashboard** | `http://localhost/attendease/admin/dashboard.php` |
| **Student Registration** | `http://localhost/attendease/register_student.php` |
| **QR Scanner** | `http://localhost/attendease/scan_attendance.php` |
| **Manual Attendance** | `http://localhost/attendease/admin/manual_attendance.php` |
| **Manage Students** | `http://localhost/attendease/admin/manage_students.php` |
| **Manage Sections** | `http://localhost/attendease/admin/manage_sections.php` |
| **Reports** | `http://localhost/attendease/admin/attendance_reports_sections.php` |
| **phpMyAdmin** | `http://localhost/phpmyadmin/` |

**Note:** Replace `attendease` with your actual folder name if different.

---

## 🎉 Congratulations!

You've successfully set up the AttendEase Attendance Management System on your local computer!

The system is now ready for:
- ✅ Demonstration
- ✅ Testing
- ✅ Training
- ✅ Feature evaluation

**Remember**: This is a localhost setup for demo purposes. For production use, you'll need proper web hosting with security measures in place.

Enjoy using AttendEase! 🚀

---

**Last Updated**: November 2025  
**Version**: 2.0  
**For**: Academy of St. Joseph, Claveria Cagayan Inc.
