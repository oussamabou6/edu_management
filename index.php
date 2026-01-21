<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة المؤسسات التربوية - الأقوى والأذكى</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            overflow-x: hidden;
        }

        /* Header */
        header {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.2rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #667eea;
        }

        .login-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Hero Section */
        .hero {
            max-width: 1200px;
            margin: 0 auto;
            padding: 5rem 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3rem;
            color: white;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-content .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            margin-bottom: 1rem;
            font-weight: bold;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn-primary, .btn-secondary {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: white;
            color: #667eea;
        }

        .hero-image {
            position: relative;
        }

        .hero-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .floating-card {
            position: absolute;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: float 3s ease-in-out infinite;
        }

        .card-1 {
            top: 10%;
            right: -10%;
        }

        .card-2 {
            bottom: 20%;
            left: -10%;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        /* Features Section */
        .features {
            background: white;
            padding: 5rem 2rem;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 3rem;
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Stats Section */
        .stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 4rem 2rem;
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }

        .stat-item h2 {
            font-size: 3rem;
            color: white;
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
        }

        /* Footer */
        footer {
            background: #1a1a2e;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero {
                grid-template-columns: 1fr;
                padding: 3rem 1rem;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .nav-links {
                display: none;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .floating-card {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <nav>
            <div class="logo">
                <div class="logo-icon">🎓</div>
                <span>EduManage Pro</span>
            </div>
            <ul class="nav-links">
                <li><a href="#features">المميزات</a></li>
                <li><a href="#about">عن النظام</a></li>
                <li><a href="#pricing">الأسعار</a></li>
                <li><a href="#contact">اتصل بنا</a></li>
            </ul>
            <button class="login-btn" onclick="location.href='login.php'">تسجيل الدخول</button>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <span class="badge">🥇 الأقوى والأذكى</span>
            <h1>نظام إدارة المؤسسات التربوية المتكامل</h1>
            <p>حل شامل لإدارة المدارس والمؤسسات التعليمية بكفاءة عالية وسهولة استخدام</p>
          
        </div>
        <div class="hero-image">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 400px; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: white; font-size: 4rem;">
                📚
            </div>
            <div class="floating-card card-1">
                <strong style="color: #667eea;">✅ 500+</strong>
                <p style="margin: 0; color: #666;">مؤسسة تستخدم النظام</p>
            </div>
            <div class="floating-card card-2">
                <strong style="color: #667eea;">⭐ 4.9/5</strong>
                <p style="margin: 0; color: #666;">تقييم المستخدمين</p>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <h2 class="section-title">مميزات النظام الشاملة</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🚪</div>
                <h3>دخول وخروج التلاميذ</h3>
                <p>تتبع دقيق لحضور ومغادرة الطلاب مع سجلات زمنية شاملة وتقارير يومية</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>إدارة النقاط</h3>
                <p>نظام متكامل لتسجيل وتتبع درجات الطلاب والتقييمات بجميع المواد الدراسية</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h3>متابعة الغياب</h3>
                <p>مراقبة غياب الطلاب وإنشاء تقارير تفصيلية ترسل تلقائياً للأولياء</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔔</div>
                <h3>إشعارات للأولياء</h3>
                <p>إرسال تنبيهات فورية عبر SMS والبريد الإلكتروني حول أداء وحضور الطلاب</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>حساب لكل مستخدم</h3>
                <p>نظام صلاحيات متعدد المستويات للإدارة والمعلمين والأولياء والطلاب</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📈</div>
                <h3>تقارير ذكية</h3>
                <p>تقارير تحليلية مفصلة وإحصائيات شاملة لمتابعة الأداء الأكاديمي</p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <h2>500+</h2>
                <p>مؤسسة تربوية</p>
            </div>
            <div class="stat-item">
                <h2>50,000+</h2>
                <p>طالب وطالبة</p>
            </div>
            <div class="stat-item">
                <h2>99.9%</h2>
                <p>وقت التشغيل</p>
            </div>
            <div class="stat-item">
                <h2>24/7</h2>
                <p>دعم فني</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; 2025 EduManage Pro - جميع الحقوق محفوظة</p>
        <p>نظام إدارة المؤسسات التربوية الأقوى والأذكى 🎯</p>
    </footer>

    <script>
        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease-out';
            observer.observe(card);
        });
    </script>
</body>
</html>