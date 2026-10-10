/**
 * Dharma Shop: галерея товара. Инициализирует Swiper (главный слайдер + миниатюры) в блоках [data-dharmashop-gallery]
 * с data-type="swiper" и привязывает лайтбокс Fancybox, если он включён и подключён (плагин с набором wtfancybox).
 * Разметка и классы — в layouts/components/dharmashop/product-gallery/default.php; блок можно править независимо.
 * Без Swiper (плагин выключен) макет отдаёт карусель Bootstrap, и этот скрипт ничего не делает со слайдером.
 */
(() => {
  'use strict';

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

  const initialise = (root) => {
    if (root.dataset.dharmashopGalleryReady) return;
    root.dataset.dharmashopGalleryReady = '1';

    if (root.dataset.type === 'swiper' && typeof window.Swiper === 'function') {
      const mainElement = root.querySelector('.dharmaGalleryMain');
      const thumbsElement = root.querySelector('.dharmaGalleryThumbs');
      const slides = mainElement ? mainElement.querySelectorAll('.swiper-slide').length : 0;
      const options = {
        loop: root.dataset.loop === '1' && slides > 1,
        spaceBetween: 8,
        a11y: true,
      };

      if (slides > 1) {
        Object.assign(options, controls(mainElement));
        options.keyboard = { enabled: true };
      }

      let thumbs = null;

      if (thumbsElement) {
        const prev = thumbsElement.querySelector('[data-dharmashop-slider-prev]');
        const next = thumbsElement.querySelector('[data-dharmashop-slider-next]');
        // На узких экранах последняя миниатюра видна частично — намёк на прокрутку; стрелки нужны, только если миниатюры не помещаются.
        thumbs = new window.Swiper(thumbsElement, {
          slidesPerView: 3.5,
          spaceBetween: 8,
          freeMode: true,
          watchSlidesProgress: true,
          breakpoints: { 576: { slidesPerView: 4.5 }, 992: { slidesPerView: 5 } },
          navigation: prev && next ? { prevEl: prev, nextEl: next } : false,
        });
        const toggleArrows = () => [prev, next].forEach((arrow) => arrow && arrow.classList.toggle('d-none', thumbs.isLocked));
        ['lock', 'unlock', 'breakpoint', 'resize'].forEach((name) => thumbs.on(name, toggleArrows));
        toggleArrows();
        options.thumbs = { swiper: thumbs, autoScrollOffset: 1 };
      }

      const main = mainElement ? new window.Swiper(mainElement, options) : null;

      // Активная миниатюра: рамка основного цвета Bootstrap (border-primary), остальные приглушены (opacity-50).
      if (main && thumbs) {
        const mark = () => thumbs.slides.forEach((slide, index) => {
          const active = index === main.realIndex;
          slide.classList.toggle('border-primary', active);
          slide.classList.toggle('border-2', active);
          slide.classList.toggle('opacity-50', !active);
        });
        main.on('slideChange', mark);
        mark();
      }
    }

    if (root.dataset.lightbox === '1' && window.Fancybox && typeof window.Fancybox.bind === 'function') {
      window.Fancybox.bind('#' + root.id + ' [data-fancybox]', {});
    }
  };

  const boot = () => document.querySelectorAll('[data-dharmashop-gallery]').forEach(initialise);

  document.addEventListener('dharmashop:updated', boot);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
