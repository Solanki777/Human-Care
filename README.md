# Human Care - Hospital Management System

Human Care is a comprehensive, multi-portal hospital management system built with PHP and MySQL. It features dedicated portals for administrators, doctors, and patients, offering functionalities like appointment booking, patient records management, doctor schedules, and health education resources.

## 🚀 Features

### 👤 Patient Portal
- **Easy Registration & Login**: Simple onboarding process for patients.
- **Appointment Management**: Book, view, and manage medical appointments.
- **Medical Records**: Access personal medical history and prescriptions.
- **Consultation**: 24/7 online consultation and chat support with doctors.
- **Health Education**: Access to health, wellness, and prevention resources.

### 👨‍⚕️ Doctor Portal
- **Secure Registration**: Doctor accounts require verification and approval from the admin before access is granted.
- **Schedule Management**: Manage daily schedules, available days, and consultation times.
- **Appointment Handling**: View, accept, or manage upcoming patient appointments.
- **Patient Records**: Access patient medical history during consultations.
- **Prescriptions**: Prescribe medications and add notes to patient records.

### 🛡️ Admin Portal
- **System Oversight**: Full control over the hospital's digital operations.
- **Doctor Verification**: Review, approve, or reject new doctor registrations.
- **User Management**: Manage patient and doctor records.
- **Activity Monitoring**: Track all actions via system activity logs.
- **System Settings**: Configure site details, contact info, and verification requirements.

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP (Session Management, OOP concepts for Database)
- **Database**: MySQL (Multi-database architecture)

## 📋 Installation & Setup

1. **Clone the Repository**
   Download or clone the repository into your local web server's root directory (e.g., `htdocs` for XAMPP or `www` for WAMP).
   
2. **Start Server**
   Start your Apache and MySQL services from your control panel.

3. **Database Configuration**
   Open your MySQL client (like phpMyAdmin) and execute the following queries to create the necessary databases:
   ```sql
   CREATE DATABASE human_care_admin;
   CREATE DATABASE human_care_patients;
   CREATE DATABASE human_care_doctors;
   ```

4. **Import Database Structure**
   Import the provided SQL dump located at `database/reg.sql`. This script will automatically create the required tables in the respective databases and populate them with essential default data.

5. **Verify Configuration**
   Check the database configuration in `config/database.php`. By default, it connects to `localhost` with the username `root` and an empty password. Update these credentials if your local environment setup differs.

6. **Access the Application**
   Open your web browser and navigate to the project directory (e.g., `http://localhost/human-care` or the folder name you used).

## 🔐 Default Test Credentials

All default test accounts use the same password: **`password123`**

### Admin Portal (`admin_login.php`)
| Role | Username | Email |
|------|----------|-------|
| Super Admin | `admin` | `admin@humancare.com` |
| Hospital Manager | `manager` | `manager@humancare.com` |

### Patient Portal (`login.php`)
| Name | Email | Status |
|------|-------|--------|
| John Doe | `john@example.com` | Active |
| Jane Smith | `jane@example.com` | Active |

### Doctor Portal (`login.php`)
| Name | Specialty | Email | Status |
|------|-----------|-------|--------|
| Dr. Rajesh Kumar | Cardiologist | `dr.rajesh@humancare.com` | Verified |
| Dr. Sarah Patel | Pediatrician | `dr.sarah@humancare.com` | Verified |
| Dr. Amit Shah | Orthopedic | `dr.amit@humancare.com` | Pending Verification |

*Note: Doctors with a "Pending" or "Rejected" status cannot log in until an admin approves their account.*

## 📂 Project Structure

- `/classes` - Contains PHP class files.
- `/config` - Database and application configuration files.
- `/database` - Contains the `reg.sql` dump file.
- `/includes` - Reusable UI components (header, footer, sidebar).
- `/scripts` - JavaScript files for frontend functionality.
- `/styles` - CSS stylesheets.
- `/uploads` - Directory for user uploads (profile pictures, documents).

## 📄 License
&copy; 2025 Human Care. All rights reserved.
