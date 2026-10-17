/**
 * CineNova — watch page controller.
 * ---------------------------------------------------------------
 * Reads ?id=&type=&season=&episode= from the URL, fetches
 * /backend/api/watch.php, and renders the same 4-section layout the
 * original watch.php used: title strip, player + "This
 * Title"/Episodes/Parts/Collection switcher, description + cast,
 * comments + site-owner card, recommended grid. Every non-local
 * title with a real tmdb_id gets a VidSrc fallback, so "Watch Full
 * Movie" plays that unless a curated embed exists — the "demo
 * build" notice is the rare exception, not the default.
 */
(function () {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const id = parseInt(params.get('id') || '0', 10);
  const mediaType = params.get('type') === 'tv' ? 'tv' : 'movie';
  let season = parseInt(params.get('season') || '1', 10);
  let episode = parseInt(params.get('episode') || '1', 10);

  document.addEventListener('DOMContentLoaded', function () {
    CNSidebar.render({ activePage: mediaType === 'tv' ? 'series' : 'movies', mediaType: mediaType });
    CNFooter.render();
    initSearch();
    initComments();
    initTabs();
    loadPopularSidebar();

    if (!id) {
      document.querySelector('main .container').innerHTML = '<div class="text-center text-gray-400 py-20">Title not found.</div>';
      return;
    }
    loadTitle();
  });

  function esc(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  // ---------------------------------------------------------
  // Details tabs — Description / Cast (Show All) / Comments / Site
  // Owner. Only one panel is visible at a time; clicking a tab
  // swaps the active button state and the matching panel.
  // ---------------------------------------------------------
  function initTabs() {
    const tabs = document.querySelectorAll('[data-tab-target]');
    const panels = document.querySelectorAll('[data-tab-panel]');
    const scrollBody = document.querySelector('.watch-tabs-content .watch-scroll-body');
    if (!tabs.length || !panels.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener('click', function () {
        const target = this.dataset.tabTarget;
        tabs.forEach((t) => {
          const active = t === this;
          t.classList.toggle('is-active', active);
          t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((p) => { p.classList.toggle('hidden', p.dataset.tabPanel !== target); });
        if (scrollBody) scrollBody.scrollTop = 0;
      });
    });
  }

  // ---------------------------------------------------------
  // Popular Movies — right-rail sidebar, ranked #1-8 by trending
  // this week. Independent of the current title's own data, so it
  // loads immediately rather than waiting on /watch.php.
  // ---------------------------------------------------------
  function loadPopularSidebar() {
    const list = document.getElementById('popular-movies-list');
    if (!list) return;
    fetch(CN_BASE + 'backend/api/catalog.php?type=trending&media=' + mediaType + '&window=week&limit=9')
      .then((r) => r.json())
      .then((res) => {
        const items = ((res.data && res.data.items) || []).filter((item) => Number(item.id) !== id).slice(0, 8);
        if (!items.length) { list.innerHTML = '<p class="text-xs text-gray-500 px-2 py-4 text-center">No popular titles right now.</p>'; return; }
        list.innerHTML = items.map((item, i) => CNCards.popularItemHtml(item, i + 1)).join('');
      })
      .catch(() => { list.innerHTML = '<p class="text-xs text-gray-500 px-2 py-4 text-center">Popular titles are temporarily unavailable.</p>'; });
  }

  function loadTitle() {
    fetch(CN_BASE + 'backend/api/watch.php?id=' + id + '&type=' + mediaType + '&season=' + season + '&episode=' + episode)
      .then((r) => r.json())
      .then((res) => { if (res.success) render(res.data); });
  }

  function render(data) {
    document.title = data.title + ' — CineNova';
    document.getElementById('back-link').href = data.browse_href;
    document.getElementById('back-label').textContent = 'Back to ' + data.browse_label;

    document.getElementById('watch-title').textContent = data.title;

    const meta = document.getElementById('watch-meta');
    let metaHtml =
      '<span class="meta-pill font-mono">' + esc(data.year) + '</span>' +
      '<span class="meta-pill meta-pill--rating">' + cnIcon('star', 'w-3 h-3') + ' ' + esc(data.vote_average) + '</span>';
    if (data.runtime) metaHtml += '<span class="meta-pill">' + esc(data.runtime) + '</span>';
    (data.genres || []).forEach((g) => { metaHtml += '<span class="meta-pill meta-pill--genre">' + esc(g) + '</span>'; });
    if (data.country) metaHtml += '<span class="meta-pill meta-pill--country">' + cnIcon('globe', 'w-3 h-3') + ' ' + esc(data.country) + '</span>';
    meta.innerHTML = metaHtml;

    const poster = document.getElementById('player-poster-img');
    if (poster) poster.src = data.player_image || data.backdrop_url || data.poster_url || '';

    const captionBadge = document.getElementById('player-caption-badge');
    if (captionBadge) {
      let badgeText = data.title + (data.year ? ' ' + data.year : '');
      if (mediaType === 'tv') {
        const s = String(data.season || season).padStart(2, '0');
        const e = String(data.episode || episode).padStart(2, '0');
        badgeText += ' · S' + s + ' E' + e;
      }
      captionBadge.textContent = badgeText;
      captionBadge.classList.remove('hidden');
    }

    document.getElementById('btn-watch-full').innerHTML = cnIcon('film', 'w-4 h-4') + ' ' + esc(data.watch_full_label);

    document.getElementById('watch-overview').textContent = data.overview;
    const tagline = document.getElementById('watch-tagline');
    if (data.tagline) { tagline.textContent = '"' + data.tagline + '"'; tagline.classList.remove('hidden'); }
    else { tagline.classList.add('hidden'); }

    const factsWrap = document.getElementById('watch-facts');
    factsWrap.innerHTML = (data.facts || []).map(([label, value]) =>
      '<div class="info-fact flex items-center justify-between text-sm"><span class="text-gray-500">' + esc(label) + '</span><span class="text-white font-medium text-right">' + esc(value) + '</span></div>'
    ).join('');

    // ---- Cast ----
    const castList = document.getElementById('cast-list');
    const castEmpty = document.getElementById('cast-empty');
    if ((data.cast || []).length) {
      castEmpty.classList.add('hidden');
      castList.classList.remove('hidden');
      castList.innerHTML = data.cast.map((c) => (
        '<div class="cast-card cast-card-sm text-center"><div class="cast-avatar-fallback aspect-square rounded-lg overflow-hidden mb-1 flex items-center justify-center">' +
          (c.profile_url ? '<img src="' + esc(c.profile_url) + '" alt="' + esc(c.name) + '" class="w-full h-full object-cover" loading="lazy">' : cnIcon('user', 'w-4 h-4 text-white/25')) +
          '</div><p class="text-[9px] font-semibold text-white truncate">' + esc(c.name) + '</p><p class="text-[8px] text-gray-500 truncate">' + esc(c.character) + '</p></div>'
      )).join('');
    } else {
      castList.classList.add('hidden');
      castEmpty.classList.remove('hidden');
    }

    // ---- Similar / Recommended ----
    document.getElementById('recommended-heading').textContent = 'Recommended ' + data.browse_label;
    document.getElementById('recommended-sub').textContent = 'Titles with similar story, genre, and familiar cast members from ' + data.title + '.';
    document.getElementById('icon-recommended').innerHTML = cnIcon(mediaType === 'tv' ? 'tv' : 'film', 'w-4 h-4');
    document.getElementById('similar-grid').innerHTML = (data.similar || []).map((item) => CNCards.gridCardHtml(item)).join('');

    // ---- Right rail switcher (This Title / Episodes / Parts / Collection) ----
    document.getElementById('switcher-heading').textContent = data.switcher_heading;
    renderSwitcher(data);

    window.CINENOVA_TRAILER_KEY = data.trailer_key || '';
    window.CINENOVA_COMMENTS_KEY = data.comments_key;
    initPlayerControls(data);
    const firstPlayable = (data.episode_items || []).find((item) => item && item.kind === 'embed' && item.url);
    const initialUrl = firstPlayable ? firstPlayable.url : data.vidsrc_url;
    if (initialUrl) {
      playEmbed(initialUrl, firstPlayable ? (firstPlayable.label || 'Full Movie') : (mediaType === 'movie' ? 'Full Movie' : 'Episode ' + (data.episode || episode)), firstPlayable ? (data.vidsrc_urls || [])[0] || (data.vidsrc_urls || [])[1] || '' : (data.vidsrc_urls || [])[1] || '');
    setupDownload(data);
    }
  }

  function renderSwitcher(data) {
    const list = document.getElementById('switcher-list');
    const items = data.episode_items || [];

    if (!items.length) {
      list.innerHTML =
        '<div class="episode-item is-active cursor-default"><div class="episode-thumb !w-11 !h-16 rounded-md"><img src="' + esc(data.poster_url) + '" alt="" loading="lazy"></div>' +
        '<div><p class="text-xs font-semibold text-white">Feature Presentation</p><p class="text-[11px] text-gray-500">Standalone film &middot; Full length</p></div></div>';
      return;
    }

    if (items[0].kind === 'embed') {
      list.innerHTML = items.map((it, i) =>
        '<button type="button" data-embed-item data-embed-url="' + esc(it.url) + '" data-fallback-url="' + esc(it.fallback_url || '') + '" data-download-url="' + esc(it.download_url || '') + '" data-title="' + esc(it.label) + '" class="episode-item w-full ' + (i === 0 ? 'is-active' : '') + '">' +
          '<div class="episode-thumb !w-11 !h-16 rounded-md"><img src="' + esc(data.player_image) + '" alt="" loading="lazy"></div>' +
          '<div class="text-left min-w-0"><p class="text-xs font-semibold text-white truncate">' + esc(it.label) + '</p><p class="text-[11px] text-gray-500 truncate">' + esc(data.title) + '</p></div>' +
        '</button>'
      ).join('');
      list.querySelectorAll('[data-embed-item]').forEach((btn) => {
        btn.addEventListener('click', function () {
          list.querySelectorAll('[data-embed-item]').forEach((b) => b.classList.remove('is-active'));
          this.classList.add('is-active');
          playEmbed(this.dataset.embedUrl, this.dataset.title, this.dataset.fallbackUrl || '');
          setupDownload(data);
        });
      });
    } else if (items[0].kind === 'episode') {
      list.innerHTML = items.map((ep, i) => (
        '<button type="button" data-episode-item data-still="' + esc(ep.still_url || data.player_image) + '" data-watch-url="' + esc(ep.watch_url || '') + '" data-fallback-watch-url="' + esc(ep.fallback_watch_url || '') + '" data-title="Episode ' + ep.episode_number + ': ' + esc(ep.name) + '" data-overview="' + esc(ep.overview) + '" class="episode-item w-full ' + (i === 0 ? 'is-active' : '') + '">' +
          '<div class="episode-thumb">' + (ep.still_url ? '<img src="' + esc(ep.still_url) + '" alt="" loading="lazy">' : '<div class="h-full w-full flex items-center justify-center">' + cnIcon('film', 'w-4 h-4 text-white/25') + '</div>') + '</div>' +
          '<div class="text-left min-w-0"><p class="text-xs font-semibold text-white truncate">Ep ' + ep.episode_number + ' &middot; ' + esc(ep.name) + '</p><p class="text-[11px] text-gray-500 truncate">' + esc(ep.overview) + '</p></div>' +
        '</button>'
      )).join('');
      const episodeItems = list.querySelectorAll('[data-episode-item]');
      episodeItems.forEach((item) => {
        item.addEventListener('click', function (e) {
          e.preventDefault();
          episodeItems.forEach((i) => i.classList.remove('is-active'));
          this.classList.add('is-active');
          const url = this.dataset.watchUrl || '';
          if (url) {
            playEmbed(url, this.dataset.title || 'Episode', this.dataset.fallbackWatchUrl || '');
          } else {
            const playerFrame = document.getElementById('player-frame');
            const still = this.dataset.still;
            if (playerFrame && still) {
              playerFrame.innerHTML = '<img src="' + still + '" alt="" class="w-full h-full object-cover">';
            }
          }
          document.getElementById('player-caption-title').textContent = this.dataset.title || '';
          document.getElementById('player-caption-overview').textContent = this.dataset.overview || '';
        });
      });
    } else if (items[0].kind === 'part') {
      list.innerHTML = items.map((p) => (
        '<a href="' + esc(p.watch_url) + '" class="episode-item ' + (p.is_current ? 'is-active' : '') + '">' +
          '<div class="episode-thumb !w-11 !h-16 rounded-md"><img src="' + esc(p.poster_url) + '" alt="" loading="lazy"></div>' +
          '<div class="min-w-0"><p class="text-xs font-semibold text-white truncate">' + esc(p.title) + '</p><p class="text-[11px] text-gray-500">' + esc(p.year) + (p.is_current ? ' &middot; Now Viewing' : '') + '</p></div>' +
        '</a>'
      )).join('');
    }
  }

  // ---------------------------------------------------------
  // Player controls — playEmbed is defined once here and reused by
  // both the switcher's embed-item clicks and the "Watch Full Movie"
  // button, so there's a single source of truth for how a curated
  // source plays (native <video> for local uploads, <iframe> for
  // hand-curated third-party embeds you've added to the catalog).
  // ---------------------------------------------------------
  function playEmbed(url, label, fallbackUrl) {
    const playerFrame = document.getElementById('player-frame');
    if (!playerFrame || !url) return;

    const isDirectVideo = /^\/uploads\/videos\//i.test(url) || /\.(mp4|webm|m4v|mov)(?:[?#].*)?$/i.test(url);
    playerFrame.innerHTML =
      '<div class="absolute inset-0 flex items-center justify-center bg-black z-10" data-player-loading>' +
        '<div class="text-center"><div class="mx-auto h-9 w-9 rounded-full border-2 border-white/20 border-t-orange-500 animate-spin"></div>' +
        '<p class="text-xs text-gray-400 mt-3">Loading player…</p></div>' +
      '</div>' +
      (isDirectVideo
        ? '<video src="' + esc(url) + '" title="' + esc(label || 'Full movie') + '" controls autoplay playsinline class="w-full h-full object-contain bg-black"></video>'
        : '<iframe src="' + esc(url) + '" title="' + esc(label || 'Full movie') + '" allow="autoplay; encrypted-media; picture-in-picture; fullscreen; web-share" allowfullscreen loading="eager"></iframe>');

    const iframe = playerFrame.querySelector('iframe');
    const video = playerFrame.querySelector('video');
    const loading = playerFrame.querySelector('[data-player-loading]');
    if (video) {
      video.addEventListener('loadedmetadata', function () { if (loading) loading.remove(); }, { once: true });
      video.addEventListener('error', function () { if (loading) loading.remove(); const notice = document.getElementById('download-notice'); if (notice) notice.classList.remove('hidden'); }, { once: true });
    }
    let timeout = window.setTimeout(function () {
      if (loading) {
        const backup = fallbackUrl ? '<button type="button" data-try-backup class="mt-4 rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-400">Try backup player</button>' : '';
        loading.innerHTML = '<div class="text-center px-6"><p class="text-sm text-gray-300">The player is taking longer than expected.</p><p class="text-xs text-gray-500 mt-2">Trying the backup player can fix a temporary source/domain problem.</p>' + backup + '</div>';
        const backupBtn = loading.querySelector('[data-try-backup]');
        if (backupBtn) backupBtn.onclick = function () { playEmbed(fallbackUrl, label); };
      }
    }, 9000);

    if (iframe) {
      iframe.addEventListener('load', function () {
        window.clearTimeout(timeout);
        if (loading) loading.remove();
      }, { once: true });
    }

    const captionTitle = document.getElementById('player-caption-title');
    const captionOverview = document.getElementById('player-caption-overview');
    if (captionTitle) captionTitle.textContent = label || 'Now Playing';
    if (captionOverview) captionOverview.textContent = 'Now playing';
  }

  function initPlayerControls(data) {
    function showNotice(id) {
      document.querySelectorAll('.watch-notice').forEach((el) => el.classList.add('hidden'));
      const el = document.getElementById(id);
      if (el) el.classList.remove('hidden');
    }

    window.playTrailer = function () {
      const playerFrame = document.getElementById('player-frame');
      if (!playerFrame) return;
      if (!window.CINENOVA_TRAILER_KEY) { showNotice('trailer-notice'); return; }
      playerFrame.innerHTML =
        '<iframe src="https://www.youtube.com/embed/' + encodeURIComponent(window.CINENOVA_TRAILER_KEY) + '?autoplay=1&rel=0" ' +
        'title="Trailer" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
      const captionTitle = document.getElementById('player-caption-title');
      const captionOverview = document.getElementById('player-caption-overview');
      if (captionTitle) captionTitle.textContent = 'Trailer';
      if (captionOverview) captionOverview.textContent = '';
    };
    document.querySelectorAll('[data-play-trailer]').forEach((btn) => btn.addEventListener('click', window.playTrailer));

    const fullMovieBtn = document.querySelector('[data-watch-full]');
    if (fullMovieBtn) {
      fullMovieBtn.onclick = () => {
        const first = (data.episode_items || [])[0];
        const url = (first && first.kind === 'embed' && first.url) || data.vidsrc_url || '';
        if (url) {
          document.querySelectorAll('[data-embed-item]').forEach((i, idx) => i.classList.toggle('is-active', idx === 0));
          playEmbed(url, mediaType === 'movie' ? 'Full Movie' : 'Episode ' + episode, (data.vidsrc_urls || [])[1] || '');
        } else {
          showNotice('full-movie-notice');
        }
      };
    }
    const downloadBtn = document.querySelector('[data-download]');
    if (downloadBtn) {
      downloadBtn.onclick = function () {
        const active = document.querySelector('[data-embed-item].is-active');
        const url = (active && active.dataset.downloadUrl) || data.download_url || '';
        if (!url) return;
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        a.target = '_blank';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
      };
    }
  }

  function setupDownload(data) {
    const btn = document.querySelector('[data-download]');
    if (!btn) return;

    // Only show Download when the currently selected source is a real
    // downloadable video URL. Embed/player URLs (for example /e/...) are
    // intentionally not treated as downloads.
    const active = document.querySelector('[data-embed-item].is-active');
    const downloadable = (active && active.dataset.downloadUrl) || '';
    const fallbackDownload = data.download_url || '';
    const url = downloadable || fallbackDownload;
    const isDownloadable = !!url;

    btn.classList.toggle('hidden', !isDownloadable);
    btn.setAttribute('aria-hidden', isDownloadable ? 'false' : 'true');
    btn.disabled = !isDownloadable;
    btn.title = isDownloadable ? 'Download this video' : '';
  }

  // ---------------------------------------------------------
  // Comments — localStorage demo (see comment in the original
  // watch.php for what a real backend would need to replace this).
  // ---------------------------------------------------------
  function initComments() {
    const commentForm = document.getElementById('comment-form');
    const commentList = document.getElementById('comment-list');
    const commentEmpty = document.getElementById('comment-empty');
    const commentCount = document.getElementById('comment-count');

    function storageKey() { return window.CINENOVA_COMMENTS_KEY || 'cinenova_comments_demo'; }
    function loadComments() {
      try { const raw = localStorage.getItem(storageKey()); return raw ? JSON.parse(raw) : []; } catch (e) { return []; }
    }
    function saveComments(comments) {
      try { localStorage.setItem(storageKey(), JSON.stringify(comments)); } catch (e) { /* ignore */ }
    }
    function initials(name) {
      return name.trim().split(/\s+/).slice(0, 2).map((w) => w[0].toUpperCase()).join('');
    }
    function renderComments() {
      if (!commentList) return;
      const comments = loadComments();
      if (commentCount) commentCount.textContent = '(' + comments.length + ')';
      if (comments.length === 0) {
        commentList.innerHTML = '';
        if (commentEmpty) commentEmpty.classList.remove('hidden');
        return;
      }
      if (commentEmpty) commentEmpty.classList.add('hidden');
      commentList.innerHTML = comments.map((c) => (
        '<div class="comment-item flex gap-3 py-4 border-b border-white/5 last:border-b-0">' +
          '<div class="comment-avatar h-9 w-9 rounded-full flex items-center justify-center text-xs font-bold text-black flex-shrink-0">' + esc(initials(c.name)) + '</div>' +
          '<div class="min-w-0"><div class="flex items-center gap-2 flex-wrap"><span class="text-sm font-semibold text-white">' + esc(c.name) + '</span><span class="text-[11px] text-gray-500">' + esc(c.date) + '</span></div>' +
          '<p class="text-sm text-gray-300 mt-1 leading-relaxed break-words">' + esc(c.text) + '</p></div>' +
        '</div>'
      )).join('');
    }
    if (commentForm) {
      commentForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const nameInput = document.getElementById('comment-name');
        const textInput = document.getElementById('comment-text');
        const name = (nameInput.value || 'Guest').trim() || 'Guest';
        const text = textInput.value.trim();
        if (!text) return;
        const comments = loadComments();
        comments.unshift({ name: name, text: text, date: new Date().toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) });
        saveComments(comments);
        textInput.value = '';
        renderComments();
      });
    }
    renderComments();
  }

  // ---------------------------------------------------------
  // Top search
  // ---------------------------------------------------------
  function initSearch() {
    const input = document.getElementById('watch-search-input');
    const dropdown = document.getElementById('watch-search-dropdown');
    const loader = document.getElementById('watch-search-loader');
    let timer = null;
    if (!input || !dropdown) return;

    function closeSearch() { dropdown.classList.add('hidden'); }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();
      if (q.length < 2) { closeSearch(); return; }
      dropdown.classList.remove('hidden');
      if (loader) loader.classList.remove('hidden');
      timer = setTimeout(function () {
        fetch(CN_BASE + 'backend/api/catalog.php?type=search&q=' + encodeURIComponent(q))
          .then((r) => r.json())
          .then((res) => {
            if (loader) loader.classList.add('hidden');
            const items = (res.data && res.data.items) || [];
            dropdown.innerHTML = items.length
              ? items.map((item) => CNCards.searchListItemHtml(item)).join('')
              : '<div class="flex flex-col items-center justify-center text-center py-8 px-3"><p class="text-gray-400 text-xs">No movies found. Try a different title.</p></div>';
          })
          .catch(() => { if (loader) loader.classList.add('hidden'); });
      }, 350);
    });
    input.addEventListener('focus', function () { if (this.value.trim().length >= 2) dropdown.classList.remove('hidden'); });
    document.addEventListener('click', function (e) {
      const wrap = document.getElementById('watch-search-wrap');
      if (wrap && !wrap.contains(e.target)) closeSearch();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSearch(); });
  }
})();
