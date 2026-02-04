<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "human_care_doctors");

$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";
$error = "";

if ($doctor_id === 0) {
    header("Location: admin_doctors.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $specialty = trim($_POST['specialty']);
    $qualification = trim($_POST['qualification']);
    $experience_years = intval($_POST['experience_years']);
    $license_number = trim($_POST['license_number']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $about = trim($_POST['about']);
    $available_days = trim($_POST['available_days']);
    $available_time = trim($_POST['available_time']);
    $hospital_affiliation = trim($_POST['hospital_affiliation']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($specialty)) {
        $error = "Please fill in all required fields.";
    } else {
        // Update doctor information
        $stmt = $conn->prepare("UPDATE doctors SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?, 
            specialty = ?, 
            qualification = ?, 
            experience_years = ?, 
            license_number = ?, 
            consultation_fee = ?, 
            about = ?, 
            available_days = ?, 
            available_time = ?, 
            hospital_affiliation = ? 
            WHERE id = ?");
        
        // Type string breakdown:
        // s = string, i = integer, d = decimal/double
        // Count: 14 placeholders (?) = 14 bind variables needed
        // Types: s s s s s s i s d s s s s i
        //        │ │ │ │ │ │ │ │ │ │ │ │ │ └─ doctor_id (int)
        //        │ │ │ │ │ │ │ │ │ │ │ │ └─── hospital_affiliation (string)
        //        │ │ │ │ │ │ │ │ │ │ │ └───── available_time (string)
        //        │ │ │ │ │ │ │ │ │ │ └─────── available_days (string)
        //        │ │ │ │ │ │ │ │ │ └───────── about (string)
        //        │ │ │ │ │ │ │ │ └─────────── consultation_fee (decimal)
        //        │ │ │ │ │ │ │ └───────────── license_number (string)
        //        │ │ │ │ │ │ └─────────────── experience_years (int)
        //        │ │ │ │ │ └───────────────── qualification (string)
        //        │ │ │ │ └─────────────────── specialty (string)
        //        │ │ │ └───────────────────── phone (string)
        //        │ │ └─────────────────────── email (string)
        //        │ └───────────────────────── last_name (string)
        //        └─────────────────────────── first_name (string)
        
        $stmt->bind_param("ssssssisdssssi", 
            $first_name,           // s - string
            $last_name,            // s - string
            $email,                // s - string
            $phone,                // s - string
            $specialty,            // s - string
            $qualification,        // s - string
            $experience_years,     // i - integer
            $license_number,       // s - string
            $consultation_fee,     // d - decimal
            $about,                // s - string
            $available_days,       // s - string
            $available_time,       // s - string
            $hospital_affiliation, // s - string
            $doctor_id             // i - integer
        );
        
        if ($stmt->execute()) {
            // Log activity
            $admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
            $log_stmt = $admin_conn->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, ?, ?)");
            $log_action = "doctor_update";
            $log_desc = "Updated doctor ID $doctor_id: Dr. $first_name $last_name";
            $log_stmt->bind_param("iss", $_SESSION['admin_id'], $log_action, $log_desc);
            $log_stmt->execute();
            $admin_conn->close();
            
            $message = "Doctor information updated successfully! Changes are now visible on the public doctors page.";
        } else {
            $error = "Failed to update doctor information. Please try again.";
        }
        $stmt->close();
    }
}

// Fetch doctor information
$stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_doctors.php");
    exit();
}

$doctor = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor - Admin Panel</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .edit-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .form-section {
            margin-bottom: 35px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-preview {
            background: #10b981;
            color: white;
        }

        .btn-preview:hover {
            background: #059669;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
        }

        .doctor-preview {
            background: #f9fafb;
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .preview-title {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .character-count {
            font-size: 12px;
            color: #999;
            text-align: right;
            margin-top: 5px;
        }

        .status-info {
            background: #e0e7ff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .status-info strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            ADMIN PANEL
        </div>
        <div class="user-profile">
            <div class="user-avatar">👨‍💼</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
            </div>
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="admin_doctors.php">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Manage Doctors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_patients.php">
                        <span class="nav-icon">👥</span>
                        <span>Manage Patients</span>
                    </a>
                </li>
            </ul>
        </nav>
        <form method="post" action="admin_logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <main class="main-content">
        <div class="edit-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 32px; margin-bottom: 10px;">✏️ Edit Doctor Profile</h1>
                    <p style="color: #666;">Update doctor information visible to patients</p>
                </div>
                <a href="admin_doctors.php" class="btn btn-secondary">← Back to List</a>
            </div>

            <?php if ($message): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="status-info">
                <strong>Doctor Status:</strong> 
                <?php if ($doctor['verification_status'] === 'approved'): ?>
                    ✅ Verified & Visible on Public Page
                <?php elseif ($doctor['verification_status'] === 'pending'): ?>
                    ⏳ Pending Verification - Not Visible
                <?php else: ?>
                    ❌ Rejected - Not Visible
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <div class="form-card">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="section-title">
                            👤 Personal Information
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone <span class="required">*</span></label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="form-section">
                        <div class="section-title">
                            🎓 Professional Information
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Specialty <span class="required">*</span></label>
                                <select name="specialty" required>
                                    <option value="">Select Specialty</option>
                                    <option value="Cardiologist" <?php echo $doctor['specialty'] === 'Cardiologist' ? 'selected' : ''; ?>>Cardiologist</option>
                                    <option value="Pediatrician" <?php echo $doctor['specialty'] === 'Pediatrician' ? 'selected' : ''; ?>>Pediatrician</option>
                                    <option value="Orthopedic Surgeon" <?php echo $doctor['specialty'] === 'Orthopedic Surgeon' ? 'selected' : ''; ?>>Orthopedic Surgeon</option>
                                    <option value="Dermatologist" <?php echo $doctor['specialty'] === 'Dermatologist' ? 'selected' : ''; ?>>Dermatologist</option>
                                    <option value="Neurologist" <?php echo $doctor['specialty'] === 'Neurologist' ? 'selected' : ''; ?>>Neurologist</option>
                                    <option value="Gynecologist" <?php echo $doctor['specialty'] === 'Gynecologist' ? 'selected' : ''; ?>>Gynecologist</option>
                                    <option value="Psychiatrist" <?php echo $doctor['specialty'] === 'Psychiatrist' ? 'selected' : ''; ?>>Psychiatrist</option>
                                    <option value="General Physician" <?php echo $doctor['specialty'] === 'General Physician' ? 'selected' : ''; ?>>General Physician</option>
                                    <option value="ENT Specialist" <?php echo $doctor['specialty'] === 'ENT Specialist' ? 'selected' : ''; ?>>ENT Specialist</option>
                                    <option value="Ophthalmologist" <?php echo $doctor['specialty'] === 'Ophthalmologist' ? 'selected' : ''; ?>>Ophthalmologist</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Experience (Years) <span class="required">*</span></label>
                                <input type="number" name="experience_years" value="<?php echo $doctor['experience_years']; ?>" min="0" max="50" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Qualification <span class="required">*</span></label>
                                <input type="text" name="qualification" value="<?php echo htmlspecialchars($doctor['qualification']); ?>" placeholder="e.g., MBBS, MD - Cardiology" required>
                                <div class="form-hint">Display credentials that appear on the doctor's card</div>
                            </div>
                            <div class="form-group">
                                <label>License Number <span class="required">*</span></label>
                                <input type="text" name="license_number" value="<?php echo htmlspecialchars($doctor['license_number']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Practice Information -->
                    <div class="form-section">
                        <div class="section-title">
                            🏥 Practice Information
                        </div>

                        <div class="form-group">
                            <label>About Doctor</label>
                            <textarea name="about" id="aboutText" maxlength="500" oninput="updateCharCount()"><?php echo htmlspecialchars($doctor['about']); ?></textarea>
                            <div class="character-count">
                                <span id="charCount">0</span> / 500 characters
                            </div>
                            <div class="form-hint">Brief description shown to patients (max 500 characters)</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Consultation Fee (₹)</label>
                                <input type="number" name="consultation_fee" value="<?php echo $doctor['consultation_fee']; ?>" min="0" step="0.01" placeholder="1000">
                                <div class="form-hint">Fee displayed to patients before booking</div>
                            </div>
                            <div class="form-group">
                                <label>Hospital Affiliation</label>
                                <input type="text" name="hospital_affiliation" value="<?php echo htmlspecialchars($doctor['hospital_affiliation']); ?>" placeholder="Human Care Central Hospital">
                            </div>
                        </div>
                    </div>

                    <!-- Availability Information -->
                    <div class="form-section">
                        <div class="section-title">
                            📅 Availability Schedule
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Available Days</label>
                                <input type="text" name="available_days" value="<?php echo htmlspecialchars($doctor['available_days']); ?>" placeholder="Mon-Sat">
                                <div class="form-hint">e.g., Mon-Fri, Mon-Sat, Tue-Sun</div>
                            </div>
                            <div class="form-group">
                                <label>Available Time</label>
                                <input type="text" name="available_time" value="<?php echo htmlspecialchars($doctor['available_time']); ?>" placeholder="9 AM - 5 PM">
                                <div class="form-hint">e.g., 9 AM - 5 PM, 10 AM - 6 PM</div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Section -->
                    <div class="doctor-preview">
                        <div class="preview-title">📱 How this will appear on doctors.php:</div>
                        <div style="background: white; padding: 20px; border-radius: 10px; margin-top: 10px;">
                            <div style="font-size: 20px; font-weight: 600; margin-bottom: 5px;">
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </div>
                            <div style="color: #667eea; font-weight: 600; margin-bottom: 10px;">
                                <?php echo htmlspecialchars($doctor['specialty']); ?>
                            </div>
                            <div style="font-size: 14px; color: #666;">
                                ⭐ <?php echo $doctor['experience_years']; ?> years experience
                            </div>
                            <?php if ($doctor['consultation_fee']): ?>
                                <div style="margin-top: 10px; color: #667eea; font-weight: 600;">
                                    Consultation Fee: ₹<?php echo number_format($doctor['consultation_fee']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            💾 Save Changes
                        </button>
                        <a href="doctors.php" target="_blank" class="btn btn-preview">
                            👁️ Preview on Public Page
                        </a>
                        <a href="admin_doctors.php" class="btn btn-secondary">
                            ✕ Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function updateCharCount() {
            const text = document.getElementById('aboutText').value;
            const count = text.length;
            document.getElementById('charCount').textContent = count;
            
            if (count > 450) {
                document.getElementById('charCount').style.color = '#ef4444';
            } else if (count > 400) {
                document.getElementById('charCount').style.color = '#f59e0b';
            } else {
                document.getElementById('charCount').style.color = '#10b981';
            }
        }

        // Initialize character count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCount();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>