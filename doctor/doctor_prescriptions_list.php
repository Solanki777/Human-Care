<?php
/**
 * doctor_prescriptions_list.php
 * Doctor writes a prescription by selecting medicines from DB.
 * Prescription goes to admin (status=pending).
 * Admin marks appointment complete → prescription becomes approved → patient sees it.
 */
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$active_page = 'prescriptions';
$doctor_id = $_SESSION['user_id'];

// ── DB connections ────────────────────────────────────────
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

if ($doctors_conn->connect_error || $admin_conn->connect_error) {
    die("Connection failed");
}

// ── Load doctor ───────────────────────────────────────────
$stmt = $doctors_conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$doctor) {
    header("Location: login.php");
    exit();
}

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
$appointment = null;
$existing_rx = null;
$saved_items = [];
$success_msg = '';
$error_msg = '';

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
    } elseif (!in_array($appointment['status'], ['approved'])) {
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
    if (!csrf_validate()) { die('Invalid CSRF token'); }
    $medicine_ids = $_POST['medicine_id'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $instructions = $_POST['instructions'] ?? [];
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    // Build valid items list
    $items = [];
    foreach ($medicine_ids as $i => $mid) {
        $mid = intval($mid);
        if ($mid > 0 && isset($all_medicines[$mid])) {
            $items[] = [
                'medicine_id' => $mid,
                'medicine_name' => $all_medicines[$mid]['name'],
                'price_at_time' => $all_medicines[$mid]['price'],
                'dosage' => trim($dosages[$i] ?? ''),
                'duration' => trim($durations[$i] ?? ''),
                'instructions' => trim($instructions[$i] ?? ''),
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
                $del_stmt = $doctors_conn->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
                $del_stmt->bind_param("i", $rx_id);
                $del_stmt->execute();
                $del_stmt->close();
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
        $status_bg = '#d1fae5';
    } else {
        $status_label = '⏳ Pending Admin Approval';
        $status_color = '#92400e';
        $status_bg = '#fef3c7';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Prescription – Human Care</title>
   
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/prescriptions.css">
    
</head>

<body>
    
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
                                    background:<?= $a['status'] === 'completed' ? '#d1fae5' : '#dbeafe' ?>;
                                    color:<?= $a['status'] === 'completed' ? '#065f46' : '#1e40af' ?>;">
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
                            <span
                                class="pinfo-value"><?= htmlspecialchars(substr($appointment['reason_for_visit'] ?? '', 0, 60)) ?></span>
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
                    <div class="rx-status-pill" style="background:<?= $status_bg ?>;color:<?= $status_color ?>;">
                        <?= $status_label ?>
                    </div>
                <?php endif; ?>

                <!-- Prescription Form -->
                <form method="POST" id="rxForm">
                    <?= csrf_field() ?>
                    <!-- STEP 1: Pick medicines from DB -->
                    <div class="rx-card">
                        <div class="rx-section-title">🔍 Step 1 — Search & Select Medicines</div>

                        <!-- Category tabs -->
                        <div class="cat-tabs">
                            <button type="button" class="cat-tab active" onclick="filterCat('all',this)">All</button>
                            <?php foreach (array_keys($medicines_by_category) as $cat): ?>
                                <button type="button" class="cat-tab" onclick="filterCat('<?= htmlspecialchars($cat) ?>',this)">
                                    <?= htmlspecialchars($cat) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Search box -->
                        <div class="med-search-wrap">
                            <span class="med-search-icon">🔍</span>
                            <input type="text" class="med-search" id="medSearch" placeholder="Search medicine name…"
                                oninput="searchMeds()">
                        </div>

                        <!-- Medicines grid -->
                        <div class="medicines-grid" id="medsGrid">
                            <?php foreach ($all_medicines as $m): ?>
                                <div class="med-item" id="medcard_<?= $m['id'] ?>" data-id="<?= $m['id'] ?>"
                                    data-name="<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>" data-price="<?= $m['price'] ?>"
                                    data-form="<?= htmlspecialchars($m['dosage_form'], ENT_QUOTES) ?>"
                                    data-strength="<?= htmlspecialchars($m['strength'], ENT_QUOTES) ?>"
                                    data-cat="<?= htmlspecialchars($m['category'], ENT_QUOTES) ?>" onclick="toggleMed(this)">
                                    <div class="med-name"><?= htmlspecialchars($m['name']) ?></div>
                                    <div class="med-meta">
                                        <?= htmlspecialchars($m['dosage_form']) ?> · <?= htmlspecialchars($m['strength']) ?>
                                    </div>
                                    <div class="med-price">₹<?= number_format($m['price'], 2) ?></div>
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
                                        <th>#</th>
                                        <th>Medicine</th>
                                        <th>Price/Unit</th>
                                        <th>Dosage</th>
                                        <th>Duration</th>
                                        <th>Instructions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $tot = 0;
                                    foreach ($saved_items as $idx => $it):
                                        $tot += $it['price_at_time']; ?>
                                        <tr>
                                            <td style="color:#667eea;font-weight:700;"><?= $idx + 1 ?></td>
                                            <td>
                                                <div class="tbl-med-name"><?= htmlspecialchars($it['medicine_name']) ?></div>
                                                <div class="tbl-med-meta">
                                                    <?= htmlspecialchars($it['dosage_form'] ?? '') ?>
                                                    <?= $it['strength'] ? '· ' . htmlspecialchars($it['strength']) : '' ?>
                                                </div>
                                            </td>
                                            <td class="tbl-price">₹<?= number_format($it['price_at_time'], 2) ?></td>
                                            <td><?= htmlspecialchars($it['dosage'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($it['duration'] ?? '—') ?></td>
                                            <td><?= nl2br(htmlspecialchars($it['instructions'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="2" style="color:#475569;">Total</td>
                                        <td style="color:#667eea;">₹<?= number_format($tot, 2) ?></td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php if (!empty($existing_rx['diagnosis'])): ?>
                            <div
                                style="margin-top:16px;padding:14px;background:#fffbeb;border-left:4px solid #f59e0b;border-radius:8px;">
                                <strong>Diagnosis:</strong> <?= nl2br(htmlspecialchars($existing_rx['diagnosis'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>

<script src="js/prescriptions.js"></script>
</body>

</html>
<?php $doctors_conn->close();
$admin_conn->close(); ?>