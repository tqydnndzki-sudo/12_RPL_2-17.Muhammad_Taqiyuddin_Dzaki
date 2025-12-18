/**
 * SIMBA OLE - Main JavaScript File
 * Sistem Internal Management Barang dan Order
 */

// Global Variables
let currentTheme = localStorage.getItem('theme') || 'light';
let isLoading = false;

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme
    initTheme();
    
    // Initialize sidebar
    initSidebar();
    
    // Initialize search
    initSearch();
    
    // Initialize notifications
    initNotifications();
    
    // Initialize modals
    initModals();
    
    // Initialize forms
    initForms();
    
    // Initialize tables
    initTables();
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize datepickers
    initDatepickers();
    
    // Initialize select2
    initSelect2();
    
    // Initialize charts if needed
    if (typeof Chart !== 'undefined') {
        initCharts();
    }
    
    // Global event listeners
    initEventListeners();
    
    console.log('SIMBA OLE initialized successfully');
});

// Theme Functions
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    // Set initial theme
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon();
    
    // Toggle theme on click
    themeToggle.addEventListener('click', toggleTheme);
    
    // Check system preference
    if (currentTheme === 'system' || !currentTheme) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        currentTheme = prefersDark ? 'dark' : 'light';
        localStorage.setItem('theme', currentTheme);
        document.documentElement.setAttribute('data-theme', currentTheme);
        updateThemeIcon();
    }
}

function toggleTheme() {
    currentTheme = currentTheme === 'light' ? 'dark' : 'light';
    localStorage.setItem('theme', currentTheme);
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon();
    
    // Dispatch theme change event
    document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: currentTheme } }));
}

function updateThemeIcon() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    const icon = themeToggle.querySelector('i');
    if (currentTheme === 'dark') {
        icon.className = 'fas fa-sun';
        icon.title = 'Switch to light mode';
    } else {
        icon.className = 'fas fa-moon';
        icon.title = 'Switch to dark mode';
    }
}

// Sidebar Functions
function initSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    if (!sidebarToggle || !sidebar) return;
    
    // Toggle sidebar on button click
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
    
    // Restore sidebar state
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 992 && 
            !sidebar.contains(event.target) && 
            !sidebarToggle.contains(event.target) &&
            !sidebar.classList.contains('collapsed')) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    });
}

// Search Functions
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide results if empty query
        if (!query) {
            searchResults.classList.remove('active');
            return;
        }
        
        // Show loading
        searchResults.innerHTML = '<div class="search-result-item"><i class="fas fa-spinner fa-spin"></i> Mencari...</div>';
        searchResults.classList.add('active');
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !searchResults.contains(event.target)) {
            searchResults.classList.remove('active');
        }
    });
    
    // Handle keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.classList.remove('active');
            searchInput.blur();
        }
        
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const items = searchResults.querySelectorAll('.search-result-item');
            if (items.length === 0) return;
            
            let currentIndex = -1;
            items.forEach((item, index) => {
                if (item.classList.contains('selected')) {
                    currentIndex = index;
                    item.classList.remove('selected');
                }
            });
            
            if (e.key === 'ArrowDown') {
                currentIndex = (currentIndex + 1) % items.length;
            } else {
                currentIndex = (currentIndex - 1 + items.length) % items.length;
            }
            
            items[currentIndex].classList.add('selected');
            items[currentIndex].scrollIntoView({ block: 'nearest' });
        }
        
        if (e.key === 'Enter') {
            const selected = searchResults.querySelector('.search-result-item.selected');
            if (selected) {
                selected.click();
            }
        }
    });
}

function performSearch(query) {
    if (!query) return;
    
    // Show loading
    const searchResults = document.getElementById('searchResults');
    searchResults.innerHTML = '<div class="search-result-item"><i class="fas fa-spinner fa-spin"></i> Mencari...</div>';
    
    // Perform AJAX search
    fetch(`api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results.length > 0) {
                displaySearchResults(data.results);
            } else {
                searchResults.innerHTML = '<div class="search-result-item"><i class="fas fa-search"></i> Tidak ditemukan</div>';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.innerHTML = '<div class="search-result-item"><i class="fas fa-exclamation-circle"></i> Error saat mencari</div>';
        });
}

function displaySearchResults(results) {
    const searchResults = document.getElementById('searchResults');
    searchResults.innerHTML = '';
    
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-${result.icon}"></i>
                <div>
                    <strong>${result.title}</strong>
                    <div style="font-size: 12px; color: #666;">${result.description}</div>
                </div>
            </div>
        `;
        
        item.addEventListener('click', function() {
            window.location.href = result.url;
        });
        
        searchResults.appendChild(item);
    });
}

// Notification Functions
function initNotifications() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationBadge = document.getElementById('notificationBadge');
    
    if (!notificationBtn || !notificationPanel) return;
    
    // Load notifications
    loadNotifications();
    
    // Toggle notification panel
    notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationPanel.classList.toggle('active');
    });
    
    // Close panel when clicking outside
    document.addEventListener('click', function() {
        notificationPanel.classList.remove('active');
    });
    
    // Mark all as read
    const markAllReadBtn = notificationPanel.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            markAllNotificationsAsRead();
        });
    }
    
    // Poll for new notifications every 60 seconds
    setInterval(loadNotifications, 60000);
}

function loadNotifications() {
    fetch('api/notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread);
                renderNotifications(data.notifications);
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function renderNotifications(notifications) {
    const notificationList = document.querySelector('.notification-list');
    if (!notificationList) return;
    
    notificationList.innerHTML = '';
    
    if (notifications.length === 0) {
        notificationList.innerHTML = '<div class="notification-item"><div class="notification-content"><p class="notification-title">Tidak ada notifikasi</p></div></div>';
        return;
    }
    
    notifications.forEach(notification => {
        const item = document.createElement('div');
        item.className = `notification-item ${notification.read ? '' : 'unread'}`;
        item.innerHTML = `
            <div class="notification-icon ${notification.type}">
                <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-content">
                <p class="notification-title">${notification.title}</p>
                <p class="notification-message">${notification.message}</p>
                <span class="notification-time">${formatTime(notification.created_at)}</span>
            </div>
        `;
        
        item.addEventListener('click', function() {
            markNotificationAsRead(notification.id);
            if (notification.url) {
                window.location.href = notification.url;
            }
        });
        
        notificationList.appendChild(item);
    });
}

function getNotificationIcon(type) {
    const icons = {
        'info': 'info-circle',
        'success': 'check-circle',
        'warning': 'exclamation-triangle',
        'danger': 'exclamation-circle',
        'order': 'shopping-cart',
        'inventory': 'box',
        'user': 'user'
    };
    return icons[type] || 'bell';
}

function markNotificationAsRead(notificationId) {
    fetch(`api/notifications.php?id=${notificationId}&action=read`, {
        method: 'POST'
    }).then(() => loadNotifications());
}

function markAllNotificationsAsRead() {
    fetch('api/notifications.php?action=read_all', {
        method: 'POST'
    }).then(() => loadNotifications());
}

// Modal Functions
function initModals() {
    // Initialize all modals
    document.querySelectorAll('[data-toggle="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            const modal = document.querySelector(target);
            if (modal) {
                showModal(modal);
            }
        });
    });
    
    // Close modals on close button click
    document.querySelectorAll('.modal-close, .btn[data-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                hideModal(modal);
            }
        });
    });
    
    // Close modal on outside click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal(this);
            }
        });
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(modal => {
                hideModal(modal);
            });
        }
    });
}

function showModal(modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus first input
    const input = modal.querySelector('input, select, textarea');
    if (input) {
        setTimeout(() => input.focus(), 300);
    }
    
    // Dispatch event
    modal.dispatchEvent(new CustomEvent('modal.show'));
}

function hideModal(modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
    
    // Dispatch event
    modal.dispatchEvent(new CustomEvent('modal.hide'));
}

// Form Functions
function initForms() {
    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    });
    
    // Password toggle
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
    
    // File upload preview
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function() {
            const preview = document.querySelector(this.getAttribute('data-preview'));
            if (!preview) return;
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // Auto-save forms
    document.querySelectorAll('form[data-autosave]').forEach(form => {
        let saveTimeout;
        
        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    saveFormDraft(form);
                }, 1000);
            });
        });
    });
}

function validateForm(form) {
    let isValid = true;
    
    form.querySelectorAll('[required]').forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let message = '';
    
    // Clear previous errors
    clearFieldError(field);
    
    // Required validation
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        message = 'Field ini wajib diisi';
    }
    
    // Email validation
    if (field.type === 'email' && value && !isValidEmail(value)) {
        isValid = false;
        message = 'Format email tidak valid';
    }
    
    // URL validation
    if (field.type === 'url' && value && !isValidURL(value)) {
        isValid = false;
        message = 'Format URL tidak valid';
    }
    
    // Number validation
    if (field.type === 'number' && value) {
        const min = field.getAttribute('min');
        const max = field.getAttribute('max');
        
        if (min && parseFloat(value) < parseFloat(min)) {
            isValid = false;
            message = `Nilai minimum adalah ${min}`;
        }
        
        if (max && parseFloat(value) > parseFloat(max)) {
            isValid = false;
            message = `Nilai maksimum adalah ${max}`;
        }
    }
    
    // Pattern validation
    if (field.getAttribute('pattern') && value) {
        const pattern = new RegExp(field.getAttribute('pattern'));
        if (!pattern.test(value)) {
            isValid = false;
            message = field.getAttribute('data-pattern-message') || 'Format tidak sesuai';
        }
    }
    
    // Custom validation
    if (field.getAttribute('data-validate')) {
        const validationType = field.getAttribute('data-validate');
        switch (validationType) {
            case 'phone':
                if (value && !isValidPhone(value)) {
                    isValid = false;
                    message = 'Format nomor telepon tidak valid';
                }
                break;
            case 'password':
                if (value && !isStrongPassword(value)) {
                    isValid = false;
                    message = 'Password harus mengandung huruf besar, kecil, angka, dan minimal 8 karakter';
                }
                break;
        }
    }
    
    if (!isValid) {
        showFieldError(field, message);
    } else {
        showFieldSuccess(field);
    }
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function showFieldSuccess(field) {
    field.classList.add('is-valid');
}

function clearFieldError(field) {
    field.classList.remove('is-invalid', 'is-valid');
    
    const errorDiv = field.parentNode.querySelector('.invalid-feedback');
    if (errorDiv) {
        errorDiv.remove();
    }
    
    const successDiv = field.parentNode.querySelector('.valid-feedback');
    if (successDiv) {
        successDiv.remove();
    }
}

function saveFormDraft(form) {
    const formData = new FormData(form);
    const data = {};
    
    formData.forEach((value, key) => {
        data[key] = value;
    });
    
    const formId = form.id || 'form-' + Math.random().toString(36).substr(2, 9);
    localStorage.setItem(`form-draft-${formId}`, JSON.stringify(data));
    
    showToast('Draft tersimpan secara otomatis', 'info');
}

function loadFormDraft(form) {
    const formId = form.id || 'form-' + Math.random().toString(36).substr(2, 9);
    const draft = localStorage.getItem(`form-draft-${formId}`);
    
    if (draft) {
        const data = JSON.parse(draft);
        Object.keys(data).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                field.value = data[key];
            }
        });
        
        showToast('Draft dimuat dari penyimpanan lokal', 'info');
    }
}

// Utility Functions
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidURL(url) {
    try {
        new URL(url);
        return true;
    } catch (_) {
        return false;
    }
}

function isValidPhone(phone) {
    const re = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/;
    return re.test(phone);
}

function isStrongPassword(password) {
    const re = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
    return re.test(password);
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    // Less than a minute
    if (diff < 60000) {
        return 'Baru saja';
    }
    
    // Less than an hour
    if (diff < 3600000) {
        const minutes = Math.floor(diff / 60000);
        return `${minutes} menit yang lalu`;
    }
    
    // Less than a day
    if (diff < 86400000) {
        const hours = Math.floor(diff / 3600000);
        return `${hours} jam yang lalu`;
    }
    
    // Less than a week
    if (diff < 604800000) {
        const days = Math.floor(diff / 86400000);
        return `${days} hari yang lalu`;
    }
    
    // Show date
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

// Table Functions
function initTables() {
    // Initialize DataTables if available
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json'
            },
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }
    
    // Row actions
    document.querySelectorAll('.table-action').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const action = this.getAttribute('data-action');
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            
            performTableAction(action, id, row);
        });
    });
    
    // Row selection
    document.querySelectorAll('.table-select').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
            
            updateBulkActions();
        });
    });
    
    // Select all
    const selectAll = document.querySelector('.select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.table-select');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                const row = checkbox.closest('tr');
                if (this.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
            
            updateBulkActions();
        });
    }
}

function performTableAction(action, id, row) {
    switch (action) {
        case 'view':
            viewItem(id);
            break;
        case 'edit':
            editItem(id);
            break;
        case 'delete':
            deleteItem(id, row);
            break;
        case 'print':
            printItem(id);
            break;
        case 'export':
            exportItem(id);
            break;
    }
}

function viewItem(id) {
    // Implement view functionality
    console.log('View item:', id);
}

function editItem(id) {
    // Implement edit functionality
    console.log('Edit item:', id);
}

function deleteItem(id, row) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`api/delete.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                    Swal.fire(
                        'Terhapus!',
                        'Data berhasil dihapus.',
                        'success'
                    );
                } else {
                    Swal.fire(
                        'Error!',
                        data.message || 'Gagal menghapus data.',
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Error!',
                    'Terjadi kesalahan saat menghapus data.',
                    'error'
                );
            });
        }
    });
}

function updateBulkActions() {
    const selectedCount = document.querySelectorAll('.table-select:checked').length;
    const bulkActions = document.querySelector('.bulk-actions');
    
    if (bulkActions) {
        if (selectedCount > 0) {
            bulkActions.classList.remove('d-none');
            bulkActions.querySelector('.selected-count').textContent = selectedCount;
        } else {
            bulkActions.classList.add('d-none');
        }
    }
}

// Tooltip Functions
function initTooltips() {
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    } else {
        // Fallback tooltips
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', showTooltip);
            element.addEventListener('mouseleave', hideTooltip);
        });
    }
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = this.title;
    
    const rect = this.getBoundingClientRect();
    tooltip.style.position = 'fixed';
    tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 10) + 'px';
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    
    document.body.appendChild(tooltip);
    this._tooltip = tooltip;
}

function hideTooltip() {
    if (this._tooltip) {
        this._tooltip.remove();
        this._tooltip = null;
    }
}

// Datepicker Functions
function initDatepickers() {
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            locale: 'id',
            disableMobile: true
        });
        
        flatpickr('.datetimepicker', {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            time_24hr: true,
            locale: 'id',
            disableMobile: true
        });
    }
}

// Select2 Functions
function initSelect2() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
}

// Chart Functions
function initCharts() {
    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                datasets: [{
                    label: 'Penjualan',
                    data: [65, 59, 80, 81, 56, 55, 40, 45, 60, 70, 85, 90],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
    
    // Inventory Chart
    const inventoryCtx = document.getElementById('inventoryChart');
    if (inventoryCtx) {
        new Chart(inventoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Elektronik', 'Kantor', 'IT', 'Lainnya'],
                datasets: [{
                    data: [30, 25, 35, 10],
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
}

// Global Event Listeners
function initEventListeners() {
    // Loading overlay
    document.addEventListener('ajaxStart', function() {
        showLoading();
    });
    
    document.addEventListener('ajaxStop', function() {
        hideLoading();
    });
    
    // Print functionality
    window.printPage = function() {
        window.print();
    };
    
    // Export functionality
    window.exportToExcel = function(tableId, filename = 'export.xlsx') {
        const table = document.getElementById(tableId);
        const ws = XLSX.utils.table_to_sheet(table);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
        XLSX.writeFile(wb, filename);
    };
    
    // Copy to clipboard
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Tersalin ke clipboard', 'success');
        }).catch(err => {
            console.error('Copy failed:', err);
            showToast('Gagal menyalin', 'error');
        });
    };
    
    // Refresh page
    window.refreshPage = function() {
        window.location.reload();
    };
    
    // Go back
    window.goBack = function() {
        window.history.back();
    };
    
    // Confirm before leaving unsaved form
    window.addEventListener('beforeunload', function(e) {
        const forms = document.querySelectorAll('form[data-unsaved]');
        let hasUnsavedChanges = false;
        
        forms.forEach(form => {
            if (form.classList.contains('unsaved')) {
                hasUnsavedChanges = true;
            }
        });
        
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}

// Loading Functions
function showLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.add('active');
    }
    isLoading = true;
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
    isLoading = false;
}

// Toast Notification
function showToast(message, type = 'info', duration = 3000) {
    // Remove existing toasts
    document.querySelectorAll('.toast').forEach(toast => toast.remove());
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${getToastIcon(type)}"></i>
        </div>
        <div class="toast-content">
            ${message}
        </div>
        <button class="toast-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Auto hide
    const hideTimeout = setTimeout(() => {
        hideToast(toast);
    }, duration);
    
    // Close button
    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(hideTimeout);
        hideToast(toast);
    });
}

function hideToast(toast) {
    toast.classList.remove('show');
    setTimeout(() => {
        toast.remove();
    }, 300);
}

function getToastIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// CSS for toast
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 300px;
        max-width: 400px;
        transform: translateX(120%);
        transition: transform 0.3s ease;
        z-index: 9999;
    }
    
    .toast.show {
        transform: translateX(0);
    }
    
    .toast-icon {
        font-size: 1.5rem;
    }
    
    .toast-success .toast-icon { color: #28a745; }
    .toast-error .toast-icon { color: #dc3545; }
    .toast-warning .toast-icon { color: #ffc107; }
    .toast-info .toast-icon { color: #17a2b8; }
    
    .toast-content {
        flex: 1;
    }
    
    .toast-close {
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        padding: 0.25rem;
        line-height: 1;
    }
`;

document.head.appendChild(toastStyles);

// Export functions for global use
window.SIMBA = {
    showLoading,
    hideLoading,
    showToast,
    showModal,
    hideModal,
    validateForm,
    copyToClipboard,
    exportToExcel,
    printPage,
    refreshPage,
    goBack
};