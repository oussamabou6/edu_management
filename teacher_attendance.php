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

// حفظ الحضور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $subject_id = intval($_POST['subject_id']);
    $date = sanitize($_POST['date']);
    $attendance_data = $_POST['attendance'] ?? [];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($attendance_data as $student_id => $status) {
        try {
            // حذف السجل القديم إن وجد
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id = ? AND subject_id = ? AND date = ?");
            $stmt->execute([$student_id, $subject_id, $date]);
            
            // إضافة السجل الجديد
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, subject_id, date, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $subject_id, $date, $status]);
            
            // إرسال إشعار للولي في حالة الغياب
            if ($status == 'غائب') {
                sendNotificationToParent($pdo, $student_id, 'غياب', "تنبيه: غاب ابنكم عن الحصة الدراسية بتاريخ $date");
            }
            
            $success_count++;
        } catch (PDOException $e) {
            $error_count++;
        }
    }
    
    if ($success_count > 0) {
        $success = "تم حفظ الحضور ل $success_count تلميذ بنجاح";
    }
    if ($error_count > 0) {
        $error = "حدث خطأ في حفظ $error_count سجل";
    }
}

// جلب المواد المكلف بها
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

// إذا تم اختيار مادة وتاريخ
$selected_subject = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$selected_date = isset($_POST['date']) ? sanitize($_POST['date']) : date('Y-m-d');
$students = [];

if ($selected_subject) {
    // جلب معلومات المادة المحددة
    $stmt = $pdo->prepare("
        SELECT year_id, specialization_id 
        FROM teacher_assignments 
        WHERE teacher_id = ? AND subject_id = ?
    ");
    $stmt->execute([$teacher_id, $selected_subject]);
    $subject_info = $stmt->fetch();
    
    if ($subject_info) {
        // جلب التلاميذ
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name 
            FROM students 
            WHERE year_id = ? AND specialization_id = ? AND institution_id = ?
            ORDER BY last_name, first_name
        ");
        $stmt->execute([$subject_info['year_id'], $subject_info['specialization_id'], $institution_id]);
        $students = $stmt->fetchAll();
        
        // جلب الحضور المسجل مسبقاً لهذا التاريخ
        $stmt = $pdo->prepare("
            SELECT student_id, status 
            FROM attendance 
            WHERE subject_id = ? AND date = ?
        ");
        $stmt->execute([$selected_subject, $selected_date]);
        $previous_attendance = [];
        foreach ($stmt->fetchAll() as $row) {
            $previous_attendance[$row['student_id']] = $row['status'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الحضور</title>
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
            max-width: 1000px;
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
        
        .select-form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        select, input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #11998e;
        }
        
        .btn-load {
            padding: 0.9rem 2rem;
            background: #11998e;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
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
        
        .attendance-form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .student-row {
            display: grid;
            grid-template-columns: 50px 1fr auto;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            align-items: center;
        }
        
        .student-row:hover {
            background: #f9f9f9;
        }
        
        .student-row .number {
            font-weight: bold;
            color: #666;
        }
        
        .student-row .name {
            font-weight: 600;
            color: #333;
        }
        
        .status-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .status-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .status-btn:hover {
            transform: translateY(-2px);
        }
        
        .status-btn.selected-present {
            background: #4caf50;
            color: white;
            border-color: #4caf50;
        }
        
        .status-btn.selected-absent {
            background: #f44336;
            color: white;
            border-color: #f44336;
        }
        
        .status-btn.selected-excused {
            background: #ff9800;
            color: white;
            border-color: #ff9800;
        }
        
        .btn-save {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: 2rem;
        }
        
        .quick-actions {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
        }
        
        .quick-btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .quick-all-present {
            background: #4caf50;
            color: white;
        }
        
        .quick-all-absent {
            background: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="teacher_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
            <h1 style="color: #333;">✅ تسجيل الحضور والغياب</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="select-form">
            <h3 style="margin-bottom: 1rem; color: #333;">اختر المادة والتاريخ</h3>
            <form method="POST" action="">
                <div class="form-grid">
                    <div>
                        <label>المادة</label>
                        <select name="subject_id" required>
                            <option value="">اختر المادة</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option value="<?php echo $assignment['subject_id']; ?>" 
                                        <?php echo $selected_subject == $assignment['subject_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($assignment['subject_name'] . ' - ' . $assignment['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>التاريخ</label>
                        <input type="date" name="date" value="<?php echo $selected_date; ?>" required>
                    </div>
                    
                    <button type="submit" class="btn-load">تحميل القائمة</button>
                </div>
            </form>
        </div>
        
        <?php if (count($students) > 0): ?>
            <div class="attendance-form">
                <div class="quick-actions">
                    <button class="quick-btn quick-all-present" onclick="markAll('حاضر')">
                        ✅ تعليم الكل حاضر
                    </button>
                    <button class="quick-btn quick-all-absent" onclick="markAll('غائب')">
                        ❌ تعليم الكل غائب
                    </button>
                </div>
                
                <h3 style="margin-bottom: 1rem; color: #333;">قائمة التلاميذ (<?php echo count($students); ?>)</h3>
                
                <form method="POST" action="" id="attendanceForm">
                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                    <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                    
                    <?php foreach ($students as $index => $student): ?>
                        <?php 
                            $previous_status = isset($previous_attendance[$student['id']]) ? $previous_attendance[$student['id']] : '';
                        ?>
                        <div class="student-row">
                            <div class="number"><?php echo $index + 1; ?></div>
                            <div class="name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                            <div class="status-buttons">
                                <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                       value="حاضر" id="present_<?php echo $student['id']; ?>" 
                                       <?php echo $previous_status == 'حاضر' ? 'checked' : ''; ?> hidden>
                                <label for="present_<?php echo $student['id']; ?>" 
                                       class="status-btn <?php echo $previous_status == 'حاضر' ? 'selected-present' : ''; ?>">
                                    ✅ حاضر
                                </label>
                                
                                <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                       value="غائب" id="absent_<?php echo $student['id']; ?>" 
                                       <?php echo $previous_status == 'غائب' ? 'checked' : ''; ?> hidden>
                                <label for="absent_<?php echo $student['id']; ?>" 
                                       class="status-btn <?php echo $previous_status == 'غائب' ? 'selected-absent' : ''; ?>">
                                    ❌ غائب
                                </label>
                                
                                <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                       value="غياب بعذر" id="excused_<?php echo $student['id']; ?>" 
                                       <?php echo $previous_status == 'غياب بعذر' ? 'checked' : ''; ?> hidden>
                                <label for="excused_<?php echo $student['id']; ?>" 
                                       class="status-btn <?php echo $previous_status == 'غياب بعذر' ? 'selected-excused' : ''; ?>">
                                    📝 بعذر
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" name="save_attendance" class="btn-save">
                        💾 حفظ الحضور
                    </button>
                </form>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <div style="background: white; padding: 3rem; border-radius: 15px; text-align: center; color: #999;">
                <h3>لا يوجد تلاميذ في هذا الصف</h3>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // تحديث الأزرار عند النقر
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('.student-row');
                row.querySelectorAll('.status-btn').forEach(b => {
                    b.classList.remove('selected-present', 'selected-absent', 'selected-excused');
                });
                
                const input = document.getElementById(this.getAttribute('for'));
                if (input.value === 'حاضر') {
                    this.classList.add('selected-present');
                } else if (input.value === 'غائب') {
                    this.classList.add('selected-absent');
                } else {
                    this.classList.add('selected-excused');
                }
            });
        });
        
        function markAll(status) {
            document.querySelectorAll('.student-row').forEach(row => {
                const radios = row.querySelectorAll('input[type="radio"]');
                radios.forEach(radio => {
                    if (radio.value === status) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change'));
                        
                        const label = document.querySelector(`label[for="${radio.id}"]`);
                        label.click();
                    }
                });
            });
        }
    </script>
</body>
</html>