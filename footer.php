    </main>

    <!-- Footer -->
    <?php
    $isDaleelMobile = (
        (!empty($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'SchoPlusDaleelMobile')) ||
        (!empty($_COOKIE['is_daleel_app']) && $_COOKIE['is_daleel_app'] === '1') ||
        (isset($_GET['app']) && $_GET['app'] === 'daleel')
    );
    if (!$isDaleelMobile):
    ?>
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
                    <a href="/app.php" class="d-block d-md-none text-success fw-bold text-decoration-none mb-2" style="font-size: 0.74rem;"><i class="fab fa-android me-1"></i> تطبيق أندرويد (APK)</a>
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
    <?php endif; ?>

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

    // ===== SEARCHABLE SELECT COMPONENT WITH REALTIME SEARCH =====
    window.initSearchableSelect = function(selectEl) {
        if (!selectEl || selectEl.dataset.searchableInit === 'true') return;
        selectEl.dataset.searchableInit = 'true';

        var wrapper = document.createElement('div');
        wrapper.className = 'sp-searchable-wrapper';

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'sp-searchable-trigger';

        var triggerText = document.createElement('span');
        triggerText.className = 'sp-searchable-trigger-text';
        triggerText.textContent = (selectEl.options && selectEl.selectedIndex >= 0 && selectEl.options[selectEl.selectedIndex]) ? selectEl.options[selectEl.selectedIndex].text : '-- اختر --';

        var triggerArrow = document.createElement('i');
        triggerArrow.className = 'fas fa-chevron-down sp-searchable-trigger-arrow';

        trigger.appendChild(triggerText);
        trigger.appendChild(triggerArrow);
        wrapper.appendChild(trigger);

        var dropdown = document.createElement('div');
        dropdown.className = 'sp-searchable-dropdown';

        var header = document.createElement('div');
        header.className = 'sp-searchable-header';

        var inputWrap = document.createElement('div');
        inputWrap.className = 'sp-searchable-input-wrap';

        var searchIcon = document.createElement('i');
        searchIcon.className = 'fas fa-search';

        var searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'sp-searchable-input';
        searchInput.placeholder = 'ابحث في القائمة...';
        searchInput.autocomplete = 'off';

        inputWrap.appendChild(searchIcon);
        inputWrap.appendChild(searchInput);
        header.appendChild(inputWrap);
        dropdown.appendChild(header);

        var optionsList = document.createElement('div');
        optionsList.className = 'sp-searchable-options';
        dropdown.appendChild(optionsList);

        var emptyMsg = document.createElement('div');
        emptyMsg.className = 'sp-searchable-empty';
        emptyMsg.style.display = 'none';
        emptyMsg.textContent = 'لا توجد نتائج مطابقة للبحث';
        dropdown.appendChild(emptyMsg);

        function rebuildOptions() {
            optionsList.innerHTML = '';
            var opts = Array.from(selectEl.options || []);
            triggerText.textContent = (selectEl.options && selectEl.selectedIndex >= 0 && selectEl.options[selectEl.selectedIndex]) ? selectEl.options[selectEl.selectedIndex].text : '-- اختر --';

            opts.forEach(function(opt) {
                var item = document.createElement('div');
                item.className = 'sp-searchable-option' + (opt.selected ? ' is-selected' : '');
                item.dataset.value = opt.value;
                item.textContent = opt.text;

                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    selectEl.value = opt.value;
                    triggerText.textContent = opt.text;
                    wrapper.classList.remove('is-open');

                    selectEl.dispatchEvent(new Event('change', { bubbles: true }));

                    optionsList.querySelectorAll('.sp-searchable-option').forEach(function(el) {
                        el.classList.remove('is-selected');
                    });
                    item.classList.add('is-selected');
                });

                optionsList.appendChild(item);
            });
            filterOptions(searchInput.value || '');
        }

        function filterOptions(query) {
            var q = query.trim().toLowerCase();
            var items = optionsList.querySelectorAll('.sp-searchable-option');
            var visibleCount = 0;
            items.forEach(function(item) {
                var text = item.textContent.toLowerCase();
                if (!q || text.indexOf(q) !== -1) {
                    item.classList.remove('is-hidden');
                    visibleCount++;
                } else {
                    item.classList.add('is-hidden');
                }
            });
            emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
        }

        searchInput.addEventListener('input', function() {
            filterOptions(this.value);
        });

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.sp-searchable-wrapper.is-open').forEach(function(w) {
                if (w !== wrapper) w.classList.remove('is-open');
            });
            var isOpen = wrapper.classList.toggle('is-open');
            if (isOpen) {
                searchInput.value = '';
                filterOptions('');
                setTimeout(function() { searchInput.focus(); }, 60);
            }
        });

        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('is-open');
            }
        });

        wrapper.appendChild(dropdown);
        selectEl.parentNode.insertBefore(wrapper, selectEl);
        selectEl.style.display = 'none';

        rebuildOptions();

        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                rebuildOptions();
            });
            observer.observe(selectEl, { childList: true });
        }
        selectEl._refreshSearchable = rebuildOptions;
    };

    document.addEventListener('DOMContentLoaded', function() {
        var selectors = [
            'select#filterCountrySelect',
            'select#filterStateSelect',
            'select#filterCitySelect',
            'select#regCountry',
            'select#regState',
            'select#regCity',
            'select#dashCountry',
            'select#dashState',
            'select#dashCity',
            'select.searchable-select'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(function(el) {
            window.initSearchableSelect(el);
        });

        // Fast client-side IP country detector fallback
        if (!document.cookie.includes('detected_country_iso=')) {
            fetch('https://api.country.is/')
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d && d.country) {
                        document.cookie = 'detected_country_iso=' + encodeURIComponent(d.country) + '; path=/; max-age=2592000; SameSite=Lax';
                    }
                }).catch(function() {});
        }
    });
    </script>
</body>
</html>
