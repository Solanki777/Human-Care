<?php
/**
 * admin_appointments.php
 * Admin views appointments + can:
 *   - Approve / Reject pending appointments
 *   - Cancel approved appointments
 *   - Review prescription medicines → Approve, Edit items, or Cancel (reject) the prescription
 *   - Marking prescription approved → marks appointment completed + patient sees it
 */

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}


$doctors_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_DOCTORS
);

$admin_conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_ADMIN
);

if ($admin_conn->connect_error || $doctors_conn->connect_error) {
    die("Connection failed");
}

$msg = '';
$msg_type = '';

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appointment_id'])) {
    if (!csrf_validate()) { die('Invalid CSRF token'); }
    $aid = intval($_POST['appointment_id']);
    $action = $_POST['action'];
    $admin_id = $_SESSION['admin_id'] ?? 0;

    // ── Appointment-level actions ─────────────────────────────────────────────
    if (in_array($action, ['approve', 'reject', 'complete', 'cancel'])) {
        $new_status = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'complete' => 'completed',
            'cancel' => 'cancelled',
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
            $ip = $_SERVER['REMOTE_ADDR'];
            $l = $admin_conn->prepare("INSERT INTO activity_logs (admin_id,action,description,ip_address) VALUES (?,?,?,?)");
            $la = "appointment_$action";
            $l->bind_param("isss", $admin_id, $la, $desc, $ip);
            $l->execute();
            $l->close();
        } else {
            $msg = "Database error or appointment not found.";
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

            $msg = "✅ Prescription approved and released to patient. Appointment marked complete.";
            $msg_type = 'success';

        } elseif ($action === 'rx_cancel') {
            // Reject/cancel the prescription → reset back to pending so doctor can rewrite
            $s = $doctors_conn->prepare("
                UPDATE prescriptions SET status='cancelled', updated_at=NOW() WHERE id=?
            ");
            $s->bind_param("i", $rx_id);
            $s->execute();
            $s->close();

            $msg = "🚫 Prescription cancelled. Doctor will need to resubmit.";
            $msg_type = 'error';

        } elseif ($action === 'rx_edit') {
            // Admin edits prescription items
            $diagnosis = trim($_POST['edit_diagnosis'] ?? '');
            $notes = trim($_POST['edit_notes'] ?? '');
            $med_ids = $_POST['edit_med_id'] ?? [];
            $med_names = $_POST['edit_med_name'] ?? [];
            $med_prices = $_POST['edit_med_price'] ?? [];
            $dosages = $_POST['edit_dosage'] ?? [];
            $durations = $_POST['edit_duration'] ?? [];
            $instructions = $_POST['edit_instructions'] ?? [];

            $doctors_conn->begin_transaction();
            try {
                // Update header
                $s = $doctors_conn->prepare("
                    UPDATE prescriptions SET diagnosis=?, additional_notes=?, updated_at=NOW() WHERE id=?
                ");
                $s->bind_param("ssi", $diagnosis, $notes, $rx_id);
                $s->execute();
                $s->close();

                // Replace items (prepared statement)
                $del_stmt = $doctors_conn->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
                $del_stmt->bind_param("i", $rx_id);
                $del_stmt->execute();
                $del_stmt->close();
                $si = $doctors_conn->prepare("
                    INSERT INTO prescription_items
                    (prescription_id, medicine_id, medicine_name, price_at_time, dosage, duration, instructions)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($med_ids as $i => $mid) {
                    $mid = intval($mid);
                    $mname = trim($med_names[$i] ?? '');
                    $price = floatval($med_prices[$i] ?? 0);
                    $dos = trim($dosages[$i] ?? '');
                    $dur = trim($durations[$i] ?? '');
                    $ins = trim($instructions[$i] ?? '');
                    if ($mid > 0 && $mname !== '') {
                        $si->bind_param("iisdsss", $rx_id, $mid, $mname, $price, $dos, $dur, $ins);
                        $si->execute();
                    }
                }
                $si->close();

                $doctors_conn->commit();
                $msg = "✏️ Prescription updated successfully. You can now approve it.";
                $msg_type = 'success';
            } catch (Exception $e) {
                $doctors_conn->rollback();
                $msg = "Database error while editing prescription.";
                $msg_type = 'error';
            }
        }
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$allowed = ['all', 'pending', 'approved', 'completed', 'rejected', 'cancelled'];
if (!in_array($filter, $allowed))
    $filter = 'all';

// ── Load appointments (prepared statement) ────────────────────────────────────
$appt_sql = "
    SELECT a.*,
           p.id              AS rx_id,
           p.status          AS rx_status,
           p.diagnosis       AS rx_diagnosis,
           p.additional_notes AS rx_notes
    FROM appointments a
    LEFT JOIN if0_42370337_human_care_doctors.prescriptions p ON p.appointment_id = a.id
";
if ($filter !== 'all') {
    $appt_sql .= " WHERE a.status = ?";
}
$appt_sql .= " ORDER BY CASE a.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END, a.appointment_date ASC";

$appt_stmt = $admin_conn->prepare($appt_sql);
if ($filter !== 'all') {
    $appt_stmt->bind_param("s", $filter);
}
$appt_stmt->execute();
$appointments = $appt_stmt->get_result();
$appt_stmt->close();

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
foreach (['all', 'pending', 'approved', 'completed', 'rejected', 'cancelled'] as $s) {
    $w = $s !== 'all' ? "WHERE status='$s'" : '';
    $counts[$s] = $admin_conn->query("SELECT COUNT(*) c FROM appointments $w")->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments – Admin</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/admin_appointments.css">
   
</head>

<body>
    <?php include 'includes/admin_sidebar.php'; ?>

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
            <?php foreach (['all', 'pending', 'approved', 'completed', 'rejected', 'cancelled'] as $s): ?>
                <a href="?filter=<?= $s ?>" class="f-btn <?= $filter === $s ? 'active' : '' ?>">
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
                                <div style="font-weight:700;color:#1e293b;"><?= htmlspecialchars($a['patient_name']) ?>
                                </div>
                                <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($a['patient_email']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;color:#1e293b;">Dr. <?= htmlspecialchars($a['doctor_name']) ?>
                                </div>
                                <div style="font-size:11px;color:#64748b;">
                                    <?= htmlspecialchars($a['doctor_specialty'] ?? '') ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= date('M d, Y', strtotime($a['appointment_date'])) ?></div>
                                <div style="font-size:12px;color:#64748b;">
                                    <?= date('h:i A', strtotime($a['appointment_time'])) ?></div>
                            </td>
                            <td style="max-width:180px;color:#475569;">
                                <?= htmlspecialchars(substr($a['reason_for_visit'] ?? '', 0, 70)) ?>
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
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                        <button name="action" value="approve" class="act-btn act-approve">✓ Approve</button>
                                    </form>
                                    <!-- Reject -->
                                    <form class="action-form" method="POST"
                                        onsubmit="return confirm('Reject this appointment?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                        <button name="action" value="reject" class="act-btn act-reject">✗ Reject</button>
                                    </form>

                                <?php elseif ($a['status'] === 'approved'): ?>

                                    <?php if ($a['rx_id'] && $a['rx_status'] === 'pending'): ?>
                                        <!-- ★ Review Prescription button → opens modal -->
                                        <button class="act-btn act-review" onclick="openRxModal(<?= htmlspecialchars(json_encode([
                                            'aid' => $a['id'],
                                            'rx_id' => $a['rx_id'],
                                            'patient' => $a['patient_name'],
                                            'doctor' => $a['doctor_name'],
                                            'date' => date('M d, Y', strtotime($a['appointment_date'])),
                                            'diagnosis' => $a['rx_diagnosis'] ?? '',
                                            'notes' => $a['rx_notes'] ?? '',
                                            'items' => $rx_items[$a['rx_id']] ?? [],
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
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                        <button name="action" value="cancel" class="act-btn act-cancel" style="margin-top:4px;">
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
            Admin clicks <strong>"Review Rx"</strong> → views medicines → can <strong>Approve</strong>,
            <strong>Edit</strong>, or <strong>Cancel</strong> the Rx →
            On Rx Approve: prescription becomes <em>approved</em> + appointment marked <em>completed</em> → Patient can
            see it.<br>
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
                    <button class="mode-tab active" id="tab-view" onclick="switchTab('view')">👁️ View
                        Prescription</button>
                    <button class="mode-tab" id="tab-edit" onclick="switchTab('edit')">✏️ Edit Prescription</button>
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
                    <?php echo csrf_field(); ?>
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
                <button class="mf-btn mf-save" id="btnSaveEdit" style="display:none;" onclick="submitEdit()">💾 Save
                    Changes</button>

                <!-- Hidden form for edit submit -->
                <form method="POST" id="formEdit" style="display:none;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="rx_edit">
                    <input type="hidden" name="appointment_id" id="fe-aid">
                    <input type="hidden" name="rx_id" id="fe-rxid">
                    <div id="fe-fields"></div>
                </form>

                <!-- Cancel prescription -->
                <form method="POST" id="formCancel" style="display:inline;">
                    <?php echo csrf_field(); ?>
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

<script src="js/admin_appointments.js"></script>
</body>

</html>
<?php $admin_conn->close();
$doctors_conn->close(); ?>