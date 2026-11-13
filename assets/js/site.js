/*
 * Majid Traders — public website behaviour + animations.
 * Loaded with "defer" after GSAP, ScrollTrigger, SplitText and Lenis (jsDelivr, pinned).
 *
 * Progressive enhancement: the HTML/CSS page is complete without this file.
 * If the animation libraries fail to load, only the basic interactions run.
 * prefers-reduced-motion: no smooth scroll, no parallax, no pinning, simple fades.
 */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.remove('no-js');
  root.classList.add('js');

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasGsap = typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined';
  var lenis = null;

  function $(s, c) { return (c || document).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }

  // ------------------------------------------------------------------ smooth scroll
  if (hasGsap) {
    gsap.registerPlugin(ScrollTrigger);
    if (window.SplitText) gsap.registerPlugin(SplitText);
    if (!reduceMotion && typeof window.Lenis !== 'undefined') {
      lenis = new Lenis({ duration: 1.1, smoothWheel: true });
      lenis.on('scroll', ScrollTrigger.update);
      gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
      gsap.ticker.lagSmoothing(0);
    }
  }

  /** Scroll to an element or the top (smooth when allowed). */
  function scrollToTarget(target) {
    if (lenis) lenis.scrollTo(target, { offset: 0 });
    else if (target === 0) window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    else target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth' });
  }

  // In-page links (#after-hero, back to top…)
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="#"]');
    if (!a || a.hasAttribute('data-menu-toggle')) return;
    var id = a.getAttribute('href').slice(1);
    if (id === 'main') return; // skip link: let the browser move focus
    var target = id === 'top' || id === '' ? 0 : document.getElementById(id);
    if (target === null) return;
    e.preventDefault();
    scrollToTarget(target);
  });

  // ------------------------------------------------------------------ header
  var header = $('[data-header]');
  var lastY = window.scrollY;
  var toTop = $('[data-to-top]');
  var ring = $('[data-progress]');
  function onScroll() {
    var y = window.scrollY;
    if (header && !root.classList.contains('menu-open')) {
      header.classList.toggle('is-scrolled', y > 40);
      var goingDown = y > lastY + 4, goingUp = y < lastY - 4;
      if (goingDown && y > 220) header.classList.add('is-hidden');
      else if (goingUp || y < 220) header.classList.remove('is-hidden');
    }
    if (toTop) {
      var max = document.documentElement.scrollHeight - window.innerHeight;
      var p = max > 0 ? Math.min(1, y / max) : 0;
      toTop.classList.toggle('is-visible', y > 600);
      if (ring) ring.style.strokeDashoffset = String(100 - p * 100);
    }
    lastY = y;
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ------------------------------------------------------------------ mobile menu
  var menu = $('[data-mobile-menu]');
  var toggle = $('[data-menu-toggle]');
  if (menu && toggle) {
    var setMenu = function (open) {
      if (open) {
        menu.hidden = false;
        requestAnimationFrame(function () { requestAnimationFrame(function () { menu.classList.add('is-open'); }); });
        root.classList.add('menu-open');
        if (lenis) lenis.stop();
        var first = $('a', menu);
        if (first) first.focus({ preventScroll: true });
      } else {
        menu.classList.remove('is-open');
        root.classList.remove('menu-open');
        if (lenis) lenis.start();
        setTimeout(function () { if (!menu.classList.contains('is-open')) menu.hidden = true; }, reduceMotion ? 0 : 400);
        toggle.focus({ preventScroll: true });
      }
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    };
    toggle.setAttribute('role', 'button');
    toggle.setAttribute('aria-label', 'Open menu');
    toggle.addEventListener('click', function (e) { e.preventDefault(); setMenu(!root.classList.contains('menu-open')); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && root.classList.contains('menu-open')) setMenu(false);
      // keep keyboard focus inside the open menu (+ the close button)
      if (e.key === 'Tab' && root.classList.contains('menu-open')) {
        var items = [toggle].concat($$('a', menu));
        var i = items.indexOf(document.activeElement);
        if (e.shiftKey && i <= 0) { e.preventDefault(); items[items.length - 1].focus(); }
        else if (!e.shiftKey && i === items.length - 1) { e.preventDefault(); items[0].focus(); }
      }
    });
    $$('a', menu).forEach(function (a) { a.addEventListener('click', function () { setMenu(false); }); });
    window.matchMedia('(min-width: 1100px)').addEventListener('change', function (m) { if (m.matches) setMenu(false); });
  }

  // ------------------------------------------------------------------ testimonials slider
  $$('[data-slider]').forEach(function (slider) {
    slider.setAttribute('data-lenis-prevent', '');
    var buttons = slider.parentNode.querySelector('[data-slider-buttons]');
    var step = function () {
      var card = slider.firstElementChild;
      return card ? card.getBoundingClientRect().width + parseFloat(getComputedStyle(slider).columnGap || 0) : slider.clientWidth;
    };
    var next = function () {
      if (slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 8) slider.scrollTo({ left: 0 });
      else slider.scrollBy({ left: step() });
    };
    var prev = function () {
      if (slider.scrollLeft <= 8) slider.scrollTo({ left: slider.scrollWidth });
      else slider.scrollBy({ left: -step() });
    };
    if (buttons) {
      buttons.hidden = false;
      $('[data-slider-next]', buttons).addEventListener('click', next);
      $('[data-slider-prev]', buttons).addEventListener('click', prev);
    }
    slider.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); prev(); }
    });
    if (!reduceMotion && slider.children.length > 1) {
      var paused = false;
      ['mouseenter', 'focusin', 'touchstart'].forEach(function (t) { slider.addEventListener(t, function () { paused = true; }, { passive: true }); });
      ['mouseleave', 'focusout'].forEach(function (t) { slider.addEventListener(t, function () { paused = false; }); });
      setInterval(function () { if (!paused && !document.hidden) next(); }, 7000);
    }
  });

  // ------------------------------------------------------------------ animations
  if (!hasGsap) return;

  var mm = gsap.matchMedia();
  var EASE = 'power3.out';

  /** Fade + rise when the element scrolls into view (once). */
  function reveal(targets, trigger, vars) {
    return gsap.from(targets, Object.assign({
      autoAlpha: 0, y: 40, duration: 1, ease: EASE, stagger: 0.08,
      scrollTrigger: { trigger: trigger, start: 'top 88%', once: true }
    }, vars || {}));
  }

  function countUp(el) {
    var end = parseInt(el.getAttribute('data-count'), 10) || 0;
    var obj = { v: 0 };
    el.textContent = '0';
    gsap.to(obj, {
      v: end, duration: 2.2, ease: 'power2.out',
      onUpdate: function () { el.textContent = Math.round(obj.v).toLocaleString('en-US'); }
    });
  }

  // ---- Full motion
  mm.add('(prefers-reduced-motion: no-preference)', function () {
    // Simple reveals
    $$('[data-reveal]').forEach(function (el) {
      if (el.closest('.hero')) return;
      reveal(el, el);
    });
    // Staggered groups (cards, counters…)
    $$('[data-reveal-group]').forEach(function (group) {
      if (group.closest('.is-horizontal')) return;
      reveal($$('[data-reveal-item]', group), group, { y: 50, stagger: 0.1 });
    });

    // Headings: word by word / paragraphs: line by line (SplitText)
    var runSplits = function () {
      if (!window.SplitText) return;
      $$('[data-split]').forEach(function (el) {
        SplitText.create(el, {
          type: 'words', mask: 'words', autoSplit: true,
          onSplit: function (self) {
            return gsap.from(self.words, { yPercent: 110, duration: 0.9, ease: 'power4.out', stagger: 0.045,
              scrollTrigger: { trigger: el, start: 'top 88%', once: true } });
          }
        });
      });
      $$('[data-split-lines]').forEach(function (el) {
        SplitText.create(el, {
          type: 'lines', mask: 'lines', linesClass: 'split-line', autoSplit: true,
          onSplit: function (self) {
            return gsap.from(self.lines, { yPercent: 100, autoAlpha: 0, duration: 1, ease: 'power4.out', stagger: 0.09,
              scrollTrigger: { trigger: el, start: 'top 85%', once: true } });
          }
        });
      });
    };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(runSplits); else runSplits();

    // About image: wipe reveal (mask moves up while the photo settles)
    $$('[data-mask-reveal]').forEach(function (fig) {
      var mask = $('.about__mask', fig), img = $('img', fig);
      gsap.timeline({ scrollTrigger: { trigger: fig, start: 'top 80%', once: true } })
        .from(mask, { yPercent: 100, duration: 1.3, ease: 'power4.inOut' })
        .from(img, { yPercent: -100, scale: 1.3, duration: 1.3, ease: 'power4.inOut' }, 0)
        .to(img, { scale: 1, duration: 1.2, ease: EASE }, '>-0.4');
    });

    // Hero background: slow parallax as you scroll away
    var parallax = $('[data-parallax]');
    if (parallax) {
      gsap.to(parallax, { yPercent: 14, ease: 'none',
        scrollTrigger: { trigger: parallax.closest('.hero'), start: 'top top', end: 'bottom top', scrub: true } });
    }

    // Counters count up when visible
    $$('[data-count]').forEach(function (el) {
      ScrollTrigger.create({ trigger: el, start: 'top 90%', once: true, onEnter: function () { countUp(el); } });
    });

    // Timeline line draws itself
    $$('[data-timeline]').forEach(function (tl) {
      var bar = $('[data-timeline-progress]', tl);
      if (bar) gsap.fromTo(bar, { scaleY: 0 }, { scaleY: 1, ease: 'none',
        scrollTrigger: { trigger: tl, start: 'top 70%', end: 'bottom 60%', scrub: true } });
    });
  });

  // ---- Desktop + full motion: pinned horizontal project showcase
  mm.add('(min-width: 1024px) and (prefers-reduced-motion: no-preference)', function () {
    var sec = $('[data-hscroll]');
    if (!sec) return;
    var track = $('[data-hscroll-track]', sec);
    sec.classList.add('is-horizontal');
    var distance = function () { return Math.max(0, track.scrollWidth - window.innerWidth); };
    if (distance() < 40) { sec.classList.remove('is-horizontal'); return; }
    gsap.from($$('.project-card', track), { autoAlpha: 0, x: 80, duration: 1, ease: EASE, stagger: 0.08,
      scrollTrigger: { trigger: sec, start: 'top 75%', once: true } });
    gsap.to(track, {
      x: function () { return -distance(); }, ease: 'none',
      scrollTrigger: { trigger: sec, start: 'top top', end: function () { return '+=' + distance(); },
        pin: true, scrub: 1, invalidateOnRefresh: true, anticipatePin: 1 }
    });
    return function () { sec.classList.remove('is-horizontal'); gsap.set(track, { clearProps: 'transform' }); };
  });

  // ---- Reduced motion: gentle fades only
  mm.add('(prefers-reduced-motion: reduce)', function () {
    $$('[data-reveal], [data-reveal-item], [data-split], [data-split-lines]').forEach(function (el) {
      if (el.closest('.hero')) return;
      gsap.from(el, { autoAlpha: 0, duration: 0.6, ease: 'none', scrollTrigger: { trigger: el, start: 'top 92%', once: true } });
    });
  });

  // Re-measure once fonts and images have loaded
  window.addEventListener('load', function () { ScrollTrigger.refresh(); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { ScrollTrigger.refresh(); });
})();
