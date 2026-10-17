/**
 * CineNova — card renderers.
 * ---------------------------------------------------------------
 * Ported 1:1 from the old config.php render*() functions. The
 * backend now hands back plain JSON (poster_url, watch_url, title,
 * year, vote_average, …) and these build the exact same markup the
 * PHP versions used to print server-side.
 */
(function (global) {
  'use strict';

  function esc(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function fmtRating(n) {
    return (Number(n) || 0).toFixed(1);
  }

  /** Standard poster grid card (Movies/Series/New Releases pages, search results). */
  function gridCardHtml(item) {
    const title = esc(item.title);
    const poster = item.poster_url || '';
    const rating = fmtRating(item.vote_average);
    const year = esc(item.year || '');
    const url = esc(item.watch_url || '#');
    const inlineWatch = !!global.CINENOVA_STAY_ON_PAGE_PLAY;
    const cardHref = inlineWatch ? '#' : url;
    const inlineAttrs = inlineWatch ? ' data-cn-inline-watch data-tmdb-id="' + esc(item.id || '') + '" data-media-type="' + esc(item.media_type || 'movie') + '" data-title="' + title + '"' : '';
    const genreName = (item.genre_name || '').trim();

    return (
      '<a href="' + cardHref + '"' + inlineAttrs + ' class="movie-card group relative block bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden hover:scale-[1.04] transition-all duration-300 hover:shadow-2xl hover:shadow-orange-500/20 border border-white/5 hover:border-orange-500/30 reveal-card">' +
        '<div class="aspect-poster relative overflow-hidden">' +
          '<img src="' + poster + '" alt="' + title + '" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy" width="342" height="513">' +
          '<div class="absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-3">' +
            '<div class="flex items-center gap-1.5 text-yellow-400 text-xs font-bold mb-1">' + cnIcon('star', 'w-3.5 h-3.5') + ' ' + rating +
              (genreName ? '<span class="ml-auto text-orange-300 text-[10px] font-semibold normal-case">' + esc(genreName) + '</span>' : '') + '</div>' +
            '<div class="text-white text-sm font-bold truncate leading-tight mb-1">' + title + '</div>' +
            '<div class="text-gray-300 text-[10px]">' + year + '</div>' +
            '<span class="mt-2 w-full py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white font-bold text-[11px] rounded-lg text-center">Watch Now</span>' +
          '</div>' +
        '</div>' +
      '</a>'
    );
  }

  /** Fixed-width wrapper used to drop a grid card into a horizontal carousel. */
  function carouselItemHtml(item) {
    return '<div class="flex-shrink-0 w-[140px] sm:w-[160px] md:w-[180px] lg:w-[200px]">' + gridCardHtml(item) + '</div>';
  }

  /** Rank/rating card used by the homepage Trending Now / Trending Series rows. */
  function trendingCardHtml(item, rank) {
    const title = esc(item.title);
    const poster = item.poster_url || '';
    const rating = fmtRating(item.vote_average);
    const year = esc(item.year || '');
    const url = esc(item.watch_url || '#');
    const lang = (item.original_language || '').toUpperCase();

    return (
      '<div class="flex-shrink-0 w-[150px] sm:w-[170px] md:w-[190px] relative">' +
        (rank > 0 ? '<span class="absolute -left-1 -top-1 z-10 w-8 h-8 rounded-full bg-black/70 border border-orange-500/40 flex items-center justify-center text-orange-400 font-extrabold text-sm">' + rank + '</span>' : '') +
        '<a href="' + url + '" class="movie-card group relative block bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden hover:scale-[1.04] transition-all duration-300 hover:shadow-2xl hover:shadow-orange-500/20 border border-white/5 hover:border-orange-500/30">' +
          '<div class="aspect-poster relative overflow-hidden">' +
            '<img src="' + poster + '" alt="' + title + '" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy" width="342" height="513">' +
            '<div class="absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-3">' +
              '<div class="flex items-center gap-1.5 text-yellow-400 text-xs font-bold mb-1">' + cnIcon('star', 'w-3.5 h-3.5') + ' ' + rating + '</div>' +
              '<div class="text-white text-sm font-bold truncate leading-tight mb-1">' + title + '</div>' +
              '<div class="flex items-center gap-2 text-gray-300 text-[10px]"><span>' + year + '</span>' +
                (lang ? '<span class="px-1.5 py-0.5 rounded bg-white/10">' + esc(lang) + '</span>' : '') + '</div>' +
            '</div>' +
          '</div>' +
        '</a>' +
      '</div>'
    );
  }

  /** Curated Agasobanuye card. */
  function agasobanuyeCardHtml(movie) {
    const title = esc(movie.title);
    const poster = movie.poster_url || '';
    const rating = fmtRating(movie.vote_average);
    const year = esc(movie.year || '');
    const url = esc(movie.watch_url || ('watch.html?id=' + encodeURIComponent(movie.id || movie.tmdb_id || 0) + '&type=' + (movie.media_type === 'tv' ? 'tv' : 'movie')));

    return (
      '<a href="' + url + '" class="flex-shrink-0 w-[140px] sm:w-[160px] md:w-[180px] lg:w-[200px] block">' +
        '<div class="group relative bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden hover:scale-[1.04] transition-all duration-300 hover:shadow-2xl hover:shadow-orange-500/20 border border-white/5 hover:border-orange-500/30">' +
          '<div class="aspect-[2/3] relative overflow-hidden">' +
            '<img src="' + poster + '" alt="' + title + '" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy" width="342" height="513">' +
            '<div class="absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4">' +
              '<div class="flex items-center gap-2 text-yellow-400 text-xs font-bold mb-1">' + cnIcon('star', 'w-3.5 h-3.5') + ' ' + rating + '</div>' +
              '<div class="text-white text-sm font-bold truncate leading-tight mb-1">' + title + '</div>' +
              '<div class="text-gray-300 text-[10px]">' + year + '</div>' +
              '<span class="mt-3 w-full py-2.5 bg-gradient-to-r from-orange-500 to-orange-600 text-white font-bold text-xs rounded-lg text-center block">Watch</span>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</a>'
    );
  }

  /** Compact list-row search result (used in dropdown/inline search boxes). */
  function searchListItemHtml(item) {
    const title = esc(item.title);
    const poster = item.poster_url || '';
    const year = esc(item.year || '');
    const url = esc(item.watch_url || '#');
    const typeLabel = item.media_type === 'tv' ? 'Series' : 'Movie';

    return (
      '<a href="' + url + '" class="flex items-center gap-3 p-2 rounded-xl hover:bg-white/5 transition-colors reveal-card">' +
        '<img src="' + poster + '" alt="" class="w-10 h-14 object-cover rounded-md flex-shrink-0 bg-white/5" loading="lazy">' +
        '<div class="min-w-0 flex-1">' +
          '<p class="text-sm font-semibold text-white truncate">' + title + '</p>' +
          '<p class="text-[11px] text-gray-500">' + year + ' &middot; ' + typeLabel + '</p>' +
        '</div>' +
      '</a>'
    );
  }

  /**
   * Recommended-section info card — one card per title, poster on the
   * left and a metadata block on the right (title, year, rating,
   * runtime, genre pills, country). Runtime/country only render when
   * the item actually carries them — TMDB's lightweight recommendations
   * feed doesn't include them without a full detail call per title, so
   * they degrade gracefully to nothing rather than showing blank pills.
   */
  function recommendedCardHtml(item) {
    const title = esc(item.title);
    const poster = item.poster_url || '';
    const rating = fmtRating(item.vote_average);
    const year = esc(item.year || '');
    const url = esc(item.watch_url || '#');
    const typeLabel = item.media_type === 'tv' ? 'Series' : 'Movie';
    const genres = (item.genres || []).slice(0, 3);

    return (
      '<a href="' + url + '" class="rec-card glass-panel reveal-card">' +
        '<div class="rec-card-poster"><img src="' + poster + '" alt="' + title + '" loading="lazy" width="184" height="276"></div>' +
        '<div class="rec-card-body">' +
          '<p class="rec-card-title">' + title + '</p>' +
          '<div class="rec-card-meta">' +
            '<span class="meta-pill font-mono">' + year + '</span>' +
            '<span class="meta-pill meta-pill--rating">' + cnIcon('star', 'w-3 h-3') + ' ' + rating + '</span>' +
            (item.runtime ? '<span class="meta-pill">' + esc(item.runtime) + '</span>' : '<span class="meta-pill">' + typeLabel + '</span>') +
            genres.map((g) => '<span class="meta-pill meta-pill--genre">' + esc(g) + '</span>').join('') +
            (item.country ? '<span class="meta-pill meta-pill--country">' + cnIcon('globe', 'w-3 h-3') + ' ' + esc(item.country) + '</span>' : '') +
          '</div>' +
        '</div>' +
      '</a>'
    );
  }

  /** Compact rank + thumbnail row for the Popular Movies sidebar on the watch page. */
  function popularItemHtml(item, rank) {
    const title = esc(item.title);
    const poster = item.poster_url || '';
    const rating = fmtRating(item.vote_average);
    const year = esc(item.year || '');
    const url = esc(item.watch_url || '#');

    return (
      '<a href="' + url + '" class="popular-item reveal-card">' +
        '<span class="popular-item-rank">' + rank + '</span>' +
        '<div class="popular-item-thumb"><img src="' + poster + '" alt="' + title + '" loading="lazy" width="80" height="120"></div>' +
        '<div class="min-w-0 flex-1">' +
          '<p class="text-xs font-semibold text-white truncate">' + title + '</p>' +
          '<div class="flex items-center gap-2 mt-1 text-[10px] text-gray-500">' +
            '<span class="flex items-center gap-1 text-yellow-400 font-semibold">' + cnIcon('star', 'w-2.5 h-2.5') + ' ' + rating + '</span>' +
            '<span>' + year + '</span>' +
          '</div>' +
        '</div>' +
      '</a>'
    );
  }

  global.CNCards = {
    gridCardHtml: gridCardHtml,
    carouselItemHtml: carouselItemHtml,
    trendingCardHtml: trendingCardHtml,
    agasobanuyeCardHtml: agasobanuyeCardHtml,
    searchListItemHtml: searchListItemHtml,
    recommendedCardHtml: recommendedCardHtml,
    popularItemHtml: popularItemHtml,
  };
})(window);
