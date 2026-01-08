-- =====================================================
-- DATABASE 1: PATIENTS DATABASE
-- =====================================================
CREATE DATABASE IF NOT EXISTS human_care_patients;
USE human_care_patients;

-- Patients Table
CREATE TABLE IF NOT EXISTS patients (
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
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Patient Medical History Table
CREATE TABLE IF NOT EXISTS patient_medical_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    condition_name VARCHAR(100),
    diagnosis_date DATE,
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Patient Appointments Table
CREATE TABLE IF NOT EXISTS patient_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Sample Patient Data (Password: password123)
INSERT INTO patients (first_name, last_name, email, phone, dob, gender, blood_group, password, address, emergency_contact) VALUES
('John', 'Doe', 'john@example.com', '+911234567890', '1990-01-15', 'male', 'O+', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123 Main Street, Rajkot', '+919876543210'),
('Jane', 'Smith', 'jane@example.com', '+919876543211', '1992-05-20', 'female', 'A+', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '456 Park Avenue, Rajkot', '+911234567890'),
('Amit', 'Patel', 'amit@example.com', '+919876543212', '1988-08-12', 'male', 'B+', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '789 Garden Road, Rajkot', '+919876543213');


-- =====================================================
-- DATABASE 2: DOCTORS DATABASE
-- =====================================================
CREATE DATABASE IF NOT EXISTS human_care_doctors;
USE human_care_doctors;

-- Doctors Table
CREATE TABLE IF NOT EXISTS doctors (
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
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor Appointments Table
CREATE TABLE IF NOT EXISTS doctor_appointments (
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
CREATE TABLE IF NOT EXISTS doctor_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    start_time TIME,
    end_time TIME,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Sample Doctor Data (Password: password123)
INSERT INTO doctors (first_name, last_name, email, phone, dob, gender, password, specialty, qualification, experience_years, license_number, consultation_fee, about, available_days, available_time, hospital_affiliation) VALUES
('Rajesh', 'Kumar', 'dr.rajesh@humancare.com', '+911234567800', '1980-03-10', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cardiologist', 'MBBS, MD - Cardiology', 15, 'MED-12345', 1500.00, 'Expert in heart diseases, cardiac surgery, and preventive cardiology', 'Mon-Sat', '9 AM - 5 PM', 'Human Care Central Hospital'),
('Sarah', 'Patel', 'dr.sarah@humancare.com', '+911234567801', '1985-07-22', 'female', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pediatrician', 'MBBS, DCH - Pediatrics', 12, 'MED-12346', 1000.00, 'Specialized in child health, vaccinations, and developmental care', 'Mon-Sat', '10 AM - 6 PM', 'Human Care Central Hospital'),
('Amit', 'Shah', 'dr.amit@humancare.com', '+911234567802', '1978-11-05', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Orthopedic Surgeon', 'MBBS, MS - Orthopedics', 18, 'MED-12347', 1800.00, 'Expert in bone, joint, and spine surgeries with advanced techniques', 'Mon-Fri', '8 AM - 4 PM', 'Human Care Central Hospital'),
('Priya', 'Sharma', 'dr.priya@humancare.com', '+911234567803', '1987-04-18', 'female', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dermatologist', 'MBBS, MD - Dermatology', 10, 'MED-12348', 1200.00, 'Specialized in skin disorders, cosmetic procedures, and hair care', 'Tue-Sun', '11 AM - 7 PM', 'Human Care Specialty Clinic'),
('Karthik', 'Reddy', 'dr.karthik@humancare.com', '+911234567804', '1982-09-25', 'male', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Neurologist', 'MBBS, DM - Neurology', 14, 'MED-12349', 2000.00, 'Expert in brain and nervous system disorders, stroke care', 'Mon-Sat', '9 AM - 5 PM', 'City Medical Center'),
('Anjali', 'Desai', 'dr.anjali@humancare.com', '+911234567805', '1983-12-08', 'female', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gynecologist', 'MBBS, MD - Gynecology', 16, 'MED-12350', 1600.00, 'Specialized in women''s health, pregnancy care, and reproductive health', 'Mon-Sat', '10 AM - 6 PM', 'Human Care Central Hospital');

-- Note: Default password for all sample users is: password123