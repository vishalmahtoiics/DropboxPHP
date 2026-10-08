/*
 * Builder canvas — runs inside the editor's iframe, on top of the real page.
 *
 * It draws hover and selection outlines, the element toolbar and drop
 * indicators, and reports clicks, drops and shortcuts to the editor through
 * postMessage (same origin only). The layout HTML itself is rendered by the
 * server and handed over by the editor. Vanilla JS, no inline handlers (CSP).
 */
(function () {
  'use strict';

  const root = document.getElementById('bx-root');
  if (!root || window.parent === window) return;
  const editor = window.parent;
  const origin = location.origin;
  const send = (msg) => editor.postMessage(msg, origin);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  let labels = {};
  let icons = {};
  let selectedId = null;
  let dragId = null;          // element being moved from inside the canvas
  let dropTarget = null;

  const style = document.createElement('style');
  style.id = 'bx-layout-css';
  document.head.appendChild(style);

  // --- overlays ---------------------------------------------------------------------------
  const make = (cls) => { const d = document.createElement('div'); d.className = cls; d.setAttribute('aria-hidden', 'true'); document.body.appendChild(d); return d; };
  const hoverBox = make('bx-ov bx-ov-hover');
  const hoverLabel = document.createElement('span'); hoverLabel.className = 'bx-ov-label'; hoverBox.appendChild(hoverLabel);
  const selectBox = make('bx-ov bx-ov-select');
  const toolbar = document.createElement('div'); toolbar.className = 'bx-toolbar'; selectBox.appendChild(toolbar);
  const dropLine = make('bx-dropline');
  const dropZone = make('bx-dropzone');

  const elById = (id) => (id ? root.querySelector('[data-el="' + CSS.escape(id) + '"]') : null);
  const isContainer = (node) => node && node.classList.contains('bx-con');
  const pageRect = (node) => {
    const r = node.getBoundingClientRect();
    return { left: r.left + scrollX, top: r.top + scrollY, width: r.width, height: r.height, right: r.right + scrollX, bottom: r.bottom + scrollY };
  };
  const place = (box, r) => { box.style.left = r.left + 'px'; box.style.top = r.top + 'px'; box.style.width = r.width + 'px'; box.style.height = r.height + 'px'; };

  function buildToolbar(node) {
    toolbar.innerHTML = '';
    const container = isContainer(node);
    const btn = (action, icon, title, draggable) => {
      const b = document.createElement(draggable ? 'span' : 'button');
      if (!draggable) b.type = 'button';
      b.className = 'bx-tool';
      b.dataset.action = action;
      b.title = title;
      b.innerHTML = icons[icon] || '';
      if (draggable) { b.draggable = true; b.setAttribute('role', 'button'); }
      toolbar.appendChild(b);
    };
    btn('move', 'move', 'Drag to move', true);
    if (container) btn('add', 'plus', 'Add a container after this one');
    else btn('edit', 'edit', 'Edit');
    btn('duplicate', 'copy', 'Duplicate (Ctrl+D)');
    btn('delete', 'trash', 'Delete (Del)');
    toolbar.classList.toggle('is-container', container);
  }

  function updateSelection() {
    const node = elById(selectedId);
    $$('.bx-is-selected', root).forEach((n) => n.classList.remove('bx-is-selected'));
    if (!node) { selectBox.hidden = true; return; }
    node.classList.add('bx-is-selected');
    selectBox.hidden = false;
    selectBox.classList.toggle('is-container', isContainer(node));
    const r = pageRect(node);
    selectBox.classList.toggle('at-top', r.top < 28);
    place(selectBox, r);
  }

  function showHover(node) {
    if (!node || node.dataset.el === selectedId) { hoverBox.hidden = true; return; }
    hoverBox.hidden = false;
    hoverBox.classList.toggle('is-container', isContainer(node));
    hoverLabel.textContent = labels[node.dataset.elType] || node.dataset.elType || '';
    place(hoverBox, pageRect(node));
  }

  function select(id, notify) {
    selectedId = id;
    const node = elById(id);
    if (node) buildToolbar(node);
    updateSelection();
    if (notify) send({ type: 'bx:select', id });
  }

  // --- messages from the editor -------------------------------------------------------------
  window.addEventListener('message', (e) => {
    if (e.origin !== origin || e.source !== editor) return;
    const m = e.data || {};
    if (m.type === 'bx:init') { labels = m.labels || {}; icons = m.icons || {}; }
    if (m.type === 'bx:render') {
      const y = scrollY;
      style.textContent = m.css || '';
      root.innerHTML = m.html || '';
      document.body.classList.toggle('bx-is-empty', !root.querySelector('[data-el]'));
      scrollTo(scrollX, y);
      select(m.selected || null, false);
    }
    if (m.type === 'bx:select') {
      select(m.id, false);
      const node = elById(m.id);
      if (node && m.scroll) node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    if (m.type === 'bx:hover') showHover(elById(m.id));
  });

  // --- pointer: hover, select, toolbar ------------------------------------------------------
  document.addEventListener('mouseover', (e) => {
    if (dragId) return;
    const node = e.target.closest && e.target.closest('[data-el]');
    showHover(node && root.contains(node) ? node : null);
  });
  document.addEventListener('mouseleave', () => { hoverBox.hidden = true; });

  // Clicks never navigate or submit inside the canvas.
  document.addEventListener('click', (e) => {
    const tool = e.target.closest('.bx-tool');
    if (tool) {
      e.preventDefault();
      if (tool.dataset.action !== 'move') send({ type: 'bx:action', action: tool.dataset.action, id: selectedId });
      return;
    }
    const add = e.target.closest('[data-bx-add-root]');
    if (add) { e.preventDefault(); send({ type: 'bx:action', action: 'add-root' }); return; }
    if (e.target.closest('[data-bx-add-template]')) { e.preventDefault(); send({ type: 'bx:action', action: 'templates' }); return; }
    const inside = root.contains(e.target);
    if (inside || e.target.closest('a, button, form, summary, label')) e.preventDefault();
    if (!inside) return;
    const node = e.target.closest('[data-el]');
    if (node) {
      select(node.dataset.el, true);
      if (e.target.closest('[data-bx-empty]')) send({ type: 'bx:action', action: 'widgets', id: node.dataset.el });
    }
  }, true);
  document.addEventListener('submit', (e) => e.preventDefault(), true);

  document.addEventListener('dblclick', (e) => {
    const node = e.target.closest('[data-el]');
    if (node && root.contains(node)) { e.preventDefault(); send({ type: 'bx:action', action: 'edit', id: node.dataset.el }); }
  });

  document.addEventListener('contextmenu', (e) => {
    const node = e.target.closest('[data-el]');
    if (!node || !root.contains(node)) {
      // Empty space: offer Paste and adding a container.
      e.preventDefault();
      select(null, true);
      send({ type: 'bx:context', id: null, x: e.clientX, y: e.clientY });
      return;
    }
    e.preventDefault();
    select(node.dataset.el, true);
    send({ type: 'bx:context', id: node.dataset.el, x: e.clientX, y: e.clientY });
  });

  // --- keyboard shortcuts go to the editor ---------------------------------------------------
  document.addEventListener('keydown', (e) => {
    const t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    const ctrl = e.ctrlKey || e.metaKey;
    const k = e.key.toLowerCase();
    const handled = (ctrl && ['z', 'y', 'c', 'v', 'd', 's'].includes(k)) || k === 'delete' || k === 'backspace' || k === 'escape';
    if (!handled) return;
    e.preventDefault();
    send({ type: 'bx:key', key: k, ctrl, shift: e.shiftKey });
  });

  // --- drag and drop -------------------------------------------------------------------------
  const isOurDrag = (e) => {
    const types = Array.from((e.dataTransfer && e.dataTransfer.types) || []);
    return types.includes('text/bx') || dragId !== null;
  };
  const draggingContainer = (e) => {
    const types = Array.from((e.dataTransfer && e.dataTransfer.types) || []);
    if (dragId) return isContainer(elById(dragId));
    return types.includes('text/bx-container');
  };

  document.addEventListener('dragstart', (e) => {
    const handle = e.target.closest && e.target.closest('.bx-tool[data-action="move"]');
    if (!handle || !selectedId) return;
    dragId = selectedId;
    const node = elById(dragId);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/bx', JSON.stringify({ kind: 'move', id: dragId }));
    e.dataTransfer.setData(isContainer(node) ? 'text/bx-container' : 'text/bx-widget', '1');
    if (node) {
      try { e.dataTransfer.setDragImage(node, 20, 20); } catch (err) { /* older browsers */ }
      node.classList.add('bx-is-dragging');
    }
    hoverBox.hidden = true;
  });
  document.addEventListener('dragend', () => {
    $$('.bx-is-dragging', root).forEach((n) => n.classList.remove('bx-is-dragging'));
    dragId = null;
    hideDrop();
  });

  function hideDrop() { dropLine.hidden = true; dropZone.hidden = true; dropTarget = null; }

  /** Children of a container (or the root) that are layout elements. */
  const kidsOf = (inner) => Array.from(inner.children).filter((c) => c.hasAttribute('data-el'));
  const axisOf = (inner) => {
    if (inner === root) return { horizontal: false, reverse: false };
    const dir = getComputedStyle(inner).flexDirection;
    return { horizontal: dir.startsWith('row'), reverse: dir.endsWith('reverse') };
  };

  /** Where would something dropped at (x, y) go? {parent: id|null, index, inner} */
  function targetAt(x, y, movingContainer) {
    let node = document.elementFromPoint(x, y);
    if (!node) return null;
    if (node.closest('[data-bx-add-zone]')) return { parent: null, inner: root, index: kidsOf(root).length };
    let el = node.closest('[data-el]');
    if (!el || !root.contains(el)) {
      // Between or below the containers: nearest root position by height.
      const tops = kidsOf(root);
      let index = tops.length;
      for (let i = 0; i < tops.length; i++) {
        const r = tops[i].getBoundingClientRect();
        if (y < r.top + r.height / 2) { index = i; break; }
      }
      return { parent: null, inner: root, index };
    }
    // Never into itself or its own children.
    const moving = elById(dragId);
    if (moving && moving.contains(el)) el = moving;

    // Near the edge of a container (along its parent's axis): beside it, not inside.
    const EDGE = 12;
    let probe = el;
    while (probe) {
      if (isContainer(probe)) {
        const parentInner = probe.parentElement;
        const ax = axisOf(parentInner);
        const r = probe.getBoundingClientRect();
        const before = ax.horizontal ? x - r.left < EDGE : y - r.top < EDGE;
        const after = ax.horizontal ? r.right - x < EDGE : r.bottom - y < EDGE;
        if (before || after) {
          const parentEl = parentInner === root ? null : parentInner.closest('[data-el]');
          const kids = kidsOf(parentInner);
          const i = kids.indexOf(probe);
          return { parent: parentEl ? parentEl.dataset.el : null, inner: parentInner, index: i + ((after !== ax.reverse) ? 1 : 0) };
        }
      }
      if (probe === moving) break;
      probe = isContainer(probe) ? null : probe.parentElement.closest('[data-el]');
    }

    let container, ref = null;
    if (isContainer(el) && el !== moving) {
      container = el;
    } else {
      ref = el;
      container = el.parentElement.closest('.bx-con');
    }
    if (!container || !root.contains(container)) {
      const kids = kidsOf(root);
      const r = el.getBoundingClientRect();
      return { parent: null, inner: root, index: kids.indexOf(el) + (y > r.top + r.height / 2 ? 1 : 0) };
    }
    const inner = container.querySelector(':scope > .bx-inner');
    const kids = kidsOf(inner);
    const ax = axisOf(inner);
    let index = kids.length;
    if (ref) {
      const r = ref.getBoundingClientRect();
      const after = ax.horizontal ? x > r.left + r.width / 2 : y > r.top + r.height / 2;
      index = kids.indexOf(ref) + ((after !== ax.reverse) ? 1 : 0);
    } else {
      for (let i = 0; i < kids.length; i++) {
        const r = kids[i].getBoundingClientRect();
        const firstHalf = ax.horizontal ? x < r.left + r.width / 2 : y < r.top + r.height / 2;
        if (firstHalf !== ax.reverse) { index = i; break; }
      }
    }
    return { parent: container.dataset.el, inner, index };
  }

  function showDrop(t) {
    const kids = kidsOf(t.inner);
    const ax = axisOf(t.inner);
    dropZone.hidden = true;
    dropLine.hidden = false;
    if (!kids.length) {
      const r = pageRect(t.inner === root ? root : t.inner);
      dropLine.hidden = true;
      dropZone.hidden = false;
      place(dropZone, { left: r.left, top: r.top, width: r.width, height: Math.max(r.height, 40) });
      return;
    }
    const ref = kids[Math.min(t.index, kids.length - 1)];
    const r = pageRect(ref);
    const atEnd = t.index >= kids.length;
    if (ax.horizontal) {
      const leftEdge = (atEnd !== ax.reverse) ? r.right : r.left;
      place(dropLine, { left: leftEdge - 2, top: r.top, width: 4, height: r.height });
    } else {
      const topEdge = (atEnd !== ax.reverse) ? r.bottom : r.top;
      place(dropLine, { left: r.left, top: topEdge - 2, width: r.width, height: 4 });
    }
  }

  document.addEventListener('dragover', (e) => {
    if (!isOurDrag(e)) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = dragId ? 'move' : 'copy';
    // Scroll while dragging near the top or bottom.
    if (e.clientY < 50) scrollBy(0, -18); else if (e.clientY > innerHeight - 50) scrollBy(0, 18);
    const t = targetAt(e.clientX, e.clientY, draggingContainer(e));
    if (!t) { hideDrop(); return; }
    dropTarget = t;
    showDrop(t);
  });
  document.addEventListener('dragleave', (e) => {
    if (e.relatedTarget === null && (e.clientX <= 0 || e.clientY <= 0 || e.clientX >= innerWidth || e.clientY >= innerHeight)) hideDrop();
  });
  document.addEventListener('drop', (e) => {
    if (!isOurDrag(e)) return;
    e.preventDefault();
    let source = null;
    try { source = JSON.parse(e.dataTransfer.getData('text/bx') || 'null'); } catch (err) { source = null; }
    const t = dropTarget || targetAt(e.clientX, e.clientY);
    hideDrop();
    if (!source || !t) return;
    send({ type: 'bx:drop', source, target: { parent: t.parent, index: t.index } });
    dragId = null;
  });

  // Keep the outlines in place as the page reflows.
  const refresh = () => { updateSelection(); hoverBox.hidden = true; };
  addEventListener('resize', refresh);
  if ('ResizeObserver' in window) new ResizeObserver(() => updateSelection()).observe(root);
  root.addEventListener('load', () => updateSelection(), true);

  hoverBox.hidden = true; selectBox.hidden = true; dropLine.hidden = true; dropZone.hidden = true;
  send({ type: 'bx:ready' });
})();
