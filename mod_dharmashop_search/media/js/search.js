/**
 * Dharma Shop: подсказки модуля поиска. Подключается модулем (Web Asset Manager), работает со всеми формами `[data-dharmashop-search]`.
 * Настройки формы — data-атрибуты (suggest-url, min-chars, delay, limit, all, categories), тексты — Joomla.Text. Разметка подсказок берётся
 * из шаблонов `<template data-dharmashop-search-template="…">` самой формы (их переопределяют в шаблоне сайта): скрипт клонирует шаблон и
 * подставляет значения в элементы `[data-field]` через textContent — чужая разметка в страницу не попадает. Совпадение с запросом в названии
 * подсвечивается. Ответ запроса: товары, найденные категории (со ссылкой на категорию с тем же запросом) и общее число.
 * Клавиатура: ↑/↓ — выбор, Enter — переход к выбранной подсказке (без выбора — обычная отправка формы), Esc — закрыть.
 * Без JavaScript форма просто отправляется на страницу результатов.
 */
(() => {
  'use strict';

  const text = (key, fallback) => (window.Joomla && Joomla.Text && Joomla.Text._(key)) || fallback;
  const escapePattern = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  const initialise = (form) => {
    const input = form.querySelector('[data-dharmashop-search-input]');
    const box = form.querySelector('[data-dharmashop-search-results]');
    if (!input || !box || !form.dataset.suggestUrl) return;

    const minChars = parseInt(form.dataset.minChars, 10) || 2;
    const delay = parseInt(form.dataset.delay, 10) || 300;
    const limit = parseInt(form.dataset.limit, 10) || 6;
    const templates = {};
    form.querySelectorAll('[data-dharmashop-search-template]').forEach((template) => {
      templates[template.dataset.dharmashopSearchTemplate] = template;
    });
    let timer = 0;
    let controller = null;
    let active = -1;

    const clone = (name) => templates[name] && templates[name].content.firstElementChild.cloneNode(true);
    // Элемент значения: внутри шаблона или сам корень шаблона (например, ссылка «Все результаты»).
    const field = (node, name) => (node.matches('[data-field="' + name + '"]') ? node : node.querySelector('[data-field="' + name + '"]'));
    // Значение в элемент: пустое — элемент убирается (нет артикула, цены и т. п.).
    const fill = (node, name, value) => {
      const target = field(node, name);
      if (!target) return;
      if (value === '' || value === null || value === undefined) target.remove();
      else target.textContent = value;
    };
    // Название с подсветкой слов запроса (<mark> создаётся DOM-методами).
    const highlight = (target, title, query) => {
      const words = query.split(/\s+/).filter(Boolean).map(escapePattern);
      target.textContent = '';
      if (!words.length) {
        target.textContent = title;
        return;
      }
      title.split(new RegExp('(' + words.join('|') + ')', 'ig')).forEach((part, index) => {
        if (index % 2) {
          const mark = document.createElement('mark');
          mark.className = 'bg-warning-subtle p-0 text-reset';
          mark.textContent = part;
          target.append(mark);
        } else if (part) {
          target.append(document.createTextNode(part));
        }
      });
    };

    const options = () => Array.from(box.querySelectorAll('[role="option"]'));
    const open = (state) => {
      box.classList.toggle('show', state);
      input.setAttribute('aria-expanded', state ? 'true' : 'false');
      if (!state) active = -1;
    };
    // index === null — снять выбор; иначе номер подсказки (по кругу).
    const setActive = (index) => {
      const items = options();
      items.forEach((item) => {
        item.classList.remove('active');
        item.removeAttribute('aria-selected');
      });
      active = items.length && index !== null ? (index + items.length) % items.length : -1;
      if (active >= 0) {
        items[active].classList.add('active');
        items[active].setAttribute('aria-selected', 'true');
        items[active].scrollIntoView({ block: 'nearest' });
      }
    };
    const message = (content) => {
      const node = clone('message');
      if (!node) return;
      fill(node, 'message', content);
      box.replaceChildren(node);
      open(true);
    };

    // Адрес «всех результатов»: действие формы и её скрытые поля плюс запрос.
    const resultsUrl = (query) => {
      const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
      form.querySelectorAll('input[type="hidden"]').forEach((hidden) => url.searchParams.set(hidden.name, hidden.value));
      url.searchParams.set('q', query);
      return url.toString();
    };

    const render = (data, query) => {
      if (!data.items || !data.items.length) {
        message(text('MOD_DHARMASHOP_SEARCH_EMPTY', 'Nothing found'));
        return;
      }

      const nodes = [];

      if (form.dataset.categories === '1' && data.categories && data.categories.length > 1 && templates.category) {
        const row = document.createElement('div');
        row.className = 'd-flex flex-wrap gap-2 px-3 pt-3 pb-2';
        data.categories.forEach((category) => {
          const node = clone('category');
          node.href = category.url;
          fill(node, 'title', category.title);
          fill(node, 'count', String(category.count));
          row.append(node);
        });
        nodes.push(row);
      }

      data.items.forEach((item, index) => {
        const node = clone('item');
        if (!node) return;
        node.href = item.url;
        node.id = form.id + '-option-' + index;
        const title = field(node, 'title');
        if (title) highlight(title, item.title, query);
        const image = field(node, 'image');
        if (image) {
          if (item.image) image.src = item.image;
          else image.remove();
        }
        fill(node, 'category', item.category);
        fill(node, 'sku', item.sku ? text('MOD_DHARMASHOP_SEARCH_SKU', 'SKU: %s').replace('%s', item.sku) : '');
        fill(node, 'price', item.price);
        fill(node, 'stock', item.in_stock ? '' : text('MOD_DHARMASHOP_SEARCH_OUT_OF_STOCK', 'Out of stock'));
        nodes.push(node);
      });

      if (form.dataset.all === '1' && data.total > data.items.length && templates.all) {
        const all = clone('all');
        all.href = resultsUrl(query);
        fill(all, 'label', text('MOD_DHARMASHOP_SEARCH_ALL', 'All results (%d)').replace('%d', String(data.total)));
        nodes.push(all);
      }

      box.replaceChildren(...nodes);
      open(true);
      setActive(null);
    };

    const search = () => {
      const query = input.value.trim();
      if (query.length < minChars) {
        open(false);
        return;
      }
      if (controller) controller.abort();
      controller = new AbortController();
      const url = new URL(form.dataset.suggestUrl, window.location.href);
      url.searchParams.set('q', query);
      url.searchParams.set('limit', String(limit));

      fetch(url.toString(), { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
        .then((response) => {
          if (!response.ok) throw new Error(String(response.status));
          return response.json();
        })
        .then((data) => {
          // Ответ на уже устаревший запрос не показываем.
          if (input.value.trim() === query) render(data, query);
        })
        .catch((error) => {
          if (error.name !== 'AbortError') message(text('MOD_DHARMASHOP_SEARCH_ERROR', 'Search error'));
        });
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(search, delay);
    });
    input.addEventListener('focus', () => {
      if (box.childElementCount && input.value.trim().length >= minChars) open(true);
    });
    input.addEventListener('keydown', (event) => {
      if (!box.classList.contains('show')) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActive(active + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(active < 0 ? -1 : active - 1);
      } else if (event.key === 'Enter' && active >= 0) {
        event.preventDefault();
        options()[active].click();
      } else if (event.key === 'Escape') {
        open(false);
      }
    });
    document.addEventListener('click', (event) => {
      if (!form.contains(event.target)) open(false);
    });
    form.addEventListener('submit', () => open(false));
  };

  document.querySelectorAll('[data-dharmashop-search]').forEach(initialise);
})();
