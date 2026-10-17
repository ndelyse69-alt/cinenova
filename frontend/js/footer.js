/**
 * CineNova — footer component.
 * Includes public movie updates, a newsletter subscription form, and an
 * admin-only update publisher. Newsletter subscribers are stored server-side
 * and receive email announcements whenever an admin publishes an update.
 */
(function (global) {
  'use strict';

  const SOCIALS = ['twitter', 'github', 'youtube', 'linkedin'];

  function esc(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function formatDate(value) {
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function updatesMarkup(updates) {
    if (!updates.length) {
      return '<p class="text-gray-500 text-xs leading-relaxed">No movie updates yet. Check back soon.</p>';
    }
    return updates.slice(0, 3).map(function (u) {
      const title = u.movie_title ? esc(u.movie_title) : '';
      const message = esc(u.message || 'New update from CineNova.');
      return '<div class="cn-footer-update-item">' +
        '<span class="cn-footer-update-icon">' + cnIcon('alert', 'w-3.5 h-3.5') + '</span>' +
        '<div class="min-w-0"><p class="cn-footer-update-message">' + message + '</p>' +
        (title ? '<p class="cn-footer-update-title">' + title + '</p>' : '') +
        '<p class="cn-footer-update-date">' + esc(formatDate(u.created_at)) + '</p></div>' +
      '</div>';
    }).join('');
  }

  function loadNewsletter(root) {
    const form = root.querySelector('#cn-newsletter-form');
    const input = root.querySelector('#cn-newsletter-email');
    const status = root.querySelector('[data-newsletter-status]');
    const button = root.querySelector('#cn-newsletter-button');
    if (!form || !input || !status || !button || !global.CNAuth) return;

    // If the visitor is already logged in, make subscribing faster by using
    // the email attached to their account while still allowing edits.
    if (typeof global.CNAuth.me === 'function') {
      global.CNAuth.me().then(function (state) {
        if (state && state.authenticated && state.user && state.user.email && !input.value) {
          input.value = state.user.email;
        }
      }).catch(function () {});
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const email = input.value.trim();
      if (!email) {
        status.textContent = 'Enter your email first.';
        status.className = 'cn-footer-newsletter-status is-error';
        input.focus();
        return;
      }

      button.disabled = true;
      status.textContent = 'Saving…';
      status.className = 'cn-footer-newsletter-status';

      global.CNAuth.csrfToken().then(function (token) {
        const body = new URLSearchParams({
          csrf_token: token,
          email: email,
          website: ''
        });
        return fetch(CN_BASE + 'backend/api/newsletter.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: body
        });
      }).then(async function (r) {
        const res = await r.json().catch(function () { return {}; });
        if (!r.ok || !res.success) throw new Error(res.error || 'Could not subscribe.');
        return res;
      }).then(function (res) {
        status.textContent = res.message || 'You are subscribed ✓';
        status.className = 'cn-footer-newsletter-status is-success';
        button.disabled = false;
      }).catch(function (err) {
        status.textContent = err.message || 'Could not subscribe. Please try again.';
        status.className = 'cn-footer-newsletter-status is-error';
        button.disabled = false;
      });
    });
  }

  function loadUpdates(root) {
    fetch(CN_BASE + 'backend/api/updates.php?limit=5', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        const updates = res && res.success && res.data ? (res.data.updates || []) : [];
        const feed = root.querySelector('#cn-footer-updates');
        if (feed) feed.innerHTML = updatesMarkup(updates);
      })
      .catch(function () {
        const feed = root.querySelector('#cn-footer-updates');
        if (feed) feed.innerHTML = '<p class="text-gray-500 text-xs">Updates are temporarily unavailable.</p>';
      });

    // Only authenticated administrators receive the publisher controls.
    if (!global.CNAuth || typeof global.CNAuth.me !== 'function') return;
    global.CNAuth.me().then(function (state) {
      if (!state || !state.authenticated || !state.user || state.user.role !== 'admin') return;

      const composer = root.querySelector('#cn-admin-update-composer');
      if (!composer) return;
      composer.classList.remove('hidden');

      const form = composer.querySelector('form');
      const input = composer.querySelector('textarea');
      const status = composer.querySelector('[data-update-status]');
      const button = composer.querySelector('button');

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) {
          status.textContent = 'Write an update first.';
          status.className = 'cn-footer-update-status is-error';
          return;
        }

        button.disabled = true;
        status.textContent = 'Publishing & emailing subscribers…';
        status.className = 'cn-footer-update-status';

        global.CNAuth.csrfToken().then(function (token) {
          const body = new URLSearchParams({ csrf_token: token, message: message });
          return fetch(CN_BASE + 'backend/api/updates.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: body
          });
        }).then(async function (r) {
          const res = await r.json().catch(function () { return {}; });
          if (!r.ok || !res.success) throw new Error(res.error || 'Could not publish update.');
          return res;
        }).then(function (res) {
          input.value = '';
          const delivery = res && res.data && res.data.update && res.data.update.delivery;
          if (delivery && delivery.recipients > 0) {
            if (delivery.failed > 0) {
              status.textContent = 'Published • ' + delivery.sent + ' email(s) sent • ' + delivery.failed + ' failed';
            } else {
              status.textContent = 'Published • emailed ' + delivery.sent + ' subscriber(s) ✓';
            }
          } else {
            status.textContent = 'Published • no email subscribers yet';
          }
          status.className = 'cn-footer-update-status is-success';
          return fetch(CN_BASE + 'backend/api/updates.php?limit=5', { credentials: 'same-origin' });
        }).then(function (r) { return r.json(); })
          .then(function (res) {
            const updates = res && res.success && res.data ? (res.data.updates || []) : [];
            const feed = root.querySelector('#cn-footer-updates');
            if (feed) feed.innerHTML = updatesMarkup(updates);
          })
          .catch(function (err) {
            status.textContent = err.message;
            status.className = 'cn-footer-update-status is-error';
          })
          .finally(function () {
            button.disabled = false;
          });
      });
    });
  }

  function render() {
    const root = document.getElementById('footer-root');
    if (!root) return;

    const socialsHtml = SOCIALS.map(function (n) {
      return '<a href="#" class="w-10 h-10 bg-white/5 border border-white/10 rounded-full flex items-center justify-center text-gray-400 hover:bg-orange-500 hover:border-orange-500 hover:text-white transition-all duration-300 hover:scale-110" aria-label="' + n.charAt(0).toUpperCase() + n.slice(1) + '">' + cnIcon(n, 'w-5 h-5') + '</a>';
    }).join('');

    root.innerHTML =
      '<footer class="bg-black/80 backdrop-blur-xl border-t border-orange-500/10 pt-12 pb-24 lg:pb-6">' +
        '<div class="container mx-auto px-4 sm:px-6 lg:px-8">' +
          '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">' +
            '<div><div class="flex items-center gap-2 mb-4"><img src="' + CN_FRONTEND + 'images/logo.png" alt="CineNova" class="h-8 w-8 object-contain flex-shrink-0" width="615" height="640" /><span class="text-xl font-bold text-white">Cine<span class="text-orange-500">Nova</span></span></div>' +
              '<p class="text-gray-400 text-sm leading-relaxed">Stream the future of entertainment. Unlimited movies, TV shows, and more.</p></div>' +
            '<div><h4 class="text-white font-semibold mb-3">Navigation</h4><ul class="space-y-2 text-sm">' +
              '<li><a href="index.html" class="text-gray-400 hover:text-orange-400 transition-colors">Home</a></li>' +
              '<li><a href="movies.html" class="text-gray-400 hover:text-orange-400 transition-colors">Movies</a></li>' +
              '<li><a href="series.html" class="text-gray-400 hover:text-orange-400 transition-colors">Series</a></li>' +
              '<li><a href="new-releases.html" class="text-gray-400 hover:text-orange-400 transition-colors">New Releases</a></li>' +
              '<li><a href="chine.html" class="text-gray-400 hover:text-orange-400 transition-colors">Chine</a></li></ul></div>' +
            '<div><h4 class="text-white font-semibold mb-3">Legal</h4><ul class="space-y-2 text-sm">' +
              '<li><a href="#" class="text-gray-400 hover:text-orange-400 transition-colors">Terms of Service</a></li>' +
              '<li><a href="#" class="text-gray-400 hover:text-orange-400 transition-colors">Privacy Policy</a></li>' +
              '<li><a href="#" class="text-gray-400 hover:text-orange-400 transition-colors">Cookie Policy</a></li>' +
              '<li><a href="#" class="text-gray-400 hover:text-orange-400 transition-colors">Help Center</a></li></ul></div>' +
            '<div class="text-left">' +
              '<h4 class="text-white font-semibold mb-3 flex items-center gap-2"><span class="text-orange-500">' + cnIcon('alert', 'w-4 h-4') + '</span>Connect</h4>' +
              '<div class="flex gap-3 mb-4">' + socialsHtml + '</div>' +
              '<div class="cn-footer-newsletter">' +
                '<div class="cn-footer-newsletter-title"><span class="cn-footer-newsletter-icon">' + cnIcon('mail', 'w-4 h-4') + '</span><span>Stay Updated</span></div>' +
                '<p class="cn-footer-newsletter-copy">Get the latest movie news and updates.</p>' +
                '<form id="cn-newsletter-form" class="cn-footer-newsletter-form" novalidate>' +
                  '<div class="cn-footer-newsletter-input-wrap">' +
                    '<input type="email" id="cn-newsletter-email" name="email" autocomplete="email" maxlength="190" required placeholder="Enter your email…" aria-label="Email address for movie updates">' +
                    '<input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="cn-footer-honeypot">' +
                  '</div>' +
                  '<button type="submit" id="cn-newsletter-button"><span>' + cnIcon('mail', 'w-3.5 h-3.5') + '</span><span>Subscribe</span></button>' +
                '</form>' +
                '<span data-newsletter-status class="cn-footer-newsletter-status" aria-live="polite"></span>' +
              '</div>' +
              '<div class="cn-footer-updates-wrap">' +
                '<div class="flex items-center justify-between gap-3 mb-2"><p class="cn-footer-updates-heading">Latest Movie Updates</p><span class="cn-footer-live-dot" aria-label="Live updates"></span></div>' +
                '<div id="cn-footer-updates" class="space-y-2"><p class="text-gray-500 text-xs">Loading updates…</p></div>' +
              '</div>' +
              '<div id="cn-admin-update-composer" class="cn-footer-admin-composer hidden mt-4">' +
                '<div class="flex items-center gap-2 mb-2"><span class="text-orange-400">' + cnIcon('mail', 'w-4 h-4') + '</span><p class="text-white text-xs font-semibold">Admin • Send New Movie Update</p></div>' +
                '<form class="flex flex-col gap-2">' +
                  '<textarea maxlength="300" rows="2" placeholder="e.g. New movie added: The Last Voyage…" aria-label="New movie update" class="cn-footer-update-input"></textarea>' +
                  '<div class="flex items-center gap-2"><button type="submit" class="cn-footer-update-button"><span>' + cnIcon('mail', 'w-3.5 h-3.5') + '</span><span>Send Update</span></button><span data-update-status class="cn-footer-update-status" aria-live="polite"></span></div>' +
                '</form>' +
              '</div>' +
            '</div>' +
          '</div>' +
          '<div class="border-t border-white/5 pt-6 text-center"><p class="text-gray-500 text-xs sm:text-sm">' +
            '&copy; ' + new Date().getFullYear() + ' <span class="text-orange-400 font-medium">CineNova</span>. All rights reserved. Built with <span class="text-orange-400">&hearts;</span> by Ndayambaje Elyse.' +
          '</p></div>' +
        '</div>' +
      '</footer>';

    loadNewsletter(root);
    loadUpdates(root);
  }

  global.CNFooter = { render: render };
})(window);
