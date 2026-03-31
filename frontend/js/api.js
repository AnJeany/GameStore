// ============================================
//  js/api.js — Base API caller
//  Tất cả fetch đều đi qua hàm này
// ============================================

const API_BASE = 'http://localhost:8081/backend/index.php';
// Nếu bạn dùng Laragon ở root: const API_BASE = '/gamestore/backend/index.php';

/**
 * Gọi API với tự động đính kèm JWT token
 * @param {string} endpoint   - VD: '/api/games'
 * @param {object} options    - fetch options (method, body, ...)
 * @returns {Promise<object>} - JSON response
 */
async function apiCall(endpoint, options = {}) {
    const token = localStorage.getItem('gs_token');

    const headers = {
        'Content-Type': 'application/json',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        ...(options.headers || {}),
    };

    const response = await fetch(`${API_BASE}${endpoint}`, {
        ...options,
        headers,
    });

    const data = await response.json();

    // Token hết hạn → đăng xuất
    if (response.status === 401) {
        Auth.logout();
        return data;
    }

    return data;
}

// ============================================
//  js/auth.js — Auth state management
// ============================================

const Auth = {
    // Lấy user từ localStorage
    getUser() {
        const raw = localStorage.getItem('gs_user');
        return raw ? JSON.parse(raw) : null;
    },

    // Lấy token
    getToken() {
        return localStorage.getItem('gs_token');
    },

    // Đã đăng nhập chưa
    isLoggedIn() {
        return !!this.getToken();
    },

    // Lưu sau khi login/register thành công
    save(token, user) {
        localStorage.setItem('gs_token', token);
        localStorage.setItem('gs_user', JSON.stringify(user));
    },

    // Đăng xuất
    logout() {
        localStorage.removeItem('gs_token');
        localStorage.removeItem('gs_user');
        window.location.href = 'login.html';
    },

    // Kiểm tra role
    hasRole(...roles) {
        const user = this.getUser();
        return user && roles.includes(user.role);
    },

    // Bảo vệ trang — redirect nếu chưa đăng nhập
    requireAuth() {
        if (!this.isLoggedIn()) {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    },

    // Bảo vệ trang theo role
    requireRole(...roles) {
        if (!this.requireAuth()) return false;
        if (!this.hasRole(...roles)) {
            window.location.href = 'index.html';
            return false;
        }
        return true;
    },
};

// ============================================
//  js/utils.js — Shared utilities
// ============================================

// Format tiền VND
function formatPrice(amount) {
    if (amount === 0) return 'Miễn phí';
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency', currency: 'VND'
    }).format(amount);
}

// Format ngày
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('vi-VN');
}

// Toast notification
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 3500);
}

// Render Navbar động dựa trên trạng thái đăng nhập
function renderNavbar() {
    const user = Auth.getUser();
    const navEl = document.getElementById('navbar-actions');
    if (!navEl) return;

    if (!user) {
        navEl.innerHTML = `
            <a href="login.html"    class="btn btn-ghost btn-sm">Đăng nhập</a>
            <a href="register.html" class="btn btn-primary btn-sm">Đăng ký</a>
        `;
        return;
    }

    const roleBadge = `<span class="badge badge-${user.role}">${user.role.toUpperCase()}</span>`;

    let extraLinks = '';
    if (user.role === 'dev' || user.role === 'admin') {
        extraLinks += `<a href="dev-dashboard.html" class="nav-link">Dev</a>`;
    }
    if (user.role === 'admin') {
        extraLinks += `<a href="admin-dashboard.html" class="nav-link">Admin</a>`;
    }

    navEl.innerHTML = `
        ${extraLinks}
        <a href="library.html" class="nav-link">📚 Library</a>
        <a href="cart.html"    class="nav-link cart-link">
            🛒 Giỏ hàng
            <span class="cart-badge" id="cart-badge">0</span>
        </a>
        <span style="color:var(--text-secondary);font-size:.85rem">${roleBadge} ${user.username}</span>
        <button class="btn btn-ghost btn-sm" onclick="Auth.logout()">Đăng xuất</button>
    `;

    updateCartBadge();
}

// Cập nhật số lượng giỏ hàng
async function updateCartBadge() {
    if (!Auth.isLoggedIn()) return;
    const data = await apiCall('/api/cart');
    const badge = document.getElementById('cart-badge');
    if (badge && data.items) {
        badge.textContent = data.items.length;
        badge.style.display = data.items.length > 0 ? 'flex' : 'none';
    }
}
