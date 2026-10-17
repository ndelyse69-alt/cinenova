/**
 * CineNova — auth client.
 * ---------------------------------------------------------------
 * Thin wrapper around /backend/api/auth.php. Every page that needs
 * to know "am I logged in / am I admin" goes through CNAuth.me(),
 * which is also what sidebar.js uses to paint the account menu.
 */
(function (global) {
  'use strict';

  // Resolve the CineNova project base so the app works from either
  // http://localhost/cinenova/ or the web server document root.
  const frontendMarker = '/frontend/';
  const frontendIndex = window.location.pathname.indexOf(frontendMarker);
  const CN_BASE = frontendIndex >= 0 ? window.location.pathname.slice(0, frontendIndex + 1) : '/';
  global.CN_BASE = CN_BASE;
  global.CN_FRONTEND = CN_BASE + 'frontend/';

  let csrfPromise = null;
  function csrfToken() {
    if (!csrfPromise) {
      csrfPromise = fetch(CN_BASE + 'backend/api/auth.php?action=csrf', { credentials: 'same-origin' })
        .then((r) => r.json())
        .then((res) => res.data.csrf_token);
    }
    return csrfPromise;
  }

  function me() {
    return fetch(CN_BASE + 'backend/api/auth.php?action=me', { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((res) => res.data || { authenticated: false, user: null })
      .catch(() => ({ authenticated: false, user: null }));
  }

  function postAction(action, fields) {
    return csrfToken().then((token) => {
      const body = new URLSearchParams(Object.assign({ action: action, csrf_token: token }, fields || {}));
      return fetch(CN_BASE + 'backend/api/auth.php', { method: 'POST', credentials: 'same-origin', body: body })
        .then(async (r) => {
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

  function login(username, password, returnUrl) {
    return postAction('login', { username: username, password: password, return_url: returnUrl || '' });
  }

  function signup(username, email, password, confirmPassword) {
    return postAction('signup', { username: username, email: email, password: password, confirm_password: confirmPassword });
  }

  function logout() {
    return postAction('logout', {});
  }

  function updateProfile(fields) {
    return postAction('update_profile', fields);
  }

  /**
   * Gate a page behind login (and optionally admin). Redirects if the
   * check fails, and resolves with the current user otherwise — pages
   * call this once at load, e.g.:
   *   CNAuth.requireAuth({ admin: true }).then(user => { ...init page... });
   */
  function requireAuth(opts) {
    opts = opts || {};
    return me().then((state) => {
      if (!state.authenticated) {
        window.location.href = CN_FRONTEND + 'pages/login.html?return=' + encodeURIComponent(window.location.pathname.split('/').pop());
        return Promise.reject(new Error('not authenticated'));
      }
      if (opts.admin && state.user.role !== 'admin') {
        window.location.href = CN_FRONTEND + 'pages/index.html';
        return Promise.reject(new Error('not admin'));
      }
      return state.user;
    });
  }

  global.CNAuth = { me: me, login: login, signup: signup, logout: logout, updateProfile: updateProfile, csrfToken: csrfToken, requireAuth: requireAuth };
})(window);
