<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// إضافة سنة دراسية جديدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_year'])) {
    $year_name = sanitize($_POST['year_name']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO years (institution_id, year_name) VALUES (?, ?)");
        $stmt->execute([$institution_id, $year_name]);
        $success = "تم إضافة السنة الدراسية بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء إضافة السنة الدراسية";
    }
}

// إضافة تخصص جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_specialization'])) {
    $year_id = intval($_POST['year_id']);
    $specialization_name = sanitize($_POST['specialization_name']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO specializations (institution_id, year_id, specialization_name) VALUES (?, ?, ?)");
        $stmt->execute([$institution_id, $year_id, $specialization_name]);
        $success = "تم إضافة التخصص بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء إضافة التخصص";
    }
}

// حذف سنة
if (isset($_GET['delete_year'])) {
    $year_id = intval($_GET['delete_year']);
    try {
        $stmt = $pdo->prepare("DELETE FROM years WHERE id = ? AND institution_id = ?");
        $stmt->execute([$year_id, $institution_id]);
        $success = "تم حذف السنة الدراسية وجميع التخصصات المرتبطة بها";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء حذف السنة";
    }
}

// حذف تخصص
if (isset($_GET['delete_spec'])) {
    $spec_id = intval($_GET['delete_spec']);
    try {
        $stmt = $pdo->prepare("DELETE FROM specializations WHERE id = ? AND institution_id = ?");
        $stmt->execute([$spec_id, $institution_id]);
        $success = "تم حذف التخصص بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء حذف التخصص";
    }
}

// جلب السنوات والتخصصات
$stmt = $pdo->prepare("SELECT * FROM years WHERE institution_id = ? ORDER BY year_name");
$stmt->execute([$institution_id]);
$years = $stmt->fetchAll();

// جلب التخصصات لكل سنة
$specializations = [];
foreach ($years as $year) {
    $stmt = $pdo->prepare("SELECT * FROM specializations WHERE year_id = ? AND institution_id = ?");
    $stmt->execute([$year['id'], $institution_id]);
    $specializations[$year['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة السنوات والتخصصات</title>
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
        
        .add-forms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
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
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
        }
        
        .btn:hover {
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
        
        .years-list {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .year-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: border-color 0.3s;
        }
        
        .year-card:hover {
            border-color: #667eea;
        }
        
        .year-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .year-header h3 {
            color: #333;
            font-size: 1.3rem;
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
        
        .specializations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .spec-item {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .spec-item span {
            font-weight: 600;
        }
        
        .spec-delete {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .spec-delete:hover {
            background: rgba(255,255,255,0.3);
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
            <h1>📅 إدارة السنوات والتخصصات</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="add-forms">
            <div class="form-card">
                <h2>➕ إضافة سنة دراسية</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>اسم السنة الدراسية</label>
                        <input type="text" name="year_name" required placeholder="مثال: السنة الأولى ثانوي">
                    </div>
                    <button type="submit" name="add_year" class="btn btn-primary">
                        ➕ إضافة السنة
                    </button>
                </form>
            </div>
            
            <div class="form-card">
                <h2>➕ إضافة تخصص</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>اختر السنة الدراسية</label>
                        <select name="year_id" required>
                            <option value="">اختر السنة</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year['id']; ?>">
                                    <?php echo htmlspecialchars($year['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>اسم التخصص</label>
                        <input type="text" name="specialization_name" required placeholder="مثال: علوم تجريبية">
                    </div>
                    <button type="submit" name="add_specialization" class="btn btn-success">
                        ➕ إضافة التخصص
                    </button>
                </form>
            </div>
        </div>
        
        <div class="years-list">
            <h2 style="margin-bottom: 1.5rem; color: #333;">السنوات الدراسية والتخصصات</h2>
            
            <?php if (count($years) > 0): ?>
                <?php foreach ($years as $year): ?>
                    <div class="year-card">
                        <div class="year-header">
                            <h3>📚 <?php echo htmlspecialchars($year['year_name']); ?></h3>
                            <button class="btn-delete" onclick="if(confirm('سيتم حذف السنة وجميع التخصصات المرتبطة بها. هل أنت متأكد؟')) location.href='?delete_year=<?php echo $year['id']; ?>'">
                                🗑️ حذف السنة
                            </button>
                        </div>
                        
                        <p style="color: #666; margin-bottom: 1rem;">
                            <strong>عدد التخصصات:</strong> <?php echo count($specializations[$year['id']]); ?>
                        </p>
                        
                        <?php if (count($specializations[$year['id']]) > 0): ?>
                            <div class="specializations-grid">
                                <?php foreach ($specializations[$year['id']] as $spec): ?>
                                    <div class="spec-item">
                                        <span><?php echo htmlspecialchars($spec['specialization_name']); ?></span>
                                        <button class="spec-delete" onclick="if(confirm('حذف هذا التخصص؟')) location.href='?delete_spec=<?php echo $spec['id']; ?>'">
                                            ×
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 1rem; background: #f9f9f9; border-radius: 8px;">
                                لا توجد تخصصات لهذه السنة
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <h3>لا توجد سنوات دراسية مسجلة</h3>
                    <p>ابدأ بإضافة أول سنة دراسية من النموذج أعلاه</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>