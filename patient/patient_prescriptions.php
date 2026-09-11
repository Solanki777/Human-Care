<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['user_id'];
$patient_name = $_SESSION['user_name'];

$doctors_conn = Database::getConnection('doctors');


// Only fetch APPROVED prescriptions
$stmt = $doctors_conn->prepare("
    SELECT
        rx.*,
        a.appointment_date,
        a.appointment_time,
        a.consultation_type,
        a.reason_for_visit,
        d.first_name  AS doctor_first,
        d.last_name   AS doctor_last,
        d.specialty   AS doctor_specialty,
        d.qualification AS doctor_qual
    FROM prescriptions rx
    LEFT JOIN if0_42370337_human_care_admin.appointments a ON rx.appointment_id = a.id
    LEFT JOIN doctors d ON rx.doctor_id = d.id
    WHERE rx.patient_id = ? AND rx.status = 'approved'
    ORDER BY rx.approved_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$prescriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load items for each prescription
foreach ($prescriptions as &$rx) {
    $s = $doctors_conn->prepare("
        SELECT pi.*, m.dosage_form, m.strength, m.category
        FROM prescription_items pi
        LEFT JOIN medicines m ON pi.medicine_id = m.id
        WHERE pi.prescription_id = ?
    ");
    $s->bind_param("i", $rx['id']);
    $s->execute();
    $rx['items'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    $rx['total'] = array_sum(array_column($rx['items'], 'price_at_time'));
}
unset($rx);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions – Human Care</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/patient_prescriptions.css">
    <link rel="stylesheet" href="styles/sidebar.css">

</head>

<body>
    <?php $active_page = 'prescriptions'; ?>
    <?php include 'includes/public_sidebar.php'; ?>


    <main class="main-content">
        <div class="rx-wrap">
            <div class="rx-page-hdr">
                <div class="rx-page-icon">💊</div>
                <div>
                    <h1 style="margin:0;color:#1e293b;">My Prescriptions</h1>
                    <p style="margin:4px 0 0;color:#64748b;font-size:14px;">
                        Prescriptions are visible after your doctor completes the appointment
                    </p>
                </div>
            </div>

            <?php if (empty($prescriptions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">💊</div>
                    <h3>No Prescriptions Yet</h3>
                    <p>Your prescriptions will appear here once your doctor completes an appointment and submits a
                        prescription.</p>
                    <a href="book_appointment.php" style="display:inline-block;margin-top:20px;padding:12px 28px;
                      background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;
                      border-radius:8px;text-decoration:none;font-weight:700;">
                        📅 Book an Appointment
                    </a>
                </div>

            <?php else: ?>
                <?php foreach ($prescriptions as $rx):
                    $isNew = (time() - strtotime($rx['approved_at'])) < (7 * 24 * 3600);
                    ?>
                    <div class="rx-card">
                        <!-- Header -->
                        <div class="rx-header">
                            <h3>
                                👨‍⚕️ Dr. <?= htmlspecialchars($rx['doctor_first'] . ' ' . $rx['doctor_last']) ?>
                                <?php if ($isNew): ?>
                                    <span class="badge-new">NEW</span>
                                <?php endif; ?>
                            </h3>
                            <div class="rx-date">
                                📅 Issued <?= date('F d, Y', strtotime($rx['approved_at'])) ?>
                            </div>
                        </div>

                        <div class="rx-body">
                            <!-- Doctor / Appt strip -->
                            <div class="doctor-strip">
                                <div class="ds-item">
                                    <span class="ds-label">Specialty</span>
                                    <span class="ds-value"><?= htmlspecialchars($rx['doctor_specialty'] ?? '') ?></span>
                                </div>
                                <div class="ds-item">
                                    <span class="ds-label">Qualification</span>
                                    <span class="ds-value"><?= htmlspecialchars($rx['doctor_qual'] ?? '') ?></span>
                                </div>
                                <div class="ds-item">
                                    <span class="ds-label">Appointment Date</span>
                                    <span
                                        class="ds-value"><?= $rx['appointment_date'] ? date('M d, Y', strtotime($rx['appointment_date'])) : '—' ?></span>
                                </div>
                                <div class="ds-item">
                                    <span class="ds-label">Consultation</span>
                                    <span class="ds-value"><?= htmlspecialchars(ucfirst($rx['consultation_type'] ?? '')) ?></span>
                                </div>
                            </div>

                            <!-- Diagnosis -->
                            <?php if (!empty($rx['diagnosis'])): ?>
                                <div class="diag-box">
                                    <h4>🩺 Diagnosis</h4>
                                    <p><?= nl2br(htmlspecialchars($rx['diagnosis'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Medicines table -->
                            <div class="med-title">💊 Prescribed Medicines</div>
                            <div style="overflow-x:auto;">
                                <table class="med-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Medicine</th>
                                            <th>Form / Strength</th>
                                            <th>Dosage</th>
                                            <th>Duration</th>
                                            <th>Instructions</th>
                                            <th>Price / Unit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rx['items'] as $idx => $item): ?>
                                            <tr>
                                                <td style="color:#667eea;font-weight:700;"><?= $idx + 1 ?></td>
                                                <td>
                                                    <div class="med-name-cell"><?= htmlspecialchars($item['medicine_name']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="med-sub">
                                                        <?= htmlspecialchars($item['dosage_form'] ?? '') ?>
                                                        <?= $item['strength'] ? '· ' . htmlspecialchars($item['strength']) : '' ?>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($item['dosage'] ?: '—') ?></td>
                                                <td><?= htmlspecialchars($item['duration'] ?: '—') ?></td>
                                                <td><?= !empty($item['instructions']) ? nl2br(htmlspecialchars($item['instructions'])) : '<em style="color:#94a3b8;">—</em>' ?>
                                                </td>
                                                <td class="price-cell">₹<?= number_format($item['price_at_time'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="total-row">
                                            <td colspan="6" style="color:#475569;padding:12px 14px;">
                                                💰 Estimated Total (all medicines)
                                            </td>
                                            <td style="color:#667eea;padding:12px 14px;">
                                                ₹<?= number_format($rx['total'], 2) ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Notes -->
                            <?php if (!empty($rx['additional_notes'])): ?>
                                <div class="notes-box">
                                    <h4>📝 Doctor's Notes</h4>
                                    <p><?= nl2br(htmlspecialchars($rx['additional_notes'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Print -->
                            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9;">
                                <button class="print-btn" onclick="printThis(this)">
                                    🖨️ Print Prescription
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

<script src="js/patient_pres.js"></script>
</body>

</html>
<?php $doctors_conn->close(); ?>