/* Admin panel: mobile menu. Loaded in <head>, no external libraries. */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.remove('no-js');
  root.classList.add('js');

  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.querySelector('.sidebar-backdrop');
    var openBtn = document.querySelector('.topbar__menu');
    if (!sidebar || !openBtn) return;

    function setOpen(open) {
      sidebar.classList.toggle('is-open', open);
      if (backdrop) backdrop.hidden = !open;
      openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        var first = sidebar.querySelector('.sidebar__close');
        if (first) first.focus();
      } else {
        openBtn.focus();
      }
    }

    document.querySelectorAll('[data-menu-toggle]').forEach(function (el) {
      el.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('is-open'));
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('is-open')) setOpen(false);
    });
  });
})();
