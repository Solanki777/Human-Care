<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
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

    // Only allow known actions — prevents undefined $stmt if action is tampered
    if (!in_array($action, ['approve', 'reject'])) {
        $_SESSION['msg'] = "Invalid action.";
        $_SESSION['msg_type'] = 'error';
        header("Location: admin_manage_education.php");
        exit();
    }

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    // REMOVED updated_at = NOW() as it causes crashes if column is missing
    $stmt = $admin_conn->prepare("UPDATE educational_content SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $content_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Log activity
        $admin_id  = $_SESSION['admin_id'] ?? 0;
        $ip        = $_SERVER['REMOTE_ADDR'];
        $desc_text = ($action === 'approve')
            ? "Approved educational content ID $content_id — now visible to users"
            : "Rejected educational content ID $content_id — hidden from users";
        $log_stmt = $admin_conn->prepare(
            "INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, ?, ?, ?)"
        );
        $log_action = "education_$action";
        $log_stmt->bind_param("isss", $admin_id, $log_action, $desc_text, $ip);
        $log_stmt->execute();
        $log_stmt->close();

        $_SESSION['msg']      = ($action === 'approve')
            ? "✅ Content approved — it is now visible to all logged-in users"
            : "❌ Content rejected — hidden from users";
        $_SESSION['msg_type'] = ($action === 'approve') ? 'success' : 'error';
    } else {
        // More descriptive error for debugging
        $_SESSION['msg']      = "Database error: " . $admin_conn->error;
        $_SESSION['msg_type'] = 'error';
    }
    $stmt->close();
    header("Location: admin_manage_education.php");
    exit();
}

// Get all educational content
$result = $admin_conn->query("
    SELECT * FROM educational_content 
    ORDER BY 
        CASE status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        created_at DESC
");
$all_contents = $result;

// Get statistics
$pending_count  = $admin_conn->query("SELECT COUNT(*) as c FROM educational_content WHERE status = 'pending'")->fetch_assoc()['c'];
$approved_count = $admin_conn->query("SELECT COUNT(*) as c FROM educational_content WHERE status = 'approved'")->fetch_assoc()['c'];
$rejected_count = $admin_conn->query("SELECT COUNT(*) as c FROM educational_content WHERE status = 'rejected'")->fetch_assoc()['c'];
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            vertical-align: middle;
        }

        .content-table tr:hover { background: #f9fafb; }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending  { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 4px;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-approve { background: #10b981; color: white; }
        .btn-approve:hover { background: #059669; }

        .btn-reject  { background: #ef4444; color: white; }
        .btn-reject:hover  { background: #dc2626; }

        .btn-view    { background: #3b82f6; color: white; }
        .btn-view:hover    { background: #2563eb; }

        .content-title-cell { max-width: 280px; }
        .content-icon { font-size: 22px; margin-right: 6px; }

        /* Alert messages */
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; border-left: 4px solid; }
        .alert-success { background: #d1fae5; color: #065f46; border-color: #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-color: #ef4444; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
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

        .modal-header h2 { margin: 0; display: flex; align-items: center; gap: 10px; }

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

        .detail-label { font-weight: 600; color: #6b7280; }
        .detail-value { color: #1f2937; }

        .content-preview {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-top: 10px;
            white-space: pre-wrap;
            line-height: 1.6;
            font-size: 14px;
            color: #374151;
        }

        .close {
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
            line-height: 1;
        }

        .modal-action-bar {
            padding: 20px 30px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            border-radius: 0 0 15px 15px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.2s;
        }

        .filter-btn.active, .filter-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            background: #eff6ff;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            ADMIN PANEL
        </div>

        <div class="user-profile">
            <div class="user-avatar">👨‍💼</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
                <span class="admin-badge">ADMINISTRATOR</span>
            </div>
        </div>

        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_dashboard.php">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_doctors.php">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Manage Doctors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_patients.php">
                        <span class="nav-icon">👥</span>
                        <span>Manage Patients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav" href="admin_appointments.php">
                        <span class="nav-icon">📅</span>
                        <span>Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link admin-nav active" href="admin_manage_education.php">
                        <span class="nav-icon">📚</span>
                        <span>Approve Education</span>
                        <?php if ($pending_count > 0): ?>
                            <span class="pending-badge" style="background:#fee2e2;color:#991b1b;"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </nav>

        <form method="post" action="admin_logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Alert Message -->
        <?php if (isset($_SESSION['msg'])): ?>
            <div class="alert alert-<?php echo ($_SESSION['msg_type'] === 'success') ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($_SESSION['msg']); ?>
            </div>
            <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card pending">
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">⏳ Pending Review</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-number"><?php echo $approved_count; ?></div>
                <div class="stat-label">✅ Approved (Visible to Users)</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-label">❌ Rejected (Hidden)</div>
            </div>
        </div>

        <!-- Content Table -->
        <div class="content-table-container">
            <h3 style="margin-bottom: 15px;">All Educational Content</h3>

            <!-- Filter Buttons -->
            <div class="filter-bar">
                <button class="filter-btn active" onclick="filterTable('all', this)">All</button>
                <button class="filter-btn" onclick="filterTable('pending', this)">⏳ Pending</button>
                <button class="filter-btn" onclick="filterTable('approved', this)">✅ Approved</button>
                <button class="filter-btn" onclick="filterTable('rejected', this)">❌ Rejected</button>
            </div>

            <?php if ($all_contents->num_rows > 0): ?>
                <table class="content-table" id="contentTable">
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
                            <tr data-status="<?php echo $content['status']; ?>">
                                <td class="content-title-cell">
                                    <span class="content-icon"><?php echo $content['icon']; ?></span>
                                    <strong><?php echo htmlspecialchars($content['title']); ?></strong>
                                </td>
                                <td>
                                    Dr. <?php echo htmlspecialchars($content['doctor_name']); ?><br>
                                    <small style="color:#6b7280;"><?php echo htmlspecialchars($content['doctor_specialty']); ?></small>
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
                                    <!-- View Button -->
                                    <button class="action-btn btn-view"
                                        onclick="viewContent(<?php echo $content['id']; ?>)">
                                        👁️ View
                                    </button>

                                    <!-- Approve Button: show if NOT already approved -->
                                    <?php if ($content['status'] !== 'approved'): ?>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirmAction('approve', '<?php echo htmlspecialchars($content['title'], ENT_QUOTES); ?>')">
                                            <input type="hidden" name="content_id" value="<?php echo $content['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="action-btn btn-approve">
                                                ✓ Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Reject Button: show if NOT already rejected -->
                                    <?php if ($content['status'] !== 'rejected'): ?>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirmAction('reject', '<?php echo htmlspecialchars($content['title'], ENT_QUOTES); ?>')">
                                            <input type="hidden" name="content_id" value="<?php echo $content['id']; ?>">
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
                <p style="text-align:center;color:#6b7280;padding:40px;">No educational content submitted yet.</p>
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
            <div class="modal-body" id="modalBody"></div>
            <!-- Modal Action Bar for quick approve/reject -->
            <div class="modal-action-bar" id="modalActionBar"></div>
        </div>
    </div>

    <script>
        // Collect all content into JS for modal use
        const contentData = <?php
            $all_contents->data_seek(0);
            $arr = [];
            while ($row = $all_contents->fetch_assoc()) { $arr[] = $row; }
            echo json_encode($arr);
        ?>;

        function confirmAction(action, title) {
            const verb = action === 'approve' ? 'APPROVE' : 'REJECT';
            const msg = action === 'approve'
                ? `✅ Approve "${title}"?\n\nThis will make the content VISIBLE to all logged-in users and patients.`
                : `❌ Reject "${title}"?\n\nThis will HIDE the content from all users.`;
            return confirm(msg);
        }

        function viewContent(contentId) {
            const c = contentData.find(x => x.id == contentId);
            if (!c) return;

            document.getElementById('modalTitle').innerHTML = c.icon + ' ' + c.title;

            document.getElementById('modalBody').innerHTML = `
                <div class="detail-row">
                    <div class="detail-label">Doctor:</div>
                    <div class="detail-value">Dr. ${c.doctor_name}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Specialty:</div>
                    <div class="detail-value">${c.doctor_specialty}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Qualification:</div>
                    <div class="detail-value">${c.doctor_qualification}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Category:</div>
                    <div class="detail-value">${c.category.charAt(0).toUpperCase() + c.category.slice(1)}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Difficulty:</div>
                    <div class="detail-value">${c.difficulty}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Lessons:</div>
                    <div class="detail-value">${c.lesson_count}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">
                        <span class="status-badge status-${c.status}">${c.status.toUpperCase()}</span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Views:</div>
                    <div class="detail-value">${c.views}</div>
                </div>
                <div style="margin-top:20px;">
                    <h4 style="margin-bottom:8px;">📝 Description:</h4>
                    <p style="color:#4b5563;line-height:1.6;">${c.description}</p>
                </div>
                <div style="margin-top:20px;">
                    <h4 style="margin-bottom:8px;">📚 Full Content:</h4>
                    <div class="content-preview">${c.content}</div>
                </div>
            `;

            // Build modal action buttons
            let actionHtml = '';
            if (c.status !== 'approved') {
                actionHtml += `
                    <form method="POST" style="display:inline;"
                        onsubmit="return confirmAction('approve', ${JSON.stringify(c.title).replace(/"/g, '&quot;')})">
                        <input type="hidden" name="content_id" value="${c.id}">
                        <button type="submit" name="action" value="approve" class="action-btn btn-approve">✓ Approve — Make Visible to Users</button>
                    </form>`;
            }
            if (c.status !== 'rejected') {
                actionHtml += `
                    <form method="POST" style="display:inline;"
                        onsubmit="return confirmAction('reject', ${JSON.stringify(c.title).replace(/"/g, '&quot;')})">
                        <input type="hidden" name="content_id" value="${c.id}">
                        <button type="submit" name="action" value="reject" class="action-btn btn-reject">✗ Reject — Hide from Users</button>
                    </form>`;
            }
            if (!actionHtml) {
                actionHtml = '<span style="color:#6b7280;font-size:13px;">No further action available.</span>';
            }
            document.getElementById('modalActionBar').innerHTML = actionHtml;

            document.getElementById('viewModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function filterTable(status, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('#contentTable tbody tr').forEach(row => {
                row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
            });
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        window.onclick = function(e) {
            if (e.target === document.getElementById('viewModal')) closeModal();
        };
    </script>
</body>
</html>
<?php $admin_conn->close(); ?>