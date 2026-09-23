/* Kodukontroll — nav, scroll progress, reveal, tabs, timeline, count-up, FAQ, forms, year */
(function () {
  'use strict';
  var d = document, w = window;
  var reduce = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Mobile nav ---- */
  var toggle = d.querySelector('.nav-toggle');
  var links = d.getElementById('nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    links.addEventListener('click', function (e) {
      if (e.target.tagName === 'A' && links.classList.contains('is-open')) {
        links.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---- Header state + scroll progress + timeline fill (one scroll handler) ---- */
  var top = d.querySelector('.top');
  var prog = d.querySelector('.progress span');
  var tl = d.querySelector('.tl');
  var tlLine = tl && tl.querySelector('.tl__line');
  var tlSteps = tl ? [].slice.call(tl.querySelectorAll('.tl__step')) : [];
  var ticking = false;

  function onScroll() {
    var y = w.scrollY || d.documentElement.scrollTop;
    if (top) top.classList.toggle('is-scrolled', y > 8);
    if (prog) {
      var max = d.documentElement.scrollHeight - w.innerHeight;
      prog.style.setProperty('--p', max > 0 ? Math.min(1, y / max).toFixed(4) : 0);
    }
    if (tl && tlLine) {
      var r = tl.getBoundingClientRect();
      var mid = w.innerHeight * 0.55;
      var p = (mid - r.top) / r.height;
      p = Math.max(0, Math.min(1, p));
      tlLine.style.setProperty('--p', p.toFixed(3));
      var nowIdx = -1;
      tlSteps.forEach(function (s, i) {
        var sr = s.querySelector('.tl__no').getBoundingClientRect();
        var past = sr.top + sr.height / 2 < mid;
        s.classList.toggle('is-past', past);
        if (past) nowIdx = i;
      });
      tlSteps.forEach(function (s, i) { s.classList.toggle('is-now', i === nowIdx); });
    }
    ticking = false;
  }
  w.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; w.requestAnimationFrame(onScroll); }
  }, { passive: true });
  w.addEventListener('resize', onScroll);
  onScroll();

  /* ---- Scroll reveal ---- */
  var revealEls = [].slice.call(d.querySelectorAll('[data-reveal]'));
  if ('IntersectionObserver' in w && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('is-in');
          io.unobserve(en.target);
          if (en.target.classList.contains('pcard')) countUp(en.target);
        }
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* ---- Count-up for prices ---- */
  function countUp(card) {
    var el = card.querySelector('.num');
    if (!el || reduce) return;
    var target = parseInt(el.getAttribute('data-count'), 10);
    if (isNaN(target)) return;
    var start = null, dur = 1100;
    function step(ts) {
      if (!start) start = ts;
      var t = Math.min(1, (ts - start) / dur);
      var eased = 1 - Math.pow(1 - t, 3);
      el.textContent = Math.round(target * eased);
      if (t < 1) w.requestAnimationFrame(step);
    }
    el.textContent = '0';
    w.requestAnimationFrame(step);
  }

  /* ---- Active section in nav + rail highlight ---- */
  var navLinks = [].slice.call(d.querySelectorAll('.nav a[href^="#"]'));
  var secs = [].slice.call(d.querySelectorAll('main section[id]'));
  if (secs.length && 'IntersectionObserver' in w) {
    var navMap = {};
    navLinks.forEach(function (a) { navMap[a.getAttribute('href').slice(1)] = a; });
    // nav groups: sections without their own nav item map to the nearest preceding one
    var groupFor = { when: 'checks', report: 'checks', process: 'pricing', levels: 'pricing', story: 'why', team: 'why' };
    var current = null;
    var sio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        en.target.classList.toggle('is-current', en.isIntersecting);
        if (en.isIntersecting) {
          var id = en.target.id;
          var key = navMap[id] ? id : groupFor[id];
          if (key && key !== current) {
            current = key;
            navLinks.forEach(function (a) { a.classList.toggle('is-active', a === navMap[key]); });
          }
        }
      });
    }, { rootMargin: '-40% 0px -50% 0px', threshold: 0 });
    secs.forEach(function (s) { sio.observe(s); });
  }

  /* ---- Tabs (what we check) ---- */
  var tabsRoot = d.querySelector('.tabs');
  if (tabsRoot) {
    var list = tabsRoot.querySelector('.tabs__list');
    var tabs = [].slice.call(list.querySelectorAll('[role="tab"]'));
    var panels = [].slice.call(tabsRoot.querySelectorAll('[role="tabpanel"]'));
    var ind = list.querySelector('.tabs__ind');
    var vertical = w.matchMedia('(min-width: 900px)');

    function moveInd(tab) {
      if (!ind) return;
      if (vertical.matches) {
        ind.style.top = tab.offsetTop + 'px';
        ind.style.height = tab.offsetHeight + 'px';
        ind.style.left = '-1px'; ind.style.width = '2px';
      } else {
        ind.style.left = tab.offsetLeft + 'px';
        ind.style.width = tab.offsetWidth + 'px';
        ind.style.top = ''; ind.style.height = '2px';
      }
    }
    function select(tab, focus) {
      tabs.forEach(function (t, i) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.setAttribute('tabindex', on ? '0' : '-1');
        panels[i].hidden = !on;
        panels[i].classList.toggle('is-active', on);
      });
      moveInd(tab);
      if (focus) tab.focus();
      if (!vertical.matches) tab.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduce ? 'auto' : 'smooth' });
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(t, false); });
      t.addEventListener('keydown', function (e) {
        var n = null;
        if (e.key === 'ArrowDown' || e.key === 'ArrowRight') n = tabs[(i + 1) % tabs.length];
        if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') n = tabs[(i - 1 + tabs.length) % tabs.length];
        if (e.key === 'Home') n = tabs[0];
        if (e.key === 'End') n = tabs[tabs.length - 1];
        if (n) { e.preventDefault(); select(n, true); }
      });
    });
    var initial = tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0] || tabs[0];
    function placeInd() { moveInd(tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0] || initial); }
    if (d.fonts && d.fonts.ready) d.fonts.ready.then(placeInd); else placeInd();
    w.addEventListener('resize', placeInd);
    w.addEventListener('load', placeInd);
    placeInd();
  }

  /* ---- FAQ accordion: <details> + CSS grid-row transition (no height math) ---- */
  [].slice.call(d.querySelectorAll('.faq__item')).forEach(function (det) {
    var sum = det.querySelector('summary');
    var body = det.querySelector('.faq__body');
    if (!sum || !body) return;
    var closing = null;
    function finishClose() {
      if (closing) { clearTimeout(closing); closing = null; }
      if (!det.classList.contains('is-open')) det.open = false;
    }
    body.addEventListener('transitionend', function (e) {
      if (e.propertyName === 'grid-template-rows' && !det.classList.contains('is-open')) finishClose();
    });
    sum.addEventListener('click', function (e) {
      e.preventDefault();
      if (det.classList.contains('is-open')) {
        det.classList.remove('is-open');
        if (reduce) { det.open = false; return; }
        closing = setTimeout(finishClose, 500);
      } else {
        if (closing) { clearTimeout(closing); closing = null; }
        det.open = true;
        if (reduce) { det.classList.add('is-open'); return; }
        void body.offsetHeight; // flush layout so the 0fr -> 1fr transition runs
        det.classList.add('is-open');
      }
    });
  });

  /* ---- File attachments: add in several goes, list them, remove one, check size and type before sending ---- */
  var FILE_EXT = /\.(jpe?g|png|heic|heif|webp|gif|pdf|docx?|xlsx?|odt|ods|txt)$/i;
  [].slice.call(d.querySelectorAll('[data-files]')).forEach(function (field) {
    var input = field.querySelector('input[type="file"]');
    var drop = field.querySelector('.files__drop');
    var list = field.querySelector('.files__list');
    var form = field.closest('form');
    if (!input || !list || !form) return;
    var maxFiles = +field.getAttribute('data-max-files') || 5;
    var maxFile = +field.getAttribute('data-max-file') || 10485760;
    var maxTotal = +field.getAttribute('data-max-total') || 20971520;
    var canEdit = true;
    try { new DataTransfer(); } catch (x) { canEdit = false; }   // no DataTransfer: the input keeps only the last pick
    var picked = [];
    var errEl = d.createElement('p');
    errEl.className = 'files__err';
    errEl.setAttribute('role', 'alert');
    errEl.hidden = true;
    list.parentNode.insertBefore(errEl, list.nextSibling);

    function size(n) {
      return n < 1048576 ? Math.max(1, Math.round(n / 1024)) + ' KB' : (n / 1048576).toFixed(1).replace('.0', '') + ' MB';
    }
    function problem() {
      var total = 0;
      if (picked.length > maxFiles) return field.getAttribute('data-err-count');
      for (var i = 0; i < picked.length; i++) {
        if (!FILE_EXT.test(picked[i].name)) return field.getAttribute('data-err-type');
        if (picked[i].size > maxFile) return field.getAttribute('data-err-size');
        total += picked[i].size;
      }
      return total > maxTotal ? field.getAttribute('data-err-size') : '';
    }
    function sync() {
      if (canEdit) {
        var dt = new DataTransfer();
        picked.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
      }
      list.innerHTML = '';
      picked.forEach(function (f, i) {
        var li = d.createElement('li');
        var name = d.createElement('span'); name.className = 'files__name'; name.textContent = f.name; name.title = f.name;
        var sz = d.createElement('span'); sz.className = 'files__size'; sz.textContent = size(f.size);
        li.appendChild(name); li.appendChild(sz);
        if (canEdit) {
          var rm = d.createElement('button');
          rm.type = 'button'; rm.className = 'files__remove';
          rm.textContent = field.getAttribute('data-remove') || '×';
          rm.setAttribute('aria-label', rm.textContent + ': ' + f.name);
          rm.addEventListener('click', function () { picked.splice(i, 1); sync(); input.focus(); });
          li.appendChild(rm);
        }
        list.appendChild(li);
      });
      var p = problem();
      errEl.textContent = p;
      errEl.hidden = !p;
    }

    input.addEventListener('change', function () {
      var added = [].slice.call(input.files || []);
      if (!canEdit) picked = [];
      added.forEach(function (f) {
        var dup = picked.some(function (g) { return g.name === f.name && g.size === f.size; });
        if (!dup) picked.push(f);
      });
      sync();
    });
    if (drop) {
      ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.add('is-over'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('is-over'); }); });
    }
    form.addEventListener('reset', function () { picked = []; setTimeout(sync, 0); });
    form._filesProblem = function () { sync(); return problem(); };
  });

  /* ---- Forms: post to /form.php with fetch, keep the native post as fallback ---- */
  [].slice.call(d.querySelectorAll('form[data-endpoint]')).forEach(function (form) {
    var opened = String(Date.now());          // how long the visitor took; the endpoint drops instant posts
    var ok = form.querySelector('.form-status--ok');
    var err = form.querySelector('.form-status--err');
    var msg = err && err.querySelector('.form-status__msg');
    var btn = form.querySelector('button[type="submit"]');
    var defaultError = msg ? msg.textContent : '';
    var busy = false;

    function show(el) {
      if (ok) ok.classList.toggle('is-visible', el === ok);
      if (err) err.classList.toggle('is-visible', el === err);
    }

    form.addEventListener('submit', function (e) {
      if (form._filesProblem && form._filesProblem()) {   // too many, too big or wrong type: the field says which
        e.preventDefault();
        var fe = form.querySelector('.files__err');
        if (fe) fe.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
        return;
      }
      if (!w.fetch || !w.FormData) return;    // old browser: let it post the form itself
      e.preventDefault();
      if (busy) return;
      busy = true;
      show(null);
      if (btn) { btn.disabled = true; btn.classList.add('is-busy'); }

      var data = new FormData(form);
      data.set('ajax', '1');
      data.set('started', opened);
      data.set('page', w.location.href);

      w.fetch(form.getAttribute('action'), {
        method: 'POST', body: data, headers: { 'Accept': 'application/json' }
      }).then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) { return { r: r, j: j }; });
      }).then(function (res) {
        
        if (res.r.ok && res.j.ok) {
  if (ok) {
    ok.innerHTML = '';

    if (res.j.title) {
      var title = d.createElement('strong');
      title.className = 'form-status__title';
      title.textContent = res.j.title;
      ok.appendChild(title);
    }
    if (res.j.message) {
      var text = d.createElement('span');
      text.className = 'form-status__text';
      text.textContent = res.j.message;
      ok.appendChild(text);
    }
  }

  form.reset();
  show(ok);
// if (typeof w.gtag === 'function') {
//   w.gtag('event', 'conversion', {
//     'send_to': 'AW-18467340094/TeRuCNjZ94AdEL7-9OVE',
//     'value': 1.0,
//     'currency': 'EUR'
//   });
// }
  if (ok) {
    ok.scrollIntoView({
      behavior: reduce ? 'auto' : 'smooth',
      block: 'center'
    });
  }     
        } else {
          if (msg) msg.textContent = res.j.message || defaultError;
          show(err);
        }
      }).catch(function () {
        if (msg) msg.textContent = defaultError;
        show(err);
      }).then(function () {
        busy = false;
        if (btn) { btn.disabled = false; btn.classList.remove('is-busy'); }
      });
    });
  });

  /* ---- Footer year ---- */
  var y = d.getElementById('year');
  if (y) y.textContent = String(new Date().getFullYear());
})();
