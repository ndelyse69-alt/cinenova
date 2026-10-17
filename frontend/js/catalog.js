/**
 * CineNova — catalog page controller.
 * ---------------------------------------------------------------
 * Drives movies.html, series.html and new-releases.html. Ported
 * from the old catalog.js + config.php's handleAjaxRequest() /
 * handleNewReleasesAjaxRequest(): same hero-thumbnail hover
 * behavior, same two curated carousel rows, same filterable
 * infinite-scroll grid — now fed by JSON from
 * /backend/api/catalog.php instead of pre-rendered HTML fragments.
 *
 * Page contract (set before this script runs):
 *   window.CINENOVA_MEDIA_TYPE = 'movie' | 'tv'
 *   window.CINENOVA_CATALOG_MODE = 'trending' | 'now_playing'  (grid/hero source)
 *   window.CINENOVA_ROW1 = { title, params: {with_genres:28} | {} }
 *   window.CINENOVA_ROW2 = { title, params: {...} }
 * Required DOM ids: hero-bg, hero-title, hero-year, hero-rating,
 * hero-desc, hero-watch-btn, hero-thumbs, row1-track, row2-track,
 * grid-skeleton, movieGrid, loaderContainer, loadMoreBtn.
 */
(function (global) {
  'use strict';

  function esc(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function init() {
    const mediaType = global.CINENOVA_MEDIA_TYPE === 'tv' ? 'tv' : 'movie';
    const mode = global.CINENOVA_CATALOG_MODE || 'trending';
    const row1 = global.CINENOVA_ROW1;
    const row2 = global.CINENOVA_ROW2;

    loadHero(mediaType, mode);
    if (row1) loadRow(mediaType, row1, 'row1-track');
    if (row2) loadRow(mediaType, row2, 'row2-track');
    initGrid(mediaType, mode);
    CNSidebar.render({ activePage: global.CINENOVA_ACTIVE_PAGE, mediaType: mediaType });
    CNFilterBar.render(mediaType);
    CNFooter.render();
    if (global.CINENOVA_STAY_ON_PAGE_PLAY) bindInlinePlayback();
  }

  function heroUrl(mode, mediaType, limit) {
    if (mode === 'now_playing') {
      return CN_BASE + 'backend/api/catalog.php?type=now_playing&limit=' + limit;
    }
    return CN_BASE + 'backend/api/catalog.php?type=trending&media=' + mediaType + '&window=week&limit=' + limit;
  }

  function loadHero(mediaType, mode) {
    fetch(heroUrl(mode, mediaType, 20))
      .then((r) => r.json())
      .then((res) => {
        const items = (res.data && res.data.items) || [];
        if (items.length === 0) return;
        applyHero(items[0]);
        renderThumbs(items.slice(0, 6));
        buildHeroPosterMarquee(items);
      });
  }

  // Decorative row of drifting movie posters behind the hero content —
  // same treatment as the Home page hero. Purely visual (aria-hidden),
  // built from posters already fetched for the hero, so no extra
  // network calls. Silently no-ops if the marquee markup isn't present.
  function buildHeroPosterMarquee(items) {
    const posters = items.map((item) => item.poster_url).filter(Boolean);
    if (!posters.length) return;

    const rows = [document.getElementById('hero-posters-one'), document.getElementById('hero-posters-two')];
    rows.forEach(function (row, rowIndex) {
      if (!row) return;
      const source = rowIndex ? posters.slice().reverse() : posters;
      const doubled = source.concat(source);
      row.innerHTML = doubled.map(function (url) {
        return '<div class="hero-poster"><img src="' + esc(url) + '" alt="" loading="lazy" width="342" height="513"></div>';
      }).join('');
    });
  }

  function applyHero(item) {
    const bg = document.getElementById('hero-bg');
    if (bg && item.backdrop_url) bg.style.backgroundImage = "url('" + item.backdrop_url + "')";
    setText('hero-title', item.title);
    setText('hero-year', item.year);
    document.getElementById('hero-rating').innerHTML = cnIcon('star', 'w-3 h-3 text-yellow-400') + ' ' + Number(item.vote_average || 0).toFixed(1);
    setText('hero-desc', item.overview || '');
    const btn = document.getElementById('hero-watch-btn');
    if (btn) {
      if (global.CINENOVA_STAY_ON_PAGE_PLAY) {
        btn.href = '#';
        btn.dataset.cnInlineWatch = '1';
        btn.dataset.tmdbId = String(item.id || '');
        btn.dataset.mediaType = String(item.media_type || global.CINENOVA_MEDIA_TYPE || 'movie');
        btn.dataset.title = item.title || '';
      } else {
        btn.href = item.watch_url;
        delete btn.dataset.cnInlineWatch;
      }
    }
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function renderThumbs(items) {
    const wrap = document.getElementById('hero-thumbs');
    if (!wrap) return;
    wrap.innerHTML = items.map((item) => (
      '<div class="hero-thumb flex-shrink-0 w-[70px] sm:w-[90px] cursor-pointer rounded-lg overflow-hidden border-2 border-transparent bg-black/50 backdrop-blur-md hover:border-orange-500 transition-all duration-300 group relative" ' +
      'data-backdrop="' + esc(item.backdrop_url || '') + '" data-title="' + esc(item.title) + '" data-year="' + esc(item.year) + '" data-rating="' + Number(item.vote_average || 0).toFixed(1) + '" data-desc="' + esc(item.overview || '') + '" data-url="' + esc(item.watch_url) + '" data-id="' + esc(item.id || '') + '" data-media-type="' + esc(item.media_type || global.CINENOVA_MEDIA_TYPE || 'movie') + '">' +
        '<img src="' + esc(item.poster_url || '') + '" alt="' + esc(item.title) + '" class="w-full h-full aspect-poster object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">' +
      '</div>'
    )).join('');

    wrap.querySelectorAll('.hero-thumb').forEach((thumb) => {
      thumb.addEventListener('mouseenter', function () {
        const bg = document.getElementById('hero-bg');
        if (bg && thumb.dataset.backdrop) bg.style.backgroundImage = "url('" + thumb.dataset.backdrop + "')";
        setText('hero-title', thumb.dataset.title);
        setText('hero-year', thumb.dataset.year);
        document.getElementById('hero-rating').innerHTML = cnIcon('star', 'w-3 h-3 text-yellow-400') + ' ' + thumb.dataset.rating;
        setText('hero-desc', thumb.dataset.desc);
        const btn = document.getElementById('hero-watch-btn');
        if (btn) {
          if (global.CINENOVA_STAY_ON_PAGE_PLAY) {
            btn.href = '#';
            btn.dataset.cnInlineWatch = '1';
            btn.dataset.tmdbId = thumb.dataset.id || '';
            btn.dataset.mediaType = mediaTypeFromThumb(thumb);
            btn.dataset.title = thumb.dataset.title || '';
          } else {
            btn.href = thumb.dataset.url;
          }
        }
      });
      thumb.addEventListener('click', function () {
        if (global.CINENOVA_STAY_ON_PAGE_PLAY && thumb.dataset.id) {
          openInlinePlayer({ id: thumb.dataset.id, mediaType: mediaTypeFromThumb(thumb), title: thumb.dataset.title });
          return;
        }
        if (thumb.dataset.url) window.location.href = thumb.dataset.url;
      });
    });
  }

  function loadRow(mediaType, row, trackId) {
    const track = document.getElementById(trackId);
    if (!track) return;
    const params = new URLSearchParams({ type: 'discover', media: mediaType, limit: '12' });
    Object.keys(row.params || {}).forEach((k) => params.set(k, row.params[k]));

    // "Fresh This Week" on new-releases has no discover filters of its
    // own — it's literally the same now-playing list the hero uses,
    // same as the old new-releases.php's $freshThisWeek slice.
    const url = (global.CINENOVA_CATALOG_MODE === 'now_playing' && Object.keys(row.params || {}).length === 0)
      ? CN_BASE + 'backend/api/catalog.php?type=now_playing&limit=12'
      : CN_BASE + 'backend/api/catalog.php?' + params.toString();

    fetch(url)
      .then((r) => r.json())
      .then((res) => {
        const items = (res.data && res.data.items) || [];
        track.innerHTML = items.map((item) => CNCards.carouselItemHtml(item)).join('');
      });
  }

  function mediaTypeFromThumb(thumb) {
    return thumb.dataset.mediaType === 'tv' ? 'tv' : 'movie';
  }

  function bindInlinePlayback() {
    document.addEventListener('click', function (event) {
      const trigger = event.target.closest('[data-cn-inline-watch]');
      if (!trigger) return;
      event.preventDefault();
      openInlinePlayer({
        id: trigger.dataset.tmdbId || '',
        mediaType: trigger.dataset.mediaType || 'movie',
        title: trigger.dataset.title || 'Now Playing'
      });
    });
  }

  function openInlinePlayer({ id, mediaType, title, season = 1, episode = 1 }) {
    if (!id) return;
    const type = mediaType === 'tv' ? 'tv' : 'movie';
    const base = String(global.CINENOVA_VIDSRC_BASE || 'https://vidsrc.ir/embed').replace(/\/$/, '');
    const src = type === 'tv'
      ? base + '/tv/' + encodeURIComponent(id) + '/' + encodeURIComponent(season) + '/' + encodeURIComponent(episode)
      : base + '/movie/' + encodeURIComponent(id);

    let modal = document.getElementById('cn-inline-player-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'cn-inline-player-modal';
      modal.className = 'fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-6';
      modal.innerHTML =
        '<div class="relative w-full max-w-6xl">' +
          '<div class="flex items-center justify-between gap-4 mb-3 px-1">' +
            '<h2 id="cn-inline-player-title" class="text-white font-bold text-sm sm:text-base truncate"></h2>' +
            '<button type="button" id="cn-inline-player-close" class="flex-shrink-0 w-10 h-10 rounded-full bg-white/10 border border-white/10 text-white hover:bg-orange-500 transition" aria-label="Close player">&times;</button>' +
          '</div>' +
          '<div class="relative aspect-video rounded-xl overflow-hidden bg-black border border-white/10 shadow-2xl">' +
            '<div id="cn-inline-player-loading" class="absolute inset-0 z-10 flex items-center justify-center bg-black">' +
              '<div class="text-center"><div class="mx-auto h-9 w-9 rounded-full border-2 border-white/20 border-t-orange-500 animate-spin"></div><p class="text-xs text-gray-400 mt-3">Loading player…</p></div>' +
            '</div>' +
            '<iframe id="cn-inline-player-frame" class="w-full h-full border-0" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>' +
          '</div>' +
          '<p class="text-[11px] text-gray-500 mt-2">You are still on this page. Close the player to continue browsing.</p>' +
        '</div>';
      document.body.appendChild(modal);
      modal.querySelector('#cn-inline-player-close').addEventListener('click', closeInlinePlayer);
      modal.addEventListener('click', function (e) { if (e.target === modal) closeInlinePlayer(); });
    }

    document.getElementById('cn-inline-player-title').textContent = title || 'Now Playing';
    const loading = document.getElementById('cn-inline-player-loading');
    const frame = document.getElementById('cn-inline-player-frame');
    loading.classList.remove('hidden');
    frame.src = src;
    frame.onload = function () { loading.classList.add('hidden'); };
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  }

  function closeInlinePlayer() {
    const modal = document.getElementById('cn-inline-player-modal');
    const frame = document.getElementById('cn-inline-player-frame');
    if (frame) frame.src = 'about:blank';
    if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    document.body.classList.remove('overflow-hidden');
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeInlinePlayer();
  });

  function initGrid(mediaType, mode) {
    const skeleton = document.getElementById('grid-skeleton');
    const grid = document.getElementById('movieGrid');
    const loaderContainer = document.getElementById('loaderContainer');
    const loadMoreBtn = document.getElementById('loadMoreBtn');

    let page = 1;
    let totalPages = 1;
    let genre = '';
    let country = '';
    let loading = false;

    function buildUrl() {
      if (genre || country) {
        const params = new URLSearchParams({ type: 'discover', media: mediaType, page: String(page), limit: '24' });
        if (genre) params.set('genre', genre);
        if (country) params.set('country', country);
        return CN_BASE + 'backend/api/catalog.php?' + params.toString();
      }
      if (mode === 'now_playing') {
        return CN_BASE + 'backend/api/catalog.php?type=now_playing&page=' + page + '&limit=24';
      }
      return CN_BASE + 'backend/api/catalog.php?type=trending&media=' + mediaType + '&window=week&page=' + page + '&limit=24';
    }

    function load(reset) {
      if (loading) return;
      loading = true;
      fetch(buildUrl())
        .then((r) => r.json())
        .then((res) => {
          const items = (res.data && res.data.items) || [];
          totalPages = (res.data && res.data.total_pages) || 1;

          if (reset) grid.innerHTML = '';
          items.forEach((item) => grid.insertAdjacentHTML('beforeend', CNCards.gridCardHtml(item)));

          skeleton.classList.add('hidden');
          grid.classList.remove('hidden');
          loaderContainer.classList.toggle('hidden', page >= totalPages);
          loading = false;
        })
        .catch(() => { loading = false; });
    }

    loadMoreBtn.addEventListener('click', function () {
      page += 1;
      load(false);
    });

    document.addEventListener('cn:genre-change', function (e) {
      genre = e.detail || '';
      page = 1;
      load(true);
    });
    document.addEventListener('cn:country-change', function (e) {
      country = e.detail || '';
      page = 1;
      load(true);
    });

    load(true);
  }

  global.CNCatalog = { init: init };
  document.addEventListener('DOMContentLoaded', init);
})(window);
