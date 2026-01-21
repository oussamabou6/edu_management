<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// إضافة مادة جديدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subject'])) {
    $specialization_id = intval($_POST['specialization_id']);
    $subject_name = sanitize($_POST['subject_name']);
    $coefficient = intval($_POST['coefficient']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO subjects (institution_id, specialization_id, subject_name, coefficient) VALUES (?, ?, ?, ?)");
        $stmt->execute([$institution_id, $specialization_id, $subject_name, $coefficient]);
        $success = "تم إضافة المادة بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء إضافة المادة";
    }
}

// تحديث معامل مادة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_coefficient'])) {
    $subject_id = intval($_POST['subject_id']);
    $new_coefficient = intval($_POST['new_coefficient']);
    
    try {
        $stmt = $pdo->prepare("UPDATE subjects SET coefficient = ? WHERE id = ? AND institution_id = ?");
        $stmt->execute([$new_coefficient, $subject_id, $institution_id]);
        $success = "تم تحديث المعامل بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء التحديث";
    }
}

// حذف مادة
if (isset($_GET['delete'])) {
    $subject_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ? AND institution_id = ?");
        $stmt->execute([$subject_id, $institution_id]);
        $success = "تم حذف المادة بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء حذف المادة";
    }
}

// جلب السنوات والتخصصات
$stmt = $pdo->prepare("SELECT * FROM years WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$years = $stmt->fetchAll();

// جلب جميع المواد مع التخصصات والسنوات
$stmt = $pdo->prepare("
    SELECT s.*, sp.specialization_name, y.year_name 
    FROM subjects s
    JOIN specializations sp ON s.specialization_id = sp.id
    JOIN years y ON sp.year_id = y.id
    WHERE s.institution_id = ?
    ORDER BY y.year_name, sp.specialization_name, s.subject_name
");
$stmt->execute([$institution_id]);
$subjects = $stmt->fetchAll();

// تنظيم المواد حسب السنة والتخصص
$organized_subjects = [];
foreach ($subjects as $subject) {
    $key = $subject['year_name'] . ' - ' . $subject['specialization_name'];
    if (!isset($organized_subjects[$key])) {
        $organized_subjects[$key] = [];
    }
    $organized_subjects[$key][] = $subject;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المواد الدراسية</title>
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
            color: #667eea;
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
        
        label {
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        input, select {
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-primary {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
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
        
        .subjects-list {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .category-section {
            margin-bottom: 2rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .category-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem;
            border-radius: 8px;
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
            font-weight: 600;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .coefficient-badge {
            background: #4caf50;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        .btn-edit {
            padding: 0.5rem 1rem;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        
        .btn-delete {
            padding: 0.5rem 1rem;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="institution_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
            <h1>📚 إدارة المواد الدراسية</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="add-form">
            <h2 style="margin-bottom: 1.5rem;">➕ إضافة مادة دراسية</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>السنة الدراسية</label>
                        <select id="year_select" onchange="loadSpecializations()" required>
                            <option value="">اختر السنة</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year['id']; ?>">
                                    <?php echo htmlspecialchars($year['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>التخصص</label>
                        <select name="specialization_id" id="spec_select" required>
                            <option value="">اختر السنة أولاً</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>اسم المادة</label>
                        <input type="text" name="subject_name" required placeholder="مثال: الرياضيات">
                    </div>
                    
                    <div class="form-group">
                        <label>المعامل</label>
                        <input type="number" name="coefficient" required min="1" max="10" value="1">
                    </div>
                </div>
                
                <button type="submit" name="add_subject" class="btn-primary">
                    ➕ إضافة المادة
                </button>
            </form>
        </div>
        
        <div class="subjects-list">
            <h2 style="margin-bottom: 1.5rem;">قائمة المواد الدراسية (<?php echo count($subjects); ?>)</h2>
            
            <?php if (count($organized_subjects) > 0): ?>
                <?php foreach ($organized_subjects as $category => $category_subjects): ?>
                    <div class="category-section">
                        <div class="category-header">
                            <h3><?php echo htmlspecialchars($category); ?></h3>
                            <p style="opacity: 0.9; margin-top: 0.5rem;">عدد المواد: <?php echo count($category_subjects); ?></p>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>المادة</th>
                                    <th>المعامل</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($category_subjects as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                        <td>
                                            <span class="coefficient-badge">
                                                المعامل: <?php echo $subject['coefficient']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-edit" onclick="editCoefficient(<?php echo $subject['id']; ?>, <?php echo $subject['coefficient']; ?>, '<?php echo htmlspecialchars($subject['subject_name']); ?>')">
                                                ✏️ تعديل المعامل
                                            </button>
                                            <button class="btn-delete" onclick="if(confirm('حذف المادة؟')) location.href='?delete=<?php echo $subject['id']; ?>'">
                                                🗑️ حذف
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 3rem; color: #999;">
                    لا توجد مواد دراسية. ابدأ بإضافة أول مادة!
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function loadSpecializations() {
            const yearId = document.getElementById('year_select').value;
            const specSelect = document.getElementById('spec_select');
            
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
        
        function editCoefficient(subjectId, currentCoefficient, subjectName) {
            const newCoefficient = prompt(`تعديل معامل مادة "${subjectName}"\n\nالمعامل الحالي: ${currentCoefficient}\n\nأدخل المعامل الجديد (1-10):`, currentCoefficient);
            
            if (newCoefficient !== null && newCoefficient >= 1 && newCoefficient <= 10) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="subject_id" value="${subjectId}">
                    <input type="hidden" name="new_coefficient" value="${newCoefficient}">
                    <input type="hidden" name="update_coefficient" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>