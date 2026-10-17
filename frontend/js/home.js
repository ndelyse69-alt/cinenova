/**
 * CineNova — home page controller (index.html).
 * Keeps the existing catalog/hero/trending behavior and adds a
 * lightweight poster marquee behind the hero content.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    loadHero();
    loadTrendingRow('movie', 'track-trending-movies');
    loadTrendingRow('tv', 'track-trending-series');
  });

  function loadHero() {
    fetch(CN_BASE + 'backend/api/catalog.php?type=hero')
      .then((r) => r.json())
      .then((res) => {
        const slides = (res.data && res.data.slides) || [];
        const backdrops = slides.map((s) => s.backdrop_url).filter(Boolean);
        if (backdrops.length === 0) {
          backdrops.push('https://via.placeholder.com/1920x1080/0a0a0f/ff6a1a?text=CineNova');
        }
        while (backdrops.length < 2) backdrops.push(backdrops[0]);

        const bgElement = document.getElementById('hero-bg');
        if (bgElement) bgElement.style.backgroundImage = 'url("' + backdrops[0] + '")';

        if (backdrops.length > 1) {
          let currentIndex = 0;
          setInterval(function () {
            currentIndex = (currentIndex + 1) % backdrops.length;
            if (bgElement) bgElement.style.backgroundImage = 'url("' + backdrops[currentIndex] + '")';
          }, 4000);
        }
      })
      .catch(function () { /* Keep the built-in hero fallback visible. */ });
  }

  function loadTrendingRow(mediaType, trackId) {
    const track = document.getElementById(trackId);
    if (!track) return;

    fetch(CN_BASE + 'backend/api/catalog.php?type=trending&media=' + mediaType + '&window=week&limit=12')
      .then((r) => r.json())
      .then((res) => {
        const items = (res.data && res.data.items) || [];
        track.innerHTML = items.map((item, i) => CNCards.trendingCardHtml(item, i + 1)).join('');
        startAutoScroll(track);
        if (mediaType === 'movie') buildHeroPosterMarquee(items);
      })
      .catch(function () { /* Existing empty-state behavior remains intact. */ });
  }

  function buildHeroPosterMarquee(items) {
    const posters = items.map((item) => item.poster_url).filter(Boolean);
    if (!posters.length) return;

    const rows = [document.getElementById('hero-posters-one'), document.getElementById('hero-posters-two')];
    rows.forEach(function (row, rowIndex) {
      if (!row) return;
      const source = rowIndex ? posters.slice().reverse() : posters;
      const doubled = source.concat(source);
      row.innerHTML = doubled.map(function (url, i) {
        return '<div class="hero-poster"><img src="' + url.replace(/"/g, '&quot;') + '" alt="" loading="lazy" width="342" height="513"></div>';
      }).join('');
    });
  }

  const scrollIntervals = {};

  function startAutoScroll(track) {
    if (scrollIntervals[track.id]) clearInterval(scrollIntervals[track.id]);
    scrollIntervals[track.id] = setInterval(function () {
      track.scrollLeft += Number(track.dataset.speed || 1);
      if (track.scrollLeft >= track.scrollWidth - track.clientWidth) track.scrollLeft = 0;
    }, 20);

    const btn = document.querySelector('[data-toggle-scroll][data-track="' + track.id + '"]');
    if (btn && !btn.dataset.bound) {
      btn.dataset.bound = '1';
      let isPaused = false;
      btn.addEventListener('click', function () {
        isPaused = !isPaused;
        btn.textContent = isPaused ? 'Resume' : 'Pause';
        if (isPaused) {
          clearInterval(scrollIntervals[track.id]);
        } else {
          scrollIntervals[track.id] = setInterval(function () {
            track.scrollLeft += Number(track.dataset.speed || 1);
            if (track.scrollLeft >= track.scrollWidth - track.clientWidth) track.scrollLeft = 0;
          }, 20);
        }
      });
    }
  }
})();
