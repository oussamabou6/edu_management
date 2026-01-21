<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

// التحقق من أن الأستاذ تابع للمؤسسة
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? AND institution_id = ?");
$stmt->execute([$teacher_id, $institution_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    header("Location: manage_teachers.php");
    exit();
}

// إضافة تعيين جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign'])) {
    $subject_id = intval($_POST['subject_id']);
    $year_id = intval($_POST['year_id']);
    $specialization_id = intval($_POST['specialization_id']);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO teacher_assignments (teacher_id, subject_id, year_id, specialization_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$teacher_id, $subject_id, $year_id, $specialization_id]);
        $success = "تم تعيين المادة للأستاذ بنجاح";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "هذه المادة مُعينة للأستاذ مسبقاً";
        } else {
            $error = "حدث خطأ أثناء التعيين";
        }
    }
}

// حذف تعيين
if (isset($_GET['delete_assignment'])) {
    $assignment_id = intval($_GET['delete_assignment']);
    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_assignments WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$assignment_id, $teacher_id]);
        $success = "تم إلغاء التعيين بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء إلغاء التعيين";
    }
}

// جلب السنوات
$stmt = $pdo->prepare("SELECT * FROM years WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$years = $stmt->fetchAll();

// جلب التعيينات الحالية
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعيين المواد - <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></title>
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
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .teacher-info {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
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
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        select {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        select:focus {
            outline: none;
            border-color: #11998e;
        }
        
        .btn-assign {
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
        
        .alert-error {
            background: #fee;
            color: #c33;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
        }
        
        .assignments-list {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .assignment-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .assignment-card:hover {
            border-color: #11998e;
        }
        
        .assignment-info h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .assignment-info p {
            color: #666;
            margin-bottom: 0.3rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: #4caf50;
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 0.5rem;
        }
        
        .btn-delete {
            padding: 0.7rem 1.5rem;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="manage_teachers.php" class="back-link">← العودة لإدارة الأساتذة</a>
            <h1>📚 تعيين المواد للأستاذ</h1>
        </div>
        
        <div class="teacher-info">
            <h2>👨‍🏫 الأستاذ: <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h2>
            <p style="opacity: 0.9; margin-top: 0.5rem;">رقم الهاتف: <?php echo htmlspecialchars($teacher['phone']); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="add-form">
            <h3 style="margin-bottom: 1.5rem;">➕ تعيين مادة جديدة</h3>
            <form method="POST" action="">
                <div class="form-grid">
                    <div>
                        <label>السنة الدراسية</label>
                        <select id="year_id" name="year_id" onchange="loadSpecializations()" required>
                            <option value="">اختر السنة</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year['id']; ?>">
                                    <?php echo htmlspecialchars($year['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>التخصص</label>
                        <select id="specialization_id" name="specialization_id" onchange="loadSubjects()" required>
                            <option value="">اختر السنة أولاً</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>المادة</label>
                        <select id="subject_id" name="subject_id" required>
                            <option value="">اختر التخصص أولاً</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="assign" class="btn-assign">
                    ➕ تعيين المادة
                </button>
            </form>
        </div>
        
        <div class="assignments-list">
            <h3 style="margin-bottom: 1.5rem;">المواد المعينة (<?php echo count($assignments); ?>)</h3>
            
            <?php if (count($assignments) > 0): ?>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card">
                        <div class="assignment-info">
                            <h4>📖 <?php echo htmlspecialchars($assignment['subject_name']); ?></h4>
                            <p>
                                <strong>السنة:</strong> <?php echo htmlspecialchars($assignment['year_name']); ?>
                                <span class="badge">المعامل: <?php echo $assignment['coefficient']; ?></span>
                            </p>
                            <p><strong>التخصص:</strong> <?php echo htmlspecialchars($assignment['specialization_name']); ?></p>
                        </div>
                        <button class="btn-delete" onclick="if(confirm('إلغاء هذا التعيين؟')) location.href='?id=<?php echo $teacher_id; ?>&delete_assignment=<?php echo $assignment['id']; ?>'">
                            🗑️ إلغاء التعيين
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 3rem; color: #999;">
                    لم يتم تعيين أي مواد لهذا الأستاذ بعد
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function loadSpecializations() {
            const yearId = document.getElementById('year_id').value;
            const specSelect = document.getElementById('specialization_id');
            const subjectSelect = document.getElementById('subject_id');
            
            subjectSelect.innerHTML = '<option value="">اختر التخصص أولاً</option>';
            
            if (!yearId) {
                specSelect.innerHTML = '<option value="">اختر السنة أولاً</option>';
                return;
            }
            
            fetch(`get_specializations.php?year_id=${yearId}`)
                .then(response => response.json())
                .then(data => {
                    specSelect.innerHTML = '<option value="">اختر التخصص</option>';
                    data.forEach(spec => {
                        specSelect.innerHTML += `<option value="${spec.id}">${spec.specialization_name}</option>`;
                    });
                });
        }
        
        function loadSubjects() {
            const specId = document.getElementById('specialization_id').value;
            const subjectSelect = document.getElementById('subject_id');
            
            if (!specId) {
                subjectSelect.innerHTML = '<option value="">اختر التخصص أولاً</option>';
                return;
            }
            
            fetch(`get_subjects.php?specialization_id=${specId}`)
                .then(response => response.json())
                .then(data => {
                    subjectSelect.innerHTML = '<option value="">اختر المادة</option>';
                    data.forEach(subject => {
                        subjectSelect.innerHTML += `<option value="${subject.id}">${subject.subject_name} (المعامل: ${subject.coefficient})</option>`;
                    });
                });
        }
    </script>
</body>
</html>