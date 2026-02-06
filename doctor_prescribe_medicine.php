<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'];

// Database connections
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$patients_conn = new mysqli("localhost", "root", "", "human_care_patients");

if ($doctors_conn->connect_error || $patients_conn->connect_error) {
    die("Connection failed");
}

$success_message = "";
$error_message = "";
$appointment = null;
$existing_prescription = null;

// Get appointment ID from URL
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($appointment_id > 0) {
    // Get appointment details
    $stmt = $doctors_conn->prepare("
        SELECT da.*, 
               p.first_name as patient_first_name, 
               p.last_name as patient_last_name,
               p.email as patient_email,
               p.phone as patient_phone,
               p.date_of_birth as patient_dob,
               p.gender as patient_gender
        FROM doctor_appointments da
        LEFT JOIN human_care_patients.patients p ON da.patient_id = p.id
        WHERE da.id = ? AND da.doctor_id = ?
    ");
    $stmt->bind_param("ii", $appointment_id, $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    // Check if appointment exists and belongs to this doctor
    if (!$appointment) {
        $error_message = "Appointment not found or you don't have access to it.";
    } elseif ($appointment['status'] !== 'completed') {
        $error_message = "You can only prescribe medicines for completed appointments.";
    } else {
        // Check if prescription already exists
        $stmt = $doctors_conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_prescription = $result->fetch_assoc();
        $stmt->close();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment) {
    $medicine_names = $_POST['medicine_name'] ?? [];
    $medicine_descriptions = $_POST['medicine_description'] ?? [];
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    // Remove empty entries
    $medicines = [];
    for ($i = 0; $i < count($medicine_names); $i++) {
        if (!empty(trim($medicine_names[$i]))) {
            $medicines[] = [
                'name' => trim($medicine_names[$i]),
                'description' => trim($medicine_descriptions[$i] ?? '')
            ];
        }
    }

    if (empty($medicines)) {
        $error_message = "Please add at least one medicine.";
    } else {
        // Convert medicines array to JSON
        $medicines_json = json_encode($medicines);

        if ($existing_prescription) {
            // Update existing prescription
            $stmt = $doctors_conn->prepare("
                UPDATE prescriptions 
                SET medicines = ?, diagnosis = ?, additional_notes = ?, updated_at = NOW()
                WHERE appointment_id = ?
            ");
            $stmt->bind_param("sssi", $medicines_json, $diagnosis, $additional_notes, $appointment_id);
        } else {
            // Create new prescription
            $stmt = $doctors_conn->prepare("
                INSERT INTO prescriptions 
                (appointment_id, doctor_id, patient_id, medicines, diagnosis, additional_notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iiisss", 
                $appointment_id, 
                $doctor_id, 
                $appointment['patient_id'], 
                $medicines_json, 
                $diagnosis, 
                $additional_notes
            );
        }

        if ($stmt->execute()) {
            $success_message = "Prescription saved successfully!";
            
            // Refresh prescription data
            $stmt = $doctors_conn->prepare("SELECT * FROM prescriptions WHERE appointment_id = ?");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_prescription = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error_message = "Failed to save prescription. Please try again.";
        }
        $stmt->close();
    }
}

// Parse existing medicines if available
$saved_medicines = [];
if ($existing_prescription && !empty($existing_prescription['medicines'])) {
    $saved_medicines = json_decode($existing_prescription['medicines'], true) ?? [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescribe Medicine - Human Care</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .prescription-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .patient-info-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .patient-info-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e7ff;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        .prescription-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .medicines-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .medicines-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .medicines-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .medicines-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }

        .medicines-table td {
            padding: 12px;
            vertical-align: top;
        }

        .medicine-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .medicine-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .medicine-description {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
            transition: all 0.3s;
        }

        .medicine-description:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .remove-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .remove-btn:hover {
            background: #dc2626;
        }

        .add-medicine-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .add-medicine-btn:hover {
            background: #059669;
            transform: translateY(-2px);
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

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .submit-btn {
            flex: 1;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .cancel-btn {
            flex: 1;
            padding: 15px;
            background: #f3f4f6;
            color: #333;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .cancel-btn:hover {
            background: #e5e7eb;
        }

        .success-alert {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-alert {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .helper-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        @media (max-width: 768px) {
            .medicines-table {
                display: block;
                overflow-x: auto;
            }

            .action-buttons {
                flex-direction: column;
            }

            .patient-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Menu Toggle Button -->
    <button class="menu-toggle" id="menuToggle">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">⚕️</div>
            DOCTOR PANEL
        </div>

        <div class="user-profile">
            <div class="user-avatar">👨‍⚕️</div>
            <div class="user-info">
                <h3>Dr. <?php echo htmlspecialchars($doctor_name); ?></h3>
                <p>Doctor</p>
            </div>
        </div>

        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link" href="doctor_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="doctor_dashboard.php">
                        <span class="nav-icon">💊</span>
                        <span>Prescriptions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="doctor_profile.php">
                        <span class="nav-icon">👤</span>
                        <span>My Profile</span>
                    </a>
                </li>
            </ul>
        </nav>

        <form method="post" action="logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="prescription-container">
            <h1 style="margin-bottom: 25px; color: #333;">💊 Prescribe Medicine</h1>

            <?php if ($success_message): ?>
                <div class="success-alert">
                    <span style="font-size: 20px;">✅</span>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="error-alert">
                    <span style="font-size: 20px;">⚠️</span>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($appointment): ?>
                <!-- Patient Information Card -->
                <div class="patient-info-card">
                    <div class="patient-info-header">
                        <div class="patient-avatar">
                            <?php echo $appointment['patient_gender'] === 'Female' ? '👩' : '👨'; ?>
                        </div>
                        <div>
                            <h2 style="margin: 0; color: #333;">
                                <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                            </h2>
                            <span class="badge badge-completed">Completed Appointment</span>
                        </div>
                    </div>

                    <div class="patient-details">
                        <div class="detail-item">
                            <span class="detail-label">Appointment Date</span>
                            <span class="detail-value"><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Appointment Time</span>
                            <span class="detail-value"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Gender</span>
                            <span class="detail-value"><?php echo htmlspecialchars($appointment['patient_gender'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date of Birth</span>
                            <span class="detail-value"><?php echo $appointment['patient_dob'] ? date('F d, Y', strtotime($appointment['patient_dob'])) : 'N/A'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value"><?php echo htmlspecialchars($appointment['patient_phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($appointment['patient_email'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Prescription Form -->
                <div class="prescription-form">
                    <form method="POST" id="prescriptionForm">
                        <div class="form-section-title">
                            <span style="font-size: 24px;">💊</span>
                            <span>Medicine Prescription</span>
                        </div>

                        <button type="button" class="add-medicine-btn" onclick="addMedicineRow()">
                            <span style="font-size: 18px;">+</span>
                            <span>Add Medicine</span>
                        </button>

                        <table class="medicines-table" id="medicinesTable">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Medicine Name</th>
                                    <th style="width: 60%;">Description / Instructions</th>
                                    <th style="width: 15%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="medicinesBody">
                                <?php if (!empty($saved_medicines)): ?>
                                    <?php foreach ($saved_medicines as $index => $medicine): ?>
                                        <tr>
                                            <td>
                                                <input type="text" 
                                                       name="medicine_name[]" 
                                                       class="medicine-input" 
                                                       placeholder="e.g., Paracetamol 500mg"
                                                       value="<?php echo htmlspecialchars($medicine['name']); ?>"
                                                       required>
                                            </td>
                                            <td>
                                                <textarea name="medicine_description[]" 
                                                          class="medicine-description" 
                                                          placeholder="Enter dosage, timing, duration, and instructions.&#10;&#10;Example:&#10;• Take 1 tablet twice daily (morning & evening)&#10;• Take after meals&#10;• Duration: 5 days&#10;• For fever and pain relief"><?php echo htmlspecialchars($medicine['description']); ?></textarea>
                                            </td>
                                            <td style="text-align: center;">
                                                <button type="button" class="remove-btn" onclick="removeMedicineRow(this)">Remove</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td>
                                            <input type="text" 
                                                   name="medicine_name[]" 
                                                   class="medicine-input" 
                                                   placeholder="e.g., Paracetamol 500mg"
                                                   required>
                                        </td>
                                        <td>
                                            <textarea name="medicine_description[]" 
                                                      class="medicine-description" 
                                                      placeholder="Enter dosage, timing, duration, and instructions.&#10;&#10;Example:&#10;• Take 1 tablet twice daily (morning & evening)&#10;• Take after meals&#10;• Duration: 5 days&#10;• For fever and pain relief"></textarea>
                                        </td>
                                        <td style="text-align: center;">
                                            <button type="button" class="remove-btn" onclick="removeMedicineRow(this)">Remove</button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <div class="form-group">
                            <label for="diagnosis">Diagnosis (Optional)</label>
                            <textarea id="diagnosis" 
                                      name="diagnosis" 
                                      placeholder="Enter patient's diagnosis or medical condition..."><?php echo htmlspecialchars($existing_prescription['diagnosis'] ?? ''); ?></textarea>
                            <p class="helper-text">Medical condition or reason for prescription</p>
                        </div>

                        <div class="form-group">
                            <label for="additional_notes">Additional Notes (Optional)</label>
                            <textarea id="additional_notes" 
                                      name="additional_notes" 
                                      placeholder="Any additional instructions or notes for the patient..."><?php echo htmlspecialchars($existing_prescription['additional_notes'] ?? ''); ?></textarea>
                            <p class="helper-text">Special instructions, precautions, or follow-up recommendations</p>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" class="submit-btn">
                                💾 Save Prescription
                            </button>
                            <a href="doctor_dashboard.php" class="cancel-btn">
                                ← Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            <?php elseif (!$error_message): ?>
                <div class="error-alert">
                    <span style="font-size: 20px;">⚠️</span>
                    <span>No appointment selected. Please select an appointment from your dashboard.</span>
                </div>
                <a href="doctor_dashboard.php" class="cancel-btn" style="display: inline-block; margin-top: 20px;">
                    ← Back to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }

            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });

            overlay.addEventListener('click', toggleSidebar);

            sidebar.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        // Add medicine row
        function addMedicineRow() {
            const tbody = document.getElementById('medicinesBody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <input type="text" 
                           name="medicine_name[]" 
                           class="medicine-input" 
                           placeholder="e.g., Paracetamol 500mg"
                           required>
                </td>
                <td>
                    <textarea name="medicine_description[]" 
                              class="medicine-description" 
                              placeholder="Enter dosage, timing, duration, and instructions.&#10;&#10;Example:&#10;• Take 1 tablet twice daily (morning & evening)&#10;• Take after meals&#10;• Duration: 5 days&#10;• For fever and pain relief"></textarea>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="remove-btn" onclick="removeMedicineRow(this)">Remove</button>
                </td>
            `;
            tbody.appendChild(newRow);
        }

        // Remove medicine row
        function removeMedicineRow(button) {
            const tbody = document.getElementById('medicinesBody');
            const rows = tbody.getElementsByTagName('tr');
            
            if (rows.length > 1) {
                button.closest('tr').remove();
            } else {
                alert('At least one medicine is required!');
            }
        }

        // Form validation
        document.getElementById('prescriptionForm')?.addEventListener('submit', function(e) {
            const medicineNames = document.querySelectorAll('input[name="medicine_name[]"]');
            let hasValidMedicine = false;

            medicineNames.forEach(input => {
                if (input.value.trim() !== '') {
                    hasValidMedicine = true;
                }
            });

            if (!hasValidMedicine) {
                e.preventDefault();
                alert('Please add at least one medicine!');
            }
        });
    </script>
</body>
</html>

<?php
$doctors_conn->close();
$patients_conn->close();
?>