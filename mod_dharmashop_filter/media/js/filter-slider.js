/**
 * Dharma Shop: ползунки диапазона в фильтре (цена, числа). Два штатных form-range синхронизируются с полями «от» и «до»:
 * движение ползунка меняет поле (граница каталога = пустое поле, то есть без условия), ввод в поле двигает ползунок.
 * Ползунок не имеет name: на сервер уходят только поля. Применение (по кнопке или сразу) выполняет форма фильтра.
 */
(() => {
  'use strict';

  const parts = (box) => {
    const row = box.nextElementSibling;
    const numbers = row ? row.querySelectorAll('input[type="number"]') : [];
    return { min: box.querySelector('[data-dharmashop-slider-min]'), max: box.querySelector('[data-dharmashop-slider-max]'), from: numbers[0] || null, to: numbers[1] || null };
  };

  document.addEventListener('input', (event) => {
    const target = event.target;
    if (!target.matches) return;

    const slider = target.matches('[data-dharmashop-slider-min], [data-dharmashop-slider-max]');
    const box = slider ? target.closest('[data-dharmashop-slider]') : null;

    if (box) {
      const p = parts(box);
      if (!p.min || !p.max || !p.from || !p.to) return;
      let low = Number(p.min.value);
      let high = Number(p.max.value);
      // Бегунки не пересекаются: тот, что двигали, упирается в другой.
      if (low > high) {
        if (target === p.min) { low = high; p.min.value = String(low); } else { high = low; p.max.value = String(high); }
      }
      const atMin = low <= Number(p.min.min);
      const atMax = high >= Number(p.max.max);
      p.from.value = atMin ? '' : String(low);
      p.to.value = atMax ? '' : String(high);
      return;
    }

    // Ввод в поле двигает ползунок.
    const field = target.closest ? target.closest('.row') : null;
    const holder = field && field.previousElementSibling && field.previousElementSibling.matches('[data-dharmashop-slider]') ? field.previousElementSibling : null;
    if (!holder || !target.matches('input[type="number"]')) return;
    const p = parts(holder);
    if (!p.min || !p.max) return;
    const first = target === p.from;
    const raw = target.value === '' ? (first ? p.min.min : p.max.max) : target.value;
    (first ? p.min : p.max).value = raw;
  });
})();
