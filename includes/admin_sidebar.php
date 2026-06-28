<?php
/**
 * includes/admin_sidebar.php
 * Admin Sidebar Component
 *
 * Required before include:
 * - $active_page (string) — current page identifier
 *
 * Auth is checked here so every page is protected automatically.
 */

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Fetch pending counts for badges
$_sb_doctors_conn = new mysqli("localhost", "root", "", "human_care_doctors");
$_sb_pending_doctors   = $_sb_doctors_conn->query("SELECT COUNT(*) as c FROM doctors WHERE verification_status='pending'")->fetch_assoc()['c'];
$_sb_doctors_conn->close();

$_sb_patients_conn = new mysqli("localhost", "root", "", "human_care_patients");
$_sb_pending_patients  = $_sb_patients_conn->query("SELECT COUNT(*) as c FROM patients WHERE verification_status='pending'")->fetch_assoc()['c'];
$_sb_patients_conn->close();

$_sb_admin_conn = new mysqli("localhost", "root", "", "human_care_admin");
$_sb_pending_appts   = $_sb_admin_conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='pending'")->fetch_assoc()['c'];
$_sb_pending_edu     = $_sb_admin_conn->query("SELECT COUNT(*) as c FROM educational_content WHERE status='pending'")->fetch_assoc()['c'];
$_sb_admin_conn->close();

$_sb_admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$_sb_admin_role = htmlspecialchars($_SESSION['admin_role'] ?? 'admin');
?>

<!-- ============================================================
     ADMIN SIDEBAR
============================================================ -->
<button class="menu-toggle" onclick="toggleSidebar()" style="
    position:fixed;top:15px;left:15px;z-index:1100;
    background:linear-gradient(135deg,#1e3c72,#2a5298);
    color:white;border:none;border-radius:8px;
    padding:10px 14px;font-size:20px;cursor:pointer;
    box-shadow:0 2px 8px rgba(0,0,0,0.2);
">☰</button>

<aside class="sidebar" id="sidebar" style="
    position:fixed;top:0;left:0;height:100vh;width:260px;
    background:linear-gradient(180deg,#1e3c72 0%,#2a5298 100%);
    color:white;display:flex;flex-direction:column;
    overflow-y:auto;z-index:1000;
    transition:transform .3s ease;
    box-shadow:4px 0 20px rgba(0,0,0,0.15);
">
    <!-- Logo -->
    <div style="padding:25px 20px 20px;border-bottom:1px solid rgba(255,255,255,0.12);text-align:center;">
        <div style="font-size:36px;margin-bottom:6px;">🛡️</div>
        <div style="font-size:15px;font-weight:800;letter-spacing:2px;">ADMIN PANEL</div>
        <div style="font-size:11px;opacity:.7;margin-top:2px;">Human Care Hospital</div>
    </div>

    <!-- Admin Profile -->
    <div style="padding:20px;border-bottom:1px solid rgba(255,255,255,0.12);display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,0.2);
            display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">👨‍💼</div>
        <div>
            <div style="font-weight:700;font-size:14px;"><?php echo $_sb_admin_name; ?></div>
            <span style="background:rgba(255,255,255,0.2);color:white;padding:3px 10px;
                border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;">
                <?php echo $_sb_admin_role; ?>
            </span>
        </div>
    </div>

    <!-- Navigation -->
    <nav style="flex:1;padding:15px 10px;">
        <ul style="list-style:none;margin:0;padding:0;">

            <?php
            $nav_items = [
                ['page'=>'dashboard',   'icon'=>'🏠', 'label'=>'Dashboard',         'href'=>'admin_dashboard.php',         'badge'=>0],
                ['page'=>'doctors',     'icon'=>'👨‍⚕️', 'label'=>'Manage Doctors',    'href'=>'admin_doctors.php',           'badge'=>(int)$_sb_pending_doctors],
                ['page'=>'patients',    'icon'=>'👥', 'label'=>'Manage Patients',   'href'=>'admin_patients.php',          'badge'=>(int)$_sb_pending_patients],
                ['page'=>'appointments','icon'=>'📅', 'label'=>'Appointments',      'href'=>'admin_appointments.php',      'badge'=>(int)$_sb_pending_appts],
                ['page'=>'education',   'icon'=>'📚', 'label'=>'Approve Education', 'href'=>'admin_manage_education.php',  'badge'=>(int)$_sb_pending_edu],
                ['page'=>'ai',          'icon'=>'🤖', 'label'=>'AI Assistant',      'href'=>'admin_ai_assistant.php',      'badge'=>0],
            ];
            foreach ($nav_items as $item):
                $is_active = (($active_page ?? '') === $item['page']);
            ?>
            <li style="margin-bottom:4px;">
                <a href="<?php echo $item['href']; ?>" style="
                    display:flex;align-items:center;gap:12px;
                    padding:12px 14px;border-radius:10px;
                    text-decoration:none;color:white;font-size:14px;font-weight:500;
                    background:<?php echo $is_active ? 'rgba(255,255,255,0.25)' : 'transparent'; ?>;
                    border-left:<?php echo $is_active ? '3px solid white' : '3px solid transparent'; ?>;
                    transition:all .2s;
                "
                onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                onmouseout="this.style.background='<?php echo $is_active ? 'rgba(255,255,255,0.25)' : 'transparent'; ?>'">
                    <span style="font-size:18px;width:24px;text-align:center;"><?php echo $item['icon']; ?></span>
                    <span style="flex:1;"><?php echo $item['label']; ?></span>
                    <?php if ($item['badge'] > 0): ?>
                        <span style="background:#ef4444;color:white;padding:2px 8px;
                            border-radius:12px;font-size:11px;font-weight:700;">
                            <?php echo $item['badge']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>

        </ul>
    </nav>

    <!-- Logout -->
    <div style="padding:15px 10px;border-top:1px solid rgba(255,255,255,0.12);">
        <form method="post" action="admin_logout.php">
            <button type="submit" style="
                width:100%;padding:12px;border:2px solid rgba(255,255,255,0.4);
                background:transparent;color:white;border-radius:10px;
                font-size:14px;font-weight:600;cursor:pointer;
                transition:all .2s;
            "
            onmouseover="this.style.background='rgba(255,255,255,0.15)'"
            onmouseout="this.style.background='transparent'">
                🚪 Logout
            </button>
        </form>
    </div>
</aside>

<!-- Overlay for mobile -->
<div id="sidebarOverlay" onclick="toggleSidebar()" style="
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,0.5);z-index:999;
"></div>

<style>
    body { margin: 0; }
    .main-content, .admin-main {
        margin-left: 260px;
        padding: 30px;
        min-height: 100vh;
        background: #f5f7fb;
    }
    @media(max-width:768px) {
        #sidebar { transform: translateX(-100%); }
        #sidebar.active { transform: translateX(0); }
        #sidebarOverlay.active { display:block!important; }
        .main-content, .admin-main { margin-left: 0; padding: 20px 15px; }
        .menu-toggle { display: block!important; }
    }
</style>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
</script>