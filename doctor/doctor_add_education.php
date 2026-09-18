<?php
session_start();

require_once __DIR__ . '/../classes/Database.php';

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}
$active_page = 'education';

// Get doctor info from doctors database
$doctors_conn = Database::getConnection('doctors');

$doctor_id = $_SESSION['user_id'];
$stmt = $doctors_conn->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();
$doctors_conn->close();

if (!$doctor) {
    header("Location: login.php");
    exit();
}

$doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];

// Connect to admin database (where educational_content lives)
$admin_conn = Database::getConnection('admin');

// Handle form submission
$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']);
    $category     = $_POST['category'];
    $description  = trim($_POST['description']);
    $content      = trim($_POST['content']);
    $difficulty   = $_POST['difficulty'];
    $lesson_count = intval($_POST['lesson_count']);
    $icon         = $_POST['icon'];

    if (empty($title) || empty($description) || empty($content) || empty($category)) {
        $error_message = "Please fill in all required fields.";
    } else {
        $stmt = $admin_conn->prepare("
            INSERT INTO educational_content 
            (doctor_id, doctor_name, doctor_specialty, doctor_qualification, title, category, description, content, difficulty, lesson_count, icon, status, views, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
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
            $success_message = "Your educational content has been submitted for admin review. It will become visible to users once approved.";
            $_POST = []; // clear form
        } else {
            $error_message = "Error submitting content: " . $admin_conn->error . ". Please try again.";
        }
        $stmt->close();
    }
}

// Get this doctor's submitted content (all statuses)
$stmt = $admin_conn->prepare("
    SELECT * FROM educational_content 
    WHERE doctor_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$doctor_contents = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Educational Content - Human Care</title>
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/doc_add_edu.css">
    
</head>

<body>
    <?php include 'includes/doctor_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Add Content Form -->
        <div class="content-form-container">
            <h3 class="section-title">📝 Create New Educational Content</h3>
            <p style="color:#6b7280;margin-bottom:20px;font-size:14px;">
                ℹ️ Submitted content will be reviewed by admin. Once <strong>approved</strong>, it will be visible to all logged-in users and patients.
            </p>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control"
                        placeholder="e.g., Understanding Diabetes Management"
                        value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="prevention" <?php echo (isset($_POST['category']) && $_POST['category'] == 'prevention') ? 'selected' : ''; ?>>Disease Prevention</option>
                            <option value="nutrition"  <?php echo (isset($_POST['category']) && $_POST['category'] == 'nutrition')  ? 'selected' : ''; ?>>Nutrition & Diet</option>
                            <option value="fitness"    <?php echo (isset($_POST['category']) && $_POST['category'] == 'fitness')    ? 'selected' : ''; ?>>Fitness & Exercise</option>
                            <option value="mental"     <?php echo (isset($_POST['category']) && $_POST['category'] == 'mental')     ? 'selected' : ''; ?>>Mental Health</option>
                            <option value="general"    <?php echo (isset($_POST['category']) && $_POST['category'] == 'general')    ? 'selected' : ''; ?>>General Health</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Difficulty Level <span class="required">*</span></label>
                        <select name="difficulty" class="form-control" required>
                            <option value="Beginner"     <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Beginner')     ? 'selected' : ''; ?>>Beginner</option>
                            <option value="Intermediate" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="Advanced"     <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Advanced')     ? 'selected' : ''; ?>>Advanced</option>
                            <option value="All Levels"   <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'All Levels')   ? 'selected' : ''; ?>>All Levels</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Number of Lessons</label>
                        <input type="number" name="lesson_count" class="form-control"
                            value="<?php echo isset($_POST['lesson_count']) ? intval($_POST['lesson_count']) : '10'; ?>"
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
                    <div class="content-card status-<?php echo $content['status']; ?>">
                        <div class="content-header">
                            <div class="content-title">
                                <span><?php echo $content['icon']; ?></span>
                                <?php echo htmlspecialchars($content['title']); ?>
                            </div>
                            <span class="status-badge status-<?php echo $content['status']; ?>">
                                <?php echo strtoupper($content['status']); ?>
                            </span>
                        </div>

                        <!-- Status explanation note -->
                        <?php if ($content['status'] === 'pending'): ?>
                            <div class="status-note pending">⏳ Pending — Not yet visible to users.</div>
                        <?php elseif ($content['status'] === 'approved'): ?>
                            <div class="status-note approved">✅ Approved — Visible to all logged-in users and patients.</div>
                        <?php elseif ($content['status'] === 'rejected'): ?>
                            <div class="status-note rejected">❌ Rejected — Not visible. You may submit revised content.</div>
                        <?php endif; ?>

                        <p style="color:#6b7280;margin:10px 0;">
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
                    <p>You haven't submitted any educational content yet. Fill out the form above to get started!</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function selectIcon(element, icon) {
            document.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedIcon').value = icon;
        }
    </script>
</body>
</html>
<?php $admin_conn->close(); ?>