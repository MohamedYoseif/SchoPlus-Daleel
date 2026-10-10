    </main>

    <!-- Footer -->
    <footer class="daleel-footer" style="background-color: #F5ECE0; border-top: 1px solid #E6DCCD; padding: 60px 0 30px; margin-top: 70px; color: #4B5563;">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="mb-3">
                        <a href="/" class="d-inline-block text-decoration-none">
                            <img src="/assets/images/logo.png" alt="دليل المدارس السودانية" style="height: 48px; width: auto; object-fit: contain;">
                        </a>
                    </div>
                    <p class="text-muted leading-relaxed" style="font-size: 0.72rem; line-height: 1.6;">
                        المنصة الوطنية المفتوحة الأولى لربط أولياء الأمور والطلاب بأفضل المدارس في السودان، مع إبراز المؤسسات التعليمية الرائدة التي تعتمد حلول التحول الرقمي وإدارة المدارس السحابية عبر SchoPlus.
                    </p>
                    <div class="d-flex gap-2 mt-3">
                        <span class="badge-schoplus-smart"><i class="fas fa-shield-alt"></i> معتمد وموثق</span>
                        <span class="badge-verified"><i class="fas fa-leaf"></i> بيئة كريمية مريحة</span>
                    </div>
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
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">تسجيل المدرسة مجاناً</a>
                    <a href="https://schoplus.sd/pricing.html" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">باقات نظام SchoPlus</a>
                    <a href="https://schoplus.sd/demo.html" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">طلب عرض تجريبي مجاني</a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold text-dark mb-3" style="font-size: 0.88rem;">تواصل ومساعدة</h5>
                    <p class="text-muted mb-2" style="font-size: 0.72rem;"><i class="fas fa-map-marker-alt text-warning me-2"></i> الخرطوم / بورتسودان، جمهورية السودان</p>
                    <p class="text-muted mb-2" style="font-size: 0.72rem;"><i class="fab fa-whatsapp text-success me-2"></i> واتساب الدليل المباشر: +249 91 234 5678</p>
                    <p class="text-muted mb-3" style="font-size: 0.72rem;"><i class="fas fa-envelope text-primary me-2"></i> support@schoplus.sd</p>
                    <a href="/register.php" class="btn-luxury-gold btn-sm w-100 justify-content-center" style="font-size: 0.78rem;">
                        <i class="fas fa-plus-circle"></i> سجّل مدرستك اليوم مجاناً
                    </a>
                </div>
            </div>

            <hr class="my-4" style="border-color: #E2D7C8;">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 text-muted" style="font-size: 0.7rem;">
                <div>
                    جميع الحقوق محفوظة © <?= date('Y') ?> — دليل المدارس السودانية | إحدى مبادرات <strong>SchoPlus Cloud Ecosystem</strong>.
                </div>
                <div>
                    عقدة سحابية مستقلة (Node 6) • النسخة 3.5.47
                </div>
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
    })();
    </script>
</body>
</html>
