<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// جلب النقاط مع تنظيمها حسب المادة
$stmt = $pdo->prepare("
    SELECT 
        g.*,
        s.subject_name,
        s.coefficient,
        t.first_name as teacher_first,
        t.last_name as teacher_last
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    JOIN teachers t ON g.teacher_id = t.id
    WHERE g.student_id = ?
    ORDER BY s.subject_name, g.exam_date DESC
");
$stmt->execute([$student_id]);
$all_grades = $stmt->fetchAll();

// تنظيم النقاط حسب المادة
$grades_by_subject = [];
foreach ($all_grades as $grade) {
    $subject_name = $grade['subject_name'];
    if (!isset($grades_by_subject[$subject_name])) {
        $grades_by_subject[$subject_name] = [
            'grades' => [],
            'coefficient' => $grade['coefficient'],
            'average' => 0
        ];
    }
    $grades_by_subject[$subject_name]['grades'][] = $grade;
}

// حساب المعدل لكل مادة
foreach ($grades_by_subject as $subject => &$data) {
    $total = 0;
    $count = 0;
    foreach ($data['grades'] as $grade) {
        $total += $grade['grade'];
        $count++;
    }
    $data['average'] = $count > 0 ? $total / $count : 0;
}

// حساب المعدل العام
$stmt = $pdo->prepare("
    SELECT AVG(g.grade * s.coefficient) / AVG(s.coefficient) as overall_average
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ?
");
$stmt->execute([$student_id]);
$overall_average = $stmt->fetch()['overall_average'] ?: 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>النقاط والدرجات</title>
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
        
        .overall-average {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .overall-average h2 {
            font-size: 4rem;
            margin-bottom: 0.5rem;
        }
        
        .subject-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .subject-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subject-header h3 {
            font-size: 1.5rem;
        }
        
        .subject-average {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .grades-table {
            padding: 2rem;
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
        
        .grade-excellent {
            color: #2e7d32;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .grade-good {
            color: #4caf50;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .grade-medium {
            color: #ff9800;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .grade-bad {
            color: #f44336;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .type-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .type-فرض {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .type-اختبار {
            background: #fff3e0;
            color: #e65100;
        }
        
        .type-امتحان {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .type-مشاركة {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .no-grades {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>👨‍🎓 مرحباً</h2>
            <p><?php echo htmlspecialchars($_SESSION['student_name']); ?></p>
        </div>
        
        <div class="menu-item" onclick="location.href='student_dashboard.php'">
            📊 لوحة التحكم
        </div>
        <div class="menu-item active">
            📝 النقاط والدرجات
        </div>
        <div class="menu-item" onclick="location.href='student_attendance.php'">
            ✅ سجل الحضور
        </div>
        <div class="menu-item" onclick="location.href='student_notifications.php'">
            🔔 الإشعارات
        </div>
        
        <button class="logout-btn" onclick="if(confirm('هل تريد تسجيل الخروج؟')) location.href='logout.php'">
            تسجيل الخروج
        </button>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1 style="color: #333;">📝 النقاط والدرجات</h1>
            <p style="color: #666; margin-top: 0.5rem;">تفاصيل نقاطك في جميع المواد</p>
        </div>
        
        <div class="overall-average">
            <p style="font-size: 1.2rem; opacity: 0.9; margin-bottom: 0.5rem;">معدلك العام</p>
            <h2><?php echo number_format($overall_average, 2); ?><span style="font-size: 2rem;">/20</span></h2>
            <?php if ($overall_average >= 15): ?>
                <p style="margin-top: 1rem; opacity: 0.9;">🎉 ممتاز! استمر في التفوق</p>
            <?php elseif ($overall_average >= 10): ?>
                <p style="margin-top: 1rem; opacity: 0.9;">👍 جيد! يمكنك التحسين</p>
            <?php else: ?>
                <p style="margin-top: 1rem; opacity: 0.9;">💪 اجتهد أكثر لتحسين معدلك</p>
            <?php endif; ?>
        </div>
        
        <?php if (count($grades_by_subject) > 0): ?>
            <?php foreach ($grades_by_subject as $subject_name => $subject_data): ?>
                <div class="subject-card">
                    <div class="subject-header">
                        <div>
                            <h3>📚 <?php echo htmlspecialchars($subject_name); ?></h3>
                            <p style="opacity: 0.9; margin-top: 0.5rem;">
                                المعامل: <?php echo $subject_data['coefficient']; ?> • 
                                عدد النقاط: <?php echo count($subject_data['grades']); ?>
                            </p>
                        </div>
                        <div class="subject-average">
                            المعدل: <?php echo number_format($subject_data['average'], 2); ?>/20
                        </div>
                    </div>
                    
                    <div class="grades-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>نوع التقييم</th>
                                    <th>النقطة</th>
                                    <th>الأستاذ</th>
                                    <th>التاريخ</th>
                                    <th>ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subject_data['grades'] as $grade): ?>
                                    <?php 
                                        $percentage = ($grade['grade'] / $grade['max_grade']) * 100;
                                        $grade_class = 'grade-medium';
                                        if ($percentage >= 90) $grade_class = 'grade-excellent';
                                        elseif ($percentage >= 75) $grade_class = 'grade-good';
                                        elseif ($percentage < 50) $grade_class = 'grade-bad';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="type-badge type-<?php echo $grade['grade_type']; ?>">
                                                <?php echo htmlspecialchars($grade['grade_type']); ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo $grade_class; ?>">
                                            <?php echo $grade['grade']; ?> / <?php echo $grade['max_grade']; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($grade['teacher_first'] . ' ' . $grade['teacher_last']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($grade['exam_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($grade['notes'] ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-grades">
                <h2>📝 لا توجد نقاط مسجلة بعد</h2>
                <p style="margin-top: 1rem;">لم يقم الأساتذة بإضافة أي نقاط لك حتى الآن</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>