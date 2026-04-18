<?php
/**
 * doctor_prescriptions_list.php
 * Doctor writes a prescription by selecting medicines from DB.
 * Prescription goes to admin (status=pending).
 * Admin marks appointment complete → prescription becomes approved → patient sees it.
 */
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$active_page = 'prescriptions';
$doctor_id   = $_SESSION['user_id'];

// ── DB connections ────────────────────────────────────────
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$admin_conn   = new mysqli("localhost", "root", "", "human_care_admin");
if ($doctors_conn->connect_error || $admin_conn->connect_error) {
    die("Connection failed");
}

// ── Load doctor ───────────────────────────────────────────
$stmt   = $doctors_conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$doctor) { header("Location: login.php"); exit(); }

$doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];

// ── Load ALL medicines from DB (for the picker) ───────────
$medicines_result = $doctors_conn->query(
    "SELECT * FROM medicines WHERE is_active = 1 ORDER BY category, name"
);
$all_medicines = [];
$medicines_by_category = [];
while ($m = $medicines_result->fetch_assoc()) {
    $all_medicines[$m['id']] = $m;
    $medicines_by_category[$m['category']][] = $m;
}

// ── Appointment from URL ──────────────────────────────────
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$appointment    = null;
$existing_rx    = null;
$saved_items    = [];
$success_msg    = '';
$error_msg      = '';

if ($appointment_id > 0) {
    // Fetch from admin DB (appointments live there)
    $stmt = $admin_conn->prepare("
        SELECT * FROM appointments WHERE id = ? AND doctor_id = ?
    ");
    $stmt->bind_param("ii", $appointment_id, $doctor_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        $error_msg = "Appointment not found or access denied.";
    } elseif (!in_array($appointment['status'], ['approved', 'completed'])) {
        $error_msg = "Prescriptions can only be written for approved appointments.";
    } else {
        // Existing prescription?
        $stmt = $doctors_conn->prepare(
            "SELECT * FROM prescriptions WHERE appointment_id = ?"
        );
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $existing_rx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing_rx) {
            $stmt = $doctors_conn->prepare(
                "SELECT pi.*, m.dosage_form, m.strength, m.category
                 FROM prescription_items pi
                 LEFT JOIN medicines m ON pi.medicine_id = m.id
                 WHERE pi.prescription_id = ?"
            );
            $stmt->bind_param("i", $existing_rx['id']);
            $stmt->execute();
            $saved_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// ── Handle form submit ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment) {
    $medicine_ids    = $_POST['medicine_id']    ?? [];
    $dosages         = $_POST['dosage']         ?? [];
    $durations       = $_POST['duration']       ?? [];
    $instructions    = $_POST['instructions']   ?? [];
    $diagnosis       = trim($_POST['diagnosis']       ?? '');
    $additional_notes= trim($_POST['additional_notes'] ?? '');

    // Build valid items list
    $items = [];
    foreach ($medicine_ids as $i => $mid) {
        $mid = intval($mid);
        if ($mid > 0 && isset($all_medicines[$mid])) {
            $items[] = [
                'medicine_id'   => $mid,
                'medicine_name' => $all_medicines[$mid]['name'],
                'price_at_time' => $all_medicines[$mid]['price'],
                'dosage'        => trim($dosages[$i]      ?? ''),
                'duration'      => trim($durations[$i]    ?? ''),
                'instructions'  => trim($instructions[$i] ?? ''),
            ];
        }
    }

    if (empty($items)) {
        $error_msg = "Please select at least one medicine.";
    } else {
        $doctors_conn->begin_transaction();
        try {
            if ($existing_rx) {
                // Update prescription header
                $stmt = $doctors_conn->prepare("
                    UPDATE prescriptions
                    SET diagnosis = ?, additional_notes = ?, status = 'pending', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("ssi", $diagnosis, $additional_notes, $existing_rx['id']);
                $stmt->execute();
                $stmt->close();
                $rx_id = $existing_rx['id'];
                // Delete old items to replace them
                $doctors_conn->query("DELETE FROM prescription_items WHERE prescription_id = $rx_id");
            } else {
                // Insert new prescription
                $stmt = $doctors_conn->prepare("
                    INSERT INTO prescriptions
                    (appointment_id, doctor_id, patient_id, diagnosis, additional_notes, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $pid = $appointment['patient_id'] ?? 0;
                $stmt->bind_param("iiiss", $appointment_id, $doctor_id, $pid, $diagnosis, $additional_notes);
                $stmt->execute();
                $rx_id = $doctors_conn->insert_id;
                $stmt->close();
            }

            // Insert items
            $stmt = $doctors_conn->prepare("
                INSERT INTO prescription_items
                (prescription_id, medicine_id, medicine_name, price_at_time, dosage, duration, instructions)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $it) {
                $stmt->bind_param(
                    "iisdsss",
                    $rx_id,
                    $it['medicine_id'],
                    $it['medicine_name'],
                    $it['price_at_time'],
                    $it['dosage'],
                    $it['duration'],
                    $it['instructions']
                );
                $stmt->execute();
            }
            $stmt->close();

            $doctors_conn->commit();
            $success_msg = "✅ Prescription saved and sent to admin for approval. Patient will see it once the appointment is marked complete.";

            // Reload
            $stmt = $doctors_conn->prepare("SELECT * FROM prescriptions WHERE id = ?");
            $stmt->bind_param("i", $rx_id);
            $stmt->execute();
            $existing_rx = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $doctors_conn->prepare("SELECT pi.*, m.dosage_form, m.strength, m.category FROM prescription_items pi LEFT JOIN medicines m ON pi.medicine_id = m.id WHERE pi.prescription_id = ?");
            $stmt->bind_param("i", $rx_id);
            $stmt->execute();
            $saved_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

        } catch (Exception $e) {
            $doctors_conn->rollback();
            $error_msg = "Database error. Please try again.";
        }
    }
}

// Status label helper
$status_label = '';
$status_color = '';
if ($existing_rx) {
    if ($existing_rx['status'] === 'approved') {
        $status_label = '✅ Approved — Visible to Patient';
        $status_color = '#065f46';
        $status_bg    = '#d1fae5';
    } else {
        $status_label = '⏳ Pending Admin Approval';
        $status_color = '#92400e';
        $status_bg    = '#fef3c7';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Write Prescription – Human Care</title>
<link rel="stylesheet" href="styles/dashboard.css">
<style>
/* ── Layout ──────────────────────────────── */
.rx-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
.rx-page-title { font-size: 26px; font-weight: 700; color: #1e293b; margin-bottom: 24px;
    display: flex; align-items: center; gap: 10px; }

/* ── Patient card ────────────────────────── */
.patient-card { background:#fff; border-radius:14px;
    box-shadow:0 2px 12px rgba(0,0,0,.08); padding:24px; margin-bottom:24px; }
.patient-card-header { display:flex; align-items:center; gap:14px;
    padding-bottom:16px; border-bottom:2px solid #e0e7ff; margin-bottom:16px; }
.patient-avatar { width:56px; height:56px; background:linear-gradient(135deg,#667eea,#764ba2);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:26px; color:#fff; flex-shrink:0; }
.patient-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
.pinfo-item { display:flex; flex-direction:column; }
.pinfo-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.pinfo-value { font-size:14px; font-weight:600; color:#1e293b; }

/* ── Status pill ─────────────────────────── */
.rx-status-pill { display:inline-flex; align-items:center; gap:8px;
    padding:8px 18px; border-radius:50px; font-size:13px; font-weight:600;
    margin-bottom:20px; }

/* ── Form card ───────────────────────────── */
.rx-card { background:#fff; border-radius:14px;
    box-shadow:0 2px 12px rgba(0,0,0,.08); padding:28px; margin-bottom:24px; }
.rx-section-title { font-size:17px; font-weight:700; color:#1e293b;
    margin-bottom:18px; display:flex; align-items:center; gap:8px; }

/* ── Medicine picker ─────────────────────── */
.med-search-wrap { position:relative; margin-bottom:14px; }
.med-search { width:100%; padding:11px 16px 11px 40px;
    border:2px solid #e2e8f0; border-radius:10px; font-size:14px; outline:none;
    transition:.3s; box-sizing:border-box; }
.med-search:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.15); }
.med-search-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%);
    font-size:16px; color:#94a3b8; pointer-events:none; }

.cat-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.cat-tab { padding:6px 14px; border:2px solid #e2e8f0; border-radius:20px;
    background:#fff; color:#64748b; font-size:12px; font-weight:600;
    cursor:pointer; transition:.2s; }
.cat-tab:hover, .cat-tab.active { border-color:#667eea; color:#667eea; background:#eff6ff; }

.medicines-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:10px; max-height:340px; overflow-y:auto; padding:4px 2px; }
.med-item { border:2px solid #e2e8f0; border-radius:10px; padding:12px;
    cursor:pointer; transition:.2s; background:#fff; user-select:none; }
.med-item:hover { border-color:#667eea; background:#fafbff; }
.med-item.selected { border-color:#667eea; background:#eff6ff; }
.med-name { font-size:13px; font-weight:700; color:#1e293b; margin-bottom:4px; }
.med-meta { font-size:11px; color:#64748b; }
.med-price { font-size:13px; font-weight:700; color:#667eea; margin-top:6px; }

/* ── Selected medicines table ────────────── */
.selected-table { width:100%; border-collapse:collapse; margin-top:6px; }
.selected-table th { background:#f1f5f9; padding:11px 12px; text-align:left;
    font-size:12px; font-weight:700; color:#475569; text-transform:uppercase;
    letter-spacing:.4px; }
.selected-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9;
    vertical-align:top; font-size:13px; }
.selected-table tbody tr:hover { background:#fafbff; }
.tbl-med-name { font-weight:700; color:#1e293b; font-size:13px; }
.tbl-med-meta { font-size:11px; color:#64748b; margin-top:2px; }
.tbl-price { font-weight:700; color:#667eea; }
.tbl-input { width:100%; padding:7px 10px; border:1.5px solid #e2e8f0;
    border-radius:7px; font-size:13px; outline:none; transition:.2s; box-sizing:border-box; }
.tbl-input:focus { border-color:#667eea; }
.del-btn { background:#fee2e2; color:#dc2626; border:none; padding:6px 12px;
    border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; transition:.2s; }
.del-btn:hover { background:#dc2626; color:#fff; }

.total-row { background:#f8fafc; font-weight:700; }
.total-row td { padding:12px; }

/* ── Form fields ─────────────────────────── */
.form-group { margin-bottom:18px; }
.form-group label { display:block; font-size:13px; font-weight:600;
    color:#374151; margin-bottom:7px; }
.form-control { width:100%; padding:11px 14px; border:2px solid #e2e8f0;
    border-radius:9px; font-size:14px; font-family:inherit; outline:none;
    transition:.3s; box-sizing:border-box; resize:vertical; }
.form-control:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.12); }

/* ── Alerts ──────────────────────────────── */
.alert { padding:14px 18px; border-radius:10px; margin-bottom:20px;
    font-weight:500; border-left:4px solid; display:flex; align-items:center; gap:10px; }
.alert-success { background:#d1fae5; color:#065f46; border-color:#10b981; }
.alert-error   { background:#fee2e2; color:#991b1b; border-color:#ef4444; }

/* ── Buttons ─────────────────────────────── */
.btn-row { display:flex; gap:14px; margin-top:24px; }
.btn-save { flex:1; padding:14px; background:linear-gradient(135deg,#667eea,#764ba2);
    color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:700;
    cursor:pointer; transition:.3s; }
.btn-save:hover { transform:translateY(-2px); box-shadow:0 5px 18px rgba(102,126,234,.35); }
.btn-back { flex:1; padding:14px; background:#f1f5f9; color:#475569; border:none;
    border-radius:10px; font-size:15px; font-weight:600; cursor:pointer;
    text-decoration:none; text-align:center; transition:.2s; }
.btn-back:hover { background:#e2e8f0; }

/* ── Readonly saved view ─────────────────── */
.saved-rx { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:28px; }

/* ── Empty state ─────────────────────────── */
.empty-box { background:#fff; border-radius:14px; padding:60px 30px;
    text-align:center; box-shadow:0 2px 12px rgba(0,0,0,.06); }
.empty-box h3 { color:#1e293b; margin-bottom:10px; }
.empty-box p { color:#64748b; }
</style>
</head>
<body>
<button class="menu-toggle" id="menuToggle">☰</button>
<?php include 'includes/doctor_sidebar.php'; ?>

<main class="main-content">
<div class="rx-wrap">
    <div class="rx-page-title">💊 Write Prescription</div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (!$appointment && !$error_msg): ?>
        <!-- No appointment selected – show list of completed appointments -->
        <div class="rx-card">
            <div class="rx-section-title">📋 Select an Appointment to Prescribe</div>
            <?php
            $app_list = $admin_conn->prepare("
                SELECT * FROM appointments
                WHERE doctor_id = ? AND status IN ('approved')
                ORDER BY appointment_date DESC LIMIT 30
            ");
            $app_list->bind_param("i", $doctor_id);
            $app_list->execute();
            $apps = $app_list->get_result();
            ?>
            <?php if ($apps->num_rows === 0): ?>
                <p style="color:#64748b;">No approved or completed appointments found.</p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <th style="padding:10px;text-align:left;font-size:12px;color:#475569;">Patient</th>
                            <th style="padding:10px;text-align:left;font-size:12px;color:#475569;">Date</th>
                            <th style="padding:10px;text-align:left;font-size:12px;color:#475569;">Status</th>
                            <th style="padding:10px;text-align:left;font-size:12px;color:#475569;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($a = $apps->fetch_assoc()): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px;font-size:14px;font-weight:600;color:#1e293b;">
                                <?= htmlspecialchars($a['patient_name']) ?>
                            </td>
                            <td style="padding:10px;font-size:13px;color:#475569;">
                                <?= date('M d, Y', strtotime($a['appointment_date'])) ?>
                            </td>
                            <td style="padding:10px;">
                                <span style="padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;
                                    background:<?= $a['status']==='completed'?'#d1fae5':'#dbeafe' ?>;
                                    color:<?= $a['status']==='completed'?'#065f46':'#1e40af' ?>;">
                                    <?= strtoupper($a['status']) ?>
                                </span>
                            </td>
                            <td style="padding:10px;">
                                <a href="?appointment_id=<?= $a['id'] ?>"
                                   style="padding:7px 16px;background:linear-gradient(135deg,#667eea,#764ba2);
                                          color:#fff;border-radius:7px;text-decoration:none;font-size:12px;font-weight:700;">
                                    💊 Prescribe
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($appointment): ?>

        <!-- Patient Info Card -->
        <div class="patient-card">
            <div class="patient-card-header">
                <div class="patient-avatar">👤</div>
                <div>
                    <div style="font-size:20px;font-weight:700;color:#1e293b;">
                        <?= htmlspecialchars($appointment['patient_name']) ?>
                    </div>
                    <div style="font-size:13px;color:#64748b;">
                        <?= date('F d, Y', strtotime($appointment['appointment_date'])) ?>
                        at <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                    </div>
                </div>
            </div>
            <div class="patient-grid">
                <div class="pinfo-item">
                    <span class="pinfo-label">Email</span>
                    <span class="pinfo-value"><?= htmlspecialchars($appointment['patient_email']) ?></span>
                </div>
                <div class="pinfo-item">
                    <span class="pinfo-label">Phone</span>
                    <span class="pinfo-value"><?= htmlspecialchars($appointment['patient_phone']) ?></span>
                </div>
                <div class="pinfo-item">
                    <span class="pinfo-label">Reason</span>
                    <span class="pinfo-value"><?= htmlspecialchars(substr($appointment['reason_for_visit']??'',0,60)) ?></span>
                </div>
                <div class="pinfo-item">
                    <span class="pinfo-label">Appointment Status</span>
                    <span class="pinfo-value" style="text-transform:capitalize;">
                        <?= htmlspecialchars($appointment['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Prescription status pill -->
        <?php if ($existing_rx): ?>
            <div class="rx-status-pill"
                 style="background:<?= $status_bg ?>;color:<?= $status_color ?>;">
                <?= $status_label ?>
            </div>
        <?php endif; ?>

        <!-- Prescription Form -->
        <form method="POST" id="rxForm">
            <!-- STEP 1: Pick medicines from DB -->
            <div class="rx-card">
                <div class="rx-section-title">🔍 Step 1 — Search & Select Medicines</div>

                <!-- Category tabs -->
                <div class="cat-tabs">
                    <button type="button" class="cat-tab active" onclick="filterCat('all',this)">All</button>
                    <?php foreach (array_keys($medicines_by_category) as $cat): ?>
                        <button type="button" class="cat-tab"
                            onclick="filterCat('<?= htmlspecialchars($cat) ?>',this)">
                            <?= htmlspecialchars($cat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Search box -->
                <div class="med-search-wrap">
                    <span class="med-search-icon">🔍</span>
                    <input type="text" class="med-search" id="medSearch"
                           placeholder="Search medicine name…" oninput="searchMeds()">
                </div>

                <!-- Medicines grid -->
                <div class="medicines-grid" id="medsGrid">
                    <?php foreach ($all_medicines as $m): ?>
                        <div class="med-item"
                             id="medcard_<?= $m['id'] ?>"
                             data-id="<?= $m['id'] ?>"
                             data-name="<?= htmlspecialchars($m['name'],ENT_QUOTES) ?>"
                             data-price="<?= $m['price'] ?>"
                             data-form="<?= htmlspecialchars($m['dosage_form'],ENT_QUOTES) ?>"
                             data-strength="<?= htmlspecialchars($m['strength'],ENT_QUOTES) ?>"
                             data-cat="<?= htmlspecialchars($m['category'],ENT_QUOTES) ?>"
                             onclick="toggleMed(this)">
                            <div class="med-name"><?= htmlspecialchars($m['name']) ?></div>
                            <div class="med-meta">
                                <?= htmlspecialchars($m['dosage_form']) ?> · <?= htmlspecialchars($m['strength']) ?>
                            </div>
                            <div class="med-price">₹<?= number_format($m['price'],2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STEP 2: Selected medicines table -->
            <div class="rx-card" id="selectedSection" style="display:none;">
                <div class="rx-section-title">📋 Step 2 — Selected Medicines & Instructions</div>
                <div style="overflow-x:auto;">
                <table class="selected-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medicine</th>
                            <th>Price/Unit</th>
                            <th>Dosage</th>
                            <th>Duration</th>
                            <th>Instructions</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="selectedBody"></tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="color:#475569;">Total (all medicines)</td>
                            <td id="totalPrice" style="color:#667eea;">₹0.00</td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <p style="font-size:12px;color:#94a3b8;margin-top:8px;">
                    * Price shown is per unit. Actual total depends on quantity prescribed.
                </p>
            </div>

            <!-- STEP 3: Diagnosis & Notes -->
            <div class="rx-card">
                <div class="rx-section-title">🩺 Step 3 — Diagnosis & Notes</div>
                <div class="form-group">
                    <label>Diagnosis</label>
                    <textarea name="diagnosis" class="form-control" rows="3"
                        placeholder="Patient's diagnosis or medical condition…"><?=
                        htmlspecialchars($existing_rx['diagnosis'] ?? '')
                    ?></textarea>
                </div>
                <div class="form-group">
                    <label>Additional Notes / Follow-up</label>
                    <textarea name="additional_notes" class="form-control" rows="3"
                        placeholder="Special instructions, precautions, follow-up date…"><?=
                        htmlspecialchars($existing_rx['additional_notes'] ?? '')
                    ?></textarea>
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn-save" id="saveBtn" disabled>
                    📤 Submit Prescription for Approval
                </button>
                <a href="doctor_prescriptions_list.php" class="btn-back">← Back</a>
            </div>
        </form>

        <!-- Show already-saved items highlighted -->
        <?php if (!empty($saved_items)): ?>
        <div class="saved-rx" style="margin-top:24px;">
            <div class="rx-section-title">💾 Last Saved Prescription</div>
            <div style="overflow-x:auto;">
            <table class="selected-table">
                <thead>
                    <tr>
                        <th>#</th><th>Medicine</th><th>Price/Unit</th>
                        <th>Dosage</th><th>Duration</th><th>Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $tot = 0; foreach ($saved_items as $idx => $it): $tot += $it['price_at_time']; ?>
                    <tr>
                        <td style="color:#667eea;font-weight:700;"><?= $idx+1 ?></td>
                        <td>
                            <div class="tbl-med-name"><?= htmlspecialchars($it['medicine_name']) ?></div>
                            <div class="tbl-med-meta">
                                <?= htmlspecialchars($it['dosage_form']??'') ?>
                                <?= $it['strength']?'· '.htmlspecialchars($it['strength']):'' ?>
                            </div>
                        </td>
                        <td class="tbl-price">₹<?= number_format($it['price_at_time'],2) ?></td>
                        <td><?= htmlspecialchars($it['dosage']??'—') ?></td>
                        <td><?= htmlspecialchars($it['duration']??'—') ?></td>
                        <td><?= nl2br(htmlspecialchars($it['instructions']??'—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" style="color:#475569;">Total</td>
                        <td style="color:#667eea;">₹<?= number_format($tot,2) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <?php if (!empty($existing_rx['diagnosis'])): ?>
                <div style="margin-top:16px;padding:14px;background:#fffbeb;border-left:4px solid #f59e0b;border-radius:8px;">
                    <strong>Diagnosis:</strong> <?= nl2br(htmlspecialchars($existing_rx['diagnosis'])) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
</main>

<script>
// ── Medicine state ────────────────────────────────────────
const selected = {}; // id → {name,price,form,strength}

function toggleMed(card) {
    const id = card.dataset.id;
    if (selected[id]) {
        card.classList.remove('selected');
        delete selected[id];
    } else {
        card.classList.add('selected');
        selected[id] = {
            name:     card.dataset.name,
            price:    parseFloat(card.dataset.price),
            form:     card.dataset.form,
            strength: card.dataset.strength
        };
    }
    renderSelected();
}

function renderSelected() {
    const tbody = document.getElementById('selectedBody');
    const section = document.getElementById('selectedSection');
    const saveBtn = document.getElementById('saveBtn');
    const ids = Object.keys(selected);

    if (ids.length === 0) {
        tbody.innerHTML = '';
        section.style.display = 'none';
        saveBtn.disabled = true;
        document.getElementById('totalPrice').textContent = '₹0.00';
        return;
    }

    section.style.display = '';
    saveBtn.disabled = false;

    let rows = '';
    let total = 0;
    ids.forEach((id, i) => {
        const m = selected[id];
        total += m.price;
        rows += `
        <tr>
          <td style="color:#667eea;font-weight:700;">${i+1}</td>
          <td>
            <input type="hidden" name="medicine_id[]" value="${id}">
            <div class="tbl-med-name">${m.name}</div>
            <div class="tbl-med-meta">${m.form} · ${m.strength}</div>
          </td>
          <td class="tbl-price">₹${m.price.toFixed(2)}</td>
          <td>
            <input type="text" name="dosage[]" class="tbl-input"
                   placeholder="e.g. 1 tablet twice daily">
          </td>
          <td>
            <input type="text" name="duration[]" class="tbl-input"
                   placeholder="e.g. 5 days">
          </td>
          <td>
            <input type="text" name="instructions[]" class="tbl-input"
                   placeholder="Take after meals…">
          </td>
          <td>
            <button type="button" class="del-btn" onclick="removeMed('${id}',this)">✕</button>
          </td>
        </tr>`;
    });
    tbody.innerHTML = rows;
    document.getElementById('totalPrice').textContent = '₹' + total.toFixed(2);
}

function removeMed(id, btn) {
    delete selected[id];
    const card = document.getElementById('medcard_' + id);
    if (card) card.classList.remove('selected');
    renderSelected();
}

// ── Category filter ───────────────────────────────────────
function filterCat(cat, btn) {
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.med-item').forEach(c => {
        c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
    });
}

// ── Search ────────────────────────────────────────────────
function searchMeds() {
    const q = document.getElementById('medSearch').value.toLowerCase();
    document.querySelectorAll('.med-item').forEach(c => {
        c.style.display = c.dataset.name.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Pre-select saved medicines on page reload ─────────────
<?php if (!empty($saved_items)): ?>
window.addEventListener('DOMContentLoaded', () => {
    <?php foreach ($saved_items as $it): ?>
    (function(){
        const card = document.getElementById('medcard_<?= $it['medicine_id'] ?>');
        if (card) {
            card.classList.add('selected');
            selected['<?= $it['medicine_id'] ?>'] = {
                name:     card.dataset.name,
                price:    parseFloat(card.dataset.price),
                form:     card.dataset.form,
                strength: card.dataset.strength
            };
        }
    })();
    <?php endforeach; ?>
    renderSelected();

    // Restore dosage/duration/instructions after re-render
    <?php foreach ($saved_items as $idx => $it): ?>
    (function(){
        const inputs = document.querySelectorAll('input[name="dosage[]"]');
        if (inputs[<?= $idx ?>]) inputs[<?= $idx ?>].value = <?= json_encode($it['dosage']??'') ?>;
        const dur = document.querySelectorAll('input[name="duration[]"]');
        if (dur[<?= $idx ?>]) dur[<?= $idx ?>].value = <?= json_encode($it['duration']??'') ?>;
        const ins = document.querySelectorAll('input[name="instructions[]"]');
        if (ins[<?= $idx ?>]) ins[<?= $idx ?>].value = <?= json_encode($it['instructions']??'') ?>;
    })();
    <?php endforeach; ?>
});
<?php endif; ?>

// ── Sidebar toggle ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('menuToggle');
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('sidebarOverlay');
    if (btn && sb && ov) {
        btn.addEventListener('click', e => { e.stopPropagation(); sb.classList.toggle('active'); ov.classList.toggle('active'); });
        ov.addEventListener('click', () => { sb.classList.remove('active'); ov.classList.remove('active'); });
    }
});
</script>
</body>
</html>
<?php $doctors_conn->close(); $admin_conn->close(); ?>