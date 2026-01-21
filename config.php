<?php
// ملف الاتصال بقاعدة البيانات
session_start();

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'edu_management');

// الاتصال بقاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch(PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// دالة لحماية البيانات
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// دالة للتحقق من تسجيل الدخول
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// دالة للتحقق من نوع المستخدم
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

// دالة لتسجيل الخروج
function logout() {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// دالة لإرسال إشعار للأولياء
function sendNotificationToParent($pdo, $student_id, $type, $message) {
    $stmt = $pdo->prepare("INSERT INTO parent_notifications (student_id, notification_type, message) VALUES (?, ?, ?)");
    return $stmt->execute([$student_id, $type, $message]);
}

// دالة للحصول على معلومات المؤسسة
function getInstitutionInfo($pdo, $institution_id) {
    $stmt = $pdo->prepare("SELECT * FROM institutions WHERE id = ?");
    $stmt->execute([$institution_id]);
    return $stmt->fetch();
}

// دالة للحصول على إعلانات المؤسسة
function getAnnouncements($pdo, $institution_id, $user_type = 'الجميع') {
    $stmt = $pdo->prepare("
        SELECT * FROM announcements 
        WHERE institution_id = ? 
        AND (target_audience = 'الجميع' OR target_audience = ?)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$institution_id, $user_type]);
    return $stmt->fetchAll();
}
?>