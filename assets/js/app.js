(function () {
  const sidebar = document.getElementById('sidebar');
  const scrim = document.getElementById('scrim');
  const toggle = document.getElementById('menuToggle');

  function closeMenu() {
    sidebar?.classList.remove('open');
    scrim?.classList.remove('show');
  }

  toggle?.addEventListener('click', function () {
    sidebar?.classList.toggle('open');
    scrim?.classList.toggle('show');
  });
  scrim?.addEventListener('click', closeMenu);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeMenu();
      document.getElementById('cartPanel')?.classList.remove('open');
    }
  });

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return;
    }
    const button = event.submitter;
    if (button instanceof HTMLButtonElement) {
      setTimeout(function () {
        button.disabled = true;
      }, 0);
    }
  });

  const search = document.getElementById('posSearch');
  if (search instanceof HTMLInputElement) {
    const cards = document.querySelectorAll('[data-search]');
    const filter = function () {
      const query = search.value.trim().toLowerCase();
      cards.forEach(function (card) {
        const hay = (card.getAttribute('data-search') || '').toLowerCase();
        card.hidden = query !== '' && !hay.includes(query);
      });
    };
    search.addEventListener('input', filter);
    if (!window.matchMedia('(max-width: 760px)').matches) {
      search.focus();
      if (search.value) search.select();
    }
  }

  const cartPanel = document.getElementById('cartPanel');
  document.getElementById('cartFab')?.addEventListener('click', function () {
    cartPanel?.classList.add('open');
  });
  document.getElementById('cartClose')?.addEventListener('click', function () {
    cartPanel?.classList.remove('open');
  });

  const tender = document.getElementById('tender');
  const payTotal = document.getElementById('payTotal');
  const changeOut = document.getElementById('changeOut');
  if (tender instanceof HTMLInputElement && payTotal && changeOut) {
    const total = parseFloat(payTotal.getAttribute('data-total') || '0');
    const symbol = document.body.getAttribute('data-currency') || 'Rs';
    const exactLabel = document.body.getAttribute('data-msg-exact') || 'Exact amount';
    const numberLabel = document.body.getAttribute('data-msg-number') || 'Enter a number';
    const shortLabel = document.body.getAttribute('data-msg-short') || 'Short';
    const changeLabel = document.body.getAttribute('data-msg-change') || 'Change';
    const render = function () {
      const raw = tender.value.trim();
      if (raw === '') {
        changeOut.textContent = exactLabel;
        return;
      }
      const value = parseFloat(raw.replace(/,/g, ''));
      if (Number.isNaN(value)) {
        changeOut.textContent = numberLabel;
        return;
      }
      const diff = Math.round((value - total) * 100) / 100;
      if (diff < 0) {
        changeOut.textContent = shortLabel + ' ' + symbol + ' ' + Math.abs(diff).toFixed(2);
      } else {
        changeOut.textContent = changeLabel + ' ' + symbol + ' ' + diff.toFixed(2);
      }
    };
    tender.addEventListener('input', render);
    render();
  }

  const stockLines = document.getElementById('stockLines');
  const stockTpl = document.getElementById('stockLineTpl');
  document.getElementById('addStockLine')?.addEventListener('click', function () {
    if (stockLines && stockTpl instanceof HTMLTemplateElement) {
      stockLines.appendChild(stockTpl.content.cloneNode(true));
    }
  });
  document.addEventListener('change', function (event) {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement) || !target.matches('.stock-line select')) return;
    const option = target.selectedOptions[0];
    const cost = target.closest('.stock-line')?.querySelector('.cost');
    if (option && cost instanceof HTMLInputElement && option.dataset.cost) {
      cost.value = option.dataset.cost;
    }
  });
  document.addEventListener('click', function (event) {
    const button = event.target instanceof Element ? event.target.closest('.remove-line') : null;
    if (!button) return;
    const line = button.closest('.stock-line');
    const all = document.querySelectorAll('#stockLines .stock-line');
    if (line && all.length > 1) line.remove();
  });

  const scopeControl = document.querySelector('select[name="apply_to"]');
  const productPicks = document.getElementById('productPicks');
  function syncScope() {
    if (!productPicks || !(scopeControl instanceof HTMLSelectElement)) return;
    productPicks.hidden = scopeControl.value !== 'selected';
  }
  scopeControl?.addEventListener('change', syncScope);
  syncScope();

  const pickFilter = document.getElementById('filterProducts');
  if (pickFilter instanceof HTMLInputElement) {
    pickFilter.addEventListener('input', function () {
      const query = pickFilter.value.trim().toLowerCase();
      document.querySelectorAll('.check-row').forEach(function (row) {
        const hay = (row.getAttribute('data-name') || '').toLowerCase();
        row.hidden = query !== '' && !hay.includes(query);
      });
    });
  }

  const employee = document.getElementById('employee');
  const baseSalary = document.getElementById('baseSalary');
  const deduct = document.getElementById('deduct');
  const netOut = document.getElementById('netOut');
  function updateNet() {
    if (!(baseSalary instanceof HTMLInputElement) || !(deduct instanceof HTMLInputElement) || !netOut) return;
    const salary = parseFloat(baseSalary.value || '0');
    const off = parseFloat(deduct.value || '0');
    const symbol = document.body.getAttribute('data-currency') || 'Rs';
    if (Number.isNaN(salary) || Number.isNaN(off)) {
      netOut.textContent = '—';
      return;
    }
    netOut.textContent = symbol + ' ' + (salary - off).toFixed(2);
  }
  employee?.addEventListener('change', function () {
    if (!(employee instanceof HTMLSelectElement)) return;
    const option = employee.selectedOptions[0];
    if (!option || !(baseSalary instanceof HTMLInputElement) || !(deduct instanceof HTMLInputElement)) return;
    const salary = parseFloat(option.dataset.salary || '0');
    const balance = parseFloat(option.dataset.balance || '0');
    baseSalary.value = salary.toFixed(2);
    deduct.value = Math.min(salary, balance).toFixed(2);
    const balanceOut = document.getElementById('balanceOut');
    if (balanceOut) {
      const symbol = document.body.getAttribute('data-currency') || 'Rs';
      balanceOut.textContent = symbol + ' ' + balance.toFixed(2);
    }
    updateNet();
  });
  baseSalary?.addEventListener('input', updateNet);
  deduct?.addEventListener('input', updateNet);
  updateNet();

  const langPick = document.getElementById('langPick');
  if (langPick instanceof HTMLSelectElement) {
    langPick.addEventListener('change', function () {
      const next = new URL(window.location.href);
      next.searchParams.set('lang', langPick.value);
      window.location.href = next.toString();
    });
  }

  if (document.body.getAttribute('data-print') === '1') {
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.print();
      }, 250);
    });
  }
})();
