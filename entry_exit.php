<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// تسجيل دخول/خروج
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_entry_exit'])) {
    $student_phone = sanitize($_POST['student_phone']);
    $action = sanitize($_POST['action']);
    $current_date = date('Y-m-d');
    $current_time = date('Y-m-d H:i:s');
    
    // البحث عن التلميذ
    $stmt = $pdo->prepare("SELECT * FROM students WHERE phone_primary = ? AND institution_id = ?");
    $stmt->execute([$student_phone, $institution_id]);
    $student = $stmt->fetch();
    
    if ($student) {
        // التحقق من وجود سجل لهذا اليوم
        $stmt = $pdo->prepare("SELECT * FROM entry_exit WHERE student_id = ? AND date = ?");
        $stmt->execute([$student['id'], $current_date]);
        $existing_record = $stmt->fetch();
        
        if ($action == 'entry') {
            if ($existing_record && $existing_record['entry_time']) {
                $error = "تم تسجيل الدخول مسبقاً لهذا التلميذ اليوم";
            } else {
                try {
                    if ($existing_record) {
                        $stmt = $pdo->prepare("UPDATE entry_exit SET entry_time = ? WHERE id = ?");
                        $stmt->execute([$current_time, $existing_record['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO entry_exit (student_id, entry_time, date) VALUES (?, ?, ?)");
                        $stmt->execute([$student['id'], $current_time, $current_date]);
                    }
                    $success = "تم تسجيل دخول التلميذ: " . $student['first_name'] . " " . $student['last_name'];
                } catch (PDOException $e) {
                    $error = "حدث خطأ أثناء التسجيل";
                }
            }
        } else {
            if (!$existing_record || !$existing_record['entry_time']) {
                $error = "لم يتم تسجيل دخول التلميذ اليوم";
            } elseif ($existing_record['exit_time']) {
                $error = "تم تسجيل الخروج مسبقاً لهذا التلميذ اليوم";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE entry_exit SET exit_time = ? WHERE id = ?");
                    $stmt->execute([$current_time, $existing_record['id']]);
                    $success = "تم تسجيل خروج التلميذ: " . $student['first_name'] . " " . $student['last_name'];
                } catch (PDOException $e) {
                    $error = "حدث خطأ أثناء التسجيل";
                }
            }
        }
    } else {
        $error = "رقم الهاتف غير صحيح أو غير مسجل";
    }
}

// جلب سجلات اليوم
$current_date = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT e.*, s.first_name, s.last_name, s.phone_primary, y.year_name
    FROM entry_exit e
    JOIN students s ON e.student_id = s.id
    JOIN years y ON s.year_id = y.id
    WHERE e.date = ? AND s.institution_id = ?
    ORDER BY e.entry_time DESC
");
$stmt->execute([$current_date, $institution_id]);
$today_records = $stmt->fetchAll();

// إحصائيات اليوم
$total_entries = 0;
$total_exits = 0;
$still_inside = 0;

foreach ($today_records as $record) {
    if ($record['entry_time']) $total_entries++;
    if ($record['exit_time']) $total_exits++;
    if ($record['entry_time'] && !$record['exit_time']) $still_inside++;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام الدخول والخروج</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card.entry h3 {
            color: #4caf50;
        }
        
        .stat-card.exit h3 {
            color: #f44336;
        }
        
        .stat-card.inside h3 {
            color: #2196f3;
        }
        
        .register-form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1rem;
            align-items: end;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        input, select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1.1rem;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn-entry {
            background: #4caf50;
            color: white;
        }
        
        .btn-exit {
            background: #f44336;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
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
        
        .records-table {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
        
        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-inside {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-left {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .time-display {
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="institution_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
            <h1 style="color: #333;">🚪 نظام الدخول والخروج</h1>
            <p style="color: #666; margin-top: 0.5rem;">تسجيل دخول وخروج التلاميذ - <?php echo date('Y-m-d'); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card entry">
                <h3><?php echo $total_entries; ?></h3>
                <p>إجمالي الدخول</p>
            </div>
            <div class="stat-card exit">
                <h3><?php echo $total_exits; ?></h3>
                <p>إجمالي الخروج</p>
            </div>
            <div class="stat-card inside">
                <h3><?php echo $still_inside; ?></h3>
                <p>حالياً بالمؤسسة</p>
            </div>
        </div>
        
        <div class="register-form">
            <h2 style="margin-bottom: 1.5rem; color: #333;">📱 تسجيل سريع</h2>
            <form method="POST" action="" id="entryForm">
                <div class="form-row">
                    <div>
                        <label>رقم هاتف التلميذ</label>
                        <input type="tel" name="student_phone" id="phone_input" required 
                               placeholder="أدخل رقم الهاتف" autofocus>
                    </div>
                    <div>
                        <label>نوع العملية</label>
                        <select name="action" id="action_select">
                            <option value="entry">دخول 🟢</option>
                            <option value="exit">خروج 🔴</option>
                        </select>
                    </div>
                    <button type="submit" name="register_entry_exit" class="btn btn-entry" id="submit_btn">
                        ✅ تسجيل
                    </button>
                </div>
            </form>
        </div>
        
        <div class="records-table">
            <h2 style="margin-bottom: 1.5rem; color: #333;">📋 سجلات اليوم (<?php echo count($today_records); ?>)</h2>
            
            <?php if (count($today_records) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>التلميذ</th>
                            <th>السنة</th>
                            <th>رقم الهاتف</th>
                            <th>وقت الدخول</th>
                            <th>وقت الخروج</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_records as $record): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($record['year_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['phone_primary']); ?></td>
                                <td class="time-display">
                                    <?php echo $record['entry_time'] ? date('H:i', strtotime($record['entry_time'])) : '-'; ?>
                                </td>
                                <td class="time-display">
                                    <?php echo $record['exit_time'] ? date('H:i', strtotime($record['exit_time'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($record['exit_time']): ?>
                                        <span class="status-badge status-left">غادر المؤسسة</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inside">داخل المؤسسة</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 3rem; color: #999;">
                    لا توجد سجلات لليوم
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // تغيير لون الزر حسب نوع العملية
        const actionSelect = document.getElementById('action_select');
        const submitBtn = document.getElementById('submit_btn');
        
        actionSelect.addEventListener('change', function() {
            if (this.value === 'entry') {
                submitBtn.className = 'btn btn-entry';
                submitBtn.textContent = '✅ تسجيل دخول';
            } else {
                submitBtn.className = 'btn btn-exit';
                submitBtn.textContent = '❌ تسجيل خروج';
            }
        });
        
        // إعادة التركيز على حقل الإدخال بعد الإرسال
        document.getElementById('entryForm').addEventListener('submit', function() {
            setTimeout(function() {
                document.getElementById('phone_input').value = '';
                document.getElementById('phone_input').focus();
            }, 100);
        });
        
        // تحديث الصفحة كل 30 ثانية
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>