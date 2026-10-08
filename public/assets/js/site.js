/* Public site behaviour. Vanilla JS, CSP-safe (no inline handlers, no eval). */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const nonce = (document.querySelector('script[nonce]') || {}).nonce || '';

  // --- mobile navigation ---------------------------------------------------------
  const toggle = $('[data-nav-toggle]');
  if (toggle) {
    const nav = $('#mobile-nav');
    toggle.addEventListener('click', () => {
      const open = nav.hidden;
      nav.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
      document.body.style.overflow = open ? 'hidden' : '';
    });
  }
  // Close the market switcher when clicking elsewhere.
  document.addEventListener('click', (e) => {
    $$('details.market-switch[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
  });

  // --- billing toggle --------------------------------------------------------------
  $$('[data-billing-scope]').forEach((scope) => {
    $$('[data-billing]', scope).forEach((btn) => btn.addEventListener('click', () => {
      const period = btn.dataset.billing;
      $$('[data-billing]', scope).forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
      $$('[data-price]', scope).forEach((p) => {
        const amount = p.dataset[period] || p.dataset.annual;
        if (amount) $('[data-amount]', p).textContent = amount;
        const note = $('[data-note]', p);
        if (note) note.textContent = p.dataset[period + 'Note'] || '';
      });
    }));
  });

  // --- page URL + attribution on every form -------------------------------------------
  function prepareForm(form) {
    const page = form.querySelector('input[name="page_url"]');
    if (page) page.value = location.href.slice(0, 1000);
  }

  // --- lead forms --------------------------------------------------------------------
  async function submitForm(form) {
    prepareForm(form);
    const status = $('.form-status', form);
    const btn = $('button[type="submit"]', form);
    $$('.field-error', form).forEach((el) => { el.hidden = true; el.textContent = ''; });
    $$('.invalid', form).forEach((el) => el.classList.remove('invalid'));
    if (!form.checkValidity()) {
      const first = $(':invalid', form);
      $$(':invalid', form).forEach((input) => {
        const field = input.closest('.form-field');
        if (!field) return;
        field.classList.add('invalid');
        const err = $('.field-error', field);
        if (err) { err.textContent = input.validationMessage; err.hidden = false; }
      });
      if (first) first.focus();
      return;
    }
    btn.disabled = true;
    const label = btn.textContent;
    btn.textContent = 'Sending…';
    status.hidden = true;
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({ ok: false, error: 'Something went wrong. Please try again.' }));
      if (data.ok) {
        form.classList.add('done');
        status.className = 'form-status ok';
        status.textContent = data.message || 'Thank you!';
        status.hidden = false;
        if (window.dataLayer) window.dataLayer.push({ event: 'lead_submitted', form: form.querySelector('[name=form]').value });
        if (typeof window.gtag === 'function') window.gtag('event', 'generate_lead');
        if (typeof window.fbq === 'function') window.fbq('track', 'Lead');
        if (data.redirect) setTimeout(() => { location.href = data.redirect; }, 600);
        if (data.download) setTimeout(() => { location.href = data.download; }, 400);
        return;
      }
      if (data.fieldErrors) {
        Object.entries(data.fieldErrors).forEach(([name, msg]) => {
          const err = form.querySelector('[data-error-for="' + CSS.escape(name) + '"]');
          if (err) { err.textContent = msg; err.hidden = false; const f = err.closest('.form-field'); if (f) f.classList.add('invalid'); }
        });
      }
      status.className = 'form-status err';
      status.textContent = data.error || 'Please check the form and try again.';
      status.hidden = false;
    } catch (err) {
      status.className = 'form-status err';
      status.textContent = 'Could not send. Check your connection and try again.';
      status.hidden = false;
    } finally {
      btn.disabled = false;
      btn.textContent = label;
    }
  }
  document.addEventListener('submit', (e) => {
    const form = e.target.closest('[data-lead-form]');
    if (!form) return;
    e.preventDefault();
    submitForm(form);
  });

  // --- product CTA: open the configured form in a dialog -------------------------------
  const ctaDialog = $('#cta-dialog');
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-cta-form]');
    if (!btn || !ctaDialog) return;
    e.preventDefault();
    const key = btn.dataset.ctaForm || 'default';
    const body = $('[data-cta-dialog-body]', ctaDialog);
    body.innerHTML = '<p class="muted">Loading…</p>';
    ctaDialog.showModal();
    try {
      const params = new URLSearchParams({ product: btn.dataset.productId || '', label: btn.dataset.ctaLabel || '', location: btn.dataset.ctaLocation || '' });
      const res = await fetch('/forms/render/' + encodeURIComponent(key) + '?' + params, { headers: { Accept: 'text/html' } });
      if (!res.ok) throw new Error('missing');
      body.innerHTML = await res.text();
      const first = body.querySelector('input:not([type=hidden]), select, textarea');
      if (first) first.focus();
    } catch (err) {
      body.innerHTML = '<p>Sorry, the form could not be loaded. Please use the contact page.</p>';
    }
  });

  document.addEventListener('click', (e) => {
    const close = e.target.closest('[data-close-dialog]');
    if (close) close.closest('dialog').close();
    const scroll = e.target.closest('[data-scroll-to]');
    if (scroll) { const t = document.getElementById(scroll.dataset.scrollTo); if (t) { t.scrollIntoView({ behavior: 'smooth' }); const i = t.querySelector('input:not([type=hidden])'); if (i) setTimeout(() => i.focus(), 500); } }
    const link = e.target.closest('a[href="#form"]');
    if (link) { const t = document.getElementById('form'); if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); } }
    const copy = e.target.closest('[data-copy-link]');
    if (copy && navigator.clipboard) navigator.clipboard.writeText(copy.dataset.copyLink).then(() => { copy.title = 'Copied'; });
  });
  // Close a dialog by clicking the backdrop.
  $$('dialog').forEach((d) => d.addEventListener('click', (e) => { if (e.target === d) d.close(); }));

  // --- popups ------------------------------------------------------------------------
  const isMobile = matchMedia('(max-width: 768px)').matches;
  function seen(id, days) {
    try {
      const at = parseInt(localStorage.getItem('popup-' + id) || '0', 10);
      return at && (Date.now() - at) < days * 86400000;
    } catch (e) { return false; }
  }
  function remember(id) { try { localStorage.setItem('popup-' + id, String(Date.now())); } catch (e) { /* ignore */ } }
  function openPopup(p) { if (!p.open) { p.showModal(); remember(p.dataset.popup); } }
  $$('dialog[data-popup]').forEach((p) => {
    const trigger = p.dataset.trigger, device = p.dataset.device;
    if (trigger === 'CLICK') return;
    if ((device === 'MOBILE' && !isMobile) || (device === 'DESKTOP' && isMobile)) return;
    if (seen(p.dataset.popup, parseInt(p.dataset.frequency || '7', 10))) return;
    if (trigger === 'IMMEDIATE') setTimeout(() => openPopup(p), 300);
    else if (trigger === 'DELAY') setTimeout(() => openPopup(p), parseInt(p.dataset.delay || '5', 10) * 1000);
    else if (trigger === 'SCROLL') {
      const pct = parseInt(p.dataset.scroll || '50', 10);
      const onScroll = () => {
        const scrolled = (scrollY + innerHeight) / document.documentElement.scrollHeight * 100;
        if (scrolled >= pct) { openPopup(p); removeEventListener('scroll', onScroll); }
      };
      addEventListener('scroll', onScroll, { passive: true });
    } else if (trigger === 'EXIT_INTENT') {
      const onLeave = (e) => { if (e.clientY <= 0) { openPopup(p); document.removeEventListener('mouseout', onLeave); } };
      if (isMobile) setTimeout(() => openPopup(p), 15000); else document.addEventListener('mouseout', onLeave);
    }
  });
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-popup-open], a[href^="#popup-"]');
    if (!opener) return;
    const id = opener.dataset.popupOpen || opener.getAttribute('href').replace('#popup-', '');
    const p = document.querySelector('dialog[data-popup="' + CSS.escape(id) + '"]');
    if (p) { e.preventDefault(); p.showModal(); }
  });

  // --- slider -------------------------------------------------------------------------
  $$('[data-slider]').forEach((slider) => {
    const track = $('.slider-track', slider), slides = $$('.slide', slider), dots = $$('.slider-dots button', slider);
    let i = 0, timer = null;
    const go = (n) => {
      i = (n + slides.length) % slides.length;
      track.style.transform = 'translateX(' + (-100 * i) + '%)';
      dots.forEach((d, k) => d.setAttribute('aria-current', String(k === i)));
    };
    $$('[data-slide]', slider).forEach((b) => b.addEventListener('click', () => { go(i + parseInt(b.dataset.slide, 10)); restart(); }));
    dots.forEach((d) => d.addEventListener('click', () => { go(parseInt(d.dataset.goto, 10)); restart(); }));
    const auto = parseInt(slider.dataset.autoplay || '0', 10);
    const restart = () => { if (timer) clearInterval(timer); if (auto && !matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => go(i + 1), auto); };
    slider.addEventListener('mouseenter', () => timer && clearInterval(timer));
    slider.addEventListener('mouseleave', restart);
    let x0 = null;
    slider.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', (e) => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1)); x0 = null; restart(); });
    go(0); restart();
  });

  // --- video facades: load the player only when asked -----------------------------------
  document.addEventListener('click', (e) => {
    const facade = e.target.closest('[data-video]');
    if (!facade) return;
    const src = facade.dataset.video;
    if (!/^https:\/\/(www\.youtube-nocookie\.com|player\.vimeo\.com)\//.test(src)) return;
    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen';
    iframe.allowFullscreen = true;
    iframe.title = facade.getAttribute('aria-label') || 'Video';
    facade.replaceWith(iframe);
  });

  // --- consent ---------------------------------------------------------------------------
  function activateTags() {
    $$('script[type="text/plain"][data-consent="analytics"]').forEach((inert) => {
      const s = document.createElement('script');
      [...inert.attributes].forEach((a) => { if (a.name !== 'type' && a.name !== 'data-consent') s.setAttribute(a.name, a.value); });
      s.nonce = inert.nonce || nonce;
      s.textContent = inert.textContent;
      inert.replaceWith(s);
    });
  }
  const banner = $('[data-consent-banner]');
  document.addEventListener('click', (e) => {
    const choice = e.target.closest('[data-consent]:not(script)');
    if (choice && banner) {
      const value = choice.dataset.consent;
      const secure = location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = 'consent=' + value + '; Path=/; Max-Age=' + (180 * 86400) + '; SameSite=Lax' + secure;
      banner.hidden = true;
      if (value === 'granted') activateTags();
    }
    if (e.target.closest('[data-consent-open]') && banner) banner.hidden = false;
  });
})();
