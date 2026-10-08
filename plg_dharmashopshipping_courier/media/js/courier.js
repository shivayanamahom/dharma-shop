/**
 * Dharma Shop: способ «Курьер» на странице оформления.
 *  - подсказки при вводе города, улицы и дома: в браузере через JavaScript API 3 (ymaps3.suggest), при отказе — через сервер (`shipping.ajax`:
 *    «Геосаджест» Яндекса или геокодер); выбор подсказки заполняет город, улицу и дом;
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
        const street = root.querySelector('[data-courier-street]');
        const house = root.querySelector('[data-courier-house]');
        const status = root.querySelector('[data-courier-status]');
        const priceNode = option?.querySelector('[data-shipping-option-price]');
        if (!radio || !street) return;

        const initial = { price: radio.dataset.priceText || '', total: radio.dataset.totalText || '' };
        const timers = {};
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
            delete (house || street).dataset.summaryRows;
            root.querySelector('[data-courier-map]')?._courierMap?.reset();
            say(message || '', !!error);
            announce();
        };

        const calculate = async () => {
            if (root.dataset.distance !== '1') return;
            const cityValue = city ? city.value.trim() : '';
            const streetValue = street.value.trim();
            const houseValue = house ? house.value.trim() : '';
            // Город, улица и дом нужны для расчёта; пока что-то не заполнено, цена остаётся «по адресу».
            if (!cityValue || streetValue.length < 3 || !houseValue) { reset('', false); return; }
            const mine = ++sequence;
            say(status?.dataset.progress || '…');
            try {
                const result = await post(root, 'quote', { city: cityValue, street: streetValue, house: houseValue });
                if (mine !== sequence) return;
                if (!result.ok) { reset(result.error || '', true); return; }
                setPrice(result.price_text, result.total_text, result.free);
                // Строки расчёта (расстояние, склад) идут в боковой панели после последнего базового поля — дома.
                (house || street).dataset.summaryRows = JSON.stringify(result.rows || []);
                say((result.rows || []).map((row) => row.label + ': ' + row.value).join(' · '), false);
                root.querySelector('[data-courier-map]')?._courierMap?.show(result);
                announce();
            } catch (error) {
                if (mine === sequence) reset('', true);
            }
        };

        // Подсказки у каждого из трёх полей: город, улица, дом. Выбор подставляет всё, что известно о выбранном адресе.
        const fieldKinds = [['city', city], ['street', street], ['house', house]].filter(([, field]) => field);
        const lists = new Map(fieldKinds.map(([kind]) => [kind, root.querySelector('[data-courier-suggest="' + kind + '"]')]));
        const hide = (kind) => {
            (kind ? [kind] : Array.from(lists.keys())).forEach((name) => { const list = lists.get(name); if (list) { list.classList.add('d-none'); list.replaceChildren(); } });
        };

        const choose = (kind, item) => {
            if (kind === 'city') {
                city.value = item.city || item.label;
            } else {
                if (item.street) street.value = item.street;
                else if (kind === 'street') street.value = item.label;
                if (house && item.house) house.value = item.house;
                if (city && item.city && !city.value.trim()) city.value = item.city;
            }
            hide();
            // Событие input нужно оформлению (боковая панель), но подсказки по нему повторно запрашивать не надо.
            silent = true;
            [street, house, city].forEach((field) => field?.dispatchEvent(new Event('input', { bubbles: true })));
            silent = false;
            calculate();
        };

        // Подсказки в браузере: JavaScript API 3 — ymaps3.suggest (нужен ключ с доступом к «API Геосаджеста»; ключ ставится через getDefaultConfig().setApikeys).
        // Любая ошибка (сервис не разрешён ключу, сеть) — запасной путь через сервер.
        const near = (root.dataset.near || '').split(',').map(Number);
        const clientSuggest = async (kind, text) => {
            await loadMaps(root.dataset.apikey);
            const maps = window.ymaps3;
            maps.getDefaultConfig().setApikeys({ suggest: root.dataset.apikey, search: root.dataset.apikey });
            const cityValue = city ? city.value.trim() : '';
            const streetValue = street ? street.value.trim() : '';
            const request = {
                city: { text, types: ['locality'] },
                house: { text: [cityValue, (streetValue + ' ' + text).trim()].filter(Boolean).join(', '), types: ['house'] },
                street: { text: [cityValue, text].filter(Boolean).join(', '), types: ['street', 'house'] },
            }[kind];
            const options = { ...request, limit: 7, highlight: false };
            if (near.length === 2 && near.every(Number.isFinite)) { options.center = [near[1], near[0]]; options.span = [3, 2]; }
            const found = await maps.suggest(options);
            const seen = new Set();
            const items = [];
            found.forEach((row) => {
                const parts = { city: '', street: '', house: '' };
                (row.address?.component || []).forEach((component) => {
                    const kinds = (component.kind || []).map((value) => String(value).toUpperCase());
                    if (kinds.includes('LOCALITY')) parts.city = component.name;
                    else if (kinds.includes('STREET')) parts.street = component.name;
                    else if (kinds.includes('HOUSE')) parts.house = component.name;
                });
                const title = row.title?.text || '';
                const subtitle = row.subtitle?.text || '';
                if (kind === 'city') {
                    const name = parts.city || title;
                    if (!name || seen.has(name + '|' + subtitle)) return;
                    seen.add(name + '|' + subtitle);
                    items.push({ label: name + (subtitle ? ', ' + subtitle : ''), city: name, street: '', house: '' });
                } else {
                    items.push({ label: title + (subtitle ? ', ' + subtitle : ''), city: parts.city, street: parts.street, house: parts.house });
                }
            });
            return items;
        };

        const suggest = async (kind, field) => {
            const list = lists.get(kind);
            if (!list || root.dataset.suggest !== '1') return;
            const text = field.value.trim();
            // Город — от двух символов, улица — от трёх; дом — можно без текста: список домов выбранной улицы.
            if (kind === 'house' ? (!street.value.trim() || !city.value.trim()) : text.length < (kind === 'city' ? 2 : 3)) { hide(kind); return; }
            try {
                let result;
                if (root.dataset.apikey) {
                    try { result = { ok: true, items: await clientSuggest(kind, text) }; } catch (error) { result = null; }
                }
                if (!result) result = await post(root, 'suggest', { kind, text, city: city ? city.value.trim() : '', street: street ? street.value.trim() : '' });
                if (field.value.trim() !== text) return;
                hide(kind);
                if (!result.ok || !Array.isArray(result.items) || !result.items.length) return;
                result.items.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action small text-start';
                    button.setAttribute('role', 'option');
                    button.textContent = item.label;
                    button.addEventListener('mousedown', (event) => { event.preventDefault(); choose(kind, item); });
                    list.append(button);
                });
                list.classList.remove('d-none');
            } catch (error) { hide(kind); }
        };

        fieldKinds.forEach(([kind, field]) => {
            field.addEventListener('input', () => {
                if (silent) return;
                window.clearTimeout(timers[kind]);
                timers[kind] = window.setTimeout(() => suggest(kind, field), 350);
            });
            field.addEventListener('blur', () => window.setTimeout(() => hide(kind), 150));
            field.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(kind); });
            field.addEventListener('change', calculate);
        });
        // Поле «Дом»: при входе в него — список домов выбранной улицы (чтобы выбрать существующий).
        house?.addEventListener('focus', () => suggest('house', house));
        // Расчёт по уже введённому адресу — только когда способ выбран (скрытые способы не тратят запросы геокодера).
        radio.addEventListener('change', () => { if (radio.checked && street.value.trim()) calculate(); });
        if (street.value.trim() && radio.checked) calculate();
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
