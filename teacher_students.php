<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$institution_id = $_SESSION['institution_id'];

// جلب المواد المكلف بها
$stmt = $pdo->prepare("
    SELECT DISTINCT
        ta.year_id, ta.specialization_id,
        y.year_name, sp.specialization_name
    FROM teacher_assignments ta
    JOIN years y ON ta.year_id = y.id
    JOIN specializations sp ON ta.specialization_id = sp.id
    WHERE ta.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// جلب جميع التلاميذ المرتبطين بالأستاذ
$students_by_class = [];
foreach ($classes as $class) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               (SELECT AVG(g.grade * sub.coefficient) / AVG(sub.coefficient)
                FROM grades g
                JOIN subjects sub ON g.subject_id = sub.id
                WHERE g.student_id = s.id AND g.teacher_id = ?) as my_average
        FROM students s
        WHERE s.year_id = ? AND s.specialization_id = ? AND s.institution_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$teacher_id, $class['year_id'], $class['specialization_id'], $institution_id]);
    $students_by_class[$class['year_name'] . ' - ' . $class['specialization_name']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة التلاميذ</title>
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
        
        .class-section {
            background: white;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .class-header {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            padding: 1.5rem;
        }
        
        .class-header h2 {
            margin-bottom: 0.5rem;
        }
        
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }
        
        .student-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .student-card:hover {
            border-color: #11998e;
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .student-card h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .student-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            color: #666;
            font-weight: 600;
        }
        
        .info-value {
            color: #333;
        }
        
        .average-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            margin-top: 1rem;
        }
        
        .average-good {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .average-medium {
            background: #fff3e0;
            color: #e65100;
        }
        
        .average-bad {
            background: #ffebee;
            color: #c62828;
        }
        
        .average-none {
            background: #f5f5f5;
            color: #757575;
        }
        
        .no-students {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
        
        .search-box {
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .search-box input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #11998e;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>👨‍🏫 مرحباً</h2>
            <p><?php echo htmlspecialchars($_SESSION['teacher_name']); ?></p>
        </div>
        
        <div class="menu-item" onclick="location.href='teacher_dashboard.php'">
            📊 لوحة التحكم
        </div>
        <div class="menu-item" onclick="location.href='teacher_grades.php'">
            📝 إدارة النقاط
        </div>
        <div class="menu-item" onclick="location.href='teacher_attendance.php'">
            ✅ تسجيل الحضور
        </div>
        <div class="menu-item active">
            👥 التلاميذ
        </div>
        
        <button class="logout-btn" onclick="if(confirm('هل تريد تسجيل الخروج؟')) location.href='logout.php'">
            تسجيل الخروج
        </button>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1 style="color: #333;">👥 تلاميذي</h1>
            <p style="color: #666; margin-top: 0.5rem;">قائمة التلاميذ الذين تدرسهم</p>
        </div>
        
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 ابحث عن تلميذ بالاسم أو رقم الهاتف..." onkeyup="searchStudents()">
        </div>
        
        <?php if (count($students_by_class) > 0): ?>
            <?php foreach ($students_by_class as $class_name => $students): ?>
                <div class="class-section">
                    <div class="class-header">
                        <h2>📚 <?php echo htmlspecialchars($class_name); ?></h2>
                        <p style="opacity: 0.9;">عدد التلاميذ: <?php echo count($students); ?></p>
                    </div>
                    
                    <?php if (count($students) > 0): ?>
                        <div class="students-grid">
                            <?php foreach ($students as $student): ?>
                                <div class="student-card" data-search="<?php echo strtolower($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['phone_primary']); ?>">
                                    <h3>👨‍🎓 <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                                    
                                    <div class="student-info">
                                        <div class="info-row">
                                            <span class="info-label">📞 الهاتف:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['phone_primary']); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">👨 الأب:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['father_name']); ?></span>
                                        </div>
                                        <?php if ($student['email']): ?>
                                            <div class="info-row">
                                                <span class="info-label">📧 البريد:</span>
                                                <span class="info-value" style="font-size: 0.85rem;"><?php echo htmlspecialchars($student['email']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php 
                                        $avg = $student['my_average'];
                                        if ($avg !== null) {
                                            $badge_class = 'average-none';
                                            if ($avg >= 15) $badge_class = 'average-good';
                                            elseif ($avg >= 10) $badge_class = 'average-medium';
                                            else $badge_class = 'average-bad';
                                            
                                            echo '<div class="average-badge ' . $badge_class . '">';
                                            echo '📊 المعدل معي: ' . number_format($avg, 2) . '/20';
                                            echo '</div>';
                                        } else {
                                            echo '<div class="average-badge average-none">';
                                            echo '📊 لا توجد نقاط بعد';
                                            echo '</div>';
                                        }
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-students">
                            <h3>لا يوجد تلاميذ في هذا الصف</h3>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="background: white; padding: 5rem 2rem; border-radius: 15px; text-align: center; color: #999;">
                <h2>لا يوجد تلاميذ</h2>
                <p style="margin-top: 1rem;">لم يتم تعيين أي صفوف لك بعد</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function searchStudents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const studentCards = document.querySelectorAll('.student-card');
            
            studentCards.forEach(card => {
                const searchData = card.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>