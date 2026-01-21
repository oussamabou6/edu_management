<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$institution_id = $_SESSION['institution_id'];

// الحصول على المواد والسنوات التي يدرسها الأستاذ
$stmt = $pdo->prepare("
    SELECT 
        ta.*, 
        s.subject_name, 
        s.coefficient,
        y.year_name,
        sp.specialization_name
    FROM teacher_assignments ta
    JOIN subjects s ON ta.subject_id = s.id
    JOIN years y ON ta.year_id = y.id
    JOIN specializations sp ON ta.specialization_id = sp.id
    WHERE ta.teacher_id = ?
    ORDER BY y.year_name, sp.specialization_name
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll();

// الإعلانات
$announcements = getAnnouncements($pdo, $institution_id, 'أساتذة');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo htmlspecialchars($_SESSION['teacher_name']); ?></title>
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
        
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
        
        .menu-item {
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.3s;
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
        
        .assignments-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .assignments-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
        }
        
        .assignment-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .assignment-card:hover {
            transform: translateY(-3px);
        }
        
        .assignment-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }
        
        .assignment-card p {
            color: #666;
            margin-bottom: 0.3rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: 0.5rem;
        }
        
        .quick-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .action-btn {
            padding: 1.2rem;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
        }
        
        .announcements {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .announcement-item {
            padding: 1rem;
            border-right: 4px solid #11998e;
            background: #f8f9fa;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        
        .announcement-item h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .no-assignments {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>👨‍🏫 مرحباً</h2>
            <p><?php echo htmlspecialchars($_SESSION['teacher_name']); ?></p>
        </div>
        
        <div class="menu-item active">
            📊 لوحة التحكم
        </div>
        <div class="menu-item" onclick="location.href='teacher_grades.php'">
            📝 إدارة النقاط
        </div>
        <div class="menu-item" onclick="location.href='teacher_attendance.php'">
            ✅ تسجيل الحضور
        </div>
        <div class="menu-item" onclick="location.href='teacher_students.php'">
            👥 التلاميذ
        </div>
        <div class="menu-item" onclick="location.href='teacher_schedule.php'">
            📅 جدول الأوقات
        </div>
        
        <button class="logout-btn" onclick="if(confirm('هل تريد تسجيل الخروج؟')) location.href='logout.php'">
            تسجيل الخروج
        </button>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>مرحباً بك أستاذ <?php echo htmlspecialchars($_SESSION['teacher_name']); ?></h1>
            <p>هنا يمكنك إدارة المواد والسنوات التي تدرسها</p>
        </div>
        
        <div class="quick-actions">
            <h2>إجراءات سريعة</h2>
            <div class="actions-grid">
                <button class="action-btn" onclick="location.href='teacher_grades.php'">
                    ➕ إضافة نقاط
                </button>
                <button class="action-btn" onclick="location.href='teacher_attendance.php'">
                    ✅ تسجيل حضور
                </button>
                <button class="action-btn" onclick="location.href='teacher_students.php'">
                    👥 عرض التلاميذ
                </button>
            </div>
        </div>
        
        <div class="assignments-section">
            <h2>المواد والسنوات التي تدرسها</h2>
            
            <?php if (count($assignments) > 0): ?>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card" onclick="location.href='teacher_class.php?assignment=<?php echo $assignment['id']; ?>'">
                        <h3>📚 <?php echo htmlspecialchars($assignment['subject_name']); ?></h3>
                        <p>
                            <strong>السنة:</strong> <?php echo htmlspecialchars($assignment['year_name']); ?>
                            <span class="badge">المعامل: <?php echo $assignment['coefficient']; ?></span>
                        </p>
                        <p><strong>التخصص:</strong> <?php echo htmlspecialchars($assignment['specialization_name']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-assignments">
                    <h3>لم يتم تعيين أي مواد لك بعد</h3>
                    <p>يرجى التواصل مع إدارة المؤسسة</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="announcements">
            <h2>الإعلانات والأخبار</h2>
            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item">
                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        <small><?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 2rem;">لا توجد إعلانات حالياً</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>