(() => {
  'use strict';
  const options = window.Joomla?.getOptions?.('com_dharmashop.recentProducts') || {};
  const id = Number(options.productId);
  if (!Number.isSafeInteger(id) || id < 1) return;
  const name = String(options.cookieName || 'dharmashop_recent_products');
  const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
  let ids = [];
  try { ids = decodeURIComponent(match?.[1] || '').split(',').map(Number).filter((value) => Number.isSafeInteger(value) && value > 0); } catch { ids = []; }
  ids = [id, ...ids.filter((value) => value !== id)].slice(0, 20);
  const path = String(options.cookiePath || '/');
  document.cookie = name + '=' + encodeURIComponent(ids.join(',')) + '; Max-Age=15552000; Path=' + path + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
})();
