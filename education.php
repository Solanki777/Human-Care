<?php
require_once 'includes/session.php';

// Connect to admin database
$admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
if ($admin_conn->connect_error) {
    die("Connection failed: " . $admin_conn->connect_error);
}

// Get approved educational content
$stmt = $admin_conn->prepare("
    SELECT * FROM educational_content 
    WHERE status = 'approved' 
    ORDER BY created_at DESC
");
$stmt->execute();
$educational_contents = $stmt->get_result();
$stmt->close();

// Organize content by category
$content_by_category = [
    'all' => [],
    'prevention' => [],
    'nutrition' => [],
    'fitness' => [],
    'mental' => [],
    'general' => []
];

while ($content = $educational_contents->fetch_assoc()) {
    $content_by_category['all'][] = $content;
    $content_by_category[$content['category']][] = $content;
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

        .learning-card {
            position: relative;
            overflow: visible;
        }

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
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .doctor-details {
            flex: 1;
        }

        doctor-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
        }

        .doctor-specialty {
            font-size: 12px;
            color: #6b7280;
        }

        .view-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: #6b7280;
            margin-left: auto;
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
            max-width: 800px;
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

        .modal-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-doctor-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .modal-doctor-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 24px;
        }

        .modal-doctor-details h3 {
            margin: 0 0 5px 0;
            color: #1f2937;
        }

        .modal-doctor-details p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .content-section {
            margin-bottom: 25px;
        }

        .content-section h3 {
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        .content-text {
            color: #4b5563;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .content-meta-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .meta-tag {
            background: #eff6ff;
            color: #1e40af;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .no-content-message {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .no-content-message h3 {
            color: #1f2937;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
   <?php include 'includes/sidebar.php'; ?>

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
            <!-- Categories -->
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
                        // Get doctor initials
                        $name_parts = explode(' ', $content['doctor_name']);
                        $initials = '';
                        foreach ($name_parts as $part) {
                            if (!empty($part)) {
                                $initials .= strtoupper($part[0]);
                            }
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
                        <h3>📭 No Educational Content Available</h3>
                        <p>Educational content from doctors will appear here once they are approved.</p>
                    </div>
                <?php endif; ?>

                <!-- Original static cards as fallback examples -->
                <div class="learning-card" data-category="prevention" style="opacity: 0.6;">
                    <div class="learning-icon">🥇</div>
                    <h3>First Aid Basics</h3>
                    <p>Essential first aid techniques for emergencies and how to respond in critical situations</p>
                    <div class="course-info">
                        <span class="course-count">12 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Sample content - Coming Soon!')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="nutrition" style="opacity: 0.6;">
                    <div class="learning-icon">🥗</div>
                    <h3>Nutrition & Diet</h3>
                    <p>Learn about healthy eating, balanced diets, and meal planning for better health</p>
                    <div class="course-info">
                        <span class="course-count">18 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Sample content - Coming Soon!')">Start Learning</button>
                </div>
            </div>

            <!-- Health Tips Section -->
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
        </div>
    </section>

    <!-- Content Modal -->
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
                
                <div class="content-section">
                    <h3>📝 Overview</h3>
                    <p id="modalDescription"></p>
                </div>
                
                <div class="content-section">
                    <h3>📚 Full Content</h3>
                    <div class="content-text" id="modalContent"></div>
                </div>
                
                <div class="content-meta-tags" id="modalMetaTags"></div>
            </div>
        </div>
    </div>

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

    <script>
        // Content data for modal
        const contentData = <?php echo json_encode($content_by_category['all']); ?>;

        // Category filtering
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.getAttribute('data-category');
                
                // Filter cards
                document.querySelectorAll('.learning-card').forEach(card => {
                    if (category === 'all' || card.getAttribute('data-category') === category) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        function openModal(contentId) {
            const content = contentData.find(c => c.id == contentId);
            if (!content) return;

            // Update view count
            fetch('update_view_count.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'content_id=' + contentId
            });

            // Get doctor initials
            const nameParts = content.doctor_name.split(' ');
            let initials = '';
            nameParts.forEach(part => {
                if (part) initials += part[0].toUpperCase();
            });

            // Populate modal
            document.getElementById('modalTitle').innerHTML = content.icon + ' ' + content.title;
            document.getElementById('modalDoctorAvatar').textContent = initials;
            document.getElementById('modalDoctorName').textContent = 'Dr. ' + content.doctor_name;
            document.getElementById('modalDoctorSpecialty').textContent = content.doctor_specialty + ' • ' + content.doctor_qualification;
            document.getElementById('modalDescription').textContent = content.description;
            document.getElementById('modalContent').textContent = content.content;
            
            // Meta tags
            const metaTags = `
                <span class="meta-tag">📁 ${content.category.charAt(0).toUpperCase() + content.category.slice(1)}</span>
                <span class="meta-tag">📊 ${content.difficulty}</span>
                <span class="meta-tag">📚 ${content.lesson_count} Lessons</span>
                <span class="meta-tag">👁️ ${parseInt(content.views) + 1} Views</span>
            `;
            document.getElementById('modalMetaTags').innerHTML = metaTags;

            // Show modal
            document.getElementById('contentModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('contentModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('contentModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
    <script src="scripts/main.js"></script>
</body>
</html>