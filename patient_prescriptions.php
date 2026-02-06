<?php
session_start();

// Check if patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];
$patient_name = $_SESSION['user_name'];

// Database connection
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");

if ($doctors_conn->connect_error) {
    die("Connection failed");
}

// Get all prescriptions for this patient
$stmt = $doctors_conn->prepare("
    SELECT 
        p.*,
        da.appointment_date,
        da.appointment_time,
        da.consultation_type,
        d.first_name as doctor_first_name,
        d.last_name as doctor_last_name,
        d.specialty as doctor_specialty
    FROM prescriptions p
    LEFT JOIN doctor_appointments da ON p.appointment_id = da.id
    LEFT JOIN doctors d ON p.doctor_id = d.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$prescriptions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions - Human Care</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .prescriptions-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .page-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
        }

        .empty-state {
            background: white;
            padding: 60px 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }

        .prescription-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .prescription-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .prescription-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .prescription-header h3 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prescription-date {
            font-size: 13px;
            opacity: 0.9;
        }

        .prescription-body {
            padding: 25px;
        }

        .doctor-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        .medicines-section {
            margin-top: 25px;
        }

        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e7ff;
        }

        .medicines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .medicines-table thead {
            background: #f8f9fa;
        }

        .medicines-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }

        .medicines-table td {
            padding: 15px 12px;
            vertical-align: top;
            border-bottom: 1px solid #f0f0f0;
        }

        .medicine-name {
            font-weight: 600;
            color: #667eea;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .medicine-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .diagnosis-section,
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
        }

        .notes-section {
            background: #e0e7ff;
            border-left-color: #667eea;
        }

        .diagnosis-section h4,
        .notes-section h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .diagnosis-section p,
        .notes-section p {
            margin: 0;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print {
            background: #10b981;
            color: white;
        }

        .btn-print:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-download {
            background: #3b82f6;
            color: white;
        }

        .btn-download:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-new {
            background: #dcfce7;
            color: #166534;
        }

        @media print {
            .sidebar,
            .menu-toggle,
            .action-buttons,
            .page-header {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
            }

            .prescription-card {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        @media (max-width: 768px) {
            .medicines-table {
                display: block;
                overflow-x: auto;
            }

            .doctor-info {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .prescription-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
            <div class="logo-icon">❤️</div>
            HUMAN CARE
        </div>

        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($patient_name); ?></h3>
                <p>Patient</p>
            </div>
        </div>

        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link" href="patient_appointments.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="patient_appointments.php">
                        <span class="nav-icon">📅</span>
                        <span>My Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="patient_prescriptions.php">
                        <span class="nav-icon">💊</span>
                        <span>My Prescriptions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="book_appointment.php">
                        <span class="nav-icon">➕</span>
                        <span>Book Appointment</span>
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
        <div class="prescriptions-container">
            <div class="page-header">
                <div class="page-icon">💊</div>
                <div>
                    <h1 style="margin: 0; color: #333;">My Prescriptions</h1>
                    <p style="margin: 5px 0 0 0; color: #666;">View all your medical prescriptions</p>
                </div>
            </div>

            <?php if (empty($prescriptions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">💊</div>
                    <h3>No Prescriptions Yet</h3>
                    <p>You don't have any prescriptions. Your doctor will provide prescriptions after completing your appointments.</p>
                    <a href="book_appointment.php" class="btn btn-print" style="margin: 20px auto 0;">
                        📅 Book an Appointment
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($prescriptions as $prescription): ?>
                    <?php
                    $medicines = json_decode($prescription['medicines'], true) ?? [];
                    $isNew = (time() - strtotime($prescription['created_at'])) < (7 * 24 * 60 * 60); // New if within 7 days
                    ?>
                    <div class="prescription-card">
                        <div class="prescription-header">
                            <h3>
                                <span>👨‍⚕️</span>
                                Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                                <?php if ($isNew): ?>
                                    <span class="badge badge-new">NEW</span>
                                <?php endif; ?>
                            </h3>
                            <div class="prescription-date">
                                📅 <?php echo date('F d, Y', strtotime($prescription['created_at'])); ?>
                            </div>
                        </div>

                        <div class="prescription-body">
                            <!-- Doctor & Appointment Info -->
                            <div class="doctor-info">
                                <div class="info-item">
                                    <span class="info-label">Specialty</span>
                                    <span class="info-value"><?php echo htmlspecialchars($prescription['doctor_specialty']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Appointment Date</span>
                                    <span class="info-value"><?php echo date('F d, Y', strtotime($prescription['appointment_date'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Appointment Time</span>
                                    <span class="info-value"><?php echo date('h:i A', strtotime($prescription['appointment_time'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Consultation Type</span>
                                    <span class="info-value"><?php echo htmlspecialchars(ucfirst($prescription['consultation_type'])); ?></span>
                                </div>
                            </div>

                            <!-- Diagnosis -->
                            <?php if (!empty($prescription['diagnosis'])): ?>
                                <div class="diagnosis-section">
                                    <h4>
                                        <span>🩺</span>
                                        <span>Diagnosis</span>
                                    </h4>
                                    <p><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Medicines -->
                            <div class="medicines-section">
                                <div class="section-title">
                                    <span style="font-size: 24px;">💊</span>
                                    <span>Prescribed Medicines</span>
                                </div>

                                <table class="medicines-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 30%;">Medicine Name</th>
                                            <th style="width: 65%;">Description / Instructions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medicines as $index => $medicine): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: #667eea;"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <div class="medicine-name">
                                                        <?php echo htmlspecialchars($medicine['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="medicine-description">
                                                        <?php echo !empty($medicine['description']) 
                                                            ? nl2br(htmlspecialchars($medicine['description'])) 
                                                            : '<em style="color: #999;">No description provided</em>'; 
                                                        ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Additional Notes -->
                            <?php if (!empty($prescription['additional_notes'])): ?>
                                <div class="notes-section">
                                    <h4>
                                        <span>📝</span>
                                        <span>Additional Notes</span>
                                    </h4>
                                    <p><?php echo nl2br(htmlspecialchars($prescription['additional_notes'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <button class="btn btn-print" onclick="printPrescription(this)">
                                    <span>🖨️</span>
                                    <span>Print Prescription</span>
                                </button>
                                <button class="btn btn-download" onclick="downloadPrescription(<?php echo $prescription['id']; ?>)">
                                    <span>📥</span>
                                    <span>Download PDF</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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

        // Print single prescription
        function printPrescription(button) {
            const card = button.closest('.prescription-card');
            const originalContent = document.body.innerHTML;
            const prescriptionContent = card.outerHTML;

            // Create print content
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Prescription - Human Care</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .prescription-card { border: 2px solid #667eea; border-radius: 10px; overflow: hidden; }
                        .prescription-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
                        .prescription-body { padding: 20px; }
                        .medicines-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .medicines-table th, .medicines-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        .medicines-table th { background: #f5f5f5; }
                        .medicine-name { font-weight: bold; color: #667eea; }
                        .doctor-info { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px; }
                        .diagnosis-section, .notes-section { padding: 15px; margin: 15px 0; border-left: 4px solid #667eea; background: #f8f9fa; }
                        .action-buttons { display: none; }
                    </style>
                </head>
                <body>
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h1 style="color: #667eea; margin: 0;">HUMAN CARE</h1>
                        <p style="margin: 5px 0;">Medical Prescription</p>
                    </div>
                    ${prescriptionContent}
                </body>
                </html>
            `;

            // Open print window
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }

        // Download PDF (placeholder - would need server-side PDF generation)
        function downloadPrescription(prescriptionId) {
            alert('PDF download feature will be implemented with a PDF library on the server.\n\nFor now, please use the Print option and save as PDF from your browser\'s print dialog.');
            // In production, this would call a PHP script to generate and download PDF
            // window.location.href = 'generate_prescription_pdf.php?id=' + prescriptionId;
        }
    </script>
</body>
</html>

<?php
$doctors_conn->close();
?>