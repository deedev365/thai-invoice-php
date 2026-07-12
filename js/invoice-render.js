// invoice-render.js — single source of truth for the printable invoice markup.
// Shared by the public print view (frontend.js) and the admin live preview so
// the layout only ever lives in one place. Exposes window.renderInvoiceHTML.
(function () {
  'use strict';

  // Fallback labels so the admin preview works before the server attaches its
  // own translate map. Mirrors default_translations() in lib.php.
  var DEFAULT_TRANSLATIONS = {
    seller: 'Seller', address: 'Address', tax_id: 'Tax ID', number: 'Number',
    issue_date: 'Issue Date', credit: 'Credit', due_date: 'Due Date',
    reference: 'Reference', project_name: 'Project Name',
    contancts: 'Please contact me back', customer_name: 'Name',
    customer_address: 'Address', customer_tax_id: 'Tax ID',
    items_description: 'Description', items_quantity: 'Quantity',
    items_price: 'Price', items_discount: 'Discount', items_vat: 'VAT',
    items_amount_before_tax: 'Before Tax', summary: 'Summary',
    taxable_amount_7: 'Taxable Amount (7% VAT)', vat_amount_7: 'VAT (7%)',
    total_amount: 'Total Amount', withholding_tax: 'Withholding Tax',
    amount_payable: 'Amount Payable', payment: 'Payment'
  };

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function isBlank(value) {
    return value == null || String(value).trim() === '';
  }

  // Mirror of PHP nl2br(): keep text escaped, turn newlines into <br>.
  function nl2br(value) {
    return esc(value).replace(/\r\n|\n|\r/g, '<br>');
  }

  // One <div> per line (used for multi-line item details).
  function linesHtml(value) {
    return String(value == null ? '' : value)
      .split(/\r\n|\n|\r/)
      .map(function (line) { return '<div>' + esc(line) + '</div>'; })
      .join('');
  }

  // An "label : value" info row that collapses when the value is empty.
  function infoRow(label, value, opts) {
    if (isBlank(value)) {
      return '';
    }
    var rendered = opts && opts.multiline ? nl2br(value) : esc(value);
    var extra = opts && opts.className ? ' ' + opts.className : '';
    return '<div class="info-row' + extra + '">' +
      '<span class="info-label">' + esc(label) + ' :</span>' +
      '<span class="info-value">' + rendered + '</span>' +
      '</div>';
  }

  function metaRow(label, value) {
    if (isBlank(value)) {
      return '';
    }
    return '<tr><td class="meta-label">' + esc(label) + ' :</td>' +
      '<td class="meta-val">' + esc(value) + '</td></tr>';
  }

  function contactRow(icon, value) {
    if (isBlank(value)) {
      return '';
    }
    return '<div class="contact-row"><span class="contact-icon">' + icon + '</span>' +
      '<span>' + esc(value) + '</span></div>';
  }

  function itemRow(item, index) {
    return '' +
      '<tr>' +
        '<td>' +
          '<span class="item-name">' + (index + 1) + '. ' + esc(item.name) + '</span>' +
          '<div class="item-detail">' + linesHtml(item.detail) + '</div>' +
        '</td>' +
        '<td class="center">' + esc(item.qty) + '</td>' +
        '<td class="right">' + esc(item.price) + '</td>' +
        '<td class="right">' + esc(item.discount) + '</td>' +
        '<td class="center">' + esc(item.vat) + '</td>' +
        '<td class="right">' + esc(item.pre_tax) + '</td>' +
      '</tr>';
  }

  function bankCard(bank) {
    return '' +
      '<div class="bank-card">' +
        '<div class="bank-logo ' + esc(bank.logo_class) + '">' + esc(bank.logo_text) + '</div>' +
        '<div class="bank-info">' +
          '<div class="bank-name">' + esc(bank.bank_name) + '</div>' +
          '<div class="acc-num">' + esc(bank.account_number) + '</div>' +
          '<div class="acc-name">' + esc(bank.account_name) + '</div>' +
        '</div>' +
      '</div>';
  }

  // Logo comes from the seller data: a logo image if provided, otherwise the
  // seller name as a text mark (no more hardcoded company name).
  function logoArea(seller) {
    if (!isBlank(seller.logo_url)) {
      return '<img src="' + esc(seller.logo_url) + '" alt="' + esc(seller.name) + '">';
    }
    if (!isBlank(seller.name)) {
      return '<div class="logo-text">' + esc(seller.name) + '</div>';
    }
    return '';
  }

  function renderInvoiceHTML(invoice) {
    invoice = invoice || {};
    var t = Object.assign({}, DEFAULT_TRANSLATIONS, invoice.translate || {});
    var doc = invoice.document || {};
    var seller = invoice.seller || {};
    var customer = invoice.customer || {};
    var contact = invoice.contact || {};
    var summary = invoice.summary || {};
    var items = invoice.items || [];
    var banks = invoice.banks || [];

    var lineIcon = contact.line_icon_url
      ? '<img src="' + esc(contact.line_icon_url) + '" alt="">'
      : '👤';

    var summaryHtml = isBlank(summary.total_amount) ? '' : '' +
      '<div class="summary-section">' +
        '<div class="summary-left">' +
          '<div class="summary-title">🧾 ' + esc(t.summary) + '</div>' +
          '<div class="summary-line"><span class="s-label">' + esc(t.taxable_amount_7) + '</span><span class="s-val">' + esc(summary.taxable_amount) + '</span></div>' +
          '<div class="summary-line"><span class="s-label">' + esc(t.vat_amount_7) + '</span><span class="s-val">' + esc(summary.vat_amount) + '</span></div>' +
          '<div class="summary-line"><span class="s-label">' + esc(t.total_amount) + '</span><span class="s-val">' + esc(summary.total_words) + '</span></div>' +
        '</div>' +
        '<div class="summary-right">' +
          '<div class="total-box">' +
            '<div class="total-label">' + esc(t.total_amount) + '</div>' +
            '<div class="total-amount">' + esc(summary.total_amount) + '</div>' +
          '</div>' +
          '<div class="deduct-lines">' +
            '<div class="deduct-line"><span>' + esc(t.withholding_tax) + '</span><span>' + esc(summary.withholding) + '</span></div>' +
            '<div class="deduct-line"><span>' + esc(t.amount_payable) + '</span><span><strong>' + esc(summary.payable) + '</strong></span></div>' +
          '</div>' +
        '</div>' +
      '</div>';

    var paymentHtml = banks.length === 0 ? '' : '' +
      '<div class="payment-section">' +
        '<div class="payment-title">💳 ' + esc(t.payment) + '</div>' +
        '<div class="payment-banks">' + banks.map(bankCard).join('') + '</div>' +
      '</div>';

    var noteHtml = isBlank(invoice.note) ? '' : '<div class="note-footer">📝 ' + esc(invoice.note) + '</div>';

    return '' +
      '<div class="page">' +
        '<div class="top-bar">' +
          '<div class="logo-area">' + logoArea(seller) + '</div>' +
          '<div class="doc-title-area">' +
            '<div class="page-label">' + esc(doc.page_label) + '</div>' +
            '<div class="doc-title">' + esc(doc.title) + '</div>' +
          '</div>' +
        '</div>' +

        '<div class="info-section">' +
          '<div class="seller-block">' +
            infoRow(t.seller, seller.name) +
            infoRow(t.address, seller.address, { multiline: true }) +
            infoRow(t.tax_id, seller.tax_id, { className: 'tax-id-row' }) +
          '</div>' +
          '<div class="doc-meta-block">' +
            '<table class="meta-table">' +
              metaRow(t.number, doc.number).replace('meta-val', 'meta-val doc-num') +
              metaRow(t.issue_date, doc.issue_date) +
              metaRow(t.credit, doc.credit) +
              metaRow(t.due_date, doc.due_date) +
              metaRow(t.reference, doc.reference) +
              metaRow(t.project_name, doc.project_name) +
            '</table>' +
          '</div>' +
        '</div>' +

        '<div class="customer-contact-section">' +
          '<div class="customer-block">' +
            infoRow(t.customer_name, customer.name) +
            infoRow(t.customer_address, customer.address, { multiline: true }) +
            infoRow(t.customer_tax_id, customer.tax_id) +
            infoRow('Phone', customer.phone) +
            infoRow('Email', customer.email) +
          '</div>' +
          '<div class="contact-block">' +
            '<div class="contact-title">' + esc(t.contancts) + ' :</div>' +
            contactRow('👤', contact.name) +
            contactRow('📞', contact.phone) +
            contactRow('✉️', contact.email) +
            contactRow(lineIcon, contact.line_id) +
          '</div>' +
        '</div>' +

        '<table class="items-table">' +
          '<thead>' +
            '<tr>' +
              '<th style="width:50%">' + esc(t.items_description) + '</th>' +
              '<th class="center" style="width:7%">' + esc(t.items_quantity) + '</th>' +
              '<th class="right" style="width:12%">' + esc(t.items_price) + '</th>' +
              '<th class="right" style="width:8%">' + esc(t.items_discount) + '</th>' +
              '<th class="center" style="width:5%">' + esc(t.items_vat) + '</th>' +
              '<th class="right" style="width:13%">' + esc(t.items_amount_before_tax) + '</th>' +
            '</tr>' +
          '</thead>' +
          '<tbody>' + items.map(itemRow).join('') + '</tbody>' +
        '</table>' +

        summaryHtml +
        paymentHtml +
        noteHtml +
      '</div>';
  }

  window.renderInvoiceHTML = renderInvoiceHTML;
})();
