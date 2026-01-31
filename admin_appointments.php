<?php
require_once 'config/config.php';
require_once 'config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

$conn = Database::getConnection('admin');

/* Handle approve / reject */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'approved',
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $_SESSION['admin_id'], $appointment_id);
        $stmt->execute();
    }

    if ($action === 'reject') {
        $reason = trim($_POST['rejection_reason']);

        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'rejected',
                rejection_reason = ?,
                verified_by = ?,
                verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $reason, $_SESSION['admin_id'], $appointment_id);
        $stmt->execute();
    }
}

/* Fetch pending appointments */
$result = $conn->query("
    SELECT * FROM appointments
    WHERE status = 'pending'
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Appointments</title>
    <style>
        table { width:100%; border-collapse: collapse; }
        th, td { padding:10px; border:1px solid #ccc; }
        th { background:#f3f4f6; }
        textarea { width:100%; }
    </style>
</head>
<body>

<h2>Pending Appointment Requests</h2>

<?php if ($result->num_rows === 0): ?>
    <p>No pending appointments.</p>
<?php else: ?>
<table>
    <tr>
        <th>Patient</th>
        <th>Doctor</th>
        <th>Date</th>
        <th>Time</th>
        <th>Action</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['patient_name']) ?></td>
        <td><?= htmlspecialchars($row['doctor_name']) ?></td>
        <td><?= $row['appointment_date'] ?></td>
        <td><?= $row['appointment_time'] ?></td>
        <td>
            <!-- Approve -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit">✅ Approve</button>
            </form>

            <!-- Reject -->
            <form method="POST" style="margin-top:5px;">
                <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <textarea name="rejection_reason" required placeholder="Reason"></textarea>
                <button type="submit">❌ Reject</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php endif; ?>

</body>
</html>
