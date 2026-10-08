/**
 * Dharma Shop: способ «Курьер» на странице оформления.
 *  - подсказки адреса (серверный геокодер через `shipping.ajax`, поле с ролью «адрес» — [data-courier-address]);
 *  - расчёт цены по адресу (расстояние от ближайшего склада): обновляет цену способа и итог, строки боковой панели и событием
 *    `dharmashop:shipping-quote` сообщает об этом оформлению;
 *  - карта (Яндекс Карты, JavaScript API 3): склады и точка доставки, ближайший склад выделяется, между ними линия.
 * Все тексты с сервера и из полей выводятся как текст (textContent). Цену окончательно считает сервер при оформлении заказа.
 */
(() => {
    'use strict';

    const COLORS = {
        blue: '#1e98ff', red: '#ed4543', green: '#56db40', darkGreen: '#1bad03', darkBlue: '#0e4779', lightBlue: '#82cdff', orange: '#ff931e',
        darkOrange: '#e6761b', violet: '#b51eff', pink: '#f371d1', yellow: '#ffd21e', brown: '#793d0e', black: '#595959', gray: '#b3b3b3', night: '#1c1f2b',
    };
    const NS = 'http://www.w3.org/2000/svg';

    const token = () => window.Joomla?.getOptions('csrf.token') || '';

    const post = async (root, action, data) => {
        const body = new URLSearchParams();
        body.set(token(), '1');
        body.set('method_id', root.dataset.methodId || '0');
        body.set('action', action);
        Object.entries(data).forEach(([key, value]) => body.set('data[' + key + ']', value));
        const response = await fetch(root.dataset.ajaxUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
        return response.json();
    };

    // ------------------------------------------------------------------ карта

    let mapsLoader = null;

    const loadMaps = (key) => {
        mapsLoader ??= new Promise((resolve, reject) => {
            if (window.ymaps3) { resolve(); return; }
            const existing = document.querySelector('script[src*="api-maps.yandex.ru/v3"]');
            if (existing) { existing.addEventListener('load', () => resolve()); existing.addEventListener('error', reject); return; }
            const script = document.createElement('script');
            script.src = 'https://api-maps.yandex.ru/v3/?apikey=' + encodeURIComponent(key) + '&lang=ru_RU';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = reject;
            document.head.append(script);
        }).then(() => window.ymaps3.ready);
        return mapsLoader;
    };

    const svg = (size, children) => {
        const root = document.createElementNS(NS, 'svg');
        root.setAttribute('width', String(size));
        root.setAttribute('height', String(size));
        root.setAttribute('viewBox', '0 0 24 24');
        root.setAttribute('aria-hidden', 'true');
        children.forEach((child) => root.append(child));
        return root;
    };

    const shape = (name, attributes) => {
        const node = document.createElementNS(NS, name);
        Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
        return node;
    };

    // Элемент метки нулевого размера: точка привязки — координата; содержимое позиционируется классами Bootstrap.
    const marker = (kind, color, text, size) => {
        const fill = COLORS[color] || COLORS.blue;
        const root = document.createElement('div');
        root.className = 'position-relative';
        const holder = document.createElement('div');
        holder.className = kind === 'dot' || kind === 'circle' ? 'position-absolute top-50 start-50 translate-middle' : 'position-absolute bottom-0 start-50 translate-middle-x';

        if (kind === 'dot') {
            holder.append(svg(size - 14, [shape('circle', { cx: 12, cy: 12, r: 9, fill, stroke: '#fff', 'stroke-width': 3 })]));
        } else if (kind === 'circle') {
            holder.append(svg(size - 6, [shape('circle', { cx: 12, cy: 12, r: 10, fill, stroke: '#fff', 'stroke-width': 2 }), shape('circle', { cx: 12, cy: 12, r: 4, fill: '#fff' })]));
        } else {
            holder.append(svg(size, [
                shape('path', { d: 'M12 1.5C7.3 1.5 3.8 5 3.8 9.6c0 5.9 8.2 12.9 8.2 12.9s8.2-7 8.2-12.9C20.2 5 16.7 1.5 12 1.5z', fill, stroke: '#fff', 'stroke-width': 1.2 }),
                shape('circle', { cx: 12, cy: 9.6, r: 3, fill: '#fff' }),
            ]));
        }

        root.append(holder);

        if (kind === 'label' && text) {
            const label = document.createElement('div');
            label.className = 'position-absolute bottom-0 ms-3 d-flex align-items-center gap-2 bg-body border rounded-3 px-3 py-2 lh-sm text-nowrap shadow-sm';
            label.style.fontSize = '13px';
            label.textContent = text;
            root.append(label);
        }

        return root;
    };

    const createMap = (el) => {
        let config;
        try { config = JSON.parse(el.dataset.config || '{}'); } catch (error) { return; }
        if (!config.apikey || !Array.isArray(config.warehouses) || !config.warehouses.length) return;

        const lonLat = (coords) => [coords[1], coords[0]];
        const parse = (text) => String(text).replace(/[[\]()]/g, ' ').split(/[;,\s]+/).filter(Boolean).map(Number);

        loadMaps(config.apikey).then(async () => {
            const ymaps3 = window.ymaps3;
            const { YMap, YMapDefaultSchemeLayer, YMapDefaultFeaturesLayer, YMapMarker, YMapFeature, YMapControls } = ymaps3;
            const { YMapZoomControl } = await ymaps3.import('@yandex/ymaps3-controls@0.0.1');
            const warehouses = config.warehouses.map((warehouse) => ({ ...warehouse, point: parse(warehouse.coords) }));
            const kind = config.markerKind === 'label' ? 'label' : config.markerKind;
            const map = new YMap(el, { location: { center: lonLat(warehouses[0].point), zoom: config.zoom || 10 } }, [new YMapDefaultSchemeLayer({}), new YMapDefaultFeaturesLayer({})]);
            map.addChild(new YMapControls({ position: 'right' }).addChild(new YMapZoomControl({})));

            const marks = new Map();
            warehouses.forEach((warehouse) => {
                const element = document.createElement('div');
                element.className = 'position-relative';
                const mark = new YMapMarker({ coordinates: lonLat(warehouse.point), zIndex: 100 }, element);
                marks.set(warehouse.key, { element, mark });
                map.addChild(mark);
                element.replaceChildren(...marker(kind, config.warehouseColor, warehouse.name, 32).childNodes);
            });

            // Все склады сразу: рамка по их координатам (при одном складе — масштаб 12).
            const fit = (points) => {
                const lons = points.map((point) => point[1]);
                const lats = points.map((point) => point[0]);
                const padLon = Math.max(0.01, (Math.max(...lons) - Math.min(...lons)) * 0.25);
                const padLat = Math.max(0.01, (Math.max(...lats) - Math.min(...lats)) * 0.25);
                map.setLocation({ bounds: [[Math.min(...lons) - padLon, Math.min(...lats) - padLat], [Math.max(...lons) + padLon, Math.max(...lats) + padLat]], duration: 300 });
            };
            if (!config.zoom && warehouses.length > 1) fit(warehouses.map((warehouse) => warehouse.point));

            let delivery = null;
            let line = null;
            const clear = () => {
                if (delivery) map.removeChild(delivery);
                if (line) map.removeChild(line);
                delivery = null;
                line = null;
                warehouses.forEach((warehouse) => {
                    const entry = marks.get(warehouse.key);
                    entry.element.replaceChildren(...marker(kind, config.warehouseColor, warehouse.name, 32).childNodes);
                });
            };

            el._courierMap = {
                // Ответ расчёта: ближайший склад выделяется, ставится метка адреса, между ними линия, карта вмещает оба.
                show: (result) => {
                    clear();
                    const target = warehouses.find((warehouse) => warehouse.key === result.warehouse.key);
                    if (!target || !Array.isArray(result.to)) return;
                    marks.get(target.key).element.replaceChildren(...marker(kind, config.nearestColor, target.name, 40).childNodes);
                    const element = document.createElement('div');
                    element.className = 'position-relative';
                    element.append(...marker(kind === 'label' ? 'pin' : kind, config.addressColor, '', 40).childNodes);
                    delivery = new YMapMarker({ coordinates: lonLat(result.to), zIndex: 200 }, element);
                    map.addChild(delivery);
                    line = new YMapFeature({ geometry: { type: 'LineString', coordinates: [lonLat(target.point), lonLat(result.to)] }, style: { stroke: [{ color: COLORS[config.addressColor] || COLORS.red, width: 3, opacity: 0.8 }] } });
                    map.addChild(line);
                    fit([target.point, result.to]);
                },
                reset: () => { clear(); if (!config.zoom && warehouses.length > 1) fit(warehouses.map((warehouse) => warehouse.point)); },
            };
        }).catch((error) => { console.warn('Dharma Shop: карта курьера недоступна', error); });
    };

    // ------------------------------------------------------------------ адрес, подсказки, расчёт

    const init = (root) => {
        if (root.dataset.courierReady) return;
        root.dataset.courierReady = '1';
        const option = root.closest('[data-shipping-option]');
        const radio = option?.querySelector('[data-shipping-radio]');
        const city = root.querySelector('[data-courier-city]');
        const address = root.querySelector('[data-courier-address]');
        const list = root.querySelector('[data-courier-suggest]');
        const status = root.querySelector('[data-courier-status]');
        const priceNode = option?.querySelector('[data-shipping-option-price]');
        if (!radio || !address) return;

        const initial = { price: radio.dataset.priceText || '', total: radio.dataset.totalText || '' };
        let timer = null;
        let sequence = 0;
        let silent = false;

        const say = (text, error = false) => {
            if (!status) return;
            status.textContent = text;
            status.classList.toggle('text-danger', error);
            status.classList.toggle('text-body-secondary', !error);
        };

        const announce = () => {
            document.dispatchEvent(new CustomEvent('dharmashop:shipping-quote', { detail: { radio } }));
        };

        const setPrice = (priceText, totalText, free) => {
            radio.dataset.priceText = priceText;
            radio.dataset.totalText = totalText;
            if (priceNode) {
                priceNode.textContent = priceText;
                priceNode.classList.toggle('text-success', !!free);
            }
        };

        const reset = (message, error) => {
            setPrice(initial.price, initial.total, false);
            delete address.dataset.summaryRows;
            root.querySelector('[data-courier-map]')?._courierMap?.reset();
            say(message || '', !!error);
            announce();
        };

        const calculate = async () => {
            if (root.dataset.distance !== '1') return;
            const addressValue = address.value.trim();
            if (addressValue.length < 3) { reset('', false); return; }
            const mine = ++sequence;
            say(status?.dataset.progress || '…');
            try {
                const result = await post(root, 'quote', { city: city ? city.value.trim() : '', address: addressValue });
                if (mine !== sequence) return;
                if (!result.ok) { reset(result.error || '', true); return; }
                setPrice(result.price_text, result.total_text, result.free);
                address.dataset.summaryRows = JSON.stringify(result.rows || []);
                say((result.rows || []).map((row) => row.label + ': ' + row.value).join(' · '), false);
                root.querySelector('[data-courier-map]')?._courierMap?.show(result);
                announce();
            } catch (error) {
                if (mine === sequence) reset('', true);
            }
        };

        const hide = () => { if (list) { list.classList.add('d-none'); list.replaceChildren(); } };

        const choose = (item) => {
            address.value = item.address || item.label;
            if (city && item.city && !city.value.trim()) city.value = item.city;
            hide();
            // Событие input нужно оформлению (боковая панель), но подсказки по нему повторно запрашивать не надо.
            silent = true;
            address.dispatchEvent(new Event('input', { bubbles: true }));
            silent = false;
            calculate();
        };

        const suggest = async () => {
            if (!list || root.dataset.suggest !== '1') return;
            const text = address.value.trim();
            if (text.length < 3) { hide(); return; }
            try {
                const result = await post(root, 'suggest', { text, city: city ? city.value.trim() : '' });
                if (address.value.trim() !== text) return;
                hide();
                if (!result.ok || !Array.isArray(result.items) || !result.items.length) return;
                result.items.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action small text-start';
                    button.setAttribute('role', 'option');
                    button.textContent = item.label;
                    button.addEventListener('mousedown', (event) => { event.preventDefault(); choose(item); });
                    list.append(button);
                });
                list.classList.remove('d-none');
            } catch (error) { hide(); }
        };

        address.addEventListener('input', () => {
            if (silent) return;
            window.clearTimeout(timer);
            timer = window.setTimeout(suggest, 350);
        });
        address.addEventListener('change', calculate);
        address.addEventListener('blur', () => window.setTimeout(hide, 150));
        address.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(); });
        city?.addEventListener('change', calculate);
        // Расчёт по уже введённому адресу — только когда способ выбран (скрытые способы не тратят запросы геокодера).
        radio.addEventListener('change', () => { if (radio.checked && address.value.trim()) calculate(); });
        if (address.value.trim() && radio.checked) calculate();
    };

    const boot = () => {
        document.querySelectorAll('[data-courier]').forEach(init);
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                createMap(entry.target);
            });
        });
        document.querySelectorAll('[data-courier-map]').forEach((el) => observer.observe(el));
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
