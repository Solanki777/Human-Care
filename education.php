<?php 
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Education - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
</head>
<body>
   <?php include 'includes/sidebar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>📚 Health Education Center</h1>
            <p>Learn about health, wellness, and medical topics</p>
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
            </div>

            <!-- Learning Cards Grid -->
            <div class="learning-grid">
                <div class="learning-card" data-category="prevention">
                    <div class="learning-icon">🥇</div>
                    <h3>First Aid Basics</h3>
                    <p>Essential first aid techniques for emergencies and how to respond in critical situations</p>
                    <div class="course-info">
                        <span class="course-count">12 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="nutrition">
                    <div class="learning-icon">🥗</div>
                    <h3>Nutrition & Diet</h3>
                    <p>Learn about healthy eating, balanced diets, and meal planning for better health</p>
                    <div class="course-info">
                        <span class="course-count">18 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="mental">
                    <div class="learning-icon">🧠</div>
                    <h3>Mental Wellness</h3>
                    <p>Stress management, mental health awareness, and emotional wellbeing strategies</p>
                    <div class="course-info">
                        <span class="course-count">15 Free Lessons</span>
                        <span class="difficulty">Intermediate</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="fitness">
                    <div class="learning-icon">💪</div>
                    <h3>Fitness & Exercise</h3>
                    <p>Workout routines, fitness tips, and how to maintain an active lifestyle</p>
                    <div class="course-info">
                        <span class="course-count">20 Free Lessons</span>
                        <span class="difficulty">All Levels</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="prevention">
                    <div class="learning-icon">🦠</div>
                    <h3>Disease Prevention</h3>
                    <p>Understanding common diseases, prevention methods, and health screening importance</p>
                    <div class="course-info">
                        <span class="course-count">25 Free Lessons</span>
                        <span class="difficulty">Intermediate</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="nutrition">
                    <div class="learning-icon">👶</div>
                    <h3>Child Care</h3>
                    <p>Essential parenting information, child health, and developmental milestones</p>
                    <div class="course-info">
                        <span class="course-count">16 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="prevention">
                    <div class="learning-icon">💉</div>
                    <h3>Vaccination Guide</h3>
                    <p>Complete guide to vaccinations for all ages, schedules, and importance</p>
                    <div class="course-info">
                        <span class="course-count">10 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="fitness">
                    <div class="learning-icon">🧘</div>
                    <h3>Yoga & Meditation</h3>
                    <p>Yoga poses, breathing exercises, and meditation techniques for relaxation</p>
                    <div class="course-info">
                        <span class="course-count">22 Free Lessons</span>
                        <span class="difficulty">All Levels</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
                </div>

                <div class="learning-card" data-category="nutrition">
                    <div class="learning-icon">🍎</div>
                    <h3>Healthy Lifestyle</h3>
                    <p>Tips for maintaining a healthy lifestyle, sleep hygiene, and daily habits</p>
                    <div class="course-info">
                        <span class="course-count">14 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Coming Soon! Full course will be available.')">Start Learning</button>
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

    <script src="scripts/main.js"></script>
</body>
</html>