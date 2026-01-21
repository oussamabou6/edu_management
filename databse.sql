-- قاعدة بيانات نظام إدارة المؤسسات التربوية
CREATE DATABASE IF NOT EXISTS edu_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edu_management;

-- جدول المؤسسات التربوية
CREATE TABLE institutions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    type ENUM('ابتدائي', 'متوسط', 'ثانوي', 'مدرسة خاصة') NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_locked BOOLEAN DEFAULT FALSE COMMENT 'قفل التعديلات بعد الإعداد الأولي'
);

-- جدول السنوات الدراسية
CREATE TABLE years (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    year_name VARCHAR(100) NOT NULL COMMENT 'مثل: السنة الأولى ثانوي',
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
);

-- جدول التخصصات
CREATE TABLE specializations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    year_id INT NOT NULL,
    specialization_name VARCHAR(100) NOT NULL COMMENT 'مثل: علوم تجريبية، آداب وفلسفة',
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    FOREIGN KEY (year_id) REFERENCES years(id) ON DELETE CASCADE
);

-- جدول المواد الدراسية
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    specialization_id INT NOT NULL,
    subject_name VARCHAR(100) NOT NULL COMMENT 'مثل: رياضيات، فيزياء',
    coefficient INT DEFAULT 1 COMMENT 'المعامل',
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE
);

-- جدول الأساتذة
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
);

-- جدول ربط الأساتذة بالمواد والسنوات
CREATE TABLE teacher_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    year_id INT NOT NULL,
    specialization_id INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (year_id) REFERENCES years(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (teacher_id, subject_id, year_id, specialization_id)
);

-- جدول التلاميذ
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    year_id INT NOT NULL,
    specialization_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    father_name VARCHAR(100) NOT NULL,
    mother_first_name VARCHAR(100) NOT NULL,
    mother_last_name VARCHAR(100) NOT NULL,
    siblings_count INT DEFAULT 0,
    phone_primary VARCHAR(20) UNIQUE NOT NULL COMMENT 'رقم هاتف الأب - للدخول',
    phone_secondary VARCHAR(20) COMMENT 'رقم هاتف ثاني - اختياري',
    email VARCHAR(100) COMMENT 'بريد إلكتروني - اختياري',
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    FOREIGN KEY (year_id) REFERENCES years(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE
);

-- جدول الحضور والغياب
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('حاضر', 'غائب', 'غياب بعذر') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- جدول دخول وخروج التلاميذ
CREATE TABLE entry_exit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    entry_time DATETIME,
    exit_time DATETIME,
    date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- جدول النقاط والدرجات
CREATE TABLE grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    grade_type ENUM('فرض', 'اختبار', 'امتحان', 'مشاركة') NOT NULL,
    grade DECIMAL(5,2) NOT NULL,
    max_grade DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    exam_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- جدول الإشعارات والأخبار
CREATE TABLE announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institution_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('إعلان', 'خبر', 'تنبيه') NOT NULL,
    target_audience ENUM('الجميع', 'أساتذة', 'تلاميذ') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
);

-- جدول الإشعارات الخاصة بالأولياء
CREATE TABLE parent_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    notification_type ENUM('غياب', 'نقطة ضعيفة', 'تأخر', 'إنجاز', 'عام') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- إدراج بيانات تجريبية
-- مؤسسة تجريبية
INSERT INTO institutions (name, type, address, phone, email, password) 
VALUES ('ثانوية الأمير عبد القادر', 'ثانوي', 'وهران، الجزائر', '0550123456', 'admin@school.dz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- كلمة السر: password123