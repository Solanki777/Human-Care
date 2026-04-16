<?php
require_once 'includes/session.php';

// -------------------------------------------------------
// LOGIN GUARD — Only logged-in users/patients can view
// -------------------------------------------------------
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_type']);

// Connect to admin database to fetch approved content
$admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
if ($admin_conn->connect_error) {
    die("Connection failed: " . $admin_conn->connect_error);
}

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
    $stmt = $admin_conn->prepare("
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

$admin_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Education - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
    <style>
        /* Doctor badge & card styles */
        .doctor-badge-small {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .learning-card { position: relative; overflow: visible; }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
        }

        .doctor-avatar {
            width: 35px; height: 35px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px;
        }

        .doctor-details { flex: 1; }
        .doctor-name { font-weight: 600; color: #1f2937; margin-bottom: 2px; }
        .doctor-specialty { font-size: 12px; color: #6b7280; }

        .view-count {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; color: #6b7280; margin-left: auto;
        }

        /* ---- Login wall ---- */
        .login-wall {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            max-width: 500px;
            margin: 40px auto;
        }
        .login-wall .lock-icon { font-size: 60px; margin-bottom: 15px; }
        .login-wall h2 { color: #1f2937; margin-bottom: 10px; }
        .login-wall p  { color: #6b7280; margin-bottom: 25px; line-height: 1.6; }
        .login-wall-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-login-wall {
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-login-wall.primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        .btn-login-wall.primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-login-wall.secondary {
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }
        .btn-login-wall.secondary:hover { background: #eff6ff; }

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
            width: 90%; max-width: 800px;
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
        .close {
            color: white; font-size: 35px; font-weight: bold;
            cursor: pointer; border: none; background: none; line-height: 1;
        }
        .close:hover { opacity: 0.8; }
        .modal-body { padding: 30px; }

        .modal-doctor-info {
            background: #f9fafb; padding: 20px; border-radius: 10px;
            margin-bottom: 25px; display: flex; align-items: center; gap: 15px;
        }
        .modal-doctor-avatar {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 24px;
        }
        .modal-doctor-details h3 { margin: 0 0 5px 0; color: #1f2937; }
        .modal-doctor-details p  { margin: 0; color: #6b7280; font-size: 14px; }

        .edu-content-section { margin-bottom: 25px; }
        .edu-content-section h3 {
            color: #1f2937; margin-bottom: 15px;
            padding-bottom: 10px; border-bottom: 2px solid #e5e7eb;
        }
        .content-text { color: #4b5563; line-height: 1.8; white-space: pre-wrap; }

        .content-meta-tags { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .meta-tag {
            background: #eff6ff; color: #1e40af;
            padding: 6px 14px; border-radius: 20px; font-size: 13px;
        }

        .no-content-message { text-align: center; padding: 60px 20px; color: #6b7280; }
        .no-content-message h3 { color: #1f2937; margin-bottom: 10px; }

        /* Logged-in notice bar */
        .logged-in-notice {
            background: #d1fae5;
            color: #065f46;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Human Care</h3>
                    <p>Your health, our priority</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="hospitals.php">Hospitals</a></li>
                        <li><a href="doctors.php">Doctors</a></li>
                        <li><a href="education.php">Education</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>📞 +91 1234-567890</p>
                    <p>📧 info@humancare.com</p>
                    <p>📍 Rajkot, Gujarat, India</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Human Care. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <?php if ($is_logged_in): ?>
    <script>
        const contentData = <?php echo json_encode($content_by_category['all']); ?>;

        // Category filtering
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const category = this.getAttribute('data-category');
                document.querySelectorAll('.learning-card').forEach(card => {
                    card.style.display = (category === 'all' || card.getAttribute('data-category') === category)
                        ? 'block' : 'none';
                });
            });
        });

        function openModal(contentId) {
            const content = contentData.find(c => c.id == contentId);
            if (!content) return;

            // Update view count silently
            fetch('update_view_count.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'content_id=' + contentId
            });

            // Doctor initials
            const nameParts = content.doctor_name.split(' ');
            let initials = '';
            nameParts.forEach(p => { if (p) initials += p[0].toUpperCase(); });

            document.getElementById('modalTitle').innerHTML         = content.icon + ' ' + content.title;
            document.getElementById('modalDoctorAvatar').textContent = initials;
            document.getElementById('modalDoctorName').textContent   = 'Dr. ' + content.doctor_name;
            document.getElementById('modalDoctorSpecialty').textContent = content.doctor_specialty + ' • ' + content.doctor_qualification;
            document.getElementById('modalDescription').textContent  = content.description;
            document.getElementById('modalContent').textContent      = content.content;

            document.getElementById('modalMetaTags').innerHTML = `
                <span class="meta-tag">📁 ${content.category.charAt(0).toUpperCase() + content.category.slice(1)}</span>
                <span class="meta-tag">📊 ${content.difficulty}</span>
                <span class="meta-tag">📚 ${content.lesson_count} Lessons</span>
                <span class="meta-tag">👁️ ${parseInt(content.views) + 1} Views</span>
            `;

            document.getElementById('contentModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('contentModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(e) {
            if (e.target === document.getElementById('contentModal')) closeModal();
        };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
    <?php endif; ?>
    <script src="scripts/main.js"></script>
</body>
</html>