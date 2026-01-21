<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$institution_id = $_SESSION['institution_id'];

// جلب معلومات التلميذ
$stmt = $pdo->prepare("
    SELECT s.*, y.year_name, sp.specialization_name 
    FROM students s
    JOIN years y ON s.year_id = y.id
    JOIN specializations sp ON s.specialization_id = sp.id
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// جلب النقاط الأخيرة
$stmt = $pdo->prepare("
    SELECT g.*, sub.subject_name, sub.coefficient, t.first_name, t.last_name
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    JOIN teachers t ON g.teacher_id = t.id
    WHERE g.student_id = ?
    ORDER BY g.created_at DESC
    LIMIT 10
");
$stmt->execute([$student_id]);
$recent_grades = $stmt->fetchAll();

// جلب سجل الحضور الأخير
$stmt = $pdo->prepare("
    SELECT a.*, sub.subject_name
    FROM attendance a
    JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.student_id = ?
    ORDER BY a.date DESC
    LIMIT 10
");
$stmt->execute([$student_id]);
$recent_attendance = $stmt->fetchAll();

// حساب المعدل العام
$stmt = $pdo->prepare("
    SELECT AVG(g.grade * sub.coefficient) / AVG(sub.coefficient) as average
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.student_id = ?
");
$stmt->execute([$student_id]);
$average_result = $stmt->fetch();
$average = $average_result['average'] ? round($average_result['average'], 2) : 0;

// الإشعارات غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM parent_notifications WHERE student_id = ? AND is_read = 0");
$stmt->execute([$student_id]);
$unread_notifications = $stmt->fetch()['count'];

// الإعلانات
$announcements = getAnnouncements($pdo, $institution_id, 'تلاميذ');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo htmlspecialchars($_SESSION['student_name']); ?></title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
        
        .student-info {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .student-info h2 {
            margin-bottom: 1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            background: rgba(255,255,255,0.2);
            padding: 1rem;
            border-radius: 10px;
        }
        
        .info-item strong {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            color: #f5576c;
            margin-bottom: 0.5rem;
        }
        
        .section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f5f7fa;
            padding: 1rem;
            text-align: right;
            color: #333;
            font-weight: 600;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .grade-good {
            color: #4caf50;
            font-weight: bold;
        }
        
        .grade-medium {
            color: #ff9800;
            font-weight: bold;
        }
        
        .grade-bad {
            color: #f44336;
            font-weight: bold;
        }
        
        .status-present {
            color: #4caf50;
            font-weight: 600;
        }
        
        .status-absent {
            color: #f44336;
            font-weight: 600;
        }
        
        .status-excused {
            color: #ff9800;
            font-weight: 600;
        }
        
        .announcement-item {
            padding: 1rem;
            border-right: 4px solid #f5576c;
            background: #f8f9fa;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>👨‍🎓 مرحباً</h2>
            <p><?php echo htmlspecialchars($_SESSION['student_name']); ?></p>
        </div>
        
        <div class="menu-item active">
            📊 لوحة التحكم
        </div>
        <div class="menu-item" onclick="location.href='student_grades.php'">
            📝 النقاط والدرجات
        </div>
        <div class="menu-item" onclick="location.href='student_attendance.php'">
            ✅ سجل الحضور
        </div>
        <div class="menu-item" onclick="location.href='student_notifications.php'">
            🔔 الإشعارات <?php if($unread_notifications > 0) echo "($unread_notifications)"; ?>
        </div>
        <div class="menu-item" onclick="location.href='student_schedule.php'">
            📅 جدول الأوقات
        </div>
        
        <button class="logout-btn" onclick="if(confirm('هل تريد تسجيل الخروج؟')) location.href='logout.php'">
            تسجيل الخروج
        </button>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>مرحباً <?php echo htmlspecialchars($student['first_name']); ?></h1>
            <p>تابع تقدمك الدراسي وأداءك الأكاديمي</p>
        </div>
        
        <div class="student-info">
            <h2>معلوماتك الدراسية</h2>
            <div class="info-grid">
                <div class="info-item">
                    <strong>الاسم الكامل</strong>
                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                </div>
                <div class="info-item">
                    <strong>السنة الدراسية</strong>
                    <?php echo htmlspecialchars($student['year_name']); ?>
                </div>
                <div class="info-item">
                    <strong>التخصص</strong>
                    <?php echo htmlspecialchars($student['specialization_name']); ?>
                </div>
                <div class="info-item">
                    <strong>رقم الهاتف</strong>
                    <?php echo htmlspecialchars($student['phone_primary']); ?>
                </div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $average; ?>/20</h3>
                <p>المعدل العام</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($recent_grades); ?></h3>
                <p>عدد التقييمات</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $unread_notifications; ?></h3>
                <p>إشعارات جديدة</p>
            </div>
        </div>
        
        <div class="section">
            <h2>آخر النقاط</h2>
            <?php if (count($recent_grades) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>المادة</th>
                            <th>نوع التقييم</th>
                            <th>النقطة</th>
                            <th>المعامل</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_grades as $grade): ?>
                            <?php 
                                $grade_class = 'grade-medium';
                                if ($grade['grade'] >= 15) $grade_class = 'grade-good';
                                elseif ($grade['grade'] < 10) $grade_class = 'grade-bad';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($grade['grade_type']); ?></td>
                                <td class="<?php echo $grade_class; ?>">
                                    <?php echo $grade['grade']; ?>/<?php echo $grade['max_grade']; ?>
                                </td>
                                <td><?php echo $grade['coefficient']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($grade['exam_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 2rem;">لا توجد نقاط مسجلة بعد</p>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>سجل الحضور الأخير</h2>
            <?php if (count($recent_attendance) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>المادة</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_attendance as $att): ?>
                            <?php 
                                $status_class = 'status-absent';
                                if ($att['status'] == 'حاضر') $status_class = 'status-present';
                                elseif ($att['status'] == 'غياب بعذر') $status_class = 'status-excused';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($att['subject_name']); ?></td>
                                <td class="<?php echo $status_class; ?>"><?php echo $att['status']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($att['date'])); ?></td>
                                <td><?php echo htmlspecialchars($att['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 2rem;">لا يوجد سجل حضور</p>
            <?php endif; ?>
        </div>
        
        <div class="section">
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
                <p style="text-align: center; color: #999; padding: 2rem;">لا توجد إعلانات</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>