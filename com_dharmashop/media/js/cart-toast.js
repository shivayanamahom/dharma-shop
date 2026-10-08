/**
 * Тост добавления: HTML приходит из переопределяемого cart/toast.php.
 * Один тост на страницу; новое добавление заменяет его и начинает отсчёт заново.
 * Bootstrap управляет появлением и ручным закрытием; общий отсчёт синхронизирует линию и автозакрытие.
 */
(() => {
  'use strict';
  let current = null;

  const countdown = (element, instance) => {
    const duration = Number(element.dataset.bsDelay) || 0;
    if (element.dataset.bsAutohide === 'false' || duration <= 0) return () => {};

    const line = element.querySelector('[data-dharmashop-cart-toast-progress]');
    let animation = null;
    let remaining = duration;
    let started = 0;
    let timer = null;
    let shown = false;
    let hovered = element.matches(':hover');
    let focused = element.contains(document.activeElement);

    const pause = () => {
      if (timer !== null) {
        window.clearTimeout(timer);
        timer = null;
        remaining = Math.max(0, remaining - (performance.now() - started));
      }
      animation?.pause();
    };
    const resume = () => {
      if (!shown || hovered || focused || timer !== null) return;
      started = performance.now();
      animation?.play();
      timer = window.setTimeout(() => {
        timer = null;
        remaining = 0;
        instance.hide();
      }, remaining);
    };
    const onShown = () => {
      shown = true;
      if (line && typeof line.animate === 'function') {
        animation = line.animate([
          { transform: 'scaleX(1)', transformOrigin: 'left' },
          { transform: 'scaleX(0)', transformOrigin: 'left' },
        ], {
          duration, fill: 'forwards',
          easing: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'steps(1, end)' : 'linear',
        });
        animation.pause();
        animation.currentTime = 0;
      }
      resume();
    };
    const onHide = () => { shown = false; pause(); };
    const onInteraction = (event) => {
      if (event.relatedTarget && element.contains(event.relatedTarget)) return;
      if (event.type === 'mouseenter' || event.type === 'mouseleave') hovered = event.type === 'mouseenter';
      else focused = event.type === 'focusin';
      if (hovered || focused) pause();
      else resume();
    };
    element.addEventListener('shown.bs.toast', onShown);
    element.addEventListener('hide.bs.toast', onHide);
    const events = ['mouseenter', 'mouseleave', 'focusin', 'focusout'];
    events.forEach((name) => element.addEventListener(name, onInteraction));
    return () => {
      shown = false;
      pause();
      animation?.cancel();
      element.removeEventListener('shown.bs.toast', onShown);
      element.removeEventListener('hide.bs.toast', onHide);
      events.forEach((name) => element.removeEventListener(name, onInteraction));
    };
  };

  const remove = () => {
    if (!current) return;
    const previous = current;
    current = null;
    previous.stopCountdown();
    // Завершить переход перед dispose: отложенный callback Bootstrap иначе обращается к уже удалённому элементу.
    if (previous.element.classList.contains('showing')) previous.element.dispatchEvent(new Event('transitionend'));
    bootstrap.Toast.getInstance(previous.element)?.dispose();
    previous.host.remove();
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
    // Автозакрытием управляет один отсчёт с линией: Bootstrap при выходе мыши начинает полную задержку заново.
    const instance = bootstrap.Toast.getOrCreateInstance(element, { autohide: false });
    const stopCountdown = countdown(element, instance);
    current = { host, element, stopCountdown };
    element.addEventListener('hidden.bs.toast', () => {
      stopCountdown();
      instance.dispose();
      host.remove();
      if (current?.host === host) current = null;
    }, { once: true });
    instance.show();
  });

  // Не возвращать устаревшее уведомление из кеша истории браузера.
  window.addEventListener('pagehide', remove);
})();
