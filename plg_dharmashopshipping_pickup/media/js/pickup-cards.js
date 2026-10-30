/**
 * Dharma Shop: карточки пунктов самовывоза в слайдере Swiper (набор dharmashop.swiper даёт плагин «Dharma Shop Swiper»).
 * Слайдер создаётся, когда блок становится видимым (до выбора способа он скрыт); выбранная карточка прокручивается в видимую область
 * — и при выборе радио, и при клике по метке карты (change всплывает из карточки).
 */
(() => {
    'use strict';

    const create = (element) => {
        if (element.dataset.pickupSliderReady || typeof window.Swiper !== 'function') return;
        element.dataset.pickupSliderReady = '1';
        const root = element.parentElement;
        const swiper = new window.Swiper(element, {
            slidesPerView: 1.15,
            spaceBetween: 8,
            watchOverflow: true,
            a11y: true,
            navigation: { prevEl: root.querySelector('[data-pickup-prev]'), nextEl: root.querySelector('[data-pickup-next]') },
            breakpoints: { 576: { slidesPerView: 2.1 }, 992: { slidesPerView: 2.2 } },
        });
        const slides = Array.from(element.querySelectorAll('.swiper-slide'));
        const reveal = () => {
            const radio = element.querySelector('[data-shipping-point]:checked');
            const index = radio ? slides.indexOf(radio.closest('.swiper-slide')) : -1;
            if (index >= 0) swiper.slideTo(index);
        };
        element.addEventListener('change', reveal);
        reveal();
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            create(entry.target);
        });
    });

    const boot = () => document.querySelectorAll('[data-pickup-slider]').forEach((element) => observer.observe(element));
    boot();
    // Оформление «в один шаг» (C100) подгружает форму заново: слайдеры создаются на новых узлах.
    document.addEventListener('dharmashop:checkout-replaced', boot);
})();
