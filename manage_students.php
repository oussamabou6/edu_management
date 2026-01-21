<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() != 'institution') {
    header("Location: login.php");
    exit();
}

$institution_id = $_SESSION['user_id'];
$error = '';
$success = '';

// حذف تلميذ
if (isset($_GET['delete'])) {
    $student_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND institution_id = ?");
        $stmt->execute([$student_id, $institution_id]);
        $success = "تم حذف التلميذ بنجاح";
    } catch (PDOException $e) {
        $error = "حدث خطأ أثناء حذف التلميذ";
    }
}

// البحث والفلترة
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : 0;
$spec_filter = isset($_GET['spec']) ? intval($_GET['spec']) : 0;

// بناء الاستعلام
$sql = "
    SELECT s.*, y.year_name, sp.specialization_name 
    FROM students s
    JOIN years y ON s.year_id = y.id
    JOIN specializations sp ON s.specialization_id = sp.id
    WHERE s.institution_id = ?
";
$params = [$institution_id];

if ($search) {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.phone_primary LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($year_filter) {
    $sql .= " AND s.year_id = ?";
    $params[] = $year_filter;
}

if ($spec_filter) {
    $sql .= " AND s.specialization_id = ?";
    $params[] = $spec_filter;
}

$sql .= " ORDER BY s.last_name, s.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// جلب السنوات للفلتر
$stmt = $pdo->prepare("SELECT * FROM years WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$years = $stmt->fetchAll();

// جلب التخصصات للفلتر
$stmt = $pdo->prepare("SELECT * FROM specializations WHERE institution_id = ?");
$stmt->execute([$institution_id]);
$specializations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التلاميذ</title>
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
            max-width: 1400px;
            margin: 0 auto;
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
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .btn-add {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 0.5fr;
            gap: 1rem;
            align-items: end;
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
        
        .btn-filter {
            padding: 0.9rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
        }
        
        .students-list {
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
            font-weight: 600;
            color: #333;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        
        .btn-view {
            background: #2196f3;
            color: white;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-year {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-spec {
            background: #f3e5f5;
            color: #7b1fa2;
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
            <div>
                <a href="institution_dashboard.php" class="back-link">← العودة للوحة التحكم</a>
                <h1 style="color: #333; margin-top: 0.5rem;">👨‍🎓 إدارة التلاميذ</h1>
            </div>
            <a href="add_student.php" class="btn-add">➕ إضافة تلميذ جديد</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="filters">
            <h3 style="margin-bottom: 1rem; color: #333;">🔍 البحث والفلترة</h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <input type="text" name="search" placeholder="ابحث بالاسم أو رقم الهاتف..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="year">
                        <option value="">جميع السنوات</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year['id']; ?>" <?php echo $year_filter == $year['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="spec">
                        <option value="">جميع التخصصات</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo $spec['id']; ?>" <?php echo $spec_filter == $spec['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialization_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn-filter">بحث</button>
                </div>
            </form>
        </div>
        
        <div class="students-list">
            <h3 style="margin-bottom: 1.5rem; color: #333;">
                قائمة التلاميذ (<?php echo count($students); ?>)
            </h3>
            
            <?php if (count($students) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم الكامل</th>
                            <th>السنة والتخصص</th>
                            <th>رقم الهاتف</th>
                            <th>اسم الأب</th>
                            <th>تاريخ التسجيل</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                                <td>
                                    <span class="badge badge-year"><?php echo htmlspecialchars($student['year_name']); ?></span>
                                    <span class="badge badge-spec"><?php echo htmlspecialchars($student['specialization_name']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($student['phone_primary']); ?></td>
                                <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <button class="btn-action btn-view" onclick="location.href='student_profile.php?id=<?php echo $student['id']; ?>'">
                                        👁️ عرض
                                    </button>
                                    <button class="btn-action btn-delete" onclick="if(confirm('حذف التلميذ؟')) location.href='?delete=<?php echo $student['id']; ?>'">
                                        🗑️ حذف
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <h3>لا يوجد تلاميذ</h3>
                    <p>جرب تعديل معايير البحث أو ابدأ بإضافة تلاميذ جدد</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>