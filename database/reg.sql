
-- =====================================================
-- ADMIN DATABASE
-- =====================================================


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

-- Insert Default Admin (Username: admin, Password: admin123)
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
    is_verified BOOLEAN DEFAULT FALSE,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
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
('Amit', 'Patel', 'amit@example.com', '+919876543212', '1988-08-12', 'male', 'B+', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '789 Garden Road, Rajkot', '+919876543213', FALSE, 'pending');



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
    qualification VARCHAR(255) NOT NULL,
    experience_years INT NOT NULL,
    license_number VARCHAR(50) NOT NULL UNIQUE,
    consultation_fee DECIMAL(10, 2),
    about TEXT,
    available_days VARCHAR(100),
    available_time VARCHAR(50),
    hospital_affiliation VARCHAR(100),
    is_verified BOOLEAN DEFAULT FALSE,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor Appointments Table
CREATE TABLE doctor_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
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
INSERT INTO doctors (first_name, last_name, email, phone, dob, gender, password, specialty, qualification, experience_years, license_number, consultation_fee, about, available_days, available_time, hospital_affiliation, is_verified, verification_status) VALUES
('Rajesh', 'Kumar', 'dr.rajesh@humancare.com', '+911234567800', '1980-03-10', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cardiologist', 'MBBS, MD - Cardiology', 15, 'MED-12345', 1500.00, 'Expert in heart diseases, cardiac surgery, and preventive cardiology', 'Mon-Sat', '9 AM - 5 PM', 'Human Care Central Hospital', TRUE, 'approved'),
('Sarah', 'Patel', 'dr.sarah@humancare.com', '+911234567801', '1985-07-22', 'female', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pediatrician', 'MBBS, DCH - Pediatrics', 12, 'MED-12346', 1000.00, 'Specialized in child health, vaccinations, and developmental care', 'Mon-Sat', '10 AM - 6 PM', 'Human Care Central Hospital', TRUE, 'approved'),
('Amit', 'Shah', 'dr.amit@humancare.com', '+911234567802', '1978-11-05', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Orthopedic Surgeon', 'MBBS, MS - Orthopedics', 18, 'MED-12347', 1800.00, 'Expert in bone, joint, and spine surgeries with advanced techniques', 'Mon-Fri', '8 AM - 4 PM', 'Human Care Central Hospital', FALSE, 'pending'),
('Priya', 'Sharma', 'dr.priya@humancare.com', '+911234567803', '1987-04-18', 'female', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dermatologist', 'MBBS, MD - Dermatology', 10, 'MED-12348', 1200.00, 'Specialized in skin disorders, cosmetic procedures, and hair care', 'Tue-Sun', '11 AM - 7 PM', 'Human Care Specialty Clinic', FALSE, 'rejected'),
('Karthik', 'Reddy', 'dr.karthik@humancare.com', '+911234567804', '1982-09-25', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Neurologist', 'MBBS, DM - Neurology', 14, 'MED-12349', 2000.00, 'Expert in brain and nervous system disorders, stroke care', 'Mon-Sat', '9 AM - 5 PM', 'City Medical Center', TRUE, 'approved');

-- =====================================================
-- TEST CREDENTIALS
-- =====================================================
/*
ADMIN LOGIN:
Username: admin
Password: password123

PATIENT LOGIN:
Email: john@example.com (Approved)
Email: amit@example.com (Pending - needs verification)
Password: password123

DOCTOR LOGIN:
Email: dr.rajesh@humancare.com (Approved)
Email: dr.amit@humancare.com (Pending - needs verification)
Email: dr.priya@humancare.com (Rejected)
Password: password123
*/