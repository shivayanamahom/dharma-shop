/**
 * Dharma Shop: виджет количества в карточке товара (кнопки «−» и «+»). Подключается только если в карточках включён
 * виджет количества. Шаг и минимум — из data-атрибутов поля; события делегированы, поэтому работают и после подгрузки списка.
 */
(() => {
  'use strict';

  const decimals = (step) => (String(step).split('.')[1] || '').length;
  const clamp = (input, value) => {
    const step = parseFloat(input.dataset.step) || 1;
    const min = parseFloat(input.dataset.min) || step;
    const max = input.dataset.max ? parseFloat(input.dataset.max) : Infinity;
    const number = Number.isFinite(value) ? value : min;
    // Значение приводится к шагу от минимума.
    const stepped = min + Math.round((number - min) / step) * step;

    return Math.min(max, Math.max(min, stepped)).toFixed(decimals(step));
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-dharmashop-qty-step]');
    if (!button) return;
    const input = button.closest('[data-dharmashop-qty]').querySelector('input');
    const step = parseFloat(input.dataset.step) || 1;
    const direction = button.dataset.dharmashopQtyStep === 'up' ? 1 : -1;
    input.value = clamp(input, (parseFloat(String(input.value).replace(',', '.')) || 0) + direction * step);
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  document.addEventListener('change', (event) => {
    const input = event.target.closest('[data-dharmashop-qty] input');
    if (input) input.value = clamp(input, parseFloat(String(input.value).replace(',', '.')));
  });
})();
