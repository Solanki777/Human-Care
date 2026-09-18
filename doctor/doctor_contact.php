<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

$active_page = 'contact';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact Us - Human Care</title>

    <link rel="stylesheet" href="styles/doc_con.css">
    <link rel="stylesheet" href="styles/sidebar.css">
   
</head>

<body>

    <?php include 'includes/doctor_sidebar.php'; ?>

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
                        href="mailto:anantra.login7@gmail.com"
                        class="contact-link"
                    >
                        anantra.login7@gmail.com
                    </a>

                    <button
                        type="button"
                        class="btn-primary"
                        onclick="window.location.href='mailto:anantra.login7@gmail.com'"
                    >
                        Send Email
                    </button>

                </div>

            </div>


           

        </div>

    </section>
    <!-- =========================================
         MAIN JAVASCRIPT
    ========================================== -->

    <script src="scripts/doc_con.js"></script>

</body>

</html>


