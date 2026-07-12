// admin.js — invoice editor driven entirely by the JSON API (api.php).
(function () {
  'use strict';

  var form = document.getElementById('invoice-form');
  var picker = document.getElementById('data');
  var sourceInput = document.getElementById('source-data');
  var messageBox = document.getElementById('message');
  var errorBox = document.getElementById('error');
  var docNumberLabel = document.getElementById('doc-number-label');
  var editingLabel = document.getElementById('editing-label');
  var openInvoiceLink = document.getElementById('open-invoice');
  var previewFrame = document.getElementById('preview-frame');
  var duplicateBtn = document.getElementById('duplicate-invoice');
  var deleteBtn = document.getElementById('delete-invoice');

  var issueInput = document.getElementById('issue-date');
  var creditInput = document.getElementById('credit-days');
  var dueInput = document.getElementById('due-date');
  var docNumberInput = document.getElementById('document-number');

  var itemFields = ['name', 'qty', 'price', 'discount', 'vat', 'pre_tax', 'detail'];
  var bankFields = ['logo_class', 'logo_text', 'bank_name', 'account_number', 'account_name'];

  var selectedKey = 'invoice_data.json';
  var isNewInvoice = true;
  var nextNumberTimer = null;
  var recomputeTimer = null;
  var loadedTranslate = null;

  // ---- helpers ------------------------------------------------------------

  function api(action, opts) {
    return fetch('api.php?action=' + action, opts).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok || !data.ok) {
          throw new Error(data && data.error ? data.error : 'Request failed (' + res.status + ')');
        }
        return data;
      });
    });
  }

  function showMessage(text) {
    messageBox.textContent = text;
    messageBox.hidden = !text;
  }

  function showError(text) {
    errorBox.textContent = text;
    errorBox.hidden = !text;
  }

  function get(obj, path) {
    return path.split('.').reduce(function (acc, key) {
      return acc == null ? '' : acc[key];
    }, obj);
  }

  // ---- form <-> data ------------------------------------------------------

  function fillScalarFields(invoice) {
    form.querySelectorAll('[data-field]').forEach(function (el) {
      var value = get(invoice, el.getAttribute('data-field'));
      el.value = value == null ? '' : value;
    });
  }

  function rowFromTemplate(templateId, rowType, fields, fieldAttr, data) {
    var tpl = document.getElementById(templateId);
    var fragment = tpl.content.cloneNode(true);
    fields.forEach(function (name) {
      var input = fragment.querySelector('[' + fieldAttr + '="' + name + '"]');
      if (input) { input.value = (data && data[name] != null) ? data[name] : ''; }
    });
    return fragment;
  }

  function renderRepeater(listType, templateId, fields, fieldAttr, rows) {
    var list = form.querySelector('[data-list="' + listType + '"]');
    list.innerHTML = '';
    (rows && rows.length ? rows : [{}]).forEach(function (row) {
      list.appendChild(rowFromTemplate(templateId, listType, fields, fieldAttr, row));
    });
    renumberRows(listType);
  }

  function collectRepeater(listType, fields, fieldAttr) {
    var rows = [];
    form.querySelectorAll('[data-row="' + listType + '"]').forEach(function (row) {
      var obj = {};
      fields.forEach(function (name) {
        var input = row.querySelector('[' + fieldAttr + '="' + name + '"]');
        obj[name] = input ? input.value.trim() : '';
      });
      rows.push(obj);
    });
    return rows;
  }

  function collectInvoice() {
    var invoice = {
      document: {}, seller: {}, customer: {}, contact: {}, summary: {}, note: ''
    };
    form.querySelectorAll('[data-field]').forEach(function (el) {
      var path = el.getAttribute('data-field').split('.');
      if (path.length === 1) {
        invoice[path[0]] = el.value;
      } else {
        invoice[path[0]] = invoice[path[0]] || {};
        invoice[path[0]][path[1]] = el.value;
      }
    });
    invoice.items = collectRepeater('items', itemFields, 'data-item-field');
    invoice.banks = collectRepeater('banks', bankFields, 'data-bank-field');
    return invoice;
  }

  // ---- repeater controls --------------------------------------------------

  function renumberRows(listType) {
    form.querySelectorAll('[data-row="' + listType + '"] [data-index]').forEach(function (node, index) {
      node.textContent = index + 1;
    });
  }

  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var type = btn.getAttribute('data-add');
      var list = form.querySelector('[data-list="' + type + '"]');
      if (type === 'items') {
        // Default VAT to 7% for new lines (the common case for Thai invoices).
        list.appendChild(rowFromTemplate('items-template', 'items', itemFields, 'data-item-field', { vat: '7' }));
      } else {
        list.appendChild(rowFromTemplate('banks-template', 'banks', bankFields, 'data-bank-field', {}));
      }
      renumberRows(type);
      scheduleRecompute();
    });
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-remove]');
    if (!button) return;
    var row = button.closest('[data-row]');
    var type = row.dataset.row;
    row.remove();
    renumberRows(type);
    scheduleRecompute();
  });

  // ---- date / number logic (client-side, number confirmed via API) --------

  function parseInvoiceDate(value) {
    var match = String(value).trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!match) return null;
    var day = Number(match[1]), month = Number(match[2]), year = Number(match[3]);
    var date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
      return null;
    }
    return date;
  }

  function formatInvoiceDate(date) {
    var day = String(date.getDate()).padStart(2, '0');
    var month = String(date.getMonth() + 1).padStart(2, '0');
    return day + '/' + month + '/' + date.getFullYear();
  }

  function refreshNextNumber(issueValue) {
    if (!isNewInvoice) return;
    if (nextNumberTimer) clearTimeout(nextNumberTimer);
    nextNumberTimer = setTimeout(function () {
      api('next-number&date=' + encodeURIComponent(issueValue))
        .then(function (data) {
          if (isNewInvoice) {
            docNumberInput.value = data.number;
            docNumberLabel.textContent = data.number;
          }
        })
        .catch(function () { /* keep last known number */ });
    }, 250);
  }

  function updateDueDate() {
    var issueDate = parseInvoiceDate(issueInput.value);
    var creditDays = Math.max(0, parseInt(creditInput.value, 10) || 0);
    if (!issueDate) return;
    refreshNextNumber(issueInput.value);
    issueDate.setDate(issueDate.getDate() + creditDays);
    dueInput.value = formatInvoiceDate(issueDate);
  }

  issueInput.addEventListener('input', updateDueDate);
  creditInput.addEventListener('input', updateDueDate);

  // ---- load / save --------------------------------------------------------

  function applyInvoice(key, invoice) {
    selectedKey = key;
    isNewInvoice = (key === 'invoice_data.json');
    sourceInput.value = key;
    editingLabel.textContent = key;
    docNumberLabel.textContent = (invoice.document && invoice.document.number) || '-';
    openInvoiceLink.href = 'index.php?data=' + encodeURIComponent(key);

    fillScalarFields(invoice);
    renderRepeater('items', 'items-template', itemFields, 'data-item-field', invoice.items);
    renderRepeater('banks', 'banks-template', bankFields, 'data-bank-field', invoice.banks);

    loadedTranslate = invoice.translate || null;
    updateActionButtons();
    recompute();
  }

  // ---- live totals & preview ----------------------------------------------

  function setField(path, value) {
    var el = form.querySelector('[data-field="' + path + '"]');
    if (el) { el.value = value == null ? '' : value; }
  }

  function updatePreview(invoice) {
    if (!previewFrame || !window.renderInvoiceHTML) { return; }
    var cssHref = new URL('css/index.css', document.baseURI).href;
    previewFrame.srcdoc =
      '<!doctype html><html lang="th"><head><meta charset="utf-8">' +
      '<link rel="stylesheet" href="' + cssHref + '">' +
      '<style>body{padding:16px;margin:0}</style></head><body>' +
      '<div class="page-wrapper">' + window.renderInvoiceHTML(invoice) + '</div>' +
      '</body></html>';
  }

  // Recalculate line totals + summary from the current form and refresh the
  // read-only outputs and the live preview. Server stays authoritative on save.
  function recompute() {
    if (!window.InvoiceCalc) { return; }
    var invoice = collectInvoice();
    var result = window.InvoiceCalc.computeTotals(invoice);

    form.querySelectorAll('[data-row="items"]').forEach(function (row, index) {
      var input = row.querySelector('[data-item-field="pre_tax"]');
      if (input) { input.value = (result.items[index] && result.items[index].pre_tax) || ''; }
    });

    Object.keys(result.summary).forEach(function (key) {
      setField('summary.' + key, result.summary[key]);
    });

    invoice.items = result.items;
    invoice.summary = result.summary;
    if (loadedTranslate) { invoice.translate = loadedTranslate; }
    updatePreview(invoice);
  }

  function scheduleRecompute() {
    if (recomputeTimer) { clearTimeout(recomputeTimer); }
    recomputeTimer = setTimeout(recompute, 250);
  }

  function updateActionButtons() {
    var isTemplate = selectedKey === 'invoice_data.json';
    if (duplicateBtn) { duplicateBtn.disabled = isTemplate; }
    if (deleteBtn) { deleteBtn.disabled = isTemplate; }
  }

  form.addEventListener('input', scheduleRecompute);
  form.addEventListener('change', scheduleRecompute);

  function loadInvoice(key) {
    showError('');
    var query = key ? '&data=' + encodeURIComponent(key) : '';
    return api('get' + query)
      .then(function (data) {
        applyInvoice(data.key, data.invoice);
        if (picker.querySelector('option[value="' + data.key + '"]')) {
          picker.value = data.key;
        }
        var url = 'admin.php' + (data.key === 'invoice_data.json' ? '' : '?data=' + encodeURIComponent(data.key));
        window.history.replaceState({}, '', url);
      })
      .catch(function (err) { showError('Could not load invoice: ' + err.message); });
  }

  function loadFileList(selectValue) {
    return api('list').then(function (data) {
      picker.innerHTML = '';
      data.files.forEach(function (file) {
        var option = document.createElement('option');
        option.value = file.key;
        option.textContent = file.label;
        picker.appendChild(option);
      });
      if (selectValue && picker.querySelector('option[value="' + selectValue + '"]')) {
        picker.value = selectValue;
      }
    });
  }

  document.getElementById('picker-form').addEventListener('submit', function (event) {
    event.preventDefault();
    showMessage('');
    loadInvoice(picker.value);
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    showMessage('');
    showError('');

    var payload = { source_data: selectedKey, invoice: collectInvoice() };
    api('save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (data) {
        return loadFileList(data.key).then(function () {
          applyInvoice(data.key, data.invoice);
          picker.value = data.key;
          window.history.replaceState({}, '', 'admin.php?data=' + encodeURIComponent(data.key));
          showMessage((data.action === 'updated' ? 'Invoice updated: ' : 'Invoice created: ') + data.key);
        });
      })
      .catch(function (err) { showError('Could not save invoice: ' + err.message); });
  });

  function postAction(action, key) {
    return api(action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ source_data: key })
    });
  }

  if (duplicateBtn) {
    duplicateBtn.addEventListener('click', function () {
      if (selectedKey === 'invoice_data.json') { return; }
      showMessage('');
      showError('');
      postAction('duplicate', selectedKey)
        .then(function (data) {
          return loadFileList(data.key).then(function () {
            applyInvoice(data.key, data.invoice);
            picker.value = data.key;
            window.history.replaceState({}, '', 'admin.php?data=' + encodeURIComponent(data.key));
            showMessage('Invoice duplicated as ' + data.key);
          });
        })
        .catch(function (err) { showError('Could not duplicate invoice: ' + err.message); });
    });
  }

  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      if (selectedKey === 'invoice_data.json') { return; }
      if (!window.confirm('Delete ' + selectedKey + '? This cannot be undone.')) { return; }
      showMessage('');
      showError('');
      var deletedKey = selectedKey;
      postAction('delete', selectedKey)
        .then(function () {
          return loadFileList('invoice_data.json').then(function () {
            return loadInvoice('');
          });
        })
        .then(function () { showMessage('Invoice deleted: ' + deletedKey); })
        .catch(function (err) { showError('Could not delete invoice: ' + err.message); });
    });
  }

  // ---- boot ---------------------------------------------------------------

  var params = new URLSearchParams(window.location.search);
  var initialKey = params.get('data') || '';

  loadFileList(initialKey || 'invoice_data.json')
    .then(function () { return loadInvoice(initialKey); })
    .catch(function (err) { showError('Could not initialise: ' + err.message); });
})();
