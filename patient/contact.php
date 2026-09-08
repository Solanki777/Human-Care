
<?php
require_once __DIR__ . '/../includes/session.php';

$active_page = 'contact';

include 'includes/public_sidebar.php';
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

    <!-- =========================================
         PAGE HEADER
    ========================================== -->

    <div class="page-header">
        <div class="container">

            <h1>💬 Contact Us &amp; Support</h1>

            <p>
                We're here to help you 24/7
            </p>

        </div>
    </div>


    <!-- =========================================
         MAIN CONTENT
    ========================================== -->

    <section class="content-section">

        <div class="container">


            <!-- =====================================
                 CONTACT METHODS
            ====================================== -->

            <div class="contact-methods">


                <!-- Emergency Hotline -->

                <div class="contact-card">

                    <div class="contact-icon">
                        📞
                    </div>

                    <h3>
                        Emergency Hotline
                    </h3>

                    <p>
                        24/7 emergency medical assistance
                    </p>

                    <a
                        href="tel:+919725219106"
                        class="contact-link"
                    >
                        +91 97252 19106
                    </a>

                    <button
                        type="button"
                        class="btn-primary"
                        onclick="window.location.href='tel:+919725219106'"
                    >
                        Call Now
                    </button>

                </div>


                <!-- Email Support -->

                <div class="contact-card">

                    <div class="contact-icon">
                        📧
                    </div>

                    <h3>
                        Email Support
                    </h3>

                    <p>
                        Get response within 24 hours
                    </p>

                    <a
                        href="mailto:solankimaheshkhash7@gmail.com"
                        class="contact-link"
                    >
                        solankimaheshkhash7@gmail.com
                    </a>

                    <button
                        type="button"
                        class="btn-primary"
                        onclick="window.location.href='mailto:solankimaheshkhash7@gmail.com'"
                    >
                        Send Email
                    </button>

                </div>


                <!-- Visit Us -->

                <div class="contact-card">

                    <div class="contact-icon">
                        📍
                    </div>

                    <h3>
                        Visit Us
                    </h3>

                    <p>
                        Main office location
                    </p>

                    <span class="contact-link">
                        Fake, Fake, Fake
                    </span>

                </div>

            </div>


            <!-- =====================================
                 FAQ SECTION
            ====================================== -->

            <div class="faq-section">

                <h2>
                    Frequently Asked Questions
                </h2>


                <div class="faq-list">


                    <!-- FAQ 1 -->

                    <div class="faq-item">

                        <div
                            class="faq-question"
                            onclick="toggleFaq(this)"
                        >

                            <h4>
                                How do I book an appointment?
                            </h4>

                            <span class="faq-icon">
                                +
                            </span>

                        </div>

                        <div class="faq-answer">

                            <p>
                                You can book an appointment by logging
                                into your account, visiting the
                                "Our Doctors" page, and clicking on
                                "Book Appointment" for your preferred
                                doctor.
                            </p>

                        </div>

                    </div>


                    <!-- FAQ 2 -->

                    <div class="faq-item">

                        <div
                            class="faq-question"
                            onclick="toggleFaq(this)"
                        >

                            <h4>
                                What are your operating hours?
                            </h4>

                            <span class="faq-icon">
                                +
                            </span>

                        </div>

                        <div class="faq-answer">

                            <p>
                                Our emergency services are available
                                24/7. Regular consultation hours are
                                9 AM to 9 PM on all days.
                            </p>

                        </div>

                    </div>


                    <!-- FAQ 3 -->

                    <div class="faq-item">

                        <div
                            class="faq-question"
                            onclick="toggleFaq(this)"
                        >

                            <h4>
                                Do you accept insurance?
                            </h4>

                            <span class="faq-icon">
                                +
                            </span>

                        </div>

                        <div class="faq-answer">

                            <p>
                                Yes, we accept most major health
                                insurance plans. Please contact us
                                with your insurance details for
                                verification.
                            </p>

                        </div>

                    </div>


                    <!-- FAQ 4 -->

                    <div class="faq-item">

                        <div
                            class="faq-question"
                            onclick="toggleFaq(this)"
                        >

                            <h4>
                                How can I access my medical records?
                            </h4>

                            <span class="faq-icon">
                                +
                            </span>

                        </div>

                        <div class="faq-answer">

                            <p>
                                After logging in, you can access your
                                medical records from your dashboard.
                                All your reports and prescriptions
                                are stored securely.
                            </p>

                        </div>

                    </div>


                    <!-- FAQ 5 -->

                    <div class="faq-item">

                        <div
                            class="faq-question"
                            onclick="toggleFaq(this)"
                        >

                            <h4>
                                Is online consultation available?
                            </h4>

                            <span class="faq-icon">
                                +
                            </span>

                        </div>

                        <div class="faq-answer">

                            <p>
                                Yes, we offer online video
                                consultations with our doctors.
                                This feature is available for
                                registered users.
                            </p>

                        </div>

                    </div>


                </div>

            </div>


            <!-- =====================================
                 EMERGENCY NOTICE
            ====================================== -->

            <div class="emergency-notice">

                <h3>
                    🚨 In Case of Emergency
                </h3>

                <p>
                    For immediate medical emergencies, please call
                    our emergency hotline or visit the nearest
                    emergency room.
                </p>

                <a
                    href="tel:+919725219106"
                    class="emergency-btn"
                >
                    📞 Call Emergency: +91 97252 19106
                </a>

            </div>


        </div>

    </section>


    <!-- =========================================
         FOOTER
    ========================================== -->

    <footer class="footer">

        <div class="container">

            <div class="footer-content">


                <!-- Footer About -->

                <div class="footer-section">

                    <h3>
                        Human Care
                    </h3>

                    <p>
                        Your health, our priority
                    </p>

                </div>


                <!-- Quick Links -->

                <div class="footer-section">

                    <h4>
                        Quick Links
                    </h4>

                    <ul>

                        <li>
                            <a href="index.php">
                                Home
                            </a>
                        </li>

                      

                        <li>
                            <a href="doctors.php">
                                Doctors
                            </a>
                        </li>

                        <li>
                            <a href="education.php">
                                Education
                            </a>
                        </li>

                    </ul>

                </div>


                <!-- Footer Contact -->

                <div class="footer-section">

                    <h4>
                        Contact
                    </h4>

                    <p>
                        📞 +91 97252 19106
                    </p>

                    <p>
                        📧 solankimaheshkhash7@gmail.com
                    </p>

                    <p>
                        📍 Fake , Fake , Fake
                    </p>

                </div>


            </div>


            <!-- Footer Bottom -->

            <div class="footer-bottom">

                <p>
                    &copy; 2025 Human Care.
                    All rights reserved.
                </p>

            </div>

        </div>

    </footer>


    <!-- =========================================
         MAIN JAVASCRIPT
    ========================================== -->

    <script src="scripts/main.js"></script>

</body>

</html>


