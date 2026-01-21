<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// إضافة أستاذ جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_teacher'])) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // التحقق من عدم تكرار رقم الهاتف
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE phone = ?");
    $stmt->execute([$phone]);
    
    if ($stmt->fetch()) {
        $error = "رقم الهاتف مسجل من قبل";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO teachers (institution_id, first_name, last_name, phone, email, password) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$institution_id, $first_name, $last_name, $phone, $email ?: null, $password]);
            $success = "تم إضافة الأستاذ بنجاح! يمكنه تسجيل الدخول برقم الهاتف: " . $phone;
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء إضافة الأستاذ";
        }
    }
}

// حذف أستاذ
if (isset($_GET['delete'])) {
    $teacher_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ? AND institution_id = ?");
        $stmt->execute([$teacher_id, $institution_id]);
        $success = "تم حذف الأستاذ بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء حذف الأستاذ";
    }
}

// جلب جميع الأساتذة
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE institution_id = ? ORDER BY first_name");
$stmt->execute([$institution_id]);
$teachers = $stmt->fetchAll();

// جلب السنوات والمواد للتعيينات
$stmt = $pdo->prepare("SELECT * FROM years WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$years = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM subjects WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$subjects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأساتذة</title>
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
        
        input {
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        
        input:focus {
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
            transition: transform 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
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
        
        .teachers-list {
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
            color: #333;
            font-weight: 600;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }
        
        tr:hover {
            background: #f9f9f9;
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
        
        .btn-delete:hover {
            background: #d32f2f;
        }
        
        .btn-assign {
            padding: 0.5rem 1rem;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        
        .btn-assign:hover {
            background: #388e3c;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="institution_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
            <h1>👨‍🏫 إدارة الأساتذة</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="add-form">
            <h2 style="margin-bottom: 1.5rem; color: #333;">➕ إضافة أستاذ جديد</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>الاسم <span style="color: red;">*</span></label>
                        <input type="text" name="first_name" required placeholder="أدخل الاسم">
                    </div>
                    
                    <div class="form-group">
                        <label>اللقب <span style="color: red;">*</span></label>
                        <input type="text" name="last_name" required placeholder="أدخل اللقب">
                    </div>
                    
                    <div class="form-group">
                        <label>رقم الهاتف (للدخول) <span style="color: red;">*</span></label>
                        <input type="tel" name="phone" required placeholder="0550123456">
                    </div>
                    
                    <div class="form-group">
                        <label>البريد الإلكتروني (اختياري)</label>
                        <input type="email" name="email" placeholder="teacher@example.com">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>كلمة المرور <span style="color: red;">*</span></label>
                        <input type="password" name="password" required minlength="6" placeholder="6 أحرف على الأقل">
                    </div>
                </div>
                
                <button type="submit" name="add_teacher" class="btn-primary">
                    ➕ إضافة الأستاذ
                </button>
            </form>
        </div>
        
        <div class="teachers-list">
            <h2 style="margin-bottom: 1.5rem; color: #333;">قائمة الأساتذة (<?php echo count($teachers); ?>)</h2>
            
            <?php if (count($teachers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم الكامل</th>
                            <th>رقم الهاتف</th>
                            <th>البريد الإلكتروني</th>
                            <th>تاريخ التسجيل</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $index => $teacher): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email'] ?: '-'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($teacher['created_at'])); ?></td>
                                <td>
                                    <button class="btn-assign" onclick="location.href='assign_teacher.php?id=<?php echo $teacher['id']; ?>'">
                                        📚 تعيين مواد
                                    </button>
                                    <button class="btn-delete" onclick="if(confirm('هل تريد حذف هذا الأستاذ؟')) location.href='?delete=<?php echo $teacher['id']; ?>'">
                                        🗑️ حذف
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <h3>لا يوجد أساتذة مسجلين</h3>
                    <p>ابدأ بإضافة أول أستاذ من النموذج أعلاه</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>