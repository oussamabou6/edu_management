<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$institution_id = $_SESSION['institution_id'];
$error = '';
$success = '';

// إضافة نقطة جديدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_grade'])) {
    $student_id = intval($_POST['student_id']);
    $subject_id = intval($_POST['subject_id']);
    $grade_type = sanitize($_POST['grade_type']);
    $grade = floatval($_POST['grade']);
    $max_grade = floatval($_POST['max_grade']);
    $exam_date = sanitize($_POST['exam_date']);
    $notes = sanitize($_POST['notes']);
    
    if ($grade <= $max_grade) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, subject_id, teacher_id, grade_type, grade, max_grade, exam_date, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $subject_id, $teacher_id, $grade_type, $grade, $max_grade, $exam_date, $notes ?: null]);
            
            // إرسال إشعار للولي إذا كانت النقطة ضعيفة
            if ($grade < ($max_grade / 2)) {
                $msg = "تنبيه: حصل ابنكم على نقطة ضعيفة في $grade_type: $grade/$max_grade";
                sendNotificationToParent($pdo, $student_id, 'نقطة ضعيفة', $msg);
            }
            
            $success = "تم إضافة النقطة بنجاح";
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء إضافة النقطة";
        }
    } else {
        $error = "النقطة لا يمكن أن تكون أكبر من النقطة القصوى";
    }
}

// جلب المواد المكلف بها الأستاذ
$stmt = $pdo->prepare("
    SELECT 
        ta.*, 
        s.subject_name, 
        y.year_name,
        sp.specialization_name
    FROM teacher_assignments ta
    JOIN subjects s ON ta.subject_id = s.id
    JOIN years y ON ta.year_id = y.id
    JOIN specializations sp ON ta.specialization_id = sp.id
    WHERE ta.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll();

// جلب آخر النقاط المضافة
$stmt = $pdo->prepare("
    SELECT 
        g.*,
        s.first_name, s.last_name,
        sub.subject_name
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.teacher_id = ?
    ORDER BY g.created_at DESC
    LIMIT 20
");
$stmt->execute([$teacher_id]);
$recent_grades = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة النقاط</title>
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
            max-width: 1200px;
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
            color: #11998e;
            text-decoration: none;
            font-weight: 600;
        }
        
        .add-form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        input, select, textarea {
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #11998e;
        }
        
        .btn-add {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
        }
        
        .grades-list {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="teacher_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
            <h1 style="color: #333;">📝 إدارة النقاط والتقييمات</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="add-form">
            <h2 style="margin-bottom: 1.5rem; color: #333;">➕ إضافة نقطة جديدة</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>المادة</label>
                        <select name="subject_id" id="subject_select" onchange="loadStudents()" required>
                            <option value="">اختر المادة</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option value="<?php echo $assignment['subject_id']; ?>" 
                                        data-year="<?php echo $assignment['year_id']; ?>"
                                        data-spec="<?php echo $assignment['specialization_id']; ?>">
                                    <?php echo htmlspecialchars($assignment['subject_name'] . ' - ' . $assignment['year_name'] . ' - ' . $assignment['specialization_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>التلميذ</label>
                        <select name="student_id" id="student_select" required>
                            <option value="">اختر المادة أولاً</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>نوع التقييم</label>
                        <select name="grade_type" required>
                            <option value="فرض">فرض</option>
                            <option value="اختبار">اختبار</option>
                            <option value="امتحان">امتحان</option>
                            <option value="مشاركة">مشاركة</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>التاريخ</label>
                        <input type="date" name="exam_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>النقطة المحصل عليها</label>
                        <input type="number" name="grade" step="0.01" min="0" max="20" required placeholder="مثال: 15.50">
                    </div>
                    
                    <div class="form-group">
                        <label>النقطة القصوى</label>
                        <input type="number" name="max_grade" step="0.01" min="0" value="20" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>ملاحظات (اختياري)</label>
                        <textarea name="notes" rows="2" placeholder="أضف ملاحظات إضافية..."></textarea>
                    </div>
                </div>
                
                <button type="submit" name="add_grade" class="btn-add">
                    ➕ إضافة النقطة
                </button>
            </form>
        </div>
        
        <div class="grades-list">
            <h2 style="margin-bottom: 1.5rem; color: #333;">آخر النقاط المضافة</h2>
            
            <?php if (count($recent_grades) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>التلميذ</th>
                            <th>المادة</th>
                            <th>نوع التقييم</th>
                            <th>النقطة</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_grades as $grade): ?>
                            <?php 
                                $percentage = ($grade['grade'] / $grade['max_grade']) * 100;
                                $grade_class = 'grade-medium';
                                if ($percentage >= 75) $grade_class = 'grade-good';
                                elseif ($percentage < 50) $grade_class = 'grade-bad';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($grade['grade_type']); ?></td>
                                <td class="<?php echo $grade_class; ?>">
                                    <?php echo $grade['grade']; ?> / <?php echo $grade['max_grade']; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($grade['exam_date'])); ?></td>
                                <td><?php echo htmlspecialchars($grade['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 3rem; color: #999;">
                    لم تقم بإضافة أي نقاط بعد
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function loadStudents() {
            const subjectSelect = document.getElementById('subject_select');
            const studentSelect = document.getElementById('student_select');
            const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
            
            if (!selectedOption.value) {
                studentSelect.innerHTML = '<option value="">اختر المادة أولاً</option>';
                return;
            }
            
            const yearId = selectedOption.dataset.year;
            const specId = selectedOption.dataset.spec;
            
            fetch(`get_class_students.php?year=${yearId}&spec=${specId}`)
                .then(response => response.json())
                .then(data => {
                    studentSelect.innerHTML = '<option value="">اختر التلميذ</option>';
                    data.forEach(student => {
                        studentSelect.innerHTML += `<option value="${student.id}">${student.first_name} ${student.last_name}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    studentSelect.innerHTML = '<option value="">خطأ في تحميل التلاميذ</option>';
                });
        }
    </script>
</body>
</html>