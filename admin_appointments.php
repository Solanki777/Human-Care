<?php
/**
 * admin_appointments.php
 * Admin views appointments + can:
 *   - Approve / Reject pending appointments
 *   - Cancel approved appointments
 *   - Review prescription medicines → Approve, Edit items, or Cancel (reject) the prescription
 *   - Marking prescription approved → marks appointment completed + patient sees it
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$admin_conn   = new mysqli("localhost", "root", "", "human_care_admin");
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
if ($admin_conn->connect_error || $doctors_conn->connect_error) {
    die("Connection failed");
}

$msg      = '';
$msg_type = '';

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appointment_id'])) {
    $aid      = intval($_POST['appointment_id']);
    $action   = $_POST['action'];
    $admin_id = $_SESSION['admin_id'] ?? 0;

    // ── Appointment-level actions ─────────────────────────────────────────────
    if (in_array($action, ['approve', 'reject', 'complete', 'cancel'])) {
        $new_status = match($action) {
            'approve'  => 'approved',
            'reject'   => 'rejected',
            'complete' => 'completed',
            'cancel'   => 'cancelled',
        };

        $stmt = $admin_conn->prepare("
            UPDATE appointments
            SET status = ?, verified_by = ?, verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $new_status, $admin_id, $aid);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            if ($action === 'complete') {
                // Approve the prescription so patient can see it
                $stmt2 = $doctors_conn->prepare("
                    UPDATE prescriptions
                    SET status = 'approved', approved_at = NOW()
                    WHERE appointment_id = ?
                ");
                $stmt2->bind_param("i", $aid);
                $stmt2->execute();
                $stmt2->close();
                $msg = "✅ Appointment marked complete. Prescription is now visible to the patient.";
            } elseif ($action === 'approve') {
                $msg = "✅ Appointment approved.";
            } elseif ($action === 'cancel') {
                $msg = "🚫 Appointment has been cancelled.";
            } else {
                $msg = "❌ Appointment rejected.";
            }
            $msg_type = in_array($action, ['reject', 'cancel']) ? 'error' : 'success';

            // Activity log
            $desc = "Appointment #$aid marked $new_status";
            $ip   = $_SERVER['REMOTE_ADDR'];
            $l    = $admin_conn->prepare("INSERT INTO activity_logs (admin_id,action,description,ip_address) VALUES (?,?,?,?)");
            $la   = "appointment_$action";
            $l->bind_param("isss", $admin_id, $la, $desc, $ip);
            $l->execute();
            $l->close();
        } else {
            $msg      = "Database error or appointment not found.";
            $msg_type = 'error';
        }
        $stmt->close();

    // ── Prescription-level actions ────────────────────────────────────────────
    } elseif (in_array($action, ['rx_approve', 'rx_cancel', 'rx_edit'])) {
        $rx_id = intval($_POST['rx_id'] ?? 0);

        if ($action === 'rx_approve') {
            // Approve prescription + mark appointment completed
            $s = $doctors_conn->prepare("
                UPDATE prescriptions SET status='approved', approved_at=NOW() WHERE id=?
            ");
            $s->bind_param("i", $rx_id);
            $s->execute();
            $s->close();

            $s2 = $admin_conn->prepare("
                UPDATE appointments SET status='completed', verified_by=?, verified_at=NOW() WHERE id=?
            ");
            $s2->bind_param("ii", $admin_id, $aid);
            $s2->execute();
            $s2->close();

            $msg      = "✅ Prescription approved and released to patient. Appointment marked complete.";
            $msg_type = 'success';

        } elseif ($action === 'rx_cancel') {
            // Reject/cancel the prescription → reset back to pending so doctor can rewrite
            $s = $doctors_conn->prepare("
                UPDATE prescriptions SET status='cancelled', updated_at=NOW() WHERE id=?
            ");
            $s->bind_param("i", $rx_id);
            $s->execute();
            $s->close();

            $msg      = "🚫 Prescription cancelled. Doctor will need to resubmit.";
            $msg_type = 'error';

        } elseif ($action === 'rx_edit') {
            // Admin edits prescription items
            $diagnosis   = trim($_POST['edit_diagnosis']        ?? '');
            $notes       = trim($_POST['edit_notes']            ?? '');
            $med_ids     = $_POST['edit_med_id']                ?? [];
            $med_names   = $_POST['edit_med_name']              ?? [];
            $med_prices  = $_POST['edit_med_price']             ?? [];
            $dosages     = $_POST['edit_dosage']                ?? [];
            $durations   = $_POST['edit_duration']              ?? [];
            $instructions= $_POST['edit_instructions']          ?? [];

            $doctors_conn->begin_transaction();
            try {
                // Update header
                $s = $doctors_conn->prepare("
                    UPDATE prescriptions SET diagnosis=?, additional_notes=?, updated_at=NOW() WHERE id=?
                ");
                $s->bind_param("ssi", $diagnosis, $notes, $rx_id);
                $s->execute();
                $s->close();

                // Replace items
                $doctors_conn->query("DELETE FROM prescription_items WHERE prescription_id = $rx_id");
                $si = $doctors_conn->prepare("
                    INSERT INTO prescription_items
                    (prescription_id, medicine_id, medicine_name, price_at_time, dosage, duration, instructions)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($med_ids as $i => $mid) {
                    $mid   = intval($mid);
                    $mname = trim($med_names[$i]  ?? '');
                    $price = floatval($med_prices[$i] ?? 0);
                    $dos   = trim($dosages[$i]     ?? '');
                    $dur   = trim($durations[$i]   ?? '');
                    $ins   = trim($instructions[$i]?? '');
                    if ($mid > 0 && $mname !== '') {
                        $si->bind_param("iisdsss", $rx_id, $mid, $mname, $price, $dos, $dur, $ins);
                        $si->execute();
                    }
                }
                $si->close();

                $doctors_conn->commit();
                $msg      = "✏️ Prescription updated successfully. You can now approve it.";
                $msg_type = 'success';
            } catch (Exception $e) {
                $doctors_conn->rollback();
                $msg      = "Database error while editing prescription.";
                $msg_type = 'error';
            }
        }
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$filter  = $_GET['filter'] ?? 'all';
$allowed = ['all','pending','approved','completed','rejected','cancelled'];
if (!in_array($filter, $allowed)) $filter = 'all';
$where = $filter !== 'all' ? "WHERE a.status = '$filter'" : '';

// ── Load appointments ─────────────────────────────────────────────────────────
$appointments = $admin_conn->query("
    SELECT a.*,
           p.id              AS rx_id,
           p.status          AS rx_status,
           p.diagnosis       AS rx_diagnosis,
           p.additional_notes AS rx_notes
    FROM appointments a
    LEFT JOIN human_care_doctors.prescriptions p ON p.appointment_id = a.id
    $where
    ORDER BY
        CASE a.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
        a.appointment_date ASC
");

// ── Load prescription items for each appointment that has a pending rx ────────
$rx_items = [];
if ($appointments) {
    $rows = $appointments->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        if ($row['rx_id'] && $row['rx_status'] === 'pending') {
            $si = $doctors_conn->prepare("
                SELECT pi.*, m.dosage_form, m.strength, m.category
                FROM prescription_items pi
                LEFT JOIN medicines m ON pi.medicine_id = m.id
                WHERE pi.prescription_id = ?
            ");
            $si->bind_param("i", $row['rx_id']);
            $si->execute();
            $rx_items[$row['rx_id']] = $si->get_result()->fetch_all(MYSQLI_ASSOC);
            $si->close();
        }
    }
    unset($row);
    $appointments_data = $rows;
} else {
    $appointments_data = [];
}

// ── Counts ────────────────────────────────────────────────────────────────────
$counts = [];
foreach (['all','pending','approved','completed','rejected','cancelled'] as $s) {
    $w          = $s !== 'all' ? "WHERE status='$s'" : '';
    $counts[$s] = $admin_conn->query("SELECT COUNT(*) c FROM appointments $w")->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Appointments – Admin</title>
<link rel="stylesheet" href="styles/dashboard.css">
<style>
/* ── Filter bar ── */
.filter-bar { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
.f-btn { padding:8px 18px; border:2px solid #e2e8f0; border-radius:20px;
    background:#fff; color:#64748b; font-size:13px; font-weight:600;
    cursor:pointer; text-decoration:none; transition:.2s; }
.f-btn:hover,.f-btn.active { border-color:#1e3c72; color:#1e3c72; background:#e8eef8; }
.f-count { background:#e2e8f0; border-radius:10px; padding:1px 7px; font-size:11px; margin-left:5px; }

/* ── Table ── */
.appt-table { width:100%; border-collapse:collapse; background:#fff;
    border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.07); }
.appt-table th { background:#f1f5f9; padding:13px 14px; text-align:left;
    font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.4px; }
.appt-table td { padding:13px 14px; border-bottom:1px solid #f1f5f9;
    vertical-align:middle; font-size:13px; }
.appt-table tbody tr:hover { background:#fafbff; }

/* ── Badges ── */
.badge { display:inline-block; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-pending   { background:#fef3c7; color:#92400e; }
.badge-approved  { background:#dbeafe; color:#1e40af; }
.badge-completed { background:#d1fae5; color:#065f46; }
.badge-rejected  { background:#fee2e2; color:#991b1b; }
.badge-cancelled { background:#f3f4f6; color:#6b7280; }

.rx-pill { display:inline-block; padding:3px 9px; border-radius:10px; font-size:10px; font-weight:700; }
.rx-pending   { background:#fef3c7; color:#92400e; }
.rx-approved  { background:#d1fae5; color:#065f46; }
.rx-cancelled { background:#f3f4f6; color:#6b7280; }
.rx-none      { background:#f1f5f9; color:#94a3b8; }

/* ── Action buttons ── */
.action-form { display:inline; }
.act-btn { padding:6px 13px; border:none; border-radius:7px;
    font-size:12px; font-weight:700; cursor:pointer; margin:2px; transition:.2s; }
.act-approve  { background:#3b82f6; color:#fff; }
.act-approve:hover  { background:#2563eb; }
.act-complete { background:#10b981; color:#fff; }
.act-complete:hover { background:#059669; }
.act-reject   { background:#ef4444; color:#fff; }
.act-reject:hover   { background:#dc2626; }
.act-cancel   { background:#f97316; color:#fff; }
.act-cancel:hover   { background:#ea580c; }
.act-review   { background:#8b5cf6; color:#fff; }
.act-review:hover   { background:#7c3aed; }

/* ── Alert ── */
.alert { padding:14px 18px; border-radius:10px; margin-bottom:20px;
    font-weight:500; border-left:4px solid; }
.alert-success { background:#d1fae5; color:#065f46; border-color:#10b981; }
.alert-error   { background:#fee2e2; color:#991b1b; border-color:#ef4444; }

/* ── Stats ── */
.stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
    gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; padding:20px; border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,.06); text-align:center; }
.stat-num { font-size:28px; font-weight:800; }
.stat-lbl { font-size:12px; color:#64748b; margin-top:4px; }

/* ─────────────────────────────────────────────────────────
   PRESCRIPTION REVIEW MODAL
   ───────────────────────────────────────────────────────── */
.modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
    z-index:9999; align-items:flex-start; justify-content:center;
    padding:30px 16px; overflow-y:auto;
}
.modal-overlay.open { display:flex; }
.modal-box {
    background:#fff; border-radius:18px; width:100%; max-width:820px;
    box-shadow:0 8px 40px rgba(0,0,0,.22); animation:slideIn .25s ease;
}
@keyframes slideIn { from{transform:translateY(-30px);opacity:0} to{transform:translateY(0);opacity:1} }

.modal-header {
    background:linear-gradient(135deg,#8b5cf6,#6d28d9);
    color:#fff; padding:20px 26px; border-radius:18px 18px 0 0;
    display:flex; justify-content:space-between; align-items:center;
}
.modal-header h3 { margin:0; font-size:18px; }
.modal-close { background:rgba(255,255,255,.2); border:none; color:#fff;
    width:34px; height:34px; border-radius:50%; font-size:18px; cursor:pointer;
    display:flex; align-items:center; justify-content:center; transition:.2s; }
.modal-close:hover { background:rgba(255,255,255,.35); }

.modal-body { padding:24px 26px; }

.rx-info-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
    gap:12px; background:#f8fafc; border-radius:10px; padding:14px 18px; margin-bottom:20px; }
.ri-item { display:flex; flex-direction:column; }
.ri-label { font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
.ri-value { font-size:13px; font-weight:600; color:#1e293b; }

.rx-diag { padding:12px 16px; background:#fffbeb; border-left:4px solid #f59e0b;
    border-radius:8px; margin-bottom:18px; }
.rx-diag h4 { margin:0 0 5px; font-size:13px; color:#92400e; }
.rx-diag p  { margin:0; font-size:13px; color:#1e293b; }

/* View mode table */
.rx-view-table { width:100%; border-collapse:collapse; margin-bottom:16px; }
.rx-view-table thead { background:linear-gradient(135deg,#8b5cf6,#6d28d9); }
.rx-view-table th { padding:10px 12px; text-align:left; color:#fff; font-size:12px; font-weight:700; }
.rx-view-table td { padding:11px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; }
.rx-view-table tbody tr:hover { background:#fafbff; }
.rx-view-table tfoot td { background:#f0f9ff; font-weight:700; font-size:13px; }

/* Edit mode table */
.rx-edit-table { width:100%; border-collapse:collapse; margin-bottom:16px; }
.rx-edit-table thead { background:#1e3c72; }
.rx-edit-table th { padding:10px 12px; text-align:left; color:#fff; font-size:12px; font-weight:700; }
.rx-edit-table td { padding:8px 10px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.edit-inp { width:100%; padding:6px 9px; border:1px solid #d1d5db; border-radius:6px;
    font-size:12px; box-sizing:border-box; }
.edit-inp:focus { outline:none; border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.15); }
.del-row-btn { background:#fee2e2; color:#991b1b; border:none; border-radius:6px;
    padding:5px 9px; cursor:pointer; font-size:13px; transition:.2s; }
.del-row-btn:hover { background:#fca5a5; }

.edit-field-label { font-size:12px; color:#64748b; font-weight:600; margin-bottom:5px; }
.edit-textarea { width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:8px;
    font-size:13px; resize:vertical; box-sizing:border-box; font-family:inherit; }
.edit-textarea:focus { outline:none; border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.15); }

/* Modal action buttons */
.modal-footer { display:flex; flex-wrap:wrap; gap:10px; padding:16px 26px 24px;
    border-top:1px solid #f1f5f9; justify-content:flex-end; }
.mf-btn { padding:10px 22px; border:none; border-radius:9px;
    font-size:13px; font-weight:700; cursor:pointer; transition:.2s; }
.mf-approve { background:#10b981; color:#fff; }
.mf-approve:hover { background:#059669; }
.mf-edit   { background:#f59e0b; color:#fff; }
.mf-edit:hover   { background:#d97706; }
.mf-cancel { background:#ef4444; color:#fff; }
.mf-cancel:hover { background:#dc2626; }
.mf-close  { background:#f1f5f9; color:#475569; }
.mf-close:hover  { background:#e2e8f0; }
.mf-save   { background:#8b5cf6; color:#fff; }
.mf-save:hover   { background:#7c3aed; }

/* Tab switcher inside modal */
.mode-tabs { display:flex; gap:0; margin-bottom:20px; border:2px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.mode-tab { flex:1; padding:10px; text-align:center; font-size:13px; font-weight:700;
    cursor:pointer; background:#fff; color:#64748b; border:none; transition:.2s; }
.mode-tab.active { background:linear-gradient(135deg,#8b5cf6,#6d28d9); color:#fff; }

.view-section { display:block; }
.edit-section { display:none; }
</style>
</head>
<body>
<button class="menu-toggle" onclick="toggleSidebar()">☰</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="logo"><div class="logo-icon">🛡️</div>ADMIN PANEL</div>
    <div class="user-profile">
        <div class="user-avatar">👨‍💼</div>
        <div class="user-info">
            <h3><?= htmlspecialchars($_SESSION['admin_name']) ?></h3>
            <span style="font-size:11px;opacity:.8;">ADMINISTRATOR</span>
        </div>
    </div>
    <nav><ul class="nav-menu">
        <li><a class="nav-link" href="admin_dashboard.php"><span class="nav-icon">🏠</span>Dashboard</a></li>
        <li><a class="nav-link" href="admin_doctors.php"><span class="nav-icon">👨‍⚕️</span>Manage Doctors</a></li>
        <li><a class="nav-link" href="admin_patients.php"><span class="nav-icon">👥</span>Manage Patients</a></li>
        <li><a class="nav-link active" href="admin_appointments.php"><span class="nav-icon">📅</span>Appointments</a></li>
        <li><a class="nav-link" href="admin_manage_education.php"><span class="nav-icon">📚</span>Approve Education</a></li>
    </ul></nav>
    <form method="post" action="admin_logout.php">
        <button class="logout-btn" type="submit">🚪 Logout</button>
    </form>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<main class="main-content">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="hero-banner" style="background:linear-gradient(135deg,#1e3c72,#2a5298);margin-bottom:24px;">
        <h2>📅 Manage Appointments</h2>
        <p>Approve · Cancel · Review &amp; approve prescriptions before releasing to patients</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num" style="color:#f59e0b;"><?= $counts['pending'] ?></div>
            <div class="stat-lbl">⏳ Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#3b82f6;"><?= $counts['approved'] ?></div>
            <div class="stat-lbl">✅ Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#10b981;"><?= $counts['completed'] ?></div>
            <div class="stat-lbl">🏁 Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#ef4444;"><?= $counts['rejected'] ?></div>
            <div class="stat-lbl">❌ Rejected</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#6b7280;"><?= $counts['cancelled'] ?></div>
            <div class="stat-lbl">🚫 Cancelled</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:#1e3c72;"><?= $counts['all'] ?></div>
            <div class="stat-lbl">📊 Total</div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <?php foreach (['all','pending','approved','completed','rejected','cancelled'] as $s): ?>
            <a href="?filter=<?= $s ?>" class="f-btn <?= $filter===$s?'active':'' ?>">
                <?= ucfirst($s) ?>
                <span class="f-count"><?= $counts[$s] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
    <table class="appt-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date &amp; Time</th>
                <th>Reason</th>
                <th>Appt Status</th>
                <th>Prescription</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        foreach ($appointments_data as $a):
        ?>
            <tr>
                <td style="color:#94a3b8;font-weight:700;"><?= $i++ ?></td>
                <td>
                    <div style="font-weight:700;color:#1e293b;"><?= htmlspecialchars($a['patient_name']) ?></div>
                    <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($a['patient_email']) ?></div>
                </td>
                <td>
                    <div style="font-weight:600;color:#1e293b;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
                    <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($a['doctor_specialty']??'') ?></div>
                </td>
                <td>
                    <div style="font-weight:600;"><?= date('M d, Y', strtotime($a['appointment_date'])) ?></div>
                    <div style="font-size:12px;color:#64748b;"><?= date('h:i A', strtotime($a['appointment_time'])) ?></div>
                </td>
                <td style="max-width:180px;color:#475569;">
                    <?= htmlspecialchars(substr($a['reason_for_visit']??'',0,70)) ?>
                </td>
                <td>
                    <span class="badge badge-<?= $a['status'] ?>">
                        <?= strtoupper($a['status']) ?>
                    </span>
                </td>

                <!-- Prescription pill -->
                <td>
                    <?php if ($a['rx_id']): ?>
                        <span class="rx-pill rx-<?= $a['rx_status'] ?>">
                            💊 <?= strtoupper($a['rx_status']) ?>
                        </span>
                    <?php else: ?>
                        <span class="rx-pill rx-none">No Rx</span>
                    <?php endif; ?>
                </td>

                <!-- Actions -->
                <td>
                    <?php if ($a['status'] === 'pending'): ?>
                        <!-- Approve -->
                        <form class="action-form" method="POST"
                              onsubmit="return confirm('Approve this appointment?')">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <button name="action" value="approve" class="act-btn act-approve">✓ Approve</button>
                        </form>
                        <!-- Reject -->
                        <form class="action-form" method="POST"
                              onsubmit="return confirm('Reject this appointment?')">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <button name="action" value="reject" class="act-btn act-reject">✗ Reject</button>
                        </form>

                    <?php elseif ($a['status'] === 'approved'): ?>

                        <?php if ($a['rx_id'] && $a['rx_status'] === 'pending'): ?>
                            <!-- ★ Review Prescription button → opens modal -->
                            <button class="act-btn act-review"
                                onclick="openRxModal(<?= htmlspecialchars(json_encode([
                                    'aid'       => $a['id'],
                                    'rx_id'     => $a['rx_id'],
                                    'patient'   => $a['patient_name'],
                                    'doctor'    => $a['doctor_name'],
                                    'date'      => date('M d, Y', strtotime($a['appointment_date'])),
                                    'diagnosis' => $a['rx_diagnosis'] ?? '',
                                    'notes'     => $a['rx_notes'] ?? '',
                                    'items'     => $rx_items[$a['rx_id']] ?? [],
                                ]), ENT_QUOTES) ?>)">
                                🔍 Review Rx
                            </button>

                        <?php elseif (!$a['rx_id']): ?>
                            <span style="font-size:12px;color:#94a3b8;">
                                Awaiting doctor's Rx…
                            </span>

                        <?php else: ?>
                            <span style="font-size:12px;color:#10b981;">Rx released ✓</span>
                        <?php endif; ?>

                        <!-- ★ Cancel appointment button (always available for approved) -->
                        <form class="action-form" method="POST"
                              onsubmit="return confirm('Cancel this approved appointment?')">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <button name="action" value="cancel" class="act-btn act-cancel"
                                    style="margin-top:4px;">
                                🚫 Cancel
                            </button>
                        </form>

                    <?php elseif ($a['status'] === 'completed'): ?>
                        <span style="font-size:12px;color:#10b981;">✅ Completed</span>

                    <?php else: ?>
                        <span style="font-size:12px;color:#94a3b8;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Flow legend -->
    <div style="margin-top:18px;padding:14px 18px;background:#eff6ff;border-radius:10px;
                border-left:4px solid #3b82f6;font-size:13px;color:#1e40af;">
        <strong>Flow:</strong>
        Patient books → Admin <em>Approves</em> (or <em>Rejects</em>) →
        Doctor writes prescription (status: <em>pending</em>) →
        Admin clicks <strong>"Review Rx"</strong> → views medicines → can <strong>Approve</strong>, <strong>Edit</strong>, or <strong>Cancel</strong> the Rx →
        On Rx Approve: prescription becomes <em>approved</em> + appointment marked <em>completed</em> → Patient can see it.<br>
        Admin can also <strong>Cancel</strong> an approved appointment at any time.
    </div>
</main>


<!-- ══════════════════════════════════════════════════════════════════
     PRESCRIPTION REVIEW MODAL
     ══════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="rxModal">
  <div class="modal-box">

    <!-- Header -->
    <div class="modal-header">
        <h3>💊 Prescription Review</h3>
        <button class="modal-close" onclick="closeRxModal()">✕</button>
    </div>

    <!-- Body -->
    <div class="modal-body">

        <!-- Patient / Doctor / Date strip -->
        <div class="rx-info-strip">
            <div class="ri-item">
                <span class="ri-label">Patient</span>
                <span class="ri-value" id="mi-patient">—</span>
            </div>
            <div class="ri-item">
                <span class="ri-label">Doctor</span>
                <span class="ri-value" id="mi-doctor">—</span>
            </div>
            <div class="ri-item">
                <span class="ri-label">Appt Date</span>
                <span class="ri-value" id="mi-date">—</span>
            </div>
            <div class="ri-item">
                <span class="ri-label">Rx Status</span>
                <span class="ri-value" style="color:#92400e;">⏳ Pending Review</span>
            </div>
        </div>

        <!-- Mode tabs -->
        <div class="mode-tabs">
            <button class="mode-tab active" id="tab-view" onclick="switchTab('view')">👁️ View Prescription</button>
            <button class="mode-tab"        id="tab-edit" onclick="switchTab('edit')">✏️ Edit Prescription</button>
        </div>

        <!-- ── VIEW SECTION ─────────────────────────────── -->
        <div class="view-section" id="viewSection">
            <div class="rx-diag" id="view-diag" style="display:none;">
                <h4>🩺 Diagnosis</h4>
                <p id="view-diag-text"></p>
            </div>
            <div style="overflow-x:auto;">
            <table class="rx-view-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicine</th>
                        <th>Form / Strength</th>
                        <th>Dosage</th>
                        <th>Duration</th>
                        <th>Instructions</th>
                        <th>Price/Unit</th>
                    </tr>
                </thead>
                <tbody id="viewTbody"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="padding:11px 12px;color:#475569;">💰 Estimated Total</td>
                        <td style="padding:11px 12px;color:#8b5cf6;font-weight:700;" id="viewTotal"></td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <div id="view-notes-box" style="display:none;padding:12px 16px;background:#e0e7ff;
                 border-left:4px solid #667eea;border-radius:8px;margin-top:8px;">
                <h4 style="margin:0 0 5px;font-size:13px;color:#3730a3;">📝 Doctor's Notes</h4>
                <p id="view-notes-text" style="margin:0;font-size:13px;color:#1e293b;"></p>
            </div>
        </div>

        <!-- ── EDIT SECTION ─────────────────────────────── -->
        <div class="edit-section" id="editSection">
            <div style="margin-bottom:14px;">
                <div class="edit-field-label">🩺 Diagnosis</div>
                <textarea class="edit-textarea" id="editDiagnosis" rows="2"
                          placeholder="Enter diagnosis…"></textarea>
            </div>
            <div style="overflow-x:auto;margin-bottom:14px;">
            <table class="rx-edit-table" id="editTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Medicine</th>
                        <th>Price (₹)</th>
                        <th>Dosage</th>
                        <th>Duration</th>
                        <th>Instructions</th>
                        <th>Remove</th>
                    </tr>
                </thead>
                <tbody id="editTbody"></tbody>
            </table>
            </div>
            <div>
                <div class="edit-field-label">📝 Doctor's Notes</div>
                <textarea class="edit-textarea" id="editNotes" rows="2"
                          placeholder="Additional notes…"></textarea>
            </div>
        </div>

    </div><!-- /modal-body -->

    <!-- Footer with action buttons -->
    <div class="modal-footer">
        <!-- Hidden form for approve -->
        <form method="POST" id="formApprove" style="display:inline;">
            <input type="hidden" name="action" value="rx_approve">
            <input type="hidden" name="appointment_id" id="fa-aid">
            <input type="hidden" name="rx_id" id="fa-rxid">
            <button type="submit" class="mf-btn mf-approve"
                    onclick="return confirm('Approve this prescription? Patient will see it and appointment will be marked complete.')">
                ✅ Approve Rx &amp; Complete
            </button>
        </form>

        <!-- Button to switch to edit tab -->
        <button class="mf-btn mf-edit" id="btnOpenEdit" onclick="switchTab('edit')">✏️ Edit Rx</button>

        <!-- Save edits (submits edit form) -->
        <button class="mf-btn mf-save" id="btnSaveEdit" style="display:none;"
                onclick="submitEdit()">💾 Save Changes</button>

        <!-- Hidden form for edit submit -->
        <form method="POST" id="formEdit" style="display:none;">
            <input type="hidden" name="action" value="rx_edit">
            <input type="hidden" name="appointment_id" id="fe-aid">
            <input type="hidden" name="rx_id" id="fe-rxid">
            <div id="fe-fields"></div>
        </form>

        <!-- Cancel prescription -->
        <form method="POST" id="formCancel" style="display:inline;">
            <input type="hidden" name="action" value="rx_cancel">
            <input type="hidden" name="appointment_id" id="fc-aid">
            <input type="hidden" name="rx_id" id="fc-rxid">
            <button type="submit" class="mf-btn mf-cancel"
                    onclick="return confirm('Cancel this prescription? The doctor will need to resubmit.')">
                🚫 Cancel Rx
            </button>
        </form>

        <button class="mf-btn mf-close" onclick="closeRxModal()">✕ Close</button>
    </div>
  </div>
</div>

<!-- ── Scripts ─────────────────────────────────────────────────────────────── -->
<script>
// Sidebar
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

// ── Modal state ───────────────────────────────────────────────────────────────
let currentRx = null;

function openRxModal(data) {
    currentRx = data;

    // Fill info strip
    document.getElementById('mi-patient').textContent = data.patient;
    document.getElementById('mi-doctor').textContent  = 'Dr. ' + data.doctor;
    document.getElementById('mi-date').textContent    = data.date;

    // Set form hidden fields
    ['fa','fe','fc'].forEach(p => {
        document.getElementById(p + '-aid').value  = data.aid;
        document.getElementById(p + '-rxid').value = data.rx_id;
    });

    // Build view table
    buildViewTable(data);

    // Build edit table
    buildEditTable(data);

    // Start on view tab
    switchTab('view');

    document.getElementById('rxModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeRxModal() {
    document.getElementById('rxModal').classList.remove('open');
    document.body.style.overflow = '';
}

// Close on overlay click
document.getElementById('rxModal').addEventListener('click', function(e) {
    if (e.target === this) closeRxModal();
});

// ── Tab switcher ──────────────────────────────────────────────────────────────
function switchTab(tab) {
    const isEdit = tab === 'edit';
    document.getElementById('tab-view').classList.toggle('active', !isEdit);
    document.getElementById('tab-edit').classList.toggle('active',  isEdit);
    document.getElementById('viewSection').style.display = isEdit ? 'none' : 'block';
    document.getElementById('editSection').style.display = isEdit ? 'block' : 'none';
    document.getElementById('btnOpenEdit').style.display = isEdit ? 'none' : '';
    document.getElementById('btnSaveEdit').style.display = isEdit ? '' : 'none';
}

// ── Build VIEW table ──────────────────────────────────────────────────────────
function buildViewTable(data) {
    const tbody = document.getElementById('viewTbody');
    tbody.innerHTML = '';
    let total = 0;

    // Diagnosis
    const diagBox  = document.getElementById('view-diag');
    const diagText = document.getElementById('view-diag-text');
    if (data.diagnosis) {
        diagBox.style.display = '';
        diagText.textContent  = data.diagnosis;
    } else {
        diagBox.style.display = 'none';
    }

    (data.items || []).forEach((item, idx) => {
        const price = parseFloat(item.price_at_time) || 0;
        total += price;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="color:#8b5cf6;font-weight:700;">${idx + 1}</td>
            <td>
                <div style="font-weight:700;color:#1e293b;">${esc(item.medicine_name)}</div>
                <div style="font-size:11px;color:#64748b;">${esc(item.category||'')}</div>
            </td>
            <td style="font-size:12px;color:#64748b;">
                ${esc(item.dosage_form||'')}${item.strength ? ' · '+esc(item.strength) : ''}
            </td>
            <td>${esc(item.dosage||'—')}</td>
            <td>${esc(item.duration||'—')}</td>
            <td style="font-size:12px;">${esc(item.instructions||'—')}</td>
            <td style="font-weight:700;color:#8b5cf6;">₹${price.toFixed(2)}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('viewTotal').textContent = '₹' + total.toFixed(2);

    // Notes
    const notesBox  = document.getElementById('view-notes-box');
    const notesText = document.getElementById('view-notes-text');
    if (data.notes) {
        notesBox.style.display = '';
        notesText.textContent  = data.notes;
    } else {
        notesBox.style.display = 'none';
    }
}

// ── Build EDIT table ──────────────────────────────────────────────────────────
function buildEditTable(data) {
    document.getElementById('editDiagnosis').value = data.diagnosis || '';
    document.getElementById('editNotes').value     = data.notes     || '';

    const tbody = document.getElementById('editTbody');
    tbody.innerHTML = '';

    (data.items || []).forEach((item, idx) => {
        appendEditRow(tbody, idx, item);
    });
}

function appendEditRow(tbody, idx, item) {
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td style="color:#1e3c72;font-weight:700;">${idx + 1}</td>
        <td>
            <input type="text" class="edit-inp" style="min-width:130px;"
                   data-field="name" value="${esc(item.medicine_name||'')}"
                   placeholder="Medicine name" readonly>
            <input type="hidden" data-field="id"    value="${item.medicine_id||0}">
            <input type="hidden" data-field="price" value="${parseFloat(item.price_at_time||0).toFixed(2)}">
        </td>
        <td>₹${parseFloat(item.price_at_time||0).toFixed(2)}</td>
        <td><input type="text" class="edit-inp" data-field="dosage"
                   value="${esc(item.dosage||'')}" placeholder="e.g. 1 tablet twice daily"></td>
        <td><input type="text" class="edit-inp" data-field="duration"
                   value="${esc(item.duration||'')}" placeholder="e.g. 5 days"></td>
        <td><input type="text" class="edit-inp" style="min-width:140px;" data-field="instructions"
                   value="${esc(item.instructions||'')}" placeholder="After meals…"></td>
        <td><button type="button" class="del-row-btn" onclick="removeEditRow(this)">✕</button></td>
    `;
    tbody.appendChild(tr);
}

function removeEditRow(btn) {
    const tr = btn.closest('tr');
    tr.remove();
    // Re-number
    document.querySelectorAll('#editTbody tr').forEach((r, i) => {
        r.cells[0].textContent = i + 1;
    });
}

// ── Submit edit ───────────────────────────────────────────────────────────────
function submitEdit() {
    const feFields = document.getElementById('fe-fields');
    feFields.innerHTML = '';

    // Diagnosis & notes
    addHidden(feFields, 'edit_diagnosis', document.getElementById('editDiagnosis').value);
    addHidden(feFields, 'edit_notes',     document.getElementById('editNotes').value);

    // Rows
    document.querySelectorAll('#editTbody tr').forEach(tr => {
        addHidden(feFields, 'edit_med_id[]',    tr.querySelector('[data-field="id"]').value);
        addHidden(feFields, 'edit_med_name[]',  tr.querySelector('[data-field="name"]').value);
        addHidden(feFields, 'edit_med_price[]', tr.querySelector('[data-field="price"]').value);
        addHidden(feFields, 'edit_dosage[]',    tr.querySelector('[data-field="dosage"]').value);
        addHidden(feFields, 'edit_duration[]',  tr.querySelector('[data-field="duration"]').value);
        addHidden(feFields, 'edit_instructions[]', tr.querySelector('[data-field="instructions"]').value);
    });

    document.getElementById('formEdit').submit();
}

function addHidden(parent, name, value) {
    const i = document.createElement('input');
    i.type  = 'hidden';
    i.name  = name;
    i.value = value;
    parent.appendChild(i);
}

// ── HTML escape helper ────────────────────────────────────────────────────────
function esc(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
<?php $admin_conn->close(); $doctors_conn->close(); ?>