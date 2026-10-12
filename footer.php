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
    <footer class="daleel-footer" style="background-color: #F8FAFC; border-top: 1px solid #DDE4ED; padding: 50px 0 25px; margin-top: 60px; color: #475569;">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="mb-3">
                        <a href="/" class="d-inline-block text-decoration-none">
                            <img src="/assets/images/logo.png" alt="دليل المدارس" style="height: 46px; width: auto; object-fit: contain;">
                        </a>
                    </div>
                    <p class="text-muted leading-relaxed mb-0" style="font-size: 0.73rem; line-height: 1.75;">
                        خدمة رقمية مقدمة من منظومة <strong>ايسكو بلص (SchoPlus)</strong>، تهدف إلى مساعدة أولياء الأمور والطلاب في البحث والتعرف على المدارس ومقارنتها، وإتاحة مساحة احترافية لأصحاب المدارس للتعريف بخدماتهم التعليمية.
                    </p>
                </div>

                <div class="col-lg-2 col-md-6 col-6">
                    <h5 class="fw-bold mb-3" style="font-size: 0.85rem; color: #0B2A52;">تصفح الدليل</h5>
                    <a href="/" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">كافة المدارس</a>
                    <a href="/?view=schoplus" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">مدارس SchoPlus الذكية</a>
                    <a href="/?view=top_rated" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">المدارس الأعلى تقييماً</a>
                    <a href="/compare.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">مقارنة المدارس</a>
                    <a href="/app.php" class="d-block d-md-none text-success fw-bold text-decoration-none mb-2" style="font-size: 0.74rem;"><i class="fab fa-android me-1"></i> تطبيق أندرويد (APK)</a>
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">إضافة مدرسة جديدة</a>
                </div>

                <div class="col-lg-3 col-md-6 col-6">
                    <h5 class="fw-bold mb-3" style="font-size: 0.85rem; color: #0B2A52;">لأصحاب المدارس</h5>
                    <a href="/login.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">بوابة مدراء المدارس</a>
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">تسجيل مدرسة جديدة</a>
                    <a href="https://schoplus.sd/pricing.html" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">باقات نظام SchoPlus</a>
                    <a href="https://schoplus.sd/demo.html" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">طلب عرض تجريبي للنظام</a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold mb-3" style="font-size: 0.85rem; color: #0B2A52;">منظومة ايسكو بلص</h5>
                    <a href="https://schoplus.sd" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-globe text-primary me-1"></i> الموقع الإلكتروني لـ ايسكو بلص
                    </a>
                    <a href="/login.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-sign-in-alt text-secondary me-1"></i> تسجيل دخول المدرسة
                    </a>
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-3" style="font-size: 0.74rem;">
                        <i class="fas fa-plus-circle text-warning me-1"></i> تسجيل مدرسة جديدة
                    </a>
                    <a href="/register.php" class="btn-luxury-primary btn-sm w-100 justify-content-center" style="font-size: 0.78rem;">
                        <i class="fas fa-school"></i> سجّل مدرستك الآن
                    </a>
                </div>
            </div>

            <!-- سياسة الاستخدام وإخلاء المسؤولية -->
            <div class="mt-4 p-3 rounded-3" style="background: #FFFFFF; border: 1px solid #DDE4ED;">
                <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-info-circle text-primary mt-1" style="font-size: 0.85rem; flex-shrink: 0;"></i>
                    <div style="font-size: 0.71rem; color: #64748B; line-height: 1.7;">
                        <strong class="text-dark">سياسة الاستخدام وإخلاء المسؤولية:</strong>
                        هذا الدليل هو خدمة رقمية مفتوحة مقدمة من منظومة <strong>ايسكو بلص (SchoPlus)</strong> ولا يُعد دليلاً رسمياً معتمداً من وزارة التربية والتعليم أو أي جهة حكومية. كافة البيانات والمعلومات المنشورة تتم إضافتها وإدارتها مباشرة من قِبل أصحاب وإدارات المدارس على مسؤوليتهم.
                    </div>
                </div>
            </div>

            <hr class="my-3" style="border-color: #DDE4ED;">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 text-muted" style="font-size: 0.72rem;">
                <div>جميع الحقوق محفوظة © <?= date('Y') ?> — دليل المدارس | إحدى مبادرات <strong>SchoPlus</strong></div>
                <div>النسخة 3.6.43</div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- FLOATING COMPARE BAR -->
    <div id="spFloatingCompareBar" class="sp-floating-compare-bar" style="display: none;">
        <div class="container">
            <div class="sp-compare-bar-content">
                <div class="sp-compare-bar-info">
                    <div class="sp-compare-bar-title">
                        <i class="fas fa-balance-scale"></i>
                        <span>مقارنة المدارس (<span id="compareCount">0</span>/4)</span>
                    </div>
                    <button type="button" class="sp-compare-clear-btn" onclick="clearCompare()">مسح الكل</button>
                </div>
                <div class="sp-compare-thumbnails" id="compareThumbnails"></div>
                <div class="sp-compare-actions">
                    <a href="/compare.php" id="compareSubmitBtn" class="btn-luxury-primary sp-compare-go-btn">
                        <span>قارن الآن</span>
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

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
        if (document.cookie.indexOf('detected_country_iso=') === -1) {
            fetch('https://api.country.is/')
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d && d.country) {
                        document.cookie = 'detected_country_iso=' + encodeURIComponent(d.country) + '; path=/; max-age=2592000; SameSite=Lax';
                    }
                })
                .catch(function() {});
        }

        // Initialize compare UI
        if (window.renderCompareUI) {
            window.renderCompareUI();
        }
    });

    // ===== DALEEL SCHOOL COMPARISON ENGINE =====
    var DALEEL_COMPARE_KEY = 'daleel_compare_schools';
    var MAX_COMPARE_SCHOOLS = 4;

    window.getCompareSchools = function() {
        try {
            var raw = localStorage.getItem(DALEEL_COMPARE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch(e) {
            return [];
        }
    };

    window.saveCompareSchools = function(list) {
        try {
            localStorage.setItem(DALEEL_COMPARE_KEY, JSON.stringify(list));
        } catch(e) {}
        window.renderCompareUI();
    };

    window.toggleSchoolCompare = function(school) {
        if (!school || !school.id) return;
        var list = window.getCompareSchools();
        var idx = list.findIndex(function(s) { return String(s.id) === String(school.id); });
        if (idx > -1) {
            list.splice(idx, 1);
            window.saveCompareSchools(list);
        } else {
            if (list.length >= MAX_COMPARE_SCHOOLS) {
                alert('يمكنك مقارنة حتى 4 مدارس كحد أقصى في المرة الواحدة.');
                return;
            }
            list.push({
                id: String(school.id),
                name: school.name || 'مدرسة',
                logo: school.logo || '/assets/images/logo-icon.png',
                slug: school.slug || ''
            });
            window.saveCompareSchools(list);
        }
    };

    window.removeCompareSchool = function(id) {
        var list = window.getCompareSchools();
        list = list.filter(function(s) { return String(s.id) !== String(id); });
        window.saveCompareSchools(list);
    };

    window.clearCompare = function() {
        window.saveCompareSchools([]);
    };

    window.renderCompareUI = function() {
        var list = window.getCompareSchools();
        var bar = document.getElementById('spFloatingCompareBar');
        var countEl = document.getElementById('compareCount');
        var thumbsEl = document.getElementById('compareThumbnails');
        var submitBtn = document.getElementById('compareSubmitBtn');

        // Update compare buttons across the page
        document.querySelectorAll('[data-compare-id]').forEach(function(btn) {
            var id = btn.getAttribute('data-compare-id');
            var isSelected = list.some(function(s) { return String(s.id) === String(id); });
            var lbl = btn.querySelector('.compare-lbl');
            if (isSelected) {
                btn.classList.add('is-active');
                if (lbl) lbl.textContent = 'تمت الإضافة';
            } else {
                btn.classList.remove('is-active');
                if (lbl) lbl.textContent = 'مقارنة';
            }
        });

        if (!bar) return;

        if (list.length === 0) {
            bar.style.display = 'none';
            return;
        }

        bar.style.display = 'block';
        if (countEl) countEl.textContent = list.length;

        if (thumbsEl) {
            thumbsEl.innerHTML = '';
            list.forEach(function(s) {
                var item = document.createElement('div');
                item.className = 'sp-compare-thumb-item';
                item.title = s.name;
                item.innerHTML = '<img src="' + s.logo + '" alt="' + s.name + '">' +
                    '<button type="button" class="sp-compare-thumb-remove" onclick="event.stopPropagation(); removeCompareSchool(\'' + s.id + '\');" title="إزالة">×</button>';
                thumbsEl.appendChild(item);
            });
            for (var i = list.length; i < MAX_COMPARE_SCHOOLS; i++) {
                var slot = document.createElement('div');
                slot.className = 'sp-compare-slot-empty';
                slot.title = 'خانة شاغرة للمقارنة';
                slot.innerHTML = '<i class="fas fa-plus"></i>';
                thumbsEl.appendChild(slot);
            }
        }

        if (submitBtn) {
            var ids = list.map(function(s) { return s.id; }).join(',');
            submitBtn.href = '/compare.php?ids=' + encodeURIComponent(ids);
            submitBtn.innerHTML = '<span>قارن الآن (' + list.length + ')</span> <i class="fas fa-arrow-left"></i>';
        }
    };
    </script>
</body>
</html>
