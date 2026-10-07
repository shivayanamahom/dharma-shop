/**
 * Dharma Shop: список товаров категории. Сортировка, вид и страницы подгружаются без перезагрузки и без смены адреса
 * (адрес страницы остаётся каноническим — для поисковиков); без JavaScript работают обычные формы и ссылки.
 * Фрагмент приходит с того же адреса страницы с параметрами format=raw, sort, product_view, start.
 */
(() => {
  'use strict';

  const initialise = (form) => {
    if (form.dataset.dharmashopListingReady) return;
    form.dataset.dharmashopListingReady = '1';
    const container = document.querySelector('[data-dharmashop-listing]');
    if (!container) return;
    const submit = form.querySelector('[data-dharmashop-listing-submit]');
    if (submit) submit.hidden = true;
    let controller = null;

    const state = () => {
      const data = new FormData(form);
      return { sort: String(data.get('sort') || ''), view: String(data.get('product_view') || '') };
    };

    const load = (start) => {
      if (controller) controller.abort();
      controller = new AbortController();
      const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
      const current = state();
      url.search = '';
      url.searchParams.set('format', 'raw');
      if (current.sort) url.searchParams.set('sort', current.sort);
      if (current.view) url.searchParams.set('product_view', current.view);
      if (start > 0) url.searchParams.set('start', String(start));
      container.setAttribute('aria-busy', 'true');
      container.classList.add('opacity-50');

      fetch(url.toString(), { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => {
          if (!response.ok) throw new Error(String(response.status));
          return response.text();
        })
        .then((html) => {
          container.innerHTML = html;
          if (container.getBoundingClientRect().top < 0) {
            container.scrollIntoView({ block: 'start' });
          }
        })
        .catch((error) => {
          if (error.name === 'AbortError') return;
          // Не получилось подгрузить: обычная отправка формы (страница с параметрами).
          form.submit();
        })
        .finally(() => {
          container.removeAttribute('aria-busy');
          container.classList.remove('opacity-50');
        });
    };

    form.addEventListener('change', (event) => {
      if (event.target.matches('[name="sort"], [name="product_view"]')) load(0);
    });
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      load(0);
    });
    container.addEventListener('click', (event) => {
      // Штатная постраничная навигация Joomla: номер страницы берётся из ссылки (limitstart), остальное — из формы.
      const link = event.target.closest('.pagination a[href]');
      if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
      event.preventDefault();
      const params = new URL(link.href, window.location.href).searchParams;
      load(parseInt(params.get('start') || params.get('limitstart') || '0', 10) || 0);
    });
  };

  const boot = () => document.querySelectorAll('[data-dharmashop-listing-controls]').forEach(initialise);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
