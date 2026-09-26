<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$error = "";

// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $conn = new mysqli(
                DB_HOST,
                DB_USERNAME,
                DB_PASSWORD,
                DB_ADMIN
            );
        
        if ($conn->connect_error) {
            $error = "Database connection failed!";
        } else {
            $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();

                if (password_verify($password, $admin["password"])) {
                    $_SESSION["admin_id"] = $admin["id"];
                    $_SESSION["admin_name"] = $admin["full_name"];
                    $_SESSION["admin_role"] = $admin["role"];
                    $_SESSION["admin_logged_in"] = true;
                    
                    // Log activity
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'login', 'Admin logged in', ?)");
                    $log_stmt->bind_param("is", $admin["id"], $ip);
                    $log_stmt->execute();
                    $log_stmt->close();

                    header("Location: admin_dashboard.php");
                    exit;
                } else {
                    $error = "Incorrect password!";
                }
            } else {
                $error = "Admin account not found!";
            }
            $stmt->close();
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Human Care</title>
    <link rel="stylesheet" href="styles/login.css">
    
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-header">
            <div class="admin-icon">🛡️</div>
            <h1>Admin Login</h1>
            <p>Human Care Management System</p>
        </div>

        <div class="admin-form">
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" placeholder="Enter admin username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>

                <button type="submit" class="login-btn">Login to Admin Panel</button>

                <div class="back-link">
                    <a href="index.php">← Back to Website</a>
                </div>

               
            </form>
        </div>
    </div>
</body>
</html>