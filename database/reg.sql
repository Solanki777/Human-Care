-- =====================================================
-- -- DROP EXISTING DATABASES IF THEY EXIST
-- -- =====================================================
-- DROP DATABASE IF EXISTS human_care_admin;
-- DROP DATABASE IF EXISTS human_care_patients;
-- DROP DATABASE IF EXISTS human_care_doctors;

-- -- =====================================================
-- -- CREATE DATABASES
-- -- =====================================================
-- CREATE DATABASE human_care_admin;
-- CREATE DATABASE human_care_patients;
-- CREATE DATABASE human_care_doctors;

-- =====================================================
-- ADMIN DATABASE
-- =====================================================
USE human_care_admin;

-- Admin Users Table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System Settings Table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

-- Insert Default Admin (Username: admin, Password: password123)
INSERT INTO admins (username, email, password, full_name, role) VALUES
('admin', 'admin@humancare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin'),
('manager', 'manager@humancare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hospital Manager', 'admin');

-- Insert System Settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
('site_name', 'Human Care Hospital'),
('site_email', 'info@humancare.com'),
('site_phone', '+91 1234-567890'),
('require_doctor_verification', '1'),
('require_patient_verification', '0');

-- =====================================================
-- PATIENTS DATABASE
-- =====================================================
USE human_care_patients;

-- Patients Table
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    dob DATE NOT NULL,
    gender VARCHAR(10) NOT NULL,
    blood_group VARCHAR(5),
    password VARCHAR(255) NOT NULL,
    address TEXT,
    emergency_contact VARCHAR(15),
    is_verified BOOLEAN DEFAULT TRUE,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    verified_by INT,
    verified_at TIMESTAMP NULL,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patient Medical History Table
CREATE TABLE patient_medical_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    condition_name VARCHAR(100),
    diagnosis_date DATE,
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Patient Appointments Table
CREATE TABLE patient_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Insert Sample Patients (Password: password123)
INSERT INTO patients (first_name, last_name, email, phone, dob, gender, blood_group, password, address, emergency_contact, is_verified, verification_status) VALUES
('John', 'Doe', 'john@example.com', '+911234567890', '1990-01-15', 'male', 'O+', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123 Main Street, Rajkot', '+919876543210', TRUE, 'approved'),
('Jane', 'Smith', 'jane@example.com', '+919876543211', '1992-05-20', 'female', 'A+', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '456 Park Avenue, Rajkot', '+911234567890', TRUE, 'approved'),
('Amit', 'Patel', 'amit@example.com', '+919876543212', '1988-08-12', 'male', 'B+', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '789 Garden Road, Rajkot', '+919876543213', TRUE, 'approved');

-- =====================================================
-- DOCTORS DATABASE
-- =====================================================
USE human_care_doctors;

-- Doctors Table
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    dob DATE NOT NULL,
    gender VARCHAR(10) NOT NULL,
    password VARCHAR(255) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    qualification VARCHAR(255) DEFAULT 'MBBS',
    experience_years INT DEFAULT 0,
    license_number VARCHAR(50) NOT NULL UNIQUE,
    consultation_fee DECIMAL(10, 2) DEFAULT 1000.00,
    about TEXT,
    available_days VARCHAR(100),
    available_time VARCHAR(50),
    hospital_affiliation VARCHAR(100),
    is_verified BOOLEAN DEFAULT FALSE,
    verification_status ENUM('pending', 'approved', 'rejected', 'deleted') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_by INT,
    deleted_at TIMESTAMP NULL,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor Appointments Table
CREATE TABLE doctor_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT DEFAULT NULL,
    patient_name VARCHAR(100) NOT NULL,
    patient_email VARCHAR(100) NOT NULL,
    patient_phone VARCHAR(15) NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Doctor Schedule Table
CREATE TABLE doctor_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    start_time TIME,
    end_time TIME,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Insert Sample Doctors (Password: password123)
-- Pre-approved doctors
INSERT INTO doctors (first_name, last_name, email, phone, dob, gender, password, specialty, qualification, experience_years, license_number, consultation_fee, about, available_days, available_time, hospital_affiliation, is_verified, verification_status, verified_at) VALUES
('Rajesh', 'Kumar', 'dr.rajesh@humancare.com', '+911234567800', '1980-03-10', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cardiologist', 'MBBS, MD - Cardiology', 15, 'MED-12345', 1500.00, 'Expert in heart diseases, cardiac surgery, and preventive cardiology', 'Mon-Sat', '9 AM - 5 PM', 'Human Care Central Hospital', TRUE, 'approved', NOW()),
('Sarah', 'Patel', 'dr.sarah@humancare.com', '+911234567801', '1985-07-22', 'female', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pediatrician', 'MBBS, DCH - Pediatrics', 12, 'MED-12346', 1000.00, 'Specialized in child health, vaccinations, and developmental care', 'Mon-Sat', '10 AM - 6 PM', 'Human Care Central Hospital', TRUE, 'approved', NOW()),
('Karthik', 'Reddy', 'dr.karthik@humancare.com', '+911234567804', '1982-09-25', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Neurologist', 'MBBS, DM - Neurology', 14, 'MED-12349', 2000.00, 'Expert in brain and nervous system disorders, stroke care', 'Mon-Sat', '9 AM - 5 PM', 'City Medical Center', TRUE, 'approved', NOW());

-- Pending verification doctors
INSERT INTO doctors (first_name, last_name, email, phone, dob, gender, password, specialty, qualification, experience_years, license_number, consultation_fee, about, available_days, available_time, hospital_affiliation, is_verified, verification_status) VALUES
('Amit', 'Shah', 'dr.amit@humancare.com', '+911234567802', '1978-11-05', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Orthopedic Surgeon', 'MBBS, MS - Orthopedics', 18, 'MED-12347', 1800.00, 'Expert in bone, joint, and spine surgeries with advanced techniques', 'Mon-Fri', '8 AM - 4 PM', 'Human Care Central Hospital', FALSE, 'pending');

-- Rejected doctor
INSERT INTO doctors (first_name, last_name, email, phone, dob, gender, password, specialty, qualification, experience_years, license_number, consultation_fee, about, available_days, available_time, hospital_affiliation, is_verified, verification_status, rejection_reason, verified_at) VALUES
('Priya', 'Sharma', 'dr.priya@humancare.com', '+911234567803', '1987-04-18', 'female', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dermatologist', 'MBBS, MD - Dermatology', 10, 'MED-12348', 1200.00, 'Specialized in skin disorders, cosmetic procedures, and hair care', 'Tue-Sun', '11 AM - 7 PM', 'Human Care Specialty Clinic', FALSE, 'rejected', 'License verification failed. Please provide valid medical license documentation.', NOW());

-- =====================================================
-- TEST CREDENTIALS
-- =====================================================
/*
===========================================
ADMIN LOGIN CREDENTIALS:
===========================================
Username: admin
Password: password123
Email: admin@humancare.com
Access: Full admin panel access

Username: manager
Password: password123
Email: manager@humancare.com
Access: Limited admin access

===========================================
PATIENT LOGIN CREDENTIALS:
===========================================
All patients can login immediately (no verification required)

Email: john@example.com
Password: password123
Status: Approved (Active)

Email: jane@example.com
Password: password123
Status: Approved (Active)

Email: amit@example.com
Password: password123
Status: Approved (Active)

===========================================
DOCTOR LOGIN CREDENTIALS:
===========================================

✅ APPROVED DOCTORS (Can Login):
--------------------------------
Email: dr.rajesh@humancare.com
Password: password123
Specialty: Cardiologist
Status: ✓ Verified & Approved

Email: dr.sarah@humancare.com
Password: password123
Specialty: Pediatrician
Status: ✓ Verified & Approved

Email: dr.karthik@humancare.com
Password: password123
Specialty: Neurologist
Status: ✓ Verified & Approved

⏳ PENDING DOCTORS (Cannot Login - Awaiting Admin Approval):
------------------------------------------------------------
Email: dr.amit@humancare.com
Password: password123
Specialty: Orthopedic Surgeon
Status: ⏳ Pending Admin Verification
Message: "Your account is pending admin verification. Please wait for approval email."

❌ REJECTED DOCTORS (Cannot Login):
------------------------------------
Email: dr.priya@humancare.com
Password: password123
Specialty: Dermatologist
Status: ❌ Rejected
Reason: License verification failed
Message: Shows rejection reason on login attempt

===========================================
WORKFLOW TESTING:
===========================================

1. DOCTOR REGISTRATION:
   - Register new doctor at register.php
   - Doctor data saved with verification_status = 'pending'
   - Doctor CANNOT login yet

2. ADMIN VERIFICATION:
   - Admin logs in at admin_login.php
   - Views pending doctors at admin_doctors.php
   - Admin approves or rejects the application
   
3. ON APPROVAL:
   - Doctor status changes to 'approved'
   - is_verified set to TRUE
   - Email sent to doctor with login credentials
   - Doctor can NOW login at login.php
   
4. ON REJECTION:
   - Doctor status changes to 'rejected'
   - rejection_reason saved in database
   - Email sent to doctor with rejection reason
   - Doctor CANNOT login (shows rejection message)

===========================================
DATABASE STRUCTURE:
===========================================

human_care_admin:
  - admins (admin accounts)
  - system_settings (site configuration)
  - activity_logs (admin action logs)

human_care_patients:
  - patients (patient accounts - auto-approved)
  - patient_medical_history
  - patient_appointments

human_care_doctors:
  - doctors (doctor accounts - requires approval)
  - doctor_appointments
  - doctor_schedule

===========================================
IMPORTANT NOTES:
===========================================

1. All passwords are hashed using bcrypt
2. Default password for all accounts: password123
3. Patients are auto-approved upon registration
4. Doctors MUST be approved by admin before login
5. Email notifications sent on approval/rejection
6. Admin can track all actions via activity_logs

*/
-- =====================================================
-- APPOINTMENTS TABLE (New Unified Structure)
-- =====================================================
USE human_care_admin;

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Patient Information
    patient_id INT NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    patient_email VARCHAR(100) NOT NULL,
    patient_phone VARCHAR(15) NOT NULL,
    patient_age INT,
    
    -- Doctor Information
    doctor_id INT NOT NULL,
    doctor_name VARCHAR(100) NOT NULL,
    doctor_specialty VARCHAR(100),
    
    -- Appointment Details
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    consultation_type ENUM('in-person', 'online') DEFAULT 'in-person',
    reason_for_visit TEXT NOT NULL,
    symptoms TEXT,
    
    -- Status & Verification
    status ENUM(
        'pending',      -- Awaiting admin approval
        'approved',     -- Approved by admin
        'rejected',     -- Rejected by admin
        'rescheduled',  -- Admin changed time
        'completed',    -- Consultation done
        'cancelled'     -- Cancelled
    ) DEFAULT 'pending',
    
    -- Admin Actions
    verified_by INT,                    -- Admin who verified
    verified_at TIMESTAMP NULL,         -- When verified
    rejection_reason TEXT,              -- If rejected
    admin_notes TEXT,                   -- Admin comments
    
    -- Rescheduling
    original_date DATE,                 -- If rescheduled
    original_time TIME,
    rescheduled_by INT,
    rescheduled_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_status (status),
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- APPOINTMENT NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE appointment_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    recipient_type ENUM('patient', 'doctor', 'admin'),
    recipient_id INT NOT NULL,
    notification_type ENUM('new', 'approved', 'rejected', 'rescheduled', 'cancelled', 'reminder'),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_type, recipient_id, is_read)
);

-- =====================================================
-- APPOINTMENT HISTORY TABLE (Audit Trail)
-- =====================================================
CREATE TABLE appointment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,  -- 'created', 'approved', 'rejected', etc.
    performed_by INT,
    performed_by_type ENUM('admin', 'patient', 'doctor'),
    old_status VARCHAR(20),
    new_status VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_appointment (appointment_id)
);