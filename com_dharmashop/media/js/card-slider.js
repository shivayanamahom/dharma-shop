/**
 * Dharma Shop: слайдер фото в карточках товаров (Swiper). Слайдеры создаются лениво — когда карточка приближается к области
 * видимости; до этого видна первая картинка (без скрипта и после сбоя — тоже). Подхватывает карточки, добавленные
 * подгрузкой («Показать ещё», бесконечная прокрутка, замена страницы) — по событию `dharmashop:updated`.
 * Разметка — layouts/components/dharmashop/product-card-parts/media.php.
 */
(() => {
  'use strict';

  let observer = null;

  // Стрелки и полоски — разметка и классы Bootstrap (carousel-control-*, carousel-indicators), а не стили Swiper: вид не зависит от шаблона.
  // Классы пагинации Swiper переименованы, чтобы его стили на них не действовали; активная полоска получает класс Bootstrap `active`.
  const controls = (element) => {
    const pagination = element.querySelector('[data-dharmashop-slider-pagination]');
    const prev = element.querySelector('[data-dharmashop-slider-prev]');
    const next = element.querySelector('[data-dharmashop-slider-next]');
    [pagination, prev, next].forEach((control) => control && control.classList.remove('d-none'));

    return {
      pagination: pagination ? {
        el: pagination,
        clickable: true,
        bulletClass: 'dharmashop-bullet',
        bulletActiveClass: 'active',
        modifierClass: 'dharmashop-pagination-',
        clickableClass: 'dharmashop-pagination-clickable',
        horizontalClass: 'dharmashop-pagination-horizontal',
        renderBullet: (index, className) => '<button type="button" class="' + className + '" data-bs-target aria-label="' + (index + 1) + '"></button>',
      } : false,
      navigation: prev && next ? { prevEl: prev, nextEl: next } : false,
    };
  };


  const create = (element) => {
    if (element.dataset.dharmashopCardSliderReady || typeof window.Swiper !== 'function') return;
    element.dataset.dharmashopCardSliderReady = '1';
    new window.Swiper(element, Object.assign({ spaceBetween: 0, a11y: true }, controls(element)));
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
