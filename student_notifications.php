<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// تعليم الإشعار كمقروء
if (isset($_GET['read'])) {
    $notif_id = intval($_GET['read']);
    $stmt = $pdo->prepare("UPDATE parent_notifications SET is_read = TRUE WHERE id = ? AND student_id = ?");
    $stmt->execute([$notif_id, $student_id]);
    header("Location: student_notifications.php");
    exit();
}

// تعليم الكل كمقروء
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE parent_notifications SET is_read = TRUE WHERE student_id = ?");
    $stmt->execute([$student_id]);
    header("Location: student_notifications.php");
    exit();
}

// جلب الإشعارات
$stmt = $pdo->prepare("
    SELECT * FROM parent_notifications 
    WHERE student_id = ? 
    ORDER BY is_read ASC, created_at DESC
");
$stmt->execute([$student_id]);
$notifications = $stmt->fetchAll();

// عدد الإشعارات غير المقروءة
$unread_count = 0;
foreach ($notifications as $notif) {
    if (!$notif['is_read']) $unread_count++;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 2rem 1rem;
            overflow-y: auto;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .menu-item {
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .menu-item.active {
            background: rgba(255,255,255,0.2);
        }
        
        .logout-btn {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
        }
        
        .main-content {
            margin-right: 260px;
            padding: 2rem;
        }
        
        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-mark-all {
            padding: 0.8rem 1.5rem;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .notifications-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.3s;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: #f9f9f9;
        }
        
        .notification-item.unread {
            background: #e3f2fd;
            border-right: 4px solid #2196f3;
        }
        
        .notif-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .notif-type {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .type-غياب {
            background: #ffebee;
            color: #c62828;
        }
        
        .type-نقطة {
            background: #fff3e0;
            color: #e65100;
        }
        
        .type-إنجاز {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .type-عام {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .type-تأخر {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .notif-message {
            color: #333;
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .notif-time {
            color: #999;
            font-size: 0.9rem;
        }
        
        .no-notifications {
            text-align: center;
            padding: 5rem 2rem;
            color: #999;
        }
        
        .unread-badge {
            background: #f44336;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>👨‍🎓 مرحباً</h2>
            <p><?php echo htmlspecialchars($_SESSION['student_name']); ?></p>
        </div>
        
        <div class="menu-item" onclick="location.href='student_dashboard.php'">
            📊 لوحة التحكم
        </div>
        <div class="menu-item" onclick="location.href='student_grades.php'">
            📝 النقاط والدرجات
        </div>
        <div class="menu-item" onclick="location.href='student_attendance.php'">
            ✅ سجل الحضور
        </div>
        <div class="menu-item active">
            🔔 الإشعارات
            <?php if ($unread_count > 0): ?>
                <span class="unread-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </div>
        
        <button class="logout-btn" onclick="if(confirm('هل تريد تسجيل الخروج؟')) location.href='logout.php'">
            تسجيل الخروج
        </button>
    </div>
    
    <div class="main-content">
        <div class="header">
            <div>
                <h1 style="color: #333;">🔔 الإشعارات</h1>
                <p style="color: #666; margin-top: 0.5rem;">
                    <?php echo $unread_count; ?> إشعار غير مقروء من أصل <?php echo count($notifications); ?>
                </p>
            </div>
            <?php if ($unread_count > 0): ?>
                <form method="POST" action="">
                    <button type="submit" name="mark_all_read" class="btn-mark-all">
                        ✅ تعليم الكل كمقروء
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="notifications-container">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" 
                         onclick="location.href='?read=<?php echo $notif['id']; ?>'">
                        <div class="notif-header">
                            <span class="notif-type type-<?php echo str_replace(' ', '', $notif['notification_type']); ?>">
                                <?php 
                                    $icons = [
                                        'غياب' => '❌',
                                        'نقطة ضعيفة' => '📉',
                                        'إنجاز' => '🎉',
                                        'تأخر' => '⏰',
                                        'عام' => '📢'
                                    ];
                                    echo ($icons[$notif['notification_type']] ?? '📢') . ' ' . $notif['notification_type'];
                                ?>
                            </span>
                            <?php if (!$notif['is_read']): ?>
                                <span style="color: #2196f3; font-weight: bold; font-size: 0.9rem;">جديد</span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div class="notif-time">
                            📅 <?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <h2>🔕 لا توجد إشعارات</h2>
                    <p style="margin-top: 1rem;">لم يتم إرسال أي إشعارات لك حتى الآن</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>