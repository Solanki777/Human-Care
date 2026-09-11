<?php

require_once __DIR__ . '/../includes/session.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// -------------------------------------------------------
// LOGIN GUARD — Only logged-in users/patients can view
// -------------------------------------------------------
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_type']);


$conn = Database::getConnection('admin');

// Only fetch approved content — always filter by status = 'approved'
$educational_contents = null;
$content_by_category = [
    'all'        => [],
    'prevention' => [],
    'nutrition'  => [],
    'fitness'    => [],
    'mental'     => [],
    'general'    => []
];

if ($is_logged_in) {
    // Logged-in users see all approved content
    $stmt = $conn->prepare("
    SELECT * FROM educational_content 
    WHERE status = 'approved' 
    ORDER BY created_at DESC
");
    $stmt->execute();
    $educational_contents = $stmt->get_result();
    $stmt->close();

    while ($content = $educational_contents->fetch_assoc()) {
        $content_by_category['all'][] = $content;
        if (array_key_exists($content['category'], $content_by_category)) {
            $content_by_category[$content['category']][] = $content;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Health Education - Human Care</title>

    <!-- Common CSS -->
    <link rel="stylesheet" href="styles/main.css">

    <!-- Education CSS -->
    <link rel="stylesheet" href="styles/education.css">

    <!-- Sidebar CSS -->
    <link rel="stylesheet" href="styles/sidebar.css">

    <!-- Footer CSS -->
    <link rel="stylesheet" href="includes/footer.css">

</head>
<body>
    <?php $active_page = 'education'; ?>
    <?php include 'includes/public_sidebar.php'; ?>

    

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>📚 Health Education Center</h1>
            <p>Learn about health, wellness, and medical topics from verified doctors</p>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content-section">
        <div class="container">

            <?php if (!$is_logged_in): ?>
                <!-- ===== LOGIN WALL ===== -->
                <div class="login-wall">
                    <div class="lock-icon">🔒</div>
                    <h2>Login to Access Health Education</h2>
                    <p>
                        Our educational content is written and reviewed by verified doctors.<br>
                        Please <strong>log in</strong> or <strong>register</strong> to read articles, 
                        explore lessons, and improve your health knowledge.
                    </p>
                    <div class="login-wall-btns">
                        <a href="login.php" class="btn-login-wall primary">🔑 Login</a>
                        <a href="register.php" class="btn-login-wall secondary">📝 Register</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- ===== CONTENT VISIBLE TO LOGGED-IN USERS ===== -->

                <div class="logged-in-notice">
                    ✅ Welcome — you are viewing doctor-approved health education content.
                </div>

                <!-- Categories Filter -->
                <div class="education-categories">
                    <button class="category-btn active" data-category="all">All Topics</button>
                    <button class="category-btn" data-category="prevention">Prevention</button>
                    <button class="category-btn" data-category="nutrition">Nutrition</button>
                    <button class="category-btn" data-category="fitness">Fitness</button>
                    <button class="category-btn" data-category="mental">Mental Health</button>
                    <button class="category-btn" data-category="general">General</button>
                </div>

                <!-- Learning Cards Grid -->
                <div class="learning-grid">
                    <?php if (!empty($content_by_category['all'])): ?>
                        <?php foreach ($content_by_category['all'] as $content):
                            $name_parts = explode(' ', $content['doctor_name']);
                            $initials = '';
                            foreach ($name_parts as $part) {
                                if (!empty($part)) $initials .= strtoupper($part[0]);
                            }
                        ?>
                            <div class="learning-card" data-category="<?php echo htmlspecialchars($content['category']); ?>">
                                <div class="learning-icon"><?php echo $content['icon']; ?></div>
                                <h3><?php echo htmlspecialchars($content['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($content['description'], 0, 100)) . '...'; ?></p>
                                <div class="course-info">
                                    <span class="course-count"><?php echo $content['lesson_count']; ?> Lessons</span>
                                    <span class="difficulty"><?php echo $content['difficulty']; ?></span>
                                </div>

                                <div class="doctor-info">
                                    <div class="doctor-avatar"><?php echo $initials; ?></div>
                                    <div class="doctor-details">
                                        <div class="doctor-name">Dr. <?php echo htmlspecialchars($content['doctor_name']); ?></div>
                                        <div class="doctor-specialty"><?php echo htmlspecialchars($content['doctor_specialty']); ?></div>
                                    </div>
                                    <div class="view-count">
                                        <span>👁️</span>
                                        <span><?php echo $content['views']; ?></span>
                                    </div>
                                </div>

                                <button class="btn-primary" onclick="openModal(<?php echo $content['id']; ?>)">
                                    Start Learning
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-content-message">
                            <h3>📭 No Educational Content Available Yet</h3>
                            <p>Approved content from doctors will appear here. Check back soon!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Health Tips Section (always visible to logged-in users) -->
                <div class="health-tips-section">
                    <h2>Daily Health Tips</h2>
                    <div class="tips-grid">
                        <div class="tip-card">
                            <h4>💧 Stay Hydrated</h4>
                            <p>Drink at least 8 glasses of water daily to keep your body functioning properly</p>
                        </div>
                        <div class="tip-card">
                            <h4>🚶 Walk Daily</h4>
                            <p>Take a 30-minute walk every day to improve cardiovascular health</p>
                        </div>
                        <div class="tip-card">
                            <h4>😴 Sleep Well</h4>
                            <p>Get 7-8 hours of quality sleep for better physical and mental health</p>
                        </div>
                        <div class="tip-card">
                            <h4>🥦 Eat Vegetables</h4>
                            <p>Include colorful vegetables in your diet for essential vitamins and minerals</p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </section>

    <!-- Content Modal (only rendered for logged-in users) -->
    <?php if ($is_logged_in): ?>
    <div id="contentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-doctor-info">
                    <div class="modal-doctor-avatar" id="modalDoctorAvatar"></div>
                    <div class="modal-doctor-details">
                        <h3 id="modalDoctorName"></h3>
                        <p id="modalDoctorSpecialty"></p>
                    </div>
                </div>
                <div class="edu-content-section">
                    <h3>📝 Overview</h3>
                    <p id="modalDescription"></p>
                </div>
                <div class="edu-content-section">
                    <h3>📚 Full Content</h3>
                    <div class="content-text" id="modalContent"></div>
                </div>
                <div class="content-meta-tags" id="modalMetaTags"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <?php if ($is_logged_in): ?>
    <script src="js/education.js"></script>
   
    <?php endif; ?>
    <script src="js/main.js"></script>
</body>
</html>