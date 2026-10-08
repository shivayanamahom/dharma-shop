/** Контактная форма checkout: маски телефона и защита кнопки от повторного нажатия. */
(() => {
    'use strict';

    const form = document.querySelector('[data-dharmashop-checkout-form]');
    if (!form) return;

    let sending = false;
    form.addEventListener('submit', (event) => {
        if (sending) { event.preventDefault(); return; }
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        sending = true;
        form.querySelectorAll('[data-checkout-submit]').forEach((button) => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
    });
    window.addEventListener('pageshow', () => {
        sending = false;
        form.querySelectorAll('[data-checkout-submit]').forEach((button) => { button.disabled = false; button.removeAttribute('aria-busy'); });
    });

    const options = window.Joomla?.getOptions('com_dharmashop.checkout', {}) || {};
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
