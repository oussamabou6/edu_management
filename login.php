<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_type = sanitize($_POST['user_type']);
    $identifier = sanitize($_POST['identifier']); // رقم الهاتف أو البريد
    $password = $_POST['password'];
    
    if ($user_type == 'institution') {
        // تسجيل دخول المؤسسة
        $stmt = $pdo->prepare("SELECT * FROM institutions WHERE email = ?");
        $stmt->execute([$identifier]);
        $institution = $stmt->fetch();
        
        if ($institution && password_verify($password, $institution['password'])) {
            $_SESSION['user_id'] = $institution['id'];
            $_SESSION['user_type'] = 'institution';
            $_SESSION['institution_name'] = $institution['name'];
            header("Location: institution_dashboard.php");
            exit();
        } else {
            $error = "البريد الإلكتروني أو كلمة المرور غير صحيحة";
        }
        
    } elseif ($user_type == 'teacher') {
        // تسجيل دخول الأستاذ
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE phone = ?");
        $stmt->execute([$identifier]);
        $teacher = $stmt->fetch();
        
        if ($teacher && password_verify($password, $teacher['password'])) {
            $_SESSION['user_id'] = $teacher['id'];
            $_SESSION['user_type'] = 'teacher';
            $_SESSION['institution_id'] = $teacher['institution_id'];
            $_SESSION['teacher_name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
            header("Location: teacher_dashboard.php");
            exit();
        } else {
            $error = "رقم الهاتف أو كلمة المرور غير صحيحة";
        }
        
    } elseif ($user_type == 'student') {
        // تسجيل دخول التلميذ
        $stmt = $pdo->prepare("SELECT * FROM students WHERE phone_primary = ?");
        $stmt->execute([$identifier]);
        $student = $stmt->fetch();
        
        if ($student && password_verify($password, $student['password'])) {
            $_SESSION['user_id'] = $student['id'];
            $_SESSION['user_type'] = 'student';
            $_SESSION['institution_id'] = $student['institution_id'];
            $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
            header("Location: student_dashboard.php");
            exit();
        } else {
            $error = "رقم الهاتف أو كلمة المرور غير صحيحة";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة المؤسسات التربوية</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        input, select {
            width: 100%;
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
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
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
        
        .user-type-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .user-type-tab {
            flex: 1;
            padding: 0.8rem;
            background: #f5f5f5;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .user-type-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🎓 تسجيل الدخول</h1>
            <p>نظام إدارة المؤسسات التربوية</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="user-type-tabs">
                    <div class="user-type-tab active" onclick="selectUserType('institution', this)">
                        🏫 المؤسسة
                    </div>
                    <div class="user-type-tab" onclick="selectUserType('teacher', this)">
                        👨‍🏫 أستاذ
                    </div>
                    <div class="user-type-tab" onclick="selectUserType('student', this)">
                        👨‍🎓 تلميذ
                    </div>
                </div>
                
                <input type="hidden" name="user_type" id="user_type" value="institution">
                
                <div class="form-group">
                    <label id="identifier_label">البريد الإلكتروني</label>
                    <input type="text" name="identifier" id="identifier" required placeholder="أدخل البريد الإلكتروني">
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <input type="password" name="password" required placeholder="أدخل كلمة المرور">
                </div>
                
                <button type="submit" class="btn-login">تسجيل الدخول</button>
            </form>
            
            <div class="back-link">
                <a href="index.php">← العودة للصفحة الرئيسية</a>
            </div>
        </div>
    </div>
    
    <script>
        function selectUserType(type, element) {
            // إزالة active من جميع التبويبات
            document.querySelectorAll('.user-type-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إضافة active للتبويب المحدد
            element.classList.add('active');
            
            // تحديث نوع المستخدم
            document.getElementById('user_type').value = type;
            
            // تحديث التسمية حسب نوع المستخدم
            const label = document.getElementById('identifier_label');
            const input = document.getElementById('identifier');
            
            if (type === 'institution') {
                label.textContent = 'البريد الإلكتروني';
                input.placeholder = 'أدخل البريد الإلكتروني';
                input.type = 'email';
            } else {
                label.textContent = 'رقم الهاتف';
                input.placeholder = 'أدخل رقم الهاتف';
                input.type = 'tel';
            }
        }
    </script>
</body>
</html>