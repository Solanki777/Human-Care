<?php

require_once __DIR__ . '/../includes/session.php';

require_once __DIR__ . '/../config/config.php';

$conn = new mysqli(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_DOCTORS
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Get filter parameters
$specialty_filter = isset($_GET['specialty']) ? $_GET['specialty'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for verified doctors only (excluding deleted)
$query = "SELECT * FROM doctors WHERE is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL)";

// Add specialty filter
if ($specialty_filter !== 'all') {
    $query .= " AND specialty = '" . $conn->real_escape_string($specialty_filter) . "'";
}

// Add search filter
if (!empty($search_query)) {
    $query .= " AND (first_name LIKE '%" . $conn->real_escape_string($search_query) . "%' 
                OR last_name LIKE '%" . $conn->real_escape_string($search_query) . "%' 
                OR specialty LIKE '%" . $conn->real_escape_string($search_query) . "%'
                OR qualification LIKE '%" . $conn->real_escape_string($search_query) . "%')";
}

$query .= " ORDER BY specialty, first_name";

$doctors_result = $conn->query($query);

// Get all unique specialties for filter dropdown (excluding deleted)
$specialties_query = "SELECT DISTINCT specialty FROM doctors WHERE is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY specialty";
$specialties_result = $conn->query($specialties_query);

// Get total verified doctors count (excluding deleted)
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE is_verified = 1 AND verification_status = 'approved' AND (is_deleted = 0 OR is_deleted IS NULL)")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Our Doctors - Human Care</title>

    <!-- Common CSS -->
    <link rel="stylesheet" href="styles/main.css">

    <!-- Sidebar CSS -->
    <link rel="stylesheet" href="styles/sidebar.css">

    <!-- Doctors Page CSS -->
    <link rel="stylesheet" href="styles/doctors.css">

    <!-- Footer CSS -->
    <link rel="stylesheet" href="includes/styles/footer.css">
</head>

<body>
    <?php $active_page = 'doctors'; ?>
    <?php include 'includes/public_sidebar.php'; ?>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>👨‍⚕️ Meet Our Expert Doctors</h1>
            <p>Experienced healthcare professionals dedicated to your wellbeing</p>
            <span class="doctors-count">
                <?php echo $total_doctors; ?> Verified Doctors Available
            </span>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content-section">
        <div class="container">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <select class="filter-select" name="specialty" onchange="this.form.submit()">
                        <option value="all" <?php echo $specialty_filter === 'all' ? 'selected' : ''; ?>>All Specialties</option>
                        <?php while ($spec = $specialties_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialty']); ?>" 
                                    <?php echo $specialty_filter === $spec['specialty'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialty']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <input type="text" 
                           name="search" 
                           placeholder="Search by name or specialty..." 
                           class="search-input" 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <button type="submit" class="btn-primary">Search</button>
                    
                    <?php if ($specialty_filter !== 'all' || !empty($search_query)): ?>
                        <a href="doctors.php" class="btn-secondary">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Filter Results Info -->
            <?php if ($specialty_filter !== 'all' || !empty($search_query)): ?>
                <div class="filter-results">
                    Showing <strong><?php echo $doctors_result->num_rows; ?> doctors</strong>
                    <?php if ($specialty_filter !== 'all'): ?>
                        in <strong><?php echo htmlspecialchars($specialty_filter); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($search_query)): ?>
                        matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                    <?php endif; ?>
                    <a href="doctors.php" class="clear-filters">✕ Clear all filters</a>
                </div>
            <?php endif; ?>

            <!-- Doctors Grid -->
            <?php if ($doctors_result->num_rows > 0): ?>
                <div class="doctors-grid">
                    <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                        <div class="doctor-card" data-specialty="<?php echo htmlspecialchars($doctor['specialty']); ?>">
                            <div class="doctor-avatar">👨‍⚕️</div>
                            
                            <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                            
                            <p class="specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                            
                            <span class="verified-badge">
                                ✓ Verified Doctor
                            </span>
                            
                            <div class="doctor-meta">
                                <span class="meta-item">
                                    ⭐ <?php echo $doctor['experience_years']; ?> years experience
                                </span>
                            </div>
                            
                            <div class="qualifications">
                                <span class="badge"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
                            </div>
                            
                            <?php if ($doctor['about']): ?>
                                <p class="doctor-description">
                                    <?php 
                                    $about = $doctor['about'];
                                    echo htmlspecialchars(strlen($about) > 100 ? substr($about, 0, 100) . '...' : $about); 
                                    ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($doctor['consultation_fee']): ?>
                                <div class="consultation-fee">
                                    Consultation: ₹<?php echo number_format($doctor['consultation_fee']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($doctor['available_days'] || $doctor['available_time']): ?>
                                <div style="margin: 10px 0; font-size: 13px; color: #666;">
                                    <?php if ($doctor['available_days']): ?>
                                        <div>📅 <?php echo htmlspecialchars($doctor['available_days']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($doctor['available_time']): ?>
                                        <div>🕐 <?php echo htmlspecialchars($doctor['available_time']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($doctor['hospital_affiliation']): ?>
                                <div style="margin: 10px 0; font-size: 12px; color: #999;">
                                    🏥 <?php echo htmlspecialchars($doctor['hospital_affiliation']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="doctor-actions">
                                <?php if (isset($_SESSION['user_name']) && $_SESSION['user_type'] === 'patient'): ?>
                                    <a href="book_appointment.php?doctor_id=<?php echo $doctor['id']; ?>" class="btn-primary" style="text-decoration: none; display: block; text-align: center;">
                                        Book Appointment
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn-primary" style="text-decoration: none; display: block; text-align: center;">Login to Book</a>
                                <?php endif; ?>
                                
                                <a href="doctor_profile.php?id=<?php echo $doctor['id']; ?>" class="btn-secondary" style="text-decoration: none; display: block;">
                                    View Profile
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-doctors">
                    <div class="no-doctors-icon">🔍</div>
                    <h3>No Doctors Found</h3>
                    <p>
                        <?php if (!empty($search_query)): ?>
                            No doctors match your search "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                        <?php elseif ($specialty_filter !== 'all'): ?>
                            No verified doctors available in <strong><?php echo htmlspecialchars($specialty_filter); ?></strong> specialty
                        <?php else: ?>
                            No verified doctors available at the moment
                        <?php endif; ?>
                    </p>
                    <a href="doctors.php" class="btn-primary" style="margin-top: 20px;">View All Doctors</a>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="info-box">
                <h3>Need Help Choosing a Doctor?</h3>
                <p>Our support team can help you find the right specialist for your needs</p>
                <a href="contact.php" class="btn-primary">Contact Support</a>
            </div>
        </div>
    </section>

  <?php include 'includes/footer.php'; ?>

</body>
</html>
<?php $conn->close(); ?>