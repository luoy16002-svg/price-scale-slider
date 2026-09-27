/* Price Scale Slider: renders every .pss element from its data-pss-config JSON. No dependencies. */
(function () {
  'use strict';

  /** Unit price for a quantity: the last tier whose `from` the quantity has reached. */
  function unitPrice(tiers, qty) {
    var price = tiers.length ? tiers[0].price : 0;
    for (var i = 0; i < tiers.length; i++) if (qty >= tiers[i].from) price = tiers[i].price;
    return price;
  }

  function total(plan, qty, rate) {
    return (plan.base + unitPrice(plan.tiers, qty) * qty) * rate;
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function format(amount, cur) {
    try {
      return new Intl.NumberFormat(document.documentElement.lang || undefined, {
        style: 'currency', currency: cur.code, minimumFractionDigits: 0, maximumFractionDigits: amount >= 1000 || amount % 1 === 0 ? 0 : 2,
      }).format(amount);
    } catch (e) {
      return cur.symbol + ' ' + amount.toFixed(2);
    }
  }

  function mount(root) {
    var cfg = JSON.parse(root.getAttribute('data-pss-config'));
    var state = { qty: cfg.value, cur: cfg.currencies[0] };
    root.textContent = '';

    var head = el('div', 'pss-head');
    head.appendChild(el('h2', 'pss-title', cfg.title));
    var select = el('select', 'pss-currency');
    select.setAttribute('aria-label', 'Currency');
    cfg.currencies.forEach(function (c, i) {
      var o = el('option', null, c.code + ' (' + c.symbol + ')');
      o.value = String(i);
      select.appendChild(o);
    });
    head.appendChild(select);
    root.appendChild(head);

    var qtyLabel = el('p', 'pss-qty');
    root.appendChild(qtyLabel);
    var range = el('input', 'pss-range');
    range.type = 'range';
    range.min = cfg.min; range.max = cfg.max; range.step = cfg.step; range.value = cfg.value;
    range.setAttribute('aria-label', cfg.unitLabel);
    root.appendChild(range);
    var scale = el('div', 'pss-scale');
    scale.appendChild(el('span', null, String(cfg.min)));
    scale.appendChild(el('span', null, String(cfg.max) + '+'));
    root.appendChild(scale);

    var cards = el('div', 'pss-plans');
    var priceEls = [], unitEls = [];
    cfg.plans.forEach(function (plan, i) {
      var card = el('div', 'pss-plan' + (i === cfg.plans.length - 1 ? ' pss-plan--featured' : ''));
      card.appendChild(el('h3', 'pss-plan-name', plan.name));
      var price = el('p', 'pss-price');
      card.appendChild(price);
      var unit = el('p', 'pss-unit');
      card.appendChild(unit);
      var ul = el('ul', 'pss-features');
      plan.features.forEach(function (f) { ul.appendChild(el('li', null, f)); });
      card.appendChild(ul);
      if (plan.ctaText) {
        var a = el('a', 'pss-cta', plan.ctaText);
        a.href = plan.ctaUrl || '#';
        card.appendChild(a);
      }
      priceEls.push(price); unitEls.push(unit);
      cards.appendChild(card);
    });
    root.appendChild(cards);

    function render() {
      var q = state.qty, cur = state.cur;
      var pct = (q - cfg.min) / (cfg.max - cfg.min) * 100;
      range.style.setProperty('--pss-fill', pct + '%');
      qtyLabel.textContent = q + ' ' + cfg.unitLabel;
      range.setAttribute('aria-valuetext', q + ' ' + cfg.unitLabel);
      cfg.plans.forEach(function (plan, i) {
        priceEls[i].textContent = '';
        priceEls[i].appendChild(el('span', 'pss-amount', format(total(plan, q, cur.rate), cur)));
        priceEls[i].appendChild(el('span', 'pss-period', ' ' + cfg.period));
        unitEls[i].textContent = format(unitPrice(plan.tiers, q) * cur.rate, cur) + ' per ' + cfg.unitLabel.replace(/s$/, '') +
          (plan.base ? ' + ' + format(plan.base * cur.rate, cur) + ' fixed' : '');
      });
    }

    range.addEventListener('input', function () { state.qty = Number(range.value); render(); });
    select.addEventListener('change', function () { state.cur = cfg.currencies[Number(select.value)]; render(); });
    render();
  }

  function init() {
    var nodes = document.querySelectorAll('.pss[data-pss-config]');
    for (var i = 0; i < nodes.length; i++) mount(nodes[i]);
  }

  window.PriceScale = { unitPrice: unitPrice, total: total, init: init };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
