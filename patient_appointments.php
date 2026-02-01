<?php
require_once 'config/config.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = Database::getConnection('admin');

$stmt = $conn->prepare("
    SELECT * FROM appointments
    WHERE patient_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>

<head>
    <title>My Appointments</title>
    <link rel="stylesheet" href="styles/main.css">
    <script src="scripts/main.js"></script>
    <style>
        .appointment-card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .status-approved {
            color: green;
        }

        .status-rejected {
            color: red;
        }

        .status-pending {
            color: orange;
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>

    <h2 style="margin:30px;">📋 My Appointments</h2>

    <div style="max-width:800px; margin:auto;">
        <?php if ($result->num_rows === 0): ?>
            <p>No appointments booked yet.</p>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="appointment-card">
                    <h3>Dr. <?= htmlspecialchars($row['doctor_name']) ?></h3>
                    <p><strong>Date:</strong> <?= $row['appointment_date'] ?></p>
                    <p><strong>Time:</strong> <?= $row['appointment_time'] ?></p>

                    <p>
                        <strong>Status:</strong>
                        <span class="status-<?= $row['status'] ?>">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </p>

                    <?php if ($row['status'] === 'rejected'): ?>
                        <p style="color:#991b1b;">
                            <strong>Reason:</strong>
                            <?= htmlspecialchars($row['rejection_reason']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

</body>

</html>