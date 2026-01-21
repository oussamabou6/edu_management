<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// جلب معلومات المؤسسة
$stmt = $pdo->prepare("SELECT * FROM institutions WHERE id = ?");
$stmt->execute([$institution_id]);
$institution = $stmt->fetch();

// تحديث معلومات المؤسسة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    if (!$institution['is_locked']) {
        $name = sanitize($_POST['name']);
        $type = sanitize($_POST['type']);
        $address = sanitize($_POST['address']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE institutions 
                SET name = ?, type = ?, address = ?, phone = ?, email = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $type, $address, $phone, $email, $institution_id]);
            $success = "تم تحديث معلومات المؤسسة بنجاح";
            
            // تحديث البيانات المعروضة
            $stmt = $pdo->prepare("SELECT * FROM institutions WHERE id = ?");
            $stmt->execute([$institution_id]);
            $institution = $stmt->fetch();
            $_SESSION['institution_name'] = $institution['name'];
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء التحديث";
        }
    } else {
        $error = "تم قفل المعلومات الأساسية ولا يمكن تعديلها";
    }
}

// قفل المعلومات الأساسية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lock_settings'])) {
    try {
        $stmt = $pdo->prepare("UPDATE institutions SET is_locked = TRUE WHERE id = ?");
        $stmt->execute([$institution_id]);
        $success = "تم قفل المعلومات الأساسية بنجاح. لن تتمكن من تعديلها بعد الآن!";
        $institution['is_locked'] = true;
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء القفل";
    }
}

// تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (password_verify($current_password, $institution['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE institutions SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $institution_id]);
                    $success = "تم تغيير كلمة المرور بنجاح";
                } catch (PDOException $e) {
                    $error = "حدث خطأ أثناء تغيير كلمة المرور";
                }
            } else {
                $error = "كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل";
            }
        } else {
            $error = "كلمة المرور الجديدة وتأكيدها غير متطابقين";
        }
    } else {
        $error = "كلمة المرور الحالية غير صحيحة";
    }
}

// حذف جميع البيانات (خطير!)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_all_data'])) {
    $confirm_text = $_POST['confirm_delete'];
    
    if ($confirm_text === 'DELETE ALL DATA') {
        try {
            // حذف جميع البيانات المرتبطة
            $pdo->prepare("DELETE FROM parent_notifications WHERE student_id IN (SELECT id FROM students WHERE institution_id = ?)")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM grades WHERE student_id IN (SELECT id FROM students WHERE institution_id = ?)")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM attendance WHERE student_id IN (SELECT id FROM students WHERE institution_id = ?)")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM entry_exit WHERE student_id IN (SELECT id FROM students WHERE institution_id = ?)")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM students WHERE institution_id = ?")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM teacher_assignments WHERE teacher_id IN (SELECT id FROM teachers WHERE institution_id = ?)")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM teachers WHERE institution_id = ?")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM subjects WHERE institution_id = ?")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM specializations WHERE institution_id = ?")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM years WHERE institution_id = ?")->execute([$institution_id]);
            $pdo->prepare("DELETE FROM announcements WHERE institution_id = ?")->execute([$institution_id]);
            
            $success = "تم حذف جميع البيانات بنجاح";
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء حذف البيانات";
        }
    } else {
        $error = "يجب كتابة 'DELETE ALL DATA' بالضبط للتأكيد";
    }
}

// إحصائيات الاستخدام
$stats = [];

// الجداول التي تحتوي على institution_id مباشرة
$direct_tables = ['students', 'teachers', 'years', 'specializations', 'subjects', 'announcements'];
foreach ($direct_tables as $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE institution_id = ?");
    $stmt->execute([$institution_id]);
    $stats[$table] = $stmt->fetch()['count'];
}

// جدول النقاط - يحتاج join مع جدول students
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE s.institution_id = ?
");
$stmt->execute([$institution_id]);
$stats['grades'] = $stmt->fetch()['count'];

// جدول الحضور - يحتاج join مع جدول students
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM attendance a
    JOIN students st ON a.student_id = st.id
    WHERE st.institution_id = ?
");
$stmt->execute([$institution_id]);
$stats['attendance'] = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - <?php echo htmlspecialchars($institution['name']); ?></title>
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
        
        h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 1rem 2rem;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .tab:hover {
            border-color: #667eea;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .settings-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .settings-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        input:disabled, select:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
            font-size: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
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
        
        .btn-danger {
            background: linear-gradient(135deg, #eb3349, #f45c43);
            color: white;
        }
        
        .locked-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #ffeaa7;
            color: #856404;
            border-radius: 20px;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            opacity: 0.9;
        }
        
        .danger-zone {
            border: 3px solid #f44336;
            background: #ffebee;
            padding: 2rem;
            border-radius: 15px;
            margin-top: 2rem;
        }
        
        .danger-zone h3 {
            color: #c62828;
            margin-bottom: 1rem;
        }
        
        .warning-text {
            color: #d32f2f;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .confirm-box {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
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
        
        .info-box p {
            color: #0d47a1;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="institution_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
            <h1>⚙️ إعدادات المؤسسة</h1>
            <p style="color: #666;">إدارة إعدادات وتكوين المؤسسة التربوية</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('general')">📋 معلومات عامة</div>
            <div class="tab" onclick="switchTab('security')">🔒 الأمان</div>
            <div class="tab" onclick="switchTab('stats')">📊 الإحصائيات</div>
            <div class="tab" onclick="switchTab('danger')">⚠️ المنطقة الخطرة</div>
        </div>
        
        <!-- General Settings Tab -->
        <div id="general" class="tab-content active">
            <div class="settings-section">
                <h2>
                    معلومات المؤسسة الأساسية
                    <?php if ($institution['is_locked']): ?>
                        <span class="locked-badge">🔒 مقفلة</span>
                    <?php endif; ?>
                </h2>
                
                <?php if (!$institution['is_locked']): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ تنبيه:</strong> بعد قفل المعلومات الأساسية، لن تتمكن من تعديلها مرة أخرى. تأكد من صحة جميع البيانات قبل القفل.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>اسم المؤسسة</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($institution['name']); ?>" 
                                   required <?php echo $institution['is_locked'] ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>نوع المؤسسة</label>
                            <select name="type" required <?php echo $institution['is_locked'] ? 'disabled' : ''; ?>>
                                <option value="ابتدائي" <?php echo $institution['type'] == 'ابتدائي' ? 'selected' : ''; ?>>ابتدائي</option>
                                <option value="متوسط" <?php echo $institution['type'] == 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                                <option value="ثانوي" <?php echo $institution['type'] == 'ثانوي' ? 'selected' : ''; ?>>ثانوي</option>
                                <option value="مدرسة خاصة" <?php echo $institution['type'] == 'مدرسة خاصة' ? 'selected' : ''; ?>>مدرسة خاصة</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>رقم الهاتف</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($institution['phone']); ?>" 
                                   <?php echo $institution['is_locked'] ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label>البريد الإلكتروني</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($institution['email']); ?>" 
                                   required <?php echo $institution['is_locked'] ? 'disabled' : ''; ?>>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>العنوان</label>
                            <textarea name="address" rows="3" <?php echo $institution['is_locked'] ? 'disabled' : ''; ?>><?php echo htmlspecialchars($institution['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <?php if (!$institution['is_locked']): ?>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="submit" name="update_info" class="btn btn-primary">
                                💾 حفظ التغييرات
                            </button>
                            <button type="submit" name="lock_settings" class="btn btn-warning" 
                                    onclick="return confirm('هل أنت متأكد؟ لن تتمكن من تعديل المعلومات الأساسية بعد القفل!')">
                                🔒 قفل المعلومات الأساسية
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <h4>تم قفل المعلومات الأساسية</h4>
                            <p>لا يمكن تعديل معلومات المؤسسة الأساسية بعد القفل للحفاظ على سلامة البيانات.</p>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="settings-section">
                <h2>تاريخ الإنشاء والمعلومات الإضافية</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>تاريخ إنشاء الحساب</label>
                        <input type="text" value="<?php echo date('Y-m-d H:i', strtotime($institution['created_at'])); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>حالة القفل</label>
                        <input type="text" value="<?php echo $institution['is_locked'] ? '🔒 مقفل' : '🔓 غير مقفل'; ?>" disabled>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security Tab -->
        <div id="security" class="tab-content">
            <div class="settings-section">
                <h2>🔐 تغيير كلمة المرور</h2>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>كلمة المرور الحالية</label>
                            <input type="password" name="current_password" required placeholder="أدخل كلمة المرور الحالية">
                        </div>
                        
                        <div class="form-group">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" required minlength="6" placeholder="6 أحرف على الأقل">
                        </div>
                        
                        <div class="form-group">
                            <label>تأكيد كلمة المرور الجديدة</label>
                            <input type="password" name="confirm_password" required minlength="6" placeholder="أعد إدخال كلمة المرور">
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-success">
                        🔑 تغيير كلمة المرور
                    </button>
                </form>
            </div>
            
            <div class="settings-section">
                <h2>🛡️ نصائح الأمان</h2>
                <div class="info-box">
                    <h4>حماية حسابك</h4>
                    <ul style="color: #0d47a1; line-height: 2;">
                        <li>استخدم كلمة مرور قوية تحتوي على أحرف وأرقام ورموز</li>
                        <li>لا تشارك كلمة المرور مع أي شخص</li>
                        <li>قم بتغيير كلمة المرور بشكل دوري</li>
                        <li>تأكد من تسجيل الخروج عند استخدام جهاز مشترك</li>
                        <li>احفظ بيانات الدخول في مكان آمن</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Statistics Tab -->
        <div id="stats" class="tab-content">
            <div class="settings-section">
                <h2>📊 إحصائيات الاستخدام</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $stats['students']; ?></h3>
                        <p>التلاميذ المسجلين</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['teachers']; ?></h3>
                        <p>الأساتذة</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['years']; ?></h3>
                        <p>السنوات الدراسية</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['specializations']; ?></h3>
                        <p>التخصصات</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['subjects']; ?></h3>
                        <p>المواد الدراسية</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['grades']; ?></h3>
                        <p>النقاط المسجلة</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['attendance']; ?></h3>
                        <p>سجلات الحضور</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['announcements']; ?></h3>
                        <p>الإعلانات المنشورة</p>
                    </div>
                </div>
            </div>
            
            <div class="settings-section">
                <h2>📈 تحليل البيانات</h2>
                <div class="info-box">
                    <h4>ملخص النظام</h4>
                    <p style="margin-bottom: 1rem;">يحتوي نظامك على بيانات شاملة تساعدك في إدارة المؤسسة بكفاءة عالية.</p>
                    <p><strong>إجمالي السجلات:</strong> <?php echo array_sum($stats); ?> سجل</p>
                </div>
            </div>
        </div>
        
        <!-- Danger Zone Tab -->
        <div id="danger" class="tab-content">
            <div class="settings-section">
                <h2>⚠️ المنطقة الخطرة</h2>
                <p style="color: #666; margin-bottom: 2rem;">العمليات التالية خطيرة ولا يمكن التراجع عنها. استخدمها بحذر شديد.</p>
                
                <div class="danger-zone">
                    <h3>🗑️ حذف جميع البيانات</h3>
                    <p class="warning-text">⚠️ تحذير: ستقوم هذه العملية بحذف جميع البيانات التالية بشكل نهائي:</p>
                    
                    <ul style="color: #c62828; margin: 1rem 0; line-height: 2;">
                        <li>جميع التلاميذ (<?php echo $stats['students']; ?>)</li>
                        <li>جميع الأساتذة (<?php echo $stats['teachers']; ?>)</li>
                        <li>جميع السنوات والتخصصات</li>
                        <li>جميع المواد الدراسية</li>
                        <li>جميع النقاط والدرجات (<?php echo $stats['grades']; ?>)</li>
                        <li>جميع سجلات الحضور (<?php echo $stats['attendance']; ?>)</li>
                        <li>جميع الإعلانات</li>
                        <li>جميع الإشعارات</li>
                    </ul>
                    
                    <div class="confirm-box">
                        <form method="POST" action="" onsubmit="return confirmDelete()">
                            <div class="form-group">
                                <label>للتأكيد، اكتب النص التالي بالضبط: <strong style="color: #d32f2f;">DELETE ALL DATA</strong></label>
                                <input type="text" name="confirm_delete" placeholder="اكتب: DELETE ALL DATA" 
                                       style="border: 2px solid #f44336;">
                            </div>
                            
                            <button type="submit" name="delete_all_data" class="btn btn-danger">
                                🗑️ حذف جميع البيانات نهائياً
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="info-box" style="background: #fff3cd; border-right-color: #ffc107; margin-top: 2rem;">
                    <h4 style="color: #856404;">💡 نصيحة</h4>
                    <p style="color: #856404;">قبل حذف البيانات، تأكد من أنك قمت بعمل نسخة احتياطية من قاعدة البيانات إذا كنت قد تحتاجها مستقبلاً.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function confirmDelete() {
            return confirm('⚠️ تحذير أخير!\n\nهل أنت متأكد تماماً من حذف جميع البيانات؟\nهذه العملية لا يمكن التراجع عنها!\n\nسيتم حذف:\n- جميع التلاميذ والأساتذة\n- جميع النقاط والحضور\n- جميع السنوات والمواد\n- جميع الإعلانات\n\nاضغط OK للمتابعة أو Cancel للإلغاء');
        }
    </script>
</body>
</html>