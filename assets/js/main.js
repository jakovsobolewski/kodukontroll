/* Kodukontroll — nav toggle, contact form, footer year */
(function () {
  'use strict';

  var toggle = document.querySelector('.nav-toggle');
  var links = document.getElementById('nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Contact form: opens the visitor's mail client with a prefilled message.
  // Replace with a form backend (see README) when one is set up.
  var form = document.getElementById('contact-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = new FormData(form);
      var to = form.getAttribute('data-to') || 'info@kodukontroll.com';
      var en = document.documentElement.lang === 'en';
      var L = en
        ? { subject: 'Inspection request', name: 'Name', phone: 'Phone', email: 'Email', type: 'Furniture type', city: 'Location', date: 'Timing', msg: 'Details' }
        : { subject: 'Ülevaatuse päring', name: 'Nimi', phone: 'Telefon', email: 'E-post', type: 'Mööbli liik', city: 'Asukoht', date: 'Aeg', msg: 'Lisainfo' };
      var body = [
        L.name + ': ' + (data.get('name') || ''),
        L.phone + ': ' + (data.get('phone') || ''),
        L.email + ': ' + (data.get('email') || ''),
        L.type + ': ' + (data.get('type') || ''),
        L.city + ': ' + (data.get('city') || ''),
        L.date + ': ' + (data.get('date') || ''),
        '',
        L.msg + ':',
        (data.get('message') || '')
      ].join('\n');
      var subject = L.subject + (data.get('city') ? ' — ' + data.get('city') : '');
      window.location.href = 'mailto:' + to + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
      var status = document.getElementById('form-status');
      if (status) status.classList.add('is-visible');
    });
  }

  var y = document.getElementById('year');
  if (y) y.textContent = String(new Date().getFullYear());
})();
