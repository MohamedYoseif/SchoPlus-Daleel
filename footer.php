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
    <!-- APP DOWNLOAD SECTION (Above Footer) -->
    <section id="app-download" class="daleel-app-download-section" style="padding: 24px 0 0; margin-top: 45px;">
        <div class="container">
            <div class="card border-0 rounded-4 overflow-hidden shadow-sm" style="background: linear-gradient(135deg, #0B2A52 0%, #163870 100%); border-right: 4px solid #F5A020 !important;">
                <div class="card-body p-4">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-6 col-md-5 text-center text-md-start">
                            <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-3">
                                <div class="bg-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px; flex-shrink: 0;">
                                    <img src="/assets/images/logo-icon.png?v=3.7.4" alt="دليل المدارس" style="width: 36px; height: 36px; object-fit: contain;">
                                </div>
                                <div>
                                    <h4 class="fw-bold text-white mb-1" style="font-size: 1.15rem;">حمّل تطبيق دليل المدارس على هاتفك</h4>
                                    <p class="text-white-50 mb-0" style="font-size: 0.74rem;">تصفح أسرع، وصول فوري لكافة المدارس، وتواصل مباشر بنقرة واحدة.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-7">
                            <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-end gap-2">
                                <!-- Android Button -->
                                <a href="/downloads/daleel-schoplus.apk" download class="btn px-3 py-2 fw-bold text-white d-inline-flex align-items-center gap-2 rounded-3 shadow-xs" style="background: #1E5FA8; border: 1.5px solid #F5A020; font-size: 0.82rem;">
                                    <i class="fab fa-android fa-lg text-warning"></i>
                                    <div class="text-start">
                                        <span class="d-block text-warning opacity-75" style="font-size: 0.62rem; line-height: 1;">أندرويد مباشر</span>
                                        <span class="d-block fw-bold" style="font-size: 0.82rem; line-height: 1.2;">تنزيل ملف APK (56.5MB)</span>
                                    </div>
                                </a>

                                <!-- iOS Button -->
                                <button type="button" class="btn px-3 py-2 fw-bold text-dark d-inline-flex align-items-center gap-2 rounded-3 shadow-xs" style="background: #FFFFFF; border: 1.5px solid #DDE4ED; font-size: 0.82rem;" onclick="openIosInstallModal()">
                                    <i class="fab fa-apple fa-lg text-dark"></i>
                                    <div class="text-start">
                                        <span class="d-block text-muted" style="font-size: 0.62rem; line-height: 1;">آيفون (iOS)</span>
                                        <span class="d-block fw-bold" style="font-size: 0.82rem; line-height: 1.2; color: #0B2A52;">تثبيت فوري للشاشة</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="daleel-footer" style="background-color: #F8FAFC; border-top: 1px solid #DDE4ED; padding: 40px 0 25px; margin-top: 24px; color: #475569;">
        <div class="container">
            <div class="row g-4">
                <!-- Col 1: Identity & Description -->
                <div class="col-lg-4 col-md-6">
                    <div class="mb-3">
                        <a href="/" class="d-inline-block text-decoration-none">
                            <img src="/assets/images/logo.png?v=3.7.6" alt="دليل المدارس السودانية" style="height: 46px; width: auto; object-fit: contain;">
                        </a>
                    </div>
                    <p class="text-muted leading-relaxed mb-0" style="font-size: 0.73rem; line-height: 1.75;">
                        المنصة الرقمية الوطنية الموحدة للمدارس السودانية. تمكّن أولياء الأمور والطلاب من البحث والتعرف على كافة المدارس ومقارنتها، وتتيح لإدارات المدارس حضوراً رقمياً احترافياً وتواصلاً مباشراً مع الأسر.
                    </p>
                </div>

                <!-- Col 2: Directory Browsing (Parents/Students) -->
                <div class="col-lg-3 col-md-6 col-6">
                    <h5 class="fw-bold mb-3" style="font-size: 0.85rem; color: #0B2A52;">تصفح واستكشاف</h5>
                    <a href="/" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-th-large me-1 text-muted" style="font-size: 0.68rem;"></i> كافة المدارس
                    </a>
                    <a href="/#schoplus-schools" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-star me-1 text-warning" style="font-size: 0.68rem;"></i> المدارس الذكية المعتمدة
                    </a>
                    <a href="/?view=top_rated" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-award me-1 text-primary" style="font-size: 0.68rem;"></i> المدارس الأعلى تقييماً
                    </a>
                    <a href="/compare.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-balance-scale me-1 text-muted" style="font-size: 0.68rem;"></i> مقارنة المدارس
                    </a>
                </div>

                <!-- Col 3: School Administration (Management Only) -->
                <div class="col-lg-3 col-md-6 col-6">
                    <h5 class="fw-bold mb-3" style="font-size: 0.85rem; color: #0B2A52;">لأصحاب المدارس</h5>
                    <a href="/login.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-user-shield me-1 text-muted" style="font-size: 0.68rem;"></i> دخول بوابة الإدارة
                    </a>
                    <a href="/register.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-plus-circle me-1 text-success" style="font-size: 0.68rem;"></i> تسجيل واعتماد مدرسة جديدة
                    </a>
                    <a href="/presence-audit.php" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-satellite-dish me-1 text-muted" style="font-size: 0.68rem;"></i> فحص الوجود الرقمي للمدرسة
                    </a>
                    <a href="https://schoplus.sd" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-cloud me-1 text-primary" style="font-size: 0.68rem;"></i> حلول SchoPlus لإدارة المدارس
                    </a>
                </div>

                <!-- Col 4: Support & Contact -->
                <div class="col-lg-2 col-md-6">
                    <h5 class="fw-bold mb-3" style="font-size: 0.85rem; color: #0B2A52;">تواصل ودعم</h5>
                    <a href="https://wa.me/249912345678" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fab fa-whatsapp me-1 text-success" style="font-size: 0.78rem;"></i> واتساب الدعم الفني
                    </a>
                    <a href="https://schoplus.sd" target="_blank" class="d-block text-secondary text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-globe me-1 text-muted" style="font-size: 0.68rem;"></i> موقع المنصة الرسمي
                    </a>
                    <a href="/#app-download" class="d-block text-success fw-bold text-decoration-none mb-2" style="font-size: 0.74rem;">
                        <i class="fas fa-mobile-alt me-1" style="font-size: 0.68rem;"></i> تطبيق الهاتف الذكي
                    </a>
                </div>
            </div>

            <!-- سياسة الاستخدام وإخلاء المسؤولية -->
            <div class="mt-4 p-3 rounded-3" style="background: #FFFFFF; border: 1px solid #DDE4ED;">
                <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-info-circle text-primary mt-1" style="font-size: 0.85rem; flex-shrink: 0;"></i>
                    <div style="font-size: 0.71rem; color: #64748B; line-height: 1.7;">
                        <strong class="text-dark">سياسة الاستخدام وإخلاء المسؤولية:</strong>
                        هذا الدليل هو منصة رقمية وطنية مفتوحة ومستقلة. كافة البيانات والمعلومات المنشورة تتم إضافتها وإدارتها مباشرة من قِبل إدارات المدارس على مسؤوليتهم.
                    </div>
                </div>
            </div>

            <hr class="my-3" style="border-color: #DDE4ED;">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 text-muted" style="font-size: 0.72rem;">
                <div>جميع الحقوق محفوظة © <?= date('Y') ?> — دليل المدارس السودانية | ملتقى المدارس وأولياء الأمور في السودان</div>
                <div>خدمة رقمية مجانية مقدمة من فريق <strong>SchoPlus</strong></div>
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

    <!-- iOS PWA Install Modal (Apple Web App Guide) -->
    <div class="modal fade" id="iosInstallModal" tabindex="-1" aria-labelledby="iosInstallModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header border-0 px-4 pt-4 pb-2 text-white" style="background: linear-gradient(135deg, #0B2A52 0%, #1E5FA8 100%);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 46px; height: 46px;">
                            <img src="/assets/images/logo-icon.png?v=3.7.3" alt="دليل المدارس" style="width: 32px; height: 32px; object-fit: contain;">
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold fs-6 mb-0 text-white" id="iosInstallModalLabel">تثبيت التطبيق على الآيفون (iOS)</h5>
                            <span class="small opacity-75" style="font-size: 0.72rem;">تطبيق مباشر وسريع بدون متجر App Store</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="background: #F8FAFC;">
                    <p class="text-secondary small mb-3 leading-relaxed" style="font-size: 0.78rem;">
                        يمكنك إضافة تطبيق <strong>دليل المدارس السودانية</strong> مباشرة إلى شاشتك الرئيسية ليعمل كتطبيق أصلي سريع وفوري بـ 3 خطوات فقط من متصفح Safari:
                    </p>

                    <!-- Steps -->
                    <div class="d-flex flex-column gap-3">
                        <div class="p-3 bg-white rounded-3 border d-flex align-items-start gap-3 shadow-2xs">
                            <span class="badge rounded-pill d-flex align-items-center justify-content-center fw-bold" style="background: #0B2A52; color: #F5A020; width: 26px; height: 26px; font-size: 0.8rem; flex-shrink: 0;">1</span>
                            <div>
                                <strong class="d-block text-dark mb-1" style="font-size: 0.8rem;">اضغط على زر المشاركة في Safari</strong>
                                <span class="text-muted d-block small" style="font-size: 0.72rem;">ابحث عن أيقونة المربع ذو السهم للأعلى <span class="badge bg-light text-primary border px-2 py-1"><i class="fas fa-arrow-up-from-bracket"></i> مشاركة</span> في الشريط السفلي للمتصفح.</span>
                            </div>
                        </div>

                        <div class="p-3 bg-white rounded-3 border d-flex align-items-start gap-3 shadow-2xs">
                            <span class="badge rounded-pill d-flex align-items-center justify-content-center fw-bold" style="background: #0B2A52; color: #F5A020; width: 26px; height: 26px; font-size: 0.8rem; flex-shrink: 0;">2</span>
                            <div>
                                <strong class="d-block text-dark mb-1" style="font-size: 0.8rem;">اختر «إضافة إلى الشاشة الرئيسية»</strong>
                                <span class="text-muted d-block small" style="font-size: 0.72rem;">اسحب قائمة الخيارات لأسفل واضغط على <span class="badge bg-light text-success border px-2 py-1"><i class="fas fa-plus-square"></i> Add to Home Screen</span>.</span>
                            </div>
                        </div>

                        <div class="p-3 bg-white rounded-3 border d-flex align-items-start gap-3 shadow-2xs">
                            <span class="badge rounded-pill d-flex align-items-center justify-content-center fw-bold" style="background: #0B2A52; color: #F5A020; width: 26px; height: 26px; font-size: 0.8rem; flex-shrink: 0;">3</span>
                            <div>
                                <strong class="d-block text-dark mb-1" style="font-size: 0.8rem;">اضغط على «إضافة» (Add) في الزاوية العلوية</strong>
                                <span class="text-muted d-block small" style="font-size: 0.72rem;">سيظهر التطبيق فوراً على شاشة هاتفك بأيقونة وشعار الدليل الرسمي كأي تطبيق تم تحميله.</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between">
                        <span class="small text-muted" style="font-size: 0.7rem;"><i class="fab fa-apple text-dark me-1"></i> متوافق مع كافة أجهزة iPhone و iPad</span>
                        <button type="button" class="btn btn-sm btn-primary fw-bold px-4 py-2 rounded-3" style="background: #0B2A52; border-color: #0B2A52;" data-bs-dismiss="modal">فهمت ذلك</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Smart iOS Safari Floating Banner (Auto-detected on iPhone / iPad) -->
    <div id="spIosInstallBanner" class="position-fixed bottom-0 start-50 translate-middle-x p-3 w-100" style="max-width: 480px; z-index: 1080; display: none;">
        <div class="rounded-4 p-3 shadow-lg d-flex align-items-center justify-content-between gap-3 text-white border" style="background: linear-gradient(135deg, #0B2A52 0%, #163870 100%); border-color: #F5A020 !important;">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-white rounded-3 p-1 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; flex-shrink: 0;">
                    <img src="/assets/images/logo-icon.png?v=3.7.3" alt="دليل المدارس" style="width: 28px; height: 28px;">
                </div>
                <div>
                    <div class="fw-bold text-white small" style="font-size: 0.76rem;">ثبّت التطبيق على الآيفون 📱</div>
                    <div class="text-white-50 small" style="font-size: 0.68rem;">أضف الدليل لشاشتك الرئيسية بضغطة زر</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm text-dark fw-bold px-3 py-1 rounded-pill" style="background: #F5A020; font-size: 0.72rem; border: none;" onclick="openIosInstallModal()">
                    طريقة التثبيت
                </button>
                <button type="button" class="btn-close btn-close-white" style="font-size: 0.65rem;" onclick="dismissIosBanner()" aria-label="إغلاق"></button>
            </div>
        </div>
    </div>

    <!-- PWA Service Worker & iOS Banner Script -->
    <script>
    // Open iOS Install Modal
    window.openIosInstallModal = function() {
        var modalEl = document.getElementById('iosInstallModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
    };

    // Dismiss iOS banner for 7 days
    window.dismissIosBanner = function() {
        var b = document.getElementById('spIosInstallBanner');
        if (b) b.style.display = 'none';
        try { localStorage.setItem('daleel_hide_ios_banner', Date.now()); } catch(e){}
    };

    // Auto-detect iOS and Safari (non-standalone)
    (function() {
        var isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        var isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
        
        if (isIos && !isStandalone) {
            try {
                var lastDismissed = localStorage.getItem('daleel_hide_ios_banner');
                if (!lastDismissed || (Date.now() - parseInt(lastDismissed)) > 7 * 24 * 3600 * 1000) {
                    setTimeout(function() {
                        var banner = document.getElementById('spIosInstallBanner');
                        if (banner) banner.style.display = 'block';
                    }, 2500);
                }
            } catch(e){}
        }

        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js?v=3.7.3').then(function(reg) {
                    console.log('[PWA] ServiceWorker registered with scope:', reg.scope);
                }).catch(function(err) {
                    console.warn('[PWA] ServiceWorker registration failed:', err);
                });
            });
        }
    })();
    </script>
</body>
</html>
