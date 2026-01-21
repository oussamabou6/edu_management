<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// المرحلة الحالية
$step = isset($_POST['step']) ? intval($_POST['step']) : 1;

// حفظ بيانات الصف
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_class_info'])) {
    $_SESSION['class_info'] = [
        'year_id' => intval($_POST['year_id']),
        'specialization_id' => intval($_POST['specialization_id']),
        'academic_year' => sanitize($_POST['academic_year']),
        'student_count' => intval($_POST['student_count']),
        'current_student' => 0
    ];
    $step = 2;
}

// حفظ بيانات تلميذ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_single_student'])) {
    $class_info = $_SESSION['class_info'];
    
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $father_name = sanitize($_POST['father_name']);
    $mother_first_name = sanitize($_POST['mother_first_name']);
    $mother_last_name = sanitize($_POST['mother_last_name']);
    $siblings_count = intval($_POST['siblings_count']);
    $phone_primary = sanitize($_POST['phone_primary']);
    $phone_secondary = sanitize($_POST['phone_secondary']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // التحقق من عدم تكرار رقم الهاتف
    $stmt = $pdo->prepare("SELECT id FROM students WHERE phone_primary = ?");
    $stmt->execute([$phone_primary]);
    
    if ($stmt->fetch()) {
        $error = "رقم الهاتف مسجل من قبل";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO students 
                (institution_id, year_id, specialization_id, first_name, last_name, 
                father_name, mother_first_name, mother_last_name, siblings_count, 
                phone_primary, phone_secondary, email, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $institution_id, 
                $class_info['year_id'], 
                $class_info['specialization_id'], 
                $first_name, 
                $last_name,
                $father_name, 
                $mother_first_name, 
                $mother_last_name, 
                $siblings_count,
                $phone_primary, 
                $phone_secondary ?: null, 
                $email ?: null, 
                $password
            ]);
            
            $_SESSION['class_info']['current_student']++;
            $success = "تم إضافة التلميذ رقم " . $_SESSION['class_info']['current_student'] . " بنجاح!";
            
            // حفظ البيانات المضافة
            if (!isset($_SESSION['added_students'])) {
                $_SESSION['added_students'] = [];
            }
            $_SESSION['added_students'][] = [
                'name' => $first_name . ' ' . $last_name,
                'phone' => $phone_primary,
                'father' => $father_name
            ];
            
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء إضافة التلميذ";
        }
    }
}

// إنهاء وبدء قسم جديد
if (isset($_POST['finish_and_new'])) {
    $_SESSION['finished_classes'][] = $_SESSION['class_info'];
    unset($_SESSION['class_info']);
    unset($_SESSION['added_students']);
    $step = 1;
    $success = "تم إنهاء القسم بنجاح! يمكنك البدء بقسم جديد";
}

// إنهاء نهائي
if (isset($_POST['finish_all'])) {
    $total_added = $_SESSION['class_info']['current_student'] ?? 0;
    unset($_SESSION['class_info']);
    unset($_SESSION['added_students']);
    unset($_SESSION['finished_classes']);
    header("Location: manage_students.php?success=تم إضافة $total_added تلميذ بنجاح");
    exit();
}

// جلب السنوات والتخصصات
$stmt = $pdo->prepare("SELECT * FROM years WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$years = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة تلاميذ - نظام متقدم</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 0.7rem 1.5rem;
            border-radius: 10px;
        }
        
        .progress-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .progress-line {
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 3px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .progress-line-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: width 0.3s;
        }
        
        .step {
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 3px solid #e0e0e0;
            position: relative;
            z-index: 2;
        }
        
        .step.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }
        
        .step.completed {
            background: #4caf50;
            color: white;
            border-color: #4caf50;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .required {
            color: red;
        }
        
        input, select {
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 2px solid #4caf50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 2px solid #f44336;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 1.5rem;
            border-radius: 10px;
            border-right: 4px solid #2196f3;
            margin-bottom: 1.5rem;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        
        .counter {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .counter h3 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        
        .added-students-list {
            background: #f5f7fa;
            padding: 1rem;
            border-radius: 10px;
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }
        
        .student-item {
            padding: 0.8rem;
            background: white;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="manage_students.php" class="back-link">← العودة لإدارة التلاميذ</a>
        
        <div class="progress-bar">
            <div class="progress-steps">
                <div class="progress-line">
                    <div class="progress-line-fill" style="width: <?php echo $step == 1 ? '0%' : '100%'; ?>"></div>
                </div>
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 1rem;">
                <span style="font-weight: 600;">معلومات القسم</span>
                <span style="font-weight: 600;">إضافة التلاميذ</span>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <!-- المرحلة الأولى: معلومات القسم -->
            <div class="card">
                <h2>📋 الخطوة 1: تحديد معلومات القسم</h2>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>السنة الدراسية <span class="required">*</span></label>
                            <select name="year_id" id="year_id" required onchange="loadSpecializations()">
                                <option value="">اختر السنة</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>">
                                        <?php echo htmlspecialchars($year['year_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>التخصص <span class="required">*</span></label>
                            <select name="specialization_id" id="specialization_id" required>
                                <option value="">اختر السنة أولاً</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>العام الدراسي <span class="required">*</span></label>
                            <input type="text" name="academic_year" required 
                                   placeholder="مثال: 2025/2026" 
                                   value="<?php echo date('Y') . '/' . (date('Y') + 1); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>عدد التلاميذ في القسم <span class="required">*</span></label>
                            <input type="number" name="student_count" required min="1" max="50" 
                                   placeholder="أدخل العدد">
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h4>💡 معلومات مهمة</h4>
                        <p>• حدد السنة والتخصص والعام الدراسي لهذا القسم</p>
                        <p>• أدخل عدد التلاميذ الذين تريد إضافتهم</p>
                        <p>• سيتم إضافة التلاميذ واحداً تلو الآخر في الخطوة التالية</p>
                    </div>
                    
                    <button type="submit" name="save_class_info" class="btn btn-primary" style="width: 100%;">
                        التالي: بدء إضافة التلاميذ ←
                    </button>
                </form>
            </div>
            
        <?php elseif ($step == 2): ?>
            <!-- المرحلة الثانية: إضافة التلاميذ -->
            <?php 
                $class_info = $_SESSION['class_info'];
                $remaining = $class_info['student_count'] - $class_info['current_student'];
                
                // جلب معلومات السنة والتخصص
                $stmt = $pdo->prepare("SELECT year_name FROM years WHERE id = ?");
                $stmt->execute([$class_info['year_id']]);
                $year_name = $stmt->fetch()['year_name'];
                
                $stmt = $pdo->prepare("SELECT specialization_name FROM specializations WHERE id = ?");
                $stmt->execute([$class_info['specialization_id']]);
                $spec_name = $stmt->fetch()['specialization_name'];
            ?>
            
            <div class="counter">
                <h3><?php echo $class_info['current_student']; ?> / <?php echo $class_info['student_count']; ?></h3>
                <p>تلميذ مضاف</p>
            </div>
            
            <div class="card">
                <h2>👨‍🎓 إضافة تلميذ رقم <?php echo $class_info['current_student'] + 1; ?></h2>
                
                <div class="info-box">
                    <p><strong>📚 القسم:</strong> <?php echo htmlspecialchars($year_name . ' - ' . $spec_name); ?></p>
                    <p><strong>📅 العام الدراسي:</strong> <?php echo htmlspecialchars($class_info['academic_year']); ?></p>
                    <p><strong>📊 متبقي:</strong> <?php echo $remaining; ?> تلميذ</p>
                </div>
                
                <?php if (isset($_SESSION['added_students']) && count($_SESSION['added_students']) > 0): ?>
                    <h4 style="margin-bottom: 0.5rem;">التلاميذ المضافون:</h4>
                    <div class="added-students-list">
                        <?php foreach ($_SESSION['added_students'] as $index => $st): ?>
                            <div class="student-item">
                                <span>✅ <?php echo htmlspecialchars($st['name']); ?></span>
                                <span style="color: #666;"><?php echo htmlspecialchars($st['phone']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($remaining > 0): ?>
                    <form method="POST" action="" id="studentForm">
                        <input type="hidden" name="step" value="2">
                        
                        <h3 style="margin-bottom: 1rem; color: #667eea;">معلومات التلميذ الشخصية</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>الاسم <span class="required">*</span></label>
                                <input type="text" name="first_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label>اللقب <span class="required">*</span></label>
                                <input type="text" name="last_name" required>
                            </div>
                        </div>
                        
                        <h3 style="margin: 1.5rem 0 1rem; color: #667eea;">معلومات العائلة</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>اسم الأب <span class="required">*</span></label>
                                <input type="text" name="father_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label>اسم الأم <span class="required">*</span></label>
                                <input type="text" name="mother_first_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label>لقب الأم <span class="required">*</span></label>
                                <input type="text" name="mother_last_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label>عدد الإخوة <span class="required">*</span></label>
                                <input type="number" name="siblings_count" value="0" min="0" required>
                            </div>
                        </div>
                        
                        <h3 style="margin: 1.5rem 0 1rem; color: #667eea;">معلومات الاتصال</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>رقم الهاتف (للدخول) <span class="required">*</span></label>
                                <input type="tel" name="phone_primary" required placeholder="0550123456">
                            </div>
                            
                            <div class="form-group">
                                <label>رقم هاتف ثاني (اختياري)</label>
                                <input type="tel" name="phone_secondary" placeholder="0660123456">
                            </div>
                            
                            <div class="form-group">
                                <label>البريد الإلكتروني (اختياري)</label>
                                <input type="email" name="email" placeholder="student@example.com">
                            </div>
                            
                            <div class="form-group">
                                <label>كلمة المرور <span class="required">*</span></label>
                                <input type="password" name="password" required minlength="6">
                            </div>
                        </div>
                        
                        <button type="submit" name="add_single_student" class="btn btn-primary" style="width: 100%;">
                            ✅ حفظ والانتقال للتلميذ التالي
                        </button>
                    </form>
                <?php else: ?>
                    <div class="info-box">
                        <h4>✅ تم إضافة جميع التلاميذ!</h4>
                        <p>تم إضافة <?php echo $class_info['student_count']; ?> تلميذ بنجاح.</p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" style="margin-top: 1rem;">
                    <div class="btn-group">
                        <button type="submit" name="finish_and_new" class="btn btn-warning">
                            ➕ إنهاء وبدء قسم جديد
                        </button>
                        <button type="submit" name="finish_all" class="btn btn-success">
                            ✅ إنهاء والعودة للقائمة
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function loadSpecializations() {
            const yearId = document.getElementById('year_id').value;
            const specSelect = document.getElementById('specialization_id');
            
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
                })
                .catch(error => {
                    console.error('Error:', error);
                    specSelect.innerHTML = '<option value="">خطأ في تحميل التخصصات</option>';
                });
        }
        
        // تركيز تلقائي على أول حقل
        <?php if ($step == 2 && $remaining > 0): ?>
            document.querySelector('input[name="first_name"]').focus();
        <?php endif; ?>
    </script>
</body>
</html>