/**
 * Street Vendor License & Location Management
 * Main JavaScript File
 */

document.addEventListener('DOMContentLoaded', function() {

    // ---- Sidebar Toggle (Mobile) ----
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // ---- Auto-dismiss Flash Messages ----
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(function(msg) {
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            setTimeout(function() { msg.remove(); }, 400);
        }, 5000);

        // Close button
        const closeBtn = msg.querySelector('.close-flash');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-10px)';
                setTimeout(function() { msg.remove(); }, 400);
            });
        }
    });

    // ---- Client-side Table Search/Filter ----
    const searchInput = document.querySelector('#searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const table = document.querySelector('.data-table');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // ---- Status Filter Dropdown ----
    const statusFilter = document.querySelector('#statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            const value = this.value.toLowerCase();
            const table = document.querySelector('.data-table');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                if (!value) {
                    row.style.display = '';
                    return;
                }
                const badge = row.querySelector('.badge');
                if (badge) {
                    const status = badge.textContent.toLowerCase().trim();
                    row.style.display = status.includes(value) ? '' : 'none';
                } else {
                    row.style.display = '';
                }
            });
        });
    }

    // ---- Form Validation ----
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            // Remove previous error styles
            form.querySelectorAll('.input-error').forEach(function(el) {
                el.classList.remove('input-error');
            });
            form.querySelectorAll('.error-text').forEach(function(el) {
                el.remove();
            });

            // Check required fields
            const required = form.querySelectorAll('[required]');
            required.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e17055';
                    const errMsg = document.createElement('span');
                    errMsg.className = 'error-text';
                    errMsg.style.cssText = 'color:#e17055;font-size:0.78rem;display:block;margin-top:4px;';
                    errMsg.textContent = 'This field is required';
                    field.parentNode.appendChild(errMsg);
                } else {
                    field.style.borderColor = '';
                }
            });

            // Email validation
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(function(field) {
                if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                    isValid = false;
                    field.style.borderColor = '#e17055';
                    const errMsg = document.createElement('span');
                    errMsg.className = 'error-text';
                    errMsg.style.cssText = 'color:#e17055;font-size:0.78rem;display:block;margin-top:4px;';
                    errMsg.textContent = 'Please enter a valid email address';
                    field.parentNode.appendChild(errMsg);
                }
            });

            // Password confirmation
            const pass = form.querySelector('input[name="password"]');
            const confirmPass = form.querySelector('input[name="confirm_password"]');
            if (pass && confirmPass && pass.value !== confirmPass.value) {
                isValid = false;
                confirmPass.style.borderColor = '#e17055';
                const errMsg = document.createElement('span');
                errMsg.className = 'error-text';
                errMsg.style.cssText = 'color:#e17055;font-size:0.78rem;display:block;margin-top:4px;';
                errMsg.textContent = 'Passwords do not match';
                confirmPass.parentNode.appendChild(errMsg);
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    // ---- Confirm Dialogs for dangerous actions ----
    const confirmBtns = document.querySelectorAll('[data-confirm]');
    confirmBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // ---- Active Sidebar Link Highlight ----
    const currentPath = window.location.pathname;
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(function(link) {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });

});
