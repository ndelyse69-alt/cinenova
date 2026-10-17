/**
 * CineNova — sidebar component.
 * ---------------------------------------------------------------
 * Ported 1:1 from the old includes/sidebar.php (renderSidebar()):
 * same markup/classes, same behavior, now built client-side and
 * injected into <div id="sidebar-root">. Every page includes this
 * file and calls:
 *   CNSidebar.render({ activePage: 'movies', mediaType: 'movie' });
 *
 * mediaType: 'movie' | 'tv' | null — null (used on index.html) means
 * "pure site nav", so the Search icon links to movies.html instead
 * of opening the live-search overlay.
 */
(function (global) {
  'use strict';

  const NAV_ITEMS = [
    { key: 'home', label: 'Home', href: 'index.html', icon: 'home' },
    { key: 'movies', label: 'Movies', href: 'movies.html', icon: 'film' },
    { key: 'series', label: 'Series', href: 'series.html', icon: 'tv' },
    { key: 'new-releases', label: 'New Releases', href: 'new-releases.html', icon: 'calendar' },
    { key: 'chine', label: 'Chine', href: 'chine.html', icon: 'globe' },
  ];

  function esc(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function accountMenuMarkup(user) {
    if (user) {
      const adminLink = user.role === 'admin'
        ? '<a href="admin/dashboard.html" class="block px-3 py-2 rounded-lg text-[12px] font-semibold text-orange-400 hover:bg-white/5 transition-colors">Admin Panel</a>'
        : '';
      return (
        '<a href="profile.html" class="block px-3 py-2 rounded-lg text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition-colors">My Profile</a>' +
        adminLink +
        '<a href="#" data-action="logout" class="block px-3 py-2 rounded-lg text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition-colors">Log Out</a>'
      );
    }
    return (
      '<a href="login.html" class="block px-3 py-2 rounded-lg text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition-colors">Log In</a>' +
      '<a href="signup.html" class="block px-3 py-2 rounded-lg text-[12px] font-semibold text-orange-400 hover:bg-white/5 transition-colors">Sign Up</a>'
    );
  }

  function avatarInitial(user) {
    return user && user.username ? esc(user.username.charAt(0).toUpperCase()) : cnIcon('user', 'w-3 h-3');
  }

  function render(opts) {
    const activePage = opts.activePage || 'home';
    const mediaType = opts.mediaType || null;
    const root = document.getElementById('sidebar-root');
    if (!root) return;

    const navHtml = NAV_ITEMS.map((item) => {
      const active = activePage === item.key;
      return (
        '<a href="' + item.href + '" class="sidebar-item flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[12px] font-medium transition-colors ' +
        (active ? 'is-active' : 'text-gray-400 hover:text-orange-400') + '" ' + (active ? 'aria-current="page"' : '') + '>' +
        cnIcon(item.icon, 'w-4 h-4 flex-shrink-0') + '<span>' + esc(item.label) + '</span></a>'
      );
    }).join('');

    const searchNavHtml = mediaType !== null
      ? '<button id="search-toggle" type="button" class="sidebar-item flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[12px] font-medium text-gray-400 hover:text-orange-400 transition-colors text-left">' + cnIcon('search', 'w-4 h-4 flex-shrink-0') + '<span>Search</span></button>'
      : '<a href="movies.html" class="sidebar-item flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[12px] font-medium text-gray-400 hover:text-orange-400 transition-colors">' + cnIcon('search', 'w-4 h-4 flex-shrink-0') + '<span>Search</span></a>';

    const searchOverlayHtml = mediaType !== null ? (
      '<div id="search-overlay" class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-xl flex items-center justify-center p-4">' +
        '<div class="w-full max-w-4xl bg-black/60 backdrop-blur-lg border border-white/10 rounded-3xl p-6 shadow-2xl relative max-h-[90vh] flex flex-col">' +
          '<div class="flex items-center justify-between mb-4 border-b border-white/10 pb-3">' +
            '<h2 class="text-xl font-bold text-white">Search ' + (mediaType === 'tv' ? 'Series' : 'Movies') + '</h2>' +
            '<button id="close-search" type="button" class="w-8 h-8 rounded-full bg-white/10 hover:bg-red-500/80 text-white transition-colors flex items-center justify-center" aria-label="Close search">' + cnIcon('close', 'w-4 h-4') + '</button>' +
          '</div>' +
          '<div class="relative mb-4">' +
            '<label for="live-search-input" class="sr-only">Search</label>' +
            '<input type="text" id="live-search-input" placeholder="Search for ' + (mediaType === 'tv' ? 'series' : 'movies') + '…" class="w-full px-5 py-4 bg-black/40 rounded-xl text-white placeholder-gray-400 border border-white/10 focus:border-orange-500 outline-none transition-colors text-sm">' +
            '<div id="search-loader" class="absolute right-4 top-1/2 -translate-y-1/2 hidden"><div class="w-5 h-5 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div></div>' +
          '</div>' +
          '<div id="live-results" class="overflow-y-auto hide-scrollbar flex-1 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-5"></div>' +
        '</div>' +
      '</div>'
    ) : '';

    root.innerHTML =
      '<aside class="hidden lg:flex fixed left-4 top-1/2 -translate-y-1/2 z-50 flex-col glass-sidebar rounded-2xl w-[156px] max-h-[calc(100vh-2rem)] overflow-hidden shadow-2xl" aria-label="Primary">' +
        '<a href="index.html" class="flex items-center justify-center gap-2 px-3 pt-4 pb-3 mb-1 border-b border-white/10 flex-shrink-0" aria-label="CineNova — home">' +
          '<img src="' + CN_FRONTEND + 'images/logo.png" alt="CineNova" class="h-7 w-7 object-contain flex-shrink-0" width="615" height="640" />' +
          '<span class="text-base font-bold text-white tracking-tight leading-none">Cine<span class="text-orange-500">Nova</span></span>' +
        '</a>' +
        '<nav class="flex flex-col gap-0.5 px-2 py-1 overflow-y-auto hide-scrollbar min-h-0">' +
          navHtml +
          '<a href="#" class="sidebar-item flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[12px] font-medium text-gray-400 hover:text-orange-400 transition-colors">' + cnIcon('download', 'w-4 h-4 flex-shrink-0') + '<span>Downloads</span></a>' +
          '<div class="h-px bg-white/10 my-1.5 mx-1"></div>' +
          searchNavHtml +
        '</nav>' +
        '<div class="mt-auto px-2 pb-3 pt-2 border-t border-white/10 relative flex-shrink-0">' +
          '<button id="profile-toggle" type="button" class="sidebar-item flex items-center gap-2.5 w-full px-2.5 py-1.5 rounded-lg text-gray-400 hover:text-orange-400 text-[12px] font-medium transition-colors text-left">' +
            '<span id="sidebar-avatar" class="flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg shadow-orange-500/20 flex-shrink-0 text-[9px] font-bold uppercase">' + cnIcon('user', 'w-3 h-3') + '</span>' +
            '<span id="sidebar-username" class="truncate">Account</span>' +
          '</button>' +
          '<div id="profile-dropdown" class="absolute bottom-[2.9rem] left-2 right-2 bg-black/90 backdrop-blur-xl border border-white/10 rounded-xl p-1 shadow-2xl transition-all duration-250 opacity-0 pointer-events-none transform translate-y-2 scale-95 origin-bottom">' +
            accountMenuMarkup(null) +
          '</div>' +
        '</div>' +
      '</aside>' +
      searchOverlayHtml +
      '<div class="lg:hidden flex items-center justify-between py-3.5 px-5 bg-black/70 backdrop-blur-xl border-b border-white/5 sticky top-0 z-40">' +
        '<a href="index.html" class="flex items-center gap-2" aria-label="CineNova — home">' +
          '<img src="' + CN_FRONTEND + 'images/logo.png" alt="CineNova" class="h-7 w-7 object-contain flex-shrink-0" width="615" height="640" />' +
          '<span class="text-base font-bold text-white">Cine<span class="text-orange-500">Nova</span></span>' +
        '</a>' +
        '<div id="mobile-topbar-account"><a href="signup.html" class="btn-ember px-4 py-1.5 rounded-full text-xs font-semibold">Sign Up</a></div>' +
      '</div>' +
      '<div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 flex justify-between items-center px-5 py-3 bg-black/85 backdrop-blur-xl border-t border-white/10">' +
        '<a href="index.html" class="' + (activePage === 'home' ? 'text-orange-500' : 'text-white/50') + ' hover:scale-110 transition-transform" aria-label="Home">' + cnIcon('home', 'w-5 h-5') + '</a>' +
        '<a href="movies.html" class="' + (activePage === 'movies' ? 'text-orange-500' : 'text-white/50') + ' hover:scale-110 transition-transform" aria-label="Movies">' + cnIcon('film', 'w-5 h-5') + '</a>' +
        '<a href="series.html" class="' + (activePage === 'series' ? 'text-orange-500' : 'text-white/50') + ' hover:scale-110 transition-transform" aria-label="Series">' + cnIcon('tv', 'w-5 h-5') + '</a>' +
        (mediaType !== null
          ? '<button id="mob-search-toggle" type="button" class="text-white/50 hover:text-white hover:scale-110 transition-transform" aria-label="Search">' + cnIcon('search', 'w-5 h-5') + '</button>'
          : '<a href="movies.html" class="text-white/50 hover:text-white hover:scale-110 transition-transform" aria-label="Search">' + cnIcon('search', 'w-5 h-5') + '</a>') +
        '<div id="mobile-bottombar-account"><a href="signup.html" class="flex items-center justify-center w-9 h-9 rounded-full bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg shadow-orange-500/30 hover:scale-110 transition-transform" aria-label="Sign Up">' + cnIcon('user', 'w-[1.125rem] h-[1.125rem]') + '</a></div>' +
      '</div>';

    bindInteractions(mediaType);
    applyAuthState();
  }

  function bindInteractions(mediaType) {
    const profileToggle = document.getElementById('profile-toggle');
    const profileDropdown = document.getElementById('profile-dropdown');
    if (profileToggle && profileDropdown) {
      profileToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = profileDropdown.classList.contains('opacity-0') === false;
        profileDropdown.classList.toggle('opacity-0', isOpen);
        profileDropdown.classList.toggle('pointer-events-none', isOpen);
        profileDropdown.classList.toggle('translate-y-2', isOpen);
        profileDropdown.classList.toggle('scale-95', isOpen);
      });
      document.addEventListener('click', function () {
        profileDropdown.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2', 'scale-95');
      });
      profileDropdown.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    if (mediaType !== null) {
      const overlay = document.getElementById('search-overlay');
      const openBtns = [document.getElementById('search-toggle'), document.getElementById('mob-search-toggle')].filter(Boolean);
      const closeBtn = document.getElementById('close-search');
      openBtns.forEach((btn) => btn.addEventListener('click', () => {
        overlay.classList.remove('hidden');
        const input = document.getElementById('live-search-input');
        if (input) { input.value = ''; input.focus(); }
        document.getElementById('live-results').innerHTML = '';
      }));
      if (closeBtn) closeBtn.addEventListener('click', () => overlay.classList.add('hidden'));
      overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.add('hidden'); });

      let searchTimer = null;
      const input = document.getElementById('live-search-input');
      if (input) {
        input.addEventListener('input', function () {
          clearTimeout(searchTimer);
          const q = input.value.trim();
          const loader = document.getElementById('search-loader');
          const results = document.getElementById('live-results');
          if (q.length < 2) { results.innerHTML = ''; return; }
          loader.classList.remove('hidden');
          searchTimer = setTimeout(function () {
            fetch(CN_BASE + 'backend/api/catalog.php?type=search&media=' + mediaType + '&q=' + encodeURIComponent(q))
              .then((r) => r.json())
              .then((res) => {
                loader.classList.add('hidden');
                const items = (res.data && res.data.items) || [];
                results.innerHTML = items.length
                  ? items.map((item) => CNCards.gridCardHtml(item)).join('')
                  : '<p class="col-span-full text-center text-gray-500 py-10 text-sm">No results found.</p>';
              })
              .catch(() => { loader.classList.add('hidden'); });
          }, 350);
        });
      }
    }

    document.addEventListener('click', function (e) {
      const logoutLink = e.target.closest('[data-action="logout"]');
      if (!logoutLink) return;
      e.preventDefault();
      CNAuth.logout().then(() => { window.location.href = 'index.html'; });
    });
  }

  function applyAuthState() {
    CNAuth.me().then(function (state) {
      const dropdown = document.getElementById('profile-dropdown');
      const username = document.getElementById('sidebar-username');
      const avatar = document.getElementById('sidebar-avatar');
      const mobileTop = document.getElementById('mobile-topbar-account');
      const mobileBottom = document.getElementById('mobile-bottombar-account');
      const user = state.authenticated ? state.user : null;

      if (dropdown) dropdown.innerHTML = accountMenuMarkup(user);
      if (username) username.textContent = user ? user.username : 'Account';
      if (avatar) avatar.innerHTML = avatarInitial(user);

      if (mobileTop) {
        mobileTop.innerHTML = user
          ? '<a href="profile.html" class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-r from-orange-500 to-orange-600 text-white text-xs font-bold uppercase shadow-lg shadow-orange-500/30" aria-label="My Profile">' + esc(user.username.charAt(0)) + '</a>'
          : '<a href="signup.html" class="btn-ember px-4 py-1.5 rounded-full text-xs font-semibold">Sign Up</a>';
      }
      if (mobileBottom) {
        mobileBottom.innerHTML = user
          ? '<a href="profile.html" class="flex items-center justify-center w-9 h-9 rounded-full bg-gradient-to-r from-orange-500 to-orange-600 text-white text-sm font-bold uppercase shadow-lg shadow-orange-500/30 hover:scale-110 transition-transform" aria-label="My Profile">' + esc(user.username.charAt(0)) + '</a>'
          : '<a href="signup.html" class="flex items-center justify-center w-9 h-9 rounded-full bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg shadow-orange-500/30 hover:scale-110 transition-transform" aria-label="Sign Up">' + cnIcon('user', 'w-[1.125rem] h-[1.125rem]') + '</a>';
      }
    });
  }

  global.CNSidebar = { render: render };
})(window);
