/**
 * Тост добавления: HTML приходит из переопределяемого cart/toast.php.
 * Один тост на страницу; новое добавление заменяет его и начинает отсчёт заново.
 * Bootstrap управляет анимацией, паузой при hover/focus и ручным закрытием.
 */
(() => {
  'use strict';
  let current = null;

  const remove = () => {
    if (!current) return;
    current.instance.dispose();
    current.host.remove();
    current = null;
  };

  document.addEventListener('dharmashop:cart-updated', (event) => {
    const notification = event.detail && event.detail.notification;
    if (!notification || notification.type !== 'toast' || !notification.html || !window.bootstrap?.Toast) return;

    const fragment = document.createElement('template');
    fragment.innerHTML = notification.html;
    const host = fragment.content.querySelector('[data-dharmashop-cart-toast-host]');
    const element = host?.querySelector('[data-dharmashop-cart-toast]');
    if (!element) return;

    remove();
    document.body.append(host);
    // Top layer stays above the module's offcanvas without changing its styles.
    if (typeof host.showPopover === 'function') host.showPopover();
    else host.removeAttribute('popover');
    const instance = bootstrap.Toast.getOrCreateInstance(element);
    current = { host, instance };
    element.addEventListener('hidden.bs.toast', () => {
      instance.dispose();
      host.remove();
      if (current?.host === host) current = null;
    }, { once: true });
    instance.show();
  });

  // Не возвращать устаревшее уведомление из кеша истории браузера.
  window.addEventListener('pagehide', remove);
})();
