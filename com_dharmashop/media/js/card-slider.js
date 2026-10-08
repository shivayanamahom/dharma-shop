/**
 * Dharma Shop: слайдер фото в карточках товаров (Swiper). Слайдеры создаются лениво — когда карточка приближается к области
 * видимости; до этого видна первая картинка (без скрипта и после сбоя — тоже). Подхватывает карточки, добавленные
 * подгрузкой («Показать ещё», бесконечная прокрутка, замена страницы) — по событию `dharmashop:updated`.
 * Разметка — layouts/components/dharmashop/product-card-parts/media.php.
 */
(() => {
  'use strict';

  let observer = null;

  const create = (element) => {
    if (element.dataset.dharmashopCardSliderReady || typeof window.Swiper !== 'function') return;
    element.dataset.dharmashopCardSliderReady = '1';
    new window.Swiper(element, {
      spaceBetween: 0,
      a11y: true,
      pagination: { el: element.querySelector('.swiper-pagination'), clickable: true },
      navigation: { nextEl: element.querySelector('.swiper-button-next'), prevEl: element.querySelector('.swiper-button-prev') },
    });
  };

  const observe = () => {
    const elements = document.querySelectorAll('[data-dharmashop-card-slider]:not([data-dharmashop-card-slider-ready])');

    if (!('IntersectionObserver' in window)) {
      elements.forEach(create);
      return;
    }

    observer = observer || new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);
        create(entry.target);
      });
    }, { rootMargin: '300px 0px' });
    elements.forEach((element) => observer.observe(element));
  };

  document.addEventListener('dharmashop:updated', observe);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observe, { once: true });
  } else {
    observe();
  }
})();
