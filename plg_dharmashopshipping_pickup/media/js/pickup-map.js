/**
 * Dharma Shop: общая карта пунктов самовывоза на странице оформления (Яндекс Карты, JavaScript API 2.1 по ключу).
 * Конфигурацию отдаёт сервер в data-config контейнера [data-pickup-map]; карта создаётся, когда контейнер становится видимым.
 * Выбор пункта (радио [data-shipping-point]) выделяет метку и сдвигает карту. Тексты в баллуне экранируются.
 */
(() => {
    'use strict';

    let loader = null;

    const load = (key) => {
        loader ??= new Promise((resolve, reject) => {
            if (window.ymaps) { window.ymaps.ready(resolve); return; }
            const script = document.createElement('script');
            script.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(key) + '&lang=ru_RU';
            script.async = true;
            script.onload = () => window.ymaps.ready(resolve);
            script.onerror = reject;
            document.head.append(script);
        });
        return loader;
    };

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const create = (el) => {
        let config;
        try { config = JSON.parse(el.dataset.config || '{}'); } catch (error) { return; }
        if (!config.apikey || !Array.isArray(config.points) || !config.points.length) return;

        load(config.apikey).then(() => {
            const ymaps = window.ymaps;
            const active = config.points.find((point) => point.key === config.active) || config.points[0];
            const map = new ymaps.Map(el, {
                center: config.center || active.coords,
                zoom: config.zoom || (config.center ? 12 : 16),
                controls: ['zoomControl', 'fullscreenControl'],
            }, { suppressMapOpenBlock: true });
            const marks = new Map();
            const preset = (selected) => 'islands#' + (selected ? config.selectedColor : config.markerColor) + config.markerKind;

            config.points.forEach((point) => {
                const mark = new ymaps.Placemark(point.coords, {
                    hintContent: escapeHtml(point.name),
                    balloonContentHeader: escapeHtml(point.name),
                    balloonContentBody: escapeHtml(point.address),
                    iconContent: escapeHtml(point.name),
                }, { preset: preset(point.key === active.key) });
                marks.set(point.key, mark);
                map.geoObjects.add(mark);
            });

            // Масштаб 0: показать все пункты сразу (если пункт один, применяется масштаб 16).
            if (!config.zoom && !config.center && config.points.length > 1) {
                map.setBounds(map.geoObjects.getBounds(), { checkZoomRange: true, zoomMargin: 40 });
            }

            // Блок карты скрыт, пока выбран другой способ: пересчитываем размер при показе.
            new ResizeObserver(() => map.container.fitToViewport()).observe(el);

            el._pickupMap = {
                setActive: (key) => {
                    marks.forEach((mark, markKey) => mark.options.set('preset', preset(markKey === key)));
                    const target = config.points.find((point) => point.key === key);
                    if (target) map.panTo(target.coords, { flying: false });
                },
            };
        }).catch(() => { /* карта недоступна: пункты остаются в списке */ });
    };

    document.addEventListener('change', (event) => {
        const radio = event.target.closest?.('[data-shipping-point]');
        if (radio) radio.closest('[data-shipping-option]')?.querySelector('[data-pickup-map]')?._pickupMap?.setActive(radio.value);
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            create(entry.target);
        });
    });

    document.querySelectorAll('[data-pickup-map]').forEach((el) => observer.observe(el));
})();
