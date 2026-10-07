/**
 * Dharma Shop: AJAX-пагинация (включается настройкой магазина «Каталог → Страницы списка подгружаются без перезагрузки»).
 * Пагинация остаётся настоящей: адрес страницы меняется (`?start=N`, история браузера, «Назад»), заголовок и теги head
 * обновляются, без JavaScript и для поисковиков работают обычные ссылки. Скрипт перехватывает событие `dharmashop:navigate`
 * (его вызывает listing.js), загружает страницу целиком и заменяет контейнер `#dharma_shop`.
 */
(() => {
  'use strict';

  const CONTAINER = '#dharma_shop';
  let controller = null;

  // Теги head, значения которых зависят от страницы списка.
  const syncHead = (doc) => {
    document.title = doc.title;
    doc.head.querySelectorAll('meta[name="description"], meta[name="robots"], meta[property^="og:"], meta[name^="twitter:"]').forEach((meta) => {
      const key = meta.hasAttribute('name') ? 'name' : 'property';
      const current = document.head.querySelector('meta[' + key + '="' + meta.getAttribute(key) + '"]');
      if (current) current.setAttribute('content', meta.getAttribute('content') || '');
    });
    const canonical = doc.head.querySelector('link[rel="canonical"]');
    const currentCanonical = document.head.querySelector('link[rel="canonical"]');
    if (canonical && currentCanonical) currentCanonical.setAttribute('href', canonical.getAttribute('href') || '');
  };

  const load = (url, push) => {
    const container = document.querySelector(CONTAINER);
    if (!container) {
      window.location.assign(url);
      return;
    }
    if (controller) controller.abort();
    controller = new AbortController();
    container.setAttribute('aria-busy', 'true');
    container.classList.add('opacity-50');

    fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((response) => {
        if (!response.ok) throw new Error(String(response.status));
        return response.text();
      })
      .then((html) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const next = doc.querySelector(CONTAINER);
        if (!next) throw new Error('no container');
        const top = container.getBoundingClientRect().top;
        container.replaceWith(next);
        syncHead(doc);
        if (push) window.history.pushState({ dharmaShop: 1 }, '', url);
        document.dispatchEvent(new CustomEvent('dharmashop:updated'));
        if (top < 0) next.scrollIntoView({ block: 'start' });
      })
      .catch((error) => {
        if (error.name === 'AbortError') return;
        // Не получилось подгрузить: обычный переход.
        window.location.assign(url);
      });
  };

  document.addEventListener('dharmashop:navigate', (event) => {
    if (!document.querySelector(CONTAINER)) return;
    event.preventDefault();
    load(event.detail.url, true);
  });

  // Первая запись истории помечается, чтобы «Назад» к ней тоже подгружал страницу без перезагрузки.
  window.history.replaceState({ dharmaShop: 1 }, '', window.location.href);
  window.addEventListener('popstate', (event) => {
    if (event.state && event.state.dharmaShop) load(window.location.href, false);
  });
})();
