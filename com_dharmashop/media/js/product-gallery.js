/**
 * Dharma Shop: галерея товара. Инициализирует Swiper (главный слайдер + миниатюры) в блоках [data-dharmashop-gallery]
 * с data-type="swiper" и привязывает лайтбокс Fancybox, если он включён и подключён (плагин с набором wtfancybox).
 * Разметка и классы — в layouts/components/dharmashop/product-gallery/default.php; блок можно править независимо.
 * Без Swiper (плагин выключен) макет отдаёт карусель Bootstrap, и этот скрипт ничего не делает со слайдером.
 */
(() => {
  'use strict';

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
        options.navigation = { nextEl: root.querySelector('.swiper-button-next'), prevEl: root.querySelector('.swiper-button-prev') };
        options.keyboard = { enabled: true };
      }

      if (thumbsElement) {
        options.thumbs = {
          swiper: new window.Swiper(thumbsElement, {
            slidesPerView: 5,
            spaceBetween: 8,
            freeMode: true,
            watchSlidesProgress: true,
          }),
        };
      }

      if (mainElement) new window.Swiper(mainElement, options);
    }

    if (root.dataset.lightbox === '1' && window.Fancybox && typeof window.Fancybox.bind === 'function') {
      window.Fancybox.bind('#' + root.id + ' [data-fancybox]', {});
    }
  };

  const boot = () => document.querySelectorAll('[data-dharmashop-gallery]').forEach(initialise);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
