    </main>

    <!-- Footer -->
    <footer class="daleel-footer" style="background-color: #F5ECE0; border-top: 1px solid #E6DCCD; padding: 60px 0 30px; margin-top: 70px; color: #4B5563;">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="mb-3 text-center">
                        <a href="/" class="d-inline-block text-decoration-none">
                            <img src="/assets/images/logo.png" alt="دليل المدارس السودانية" style="height: 52px; width: auto; object-fit: contain;">
                        </a>
                    </div>
                    <p class="text-muted leading-relaxed text-center mb-0" style="font-size: 0.73rem; line-height: 1.75;">
                        خدمة رقمية مقدمة من منظومة <strong>ايسكو بلص (SchoPlus)</strong>، تهدف إلى مساعدة أولياء الأمور والطلاب في البحث والتعرف على المدارس في مختلف ولايات السودان، وإتاحة مساحة مفتوحة لأصحاب المدارس للتعريف بمدارسهم وخدماتهم التعليمية.
                    </p>
                </div>

                <div class="col-lg-2 col-md-6 col-6">
                    <h5 class="fw-bold text-dark mb-3" style="font-size: 0.88rem;">تصفح الدليل</h5>
                    <a href="/" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">كافة المدارس</a>
                    <a href="/#schoplus-schools" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">مدارس SchoPlus الذكية</a>
                    <a href="/#top-rated" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">المدارس الأعلى تقييماً</a>
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">إضافة مدرسة جديدة</a>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <h5 class="fw-bold text-dark mb-3" style="font-size: 0.88rem;">لأصحاب المدارس</h5>
                    <a href="/login.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">بوابة مدراء المدارس</a>
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">تسجيل مدرسة جديدة</a>
                    <a href="https://schoplus.sd/pricing.html" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">باقات نظام SchoPlus</a>
                    <a href="https://schoplus.sd/demo.html" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">طلب عرض تجريبي للنظام</a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold text-dark mb-3" style="font-size: 0.88rem;">منظومة ايسكو بلص</h5>
                    <a href="https://schoplus.sd" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-globe text-primary me-1"></i> الموقع الإلكتروني لـ ايسكو بلص
                    </a>
                    <a href="/login.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-sign-in-alt text-secondary me-1"></i> تسجيل دخول المدرسة
                    </a>
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-3" style="font-size: 0.74rem;">
                        <i class="fas fa-plus-circle text-warning me-1"></i> تسجيل مدرسة جديدة
                    </a>
                    <a href="/register.php" class="btn-luxury-gold btn-sm w-100 justify-content-center" style="font-size: 0.78rem;">
                        <i class="fas fa-school"></i> سجّل مدرستك الآن
                    </a>
                </div>
            </div>

            <!-- سياسة الاستخدام وإخلاء المسؤولية -->
            <div class="mt-4 p-3 rounded-4" style="background: rgba(255, 255, 255, 0.65); border: 1px solid #E2D7C8;">
                <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-info-circle text-warning mt-1" style="font-size: 0.85rem; flex-shrink: 0;"></i>
                    <div style="font-size: 0.71rem; color: #4B5563; line-height: 1.7;">
                        <strong class="text-dark">سياسة الاستخدام وإخلاء المسؤولية:</strong>
                        هذا الدليل هو خدمة رقمية مقدمة من منظومة <strong>ايسكو بلص (SchoPlus)</strong> ولا يُعد دليلاً رسمياً معتمداً من وزارة التربية والتعليم أو أي جهة حكومية. كافة البيانات والمعلومات والرسوم المنشورة تتم إضافتها وإدارتها مباشرة من قِبل أصحاب وإدارات المدارس على مسؤوليتهم.
                    </div>
                </div>
            </div>

            <hr class="my-4" style="border-color: #E2D7C8;">

            <div class="text-center text-muted" style="font-size: 0.72rem;">
                جميع الحقوق محفوظة © <?= date('Y') ?> — دليل المدارس السودانية | إحدى مبادرات <strong>SchoPlus</strong> .
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($extraScripts)) echo $extraScripts; ?>

    <!-- Scroll Reveal + CountUp Animations -->
    <script>
    (function(){
        // Fade-up scroll reveal
        var els = document.querySelectorAll('.fade-up');
        if (els.length && 'IntersectionObserver' in window) {
            var obs = new IntersectionObserver(function(entries) {
                entries.forEach(function(e) {
                    if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
                });
            }, { threshold: 0.1 });
            els.forEach(function(el) { obs.observe(el); });
        } else {
            els.forEach(function(el) { el.classList.add('visible'); });
        }

        // CountUp animation for hero stats
        function countUp(el, target, duration) {
            var start = 0;
            var step = target / (duration / 16);
            var suffix = el.dataset.suffix || '';
            var timer = setInterval(function() {
                start += step;
                if (start >= target) {
                    el.textContent = target.toLocaleString() + suffix;
                    clearInterval(timer);
                    return;
                }
                el.textContent = Math.floor(start).toLocaleString() + suffix;
            }, 16);
        }

        var stats = document.querySelectorAll('[data-countup]');
        if (stats.length && 'IntersectionObserver' in window) {
            var obs2 = new IntersectionObserver(function(entries) {
                entries.forEach(function(e) {
                    if (e.isIntersecting) {
                        var target = parseInt(e.target.dataset.countup);
                        var suffix = e.target.dataset.suffix || '';
                        countUp(e.target, target, 1200);
                        obs2.unobserve(e.target);
                    }
                });
            }, { threshold: 0.3 });
            stats.forEach(function(el) { obs2.observe(el); });
        }

        // Sticky Navbar scroll state
        window.addEventListener('scroll', function() {
            var nav = document.querySelector('.daleel-navbar');
            if (nav) {
                if (window.scrollY > 20) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }
            }
        }, { passive: true });
    })();
    </script>
</body>
</html>
