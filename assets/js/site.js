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

  // ------------------------------------------------------------------ team bios: read more / less
  $$('[data-bio-toggle]').forEach(function (btn) {
    var bio = document.getElementById(btn.getAttribute('aria-controls'));
    if (!bio) return;
    btn.hidden = false;
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') !== 'true';
      bio.classList.toggle('is-clamped', !open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.textContent = open ? 'Show less' : 'Read more';
    });
  });

  // ------------------------------------------------------------------ share: copy link
  $$('[data-copy-link]').forEach(function (btn) {
    if (!navigator.clipboard || !window.isSecureContext) return;
    btn.hidden = false;
    var done = btn.closest('.share') && $('[data-copy-done]', btn.closest('.share'));
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(btn.getAttribute('data-copy-link')).then(function () {
        if (done) { done.textContent = 'Link copied'; setTimeout(function () { done.textContent = ''; }, 2500); }
      });
    });
  });

  // ------------------------------------------------------------------ table of contents: highlight current section
  $$('[data-toc]').forEach(function (toc) {
    var links = $$('a[href^="#"]', toc);
    if (!links.length || !('IntersectionObserver' in window)) return;
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && map[en.target.id]) {
          links.forEach(function (l) { l.classList.remove('is-active'); });
          map[en.target.id].classList.add('is-active');
        }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    Object.keys(map).forEach(function (id) { var h = document.getElementById(id); if (h) io.observe(h); });
  });

  // ------------------------------------------------------------------ photo lightbox
  // Gallery links open a full-screen viewer: arrows / ← → keys / swipe, Esc to close.
  // Without JavaScript the links simply open the large photo.
  (function () {
    var galleries = $$('[data-gallery]');
    if (!galleries.length || typeof HTMLDialogElement === 'undefined') return;
    var dlg, img, cap, count, prevBtn, nextBtn, items = [], index = 0, opener = null;
    var svg = function (d) { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + d + '"/></svg>'; };

    function build() {
      dlg = document.createElement('dialog');
      dlg.className = 'lightbox';
      dlg.setAttribute('aria-label', 'Photo viewer');
      dlg.innerHTML =
        '<div class="lightbox__top"><span data-lb-count aria-live="polite"></span>' +
        '<button type="button" class="lightbox__btn" data-lb-close aria-label="Close photo viewer">' + svg('M6 6l12 12M18 6 6 18') + '</button></div>' +
        '<div class="lightbox__stage" data-lb-stage>' +
        '<button type="button" class="lightbox__btn lightbox__nav lightbox__nav--prev" data-lb-prev aria-label="Previous photo">' + svg('m15 6-6 6 6 6') + '</button>' +
        '<img class="lightbox__img" alt="" data-lb-img>' +
        '<button type="button" class="lightbox__btn lightbox__nav lightbox__nav--next" data-lb-next aria-label="Next photo">' + svg('m9 6 6 6-6 6') + '</button>' +
        '</div><p class="lightbox__caption" data-lb-caption></p>';
      document.body.appendChild(dlg);
      img = $('[data-lb-img]', dlg); cap = $('[data-lb-caption]', dlg); count = $('[data-lb-count]', dlg);
      prevBtn = $('[data-lb-prev]', dlg); nextBtn = $('[data-lb-next]', dlg);
      $('[data-lb-close]', dlg).addEventListener('click', function () { dlg.close(); });
      prevBtn.addEventListener('click', function () { show(index - 1); });
      nextBtn.addEventListener('click', function () { show(index + 1); });
      dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.hasAttribute('data-lb-stage')) dlg.close(); });
      dlg.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1); }
      });
      dlg.addEventListener('close', function () {
        root.classList.remove('lightbox-open');
        if (lenis) lenis.start();
        if (opener) opener.focus({ preventScroll: true });
      });
      // Swipe left/right on touch screens
      var startX = null, startY = null;
      var stage = $('[data-lb-stage]', dlg);
      stage.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; }, { passive: true });
      stage.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var dx = e.changedTouches[0].clientX - startX, dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) show(index + (dx < 0 ? 1 : -1));
        startX = null;
      }, { passive: true });
    }

    function show(i) {
      index = (i + items.length) % items.length;
      var a = items[index];
      var thumb = $('img', a);
      img.classList.add('is-loading');
      var loader = new Image();
      loader.onload = function () {
        img.src = loader.src;
        img.alt = thumb ? thumb.alt : '';
        img.classList.remove('is-loading');
      };
      loader.src = a.href;
      cap.textContent = a.getAttribute('data-caption') || '';
      count.textContent = items.length > 1 ? (index + 1) + ' / ' + items.length : '';
      prevBtn.hidden = nextBtn.hidden = items.length < 2;
      [index + 1, index - 1].forEach(function (n) { var x = items[(n + items.length) % items.length]; if (x) (new Image()).src = x.href; });
    }

    galleries.forEach(function (g) {
      var links = $$('[data-lightbox]', g);
      links.forEach(function (a, i) {
        a.addEventListener('click', function (e) {
          if (e.metaKey || e.ctrlKey || e.shiftKey) return; // let people open in a new tab
          e.preventDefault();
          if (!dlg) build();
          items = links; opener = a;
          dlg.showModal();
          root.classList.add('lightbox-open');
          if (lenis) lenis.stop();
          show(i);
          $('[data-lb-close]', dlg).focus();
        });
      });
    });
  })();

  // ------------------------------------------------------------------ project filters
  // Filters and page links are normal URLs (shareable + crawlable). With JavaScript
  // the results are swapped in place with a short animation and the URL is updated.
  (function () {
    var form = $('[data-filters]'), results = $('[data-results]');
    if (!form || !results || !window.fetch || !window.DOMParser || !window.history.pushState) return;
    var clear = $('[data-filters-clear]', form);
    var busy = null;

    function urlFromForm() {
      var params = [];
      $$('select', form).forEach(function (sel) { if (sel.value) params.push(encodeURIComponent(sel.name) + '=' + encodeURIComponent(sel.value)); });
      return form.getAttribute('action') + (params.length ? '?' + params.join('&') : '');
    }

    function load(url, push, scrollUp) {
      if (busy) busy.abort();
      busy = typeof AbortController !== 'undefined' ? new AbortController() : null;
      results.classList.add('is-loading');
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' }, signal: busy ? busy.signal : undefined })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var fresh = doc.querySelector('[data-results]');
          if (!fresh) { window.location.href = url; return; }
          results.innerHTML = fresh.innerHTML;
          document.title = doc.title;
          var canon = doc.querySelector('link[rel="canonical"]'), mine = $('link[rel="canonical"]');
          if (canon && mine) mine.setAttribute('href', canon.getAttribute('href'));
          // keep the dropdowns in sync (e.g. after Back/Forward)
          $$('select', form).forEach(function (sel) {
            var other = doc.querySelector('[data-filters] select[name="' + sel.name + '"]');
            if (other) sel.value = other.value;
          });
          if (clear) clear.hidden = !$$('select', form).some(function (sel) { return sel.value; });
          if (push) window.history.pushState({ projects: true }, '', url);
          results.classList.remove('is-loading');
          if (scrollUp) {
            var top = form.getBoundingClientRect().top + window.scrollY - 100;
            if (lenis) lenis.scrollTo(top); else window.scrollTo({ top: top, behavior: reduceMotion ? 'auto' : 'smooth' });
          }
          var cards = $$('[data-reveal-item]', results);
          if (hasGsap && cards.length) {
            gsap.fromTo(cards, { autoAlpha: 0, y: reduceMotion ? 0 : 30 }, { autoAlpha: 1, y: 0, duration: reduceMotion ? 0.3 : 0.6, stagger: reduceMotion ? 0 : 0.05, ease: 'power3.out', clearProps: 'transform' });
            ScrollTrigger.refresh();
          }
        })
        .catch(function (err) { if (!err || err.name !== 'AbortError') window.location.href = url; });
    }

    form.addEventListener('change', function () { load(urlFromForm(), true, false); });
    form.addEventListener('submit', function (e) { e.preventDefault(); load(urlFromForm(), true, false); });
    if (clear) clear.addEventListener('click', function (e) {
      e.preventDefault();
      $$('select', form).forEach(function (sel) { sel.value = ''; });
      load(form.getAttribute('action'), true, false);
    });
    results.addEventListener('click', function (e) {
      var a = e.target.closest('.pager a, .empty-state a[href^="/projects"]');
      if (!a) return;
      e.preventDefault();
      if (a.closest('.empty-state')) $$('select', form).forEach(function (sel) { sel.value = ''; });
      load(a.getAttribute('href'), true, true);
    });
    window.addEventListener('popstate', function () { load(window.location.pathname + window.location.search, false, false); });
  })();

  // ------------------------------------------------------------------ animations
  if (!hasGsap) return;

  var mm = gsap.matchMedia();
  var EASE = 'power3.out';

  /** Fade + rise when the element scrolls into view (once). */
  function reveal(targets, trigger, vars) {
    return gsap.from(targets, Object.assign({
      autoAlpha: 0, y: 40, duration: 1, ease: EASE, stagger: 0.08,
      scrollTrigger: { trigger: trigger, start: 'top 92%', once: true }
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

    // Hero / page header backgrounds: slow parallax as you scroll away
    $$('[data-parallax]').forEach(function (layer) {
      gsap.to(layer, { yPercent: 14, ease: 'none',
        scrollTrigger: { trigger: layer.closest('section'), start: 'top top', end: 'bottom top', scrub: true } });
    });

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

  // ---- Safety net: content must ALWAYS end up visible.
  // If a scroll animation never fires (odd scroll position, a browser hiccup,
  // a section at the very bottom of the page), finish it shortly after the
  // element is on screen so nothing stays invisible.
  (function () {
    var SEL = '[data-reveal], [data-reveal-item], [data-split], [data-split-lines], [data-mask-reveal]';
    function finish(el) {
      [el].concat($$('*', el)).forEach(function (node) {
        gsap.getTweensOf(node).forEach(function (t) {
          var top = t;
          while (top.parent && top.parent !== gsap.globalTimeline) top = top.parent;
          var st = top.scrollTrigger;
          if (st && st.vars && st.vars.scrub) return;        // parallax / pinned scrub: leave alone
          if (!top.isActive() && top.progress() < 1) top.progress(1);
        });
      });
      var cs = getComputedStyle(el);
      if (cs.visibility === 'hidden' || parseFloat(cs.opacity) < 0.05) gsap.set(el, { autoAlpha: 1, y: 0, clearProps: 'transform' });
    }
    var els = $$(SEL);
    if (!('IntersectionObserver' in window)) { setTimeout(function () { els.forEach(finish); }, 3000); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        setTimeout(function () { finish(en.target); }, 1800);
      });
    });
    els.forEach(function (el) { io.observe(el); });
  })();

  // Re-measure once fonts and images have loaded
  window.addEventListener('load', function () { ScrollTrigger.refresh(); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { ScrollTrigger.refresh(); });
})();
