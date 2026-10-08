/**
 * Dharma Shop: общая карта пунктов самовывоза на странице оформления (Яндекс Карты, JavaScript API 3 по ключу).
 * Конфигурацию отдаёт сервер в data-config контейнера [data-pickup-map] (координаты пунктов — [широта, долгота], как у Яндекса;
 * в API 3 порядок [долгота, широта], переставляем здесь). Карта создаётся, когда контейнер попадает в окно.
 * Метки — свои элементы (булавка, точка, круг, метка с названием); размеры и позиционирование — атрибуты SVG и классы Bootstrap.
 * Выбор пункта (радио [data-shipping-point] в карточке) выделяет метку и сдвигает карту; клик по метке выбирает карточку.
 * Тексты выводятся как текст (textContent).
 */
(() => {
    'use strict';

    const COLORS = {
        blue: '#1e98ff', red: '#ed4543', green: '#56db40', darkGreen: '#1bad03', darkBlue: '#0e4779', lightBlue: '#82cdff', orange: '#ff931e',
        darkOrange: '#e6761b', violet: '#b51eff', pink: '#f371d1', yellow: '#ffd21e', brown: '#793d0e', black: '#595959', gray: '#b3b3b3', night: '#1c1f2b',
    };
    const NS = 'http://www.w3.org/2000/svg';
    let loader = null;

    const load = (key) => {
        loader ??= new Promise((resolve, reject) => {
            if (window.ymaps3) { resolve(); return; }
            const script = document.createElement('script');
            script.src = 'https://api-maps.yandex.ru/v3/?apikey=' + encodeURIComponent(key) + '&lang=ru_RU';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = reject;
            document.head.append(script);
        }).then(() => window.ymaps3.ready);
        return loader;
    };

    const svg = (size, draw) => {
        const root = document.createElementNS(NS, 'svg');
        root.setAttribute('width', String(size));
        root.setAttribute('height', String(size));
        root.setAttribute('viewBox', '0 0 24 24');
        root.setAttribute('aria-hidden', 'true');
        draw(root);
        return root;
    };

    const shape = (name, attributes) => {
        const node = document.createElementNS(NS, name);
        Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
        return node;
    };

    // Элемент метки: нулевого размера, точка привязки — координата пункта; содержимое позиционируется классами Bootstrap.
    const buildMarker = (point, kind, color, selected) => {
        const fill = COLORS[color] || COLORS.blue;
        const size = selected ? 40 : 32;
        const root = document.createElement('div');
        root.className = 'position-relative';
        const holder = document.createElement('div');
        holder.className = kind === 'dot' || kind === 'circle' ? 'position-absolute top-50 start-50 translate-middle' : 'position-absolute bottom-0 start-50 translate-middle-x';

        if (kind === 'dot') {
            holder.append(svg(selected ? 22 : 16, (s) => s.append(shape('circle', { cx: 12, cy: 12, r: 9, fill, stroke: '#fff', 'stroke-width': 3 }))));
        } else if (kind === 'circle') {
            holder.append(svg(selected ? 34 : 26, (s) => s.append(
                shape('circle', { cx: 12, cy: 12, r: 10, fill, stroke: '#fff', 'stroke-width': 2 }),
                shape('circle', { cx: 12, cy: 12, r: 4, fill: '#fff' }),
            )));
        } else {
            holder.append(svg(size, (s) => s.append(
                shape('path', { d: 'M12 1.5C7.3 1.5 3.8 5 3.8 9.6c0 5.9 8.2 12.9 8.2 12.9s8.2-7 8.2-12.9C20.2 5 16.7 1.5 12 1.5z', fill, stroke: '#fff', 'stroke-width': 1.2 }),
                shape('circle', { cx: 12, cy: 9.6, r: 3, fill: '#fff' }),
            )));
        }

        root.append(holder);

        if (kind === 'label') {
            const label = document.createElement('span');
            label.className = 'badge text-bg-light border shadow-sm position-absolute bottom-0 text-nowrap fw-normal ms-3';
            label.textContent = point.name;
            root.append(label);
        }

        root.title = point.name;

        return root;
    };

    const create = (el) => {
        let config;
        try { config = JSON.parse(el.dataset.config || '{}'); } catch (error) { return; }
        if (!config.apikey || !Array.isArray(config.points) || !config.points.length) return;

        const lonLat = (coords) => [coords[1], coords[0]];

        load(config.apikey).then(async () => {
            const ymaps3 = window.ymaps3;
            const { YMap, YMapDefaultSchemeLayer, YMapDefaultFeaturesLayer, YMapMarker, YMapControls } = ymaps3;
            const { YMapZoomControl } = await ymaps3.import('@yandex/ymaps3-controls@0.0.1');
            const active = config.points.find((point) => point.key === config.active) || config.points[0];
            const center = lonLat(config.center || active.coords);
            const zoom = config.zoom || (config.center ? 12 : 16);
            const map = new YMap(el, { location: { center, zoom } }, [new YMapDefaultSchemeLayer({}), new YMapDefaultFeaturesLayer({})]);
            map.addChild(new YMapControls({ position: 'right' }).addChild(new YMapZoomControl({})));

            const marks = new Map();
            const select = (key) => {
                const radio = el.closest('[data-shipping-option]')?.querySelector('[data-shipping-point][value="' + CSS.escape(key) + '"]');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (!radio) {
                    el._pickupMap?.setActive(key);
                }
            };
            const render = (key) => config.points.forEach((point) => {
                const mark = marks.get(point.key);
                const element = buildMarker(point, config.markerKind, point.key === key ? config.selectedColor : config.markerColor, point.key === key);
                mark.element.replaceChildren(...element.childNodes);
                mark.element.title = point.name;
                mark.marker.update({ zIndex: point.key === key ? 1000 : 100 });
            });

            config.points.forEach((point) => {
                const element = document.createElement('div');
                element.className = 'position-relative';
                element.setAttribute('role', 'button');
                // Клик по метке выбирает карточку пункта (радио в ней): дальше работают общий change и боковая панель.
                const marker = new YMapMarker({ coordinates: lonLat(point.coords), zIndex: 100, onClick: () => select(point.key) }, element);
                marks.set(point.key, { marker, element });
                map.addChild(marker);
            });
            render(active.key);

            // Масштаб 0: показать все пункты сразу (если пункт один, применяется масштаб 16).
            if (!config.zoom && !config.center && config.points.length > 1) {
                const lons = config.points.map((point) => point.coords[1]);
                const lats = config.points.map((point) => point.coords[0]);
                const padLon = Math.max(0.005, (Math.max(...lons) - Math.min(...lons)) * 0.2);
                const padLat = Math.max(0.005, (Math.max(...lats) - Math.min(...lats)) * 0.2);
                map.setLocation({ bounds: [[Math.min(...lons) - padLon, Math.min(...lats) - padLat], [Math.max(...lons) + padLon, Math.max(...lats) + padLat]] });
            }

            el._pickupMap = {
                setActive: (key) => {
                    const target = config.points.find((point) => point.key === key);
                    if (!target) return;
                    render(key);
                    map.setLocation({ center: lonLat(target.coords), duration: 300 });
                },
            };
        }).catch((error) => { console.warn('Dharma Shop: карта пунктов недоступна', error); /* пункты остаются в списке */ });
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
