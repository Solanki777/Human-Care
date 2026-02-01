<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Human Care</title>
    <link rel="stylesheet" href="styles/main.css">
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>💬 Contact Us & Support</h1>
            <p>We're here to help you 24/7</p>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content-section">
        <div class="container">
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
                    <button class="btn-primary" onclick="window.location.href='mailto:support@humancare.com'">Send
                        Email</button>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">💬</div>
                    <h3>Live Chat</h3>
                    <p>Chat with our support team instantly</p>
                    <span class="contact-link">Available 9 AM - 9 PM</span>
                    <button class="btn-primary" onclick="alert('Chat feature coming soon!')">Start Chat</button>
                </div>

                <div class="contact-card">
                    <div class="contact-icon">📍</div>
                    <h3>Visit Us</h3>
                    <p>Main office location</p>
                    <span class="contact-link">Rajkot, Gujarat, India</span>
                    <button class="btn-primary" onclick="alert('Map navigation coming soon!')">Get Directions</button>
                </div>
            </div>

            <!-- Contact Form Section -->
            <div class="form-section">
                <h2>Send Us a Message</h2>
                <form class="contact-form" onsubmit="return handleContactForm(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Your Name *</label>
                            <input type="text" id="name" name="name" required placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required placeholder="your@email.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="+91 1234567890">
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Select Subject</option>
                                <option value="general">General Inquiry</option>
                                <option value="appointment">Appointment</option>
                                <option value="emergency">Emergency</option>
                                <option value="feedback">Feedback</option>
                                <option value="complaint">Complaint</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" rows="6" required
                            placeholder="Type your message here..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary btn-large">Send Message</button>
                </form>
            </div>

            <!-- FAQ Section -->
            <div class="faq-section">
                <h2>Frequently Asked Questions</h2>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <h4>How do I book an appointment?</h4>
                            <span class="faq-icon">+</span>
                        </div>
                        <div class="faq-answer">
                            <p>You can book an appointment by logging into your account, visiting the "Our Doctors"
                                page, and clicking on "Book Appointment" for your preferred doctor.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <h4>What are your operating hours?</h4>
                            <span class="faq-icon">+</span>
                        </div>
                        <div class="faq-answer">
                            <p>Our emergency services are available 24/7. Regular consultation hours are 9 AM to 9 PM on
                                all days.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <h4>Do you accept insurance?</h4>
                            <span class="faq-icon">+</span>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, we accept most major health insurance plans. Please contact us with your insurance
                                details for verification.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <h4>How can I access my medical records?</h4>
                            <span class="faq-icon">+</span>
                        </div>
                        <div class="faq-answer">
                            <p>After logging in, you can access your medical records from your dashboard. All your
                                reports and prescriptions are stored securely.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <h4>Is online consultation available?</h4>
                            <span class="faq-icon">+</span>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, we offer online video consultations with our doctors. This feature is available for
                                registered users.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Notice -->
            <div class="emergency-notice">
                <h3>🚨 In Case of Emergency</h3>
                <p>For immediate medical emergencies, please call our emergency hotline or visit the nearest emergency
                    room.</p>
                <a href="tel:+911234567890" class="emergency-btn">📞 Call Emergency: +91 1234-567890</a>
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