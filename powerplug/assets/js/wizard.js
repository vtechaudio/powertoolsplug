/* PowerPlug Setup Wizard controller. Progressive, AJAX-driven, no framework. */
(function () {
  'use strict';
  var cfg = window.PowerPlugWizard || {};
  var wrap = document.getElementById('pp-wizard');
  if (!wrap) return;

  function post(action, extra) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce);
    Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
    return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); });
  }

  function goto(step) {
    wrap.querySelectorAll('.pp-wizard__panel').forEach(function (p) {
      var on = p.getAttribute('data-panel') === String(step);
      p.hidden = !on; p.setAttribute('aria-hidden', String(!on));
    });
    wrap.querySelectorAll('.pp-wizard__steps li').forEach(function (li) {
      li.classList.toggle('is-active', li.getAttribute('data-step') === String(step));
    });
  }

  var current = 1;
  wrap.addEventListener('click', function (ev) {
    if (ev.target.closest('.pp-next')) { current = Math.min(current + 1, 5); goto(current); if (current === 2) renderPlugins(); }
  });

  function renderPlugins() {
    var list = document.getElementById('pp-plugin-list');
    if (!list || list.childElementCount) return;
    (cfg.plugins || []).forEach(function (p) {
      var li = document.createElement('li');
      li.dataset.slug = p.slug;
      li.innerHTML = '<span>' + p.name + (p.required ? ' <span class="req">Required</span>' : '') + '</span><span class="state">—</span>';
      list.appendChild(li);
    });
  }

  wrap.addEventListener('click', function (ev) {
    if (!ev.target.closest('.pp-install-all')) return;
    var items = Array.prototype.slice.call(document.querySelectorAll('#pp-plugin-list li'));
    (function next(i) {
      if (i >= items.length) { current = 3; goto(3); document.getElementById('pp-activate-log').textContent = 'All selected plugins processed.'; return; }
      var li = items[i]; var state = li.querySelector('.state');
      state.textContent = 'Installing…';
      post('powerplug_install_plugin', { slug: li.dataset.slug }).then(function (res) {
        state.innerHTML = res && res.success ? '<span class="done">Active</span>' : 'Skipped';
      }).catch(function () { state.textContent = 'Skipped'; }).finally(function () { next(i + 1); });
    })(0);
  });

  wrap.addEventListener('click', function (ev) {
    if (!ev.target.closest('.pp-import')) return;
    var log = document.getElementById('pp-import-log');
    log.textContent = 'Importing…';
    post('powerplug_import_demo', { part: 'all' }).then(function (res) {
      log.textContent = res && res.success ? 'Demo content imported.' : (res && res.data && res.data.message) || 'Import finished with notices.';
    });
  });

  var finish = document.getElementById('pp-finish');
  if (finish) finish.addEventListener('click', function (ev) {
    ev.preventDefault();
    post('powerplug_finish_wizard', {}).finally(function () { window.location.href = cfg.finishUrl; });
  });

  goto(1);
})();
