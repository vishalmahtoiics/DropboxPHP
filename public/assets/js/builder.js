/*
 * Visual builder — the editor around the canvas iframe.
 *
 * Holds the layout (a tree of containers and widgets), the panel of widgets
 * and settings, the navigator, undo/redo, the clipboard and saving. Every
 * change is sent to the server, which renders the layout with the same code
 * the public site uses and hands back HTML + CSS for the canvas — so the
 * canvas is always exactly what visitors will see. Vanilla JS, no build step,
 * no inline handlers (CSP).
 */
(function () {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const h = (tag, attrs = {}, ...kids) => {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k === 'html') n.innerHTML = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
      else n.setAttribute(k, v === true ? '' : v);
    }
    kids.flat().forEach((c) => { if (c !== null && c !== undefined && c !== false) n.append(c); });
    return n;
  };

  const cfg = JSON.parse($('#bx-config').textContent);
  const DEFS = cfg.elements;
  const ICON = cfg.icons;
  const icon = (name) => ICON[name] || '';
  const SUFFIX = { desktop: '', tablet: '_tablet', mobile: '_mobile' };
  const DEVICES = ['desktop', 'tablet', 'mobile'];
  const UNITS = ['px', '%', 'em', 'rem', 'vh', 'vw'];
  const csrf = () => ($('meta[name="csrf-token"]') || {}).content || '';
  const toast = (m, t) => (window.adminToast ? window.adminToast(m, t) : window.alert(m));
  const clone = (v) => (v === undefined ? undefined : JSON.parse(JSON.stringify(v)));
  const frame = $('[data-bx-frame]');
  const app = $('.bx-app');

  // Control lookup: type -> key -> control
  const CONTROLS = {};
  for (const [type, def] of Object.entries(DEFS)) {
    CONTROLS[type] = {};
    for (const sections of Object.values(def.tabs)) {
      for (const sec of sections) for (const c of sec.controls) if (c.key) CONTROLS[type][c.key] = c;
    }
  }

  // --- state --------------------------------------------------------------------------------
  const docFrom = (layout) => ({
    version: 1,
    elements: Array.isArray(layout && layout.elements) ? layout.elements : [],
    settings: layout && layout.settings && typeof layout.settings === 'object' && !Array.isArray(layout.settings) ? layout.settings : {},
  });
  let doc = docFrom(cfg.layout);
  let selectedId = null;
  let device = 'desktop';
  let dirty = false;
  let saving = false;
  let canvasReady = false;
  let activeTab = 'content';
  let frameScale = 1;
  const openSections = {};
  const collapsedNav = {};

  // --- server ---------------------------------------------------------------------------------
  async function api(url, body, method) {
    const opts = { method: method || (body === undefined ? 'GET' : 'POST'), credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'fetch' } };
    if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    const res = await fetch(url, opts);
    let json = null;
    try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok || !json || json.ok === false) {
      if (res.status === 419) throw new Error('Your session expired. Reload the page (your last saved draft is kept).');
      throw new Error((json && json.error) || 'Request failed (' + res.status + ')');
    }
    return json;
  }
  const post = (msg) => { if (frame.contentWindow) frame.contentWindow.postMessage(msg, location.origin); };

  // --- tree helpers -------------------------------------------------------------------------
  function newId() {
    const a = new Uint8Array(4);
    crypto.getRandomValues(a);
    return Array.from(a, (b) => b.toString(16).padStart(2, '0')).join('');
  }
  function walk(list, fn, parent = null) {
    for (let i = 0; i < list.length; i++) {
      if (fn(list[i], list, i, parent) === false) return false;
      if (list[i].children && walk(list[i].children, fn, list[i]) === false) return false;
    }
    return true;
  }
  function locate(id) {
    let found = null;
    if (!id) return null;
    walk(doc.elements, (el, list, index, parent) => { if (el.id === id) { found = { el, list, index, parent }; return false; } return true; });
    return found;
  }
  const listOf = (parentId) => (parentId ? ((locate(parentId) || {}).el || {}).children || null : doc.elements);
  const contains = (ancestorId, id) => { const f = locate(ancestorId); let hit = false; if (f && f.el.children) walk(f.el.children, (el) => { if (el.id === id) { hit = true; return false; } return true; }); return hit; };
  const reid = (el) => { el.id = newId(); (el.children || []).forEach(reid); return el; };

  function createElement(type) {
    const el = { id: newId(), type, settings: {} };
    if (type === 'container') el.children = [];
    if (type.startsWith('block.')) {
      for (const sec of DEFS[type].tabs.content || []) for (const c of sec.controls) if (c.key) el.settings[c.key] = clone(c.default);
    }
    return el;
  }

  const STRUCTURES = [[100], [50, 50], [33, 33, 33], [25, 25, 25, 25], [33, 67], [67, 33], [25, 75], [75, 25], [25, 50, 25], [20, 20, 20, 20, 20]];
  function buildStructure(cols) {
    if (cols.length === 1) return createElement('container');
    const parent = createElement('container');
    parent.settings = { direction: 'row', direction_mobile: 'column' };
    const equal = cols.every((c) => c === cols[0]);
    cols.forEach((w) => {
      const c = createElement('container');
      if (!equal) { c.settings.width = { size: w, unit: '%' }; c.settings.width_mobile = { size: 100, unit: '%' }; }
      parent.children.push(c);
    });
    return parent;
  }

  /** Insert at {parent, index}; a widget dropped at the top level gets a container. */
  function insertAt(el, target) {
    const parentId = target && target.parent ? target.parent : null;
    let list = listOf(parentId) || doc.elements;
    let index = target && Number.isInteger(target.index) ? target.index : list.length;
    index = Math.max(0, Math.min(index, list.length));
    if (!parentId && el.type !== 'container') {
      const wrap = createElement('container');
      wrap.children.push(el);
      el = wrap;
      list = doc.elements;
    }
    list.splice(index, 0, el);
  }

  /** Where a new element goes when added by click rather than drag. */
  function clickTarget() {
    const f = locate(selectedId);
    if (!f) return null;
    if (f.el.type === 'container') return { parent: f.el.id, index: f.el.children.length };
    return { parent: f.parent ? f.parent.id : null, index: f.index + 1 };
  }

  // --- history -------------------------------------------------------------------------------
  const hist = { stack: [JSON.stringify(doc)], index: 0, lastKey: null, lastTime: 0 };
  function pushHistory(key) {
    const snap = JSON.stringify(doc);
    if (snap === hist.stack[hist.index]) return;
    const now = Date.now();
    if (key && key === hist.lastKey && now - hist.lastTime < 900 && hist.index === hist.stack.length - 1 && hist.index > 0) {
      hist.stack[hist.index] = snap;
    } else {
      hist.stack = hist.stack.slice(0, hist.index + 1);
      hist.stack.push(snap);
      if (hist.stack.length > 150) hist.stack.shift();
      hist.index = hist.stack.length - 1;
    }
    hist.lastKey = key || null;
    hist.lastTime = now;
    updateUndo();
  }
  function travel(step) {
    const to = hist.index + step;
    if (to < 0 || to >= hist.stack.length) return;
    hist.index = to;
    hist.lastKey = null;
    doc = JSON.parse(hist.stack[to]);
    if (!locate(selectedId)) selectedId = null;
    setDirty(true);
    updateUndo();
    refreshPanels();
    scheduleRender(0);
  }
  function updateUndo() {
    $('[data-bx-undo]').disabled = hist.index <= 0;
    $('[data-bx-redo]').disabled = hist.index >= hist.stack.length - 1;
  }

  /** Every edit goes through here. */
  function change(fn, opts = {}) {
    fn();
    pushHistory(opts.key);
    setDirty(true);
    scheduleRender(opts.key ? 160 : 40);
    renderNavigator();
    if (opts.panel) renderSettings();
  }

  // --- rendering the canvas ------------------------------------------------------------------
  let renderTimer = null;
  let renderSeq = 0;
  function scheduleRender(delay = 120) { clearTimeout(renderTimer); renderTimer = setTimeout(doRender, delay); }
  async function doRender() {
    if (!canvasReady) return;
    const seq = ++renderSeq;
    try {
      const res = await api(cfg.urls.render, { layout: doc });
      if (seq !== renderSeq) return;
      post({ type: 'bx:render', html: res.html, css: res.css, selected: selectedId });
    } catch (e) {
      toast('Could not refresh the preview: ' + e.message, 'error');
    }
  }

  // --- selection & panels -------------------------------------------------------------------
  function showView(name) {
    $$('[data-bx-view]').forEach((v) => { v.hidden = v.dataset.bxView !== name; });
  }
  function select(id, opts = {}) {
    selectedId = id && locate(id) ? id : null;
    if (!opts.fromCanvas) post({ type: 'bx:select', id: selectedId, scroll: !!opts.scroll });
    if (selectedId && !opts.keepView) { showView('settings'); renderSettings(); }
    if (!selectedId) showView('widgets');
    highlightNav();
  }
  function refreshPanels() {
    if (selectedId && !$('[data-bx-view="settings"]').hidden) renderSettings();
    renderNavigator();
  }

  // --- widgets panel ------------------------------------------------------------------------
  const LAYOUT_TILES = [
    { label: 'Container', icon: 'layout', source: { kind: 'new', type: 'container' }, container: true },
    { label: '2 columns', icon: 'columns', source: { kind: 'structure', cols: [50, 50] }, container: true },
    { label: '3 columns', icon: 'columns', source: { kind: 'structure', cols: [33, 33, 33] }, container: true },
    { label: '4 columns', icon: 'grid', source: { kind: 'structure', cols: [25, 25, 25, 25] }, container: true },
  ];
  function tile(label, ic, source, isContainer) {
    const b = h('button', { type: 'button', class: 'bx-wtile', draggable: 'true', title: label }, h('span', { class: 'bx-wtile-icon', html: icon(ic) }), h('span', { text: label }));
    b.addEventListener('dragstart', (e) => {
      e.dataTransfer.effectAllowed = 'copy';
      e.dataTransfer.setData('text/bx', JSON.stringify(source));
      e.dataTransfer.setData(isContainer ? 'text/bx-container' : 'text/bx-widget', '1');
      app.classList.add('is-dragging');
    });
    b.addEventListener('dragend', () => app.classList.remove('is-dragging'));
    b.addEventListener('click', () => drop(source, clickTarget()));
    return b;
  }
  function renderWidgets() {
    const host = $('[data-bx-widgets]');
    const q = ($('[data-bx-search]').value || '').trim().toLowerCase();
    host.innerHTML = '';
    for (const [cat, label] of Object.entries(cfg.categories)) {
      if (cat === 'product' && !cfg.productContext) continue;
      const tiles = [];
      if (cat === 'layout') {
        LAYOUT_TILES.forEach((t) => { if (!q || t.label.toLowerCase().includes(q) || 'container column row section'.includes(q)) tiles.push(tile(t.label, t.icon, t.source, t.container)); });
      } else {
        for (const [type, d] of Object.entries(DEFS)) {
          if (d.category !== cat) continue;
          if (d.code && !cfg.canCode) continue;
          if (q && !(d.label + ' ' + d.keywords + ' ' + type).toLowerCase().includes(q)) continue;
          tiles.push(tile(d.label, d.icon, { kind: 'new', type }, false));
        }
      }
      if (!tiles.length) continue;
      const group = h('details', { class: 'bx-wgroup' }, h('summary', { text: label }), h('div', { class: 'bx-wgrid' }, tiles));
      group.open = q !== '' || cat !== 'sections';
      host.append(group);
    }
    if (!host.children.length) host.append(h('p', { class: 'bx-hint', text: 'No widget matches “' + q + '”.' }));
  }

  let templatesLoaded = false;
  async function loadTemplates(force) {
    if (templatesLoaded && !force) return;
    const host = $('[data-bx-templates]');
    try {
      const res = await api(cfg.urls.templates);
      templatesLoaded = true;
      host.innerHTML = '';
      const items = res.items.filter((t) => t.kind !== 'product' || cfg.productContext);
      if (!items.length) host.append(h('p', { class: 'bx-hint', text: 'No templates yet.' }));
      items.forEach((t) => host.append(h('div', { class: 'bx-template' },
        h('span', {}, h('strong', { text: t.name }), h('span', { class: 'bx-badge', text: t.kind })),
        h('button', { type: 'button', class: 'btn btn-secondary btn-sm', text: 'Insert', onclick: () => insertTemplate(t.id, clickTarget()) }))));
    } catch (e) {
      host.innerHTML = '';
      host.append(h('p', { class: 'bx-hint', text: e.message }));
    }
    host.append(h('button', { type: 'button', class: 'btn btn-ghost btn-sm bx-mt', text: 'Save this whole page as a template', onclick: () => openSaveTemplate(null) }));
  }

  // --- adding, moving, removing ---------------------------------------------------------------
  function drop(source, target) {
    if (!source) return;
    if (source.kind === 'new') {
      if (!DEFS[source.type]) return;
      const el = createElement(source.type);
      change(() => insertAt(el, target));
      select(el.id, { scroll: true });
    } else if (source.kind === 'structure') {
      const el = buildStructure(source.cols);
      change(() => insertAt(el, target));
      select(el.id, { scroll: true });
    } else if (source.kind === 'move') {
      moveEl(source.id, target);
    }
  }
  function moveEl(id, target) {
    const f = locate(id);
    if (!f || !target) return;
    if (target.parent && (target.parent === id || contains(id, target.parent))) return;
    change(() => {
      const dest = target.parent ? listOf(target.parent) : doc.elements;
      let index = target.index;
      if (f.list === dest && f.index < index) index--;
      f.list.splice(f.index, 1);
      insertAt(f.el, { parent: target.parent, index });
    });
    select(id, { keepView: true });
  }
  function duplicate(id) {
    const f = locate(id);
    if (!f) return;
    const copy = reid(clone(f.el));
    change(() => f.list.splice(f.index + 1, 0, copy));
    select(copy.id);
  }
  function remove(id) {
    const f = locate(id);
    if (!f) return;
    change(() => f.list.splice(f.index, 1));
    select(f.parent ? f.parent.id : null);
  }
  async function insertTemplate(templateId, target) {
    try {
      const res = await api('/admin/builder/templates/' + encodeURIComponent(templateId) + '/elements');
      if (!res.elements.length) { toast('That template is empty.', 'error'); return; }
      const t = target || { parent: null, index: doc.elements.length };
      change(() => res.elements.forEach((el, i) => insertAt(el, { parent: t.parent, index: (t.index ?? 0) + i })));
      select(res.elements[0].id, { scroll: true });
      toast('Template inserted.');
    } catch (e) { toast(e.message, 'error'); }
  }

  // Clipboard: kept in this browser so it works across pages.
  function copyEl(id) {
    const f = locate(id);
    if (!f) return;
    try { localStorage.setItem('bx-clipboard', JSON.stringify(f.el)); toast('Copied ' + DEFS[f.el.type].label.toLowerCase() + '.'); } catch (e) { toast('Could not copy.', 'error'); }
  }
  function paste() {
    let el = null;
    try { el = JSON.parse(localStorage.getItem('bx-clipboard') || 'null'); } catch (e) { el = null; }
    if (!el || !DEFS[el.type]) { toast('Nothing to paste.', 'error'); return; }
    reid(el);
    const f = locate(selectedId);
    let target = null;
    if (f) target = f.el.type === 'container' && el.type !== 'container' ? { parent: f.el.id, index: f.el.children.length } : { parent: f.parent ? f.parent.id : null, index: f.index + 1 };
    change(() => insertAt(el, target));
    select(el.id, { scroll: true });
  }

  // --- values ---------------------------------------------------------------------------------
  const isEmpty = (c, v) => v === undefined || v === null || v === ''
    || (c.type === 'slider' && (!v || v.size === '' || v.size === null || v.size === undefined))
    || (c.type === 'dimensions' && (!v || ['top', 'right', 'bottom', 'left'].every((s) => v[s] === '' || v[s] === undefined)));

  /** Accessor for an element's settings at the current device. */
  function elementAccess(el) {
    const keyOf = (c, dev) => c.key + (c.responsive ? SUFFIX[dev] : '');
    return {
      el,
      get(c, dev) {
        const v = el.settings[keyOf(c, dev)];
        if (v !== undefined) return v;
        return dev === 'desktop' || !c.responsive ? clone(c.default) : undefined;
      },
      inherited(c, dev) {
        if (!c.responsive || dev === 'desktop') return undefined;
        if (dev === 'mobile') { const t = el.settings[keyOf(c, 'tablet')]; if (!isEmpty(c, t)) return t; }
        const d = el.settings[keyOf(c, 'desktop')];
        return d !== undefined ? d : c.default;
      },
      set(c, dev, value, opts = {}) {
        const k = keyOf(c, dev);
        change(() => {
          if (value === undefined || (dev !== 'desktop' && c.responsive && isEmpty(c, value))) delete el.settings[k];
          else el.settings[k] = value;
        }, { key: opts.coalesce === false ? null : el.id + ':' + k, panel: opts.panel });
      },
      isSet(c, dev) { return el.settings[keyOf(c, dev)] !== undefined; },
      showWhenValue(key) {
        const c = CONTROLS[el.type][key];
        const v = el.settings[key];
        return v !== undefined ? v : (c ? c.default : undefined);
      },
    };
  }
  /** Accessor for one item of a repeater. */
  function itemAccess(el, rep, index) {
    const items = () => {
      if (!Array.isArray(el.settings[rep.key])) el.settings[rep.key] = clone(rep.default) || [];
      return el.settings[rep.key];
    };
    return {
      el,
      get(c) { const it = (el.settings[rep.key] || rep.default || [])[index] || {}; return it[c.key] !== undefined ? it[c.key] : clone(c.default); },
      inherited() { return undefined; },
      set(c, dev, value, opts = {}) {
        change(() => { const list = items(); list[index] = Object.assign({}, list[index] || {}, { [c.key]: value }); }, { key: el.id + ':' + rep.key + ':' + index + ':' + c.key, panel: opts.panel });
      },
      isSet() { return false; },
      showWhenValue(key) { const it = (el.settings[rep.key] || rep.default || [])[index] || {}; return it[key]; },
    };
  }
  function visible(c, acc) {
    if (!c.showWhen) return true;
    return Object.entries(c.showWhen).every(([key, values]) => {
      const v = acc.showWhenValue(key);
      const s = v === true ? '1' : v === false || v === undefined || v === null ? '' : String(v);
      return values.some((x) => String(x === true ? '1' : x === false ? '' : x) === s);
    });
  }

  // --- settings panel ---------------------------------------------------------------------------
  function renderSettings() {
    const f = locate(selectedId);
    if (!f) { showView('widgets'); return; }
    const def = DEFS[f.el.type];
    const acc = elementAccess(f.el);
    $('[data-bx-settings-title]').textContent = 'Edit ' + def.label;
    $('[data-bx-parent]').disabled = !f.parent;
    const names = { content: f.el.type === 'container' ? 'Layout' : 'Content', style: 'Style', advanced: 'Advanced' };
    const tabs = Object.keys(names).filter((t) => (def.tabs[t] || []).length);
    if (!tabs.includes(activeTab)) activeTab = tabs[0];
    const tabHost = $('[data-bx-tabs]');
    tabHost.innerHTML = '';
    tabs.forEach((t) => tabHost.append(h('button', { type: 'button', role: 'tab', 'aria-selected': String(t === activeTab), text: names[t], onclick: () => { activeTab = t; renderSettings(); } })));
    const note = $('[data-bx-device-note]');
    note.hidden = device === 'desktop';
    note.innerHTML = '';
    if (device !== 'desktop') note.append(h('span', { html: icon(device === 'tablet' ? 'tablet' : 'smartphone') }), h('span', { text: 'Editing ' + device + ' — settings with this icon change only on ' + device + (device === 'tablet' ? ' and mobile' : '') + '.' }));

    const body = $('[data-bx-settings]');
    const scroll = body.scrollTop;
    body.innerHTML = '';
    (def.tabs[activeTab] || []).filter((sec) => !sec.code || cfg.canCode).forEach((sec, i) => {
      const key = f.el.type + ':' + activeTab + ':' + sec.label;
      const det = h('details', { class: 'bx-sec' }, h('summary', { text: sec.label }));
      det.open = key in openSections ? openSections[key] : i === 0;
      det.addEventListener('toggle', () => { openSections[key] = det.open; });
      const inner = h('div', { class: 'bx-sec-body' });
      sec.controls.forEach((c) => { if (visible(c, acc)) { const n = controlNode(c, acc); if (n) inner.append(n); } });
      det.append(inner);
      body.append(det);
    });
    body.scrollTop = scroll;
  }

  function deviceButton() {
    return h('button', { type: 'button', class: 'bx-dev-btn', title: 'Responsive: click to switch screen size', html: icon(device === 'desktop' ? 'monitor' : device === 'tablet' ? 'tablet' : 'smartphone'), onclick: () => setDevice(DEVICES[(DEVICES.indexOf(device) + 1) % 3]) });
  }

  function fieldShell(c, acc, control, opts = {}) {
    const dev = c.responsive ? device : 'desktop';
    const head = h('div', { class: 'bx-field-head' }, h('span', { class: 'bx-label', text: c.label }));
    if (c.responsive) head.append(deviceButton());
    if (acc.isSet(c, dev) && opts.reset !== false) {
      head.append(h('button', { type: 'button', class: 'bx-reset', title: 'Reset to default', html: icon('refresh'), onclick: () => acc.set(c, dev, undefined, { panel: true, coalesce: false }) }));
    }
    const wrap = h('div', { class: 'bx-field bx-field-' + c.type + (opts.inline ? ' is-inline' : '') }, head, control);
    if (c.help) wrap.append(h('p', { class: 'bx-help', text: c.help }));
    return wrap;
  }

  function controlNode(c, acc) {
    const dev = c.responsive ? device : 'desktop';
    const value = acc.get(c, dev);
    const inherited = acc.inherited(c, dev);
    const set = (v, opts) => acc.set(c, dev, v, opts);
    switch (c.type) {
      case 'heading':
        return h('div', { class: 'bx-c-heading', text: c.label });
      case 'text': case 'url': case 'number': {
        const input = h('input', { class: 'input', type: c.type === 'number' ? 'number' : 'text', value: value ?? '', placeholder: c.placeholder || (inherited !== undefined && inherited !== '' ? String(inherited) : c.type === 'url' ? '/page, https://…, #id, #popup-ID' : ''), maxlength: c.type === 'number' ? null : (c.max || null), min: c.type === 'number' ? c.min : null, max: c.type === 'number' ? c.max : null, step: c.step || null });
        input.addEventListener('input', () => {
          const raw = input.value;
          if (c.type === 'number') set(raw === '' ? undefined : Number(raw));
          else set(raw === '' && dev !== 'desktop' ? undefined : raw);
        });
        return fieldShell(c, acc, input);
      }
      case 'textarea': {
        const ta = h('textarea', { class: 'textarea', rows: '3', maxlength: c.max || null });
        ta.value = value ?? '';
        ta.addEventListener('input', () => set(ta.value));
        return fieldShell(c, acc, ta);
      }
      case 'list': {
        const ta = h('textarea', { class: 'textarea', rows: '4', placeholder: 'One item per line' });
        ta.value = Array.isArray(value) ? value.join('\n') : '';
        ta.addEventListener('input', () => set(ta.value.split('\n').map((l) => l.trim()).filter(Boolean)));
        return fieldShell(c, acc, ta);
      }
      case 'richtext':
        return fieldShell(c, acc, richText(value ?? '', (html) => set(html)));
      case 'code':
        if (!cfg.canCode) return null;
        return fieldShell(c, acc, codeArea(value ?? '', c.lang, (v) => set(v)));
      case 'select': case 'form': case 'magnet': case 'category': case 'brand': case 'blogcategory': {
        const sel = h('select', { class: 'select' });
        let options;
        if (c.type === 'select') options = Object.entries(c.choices || {});
        else options = [['', '— None —']].concat(cfg.options[c.type] || []);
        if (dev !== 'desktop' && !options.some(([k]) => k === '')) options.unshift(['', 'Same as larger screens']);
        options.forEach(([k, label]) => sel.append(h('option', { value: k, text: label })));
        sel.value = value === undefined || value === null ? '' : String(value);
        sel.addEventListener('change', () => set(sel.value === '' && dev !== 'desktop' ? undefined : sel.value, { panel: true, coalesce: false }));
        return fieldShell(c, acc, sel);
      }
      case 'choose': {
        const entries = Object.entries(c.choices || {});
        if (entries.length > 5) {
          return controlNode(Object.assign({}, c, { type: 'select' }), acc);
        }
        const group = h('div', { class: 'bx-choose', role: 'group' });
        const ICONS = { left: 'align-left', center: 'align-center', right: 'align-right', justify: 'align-justify', 'flex-start': 'align-left', 'flex-end': 'align-right', row: 'arrow-right', column: 'arrow-down', 'row-reverse': 'arrow-left', 'column-reverse': 'chevron-up' };
        const current = value === undefined ? (inherited ?? '') : value;
        entries.forEach(([k, label]) => {
          if (k === '' && entries.length > 2) return;
          const useIcon = ICONS[k] && c.key !== 'content_width';
          const b = h('button', { type: 'button', class: 'bx-choice' + (String(current) === k ? ' is-on' : '') + (value === undefined && dev !== 'desktop' ? ' is-inherited' : ''), title: label, 'aria-pressed': String(String(value ?? '') === k), html: useIcon ? icon(ICONS[k]) : null, text: useIcon ? null : label });
          b.addEventListener('click', () => set(String(value ?? '') === k && (dev !== 'desktop' || entries.some(([x]) => x === '')) ? (dev !== 'desktop' ? undefined : '') : k, { panel: true, coalesce: false }));
          group.append(b);
        });
        return fieldShell(c, acc, group);
      }
      case 'switch': {
        const cb = h('input', { type: 'checkbox' });
        cb.checked = !!value;
        cb.addEventListener('change', () => set(cb.checked, { panel: true, coalesce: false }));
        return fieldShell(c, acc, h('label', { class: 'switch bx-switch' }, cb, h('span', { text: cb.checked ? 'On' : 'Off' })), { inline: true });
      }
      case 'color': return fieldShell(c, acc, colorInput(value, inherited, set));
      case 'slider': return fieldShell(c, acc, sliderInput(c, value, inherited, set));
      case 'dimensions': return fieldShell(c, acc, dimsInput(c, value, inherited, set));
      case 'image': return fieldShell(c, acc, imageInput(value, set));
      case 'icon': return fieldShell(c, acc, iconInput(value, set));
      case 'products': return fieldShell(c, acc, productsInput(value, set));
      case 'repeater': return repeaterNode(c, acc);
      default: return null;
    }
  }

  function colorInput(value, inherited, set) {
    const valid = (v) => /^#[0-9a-f]{6}$/i.test(v || '');
    const picker = h('input', { type: 'color', value: valid(value) ? value : (valid(inherited) ? inherited : '#000000') });
    const text = h('input', { class: 'input', type: 'text', value: value || '', placeholder: inherited || 'Default', maxlength: '40' });
    picker.addEventListener('input', () => { text.value = picker.value; set(picker.value); });
    text.addEventListener('input', () => {
      const v = text.value.trim();
      if (v === '') set(undefined);
      else if (/^(#[0-9a-f]{3,8}|rgba?\([\d.\s%,/]+\)|hsla?\([\d.\s%,/]+\)|transparent|white|black)$/i.test(v)) { set(v); if (valid(v)) picker.value = v; }
    });
    const clear = h('button', { type: 'button', class: 'bx-mini', title: 'Clear', html: icon('x'), onclick: () => { text.value = ''; set(undefined, { panel: true }); } });
    const wrap = h('div', { class: 'bx-color' + (value ? '' : ' is-empty') }, picker, text, clear);
    picker.addEventListener('input', () => wrap.classList.remove('is-empty'));
    text.addEventListener('input', () => wrap.classList.toggle('is-empty', text.value.trim() === ''));
    return wrap;
  }

  function sliderRange(c, unit) {
    const cap = (n) => (c.max !== undefined ? Math.min(c.max, n) : n);
    if (unit === '%' || unit === 'vh' || unit === 'vw') return { min: Math.max(c.min ?? 0, 0), max: cap(100), step: 1 };
    if (unit === 'em' || unit === 'rem') return { min: c.min ?? 0, max: cap(10), step: 0.1 };
    if (unit === '' ) return { min: c.min ?? 0, max: cap(10), step: c.step || 0.1 };
    return { min: c.min ?? 0, max: cap(c.max !== undefined && c.max <= 300 ? c.max : 1000), step: c.step || 1 };
  }
  function sliderInput(c, value, inherited, set) {
    const units = c.units || UNITS;
    const v = value && typeof value === 'object' ? value : { size: '', unit: (inherited && inherited.unit) || units[0] };
    const unit = units.includes(v.unit) ? v.unit : units[0];
    const r = sliderRange(c, unit);
    const range = h('input', { type: 'range', min: r.min, max: r.max, step: c.step || r.step, value: v.size === '' ? (inherited && inherited.size !== '' ? inherited.size : r.min) : v.size });
    const num = h('input', { class: 'input', type: 'number', value: v.size, placeholder: inherited && inherited.size !== '' && inherited.size !== undefined ? String(inherited.size) : '', min: c.min ?? null, max: c.max ?? null, step: c.step || r.step });
    const unitSel = h('select', { class: 'select bx-unit' });
    units.forEach((u) => unitSel.append(h('option', { value: u, text: u || '—' })));
    unitSel.value = unit;
    const emit = (size, u) => set(size === '' ? (device === 'desktop' && !c.responsive ? { size: '', unit: u } : undefined) : { size: Number(size), unit: u });
    range.addEventListener('input', () => { num.value = range.value; emit(range.value, unitSel.value); });
    num.addEventListener('input', () => { if (num.value !== '') range.value = num.value; emit(num.value, unitSel.value); });
    unitSel.addEventListener('change', () => emit(num.value, unitSel.value));
    return h('div', { class: 'bx-slider' }, range, num, units.length > 1 ? unitSel : h('span', { class: 'bx-unit-label', text: units[0] }));
  }

  function dimsInput(c, value, inherited, set) {
    const units = c.units || ['px', '%', 'em', 'rem'];
    const sides = ['top', 'right', 'bottom', 'left'];
    const v = value && typeof value === 'object' ? Object.assign({ top: '', right: '', bottom: '', left: '', unit: units[0], linked: true }, value) : { top: '', right: '', bottom: '', left: '', unit: units[0], linked: true };
    const ph = inherited && typeof inherited === 'object' ? inherited : {};
    const inputs = {};
    const emit = () => {
      const out = { unit: unitSel.value, linked: v.linked };
      sides.forEach((s) => { out[s] = inputs[s].value; });
      set(sides.every((s) => out[s] === '') && device !== 'desktop' ? undefined : out);
    };
    const grid = h('div', { class: 'bx-dims' });
    sides.forEach((s) => {
      const inp = h('input', { class: 'input', type: 'number', value: v[s], placeholder: ph[s] !== undefined && ph[s] !== '' ? String(ph[s]) : '', min: c.negative ? null : '0', title: s });
      inp.addEventListener('input', () => {
        if (v.linked) sides.forEach((o) => { if (o !== s) inputs[o].value = inp.value; });
        emit();
      });
      inputs[s] = inp;
      grid.append(h('label', { class: 'bx-dim' }, inp, h('span', { text: s[0].toUpperCase() + s.slice(1) })));
    });
    const link = h('button', { type: 'button', class: 'bx-mini bx-link' + (v.linked ? ' is-on' : ''), title: 'Link values together', html: icon('link') });
    link.addEventListener('click', () => { v.linked = !v.linked; link.classList.toggle('is-on', v.linked); emit(); });
    const unitSel = h('select', { class: 'select bx-unit' });
    units.forEach((u) => unitSel.append(h('option', { value: u, text: u })));
    unitSel.value = units.includes(v.unit) ? v.unit : units[0];
    unitSel.addEventListener('change', emit);
    grid.append(link);
    return h('div', { class: 'bx-dims-wrap' }, grid, units.length > 1 ? unitSel : null);
  }

  function imageInput(value, set) {
    const thumb = h('div', { class: 'bx-thumb' });
    const paint = (url) => { thumb.innerHTML = ''; if (url) thumb.append(h('img', { src: url, alt: '' })); else thumb.append(h('span', { html: icon('image') })); };
    paint(value);
    const url = h('input', { class: 'input', type: 'text', value: value || '', placeholder: 'Image address' });
    url.addEventListener('change', () => { set(url.value.trim()); paint(url.value.trim()); });
    const choose = h('button', { type: 'button', class: 'btn btn-secondary btn-sm', text: 'Choose', onclick: () => {
      if (!window.openMediaPicker) return;
      window.openMediaPicker((m) => { url.value = m.url; paint(m.url); set(m.url, { coalesce: false }); }, 'IMAGE');
    } });
    const clear = h('button', { type: 'button', class: 'btn btn-ghost btn-sm', text: 'Remove', onclick: () => { url.value = ''; paint(''); set('', { coalesce: false }); } });
    thumb.addEventListener('click', () => choose.click());
    return h('div', { class: 'bx-image' }, thumb, h('div', { class: 'bx-image-actions' }, choose, clear), url);
  }

  function iconInput(value, set) {
    const current = h('button', { type: 'button', class: 'bx-icon-current', html: (value ? icon(value) : '') + '<span>' + (value ? value : 'No icon') + '</span>' });
    const pop = h('div', { class: 'bx-icon-pop', hidden: true });
    const search = h('input', { class: 'input', type: 'search', placeholder: 'Search icons' });
    const grid = h('div', { class: 'bx-icon-grid' });
    const fill = () => {
      grid.innerHTML = '';
      const q = search.value.trim().toLowerCase();
      grid.append(h('button', { type: 'button', class: 'bx-icon-opt', title: 'None', text: '—', onclick: () => { set('', { panel: true, coalesce: false }); } }));
      Object.keys(ICON).filter((n) => !q || n.includes(q)).forEach((n) => grid.append(h('button', { type: 'button', class: 'bx-icon-opt' + (n === value ? ' is-on' : ''), title: n, html: ICON[n], onclick: () => set(n, { panel: true, coalesce: false }) })));
    };
    search.addEventListener('input', fill);
    current.addEventListener('click', () => { pop.hidden = !pop.hidden; if (!pop.hidden) { fill(); search.focus(); } });
    pop.append(search, grid);
    return h('div', { class: 'bx-icon-field' }, current, pop);
  }

  function productsInput(value, set) {
    const chosen = Array.isArray(value) ? value.map(Number) : [];
    const box = h('div', { class: 'bx-checks' });
    (cfg.options.products || []).forEach(([id, name]) => {
      const cb = h('input', { type: 'checkbox', value: id });
      cb.checked = chosen.includes(Number(id));
      cb.addEventListener('change', () => {
        const next = chosen.filter((x) => x !== Number(id));
        if (cb.checked) next.push(Number(id));
        chosen.length = 0; next.forEach((x) => chosen.push(x));
        set(next.slice(), { coalesce: false });
      });
      box.append(h('label', { class: 'check' }, cb, ' ', name));
    });
    if (!box.children.length) box.append(h('p', { class: 'bx-help', text: 'No products yet.' }));
    return box;
  }

  function repeaterNode(c, acc) {
    const el = acc.el;
    const items = Array.isArray(el.settings[c.key]) ? el.settings[c.key] : (Array.isArray(c.default) ? c.default : []);
    const ensure = () => { if (!Array.isArray(el.settings[c.key])) el.settings[c.key] = clone(c.default) || []; return el.settings[c.key]; };
    const wrap = h('div', { class: 'bx-field bx-repeater' }, h('div', { class: 'bx-field-head' }, h('span', { class: 'bx-label', text: c.label })));
    const list = h('div', { class: 'bx-rep-list' });
    items.forEach((item, i) => {
      const title = String((c.titleField && item[c.titleField]) || '').replace(/<[^>]*>/g, '').slice(0, 40) || 'Item ' + (i + 1);
      const det = h('details', { class: 'bx-rep-item' });
      const keyOpen = el.id + ':' + c.key + ':' + i;
      det.open = !!openSections[keyOpen];
      det.addEventListener('toggle', () => { openSections[keyOpen] = det.open; });
      const tools = h('span', { class: 'bx-rep-tools' },
        h('button', { type: 'button', class: 'bx-mini', title: 'Move up', html: icon('chevron-up'), disabled: i === 0, onclick: (e) => { e.preventDefault(); change(() => { const l = ensure(); [l[i - 1], l[i]] = [l[i], l[i - 1]]; }, { panel: true }); } }),
        h('button', { type: 'button', class: 'bx-mini', title: 'Move down', html: icon('chevron-down'), disabled: i === items.length - 1, onclick: (e) => { e.preventDefault(); change(() => { const l = ensure(); [l[i + 1], l[i]] = [l[i], l[i + 1]]; }, { panel: true }); } }),
        h('button', { type: 'button', class: 'bx-mini', title: 'Duplicate', html: icon('copy'), onclick: (e) => { e.preventDefault(); change(() => { const l = ensure(); l.splice(i + 1, 0, clone(l[i])); }, { panel: true }); } }),
        h('button', { type: 'button', class: 'bx-mini bx-danger', title: 'Remove', html: icon('trash'), onclick: (e) => { e.preventDefault(); change(() => ensure().splice(i, 1), { panel: true }); } }));
      det.append(h('summary', {}, h('span', { text: title }), tools));
      const body = h('div', { class: 'bx-rep-body' });
      const iacc = itemAccess(el, c, i);
      (c.fields || []).forEach((sub) => { if (visible(sub, iacc)) { const n = controlNode(Object.assign({}, sub, { responsive: false }), iacc); if (n) body.append(n); } });
      det.append(body);
      list.append(det);
    });
    wrap.append(list);
    const limit = c.limit || 50;
    wrap.append(h('button', { type: 'button', class: 'btn btn-secondary btn-sm', disabled: items.length >= limit, html: icon('plus') + ' Add item', onclick: () => {
      const item = {};
      (c.fields || []).forEach((sub) => { item[sub.key] = clone(sub.default); });
      change(() => ensure().push(item), { panel: true });
      openSections[el.id + ':' + c.key + ':' + (items.length)] = true;
      renderSettings();
    } }));
    return wrap;
  }

  function codeArea(value, lang, onChange) {
    const ta = h('textarea', { class: 'textarea bx-code', rows: lang === 'html' ? '14' : '8', spellcheck: 'false', autocapitalize: 'off', autocomplete: 'off', 'data-lang': lang || '' });
    ta.value = value;
    ta.addEventListener('input', () => onChange(ta.value));
    ta.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab' || e.shiftKey) return;
      e.preventDefault();
      const start = ta.selectionStart;
      ta.setRangeText('  ', start, ta.selectionEnd, 'end');
      onChange(ta.value);
    });
    return ta;
  }

  function richText(html, onChange) {
    const area = h('div', { class: 'bx-rte-area', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true' });
    area.innerHTML = html;
    const cmd = (name, arg) => { area.focus(); document.execCommand(name, false, arg); onChange(area.innerHTML); };
    const bar = h('div', { class: 'bx-rte-bar' },
      h('button', { type: 'button', title: 'Bold', html: '<b>B</b>', onclick: () => cmd('bold') }),
      h('button', { type: 'button', title: 'Italic', html: '<i>I</i>', onclick: () => cmd('italic') }),
      h('button', { type: 'button', title: 'Underline', html: '<u>U</u>', onclick: () => cmd('underline') }),
      h('button', { type: 'button', title: 'Heading', text: 'H2', onclick: () => cmd('formatBlock', 'h2') }),
      h('button', { type: 'button', title: 'Sub-heading', text: 'H3', onclick: () => cmd('formatBlock', 'h3') }),
      h('button', { type: 'button', title: 'Paragraph', text: 'P', onclick: () => cmd('formatBlock', 'p') }),
      h('button', { type: 'button', title: 'Bulleted list', html: icon('list'), onclick: () => cmd('insertUnorderedList') }),
      h('button', { type: 'button', title: 'Numbered list', text: '1.', onclick: () => cmd('insertOrderedList') }),
      h('button', { type: 'button', title: 'Link', html: icon('link'), onclick: () => { const url = window.prompt('Link address (https://…, /page, mailto:…)'); if (url) cmd('createLink', url); } }),
      h('button', { type: 'button', title: 'Remove formatting', html: icon('x'), onclick: () => { cmd('removeFormat'); cmd('unlink'); } }));
    area.addEventListener('input', () => onChange(area.innerHTML));
    area.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      const esc = (s) => s.replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
      const parts = text.split(/\n{2,}/).map((p) => '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>');
      document.execCommand('insertHTML', false, parts.length > 1 ? parts.join('') : esc(text).replace(/\n/g, '<br>'));
    });
    return h('div', { class: 'bx-rte' }, bar, area);
  }

  // --- navigator ---------------------------------------------------------------------------------
  const navPanel = $('[data-bx-nav-panel]');
  function snippet(el) {
    const s = el.settings || {};
    const t = s.title || s.text || s.heading || s.name || (typeof s.content === 'string' ? s.content.replace(/<[^>]*>/g, ' ') : '');
    return String(t || '').replace(/\s+/g, ' ').trim().slice(0, 28);
  }
  function renderNavigator() {
    if (navPanel.hidden) return;
    const host = $('[data-bx-nav-tree]');
    host.innerHTML = '';
    if (!doc.elements.length) { host.append(h('p', { class: 'bx-hint', text: 'Nothing here yet.' })); return; }
    const build = (list, depth, parentId) => {
      const ul = h('ul', { class: 'bx-nav-list' });
      list.forEach((el, index) => {
        const def = DEFS[el.type] || { label: el.type, icon: 'square' };
        const hidden = el.settings && el.settings._hide_desktop && el.settings._hide_tablet && el.settings._hide_mobile;
        const caret = el.children && el.children.length ? h('button', { type: 'button', class: 'bx-nav-caret' + (collapsedNav[el.id] ? '' : ' is-open'), html: icon('chevron-right'), onclick: (e) => { e.stopPropagation(); collapsedNav[el.id] = !collapsedNav[el.id]; renderNavigator(); } }) : h('span', { class: 'bx-nav-caret' });
        const row = h('div', { class: 'bx-nav-row' + (el.id === selectedId ? ' is-selected' : '') + (hidden ? ' is-hidden' : ''), draggable: 'true', 'data-id': el.id, style: 'padding-left:' + (6 + depth * 14) + 'px' },
          caret, h('span', { class: 'bx-nav-icon', html: icon(def.icon) }), h('span', { class: 'bx-nav-label', text: def.label }), h('span', { class: 'bx-nav-snippet', text: snippet(el) }));
        row.addEventListener('click', () => select(el.id, { scroll: true }));
        row.addEventListener('mouseenter', () => post({ type: 'bx:hover', id: el.id }));
        row.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/bx-nav', el.id); e.dataTransfer.effectAllowed = 'move'; });
        row.addEventListener('dragover', (e) => {
          if (!Array.from(e.dataTransfer.types).includes('text/bx-nav')) return;
          e.preventDefault();
          const r = row.getBoundingClientRect();
          const rel = (e.clientY - r.top) / r.height;
          const zone = el.type === 'container' && rel > 0.3 && rel < 0.7 ? 'inside' : rel < 0.5 ? 'before' : 'after';
          row.dataset.drop = zone;
        });
        row.addEventListener('dragleave', () => { delete row.dataset.drop; });
        row.addEventListener('drop', (e) => {
          e.preventDefault();
          const zone = row.dataset.drop;
          delete row.dataset.drop;
          const id = e.dataTransfer.getData('text/bx-nav');
          if (!id || id === el.id) return;
          if (zone === 'inside') moveEl(id, { parent: el.id, index: el.children.length });
          else moveEl(id, { parent: parentId, index: index + (zone === 'after' ? 1 : 0) });
        });
        const li = h('li', {}, row);
        if (el.children && el.children.length && !collapsedNav[el.id]) li.append(build(el.children, depth + 1, el.id));
        ul.append(li);
      });
      return ul;
    };
    host.append(build(doc.elements, 0, null));
  }
  function highlightNav() {
    $$('.bx-nav-row', navPanel).forEach((r) => r.classList.toggle('is-selected', r.dataset.id === selectedId));
  }

  // --- context menu -----------------------------------------------------------------------------
  const menu = $('[data-bx-menu]');
  function openMenu(id, x, y) {
    const f = locate(id);
    if (!f) return;
    const isContainer = f.el.type === 'container';
    const item = (label, ic, fn, extra = '') => h('button', { type: 'button', role: 'menuitem', class: 'bx-menu-item ' + extra, onclick: () => { closeMenu(); fn(); } }, h('span', { html: icon(ic) }), h('span', { text: label }));
    menu.innerHTML = '';
    menu.append(
      item('Edit ' + DEFS[f.el.type].label.toLowerCase(), 'edit', () => select(id)),
      item('Duplicate', 'copy', () => duplicate(id)),
      item('Copy', 'clipboard', () => copyEl(id)),
      item('Paste', 'clipboard', () => paste()),
      f.parent ? item('Select parent container', 'arrow-up-right', () => select(f.parent.id, { scroll: true })) : '',
      isContainer ? item('Add a container after', 'plus', () => openStructure({ parent: f.parent ? f.parent.id : null, index: f.index + 1 })) : '',
      isContainer ? item('Save as template', 'folder', () => openSaveTemplate(id)) : '',
      item('Navigator', 'layers', () => toggleNavigator(true)),
      item('Delete', 'trash', () => remove(id), 'is-danger'));
    menu.hidden = false;
    const r = menu.getBoundingClientRect();
    menu.style.left = Math.min(x, innerWidth - r.width - 8) + 'px';
    menu.style.top = Math.min(y, innerHeight - r.height - 8) + 'px';
  }
  function closeMenu() { menu.hidden = true; }
  document.addEventListener('click', (e) => { if (!menu.hidden && !menu.contains(e.target)) closeMenu(); });

  // --- dialogs -----------------------------------------------------------------------------------
  const structureDialog = $('[data-bx-structure]');
  let structureTarget = null;
  function openStructure(target) {
    structureTarget = target;
    const host = $('[data-bx-structures]');
    host.innerHTML = '';
    STRUCTURES.forEach((cols) => {
      const b = h('button', { type: 'button', class: 'bx-structure', title: cols.join(' / ') }, cols.map((w) => h('span', { style: 'flex:' + w })));
      b.addEventListener('click', () => {
        structureDialog.close();
        drop({ kind: 'structure', cols }, structureTarget);
      });
      host.append(b);
    });
    structureDialog.showModal();
  }

  const saveTplDialog = $('[data-bx-save-template]');
  let saveTplId = null;
  function openSaveTemplate(id) {
    saveTplId = id;
    const form = $('[data-bx-save-template-form]');
    form.reset();
    $('[data-bx-st-note]').textContent = id ? 'Saves this container and everything inside it.' : 'Saves the whole page layout.';
    saveTplDialog.showModal();
  }
  $('[data-bx-save-template-form]').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = e.target.name.value.trim();
    if (!name) return;
    const f = locate(saveTplId);
    const layout = { elements: saveTplId && f ? [f.el] : doc.elements };
    try {
      await api(cfg.urls.saveTemplate, { name, kind: saveTplId ? 'section' : 'page', layout });
      saveTplDialog.close();
      toast('Template saved.');
      templatesLoaded = false;
      if (!$('[data-bx-wpane="templates"]').hidden) loadTemplates(true);
    } catch (err) { toast(err.message, 'error'); }
  });

  // --- page / product / template settings --------------------------------------------------
  const setDialog = $('[data-bx-settings-dialog]');
  function openSettings() {
    const st = cfg.settings || {};
    const host = $('[data-bx-settings-fields]');
    host.innerHTML = '';
    const field = (label, input, help) => h('label', { class: 'field' }, h('span', { class: 'label', text: label }), input, help ? h('span', { class: 'help', text: help }) : null);
    const text = (name, value, attrs = {}) => h('input', Object.assign({ class: 'input', name, value: value ?? '' }, attrs));
    host.append(field(cfg.kind === 'product' ? 'Product name' : cfg.kind === 'template' ? 'Template name' : 'Page title', text('title', st.title, { required: true, maxlength: '255' })));
    if (cfg.kind !== 'template') {
      if (st.isHome) {
        host.append(field('Address', text('slug', '', { disabled: true, placeholder: st.prefix }), 'This is the homepage; its address cannot change.'));
      } else {
        const slug = text('slug', st.slug, { required: true, maxlength: '255', pattern: '[a-z0-9\\-/]+', spellcheck: 'false' });
        const preview = h('span', { class: 'help bx-url-preview' });
        const paint = () => { preview.textContent = st.prefix + (slug.value || '…'); };
        slug.addEventListener('input', () => { slug.value = slug.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-/]/g, ''); paint(); });
        paint();
        host.append(h('label', { class: 'field' }, h('span', { class: 'label', text: 'Address (URL)' }), h('span', { class: 'prefix-group' }, h('span', { text: st.prefix.replace(/^https?:\/\//, '') }), slug), preview,
          h('span', { class: 'help', text: st.live ? 'The page is live: changing the address keeps the old one working with a permanent redirect.' : 'Lower-case letters, numbers and dashes.' })));
      }
      host.append(field('SEO title', text('seo_title', st.seo_title, { maxlength: '255', placeholder: st.title }), 'Shown in search results and the browser tab. Blank = the title.'));
      const desc = h('textarea', { class: 'textarea', name: 'seo_description', rows: '3', maxlength: '500' });
      desc.value = st.seo_description || '';
      host.append(field('Meta description', desc));
    }
    if (cfg.kind === 'page') {
      const sw = (name, label, on) => { const cb = h('input', { type: 'checkbox', name, value: '1' }); cb.checked = !!on; return h('label', { class: 'switch' }, cb, h('span', { text: label })); };
      host.append(h('div', { class: 'row' }, sw('show_header', 'Show the site header', st.show_header), sw('show_footer', 'Show the site footer', st.show_footer)));
    }
    if (cfg.canCode) {
      const css = h('textarea', { class: 'textarea bx-code', rows: '8', spellcheck: 'false', 'data-css': '1', placeholder: '/* CSS for this ' + (cfg.kind === 'template' ? 'template' : cfg.kind) + ' only */' });
      css.value = (doc.settings && doc.settings.custom_css) || '';
      host.append(field('Custom CSS', css, 'Applies to this layout only. Saved with the layout (Save draft / Publish).'));
    }
    if (st.url) host.append(h('p', { class: 'small muted' }, 'Live address: ', h('a', { href: st.url, target: '_blank', rel: 'noopener', text: st.url })));
    setDialog.showModal();
  }
  $('[data-bx-settings-form]').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const css = $('[data-css]', form);
    if (css && css.value !== ((doc.settings && doc.settings.custom_css) || '')) {
      change(() => { doc.settings = Object.assign({}, doc.settings || {}, { custom_css: css.value }); });
    }
    const body = {};
    ['title', 'slug', 'seo_title', 'seo_description'].forEach((k) => { if (form.elements[k] && !form.elements[k].disabled) body[k] = form.elements[k].value; });
    ['show_header', 'show_footer'].forEach((k) => { if (form.elements[k]) body[k] = form.elements[k].checked ? 1 : 0; });
    try {
      const res = await api(cfg.urls.settings, body);
      cfg.settings = res.settings;
      $('.bx-doc-title strong').textContent = res.title;
      document.title = 'Edit with builder: ' + res.title;
      setDialog.close();
      toast(res.message || 'Settings saved.');
      if ('show_header' in body) frame.contentWindow.location.reload();
    } catch (err) { toast(err.message, 'error'); }
  });
  $('[data-bx-page-settings]').addEventListener('click', openSettings);

  const revDialog = $('[data-bx-revisions]');
  async function openRevisions() {
    const host = $('[data-bx-revision-list]');
    host.innerHTML = '<p class="muted small">Loading…</p>';
    $('[data-bx-draft-row]').hidden = !cfg.hasDraft;
    revDialog.showModal();
    try {
      const res = await api(cfg.urls.revisions);
      host.innerHTML = '';
      if (!res.items.length) host.append(h('p', { class: 'muted small', text: 'No published versions yet.' }));
      res.items.forEach((r) => host.append(h('div', { class: 'bx-rev' },
        h('span', {}, h('strong', { text: r.created_at }), r.user ? h('span', { class: 'muted small', text: ' · ' + r.user }) : ''),
        h('button', { type: 'button', class: 'btn btn-secondary btn-sm', text: 'Load', onclick: async () => {
          try {
            const rev = await api(cfg.urls.revisions + '/' + r.id);
            change(() => { doc = docFrom(rev.layout); });
            select(null);
            revDialog.close();
            toast('Revision loaded. Publish to make it live.');
          } catch (e) { toast(e.message, 'error'); }
        } }))));
    } catch (e) { host.innerHTML = ''; host.append(h('p', { class: 'muted small', text: e.message })); }
  }
  $('[data-bx-discard]').addEventListener('click', async () => {
    if (!window.confirm('Throw away the draft and go back to the published version?')) return;
    try {
      const res = await api(cfg.urls.discard, {});
      cfg.hasDraft = false;
      change(() => { doc = docFrom(res.layout); });
      setDirty(false);
      select(null);
      revDialog.close();
      updateStatus();
      toast('Draft discarded.');
    } catch (e) { toast(e.message, 'error'); }
  });
  $$('dialog [data-close-dialog]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));

  // --- saving -------------------------------------------------------------------------------------
  function setDirty(v) { dirty = v; updateStatus(); }
  function updateStatus(text) {
    const el = $('[data-bx-status]');
    el.textContent = text || (dirty ? 'Unsaved changes' : cfg.hasDraft ? 'Draft — not published yet' : cfg.status === 'PUBLISHED' ? 'Published' : cfg.status ? cfg.status.toLowerCase() : 'Saved');
    el.classList.toggle('is-dirty', dirty);
    $('[data-bx-publish]').lastChild.textContent = cfg.status === 'PUBLISHED' || cfg.kind !== 'page' ? ' Update' : ' Publish';
  }
  async function save(publish, quiet) {
    if (saving) return false;
    saving = true;
    updateStatus(publish ? 'Publishing…' : 'Saving…');
    try {
      const res = await api(cfg.urls.save, { layout: doc, publish: !!publish });
      cfg.hasDraft = !publish;
      setDirty(false);
      updateStatus((publish ? 'Published at ' : 'Draft saved at ') + res.savedAt);
      if (!quiet) toast(publish ? 'Published — live now.' : 'Draft saved.');
      return true;
    } catch (e) {
      updateStatus('Not saved');
      toast(e.message, 'error');
      return false;
    } finally { saving = false; }
  }
  $('[data-bx-save]').addEventListener('click', () => save(false));
  $('[data-bx-publish]').addEventListener('click', () => save(true));
  const previewLink = $('[data-bx-preview]');
  if (previewLink) previewLink.addEventListener('click', async (e) => {
    e.preventDefault();
    const w = window.open('about:blank', '_blank');
    if (dirty) await save(false, true);
    if (w) { w.opener = null; w.location.href = previewLink.href; }
  });
  setInterval(() => { if (dirty && !saving) save(false, true); }, 60000);
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  $('[data-bx-exit]').addEventListener('click', (e) => { if (dirty && !window.confirm('You have unsaved changes. Leave anyway?')) e.preventDefault(); else dirty = false; });

  // --- device & frame -------------------------------------------------------------------------------
  const WIDTHS = { desktop: 1200, tablet: 820, mobile: 390 };
  function layoutFrame() {
    const wrap = frame.parentElement;
    const avail = wrap.clientWidth;
    let w = WIDTHS[device];
    let scale = 1;
    if (device === 'desktop') { w = Math.max(avail, 1100); scale = Math.min(1, avail / w); }
    frame.style.width = w + 'px';
    frame.style.height = (wrap.clientHeight / scale) + 'px';
    frame.style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
    wrap.classList.toggle('is-scaled', scale < 1);
    frameScale = scale;
  }
  function setDevice(d) {
    device = d;
    app.dataset.device = d;
    $$('[data-bx-device]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.bxDevice === d)));
    layoutFrame();
    if (selectedId) renderSettings();
  }
  $$('[data-bx-device]').forEach((b) => b.addEventListener('click', () => setDevice(b.dataset.bxDevice)));
  addEventListener('resize', layoutFrame);

  function toggleNavigator(force) {
    navPanel.hidden = force === undefined ? !navPanel.hidden : !force;
    $('[data-bx-navigator]').setAttribute('aria-pressed', String(!navPanel.hidden));
    renderNavigator();
  }

  // --- keyboard -----------------------------------------------------------------------------------
  function handleKey(k) {
    const key = k.key;
    if (k.ctrl && key === 'z') return k.shift ? travel(1) : travel(-1);
    if (k.ctrl && key === 'y') return travel(1);
    if (k.ctrl && key === 's') return save(false);
    if (k.ctrl && key === 'c' && selectedId) return copyEl(selectedId);
    if (k.ctrl && key === 'v') return paste();
    if (k.ctrl && key === 'd' && selectedId) return duplicate(selectedId);
    if ((key === 'delete' || key === 'backspace') && selectedId) return remove(selectedId);
    if (key === 'escape') { closeMenu(); return select(null); }
  }
  document.addEventListener('keydown', (e) => {
    const t = e.target;
    const typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
    const ctrl = e.ctrlKey || e.metaKey;
    const key = e.key.toLowerCase();
    if (ctrl && key === 's') { e.preventDefault(); save(false); return; }
    if (typing) return;
    if ((ctrl && ['z', 'y', 'c', 'v', 'd'].includes(key)) || key === 'delete' || key === 'backspace' || key === 'escape') {
      if (document.querySelector('dialog[open]')) return;
      e.preventDefault();
      handleKey({ key, ctrl, shift: e.shiftKey });
    }
  });

  // --- canvas messages --------------------------------------------------------------------------------
  window.addEventListener('message', (e) => {
    if (e.origin !== location.origin || e.source !== frame.contentWindow) return;
    const m = e.data || {};
    switch (m.type) {
      case 'bx:ready': {
        canvasReady = true;
        $('[data-bx-loading]').hidden = true;
        const labels = {};
        for (const [t, d] of Object.entries(DEFS)) labels[t] = d.label;
        post({ type: 'bx:init', labels, icons: { move: icon('move'), plus: icon('plus'), edit: icon('edit'), copy: icon('copy'), trash: icon('trash') } });
        doRender();
        break;
      }
      case 'bx:select': select(m.id, { fromCanvas: true }); break;
      case 'bx:drop': drop(m.source, m.target); break;
      case 'bx:key': handleKey(m); break;
      case 'bx:context': {
        const r = frame.getBoundingClientRect();
        openMenu(m.id, r.left + m.x * frameScale, r.top + m.y * frameScale);
        break;
      }
      case 'bx:action':
        if (m.action === 'edit') { select(m.id); activeTab = 'content'; renderSettings(); }
        else if (m.action === 'duplicate') duplicate(m.id);
        else if (m.action === 'delete') remove(m.id);
        else if (m.action === 'add') { const f = locate(m.id); if (f) openStructure({ parent: f.parent ? f.parent.id : null, index: f.index + 1 }); }
        else if (m.action === 'add-root') openStructure({ parent: null, index: doc.elements.length });
        else if (m.action === 'templates') { showView('widgets'); switchWidgetTab('templates'); }
        else if (m.action === 'widgets') { showView('widgets'); switchWidgetTab('elements'); }
        break;
      default:
    }
  });

  // --- panel chrome -------------------------------------------------------------------------------------
  function switchWidgetTab(name) {
    $$('[data-bx-wtab]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.bxWtab === name)));
    $$('[data-bx-wpane]').forEach((p) => { p.hidden = p.dataset.bxWpane !== name; });
    if (name === 'templates') loadTemplates();
  }
  $$('[data-bx-wtab]').forEach((b) => b.addEventListener('click', () => switchWidgetTab(b.dataset.bxWtab)));
  $('[data-bx-search]').addEventListener('input', renderWidgets);
  $('[data-bx-back]').addEventListener('click', () => showView('widgets'));
  $('[data-bx-parent]').addEventListener('click', () => { const f = locate(selectedId); if (f && f.parent) select(f.parent.id, { scroll: true }); });
  $('[data-bx-undo]').addEventListener('click', () => travel(-1));
  $('[data-bx-redo]').addEventListener('click', () => travel(1));
  $('[data-bx-navigator]').addEventListener('click', () => toggleNavigator());
  $('[data-bx-nav-close]').addEventListener('click', () => toggleNavigator(false));
  $('[data-bx-history]').addEventListener('click', openRevisions);
  app.addEventListener('dragend', () => app.classList.remove('is-dragging'));

  // --- start --------------------------------------------------------------------------------------------
  renderWidgets();
  updateUndo();
  updateStatus();
  layoutFrame();
  if (cfg.hasDraft) toast('Opened your unpublished draft.', 'info');
})();
