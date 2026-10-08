/* Admin behaviour. Vanilla JS, no build step, no inline handlers (CSP). */
(function () {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  async function post(url, data) {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    if (!(data instanceof FormData)) body.append('_token', csrf()); else if (!body.has('_token')) body.append('_token', csrf());
    const res = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json', 'X-CSRF-Token': csrf() }, credentials: 'same-origin' });
    let json = null;
    try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok) throw new Error((json && json.error) || 'Request failed (' + res.status + ')');
    return json;
  }
  window.adminPost = post;

  function toast(message, type = 'success') {
    let host = $('#toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'toast-host';
      host.style.cssText = 'position:fixed;right:1rem;bottom:1rem;z-index:100;display:grid;gap:.5rem;max-width:360px';
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = 'alert alert-' + type;
    el.style.boxShadow = 'var(--shadow-lg)';
    el.style.margin = '0';
    el.textContent = message;
    host.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  }
  window.adminToast = toast;

  // --- shell -------------------------------------------------------------
  document.addEventListener('click', (e) => {
    const navBtn = e.target.closest('[data-toggle-nav]');
    if (navBtn) { document.body.classList.toggle('nav-open'); return; }
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar')) document.body.classList.remove('nav-open');

    const dd = e.target.closest('[data-dropdown-toggle]');
    $$('[data-dropdown].open').forEach((d) => { if (!dd || d !== dd.closest('[data-dropdown]')) d.classList.remove('open'); });
    if (dd) dd.closest('[data-dropdown]').classList.toggle('open');

    if (e.target.closest('[data-theme-toggle]')) {
      const dark = document.documentElement.dataset.theme !== 'dark';
      document.documentElement.dataset.theme = dark ? 'dark' : 'light';
      try { localStorage.setItem('admin-theme', dark ? 'dark' : 'light'); } catch (err) { /* ignore */ }
    }

    const closeBtn = e.target.closest('[data-close-dialog]');
    if (closeBtn) closeBtn.closest('dialog').close();

    const opener = e.target.closest('[data-open-dialog]');
    if (opener) { e.preventDefault(); const d = document.getElementById(opener.dataset.openDialog); if (d) d.showModal(); }

    const copy = e.target.closest('[data-copy]');
    if (copy && navigator.clipboard) { navigator.clipboard.writeText(copy.dataset.copy).then(() => toast('Copied')); }
  });

  document.addEventListener('change', (e) => {
    if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
  });

  // Confirm destructive actions: <form data-confirm="Delete this page?">
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { e.preventDefault(); return; }
    const btn = form.querySelector('button[type="submit"]:not([formaction])');
    if (btn && !form.dataset.noLock) setTimeout(() => { btn.disabled = true; }, 0);
  });

  // --- tabs ----------------------------------------------------------------
  $$('[data-tabs]').forEach((tabs) => {
    const key = 'tab:' + location.pathname + ':' + (tabs.dataset.tabs || '');
    const buttons = $$('[role="tab"]', tabs);
    const show = (id) => {
      buttons.forEach((b) => {
        const on = b.dataset.tab === id;
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        const panel = document.getElementById(b.dataset.tab);
        if (panel) panel.hidden = !on;
      });
      try { sessionStorage.setItem(key, id); } catch (err) { /* ignore */ }
    };
    buttons.forEach((b) => b.addEventListener('click', () => show(b.dataset.tab)));
    let initial = buttons[0] && buttons[0].dataset.tab;
    const hash = location.hash.replace('#', '');
    try { initial = sessionStorage.getItem(key) || initial; } catch (err) { /* ignore */ }
    if (hash && buttons.some((b) => b.dataset.tab === hash)) initial = hash;
    // A tab with an error inside opens first.
    const errPanel = $$('.has-error', document).map((f) => f.closest('.tab-panel')).find(Boolean);
    if (errPanel) initial = errPanel.id;
    if (initial && buttons.some((b) => b.dataset.tab === initial)) show(initial);
  });

  // --- slug from title -------------------------------------------------------
  function slugify(s) {
    return s.toString().normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
      .replace(/&/g, ' and ').replace(/[^a-z0-9\/\s-]/g, '').replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
  }
  $$('[data-slug-source]').forEach((src) => {
    const target = document.getElementById(src.dataset.slugSource);
    if (!target) return;
    let touched = target.value !== '';
    target.addEventListener('input', () => { touched = target.value !== ''; });
    src.addEventListener('input', () => { if (!touched) target.value = slugify(src.value); });
  });

  // --- character counters & SEO preview ----------------------------------------
  $$('[data-count]').forEach((input) => {
    const out = document.createElement('span');
    out.className = 'help';
    input.insertAdjacentElement('afterend', out);
    const max = parseInt(input.dataset.count, 10);
    const upd = () => { const n = input.value.length; out.textContent = n + ' / ' + max + ' characters'; out.style.color = n > max ? 'var(--danger)' : ''; };
    input.addEventListener('input', upd); upd();
  });
  $$('[data-seo-preview]').forEach((box) => {
    const t = document.getElementById(box.dataset.title), d = document.getElementById(box.dataset.desc);
    const fallbackT = box.dataset.fallbackTitle || '', fallbackD = box.dataset.fallbackDesc || '';
    const upd = () => {
      $('.t', box).textContent = (t && t.value) || fallbackT;
      $('.d', box).textContent = (d && d.value) || fallbackD;
    };
    [t, d].forEach((el) => el && el.addEventListener('input', upd)); upd();
  });

  // --- colour fields: picker <-> text -------------------------------------------
  document.addEventListener('input', (e) => {
    const field = e.target.closest('.color-field');
    if (!field) return;
    const picker = $('input[type=color]', field), text = $('input[type=text]', field);
    if (e.target === picker) text.value = picker.value;
    else if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value;
  });

  // --- conditional fields (showWhen) -----------------------------------------------
  function applyShowWhen(root = document) {
    $$('[data-show-when]', root).forEach((el) => {
      let rule;
      try { rule = JSON.parse(el.dataset.showWhen); } catch (e) { return; }
      const scope = el.closest('[data-field-scope]') || el.closest('form');
      const ok = Object.entries(rule).every(([name, values]) => {
        const input = scope.querySelector('[data-field-name="' + name + '"]');
        if (!input) return true;
        const v = input.type === 'checkbox' ? (input.checked ? '1' : '') : input.value;
        return values.map(String).includes(v);
      });
      el.hidden = !ok;
    });
  }
  document.addEventListener('change', (e) => { if (e.target.matches('[data-field-name]')) applyShowWhen(); });
  applyShowWhen();

  // --- repeaters --------------------------------------------------------------------
  function renumber(rep) {
    const base = rep.dataset.repeater;
    $$(':scope > [data-repeater-items] > [data-repeater-item]', rep).forEach((item, i) => {
      $$('[data-name]', item).forEach((input) => {
        if (input.closest('[data-repeater-item]') !== item) return;
        input.name = base + '[' + i + '][' + input.dataset.name + ']' + (input.dataset.multiple ? '[]' : '');
      });
      const title = $('[data-item-index]', item);
      if (title) title.textContent = '#' + (i + 1);
    });
  }
  document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-repeater-add]');
    if (add) {
      const rep = add.closest('[data-repeater]');
      const tpl = $(':scope > template', rep);
      const items = $(':scope > [data-repeater-items]', rep);
      const max = parseInt(rep.dataset.max || '50', 10);
      if (items.children.length >= max) { toast('Maximum of ' + max + ' items', 'warning'); return; }
      items.appendChild(tpl.content.cloneNode(true));
      renumber(rep); applyShowWhen(rep); initRte(rep);
      return;
    }
    const act = e.target.closest('[data-repeater-action]');
    if (act) {
      const item = act.closest('[data-repeater-item]'), rep = item.closest('[data-repeater]');
      const action = act.dataset.repeaterAction;
      if (action === 'remove') item.remove();
      else if (action === 'up' && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
      else if (action === 'down' && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
      else if (action === 'duplicate') {
        const clone = item.cloneNode(true);
        $$('[data-rte]', clone).forEach((r) => { r.dataset.init = ''; });
        item.after(clone); initRte(clone);
      }
      renumber(rep);
    }
  });
  $$('[data-repeater]').forEach(renumber);

  // --- icon picker -------------------------------------------------------------------
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-icon-choice]');
    if (!b) return;
    const wrap = b.closest('[data-icon-field]');
    $('input', wrap).value = b.dataset.iconChoice;
    $$('[data-icon-choice]', wrap).forEach((x) => x.classList.toggle('selected', x === b));
    const cur = $('[data-icon-current]', wrap);
    if (cur) cur.innerHTML = b.dataset.iconChoice ? b.innerHTML : '—';
    const det = b.closest('details'); if (det) det.open = false;
  });

  // --- rich text editor ----------------------------------------------------------------
  function initRte(root = document) {
    $$('[data-rte]', root).forEach((wrap) => {
      if (wrap.dataset.init === '1') return;
      wrap.dataset.init = '1';
      const textarea = $('textarea', wrap), editor = $('.rte', wrap);
      editor.innerHTML = textarea.value;
      const sync = () => { textarea.value = editor.innerHTML; };
      editor.addEventListener('input', sync);
      editor.addEventListener('blur', sync);
      editor.addEventListener('paste', (e) => {
        e.preventDefault();
        const html = (e.clipboardData || window.clipboardData).getData('text/html');
        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        if (html) {
          const tmp = document.createElement('div');
          tmp.innerHTML = html;
          $$('script,style,meta,link,iframe,object', tmp).forEach((n) => n.remove());
          $$('*', tmp).forEach((n) => { [...n.attributes].forEach((a) => { if (!['href', 'src', 'alt'].includes(a.name)) n.removeAttribute(a.name); }); });
          document.execCommand('insertHTML', false, tmp.innerHTML);
        } else document.execCommand('insertText', false, text);
      });
      $$('[data-cmd]', wrap).forEach((btn) => btn.addEventListener('click', (e) => {
        e.preventDefault();
        editor.focus();
        const cmd = btn.dataset.cmd;
        if (cmd === 'createLink') {
          const url = prompt('Link URL (https://…, /path, mailto:)');
          if (url && !/^\s*(javascript|data|vbscript):/i.test(url)) document.execCommand('createLink', false, url.trim());
        } else if (cmd === 'insertImage') {
          openMediaPicker((m) => { document.execCommand('insertHTML', false, '<img src="' + m.url.replace(/"/g, '') + '" alt="' + (m.alt || '').replace(/"/g, '') + '">'); sync(); });
        } else if (cmd === 'source') {
          if (editor.dataset.source === '1') { editor.innerHTML = editor.textContent; editor.dataset.source = ''; editor.style.fontFamily = ''; editor.style.whiteSpace = ''; }
          else { editor.textContent = editor.innerHTML; editor.dataset.source = '1'; editor.style.fontFamily = 'var(--mono)'; editor.style.whiteSpace = 'pre-wrap'; }
        } else {
          document.execCommand(cmd, false, btn.dataset.value || null);
        }
        if (editor.dataset.source !== '1') sync(); else textarea.value = editor.textContent;
      }));
      const block = $('[data-block]', wrap);
      if (block) block.addEventListener('change', () => { editor.focus(); document.execCommand('formatBlock', false, block.value); block.value = ''; sync(); });
      const form = wrap.closest('form');
      if (form) form.addEventListener('submit', () => { textarea.value = editor.dataset.source === '1' ? editor.textContent : editor.innerHTML; });
    });
  }
  window.initRte = initRte;
  initRte();

  // --- media picker --------------------------------------------------------------------
  const dialog = $('#media-dialog');
  let pickCallback = null;
  let pickKind = '';
  async function loadMedia(q = '') {
    const grid = $('[data-media-grid]', dialog);
    grid.innerHTML = '<p class="muted">Loading…</p>';
    try {
      const res = await fetch('/admin/media/picker?q=' + encodeURIComponent(q) + '&kind=' + encodeURIComponent(pickKind), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      grid.innerHTML = '';
      if (!data.items || !data.items.length) { grid.innerHTML = '<p class="muted">No media yet. Upload something.</p>'; return; }
      data.items.forEach((m) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'media-tile';
        if (m.kind === 'IMAGE') { const img = document.createElement('img'); img.src = m.url; img.alt = ''; img.loading = 'lazy'; b.appendChild(img); }
        else { const ph = document.createElement('div'); ph.className = 'file-ph'; ph.textContent = (m.mime || 'file').split('/').pop().toUpperCase(); b.appendChild(ph); }
        const cap = document.createElement('div'); cap.className = 'cap truncate'; cap.textContent = m.filename; b.appendChild(cap);
        b.addEventListener('click', () => { if (pickCallback) pickCallback(m); dialog.close(); });
        grid.appendChild(b);
      });
    } catch (e) { grid.innerHTML = '<p class="muted">Could not load media.</p>'; }
  }
  async function uploadFiles(files) {
    const status = $('[data-media-status]', dialog);
    for (const file of files) {
      status.textContent = 'Uploading ' + file.name + '…';
      const fd = new FormData(); fd.append('file', file); fd.append('_token', csrf());
      try { await post('/admin/media/upload', fd); } catch (e) { toast(file.name + ': ' + e.message, 'error'); }
    }
    status.textContent = '';
    loadMedia($('[data-media-search]', dialog).value);
  }
  function openMediaPicker(cb, kind = '') {
    if (!dialog) return;
    pickCallback = cb; pickKind = kind;
    dialog.showModal();
    loadMedia();
  }
  window.openMediaPicker = openMediaPicker;
  if (dialog) {
    let t;
    $('[data-media-search]', dialog).addEventListener('input', (e) => { clearTimeout(t); t = setTimeout(() => loadMedia(e.target.value), 250); });
    $('[data-media-upload]', dialog).addEventListener('change', (e) => { uploadFiles(e.target.files); e.target.value = ''; });
    const dz = $('[data-media-drop]', dialog);
    ['dragenter', 'dragover'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('over'); }));
    dz.addEventListener('drop', (e) => uploadFiles(e.dataTransfer.files));
  }
  document.addEventListener('click', (e) => {
    const pick = e.target.closest('[data-media-pick]');
    if (pick) {
      e.preventDefault();
      const wrap = pick.closest('.media-input');
      openMediaPicker((m) => {
        const input = $('input[type=hidden], input[type=text]', wrap);
        input.value = m.url;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        const thumb = $('.thumb', wrap);
        if (thumb) { thumb.src = m.kind === 'IMAGE' ? m.url : ''; thumb.hidden = m.kind !== 'IMAGE'; }
        const alt = wrap.dataset.altTarget && wrap.closest('[data-field-scope], form').querySelector('[data-field-name="' + wrap.dataset.altTarget + '"]');
        if (alt && !alt.value && m.alt) alt.value = m.alt;
      }, pick.dataset.mediaPick || '');
    }
    const clear = e.target.closest('[data-media-clear]');
    if (clear) {
      const wrap = clear.closest('.media-input');
      $('input', wrap).value = '';
      const thumb = $('.thumb', wrap); if (thumb) thumb.hidden = true;
    }
  });

  // --- sortable lists (sections, products, menu items) -------------------------------------
  $$('[data-sortable]').forEach((list) => {
    let dragged = null;
    list.addEventListener('dragstart', (e) => {
      const item = e.target.closest('[data-sort-id]');
      if (!item || item.parentNode !== list) return;
      dragged = item; item.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', item.dataset.sortId);
    });
    list.addEventListener('dragend', () => {
      if (!dragged) return;
      dragged.classList.remove('dragging'); dragged = null;
      $$('.drop-target', list).forEach((x) => x.classList.remove('drop-target'));
      persist();
    });
    list.addEventListener('dragover', (e) => {
      if (!dragged) return;
      e.preventDefault();
      const over = e.target.closest('[data-sort-id]');
      if (!over || over === dragged || over.parentNode !== list) return;
      const rect = over.getBoundingClientRect();
      const after = (e.clientY - rect.top) > rect.height / 2;
      list.insertBefore(dragged, after ? over.nextSibling : over);
    });
    // Keyboard: Alt+ArrowUp / Alt+ArrowDown on the handle.
    list.addEventListener('keydown', (e) => {
      if (!e.altKey || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
      const item = e.target.closest('[data-sort-id]');
      if (!item) return;
      e.preventDefault();
      if (e.key === 'ArrowUp' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
      if (e.key === 'ArrowDown' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
      e.target.focus();
      persist();
    });
    async function persist() {
      const ids = $$(':scope > [data-sort-id]', list).map((x) => x.dataset.sortId);
      const input = list.dataset.sortInput && document.getElementById(list.dataset.sortInput);
      if (input) { input.value = ids.join(','); return; }
      if (!list.dataset.sortable) return;
      try { await post(list.dataset.sortable, { order: ids.join(',') }); toast('Order saved'); }
      catch (err) { toast(err.message, 'error'); }
    }
  });

  // --- kanban ------------------------------------------------------------------------------
  const board = $('[data-kanban]');
  if (board) {
    let card = null;
    board.addEventListener('dragstart', (e) => { card = e.target.closest('.kanban-card'); if (card) e.dataTransfer.setData('text/plain', card.dataset.id); });
    board.addEventListener('dragover', (e) => {
      const list = e.target.closest('.kanban-list');
      if (!list || !card) return;
      e.preventDefault(); list.classList.add('over');
      const over = e.target.closest('.kanban-card');
      if (over && over !== card) { const r = over.getBoundingClientRect(); list.insertBefore(card, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over); }
      else if (!over) list.appendChild(card);
    });
    board.addEventListener('dragleave', (e) => { const list = e.target.closest('.kanban-list'); if (list) list.classList.remove('over'); });
    board.addEventListener('drop', async (e) => {
      const list = e.target.closest('.kanban-list');
      if (!list || !card) return;
      e.preventDefault(); list.classList.remove('over');
      const ids = $$('.kanban-card', list).map((c) => c.dataset.id);
      const moved = card; card = null;
      try {
        await post('/admin/pipeline/move', { id: moved.dataset.id, status: list.dataset.status, order: ids.join(',') });
        $$('.kanban-col').forEach((col) => { $('[data-count]', col).textContent = $$('.kanban-card', col).length; });
      } catch (err) { toast(err.message, 'error'); location.reload(); }
    });
  }

  // --- select all checkboxes for bulk actions -------------------------------------------------
  $$('[data-check-all]').forEach((all) => all.addEventListener('change', () => {
    $$('input[type=checkbox][name="' + all.dataset.checkAll + '"]').forEach((c) => { c.checked = all.checked; });
  }));

  // --- preview device switch --------------------------------------------------------------------
  $$('[data-device-switch]').forEach((sw) => {
    const frame = document.getElementById(sw.dataset.deviceSwitch);
    $$('button', sw).forEach((b) => b.addEventListener('click', () => {
      $$('button', sw).forEach((x) => x.classList.toggle('active', x === b));
      frame.style.width = b.dataset.width; frame.style.margin = '0 auto'; frame.style.display = 'block';
    }));
  });

  // --- unsaved-changes guard for long forms ------------------------------------------------------
  $$('form[data-guard]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });
})();
