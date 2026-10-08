/** Checkout: маски телефона, состояние ожидания POST и защита от повторного нажатия. */
(() => {
    'use strict';

    const form = document.querySelector('[data-dharmashop-checkout-form]');
    if (!form) return;

    const options = window.Joomla?.getOptions('com_dharmashop.checkout', {}) || {};
    const processing = document.querySelector('[data-checkout-processing]');
    const slowNotice = processing?.querySelector('[data-checkout-processing-slow]');
    const buttons = Array.from(form.querySelectorAll('[data-checkout-submit]'), (button) => ({
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
    window.addEventListener('pageshow', reset);

    form.querySelectorAll('[data-dharmashop-phone-mask]').forEach((input) => {
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
})();
