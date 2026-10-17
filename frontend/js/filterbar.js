/**
 * CineNova — centered Categories/Countries filter bar.
 * ---------------------------------------------------------------
 * Ported 1:1 from includes/filterbar.php (renderFilterBar()).
 * Rendered on every browse page (movies/series/new-releases/
 * agasobanuye). Genres come from /backend/api/catalog.php?type=genres
 * (TMDB, per media type); countries from ?type=countries.
 * Dispatches 'cn:genre-change' / 'cn:country-change' CustomEvents
 * that catalog.js listens for.
 */
(function (global) {
  'use strict';

  function esc(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function render(mediaType) {
    const root = document.getElementById('filterbar-root');
    if (!root) return;

    root.innerHTML =
      '<div class="w-full flex justify-center px-4 py-6 relative z-30" id="filter-bar-wrap">' +
        '<div class="relative">' +
          '<div class="glass-panel rounded-full flex items-center gap-1 p-1.5 shadow-xl">' +
            '<button id="cat-toggle" type="button" class="filter-pill">' + cnIcon('grid', 'w-4 h-4') + '<span>Categories</span>' + cnIcon('chevron-down', 'w-3.5 h-3.5 opacity-50') + '</button>' +
            '<div class="w-px h-5 bg-white/10" aria-hidden="true"></div>' +
            '<button id="country-toggle" type="button" class="filter-pill">' + cnIcon('globe', 'w-4 h-4') + '<span>Countries</span>' + cnIcon('chevron-down', 'w-3.5 h-3.5 opacity-50') + '</button>' +
          '</div>' +
          '<div id="cat-panel" class="filter-dropdown glass-panel rounded-2xl p-4 shadow-2xl w-[min(88vw,260px)] max-h-[70vh]">' +
            '<label for="cat-search" class="sr-only">Search categories</label>' +
            '<input type="text" id="cat-search" placeholder="Search categories…" class="w-full px-3 py-2 mb-3 bg-black/50 rounded-lg text-xs text-white placeholder-gray-500 border border-white/10 focus:border-orange-500 outline-none transition-colors">' +
            '<div class="flex flex-col gap-1 max-h-[55vh] overflow-y-auto pr-1 hide-scrollbar" id="cat-list">' +
              '<button class="cat-item is-active text-left w-full px-3 py-2 rounded-lg text-xs font-medium text-gray-300 hover:bg-white/5 hover:text-white transition-colors" data-genre="0">All Genres</button>' +
            '</div>' +
          '</div>' +
          '<div id="country-panel" class="filter-dropdown glass-panel rounded-2xl p-4 shadow-2xl w-[min(88vw,260px)] max-h-[70vh]">' +
            '<label for="country-search" class="sr-only">Search countries</label>' +
            '<input type="text" id="country-search" placeholder="Search countries…" class="w-full px-3 py-2 mb-3 bg-black/50 rounded-lg text-xs text-white placeholder-gray-500 border border-white/10 focus:border-orange-500 outline-none transition-colors">' +
            '<div class="flex flex-col gap-1 max-h-[55vh] overflow-y-auto pr-1 hide-scrollbar" id="country-list">' +
              '<button class="country-item is-active text-left w-full px-3 py-2 rounded-lg text-xs font-medium text-gray-300 hover:bg-white/5 hover:text-white transition-colors" data-country="">All Countries</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';

    loadOptions(mediaType);
    bindInteractions();
  }

  function loadOptions(mediaType) {
    fetch(CN_BASE + 'backend/api/catalog.php?type=genres&media=' + mediaType)
      .then((r) => r.json())
      .then((res) => {
        const list = document.getElementById('cat-list');
        ((res.data && res.data.genres) || []).forEach((g) => {
          const btn = document.createElement('button');
          btn.className = 'cat-item text-left w-full px-3 py-2 rounded-lg text-xs font-medium text-gray-300 hover:bg-white/5 hover:text-white transition-colors';
          btn.dataset.genre = g.id;
          btn.textContent = g.name;
          list.appendChild(btn);
        });
        bindItemClicks('.cat-item', 'genre', 'cn:genre-change');
      });

    fetch(CN_BASE + 'backend/api/catalog.php?type=countries')
      .then((r) => r.json())
      .then((res) => {
        const list = document.getElementById('country-list');
        ((res.data && res.data.countries) || []).forEach((c) => {
          const btn = document.createElement('button');
          btn.className = 'country-item text-left w-full px-3 py-2 rounded-lg text-xs font-medium text-gray-300 hover:bg-white/5 hover:text-white transition-colors';
          btn.dataset.country = c.code;
          btn.textContent = c.name;
          list.appendChild(btn);
        });
        bindItemClicks('.country-item', 'country', 'cn:country-change');
      });
  }

  function bindItemClicks(selector, dataKey, eventName) {
    document.querySelectorAll(selector).forEach((btn) => {
      btn.addEventListener('click', function () {
        document.querySelectorAll(selector).forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        const value = btn.dataset[dataKey] || '';
        const toggle = dataKey === 'genre' ? document.getElementById('cat-toggle') : document.getElementById('country-toggle');
        const label = toggle ? toggle.querySelector('span') : null;
        if (label) label.textContent = value ? btn.textContent.trim() : (dataKey === 'genre' ? 'Categories' : 'Countries');
        document.dispatchEvent(new CustomEvent(eventName, { detail: value }));
        closePanels();
        const grid = document.getElementById('grid-section');
        if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  function closePanels() {
    document.getElementById('cat-panel').classList.remove('is-open');
    document.getElementById('country-panel').classList.remove('is-open');
  }

  function bindInteractions() {
    const catToggle = document.getElementById('cat-toggle');
    const countryToggle = document.getElementById('country-toggle');
    const catPanel = document.getElementById('cat-panel');
    const countryPanel = document.getElementById('country-panel');

    catToggle.addEventListener('click', (e) => { e.stopPropagation(); countryPanel.classList.remove('is-open'); catPanel.classList.toggle('is-open'); });
    countryToggle.addEventListener('click', (e) => { e.stopPropagation(); catPanel.classList.remove('is-open'); countryPanel.classList.toggle('is-open'); });
    document.addEventListener('click', closePanels);
    catPanel.addEventListener('click', (e) => e.stopPropagation());
    countryPanel.addEventListener('click', (e) => e.stopPropagation());

    document.getElementById('cat-search').addEventListener('input', function () {
      const q = this.value.toLowerCase();
      document.querySelectorAll('.cat-item').forEach((btn) => {
        btn.style.display = btn.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
    document.getElementById('country-search').addEventListener('input', function () {
      const q = this.value.toLowerCase();
      document.querySelectorAll('.country-item').forEach((btn) => {
        btn.style.display = btn.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  global.CNFilterBar = { render: render };
})(window);
