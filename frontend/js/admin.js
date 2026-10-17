/**
 * CineNova — admin shell.
 * ---------------------------------------------------------------
 * Shared layout (left nav + topbar), matching frontend/css/admin.css
 * exactly (.admin-shell / .admin-sidebar / .admin-nav-link / etc.),
 * for every frontend/pages/admin/ page, plus fetch helpers against
 * /backend/api/admin/*.php. Each admin page calls:
 *   CNAdmin.init('dashboard', 'Dashboard', 'Overview of your library').then(user => { ...load page data... });
 */
(function (global) {
  'use strict';

  const NAV = [
    { key: 'dashboard', label: 'Dashboard', href: 'dashboard.html', icon: 'grid' },
    { key: 'movies', label: 'Movies & Series', href: 'movies.html', icon: 'film' },
    { key: 'categories', label: 'Categories', href: 'categories.html', icon: 'tag' },
    { key: 'countries', label: 'Countries', href: 'countries.html', icon: 'globe' },
    { key: 'upload', label: 'Upload Video', href: 'upload.html', icon: 'upload' },
    { key: 'users', label: 'Users', href: 'users.html', icon: 'users' },
    { key: 'analytics', label: 'Analytics', href: 'analytics.html', icon: 'chart' },
    { key: 'visitors', label: 'Visitors', href: 'visitors.html', icon: 'eye' },
    { key: 'settings', label: 'Settings', href: 'settings.html', icon: 'cog' },
    { key: 'system', label: 'System', href: 'system.html', icon: 'server' },
  ];

  function esc(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function shellHtml(activeKey, user) {
    const navHtml = NAV.map((item) => (
      '<a href="' + item.href + '" class="admin-nav-link ' + (item.key === activeKey ? 'is-active' : '') + '">' +
        cnIcon(item.icon, 'w-[1.15rem] h-[1.15rem]') + '<span>' + item.label + '</span>' +
      '</a>'
    )).join('');

    return (
      '<div class="admin-sidebar-overlay" id="admin-sidebar-overlay"></div>' +
      '<aside class="admin-sidebar" id="admin-sidebar">' +
        '<a href="dashboard.html" class="admin-sidebar-brand">' +
          '<img src="' + CN_FRONTEND + 'images/logo.png" alt="CineNova" class="h-7 w-7 object-contain" width="615" height="640" />' +
          '<span class="text-base font-bold text-white">Cine<span class="text-orange-500">Nova</span><br><span class="text-[11px] text-gray-500 font-normal">Admin Panel</span></span>' +
        '</a>' +
        '<nav class="admin-nav">' + navHtml + '</nav>' +
        '<div class="admin-sidebar-footer">' +
          '<div class="admin-user-chip">' +
            '<div class="admin-user-avatar">' + esc(user.username.charAt(0).toUpperCase()) + '</div>' +
            '<div class="min-w-0"><p class="text-xs font-bold text-white truncate">' + esc(user.username) + '</p><p class="text-[11px] text-gray-500">Administrator</p></div>' +
          '</div>' +
          '<a href="../index.html" class="admin-nav-link mt-2">' + cnIcon('home', 'w-[1.15rem] h-[1.15rem]') + '<span>View Site</span></a>' +
          '<a href="#" data-action="admin-logout" class="admin-nav-link" style="color:#fca5a5">' + cnIcon('logout', 'w-[1.15rem] h-[1.15rem]') + '<span>Log Out</span></a>' +
        '</div>' +
      '</aside>' +
      '<div class="admin-main">' +
        '<header class="admin-topbar">' +
          '<div class="flex items-center gap-3">' +
            '<button type="button" class="admin-menu-btn" id="admin-menu-btn">' + cnIcon('menu', 'w-5 h-5') + '</button>' +
            '<div><p class="admin-page-title" id="admin-page-title"></p><p class="admin-page-subtitle" id="admin-page-subtitle"></p></div>' +
          '</div>' +
          '<div class="admin-user-avatar">' + esc(user.username.charAt(0).toUpperCase()) + '</div>' +
        '</header>' +
        '<div class="admin-content" id="admin-content"></div>' +
      '</div>'
    );
  }

  function init(activeKey, pageTitle, pageSubtitle) {
    document.body.classList.add('admin-body');
    return CNAuth.requireAuth({ admin: true }).then(function (user) {
      const root = document.getElementById('admin-shell-root');
      root.innerHTML = shellHtml(activeKey, user);
      document.getElementById('admin-page-title').textContent = pageTitle;
      document.getElementById('admin-page-subtitle').textContent = pageSubtitle || '';

      const sidebar = document.getElementById('admin-sidebar');
      const overlay = document.getElementById('admin-sidebar-overlay');
      document.getElementById('admin-menu-btn').addEventListener('click', function () {
        sidebar.classList.toggle('is-open');
        overlay.classList.toggle('is-open');
      });
      overlay.addEventListener('click', function () {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
      });

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-action="admin-logout"]')) {
          e.preventDefault();
          CNAuth.logout().then(() => { window.location.href = '../login.html'; });
        }
      });
      return user;
    });
  }

  function flash(type, message) {
    const content = document.getElementById('admin-content');
    if (!content) return;
    const box = document.createElement('div');
    box.className = 'admin-flash admin-flash--' + type;
    box.textContent = message;
    content.prepend(box);
    setTimeout(() => box.remove(), 5000);
  }

  function get(url) {
    return fetch(url, { credentials: 'same-origin' }).then((r) => r.json());
  }

  function post(url, fields) {
    return CNAuth.csrfToken().then((token) => {
      const body = new URLSearchParams(Object.assign({ csrf_token: token }, fields || {}));
      return fetch(url, { method: 'POST', credentials: 'same-origin', body: body }).then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data.success) {
          const err = new Error(data.error || 'Request failed.');
          err.data = data;
          throw err;
        }
        return data;
      });
    });
  }

  function postForm(url, formData) {
    return CNAuth.csrfToken().then((token) => {
      formData.append('csrf_token', token);
      return fetch(url, { method: 'POST', credentials: 'same-origin', body: formData }).then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data.success) {
          const err = new Error(data.error || 'Request failed.');
          err.data = data;
          throw err;
        }
        return data;
      });
    });
  }

  global.CNAdmin = { init: init, get: get, post: post, postForm: postForm, flash: flash, esc: esc };
})(window);
