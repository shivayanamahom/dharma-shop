/** Checkout: маски телефона, состояние ожидания POST и защита от повторного нажатия. */
(() => {
    'use strict';

    const options = window.Joomla?.getOptions('com_dharmashop.checkout', {}) || {};
    let cleanup = [];

    // Слушатели на window и document снимаются при замене формы (оформление «в один шаг», C100), чтобы не копиться.
    const listen = (target, type, handler) => {
        target.addEventListener(type, handler);
        cleanup.push(() => target.removeEventListener(type, handler));
    };

    const bind = (form) => {
        // Боковая панель в оформлении «в один шаг» стоит вне формы (рядом с составом корзины): ищем в общем контейнере.
        const scope = form.closest('[data-dharmashop-checkout-scope]') || form;
        const processing = document.querySelector('[data-checkout-processing]');
        const slowNotice = processing?.querySelector('[data-checkout-processing-slow]');
        const buttons = Array.from(scope.querySelectorAll('[data-checkout-submit]'), (button) => ({
            button, contents: button.innerHTML, disabled: button.disabled,
        }));
        let sending = false;
        let modal = null;
        let slowTimer = null;

        const reset = () => {
            sending = false;
            window.clearTimeout(slowTimer);
            slowTimer = null;
            form.removeAttribute('aria-busy');
            slowNotice?.classList.add('d-none');
            modal?.hide();
            buttons.forEach(({ button, contents, disabled }) => {
                button.disabled = disabled;
                button.removeAttribute('aria-busy');
                button.innerHTML = contents;
            });
        };

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;
            if (sending) { event.preventDefault(); return; }
            if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
            sending = true;
            form.setAttribute('aria-busy', 'true');
            buttons.forEach(({ button }) => {
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm me-2';
                spinner.setAttribute('aria-hidden', 'true');
                button.replaceChildren(spinner, document.createTextNode(options.processing || button.textContent));
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
            // Bootstrap owns the backdrop, focus trap and scroll lock. Fields stay enabled for the POST.
            if (processing && window.bootstrap?.Modal) {
                modal = window.bootstrap.Modal.getOrCreateInstance(processing, { backdrop: 'static', keyboard: false });
                modal.show();
            }
            slowTimer = window.setTimeout(() => {
                if (sending) slowNotice?.classList.remove('d-none');
            }, 15000);
            // Another submit listener may cancel navigation after this listener has run.
            queueMicrotask(() => { if (event.defaultPrevented) reset(); });
        });
        // Restores the form and Bootstrap backdrop when returning via the browser's back/forward cache.
        listen(window, 'pageshow', reset);

        // Доставка: выбранный вариант обновляет строку «Доставка», итог и сведения в боковой панели (тексты готовит сервер).
        // Поля покупателя внутри варианта (data-summary-label) дублируются в боковой панели; значения выводятся как текст.
        const shippingRadios = scope.querySelectorAll('[data-shipping-radio]');
        const inputRows = scope.querySelector('[data-checkout-shipping-input]');
        // Панель способа (макет плагина типа) лежит отдельно от плитки с радиокнопкой и связана с ней номером способа.
        const optionOf = (radio) => scope.querySelector('[data-shipping-option][data-method-id="' + radio.value + '"]');
        const syncShipping = () => {
            const chosen = Array.from(shippingRadios).find((radio) => radio.checked);
            shippingRadios.forEach((radio) => {
                const option = optionOf(radio);
                option?.classList.toggle('d-none', !radio.checked);
                const point = option?.querySelector('[data-shipping-point]:checked')?.value;
                // Блоки способа видны у выбранного способа; сведения пункта (data-shipping-point-detail) — ещё и у выбранного пункта.
                option?.querySelectorAll('[data-shipping-extra]').forEach((node) => {
                    const forPoint = node.dataset.shippingPointDetail;
                    node.classList.toggle('d-none', !radio.checked || (forPoint !== undefined && forPoint !== point));
                });
                scope.querySelector('[data-shipping-details="' + radio.value + '"]')?.classList.toggle('d-none', !radio.checked);
            });
            // Карточки пунктов (data-shipping-point-card): рамка выбранной выделяется.
            scope.querySelectorAll('[data-shipping-point]').forEach((radio) => {
                const card = radio.closest('[data-shipping-point-card]');
                card?.classList.toggle('border-primary', radio.checked);
                card?.classList.toggle('shadow-sm', radio.checked);
            });
            // Общая карта способа: адрес берётся у выбранного пункта (data-map-url); без адреса блок скрыт.
            shippingRadios.forEach((radio) => {
                const option = optionOf(radio);
                const frame = option?.querySelector('[data-shipping-map]');
                const wrap = option?.querySelector('[data-shipping-map-wrap]');
                if (!frame || !wrap || !radio.checked) return;
                const pointRadio = option.querySelector('[data-shipping-point]:checked');
                const url = pointRadio ? (pointRadio.dataset.mapUrl || '') : (frame.getAttribute('src') || '');
                if (url && frame.getAttribute('src') !== url) frame.setAttribute('src', url);
                wrap.classList.toggle('d-none', !url);
            });
            inputRows?.replaceChildren();
            if (!chosen) return;
            scope.querySelectorAll('[data-checkout-shipping-price]').forEach((node) => { node.textContent = chosen.dataset.priceText || ''; });
            scope.querySelectorAll('[data-checkout-total]').forEach((node) => { node.textContent = chosen.dataset.totalText || ''; });
            const addRow = (label, value) => {
                if (!value || !inputRows) return;
                const line = document.createElement('div');
                line.className = 'text-break';
                const caption = document.createElement('span');
                caption.className = 'text-body-secondary';
                caption.textContent = label + ': ';
                line.append(caption, document.createTextNode(value));
                inputRows.append(line);
            };
            // Поля и выбор покупателя внутри способа дублируются в боковой панели. data-summary-value — готовое значение (радио пункта),
            // data-summary-rows — JSON-список {label, value} с дополнительными сведениями выбранного пункта. Всё выводится как текст.
            optionOf(chosen)?.querySelectorAll('[data-summary-label], [data-summary-rows]').forEach((input) => {
                if (input.type === 'radio' && !input.checked) return;
                if (input.dataset.summaryLabel) {
                    const value = input.dataset.summaryValue ?? (input.tagName === 'SELECT' ? (input.selectedOptions[0]?.value ? input.selectedOptions[0].textContent : '') : input.value.trim());
                    addRow(input.dataset.summaryLabel, value);
                }
                if (input.dataset.summaryRows) {
                    try {
                        (JSON.parse(input.dataset.summaryRows) || []).forEach((row) => addRow(String(row.label ?? ''), String(row.value ?? '')));
                    } catch (error) { /* некорректный JSON не должен ломать форму */ }
                }
            });
        };
        shippingRadios.forEach((radio) => radio.addEventListener('change', syncShipping));
        // Способ пересчитал цену по данным покупателя (например, курьер по адресу): тексты уже в data-атрибутах радио.
        listen(document, 'dharmashop:shipping-quote', syncShipping);
        form.addEventListener('input', (event) => { if (event.target.closest?.('[data-shipping-extra]')) syncShipping(); });
        form.addEventListener('change', (event) => { if (event.target.closest?.('[data-shipping-extra]')) syncShipping(); });
        syncShipping();

        // Оплата: плитки-вкладки; доступность по выбранной доставке (карта id доставки → разрешённые способы оплаты, пусто — любые),
        // панель выбранного способа и его сведения в боковой панели. Отключённая радиокнопка в запрос не попадает: сервер всё равно проверяет выбор.
        const paymentRadios = scope.querySelectorAll('[data-payment-radio]');
        let paymentMap = {};
        try { paymentMap = JSON.parse(scope.querySelector('[data-payment-map]')?.dataset.paymentMap || '{}') || {}; } catch (error) { paymentMap = {}; }
        const paymentInputRows = scope.querySelector('[data-checkout-payment-input]');
        const paymentOptionOf = (radio) => scope.querySelector('[data-payment-option][data-method-id="' + radio.value + '"]');
        const syncPayment = () => {
            if (!paymentRadios.length) return;
            const shipping = Array.from(shippingRadios).find((radio) => radio.checked)?.value;
            const allowed = (paymentMap[shipping] || []).map(String);
            let visible = 0;
            paymentRadios.forEach((radio) => {
                const ok = allowed.length === 0 || allowed.includes(radio.value);
                radio.disabled = !ok;
                radio.closest('.col')?.classList.toggle('d-none', !ok);
                if (!ok) radio.checked = false;
                if (ok) visible += 1;
            });
            if (visible && !Array.from(paymentRadios).some((radio) => radio.checked)) {
                const first = Array.from(paymentRadios).find((radio) => !radio.disabled);
                if (first) first.checked = true;
            }
            scope.querySelector('[data-payment-none]')?.classList.toggle('d-none', visible > 0);
            const chosen = Array.from(paymentRadios).find((radio) => radio.checked && !radio.disabled);
            paymentRadios.forEach((radio) => {
                paymentOptionOf(radio)?.classList.toggle('d-none', radio !== chosen);
                scope.querySelector('[data-payment-details="' + radio.value + '"]')?.classList.toggle('d-none', radio !== chosen);
            });
            paymentInputRows?.replaceChildren();
            if (!chosen) return;
            paymentOptionOf(chosen)?.querySelectorAll('[data-summary-label]').forEach((input) => {
                const value = input.tagName === 'SELECT' ? (input.selectedOptions[0]?.value ? input.selectedOptions[0].textContent : '') : input.value.trim();
                if (!value || !paymentInputRows) return;
                const line = document.createElement('div');
                line.className = 'text-break';
                const caption = document.createElement('span');
                caption.className = 'text-body-secondary';
                caption.textContent = input.dataset.summaryLabel + ': ';
                line.append(caption, document.createTextNode(value));
                paymentInputRows.append(line);
            });
        };
        paymentRadios.forEach((radio) => radio.addEventListener('change', syncPayment));
        shippingRadios.forEach((radio) => radio.addEventListener('change', syncPayment));
        form.addEventListener('input', (event) => { if (event.target.closest?.('[data-payment-option]')) syncPayment(); });
        syncPayment();

        scope.querySelectorAll('[data-dharmashop-phone-mask]').forEach((input) => {
            const mask = input.dataset.dharmashopPhoneMask;
            const capacity = (mask.match(/#/g) || []).length;
            if (!capacity) return;

            const prefix = mask.slice(0, mask.indexOf('#'));
            const fixedDigits = prefix.replace(/\D/g, '');
            let positions = [];

            const digitsFrom = (value) => {
                let digits;
                if (prefix && value.startsWith(prefix)) {
                    digits = value.slice(prefix.length).replace(/\D/g, '');
                } else {
                    digits = value.replace(/\D/g, '');
                    if (fixedDigits && digits.length > capacity && digits.startsWith(fixedDigits)) {
                        digits = digits.slice(fixedDigits.length);
                    }
                }
                return digits.slice(0, capacity);
            };

            const render = (digits, caretDigits = digits.length) => {
                let output = '';
                let used = 0;
                positions = [];
                if (digits) {
                    for (const character of mask) {
                        if (character === '#') {
                            if (used === digits.length) break;
                            positions.push(output.length);
                            output += digits[used++];
                        } else {
                            output += character;
                        }
                    }
                }
                input.value = output;
                const caret = caretDigits >= digits.length ? output.length
                    : (caretDigits > 0 ? positions[caretDigits - 1] + 1 : prefix.length);
                input.setSelectionRange(caret, caret);
                input.setCustomValidity(digits && digits.length !== capacity ? options.phoneInvalid || '' : '');
            };

            input.addEventListener('input', () => {
                const caret = input.selectionStart ?? input.value.length;
                const before = input.value.slice(0, caret);
                let count = before.replace(/\D/g, '').length;
                if (prefix && input.value.startsWith(prefix)) count -= fixedDigits.length;
                render(digitsFrom(input.value), Math.max(0, count));
            });

            // Backspace/Delete через разделители удаляют цифру, а не восстанавливают разделитель бесконечно.
            input.addEventListener('beforeinput', (event) => {
                if (input.selectionStart !== input.selectionEnd || !event.inputType.startsWith('deleteContent')) return;
                const caret = input.selectionStart ?? 0;
                const backward = event.inputType === 'deleteContentBackward';
                const position = backward ? positions.filter((index) => index < caret).pop()
                    : positions.find((index) => index >= caret);
                if (position === undefined) {
                    event.preventDefault();
                } else {
                    input.setSelectionRange(position, position + 1);
                }
            });

            input.addEventListener('paste', (event) => {
                const text = event.clipboardData?.getData('text');
                if (!text) return;
                event.preventDefault();
                const start = input.selectionStart ?? 0;
                const end = input.selectionEnd ?? start;
                input.setRangeText(digitsFrom(text), start, end, 'end');
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            if (input.value) render(digitsFrom(input.value));
            // Подхватываем заполнение браузером и проверяем неполный номер после выхода из поля.
            input.addEventListener('change', () => render(digitsFrom(input.value)));
            input.addEventListener('blur', () => {
                const digits = digitsFrom(input.value);
                input.setCustomValidity(digits && digits.length !== capacity ? options.phoneInvalid || '' : '');
            });
        });
    };

    const initial = document.querySelector('[data-dharmashop-checkout-form]');
    if (initial) bind(initial);

    // Нижняя часть оформления «в один шаг» подгружена заново: привязываемся к новой форме.
    document.addEventListener('dharmashop:checkout-replaced', (event) => {
        cleanup.forEach((undo) => undo());
        cleanup = [];
        const form = event.detail?.form || document.querySelector('[data-dharmashop-checkout-form]');
        if (form) bind(form);
    });
})();
