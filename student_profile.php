<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// جلب معلومات التلميذ
$stmt = $pdo->prepare("
    SELECT s.*, y.year_name, sp.specialization_name 
    FROM students s
    JOIN years y ON s.year_id = y.id
    JOIN specializations sp ON s.specialization_id = sp.id
    WHERE s.id = ? AND s.institution_id = ?
");
$stmt->execute([$student_id, $institution_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: manage_students.php");
    exit();
}

// حساب المعدل العام
$stmt = $pdo->prepare("
    SELECT AVG(g.grade * sub.coefficient) / AVG(sub.coefficient) as average
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.student_id = ?
");
$stmt->execute([$student_id]);
$average = $stmt->fetch()['average'] ?: 0;

// جلب النقاط
$stmt = $pdo->prepare("
    SELECT g.*, sub.subject_name, sub.coefficient, t.first_name, t.last_name
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    JOIN teachers t ON g.teacher_id = t.id
    WHERE g.student_id = ?
    ORDER BY g.exam_date DESC
");
$stmt->execute([$student_id]);
$grades = $stmt->fetchAll();

// جلب سجل الحضور
$stmt = $pdo->prepare("
    SELECT a.*, sub.subject_name
    FROM attendance a
    JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.student_id = ?
    ORDER BY a.date DESC
    LIMIT 30
");
$stmt->execute([$student_id]);
$attendance = $stmt->fetchAll();

// إحصائيات الحضور
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status = 'حاضر' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'غائب' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'غياب بعذر' THEN 1 ELSE 0 END) as excused,
        COUNT(*) as total
    FROM attendance
    WHERE student_id = ?
");
$stmt->execute([$student_id]);
$attendance_stats = $stmt->fetch();

// الإشعارات
$stmt = $pdo->prepare("
    SELECT * FROM parent_notifications 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$student_id]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملف التلميذ - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 2rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .student-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .student-header h1 {
            margin-bottom: 1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
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
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            color: #666;
        }
        
        .section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f5f7fa;
            padding: 1rem;
            text-align: right;
            font-weight: 600;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
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
        
        .notification-item {
            padding: 1rem;
            border-right: 4px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        
        .notification-item.unread {
            background: #e3f2fd;
            border-right-color: #2196f3;
        }
        
        .btn-action {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="manage_students.php" class="back-link">← العودة لإدارة التلاميذ</a>
            <h1 style="color: #333;">📋 ملف التلميذ الكامل</h1>
        </div>
        
        <div class="student-header">
            <h1>👨‍🎓 <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
            <div class="info-grid">
                <div class="info-item">
                    <strong>السنة الدراسية</strong>
                    <?php echo htmlspecialchars($student['year_name']); ?>
                </div>
                <div class="info-item">
                    <strong>التخصص</strong>
                    <?php echo htmlspecialchars($student['specialization_name']); ?>
                </div>
                <div class="info-item">
                    <strong>اسم الأب</strong>
                    <?php echo htmlspecialchars($student['father_name']); ?>
                </div>
                <div class="info-item">
                    <strong>اسم الأم</strong>
                    <?php echo htmlspecialchars($student['mother_first_name'] . ' ' . $student['mother_last_name']); ?>
                </div>
                <div class="info-item">
                    <strong>رقم الهاتف</strong>
                    <?php echo htmlspecialchars($student['phone_primary']); ?>
                </div>
                <div class="info-item">
                    <strong>عدد الإخوة</strong>
                    <?php echo $student['siblings_count']; ?>
                </div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($average, 2); ?>/20</h3>
                <p>المعدل العام</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($grades); ?></h3>
                <p>عدد التقييمات</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $attendance_stats['present']; ?>/<?php echo $attendance_stats['total']; ?></h3>
                <p>الحضور</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $attendance_stats['absent']; ?></h3>
                <p>أيام الغياب</p>
            </div>
        </div>
        
        <div class="section">
            <h2>📝 سجل النقاط والدرجات</h2>
            <?php if (count($grades) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>المادة</th>
                            <th>نوع التقييم</th>
                            <th>النقطة</th>
                            <th>المعامل</th>
                            <th>الأستاذ</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <?php 
                                $percentage = ($grade['grade'] / $grade['max_grade']) * 100;
                                $grade_class = 'grade-medium';
                                if ($percentage >= 75) $grade_class = 'grade-good';
                                elseif ($percentage < 50) $grade_class = 'grade-bad';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($grade['subject_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($grade['grade_type']); ?></td>
                                <td class="<?php echo $grade_class; ?>">
                                    <?php echo $grade['grade']; ?> / <?php echo $grade['max_grade']; ?>
                                </td>
                                <td><?php echo $grade['coefficient']; ?></td>
                                <td><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($grade['exam_date'])); ?></td>
                                <td><?php echo htmlspecialchars($grade['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: #999;">لا توجد نقاط مسجلة</p>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>📅 سجل الحضور والغياب</h2>
            <?php if (count($attendance) > 0): ?>
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
                        <?php foreach ($attendance as $att): ?>
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
                <p style="text-align: center; padding: 2rem; color: #999;">لا يوجد سجل حضور</p>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>🔔 الإشعارات المرسلة للأولياء</h2>
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                        <strong><?php echo htmlspecialchars($notif['notification_type']); ?></strong>
                        <p><?php echo htmlspecialchars($notif['message']); ?></p>
                        <small style="color: #999;">
                            <?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?>
                            <?php echo $notif['is_read'] ? '• مقروء' : '• غير مقروء'; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: #999;">لا توجد إشعارات</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>