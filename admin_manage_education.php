<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Connect to admin database
$admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
if ($admin_conn->connect_error) {
    die("Connection failed: " . $admin_conn->connect_error);
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['content_id'])) {
    $content_id = intval($_POST['content_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $admin_conn->prepare("UPDATE educational_content SET status = 'approved' WHERE id = ?");
    } elseif ($action === 'reject') {
        $stmt = $admin_conn->prepare("UPDATE educational_content SET status = 'rejected' WHERE id = ?");
    }
    
    if (isset($stmt)) {
        $stmt->bind_param("i", $content_id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_manage_education.php");
        exit();
    }
}

// Get all educational content
$stmt = $admin_conn->prepare("
    SELECT * FROM educational_content 
    ORDER BY 
        CASE status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        created_at DESC
");
$stmt->execute();
$all_contents = $stmt->get_result();
$stmt->close();

// Get statistics
$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM educational_content WHERE status = 'pending'");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM educational_content WHERE status = 'approved'");
$stmt->execute();
$approved_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $admin_conn->prepare("SELECT COUNT(*) as count FROM educational_content WHERE status = 'rejected'");
$stmt->execute();
$rejected_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$admin_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Educational Content - Admin Dashboard</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card.pending .stat-number { color: #f59e0b; }
        .stat-card.approved .stat-number { color: #10b981; }
        .stat-card.rejected .stat-number { color: #ef4444; }

        .content-table-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .content-table {
            width: 100%;
            border-collapse: collapse;
        }

        .content-table th {
            background: #f9fafb;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1f2937;
            border-bottom: 2px solid #e5e7eb;
        }

        .content-table td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .content-table tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            margin-right: 5px;
            transition: all 0.3s;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .btn-view {
            background: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
        }

        .content-title-cell {
            max-width: 300px;
        }

        .content-icon {
            font-size: 24px;
            margin-right: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-label {
            font-weight: 600;
            color: #6b7280;
        }

        .detail-value {
            color: #1f2937;
        }

        .content-preview {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .close {
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>🏥 Admin Panel</h2>
            <button class="close-btn" onclick="toggleSidebar()">×</button>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="admin_manage_education.php" class="nav-item active">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Education Content</span>
            </a>
            <a href="logout.php" class="nav-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Top Bar -->
    <header class="top-bar">
        <button class="menu-btn" onclick="toggleSidebar()">☰</button>
        <h1>Manage Educational Content</h1>
        <div class="user-info">
            <span class="user-name">Admin</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card pending">
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-number"><?php echo $approved_count; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Content Table -->
        <div class="content-table-container">
            <h3 style="margin-bottom: 20px;">All Educational Content</h3>
            
            <?php if ($all_contents->num_rows > 0): ?>
                <table class="content-table">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Doctor</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($content = $all_contents->fetch_assoc()): ?>
                            <tr>
                                <td class="content-title-cell">
                                    <span class="content-icon"><?php echo $content['icon']; ?></span>
                                    <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                                </td>
                                <td>
                                    Dr. <?php echo htmlspecialchars($content['doctor_name']); ?><br>
                                    <small style="color: #6b7280;"><?php echo htmlspecialchars($content['doctor_specialty']); ?></small>
                                </td>
                                <td><?php echo ucfirst($content['category']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $content['status']; ?>">
                                        <?php echo strtoupper($content['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $content['views']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($content['created_at'])); ?></td>
                                <td>
                                    <button class="action-btn btn-view" onclick="viewContent(<?php echo $content['id']; ?>)">
                                        👁️ View
                                    </button>
                                    <?php if ($content['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="content_id" value="<?php echo $content['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="action-btn btn-approve">
                                                ✓ Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="action-btn btn-reject">
                                                ✗ Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #6b7280; padding: 40px;">No educational content available.</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- View Content Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        const contentData = <?php 
            $all_contents->data_seek(0);
            $contents_array = [];
            while ($row = $all_contents->fetch_assoc()) {
                $contents_array[] = $row;
            }
            echo json_encode($contents_array); 
        ?>;

        function viewContent(contentId) {
            const content = contentData.find(c => c.id == contentId);
            if (!content) return;

            document.getElementById('modalTitle').innerHTML = content.icon + ' ' + content.title;
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="detail-row">
                    <div class="detail-label">Doctor:</div>
                    <div class="detail-value">Dr. ${content.doctor_name}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Specialty:</div>
                    <div class="detail-value">${content.doctor_specialty}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Qualification:</div>
                    <div class="detail-value">${content.doctor_qualification}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Category:</div>
                    <div class="detail-value">${content.category.charAt(0).toUpperCase() + content.category.slice(1)}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Difficulty:</div>
                    <div class="detail-value">${content.difficulty}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Lessons:</div>
                    <div class="detail-value">${content.lesson_count}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">
                        <span class="status-badge status-${content.status}">${content.status.toUpperCase()}</span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Views:</div>
                    <div class="detail-value">${content.views}</div>
                </div>
                <div style="margin-top: 25px;">
                    <h4 style="margin-bottom: 10px;">Description:</h4>
                    <p style="color: #4b5563; line-height: 1.6;">${content.description}</p>
                </div>
                <div style="margin-top: 25px;">
                    <h4 style="margin-bottom: 10px;">Full Content:</h4>
                    <div class="content-preview">${content.content}</div>
                </div>
            `;

            document.getElementById('viewModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>