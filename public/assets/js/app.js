document.addEventListener('DOMContentLoaded', function () {
    // Mobile sidebar toggle
    var toggleBtn = document.getElementById('bmsSidebarToggle');
    var backdrop = document.getElementById('bmsSidebarBackdrop');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            document.body.classList.remove('sidebar-open');
        });
    }

    // Desktop sidebar collapse (icon-only) & expand controls
    var setSidebarCollapsed = function (collapsed) {
        if (collapsed) {
            document.body.classList.add('sidebar-collapsed');
        } else {
            document.body.classList.remove('sidebar-collapsed');
        }

        try {
            window.localStorage.setItem('bms_sidebar_collapsed', collapsed ? '1' : '0');
        } catch (err) {
            // Ignore storage quota / privacy mode restrictions
        }

        document.cookie = 'bms_sidebar_collapsed=' + (collapsed ? '1' : '0')
            + '; path=/; max-age=31536000; SameSite=Lax';
    };

    try {
        var savedSidebarPref = window.localStorage.getItem('bms_sidebar_collapsed');
        if (savedSidebarPref === '1' && !document.body.classList.contains('sidebar-collapsed')) {
            setSidebarCollapsed(true);
        } else if (savedSidebarPref === '0' && document.body.classList.contains('sidebar-collapsed')) {
            setSidebarCollapsed(false);
        }
    } catch (err) {
        // Ignore storage read restrictions
    }

    var collapseButtons = document.querySelectorAll('[data-sidebar-collapse]');
    collapseButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setSidebarCollapsed(true);
        });
    });

    var expandButtons = document.querySelectorAll('[data-sidebar-expand]');
    expandButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setSidebarCollapsed(false);
        });
    });

    // Confirmation prompts on sensitive forms (status changes, project submission, etc.)
    var confirmForms = document.querySelectorAll('form[data-confirm]');
    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Dynamic department requirement hint on User Create/Edit forms
    var roleSelect = document.getElementById('role_id');
    var deptRequiredBadge = document.getElementById('departmentRequiredBadge');
    if (roleSelect && deptRequiredBadge) {
        var syncDeptRequirement = function () {
            var selectedOption = roleSelect.options[roleSelect.selectedIndex];
            var slug = selectedOption ? selectedOption.getAttribute('data-role-slug') : '';
            if (slug === 'administrator') {
                deptRequiredBadge.textContent = '(Optional for Administrator)';
                deptRequiredBadge.className = 'text-muted fw-normal small ms-1';
            } else {
                deptRequiredBadge.textContent = '*';
                deptRequiredBadge.className = 'text-danger ms-1';
            }
        };
        roleSelect.addEventListener('change', syncDeptRequirement);
        syncDeptRequirement();
    }

    // Offline-safe toggle panels for checklist upload/replace forms & version history rows
    var toggleButtons = document.querySelectorAll('[data-toggle-panel]');
    toggleButtons.forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            var targetId = btn.getAttribute('data-toggle-panel');
            if (!targetId) {
                return;
            }
            var panel = document.getElementById(targetId);
            if (!panel) {
                return;
            }
            event.preventDefault();
            panel.classList.toggle('d-none');
            var expanded = !panel.classList.contains('d-none');
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');

            if (expanded) {
                var fileInput = panel.querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.focus();
                }
            }
        });
    });

    // Offline fallback for topbar dropdowns (Alerts & User Account) when Bootstrap CDN JS is unavailable
    if (!window.bootstrap || typeof window.bootstrap.Dropdown !== 'function') {
        var topbarDropBtns = document.querySelectorAll('[data-topbar-dropdown]');
        var closeAllTopbarDrops = function (exceptMenu) {
            topbarDropBtns.forEach(function (btn) {
                var targetId = btn.getAttribute('data-topbar-dropdown');
                var menu = targetId ? document.getElementById(targetId) : null;
                if (menu && menu !== exceptMenu) {
                    menu.classList.remove('show');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        };

        topbarDropBtns.forEach(function (btn) {
            var menuId = btn.getAttribute('data-topbar-dropdown');
            var menu = menuId ? document.getElementById(menuId) : null;
            if (!menu) {
                return;
            }

            btn.addEventListener('click', function (event) {
                event.stopPropagation();
                closeAllTopbarDrops(menu);
                var isOpen = menu.classList.toggle('show');
                menu.style.right = '0';
                menu.style.left = 'auto';
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });

        document.addEventListener('click', function (event) {
            var clickedInside = false;
            topbarDropBtns.forEach(function (btn) {
                var targetId = btn.getAttribute('data-topbar-dropdown');
                var menu = targetId ? document.getElementById(targetId) : null;
                if ((menu && menu.contains(event.target)) || btn.contains(event.target)) {
                    clickedInside = true;
                }
            });
            if (!clickedInside) {
                closeAllTopbarDrops(null);
            }
        });
    }

    // Instant Bootstrap tooltips on minimalist table action icons, toolbar triggers & topbar icons
    if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
        var iconTriggers = document.querySelectorAll('.bms-action-icon[title], .bms-toolbar-icon-btn[title]');
        iconTriggers.forEach(function (el) {
            new window.bootstrap.Tooltip(el, {
                trigger: 'hover',
                placement: 'top',
                container: 'body'
            });
        });

        var topbarTriggers = document.querySelectorAll('.bms-topbar-icon-btn[title]');
        topbarTriggers.forEach(function (el) {
            var tip = new window.bootstrap.Tooltip(el, {
                trigger: 'hover',
                placement: 'bottom',
                container: 'body'
            });
            el.addEventListener('click', function () {
                tip.hide();
            });
        });
    }
});

