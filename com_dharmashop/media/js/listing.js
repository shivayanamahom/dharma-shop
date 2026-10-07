/**
 * Dharma Shop: список товаров категории. Сортировка и вид подгружаются без перезагрузки и без смены адреса
 * (адрес страницы остаётся каноническим — для поисковиков); без JavaScript работают обычные формы и ссылки.
 * Фрагмент приходит с того же адреса страницы с параметрами format=raw, sort, product_view.
 * Страницы пагинации — настоящие адреса (`?start=N`): ссылки открываются обычным переходом, сортировка и вид, выбранные
 * на странице, добавляются к адресу страницы. Если открыта страница пагинации, смена сортировки или вида тоже идёт обычным переходом.
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
    const initial = state();
    const onPaginationPage = () => /[?&](start|limitstart)=/.test(window.location.search);

    const load = () => {
      if (controller) controller.abort();
      controller = new AbortController();
      const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
      const current = state();
      url.search = '';
      url.searchParams.set('format', 'raw');
      if (current.sort) url.searchParams.set('sort', current.sort);
      if (current.view) url.searchParams.set('product_view', current.view);
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
      if (!event.target.matches('[name="sort"], [name="product_view"]')) return;
      // На странице пагинации новая сортировка меняет и состав страницы: обычный переход на первую страницу.
      if (onPaginationPage()) form.submit(); else load();
    });
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (onPaginationPage()) form.submit(); else load();
    });
    document.addEventListener('click', (event) => {
      // Штатная постраничная навигация Joomla: переход на адрес страницы; сортировка и вид из формы добавляются, если они не по умолчанию.
      const link = event.target.closest('[data-dharmashop-listing] .pagination a[href]');
      if (!link || link.getAttribute('href') === '#' || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
      event.preventDefault();
      const url = new URL(link.href, window.location.href);
      const current = state();
      const fromUrl = new URLSearchParams(window.location.search);
      url.searchParams.delete('sort');
      url.searchParams.delete('product_view');
      if (current.sort && (current.sort !== initial.sort || fromUrl.has('sort'))) url.searchParams.set('sort', current.sort);
      if (current.view && (current.view !== initial.view || fromUrl.has('product_view'))) url.searchParams.set('product_view', current.view);
      window.location.assign(url.toString());
    });
  };

  const boot = () => document.querySelectorAll('[data-dharmashop-listing-controls]').forEach(initialise);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
