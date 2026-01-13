<?php
session_start();

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Get user info from session
$userName = $_SESSION['user_name'];

// Connect to doctors database to get doctor list
$doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$doctors_query = $doctors_conn->query("SELECT * FROM doctors WHERE is_verified = 1 AND verification_status = 'approved' ORDER BY specialty");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Care - Dashboard</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <style>
        /* Additional styles for new sections */
        .doctors-grid, .learning-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .doctor-card, .learning-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s;
        }

        .doctor-card:hover, .learning-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .doctor-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 20px;
        }

        .specialty {
            color: #667eea;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .experience {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .qualifications {
            margin: 15px 0;
        }

        .badge {
            display: inline-block;
            background: #e0e7ff;
            color: #667eea;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin: 3px;
        }

        .doctor-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin: 15px 0;
        }

        .doctor-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .doctor-actions button {
            flex: 1;
            padding: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .learning-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }

        .course-info {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }

        .course-count {
            background: linear-gradient(135deg, #e0e7ff 0%, #f3e7ff 100%);
            color: #667eea;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .difficulty {
            color: #666;
            font-size: 13px;
            font-weight: 600;
        }

        .health-tips-section {
            margin-top: 50px;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .tip-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
        }

        .tip-card h4 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .contact-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .contact-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }

        .contact-link {
            color: #667eea;
            font-weight: 600;
            display: block;
            margin: 15px 0;
        }

        .form-section {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            min-width: 200px;
        }

        .section-title {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }

        .section-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <div class="logo-icon">❤️</div>
            HUMAN CARE
        </div>

        <!-- User profile -->
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($userName); ?></h3>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a class="nav-link active" onclick="showSection('dashboard')">
                        <span class="nav-icon">🏠</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('doctors')">
                        <span class="nav-icon">👨‍⚕️</span>
                        <span>Our Doctors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('education')">
                        <span class="nav-icon">📚</span>
                        <span>Health Education</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('contact')">
                        <span class="nav-icon">💬</span>
                        <span>Contact & Support</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('appointments')">
                        <span class="nav-icon">📅</span>
                        <span>My Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" onclick="showSection('records')">
                        <span class="nav-icon">📋</span>
                        <span>Medical Records</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Logout Button -->
        <form method="post" action="logout.php">
            <button class="logout-btn" type="submit">🚪 Logout</button>
        </form>
    </aside>

    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Dashboard Section -->
        <section id="dashboard" class="section active">
            <div class="hero-banner">
                <h2>Welcome back, <?php echo htmlspecialchars($userName); ?> 👋</h2>
                <p>Your health is our top priority. Access all your healthcare services in one place.</p>
            </div>

            <div class="cards-grid">
                <div class="card">
                    <div class="card-icon">📅</div>
                    <h3>Upcoming Appointments</h3>
                    <p>You have 2 appointments scheduled this week. Next appointment: Dr. Sarah on Oct 6</p>
                </div>
                <div class="card">
                    <div class="card-icon">📋</div>
                    <h3>Medical Records</h3>
                    <p>Access your complete medical history, test results, and prescriptions anytime</p>
                </div>
                <div class="card">
                    <div class="card-icon">💊</div>
                    <h3>Prescriptions</h3>
                    <p>3 active prescriptions. 1 refill reminder for next week</p>
                </div>
                <div class="card">
                    <div class="card-icon">📞</div>
                    <h3>24/7 Support</h3>
                    <p>Need help? Our medical team is available around the clock for consultations</p>
                </div>
            </div>
        </section>

        <!-- Doctors Section -->
        <section id="doctors" class="section hidden">
            <h2 class="section-title">👨‍⚕️ Our Expert Doctors</h2>
            <p class="section-subtitle">Book appointments with our verified healthcare professionals</p>

            <!-- Filter Section -->
            <div class="filter-section">
                <select class="filter-select" id="specialtyFilter" onchange="filterDoctors()">
                    <option value="all">All Specialties</option>
                    <option value="Cardiologist">Cardiology</option>
                    <option value="Pediatrician">Pediatrics</option>
                    <option value="Orthopedic">Orthopedics</option>
                    <option value="Dermatologist">Dermatology</option>
                    <option value="Neurologist">Neurology</option>
                    <option value="Gynecologist">Gynecology</option>
                </select>
            </div>

            <!-- Doctors Grid -->
            <div class="doctors-grid">
                <?php while ($doctor = $doctors_query->fetch_assoc()): ?>
                    <div class="doctor-card" data-specialty="<?php echo htmlspecialchars($doctor['specialty']); ?>">
                        <div class="doctor-avatar">👨‍⚕️</div>
                        <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                        <p class="specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                        <p class="experience">⭐ <?php echo $doctor['experience_years']; ?> years experience</p>
                        <div class="qualifications">
                            <span class="badge"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
                        </div>
                        <p class="doctor-description">
                            <?php echo htmlspecialchars($doctor['about'] ?? 'Expert healthcare professional'); ?>
                        </p>
                        <div class="doctor-actions">
                            <button class="btn-primary" onclick="bookAppointment(<?php echo $doctor['id']; ?>)">Book Appointment</button>
                            <button class="btn-secondary" onclick="viewProfile(<?php echo $doctor['id']; ?>)">View Profile</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- Education Section -->
        <section id="education" class="section hidden">
            <h2 class="section-title">📚 Health Education Center</h2>
            <p class="section-subtitle">Learn about health, wellness, and preventive care</p>

            <!-- Learning Cards Grid -->
            <div class="learning-grid">
                <div class="learning-card">
                    <div class="learning-icon">🥇</div>
                    <h3>First Aid Basics</h3>
                    <p>Essential first aid techniques for emergencies and how to respond in critical situations</p>
                    <div class="course-info">
                        <span class="course-count">12 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Course coming soon!')">Start Learning</button>
                </div>

                <div class="learning-card">
                    <div class="learning-icon">🥗</div>
                    <h3>Nutrition & Diet</h3>
                    <p>Learn about healthy eating, balanced diets, and meal planning for better health</p>
                    <div class="course-info">
                        <span class="course-count">18 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Course coming soon!')">Start Learning</button>
                </div>

                <div class="learning-card">
                    <div class="learning-icon">🧠</div>
                    <h3>Mental Wellness</h3>
                    <p>Stress management, mental health awareness, and emotional wellbeing strategies</p>
                    <div class="course-info">
                        <span class="course-count">15 Free Lessons</span>
                        <span class="difficulty">Intermediate</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Course coming soon!')">Start Learning</button>
                </div>

                <div class="learning-card">
                    <div class="learning-icon">💪</div>
                    <h3>Fitness & Exercise</h3>
                    <p>Workout routines, fitness tips, and how to maintain an active lifestyle</p>
                    <div class="course-info">
                        <span class="course-count">20 Free Lessons</span>
                        <span class="difficulty">All Levels</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Course coming soon!')">Start Learning</button>
                </div>

                <div class="learning-card">
                    <div class="learning-icon">🦠</div>
                    <h3>Disease Prevention</h3>
                    <p>Understanding common diseases, prevention methods, and health screening importance</p>
                    <div class="course-info">
                        <span class="course-count">25 Free Lessons</span>
                        <span class="difficulty">Intermediate</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Course coming soon!')">Start Learning</button>
                </div>

                <div class="learning-card">
                    <div class="learning-icon">👶</div>
                    <h3>Child Care</h3>
                    <p>Essential parenting information, child health, and developmental milestones</p>
                    <div class="course-info">
                        <span class="course-count">16 Free Lessons</span>
                        <span class="difficulty">Beginner</span>
                    </div>
                    <button class="btn-primary" onclick="alert('Course coming soon!')">Start Learning</button>
                </div>
            </div>

            <!-- Health Tips Section -->
            <div class="health-tips-section">
                <h3 style="font-size: 28px; color: #333; margin-bottom: 20px;">💡 Daily Health Tips</h3>
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
        </section>

        <!-- Contact Section -->
        <section id="contact" class="section hidden">
            <h2 class="section-title">💬 Contact Us & Support</h2>
            <p class="section-subtitle">We're here to help you 24/7</p>

            <!-- Contact Methods Grid -->
            <div class="contact-methods">
                <div class="contact-card">
                    <div class="contact-icon">📞</div>
                    <h3>Emergency Hotline</h3>
                    <p>24/7 emergency medical assistance</p>
                    <a href="tel:+911234567890" class="contact-link">+91 1234-567890</a>
                    <button class="btn-primary" onclick="window.location.href='tel:+911234567890'">Call Now</button>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">📧</div>
                    <h3>Email Support</h3>
                    <p>Get response within 24 hours</p>
                    <a href="mailto:support@humancare.com" class="contact-link">support@humancare.com</a>
                    <button class="btn-primary" onclick="window.location.href='mailto:support@humancare.com'">Send Email</button>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">💬</div>
                    <h3>Live Chat</h3>
                    <p>Chat with our support team</p>
                    <span class="contact-link">Available 9 AM - 9 PM</span>
                    <button class="btn-primary" onclick="alert('Chat feature coming soon!')">Start Chat</button>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">📍</div>
                    <h3>Visit Us</h3>
                    <p>Main office location</p>
                    <span class="contact-link">Rajkot, Gujarat, India</span>
                    <button class="btn-primary" onclick="alert('Map coming soon!')">Get Directions</button>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="form-section">
                <h3 style="font-size: 24px; margin-bottom: 20px;">Send Us a Message</h3>
                <form onsubmit="return handleContactForm(event)">
                    <div class="form-group">
                        <label>Your Name *</label>
                        <input type="text" required placeholder="Enter your name" value="<?php echo htmlspecialchars($userName); ?>">
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <select required>
                            <option value="">Select Subject</option>
                            <option value="general">General Inquiry</option>
                            <option value="appointment">Appointment</option>
                            <option value="feedback">Feedback</option>
                            <option value="complaint">Complaint</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message *</label>
                        <textarea rows="6" required placeholder="Type your message here..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; padding: 15px;">Send Message</button>
                </form>
            </div>
        </section>

        <!-- Appointments Section -->
        <section id="appointments" class="section hidden">
            <h2 class="section-title">📅 My Appointments</h2>
            <p class="section-subtitle">View and manage your appointments</p>
            <div class="card">
                <p>Your upcoming appointments will appear here.</p>
            </div>
        </section>

        <!-- Medical Records Section -->
        <section id="records" class="section hidden">
            <h2 class="section-title">📋 Medical Records</h2>
            <p class="section-subtitle">Access your medical history and documents</p>
            <div class="card">
                <p>Your medical records will be available here.</p>
            </div>
        </section>
    </main>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
                section.classList.add('hidden');
            });
            document.getElementById(sectionId).classList.remove('hidden');
            document.getElementById(sectionId).classList.add('active');

            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.closest('.nav-link').classList.add('active');
            
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function filterDoctors() {
            const specialty = document.getElementById('specialtyFilter').value;
            const cards = document.querySelectorAll('.doctor-card');
            
            cards.forEach(card => {
                if (specialty === 'all' || card.dataset.specialty.includes(specialty)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function bookAppointment(doctorId) {
            alert('Booking appointment with doctor ID: ' + doctorId + '\n\nAppointment booking feature coming soon!');
        }

        function viewProfile(doctorId) {
            alert('Viewing profile for doctor ID: ' + doctorId + '\n\nDoctor profile view coming soon!');
        }

        function handleContactForm(event) {
            event.preventDefault();
            alert('Thank you for your message! We will get back to you soon.');
            event.target.reset();
            return false;
        }
    </script>
</body>
</html>
<?php $doctors_conn->close(); ?>