/*
 * Majid Traders admin panel scripts (local file, no external libraries).
 *  1. Mobile menu            7. Copy-link buttons
 *  2. Auto-submit filters    8. Rich text editor
 *  3. Unsaved-changes warning 9. Media picker (choose / upload) + media fields + galleries
 *  4. Slug from title        10. Media Library drag & drop uploader
 *  5. Character counters     11. Counter rows (Site Settings)
 *  6. Google preview (SEO)
 * Every feature is optional: without JavaScript the forms still submit normally.
 */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.remove('no-js');
  root.classList.add('js');

  var MAX_BYTES = 8 * 1024 * 1024;
  var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function el(tag, attrs, html) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    if (html != null) n.innerHTML = html;
    return n;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function csrf() { var m = $('meta[name="csrf-token"]'); return m ? m.content : ''; }
  function changed(node) { node.dispatchEvent(new Event('change', { bubbles: true })); }
  function formatBytes(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
  function fileProblem(file, accept) {
    if (accept === 'any') accept = '';
    var isImg = IMAGE_TYPES.indexOf(file.type) !== -1, isPdf = file.type === 'application/pdf';
    if (accept === 'image' && !isImg) return 'Please choose a JPG, PNG or WebP image.';
    if (accept === 'document' && !isPdf) return 'Please choose a PDF file.';
    if (!isImg && !isPdf) return 'Only JPG, PNG, WebP images and PDF files can be uploaded.';
    if (file.size > MAX_BYTES) return 'This file is ' + formatBytes(file.size) + '. The maximum is 8 MB.';
    return '';
  }
  var ICON = {
    up: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>',
    down: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
    close: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    file: '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>'
  };

  /** Send a form to the server and expect a JSON answer. */
  function postJSON(url, formData) {
    formData.append('csrf_token', csrf());
    formData.append('format', 'json');
    return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { throw new Error('Unexpected answer from the server. Please reload the page and log in again.'); });
      })
      .then(function (data) { if (!data.ok) throw new Error(data.error || 'Something went wrong.'); return data; });
  }
  function getJSON(url) {
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) { if (!data.ok) throw new Error(data.error || 'Could not load files.'); return data; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initMenu();
    initAutosubmit();
    initDirtyCheck();
    initSlugs();
    initCounters();
    initSerp();
    initCopy();
    $$('textarea[data-richtext]').forEach(initRichText);
    $$('[data-media-field]').forEach(initMediaField);
    $$('[data-gallery-field]').forEach(initGallery);
    initUploader();
    initCounterRows();
    $$('[data-check-all]').forEach(function (all) {
      var form = all.closest('form');
      all.addEventListener('change', function () {
        $$('input[name="ids[]"]', form).forEach(function (c) { c.checked = all.checked; });
      });
    });
    var summary = $('[data-error-summary]');
    if (summary) summary.focus();
  });

  // ------------------------------------------------------------ 1. menu
  function initMenu() {
    var sidebar = document.getElementById('sidebar');
    var backdrop = $('.sidebar-backdrop');
    var openBtn = $('.topbar__menu');
    if (!sidebar || !openBtn) return;
    function setOpen(open) {
      sidebar.classList.toggle('is-open', open);
      if (backdrop) backdrop.hidden = !open;
      openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      var target = open ? $('.sidebar__close', sidebar) : openBtn;
      if (target) target.focus();
    }
    $$('[data-menu-toggle]').forEach(function (b) {
      b.addEventListener('click', function () { setOpen(!sidebar.classList.contains('is-open')); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('is-open')) setOpen(false);
    });
  }

  // ------------------------------------------------------------ 2. filters
  function initAutosubmit() {
    $$('[data-autosubmit]').forEach(function (s) {
      s.addEventListener('change', function () { s.form.submit(); });
    });
  }

  // ------------------------------------------------------------ 3. unsaved changes
  function initDirtyCheck() {
    $$('form[data-dirty-check]').forEach(function (form) {
      var dirty = false, submitting = false;
      form.addEventListener('input', function () { dirty = true; });
      form.addEventListener('change', function () { dirty = true; });
      form.addEventListener('submit', function () { submitting = true; });
      window.addEventListener('beforeunload', function (e) {
        if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
      });
    });
  }

  // ------------------------------------------------------------ 4. slug
  function slugify(s) {
    return String(s).normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
      .replace(/&/g, ' and ').replace(/@/g, ' at ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 160).replace(/-+$/, '');
  }
  function initSlugs() {
    $$('[data-slug-from]').forEach(function (slug) {
      var src = document.getElementById(slug.getAttribute('data-slug-from'));
      if (!src) return;
      var auto = slug.hasAttribute('data-slug-auto');
      src.addEventListener('input', function () { if (auto) slug.value = slugify(src.value); });
      slug.addEventListener('input', function () { auto = slug.value === ''; });
      slug.addEventListener('blur', function () { slug.value = slugify(slug.value); });
    });
  }

  // ------------------------------------------------------------ 5. character counters
  function initCounters() {
    $$('[data-counter]').forEach(function (input) {
      var max = parseInt(input.getAttribute('maxlength'), 10) || 0;
      var rec = (input.getAttribute('data-recommend') || '').split('-').map(Number);
      var out = el('p', { class: 'counter', 'aria-live': 'polite' });
      input.insertAdjacentElement('afterend', out);
      function update() {
        var n = input.value.length, msg = n + (max ? ' / ' + max : '') + ' characters';
        out.className = 'counter';
        if (rec.length === 2 && rec[1]) {
          if (n === 0) msg += ' · best: ' + rec[0] + '–' + rec[1];
          else if (n < rec[0]) { msg += ' · a little short (best ' + rec[0] + '–' + rec[1] + ')'; out.className += ' counter--warn'; }
          else if (n > rec[1]) { msg += ' · may be cut off in Google (best ' + rec[0] + '–' + rec[1] + ')'; out.className += ' counter--warn'; }
          else { msg += ' · good length'; out.className += ' counter--good'; }
        }
        out.textContent = msg;
      }
      input.addEventListener('input', update);
      update();
    });
  }

  // ------------------------------------------------------------ 6. Google preview
  function initSerp() {
    var box = $('[data-serp]');
    if (!box) return;
    var t = $('[data-serp-title]', box), d = $('[data-serp-desc]', box);
    var mt = document.getElementById('f-meta_title'), md = document.getElementById('f-meta_description');
    var title = document.getElementById('f-title'), sum = document.getElementById('f-excerpt');
    function update() {
      t.textContent = ((mt && mt.value) || (title && title.value) || 'Page title') + ' – Majid Traders';
      d.textContent = (md && md.value) || (sum && sum.value) || 'Add a meta description to control the text Google shows here.';
    }
    [mt, md, title, sum].forEach(function (i) { if (i) i.addEventListener('input', update); });
    update();
  }

  // ------------------------------------------------------------ 7. copy links
  function initCopy() {
    $$('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = $('[data-copy-source]', btn.parentNode);
        var done = function () {
          btn.classList.add('is-copied');
          btn.setAttribute('title', 'Copied!');
          setTimeout(function () { btn.classList.remove('is-copied'); btn.setAttribute('title', 'Copy link'); }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(input.value).then(done);
        } else {
          input.select(); document.execCommand('copy'); done();
        }
      });
    });
  }

  // ------------------------------------------------------------ 8. rich text editor
  function initRichText(textarea) {
    var buttons = [
      ['p', 'Paragraph', 'P'], ['h2', 'Heading', 'H2'], ['h3', 'Sub-heading', 'H3'], ['|'],
      ['bold', 'Bold (Ctrl+B)', '<b>B</b>'], ['italic', 'Italic (Ctrl+I)', '<i>I</i>'], ['|'],
      ['ul', 'Bullet list', '• List'], ['ol', 'Numbered list', '1. List'], ['quote', 'Quote', '❝'], ['|'],
      ['link', 'Add link', 'Link'], ['unlink', 'Remove link', 'Unlink'], ['image', 'Insert image from Media Library', 'Image'], ['|'],
      ['clear', 'Remove formatting', 'Clear'], ['undo', 'Undo', '↶'], ['redo', 'Redo', '↷']
    ];
    var wrap = el('div', { class: 'rte' });
    var bar = el('div', { class: 'rte__toolbar', role: 'toolbar', 'aria-label': 'Text formatting' });
    buttons.forEach(function (b) {
      if (b[0] === '|') { bar.appendChild(el('span', { class: 'rte__sep', 'aria-hidden': 'true' })); return; }
      bar.appendChild(el('button', { type: 'button', 'data-cmd': b[0], title: b[1], 'aria-label': b[1] }, b[2]));
    });
    var area = el('div', { class: 'rte__area', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true' });
    var label = document.querySelector('label[for="' + textarea.id + '"]');
    if (label) { label.id = label.id || textarea.id + '-label'; area.setAttribute('aria-labelledby', label.id); label.addEventListener('click', function () { area.focus(); }); }
    if (textarea.getAttribute('aria-describedby')) area.setAttribute('aria-describedby', textarea.getAttribute('aria-describedby'));
    area.innerHTML = textarea.value || '<p><br></p>';
    wrap.appendChild(bar);
    wrap.appendChild(area);
    textarea.insertAdjacentElement('afterend', wrap);
    textarea.hidden = true;

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* older browsers */ }

    function sync() {
      var html = area.innerHTML;
      if (/^(<p>(<br>)?<\/p>|<br>)?$/.test(html.trim())) html = '';
      if (textarea.value !== html) { textarea.value = html; textarea.dispatchEvent(new Event('input', { bubbles: true })); }
    }
    var saved = null;
    function saveSel() { var s = window.getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) saved = s.getRangeAt(0).cloneRange(); }
    function restoreSel() { area.focus(); if (saved) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(saved); } }

    area.addEventListener('input', sync);
    area.addEventListener('blur', function () { saveSel(); sync(); });
    area.addEventListener('keyup', saveSel);
    area.addEventListener('mouseup', saveSel);
    textarea.form.addEventListener('submit', sync);

    // Paste as plain text (formatting from Word/websites causes messy pages).
    area.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      var html = text.split(/\r?\n\s*\r?\n/).map(function (p) { return '<p>' + esc(p).replace(/\r?\n/g, '<br>') + '</p>'; }).join('');
      document.execCommand('insertHTML', false, html);
    });

    bar.addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); });
    bar.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-cmd]');
      if (!btn) return;
      var cmd = btn.getAttribute('data-cmd');
      area.focus();
      if (saved && cmd !== 'image') restoreSel();
      switch (cmd) {
        case 'p': case 'h2': case 'h3': document.execCommand('formatBlock', false, '<' + cmd + '>'); break;
        case 'quote': document.execCommand('formatBlock', false, '<blockquote>'); break;
        case 'bold': case 'italic': case 'undo': case 'redo': document.execCommand(cmd); break;
        case 'ul': document.execCommand('insertUnorderedList'); break;
        case 'ol': document.execCommand('insertOrderedList'); break;
        case 'unlink': document.execCommand('unlink'); break;
        case 'clear': document.execCommand('removeFormat'); document.execCommand('formatBlock', false, '<p>'); break;
        case 'link':
          var url = window.prompt('Link address — a page on this site (e.g. /contact) or a full https:// address:', 'https://');
          if (!url) break;
          url = url.trim();
          if (!/^(https?:\/\/|\/|mailto:|tel:)/i.test(url)) { window.alert('Please start the link with https://, / , mailto: or tel:'); break; }
          document.execCommand('createLink', false, url);
          break;
        case 'image':
          saveSel();
          MediaPicker.open({ accept: 'image', onSelect: function (items) {
            restoreSel();
            var m = items[0];
            document.execCommand('insertHTML', false, '<p><img src="' + esc(m.medium) + '" alt="' + esc(m.alt) + '"></p>');
            sync();
          } });
          return;
      }
      sync();
      saveSel();
    });
  }

  // ------------------------------------------------------------ 9. media picker
  var MediaPicker = (function () {
    var dialog, opts, state = { page: 1, pages: 1, q: '', selected: [] };

    function build() {
      dialog = el('dialog', { class: 'picker', 'aria-labelledby': 'picker-title' });
      dialog.innerHTML =
        '<div class="picker__head"><h2 id="picker-title">Media Library</h2>' +
        '<button type="button" class="icon-btn" data-picker-close aria-label="Close">' + ICON.close + '</button></div>' +
        '<div class="picker__tabs" role="tablist">' +
        '<button type="button" role="tab" data-picker-tab="library">Choose existing</button>' +
        '<button type="button" role="tab" data-picker-tab="upload">Upload new</button></div>' +
        '<div class="picker__panel" data-picker-panel="library">' +
        '<div class="picker__search"><input type="search" placeholder="Search files…" aria-label="Search files" data-picker-q></div>' +
        '<p class="picker__status" data-picker-status aria-live="polite"></p>' +
        '<ul class="picker__grid" data-picker-grid></ul>' +
        '<div class="picker__more"><button type="button" class="btn btn--ghost btn--small" data-picker-more hidden>Load more</button></div></div>' +
        '<div class="picker__panel" data-picker-panel="upload" hidden><div class="form">' +
        '<label for="picker-file">File</label><input id="picker-file" type="file" data-picker-file>' +
        '<p class="help" data-picker-file-help></p>' +
        '<div data-picker-alt-wrap><label for="picker-alt">Alt text (describe the image) <span class="req">*</span></label>' +
        '<input id="picker-alt" type="text" maxlength="255" data-picker-alt placeholder="e.g. Delivery of school desks in Mardan">' +
        '<p class="help">Required for images: Google and screen readers use it.</p></div>' +
        '<label for="picker-title-input">Title (optional)</label><input id="picker-title-input" type="text" maxlength="190" data-picker-title>' +
        '<p class="field-error" data-picker-error hidden></p>' +
        '<button type="button" class="btn" data-picker-upload>Upload and use</button></div></div>' +
        '<div class="picker__foot"><span class="muted small" data-picker-count></span>' +
        '<button type="button" class="btn btn--ghost" data-picker-close>Cancel</button>' +
        '<button type="button" class="btn" data-picker-use disabled>Use selected</button></div>';
      document.body.appendChild(dialog);

      $$('[data-picker-close]', dialog).forEach(function (b) { b.addEventListener('click', function () { dialog.close(); }); });
      $$('[data-picker-tab]', dialog).forEach(function (b) { b.addEventListener('click', function () { tab(b.getAttribute('data-picker-tab')); }); });
      var timer;
      $('[data-picker-q]', dialog).addEventListener('input', function (e) {
        clearTimeout(timer);
        timer = setTimeout(function () { state.q = e.target.value; load(1); }, 300);
      });
      $('[data-picker-more]', dialog).addEventListener('click', function () { load(state.page + 1, true); });
      $('[data-picker-use]', dialog).addEventListener('click', function () { finish(state.selected); });
      $('[data-picker-upload]', dialog).addEventListener('click', upload);
      $('[data-picker-grid]', dialog).addEventListener('click', function (e) {
        var b = e.target.closest('[data-item]');
        if (!b) return;
        var item = JSON.parse(b.getAttribute('data-item'));
        if (!opts.multiple) { finish([item]); return; }
        var i = state.selected.findIndex(function (s) { return s.id === item.id; });
        if (i === -1) state.selected.push(item); else state.selected.splice(i, 1);
        b.setAttribute('aria-pressed', i === -1 ? 'true' : 'false');
        updateFoot();
      });
    }

    function tab(name) {
      $$('[data-picker-tab]', dialog).forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-picker-tab') === name ? 'true' : 'false'); });
      $$('[data-picker-panel]', dialog).forEach(function (p) { p.hidden = p.getAttribute('data-picker-panel') !== name; });
      $('.picker__foot', dialog).hidden = name !== 'library' || !opts.multiple;
    }

    function updateFoot() {
      var n = state.selected.length;
      $('[data-picker-count]', dialog).textContent = n ? n + ' selected' : 'Click images to select them';
      $('[data-picker-use]', dialog).disabled = n === 0;
    }

    function load(page, append) {
      var grid = $('[data-picker-grid]', dialog), status = $('[data-picker-status]', dialog);
      status.textContent = 'Loading…';
      getJSON('/admin/media?format=json&kind=' + encodeURIComponent(opts.accept === 'any' ? '' : opts.accept) + '&q=' + encodeURIComponent(state.q) + '&page=' + page)
        .then(function (data) {
          state.page = data.page; state.pages = data.pages;
          if (!append) grid.innerHTML = '';
          data.items.forEach(function (m) {
            var li = el('li');
            var picked = state.selected.some(function (s) { return s.id === m.id; });
            var b = el('button', { type: 'button', class: 'picker__item', 'data-item': JSON.stringify(m), 'aria-pressed': picked ? 'true' : 'false', title: m.title });
            b.innerHTML = (m.kind === 'image' ? '<img src="' + esc(m.thumb) + '" alt="' + esc(m.alt) + '" loading="lazy">' : '<span class="file-badge">' + ICON.file + '<span>PDF</span></span>') +
              '<span class="picker__name">' + esc(m.title) + '</span>';
            li.appendChild(b);
            grid.appendChild(li);
          });
          status.textContent = data.total === 0 ? (state.q ? 'Nothing matches your search.' : 'No files yet — use “Upload new”.') : '';
          $('[data-picker-more]', dialog).hidden = data.page >= data.pages;
        })
        .catch(function (err) { status.textContent = err.message; });
    }

    function upload() {
      var file = $('[data-picker-file]', dialog).files[0];
      var errBox = $('[data-picker-error]', dialog), btn = $('[data-picker-upload]', dialog);
      var alt = $('[data-picker-alt]', dialog).value.trim();
      function fail(msg) { errBox.textContent = msg; errBox.hidden = false; }
      errBox.hidden = true;
      if (!file) return fail('Please choose a file first.');
      var problem = fileProblem(file, opts.accept);
      if (problem) return fail(problem);
      if (IMAGE_TYPES.indexOf(file.type) !== -1 && alt.length < 3) return fail('Please describe the image in the alt text box.');
      var fd = new FormData();
      fd.append('action', 'upload');
      fd.append('file', file);
      fd.append('alt_text', alt);
      fd.append('title', $('[data-picker-title]', dialog).value);
      btn.disabled = true; btn.textContent = 'Uploading…';
      postJSON('/admin/media', fd)
        .then(function (data) {
          if (opts.multiple) { state.selected.push(data.item); finish(state.selected); } else finish([data.item]);
        })
        .catch(function (err) { fail(err.message); })
        .then(function () { btn.disabled = false; btn.textContent = 'Upload and use'; });
    }

    function finish(items) {
      dialog.close();
      if (items.length && opts.onSelect) opts.onSelect(items.slice());
    }

    return {
      open: function (o) {
        if (!dialog) build();
        opts = o;
        state = { page: 1, pages: 1, q: '', selected: [] };
        var isDoc = o.accept === 'document', isAny = o.accept === 'any';
        $('#picker-title', dialog).textContent = isAny ? 'Choose a file' : isDoc ? 'Choose a PDF' : (o.multiple ? 'Choose images' : 'Choose an image');
        $('[data-picker-file]', dialog).value = '';
        $('[data-picker-file]', dialog).setAttribute('accept', isAny ? IMAGE_TYPES.concat('application/pdf').join(',') : isDoc ? 'application/pdf' : IMAGE_TYPES.join(','));
        $('[data-picker-file-help]', dialog).textContent = isAny ? 'PDF, JPG, PNG or WebP, up to 8 MB. Images need alt text.' : isDoc ? 'PDF, up to 8 MB.' : 'JPG, PNG or WebP, up to 8 MB.';
        $('[data-picker-alt-wrap]', dialog).hidden = isDoc;
        $('[data-picker-alt]', dialog).value = '';
        $('[data-picker-title]', dialog).value = '';
        $('[data-picker-q]', dialog).value = '';
        $('[data-picker-error]', dialog).hidden = true;
        updateFoot();
        tab(o.tab || 'library');
        load(1);
        dialog.showModal();
      }
    };
  })();

  function initMediaField(box) {
    var input = $('input[type="hidden"]', box), preview = $('[data-preview]', box), remove = $('[data-media-remove]', box);
    var accept = box.getAttribute('data-accept') || 'image';
    function set(m) {
      input.value = m ? m.id : '';
      preview.innerHTML = m
        ? (m.kind === 'image' ? '<img src="' + esc(m.thumb) + '" alt="' + esc(m.alt) + '">' : '<span class="file-badge">' + ICON.file + '<span>PDF</span></span>') + '<span class="media-field__name">' + esc(m.title) + '</span>'
        : '<span class="muted">Nothing chosen</span>';
      remove.hidden = !m;
      changed(input);
    }
    $('[data-media-choose]', box).addEventListener('click', function () { MediaPicker.open({ accept: accept, onSelect: function (i) { set(i[0]); } }); });
    $('[data-media-upload]', box).addEventListener('click', function () { MediaPicker.open({ accept: accept, tab: 'upload', onSelect: function (i) { set(i[0]); } }); });
    remove.addEventListener('click', function () { set(null); });
  }

  function initGallery(box) {
    var list = $('[data-gallery-list]', box), name = box.getAttribute('data-name'), captions = box.hasAttribute('data-captions');
    $('[data-gallery-add]', box).addEventListener('click', function () {
      MediaPicker.open({ accept: 'image', multiple: true, onSelect: function (items) {
        var have = $$('input', list).map(function (i) { return i.value; });
        items.forEach(function (m) {
          if (have.indexOf(String(m.id)) !== -1) return;
          var li = el('li', { class: 'gallery-item' },
            '<input type="hidden" name="' + esc(name) + '[]" value="' + m.id + '">' +
            '<span class="gallery-item__img"><img src="' + esc(m.thumb) + '" alt="' + esc(m.alt) + '">' +
            '<span class="gallery-item__tools">' +
            '<button type="button" data-gallery-move="-1" title="Move left">' + ICON.up + '<span class="visually-hidden">Move earlier</span></button>' +
            '<button type="button" data-gallery-move="1" title="Move right">' + ICON.down + '<span class="visually-hidden">Move later</span></button>' +
            '<button type="button" data-gallery-remove title="Remove">' + ICON.close + '<span class="visually-hidden">Remove</span></button></span></span>' +
            (captions ? '<input type="text" class="gallery-item__caption" name="' + esc(name) + '_caption[]" maxlength="255" placeholder="Caption (optional)" aria-label="Caption">' : ''));
          list.appendChild(li);
        });
        changed(list);
      } });
    });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (!b) return;
      var li = b.closest('li');
      if (b.hasAttribute('data-gallery-remove')) li.remove();
      else if (b.getAttribute('data-gallery-move') === '-1' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
      else if (b.getAttribute('data-gallery-move') === '1' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
      changed(list);
    });
  }

  // ------------------------------------------------------------ 10. uploader
  function initUploader() {
    var box = $('[data-uploader]');
    if (!box) return;
    var input = $('[data-file-input]', box), zone = $('[data-dropzone]', box), queue = $('[data-upload-queue]', box);
    var actions = $('[data-uploader-actions]', box), fallback = $('[data-uploader-fallback]', box);
    var startBtn = $('[data-upload-start]', box);
    fallback.hidden = true;
    input.removeAttribute('name');

    function add(files) {
      Array.prototype.forEach.call(files, function (file) {
        var problem = fileProblem(file, '');
        var isImg = IMAGE_TYPES.indexOf(file.type) !== -1;
        var li = el('li', { class: 'upload-item' + (problem ? ' is-error' : '') });
        li._file = problem ? null : file;
        li.innerHTML =
          '<span class="upload-item__thumb">' + (isImg && !problem ? '<img alt="">' : '<span class="file-badge">' + ICON.file + '<span>' + (file.type === 'application/pdf' ? 'PDF' : '?') + '</span></span>') + '</span>' +
          '<span class="upload-item__body"><strong class="upload-item__name">' + esc(file.name) + '</strong> <span class="muted small">' + formatBytes(file.size) + '</span>' +
          (problem ? '' :
            (isImg ? '<label class="upload-item__label"><span>Alt text (describe the image) <span class="req">*</span></span><input type="text" maxlength="255" data-alt placeholder="e.g. Majid Traders warehouse in Mardan"></label>' : '') +
            '<label class="upload-item__label"><span>Title <span class="muted small">(optional)</span></span><input type="text" maxlength="190" data-title></label>') +
          '<span class="upload-item__status" data-status aria-live="polite">' + esc(problem) + '</span></span>' +
          '<button type="button" class="icon-btn" data-remove title="Remove from list">' + ICON.close + '<span class="visually-hidden">Remove</span></button>';
        if (isImg && !problem) {
          var img = $('img', li);
          // If the browser can't show it, the file is probably not a real image.
          img.addEventListener('error', function () { img.replaceWith(el('span', { class: 'file-badge' }, ICON.file + '<span>?</span>')); });
          img.src = URL.createObjectURL(file);
        }
        queue.appendChild(li);
      });
      queue.hidden = actions.hidden = !queue.children.length;
      input.value = '';
      var firstAlt = $('[data-alt]', queue);
      if (firstAlt) firstAlt.focus();
    }

    input.addEventListener('change', function () { add(input.files); });
    ['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function (e) { e.preventDefault(); zone.classList.remove('is-over'); }); });
    zone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files); });

    queue.addEventListener('click', function (e) {
      var b = e.target.closest('[data-remove]');
      if (!b) return;
      b.closest('li').remove();
      queue.hidden = actions.hidden = !queue.children.length;
    });
    $('[data-upload-clear]', box).addEventListener('click', function () { queue.innerHTML = ''; queue.hidden = actions.hidden = true; });

    startBtn.addEventListener('click', function () {
      var items = $$('.upload-item', queue).filter(function (li) { return li._file && !li.classList.contains('is-done'); });
      var missing = items.filter(function (li) { var a = $('[data-alt]', li); return a && a.value.trim().length < 3; });
      if (missing.length) {
        missing.forEach(function (li) { $('[data-status]', li).textContent = 'Please describe this image in the alt text box.'; li.classList.add('is-error'); });
        $('[data-alt]', missing[0]).focus();
        return;
      }
      if (!items.length) return;
      startBtn.disabled = true;
      var ok = 0, failed = 0;
      (function next(i) {
        if (i >= items.length) {
          startBtn.disabled = false;
          if (!failed) { window.location.reload(); return; }
          var note = el('li', { class: 'upload-note' }, ok + ' uploaded, ' + failed + ' could not be uploaded (see the messages above). <a href="/admin/media">Refresh the list</a>');
          queue.appendChild(note);
          return;
        }
        var li = items[i], status = $('[data-status]', li);
        li.classList.remove('is-error');
        status.textContent = 'Uploading…';
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('file', li._file);
        fd.append('alt_text', $('[data-alt]', li) ? $('[data-alt]', li).value : '');
        fd.append('title', $('[data-title]', li).value);
        postJSON('/admin/media', fd)
          .then(function () { ok++; li.classList.add('is-done'); status.textContent = 'Uploaded ✓'; })
          .catch(function (err) { failed++; li.classList.add('is-error'); status.textContent = err.message; })
          .then(function () { next(i + 1); });
      })(0);
    });
  }

  // ------------------------------------------------------------ 11. counter rows
  function initCounterRows() {
    var list = $('[data-counter-list]');
    if (!list) return;
    var tpl = $('[data-counter-template]');
    $('[data-counter-add]').addEventListener('click', function () {
      var key = 'n' + Date.now();
      var wrap = document.createElement('ul');
      wrap.innerHTML = tpl.innerHTML.replace(/__KEY__/g, key);
      var row = wrap.firstElementChild;
      list.appendChild(row);
      $('input[name$="[value]"]', row).focus();
    });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('[data-row-move]');
      if (!b) return;
      var row = b.closest('[data-counter-row]');
      if (b.getAttribute('data-row-move') === '-1' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      else if (b.getAttribute('data-row-move') === '1' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      changed(list);
      b.focus();
    });
    list.addEventListener('change', function (e) {
      if (e.target.matches('[data-row-remove]')) e.target.closest('[data-counter-row]').classList.toggle('is-removed', e.target.checked);
    });
    $$('[data-row-remove]', list).forEach(function (c) { if (c.checked) c.closest('[data-counter-row]').classList.add('is-removed'); });
  }
})();
