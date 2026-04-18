<?php
/**
 * patient_prescriptions.php
 * Shows ONLY approved prescriptions to the patient.
 * Includes medicine names, prices, dosage, instructions, diagnosis.
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id   = $_SESSION['user_id'];
$patient_name = $_SESSION['user_name'];

$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$admin_conn   = new mysqli("localhost", "root", "", "human_care_admin");
if ($doctors_conn->connect_error || $admin_conn->connect_error) {
    die("Connection failed");
}

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
    LEFT JOIN human_care_admin.appointments a ON rx.appointment_id = a.id
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
<style>
.rx-wrap { max-width:1050px; margin:0 auto; padding:20px; }
.rx-page-hdr { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.rx-page-icon { width:56px; height:56px; background:linear-gradient(135deg,#667eea,#764ba2);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:26px; color:#fff; }

.rx-card { background:#fff; border-radius:16px;
    box-shadow:0 2px 14px rgba(0,0,0,.09); margin-bottom:28px; overflow:hidden;
    transition:.3s; }
.rx-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.13); transform:translateY(-2px); }

.rx-header { background:linear-gradient(135deg,#667eea,#764ba2);
    color:#fff; padding:20px 26px;
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
.rx-header h3 { margin:0; font-size:18px; display:flex; align-items:center; gap:10px; }
.rx-date { font-size:13px; opacity:.9; }

.rx-body { padding:24px 26px; }

/* Doctor info strip */
.doctor-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
    gap:12px; background:#f8fafc; padding:14px 18px; border-radius:10px; margin-bottom:20px; }
.ds-item { display:flex; flex-direction:column; }
.ds-label { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
.ds-value { font-size:13px; font-weight:600; color:#1e293b; }

/* Diagnosis */
.diag-box { padding:14px 18px; background:#fffbeb; border-left:4px solid #f59e0b;
    border-radius:8px; margin-bottom:20px; }
.diag-box h4 { margin:0 0 6px; font-size:13px; color:#92400e;
    display:flex; align-items:center; gap:6px; }
.diag-box p { margin:0; font-size:14px; color:#1e293b; line-height:1.6; }

/* Medicines table */
.med-title { font-size:16px; font-weight:700; color:#1e293b;
    margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.med-table { width:100%; border-collapse:collapse; margin-bottom:12px; }
.med-table thead { background:linear-gradient(135deg,#667eea,#764ba2); }
.med-table th { padding:11px 14px; text-align:left; color:#fff;
    font-size:12px; font-weight:700; letter-spacing:.3px; }
.med-table td { padding:12px 14px; border-bottom:1px solid #f1f5f9;
    font-size:13px; vertical-align:top; }
.med-table tbody tr:hover { background:#fafbff; }
.med-name-cell { font-weight:700; color:#1e293b; margin-bottom:3px; }
.med-sub { font-size:11px; color:#64748b; }
.price-cell { font-weight:700; color:#667eea; white-space:nowrap; }

/* Total row */
.total-row td { background:#f0f9ff; font-weight:700; font-size:14px; }

/* Notes */
.notes-box { padding:14px 18px; background:#e0e7ff; border-left:4px solid #667eea;
    border-radius:8px; margin-top:16px; }
.notes-box h4 { margin:0 0 6px; font-size:13px; color:#3730a3;
    display:flex; align-items:center; gap:6px; }
.notes-box p { margin:0; font-size:14px; color:#1e293b; line-height:1.6; }

/* Print btn */
.print-btn { padding:10px 22px; background:linear-gradient(135deg,#667eea,#764ba2);
    color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700;
    cursor:pointer; transition:.3s; display:inline-flex; align-items:center; gap:8px; }
.print-btn:hover { transform:translateY(-2px); box-shadow:0 4px 14px rgba(102,126,234,.35); }

/* Empty */
.empty-state { background:#fff; border-radius:14px; padding:70px 30px;
    text-align:center; box-shadow:0 2px 12px rgba(0,0,0,.06); }
.empty-icon { font-size:72px; opacity:.25; margin-bottom:16px; }
.empty-state h3 { color:#1e293b; margin-bottom:8px; }
.empty-state p { color:#64748b; }

/* Badge */
.badge-new { background:#d1fae5; color:#065f46; padding:3px 10px;
    border-radius:10px; font-size:11px; font-weight:700; }

@media print {
    .sidebar,.menu-toggle,.print-btn,.rx-page-hdr { display:none!important; }
    .main-content { margin:0!important; padding:0!important; }
    .rx-card { box-shadow:none; border:1px solid #ddd; break-inside:avoid; }
}
</style>
</head>
<body>
<button class="menu-toggle" id="menuToggle">☰</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="logo"><div class="logo-icon">❤️</div>HUMAN CARE</div>
    <div class="user-profile">
        <div class="user-avatar">👤</div>
        <div class="user-info">
            <h3><?= htmlspecialchars($patient_name) ?></h3>
            <p>Patient</p>
        </div>
    </div>
    <nav><ul class="nav-menu">
        <li><a class="nav-link" href="patient_appointments.php">
            <span class="nav-icon">🏠</span><span>Dashboard</span></a></li>
        <li><a class="nav-link" href="patient_appointments.php">
            <span class="nav-icon">📅</span><span>My Appointments</span></a></li>
        <li><a class="nav-link active" href="patient_prescriptions.php">
            <span class="nav-icon">💊</span><span>My Prescriptions</span></a></li>
        <li><a class="nav-link" href="book_appointment.php">
            <span class="nav-icon">➕</span><span>Book Appointment</span></a></li>
    </ul></nav>
    <form method="post" action="logout.php">
        <button class="logout-btn" type="submit">🚪 Logout</button>
    </form>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

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
            <p>Your prescriptions will appear here once your doctor completes an appointment and submits a prescription.</p>
            <a href="book_appointment.php"
               style="display:inline-block;margin-top:20px;padding:12px 28px;
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
                    👨‍⚕️ Dr. <?= htmlspecialchars($rx['doctor_first'].' '.$rx['doctor_last']) ?>
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
                        <span class="ds-value"><?= htmlspecialchars($rx['doctor_specialty']??'') ?></span>
                    </div>
                    <div class="ds-item">
                        <span class="ds-label">Qualification</span>
                        <span class="ds-value"><?= htmlspecialchars($rx['doctor_qual']??'') ?></span>
                    </div>
                    <div class="ds-item">
                        <span class="ds-label">Appointment Date</span>
                        <span class="ds-value"><?= $rx['appointment_date'] ? date('M d, Y', strtotime($rx['appointment_date'])) : '—' ?></span>
                    </div>
                    <div class="ds-item">
                        <span class="ds-label">Consultation</span>
                        <span class="ds-value"><?= htmlspecialchars(ucfirst($rx['consultation_type']??'')) ?></span>
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
                            <td style="color:#667eea;font-weight:700;"><?= $idx+1 ?></td>
                            <td>
                                <div class="med-name-cell"><?= htmlspecialchars($item['medicine_name']) ?></div>
                            </td>
                            <td>
                                <div class="med-sub">
                                    <?= htmlspecialchars($item['dosage_form']??'') ?>
                                    <?= $item['strength'] ? '· '.htmlspecialchars($item['strength']) : '' ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($item['dosage'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($item['duration'] ?: '—') ?></td>
                            <td><?= !empty($item['instructions']) ? nl2br(htmlspecialchars($item['instructions'])) : '<em style="color:#94a3b8;">—</em>' ?></td>
                            <td class="price-cell">₹<?= number_format($item['price_at_time'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="6" style="color:#475569;padding:12px 14px;">
                                💰 Estimated Total (all medicines)
                            </td>
                            <td style="color:#667eea;padding:12px 14px;">
                                ₹<?= number_format($rx['total'],2) ?>
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

<script>
function printThis(btn) {
    const card = btn.closest('.rx-card');
    const w = window.open('', '', 'width=850,height=700');
    w.document.write(`<!DOCTYPE html><html><head><title>Prescription – Human Care</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; color: #1e293b; }
        h2 { color: #667eea; margin-bottom: 4px; }
        .rx-header { background: linear-gradient(135deg,#667eea,#764ba2); color:#fff;
            padding:18px 22px; border-radius:10px 10px 0 0; margin-bottom:0; }
        .rx-header h3 { margin:0; font-size:18px; }
        .rx-body { padding:20px; border:1px solid #e2e8f0; border-radius:0 0 10px 10px; }
        table { width:100%; border-collapse:collapse; margin:16px 0; }
        th { background:#667eea; color:#fff; padding:10px 12px; text-align:left; font-size:12px; }
        td { padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .total-row td { font-weight:700; background:#f0f9ff; }
        .diag-box { padding:12px 16px; background:#fffbeb; border-left:4px solid #f59e0b;
            border-radius:6px; margin:14px 0; }
        .notes-box { padding:12px 16px; background:#e0e7ff; border-left:4px solid #667eea;
            border-radius:6px; margin:14px 0; }
        .print-btn { display:none; }
        .doctor-strip { background:#f8fafc; padding:12px 16px; border-radius:8px; margin-bottom:14px;
            display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        .ds-label { font-size:10px; color:#94a3b8; text-transform:uppercase; }
        .ds-value { font-size:13px; font-weight:600; }
        .brand { text-align:center; margin-bottom:20px; border-bottom:2px solid #667eea; padding-bottom:14px; }
    </style></head><body>
    <div class="brand">
        <h2>❤️ HUMAN CARE</h2>
        <p style="margin:0;color:#64748b;">Medical Prescription</p>
    </div>
    ${card.outerHTML}
    </body></html>`);
    w.document.close();
    setTimeout(() => { w.print(); w.close(); }, 350);
}

document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('menuToggle');
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('sidebarOverlay');
    btn.addEventListener('click', e => { e.stopPropagation(); sb.classList.toggle('active'); ov.classList.toggle('active'); });
    ov.addEventListener('click', () => { sb.classList.remove('active'); ov.classList.remove('active'); });
});
</script>
</body>
</html>
<?php $doctors_conn->close(); $admin_conn->close(); ?>