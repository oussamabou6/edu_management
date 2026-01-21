<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$institution = getInstitutionInfo($pdo, $institution_id);

// احصائيات
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$students_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM teachers WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$teachers_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM years WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$years_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subjects WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$subjects_count = $stmt->fetch()['count'];

// الإعلانات
$announcements = getAnnouncements($pdo, $institution_id);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo htmlspecialchars($institution['name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 1rem;
            overflow-y: auto;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .logo h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .logo p {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .menu-item {
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .menu-item.active {
            background: rgba(255,255,255,0.2);
        }
        
        .logout-btn {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
        }
        
        /* Main Content */
        .main-content {
            margin-right: 260px;
            padding: 2rem;
        }
        
        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #666;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        
        .stat-icon.blue {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .stat-icon.green {
            background: linear-gradient(135deg, #11998e, #38ef7d);
        }
        
        .stat-icon.orange {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }
        
        .stat-icon.purple {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }
        
        .stat-info h3 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.3rem;
        }
        
        .stat-info p {
            color: #666;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .quick-actions h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .action-btn {
            padding: 1.2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.3s;
            text-align: center;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
        }
        
        /* Announcements */
        .announcements {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .announcements h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .announcement-item {
            padding: 1rem;
            border-right: 4px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        
        .announcement-item h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .announcement-item p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .announcement-item small {
            color: #999;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>🏫 <?php echo htmlspecialchars($institution['name']); ?></h2>
            <p><?php echo htmlspecialchars($institution['type']); ?></p>
        </div>
        
        <div class="menu-item active">
            📊 لوحة التحكم
        </div>
        <div class="menu-item" onclick="location.href='manage_students.php'">
            👨‍🎓 إدارة التلاميذ
        </div>
        <div class="menu-item" onclick="location.href='manage_teachers.php'">
            👨‍🏫 إدارة الأساتذة
        </div>
        <div class="menu-item" onclick="location.href='manage_years.php'">
            📅 السنوات والتخصصات
        </div>
        <div class="menu-item" onclick="location.href='manage_subjects.php'">
            📚 المواد الدراسية
        </div>
        <div class="menu-item" onclick="location.href='manage_announcements.php'">
            📢 الإعلانات والأخبار
        </div>
        <div class="menu-item" onclick="location.href='reports.php'">
            📈 التقارير
        </div>
        <div class="menu-item" onclick="location.href='settings.php'">
            ⚙️ الإعدادات
        </div>
        
        <button class="logout-btn" onclick="if(confirm('هل تريد تسجيل الخروج؟')) location.href='logout.php'">
            تسجيل الخروج
        </button>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>مرحباً بك في لوحة التحكم</h1>
            <p>نظرة عامة على المؤسسة التربوية</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👨‍🎓</div>
                <div class="stat-info">
                    <h3><?php echo $students_count; ?></h3>
                    <p>عدد التلاميذ</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">👨‍🏫</div>
                <div class="stat-info">
                    <h3><?php echo $teachers_count; ?></h3>
                    <p>عدد الأساتذة</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">📅</div>
                <div class="stat-info">
                    <h3><?php echo $years_count; ?></h3>
                    <p>السنوات الدراسية</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">📚</div>
                <div class="stat-info">
                    <h3><?php echo $subjects_count; ?></h3>
                    <p>المواد الدراسية</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>إجراءات سريعة</h2>
            <div class="actions-grid">
                <button class="action-btn" onclick="location.href='add_student.php'">
                    ➕ إضافة تلميذ جديد
                </button>
                <button class="action-btn" onclick="location.href='add_teacher.php'">
                    ➕ إضافة أستاذ جديد
                </button>
                <button class="action-btn" onclick="location.href='add_announcement.php'">
                    📢 نشر إعلان
                </button>
                <button class="action-btn" onclick="location.href='view_attendance.php'">
                    📋 سجل الحضور
                </button>
            </div>
        </div>
        
        <!-- Recent Announcements -->
        <div class="announcements">
            <h2>آخر الإعلانات والأخبار</h2>
            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item">
                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        <small>
                            <?php echo $announcement['type']; ?> • 
                            للجمهور: <?php echo $announcement['target_audience']; ?> • 
                            <?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #999; text-align: center; padding: 2rem;">لا توجد إعلانات حالياً</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>