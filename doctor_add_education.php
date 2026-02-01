<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

// Get doctor info from doctors database
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
if ($doctors_conn->connect_error) {
    die("Connection failed: " . $doctors_conn->connect_error);
}

$doctor_id = $_SESSION['user_id'];
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

// Connect to admin database
$admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
if ($admin_conn->connect_error) {
    die("Connection failed: " . $admin_conn->connect_error);
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $content = trim($_POST['content']);
    $difficulty = $_POST['difficulty'];
    $lesson_count = intval($_POST['lesson_count']);
    $icon = $_POST['icon'];
    
    if (empty($title) || empty($description) || empty($content)) {
        $error_message = "Please fill in all required fields.";
    } else {
        $stmt = $admin_conn->prepare("
            INSERT INTO educational_content 
            (doctor_id, doctor_name, doctor_specialty, doctor_qualification, title, category, description, content, difficulty, lesson_count, icon, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->bind_param(
            "issssssssss",
            $doctor_id,
            $doctor_name,
            $doctor['specialty'],
            $doctor['qualification'],
            $title,
            $category,
            $description,
            $content,
            $difficulty,
            $lesson_count,
            $icon
        );
        
        if ($stmt->execute()) {
            $success_message = "Educational content submitted successfully! It will be visible once approved by admin.";
            // Clear form
            $_POST = array();
        } else {
            $error_message = "Error submitting content. Please try again.";
        }
        $stmt->close();
    }
}

// Get doctor's educational content
$stmt = $admin_conn->prepare("
    SELECT * FROM educational_content 
    WHERE doctor_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor_contents = $stmt->get_result();
$stmt->close();

$doctors_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Educational Content - Human Care</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        .content-form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .content-textarea {
            min-height: 200px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .icon-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .icon-option {
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .icon-option:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .icon-option.selected {
            border-color: #3b82f6;
            background: #dbeafe;
        }

        .submit-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }

        .my-content-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .content-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #3b82f6;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .content-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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

        .content-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
            font-size: 13px;
            color: #6b7280;
        }

        .content-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .no-content {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .section-title {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>🏥 Human Care</h2>
            <button class="close-btn" onclick="toggleSidebar()">×</button>
        </div>
        <nav class="sidebar-nav">
            <a href="doctor_dashboard.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="doctor_add_education.php" class="nav-item active">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Add Education Content</span>
            </a>
            <a href="doctor_profile.php" class="nav-item">
                <span class="nav-icon">👤</span>
                <span class="nav-text">My Profile</span>
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
        <h1>Add Educational Content</h1>
        <div class="user-info">
            <span class="user-name">Dr. <?php echo htmlspecialchars($doctor['first_name']); ?></span>
            <span class="doctor-badge">DOCTOR</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">✓ <?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">✗ <?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Add Content Form -->
        <div class="content-form-container">
            <h3 class="section-title">📝 Create New Educational Content</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" 
                           placeholder="e.g., Understanding Diabetes Management" 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                           required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="prevention" <?php echo (isset($_POST['category']) && $_POST['category'] == 'prevention') ? 'selected' : ''; ?>>Disease Prevention</option>
                            <option value="nutrition" <?php echo (isset($_POST['category']) && $_POST['category'] == 'nutrition') ? 'selected' : ''; ?>>Nutrition & Diet</option>
                            <option value="fitness" <?php echo (isset($_POST['category']) && $_POST['category'] == 'fitness') ? 'selected' : ''; ?>>Fitness & Exercise</option>
                            <option value="mental" <?php echo (isset($_POST['category']) && $_POST['category'] == 'mental') ? 'selected' : ''; ?>>Mental Health</option>
                            <option value="general" <?php echo (isset($_POST['category']) && $_POST['category'] == 'general') ? 'selected' : ''; ?>>General Health</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Difficulty Level <span class="required">*</span></label>
                        <select name="difficulty" class="form-control" required>
                            <option value="Beginner" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Beginner') ? 'selected' : ''; ?>>Beginner</option>
                            <option value="Intermediate" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="Advanced" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Advanced') ? 'selected' : ''; ?>>Advanced</option>
                            <option value="All Levels" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'All Levels') ? 'selected' : ''; ?>>All Levels</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Number of Lessons</label>
                        <input type="number" name="lesson_count" class="form-control" 
                               value="<?php echo isset($_POST['lesson_count']) ? $_POST['lesson_count'] : '10'; ?>" 
                               min="1" max="100">
                    </div>
                </div>

                <div class="form-group">
                    <label>Short Description <span class="required">*</span></label>
                    <textarea name="description" class="form-control" 
                              placeholder="Brief overview of what learners will gain from this content..." 
                              required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Full Content <span class="required">*</span></label>
                    <textarea name="content" class="form-control content-textarea" 
                              placeholder="Detailed educational content including lessons, tips, and important information..." 
                              required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Select Icon</label>
                    <input type="hidden" name="icon" id="selectedIcon" value="📚">
                    <div class="icon-selector">
                        <div class="icon-option selected" onclick="selectIcon(this, '📚')">📚</div>
                        <div class="icon-option" onclick="selectIcon(this, '🏥')">🏥</div>
                        <div class="icon-option" onclick="selectIcon(this, '💊')">💊</div>
                        <div class="icon-option" onclick="selectIcon(this, '🩺')">🩺</div>
                        <div class="icon-option" onclick="selectIcon(this, '❤️')">❤️</div>
                        <div class="icon-option" onclick="selectIcon(this, '🧠')">🧠</div>
                        <div class="icon-option" onclick="selectIcon(this, '💪')">💪</div>
                        <div class="icon-option" onclick="selectIcon(this, '🥗')">🥗</div>
                        <div class="icon-option" onclick="selectIcon(this, '🏃')">🏃</div>
                        <div class="icon-option" onclick="selectIcon(this, '🧘')">🧘</div>
                        <div class="icon-option" onclick="selectIcon(this, '💉')">💉</div>
                        <div class="icon-option" onclick="selectIcon(this, '🦷')">🦷</div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">📤 Submit Content for Review</button>
            </form>
        </div>

        <!-- My Content Section -->
        <div class="my-content-section">
            <h3 class="section-title">📋 My Educational Content</h3>
            <?php if ($doctor_contents->num_rows > 0): ?>
                <?php while ($content = $doctor_contents->fetch_assoc()): ?>
                    <div class="content-card">
                        <div class="content-header">
                            <div class="content-title">
                                <span><?php echo $content['icon']; ?></span>
                                <?php echo htmlspecialchars($content['title']); ?>
                            </div>
                            <span class="status-badge status-<?php echo $content['status']; ?>">
                                <?php echo strtoupper($content['status']); ?>
                            </span>
                        </div>
                        <p style="color: #6b7280; margin: 10px 0;">
                            <?php echo htmlspecialchars(substr($content['description'], 0, 150)) . '...'; ?>
                        </p>
                        <div class="content-meta">
                            <span>📁 <?php echo ucfirst($content['category']); ?></span>
                            <span>📊 <?php echo $content['difficulty']; ?></span>
                            <span>📚 <?php echo $content['lesson_count']; ?> Lessons</span>
                            <span>👁️ <?php echo $content['views']; ?> Views</span>
                            <span>📅 <?php echo date('M d, Y', strtotime($content['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-content">
                    <h3>📭 No Content Yet</h3>
                    <p>You haven't created any educational content yet. Start by filling out the form above!</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function selectIcon(element, icon) {
            // Remove selected class from all icons
            document.querySelectorAll('.icon-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked icon
            element.classList.add('selected');
            
            // Update hidden input
            document.getElementById('selectedIcon').value = icon;
        }
    </script>
</body>
</html>
<?php $admin_conn->close(); ?>