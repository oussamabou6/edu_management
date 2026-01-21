<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];

// إحصائيات عامة
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$total_students = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM teachers WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$total_teachers = $stmt->fetch()['count'];

// إحصائيات الحضور
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'حاضر' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'غائب' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'غياب بعذر' THEN 1 ELSE 0 END) as excused
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE s.institution_id = ?
");
$stmt->execute([$institution_id]);
$attendance_stats = $stmt->fetch();

// أفضل 10 تلاميذ
$stmt = $pdo->prepare("
    SELECT 
        s.first_name, s.last_name, y.year_name,
        AVG(g.grade * sub.coefficient) / AVG(sub.coefficient) as average
    FROM students s
    JOIN grades g ON s.id = g.student_id
    JOIN subjects sub ON g.subject_id = sub.id
    JOIN years y ON s.year_id = y.id
    WHERE s.institution_id = ?
    GROUP BY s.id
    HAVING average IS NOT NULL
    ORDER BY average DESC
    LIMIT 10
");
$stmt->execute([$institution_id]);
$top_students = $stmt->fetchAll();

// التلاميذ الأكثر غياباً
$stmt = $pdo->prepare("
    SELECT 
        s.first_name, s.last_name, y.year_name,
        COUNT(*) as absence_count
    FROM students s
    JOIN attendance a ON s.id = a.student_id
    JOIN years y ON s.year_id = y.id
    WHERE s.institution_id = ? AND a.status = 'غائب'
    GROUP BY s.id
    ORDER BY absence_count DESC
    LIMIT 10
");
$stmt->execute([$institution_id]);
$most_absent = $stmt->fetchAll();

// توزيع التلاميذ حسب السنوات
$stmt = $pdo->prepare("
    SELECT y.year_name, COUNT(s.id) as student_count
    FROM years y
    LEFT JOIN students s ON y.id = s.year_id AND s.institution_id = ?
    WHERE y.institution_id = ?
    GROUP BY y.id
    ORDER BY student_count DESC
");
$stmt->execute([$institution_id, $institution_id]);
$students_by_year = $stmt->fetchAll();

// إحصائيات النقاط
$stmt = $pdo->prepare("
    SELECT 
        AVG(g.grade) as avg_grade,
        MAX(g.grade) as max_grade,
        MIN(g.grade) as min_grade,
        COUNT(*) as total_grades
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE s.institution_id = ?
");
$stmt->execute([$institution_id]);
$grades_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير والإحصائيات</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .back-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .btn-export {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card.blue h3 {
            color: #2196f3;
        }
        
        .stat-card.green h3 {
            color: #4caf50;
        }
        
        .stat-card.orange h3 {
            color: #ff9800;
        }
        
        .stat-card.red h3 {
            color: #f44336;
        }
        
        .stat-card p {
            color: #666;
            font-weight: 600;
        }
        
        .report-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .report-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
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
        
        .rank-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .rank-1 {
            background: linear-gradient(135deg, #f9d423, #ff4e50);
        }
        
        .rank-2 {
            background: linear-gradient(135deg, #bdc3c7, #95a5a6);
        }
        
        .rank-3 {
            background: linear-gradient(135deg, #cd7f32, #d4a574);
        }
        
        .rank-other {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .bar-chart {
            margin-top: 1rem;
        }
        
        .bar {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .bar-label {
            width: 150px;
            font-weight: 600;
            color: #333;
        }
        
        .bar-visual {
            flex: 1;
            height: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 5px;
            position: relative;
        }
        
        .bar-value {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <a href="institution_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
                <h1 style="color: #333; margin-top: 0.5rem;">📈 التقارير والإحصائيات</h1>
            </div>
            <button class="btn-export" onclick="window.print()">
                🖨️ طباعة التقرير
            </button>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <h3><?php echo $total_students; ?></h3>
                <p>إجمالي التلاميذ</p>
            </div>
            <div class="stat-card green">
                <h3><?php echo $total_teachers; ?></h3>
                <p>إجمالي الأساتذة</p>
            </div>
            <div class="stat-card orange">
                <h3><?php echo number_format($grades_stats['avg_grade'], 2); ?>/20</h3>
                <p>المعدل العام</p>
            </div>
            <div class="stat-card red">
                <h3><?php echo $attendance_stats['absent']; ?></h3>
                <p>إجمالي الغيابات</p>
            </div>
        </div>
        
        <div class="report-section">
            <h2>📊 إحصائيات الحضور والغياب</h2>
            <div class="stats-grid">
                <div class="stat-card green">
                    <h3><?php echo $attendance_stats['present']; ?></h3>
                    <p>الحضور (<?php echo $attendance_stats['total'] > 0 ? round(($attendance_stats['present']/$attendance_stats['total'])*100, 1) : 0; ?>%)</p>
                </div>
                <div class="stat-card red">
                    <h3><?php echo $attendance_stats['absent']; ?></h3>
                    <p>الغياب (<?php echo $attendance_stats['total'] > 0 ? round(($attendance_stats['absent']/$attendance_stats['total'])*100, 1) : 0; ?>%)</p>
                </div>
                <div class="stat-card orange">
                    <h3><?php echo $attendance_stats['excused']; ?></h3>
                    <p>غياب بعذر (<?php echo $attendance_stats['total'] > 0 ? round(($attendance_stats['excused']/$attendance_stats['total'])*100, 1) : 0; ?>%)</p>
                </div>
            </div>
        </div>
        
        <div class="charts-grid">
            <div class="report-section">
                <h2>🏆 أفضل 10 تلاميذ</h2>
                <?php if (count($top_students) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>الترتيب</th>
                                <th>الاسم</th>
                                <th>السنة</th>
                                <th>المعدل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_students as $index => $student): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-<?php echo $index < 3 ? $index + 1 : 'other'; ?>">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['year_name']); ?></td>
                                    <td style="color: #4caf50; font-weight: bold;">
                                        <?php echo number_format($student['average'], 2); ?>/20
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: #999;">لا توجد بيانات</p>
                <?php endif; ?>
            </div>
            
            <div class="report-section">
                <h2>⚠️ الأكثر غياباً</h2>
                <?php if (count($most_absent) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>السنة</th>
                                <th>عدد الغيابات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($most_absent as $index => $student): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['year_name']); ?></td>
                                    <td style="color: #f44336; font-weight: bold;">
                                        <?php echo $student['absence_count']; ?> يوم
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: #999;">لا توجد بيانات</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="report-section">
            <h2>📚 توزيع التلاميذ حسب السنوات</h2>
            <div class="bar-chart">
                <?php 
                    $max_count = 0;
                    foreach ($students_by_year as $year) {
                        if ($year['student_count'] > $max_count) {
                            $max_count = $year['student_count'];
                        }
                    }
                ?>
                <?php foreach ($students_by_year as $year): ?>
                    <?php $percentage = $max_count > 0 ? ($year['student_count'] / $max_count) * 100 : 0; ?>
                    <div class="bar">
                        <div class="bar-label"><?php echo htmlspecialchars($year['year_name']); ?></div>
                        <div class="bar-visual" style="width: <?php echo $percentage; ?>%;">
                            <span class="bar-value"><?php echo $year['student_count']; ?> تلميذ</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="report-section">
            <h2>📊 إحصائيات النقاط</h2>
            <div class="stats-grid">
                <div class="stat-card blue">
                    <h3><?php echo $grades_stats['total_grades']; ?></h3>
                    <p>إجمالي النقاط المسجلة</p>
                </div>
                <div class="stat-card green">
                    <h3><?php echo number_format($grades_stats['max_grade'], 2); ?>/20</h3>
                    <p>أعلى نقطة</p>
                </div>
                <div class="stat-card red">
                    <h3><?php echo number_format($grades_stats['min_grade'], 2); ?>/20</h3>
                    <p>أقل نقطة</p>
                </div>
                <div class="stat-card orange">
                    <h3><?php echo number_format($grades_stats['avg_grade'], 2); ?>/20</h3>
                    <p>المتوسط العام</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>