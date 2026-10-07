/** Live days-and-hours countdown for active product sales. End timestamps are Unix seconds in UTC. */
(() => {
  const update = (timer) => {
    const end = Number(timer.dataset.saleEnd);
    const seconds = Math.ceil(end - Date.now() / 1000);

    if (!Number.isFinite(end) || seconds <= 0) {
      timer.hidden = true;
      return;
    }

    const totalHours = Math.ceil(seconds / 3600);
    const days = Math.floor(totalHours / 24);
    const hours = totalHours % 24;
    const remaining = `${days} ${timer.dataset.saleDays} ${hours} ${timer.dataset.saleHours}`;
    timer.textContent = `${timer.dataset.salePrefix} ${remaining}`;
    timer.setAttribute('aria-label', `${timer.dataset.saleLabel} ${remaining}`);
    timer.title = `${timer.dataset.saleLabel} ${remaining}`;
  };

  const refresh = () => document.querySelectorAll('[data-dharmashop-sale-countdown]').forEach(update);
  refresh();
  window.setInterval(refresh, 60_000);
})();
