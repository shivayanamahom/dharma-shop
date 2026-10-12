/** One range, two keyboard-accessible handles. Only numeric inputs are submitted. */
(() => {
  'use strict';
  const selector = '[data-dharmashop-slider]';
  const parts = (box) => ({
    low: box.querySelector('[data-dharmashop-slider-min]'),
    high: box.querySelector('[data-dharmashop-slider-max]'),
    from: document.getElementById(box.dataset.from),
    to: document.getElementById(box.dataset.to),
    track: box.querySelector('[data-slider-track]'),
  });
  const paint = (box, p) => {
    const min = Number(p.low.min), max = Number(p.high.max);
    const low = Number(p.low.value), high = Number(p.high.value);
    box.style.setProperty('--range-from', `${100 * (low - min) / (max - min)}%`);
    box.style.setProperty('--range-to', `${100 * (high - min) / (max - min)}%`);
    p.low.setAttribute('aria-valuemax', String(high));
    p.high.setAttribute('aria-valuemin', String(low));
    p.low.setAttribute('aria-valuetext', p.low.value);
    p.high.setAttribute('aria-valuetext', p.high.value);
  };
  const syncNumbers = (box, p) => {
    p.low.value = p.from.value === '' ? p.low.min : p.from.value;
    p.high.value = p.to.value === '' ? p.high.max : p.to.value;
    // Keep typed values, including values outside current facet bounds.
    if (Number(p.low.value) > Number(p.high.value)) p.high.value = p.low.value;
    paint(box, p);
  };
  const init = () => document.querySelectorAll(selector).forEach((box) => {
    const p = parts(box);
    if (!p.low || !p.high || !p.from || !p.to || !p.track) return;
    syncNumbers(box, p);
    box.hidden = false;
  });
  document.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    const box = target.closest(selector);
    if (box) {
      const p = parts(box);
      if (target === p.low && Number(p.low.value) > Number(p.high.value)) p.low.value = p.high.value;
      if (target === p.high && Number(p.high.value) < Number(p.low.value)) p.high.value = p.low.value;
      p.from.value = Number(p.low.value) <= Number(p.low.min) ? '' : p.low.value;
      p.to.value = Number(p.high.value) >= Number(p.high.max) ? '' : p.high.value;
      paint(box, p);
    } else if (target.type === 'number') {
      target.form?.querySelectorAll(selector).forEach((holder) => {
        const p = parts(holder);
        if (target === p.from || target === p.to) syncNumbers(holder, p);
      });
    }
  });
  // The nearest handle receives track clicks. Coincident handles can separate.
  document.addEventListener('pointerdown', (event) => {
    const box = event.target.closest?.(selector);
    if (!box || (event.pointerType === 'mouse' && event.button !== 0)) return;
    const p = parts(box), rect = p.track.getBoundingClientRect();
    if (!rect.width) return;
    const min = Number(p.low.min), max = Number(p.high.max);
    const raw = (x) => min + Math.min(1, Math.max(0, (x - rect.left) / rect.width)) * (max - min);
    const value = raw(event.clientX), low = Number(p.low.value), high = Number(p.high.value);
    const handle = Math.abs(value - low) < Math.abs(value - high) || (low === high && value <= low) ? p.low : p.high;
    event.preventDefault();
    handle.focus({ preventScroll: true });
    box.setPointerCapture(event.pointerId);
    const move = (e) => {
      if (e.pointerId !== event.pointerId) return;
      const step = Number(handle.step) || 1;
      let next = min + Math.round((raw(e.clientX) - min) / step) * step;
      next = handle === p.low ? Math.min(next, Number(p.high.value)) : Math.max(next, Number(p.low.value));
      handle.value = String(next);
      handle.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const finish = (e) => {
      if (e.pointerId !== event.pointerId) return;
      box.removeEventListener('pointermove', move);
      box.removeEventListener('pointerup', finish);
      box.removeEventListener('pointercancel', finish);
      if (box.hasPointerCapture(e.pointerId)) box.releasePointerCapture(e.pointerId);
      handle.dispatchEvent(new Event('change', { bubbles: true }));
    };
    box.addEventListener('pointermove', move);
    box.addEventListener('pointerup', finish);
    box.addEventListener('pointercancel', finish);
    move(event);
  });
  document.addEventListener('reset', () => setTimeout(init, 0));
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('dharmashop:updated', init);
  if (document.readyState !== 'loading') init();
})();
