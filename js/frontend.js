// frontend.js — loads the invoice JSON and renders the printable page using the
// shared renderInvoiceHTML() template (js/invoice-render.js).
(function () {
  'use strict';

  var root = document.getElementById('invoice-root');
  var toolbar = document.getElementById('invoice-toolbar');
  var printBtn = document.getElementById('print-btn');
  var adminLink = document.getElementById('admin-link');

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
    });
  }

  function showError(message) {
    root.removeAttribute('data-loading');
    root.textContent = message;
    if (toolbar) { toolbar.hidden = true; }
  }

  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }

  var params = new URLSearchParams(window.location.search);
  var dataKey = params.get('data');
  var query = dataKey ? '&data=' + encodeURIComponent(dataKey) : '';

  fetch('api.php?action=get' + query, { headers: { Accept: 'application/json' } })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (!data.ok) { throw new Error(data.error || 'Failed to load invoice.'); }

      var invoice = data.invoice;
      var doc = invoice.document || {};
      document.title = ((doc.title || 'Invoice') + ' - ' + (doc.number || '')).trim();

      root.removeAttribute('data-loading');
      root.innerHTML = window.renderInvoiceHTML(invoice);

      if (adminLink && data.key && data.key !== 'invoice_data.json') {
        adminLink.href = 'admin.php?data=' + encodeURIComponent(data.key);
      }
    })
    .catch(function (err) { showError('Could not load invoice: ' + esc(err.message)); });
})();
