/** Каскад без применения выдачи: обновляет только форму, когда товары ждут кнопки «Применить». */
(() => {
  'use strict';
  const FORM = 'form[data-dharmashop-filter][data-cascade="1"]';
  const jobs = new Map();
  const state = (form) => JSON.stringify(Array.from(new FormData(form).entries()));
  const refresh = async (form) => {
    if (!form.isConnected) return;
    const previous = jobs.get(form.id);
    if (previous?.controller) previous.controller.abort();
    const controller = new AbortController();
    const job = { controller };
    jobs.set(form.id, job);
    const before = state(form);
    const url = new URL(form.action, location.href);
    url.searchParams.delete('filter');
    for (const [key] of Array.from(url.searchParams)) {
      if (key.startsWith('filter[') || ['start', 'limitstart'].includes(key)) url.searchParams.delete(key);
    }
    new FormData(form).forEach((value, key) => { if (typeof value === 'string' && value !== '') url.searchParams.append(key, value); });
    url.searchParams.set('task', 'filter.refresh');
    url.searchParams.set('format', 'json');
    form.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) throw new Error(String(response.status));
      const data = await response.json();
      if (!data.success || !data.html) throw new Error('no filter');
      if (jobs.get(form.id) !== job || !form.isConnected || state(form) !== before) return;
      const doc = new DOMParser().parseFromString(data.html, 'text/html');
      const fresh = doc.getElementById(form.id);
      if (!fresh) throw new Error('no form');
      const active = document.activeElement;
      const focus = form.contains(active) ? { id: active.id, name: active.name, value: active.value } : null;
      form.querySelectorAll('.accordion-collapse').forEach((old) => {
        const next = fresh.querySelector('#' + CSS.escape(old.id));
        if (!next) return;
        const show = old.classList.contains('show');
        next.classList.toggle('show', show);
        const button = fresh.querySelector('[aria-controls="' + old.id + '"]');
        if (button) { button.classList.toggle('collapsed', !show); button.setAttribute('aria-expanded', String(show)); }
      });
      form.replaceWith(fresh);
      if (focus) {
        const target = focus.id ? fresh.querySelector('#' + CSS.escape(focus.id)) : Array.from(fresh.elements).find((el) => el.name === focus.name && el.value === focus.value);
        target?.focus({ preventScroll: true });
      }
      document.dispatchEvent(new CustomEvent('dharmashop:updated'));
    } catch (error) {
      // Не применяем выдачу при сетевой ошибке: обычная GET-кнопка остаётся рабочей.
      if (error.name !== 'AbortError') form.dispatchEvent(new CustomEvent('dharmashop:filter-error', { bubbles: true }));
    } finally {
      if (jobs.get(form.id) === job) { form.removeAttribute('aria-busy'); jobs.delete(form.id); }
    }
  };
  const schedule = (event, delay) => {
    const form = event.target.closest?.(FORM);
    // Режим «сразу» уже получает каскад вместе с новой выдачей в filter.js: второй запрос не нужен.
    if (!form || form.dataset.apply === 'instant' || event.target.matches('[data-filter-search]')) return;
    const old = jobs.get(form.id);
    clearTimeout(old?.timer);
    old?.controller?.abort();
    jobs.set(form.id, { timer: setTimeout(() => refresh(form), delay) });
  };
  document.addEventListener('change', (event) => schedule(event, 100));
  document.addEventListener('input', (event) => {
    if (event.target.matches('input[type="number"], input[type="text"]')) schedule(event, 700);
  });
  document.addEventListener('submit', (event) => {
    const form = event.target.closest?.(FORM);
    const job = form && jobs.get(form.id);
    clearTimeout(job?.timer); job?.controller?.abort();
  });
})();
