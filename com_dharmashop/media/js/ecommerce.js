/**
 * Электронная коммерция (C98): отправляет в массив dataLayer (Яндекс Метрика, GTM/GA4) просмотр товара и покупку со страницы
 * и добавление/удаление из корзины по ответам сервера. Данные готовит сервер; скрипт только упаковывает их в формат счётчика.
 * Подключается, только когда в настройках магазина включена «Электронная коммерция». Другие счётчики слушают событие
 * `dharmashop:ecommerce` (detail: action, currency, products, actionField).
 */
(() => {
  'use strict';

  const options = (window.Joomla && Joomla.getOptions('com_dharmashop.ecommerce')) || {};
  const container = /^[A-Za-z_$][\w$]*$/.test(options.container || '') ? options.container : 'dataLayer';
  const seen = new Set();

  const send = (currency, event) => {
    if (!event || !event.action || !Array.isArray(event.products) || !event.products.length) return;
    const block = { products: event.products };
    if (event.actionField) block.actionField = event.actionField;
    window[container] = window[container] || [];
    window[container].push({ ecommerce: { currencyCode: currency || options.currency || 'RUB', [event.action]: block } });
    document.dispatchEvent(new CustomEvent('dharmashop:ecommerce', {
      detail: { action: event.action, currency: currency || options.currency, products: event.products, actionField: event.actionField || null },
    }));
  };

  // События страницы: просмотр товара, покупка.
  (options.events || []).forEach((event) => send(options.currency, event));

  // События корзины: ответ сервера на добавление, изменение и удаление передаёт изменения состава.
  document.addEventListener('dharmashop:cart-updated', (event) => {
    const data = event.detail && event.detail.ecommerce;
    if (!data || !Array.isArray(data.events)) return;
    if (data.uid) {
      if (seen.has(data.uid)) return;
      seen.add(data.uid);
    }
    data.events.forEach((item) => send(data.currency, item));
  });
})();
