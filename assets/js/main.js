/* Kodukontroll — nav, scroll progress, reveal, tabs, timeline, count-up, FAQ, form, year */
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

  /* ---- Contact form: prefilled mailto (no backend). Labels come from the DOM, so it works in every language. ---- */
  var form = d.getElementById('contact-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var to = form.getAttribute('data-to') || 'info@kodukontroll.ee';
      var subject = form.getAttribute('data-subject') || 'Kodukontroll';
      var lines = [];
      [].slice.call(form.querySelectorAll('input[name], select[name], textarea[name]')).forEach(function (f) {
        var lab = form.querySelector('label[for="' + f.id + '"]');
        var name = lab ? lab.textContent.trim() : f.name;
        var val = (f.value || '').trim();
        if (f.tagName === 'TEXTAREA') lines.push('', name + ':', val);
        else lines.push(name + ': ' + val);
      });
      var addr = (form.querySelector('[name="address"]') || {}).value;
      w.location.href = 'mailto:' + to + '?subject=' + encodeURIComponent(subject + (addr ? ' — ' + addr : '')) + '&body=' + encodeURIComponent(lines.join('\n'));
      var status = d.getElementById('form-status');
      if (status) status.classList.add('is-visible');
    });
  }

  /* ---- Footer year ---- */
  var y = d.getElementById('year');
  if (y) y.textContent = String(new Date().getFullYear());
})();
