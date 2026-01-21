<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// إضافة إعلان جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_announcement'])) {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $type = sanitize($_POST['type']);
    $target_audience = sanitize($_POST['target_audience']);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO announcements (institution_id, title, content, type, target_audience) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$institution_id, $title, $content, $type, $target_audience]);
        $success = "تم نشر الإعلان بنجاح!";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء نشر الإعلان";
    }
}

// حذف إعلان
if (isset($_GET['delete'])) {
    $announcement_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND institution_id = ?");
        $stmt->execute([$announcement_id, $institution_id]);
        $success = "تم حذف الإعلان بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء حذف الإعلان";
    }
}

// جلب جميع الإعلانات
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE institution_id = ? ORDER BY created_at DESC");
$stmt->execute([$institution_id]);
$announcements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الإعلانات والأخبار</title>
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        input:focus, textarea:focus, select:focus {
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
        
        .announcements-list {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .announcement-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: border-color 0.3s;
        }
        
        .announcement-card:hover {
            border-color: #667eea;
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .announcement-header h3 {
            color: #333;
            flex: 1;
        }
        
        .announcement-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-type {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-audience {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .announcement-content {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .announcement-date {
            color: #999;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="institution_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
            <h1>📢 إدارة الإعلانات والأخبار</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="add-form">
            <h2 style="margin-bottom: 1.5rem; color: #333;">نشر إعلان جديد</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>عنوان الإعلان</label>
                    <input type="text" name="title" required placeholder="أدخل عنوان الإعلان">
                </div>
                
                <div class="form-group">
                    <label>المحتوى</label>
                    <textarea name="content" required placeholder="اكتب محتوى الإعلان هنا..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>نوع الإعلان</label>
                        <select name="type" required>
                            <option value="إعلان">إعلان</option>
                            <option value="خبر">خبر</option>
                            <option value="تنبيه">تنبيه</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>الجمهور المستهدف</label>
                        <select name="target_audience" required>
                            <option value="الجميع">الجميع</option>
                            <option value="أساتذة">الأساتذة فقط</option>
                            <option value="تلاميذ">التلاميذ فقط</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="add_announcement" class="btn-primary">
                    ➕ نشر الإعلان
                </button>
            </form>
        </div>
        
        <div class="announcements-list">
            <h2 style="margin-bottom: 1.5rem; color: #333;">الإعلانات المنشورة</h2>
            
            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card">
                        <div class="announcement-header">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <button class="btn-delete" onclick="if(confirm('هل تريد حذف هذا الإعلان؟')) location.href='?delete=<?php echo $announcement['id']; ?>'">
                                🗑️ حذف
                            </button>
                        </div>
                        
                        <div class="announcement-meta">
                            <span class="badge badge-type"><?php echo $announcement['type']; ?></span>
                            <span class="badge badge-audience">للجمهور: <?php echo $announcement['target_audience']; ?></span>
                        </div>
                        
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                        
                        <div class="announcement-date">
                            📅 تاريخ النشر: <?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 3rem;">
                    لا توجد إعلانات منشورة حالياً
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>