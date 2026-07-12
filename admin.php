<?php
require __DIR__ . '/vendor/autoload.php';
require_admin_page();
?>
<!doctype html>
<html lang="th">
<head>
<link rel="stylesheet" href="css/admin.css">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice Admin</title>
</head>
<body>
<div class="navbar">
  <h1 class="navbar-title">Invoice</h1>
  <div class="navbar-menu">
    <a href="index.php">Invoice</a>
    <a href="admin.php" class="active">Admin</a>
  </div>
</div>

<div class="shell">
  <div class="topbar">
    <div>
      <h1><a href="admin.php">Invoice Admin</a></h1>
      <div class="doc-number"><span id="doc-number-label">-</span> - Editing <span id="editing-label">-</span></div>
    </div>
    <div class="actions">
      <form class="invoice-picker" id="picker-form">
        <div>
          <label for="data">Choose invoice</label>
          <select id="data" name="data"></select>
        </div>
        <button class="secondary" type="submit">Load</button>
      </form>
      <button type="submit" form="invoice-form">Save Invoice</button>
      <button class="secondary" type="button" id="duplicate-invoice">Duplicate</button>
      <button class="danger" type="button" id="delete-invoice">Delete</button>
      <a class="button secondary" href="logout.php">Sign out</a>
    </div>
  </div>

  <div class="message" id="message" hidden></div>
  <div class="error" id="error" hidden></div>

  <form id="invoice-form">
    <input type="hidden" id="source-data" name="source_data" value="">
    <div class="grid">
      <section class="panel">
        <div class="panel-header"><h2>Document</h2></div>
        <div class="fields">
          <div class="field"><label>Title</label><input data-field="document.title"></div>
          <div class="field"><label>Page label</label><input data-field="document.page_label"></div>
          <div class="field"><label>Document number</label><input id="document-number" data-field="document.number" readonly></div>
          <div class="field"><label>Issue date</label><input id="issue-date" data-field="document.issue_date" placeholder="DD/MM/YYYY"></div>
          <div class="field"><label>Credit days</label><input id="credit-days" data-field="document.credit" inputmode="numeric"></div>
          <div class="field"><label>Due date</label><input id="due-date" data-field="document.due_date" readonly></div>
          <div class="field"><label>Reference</label><input data-field="document.reference"></div>
          <div class="field"><label>Project name</label><input data-field="document.project_name"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header"><h2>Seller</h2></div>
        <div class="fields">
          <div class="field full"><label>Seller name</label><input data-field="seller.name"></div>
          <div class="field full"><label>Seller address</label><textarea data-field="seller.address"></textarea></div>
          <div class="field full"><label>Seller tax ID</label><input data-field="seller.tax_id"></div>
          <div class="field full"><label>Logo URL (optional — falls back to seller name)</label><input data-field="seller.logo_url"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header"><h2>Customer</h2></div>
        <div class="fields">
          <div class="field full"><label>Customer name</label><input data-field="customer.name"></div>
          <div class="field full"><label>Customer address</label><textarea data-field="customer.address"></textarea></div>
          <div class="field"><label>Tax ID</label><input data-field="customer.tax_id"></div>
          <div class="field"><label>Phone</label><input data-field="customer.phone"></div>
          <div class="field full"><label>Email</label><input data-field="customer.email"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header"><h2>Contact</h2></div>
        <div class="fields">
          <div class="field"><label>Name</label><input data-field="contact.name"></div>
          <div class="field"><label>Phone</label><input data-field="contact.phone"></div>
          <div class="field full"><label>Email</label><input data-field="contact.email"></div>
          <div class="field full"><label>Line icon URL</label><input data-field="contact.line_icon_url"></div>
          <div class="field full"><label>Line ID</label><input data-field="contact.line_id"></div>
        </div>
      </section>
    </div>

    <section class="panel full">
      <div class="panel-header">
        <h2>Items</h2>
        <button class="secondary" type="button" data-add="items">Add item</button>
      </div>
      <div class="repeat-list" data-list="items"></div>
    </section>

    <section class="panel full">
      <div class="panel-header">
        <h2>Summary &amp; totals</h2>
        <span class="panel-note">Totals are calculated automatically from the items above.</span>
      </div>
      <div class="fields">
        <div class="field"><label>Currency label</label><input data-field="currency" placeholder="e.g. บาท or THB"></div>
        <div class="field">
          <label>Amount in words</label>
          <select data-field="language">
            <option value="th">Thai (บาทถ้วน)</option>
            <option value="en">English (Baht)</option>
          </select>
        </div>
        <div class="field"><label>Withholding tax %</label><input data-field="withholding_rate" inputmode="decimal" placeholder="0"></div>
        <div class="field full"><label>Note</label><input data-field="note"></div>
      </div>
      <div class="fields computed">
        <div class="field"><label>Taxable amount</label><input data-field="summary.taxable_amount" readonly tabindex="-1"></div>
        <div class="field"><label>VAT amount</label><input data-field="summary.vat_amount" readonly tabindex="-1"></div>
        <div class="field"><label>Total amount</label><input data-field="summary.total_amount" readonly tabindex="-1"></div>
        <div class="field"><label>Withholding amount</label><input data-field="summary.withholding" readonly tabindex="-1"></div>
        <div class="field"><label>Amount payable</label><input data-field="summary.payable" readonly tabindex="-1"></div>
        <div class="field full"><label>Total in words</label><input data-field="summary.total_words" readonly tabindex="-1"></div>
      </div>
    </section>

    <section class="panel full">
      <div class="panel-header">
        <h2>Payment Banks</h2>
        <button class="secondary" type="button" data-add="banks">Add bank</button>
      </div>
      <div class="repeat-list" data-list="banks"></div>
    </section>

    <section class="panel full">
      <div class="panel-header">
        <h2>Live preview</h2>
        <span class="panel-note">Reflects unsaved edits. Uses default labels until saved.</span>
      </div>
      <iframe id="preview-frame" class="preview-frame" title="Invoice preview"></iframe>
    </section>

    <div class="sticky-save">
      <a class="button secondary" id="open-invoice" href="index.php" target="_blank">Open invoice</a>
      <button type="submit">Save Invoice</button>
    </div>
  </form>
</div>

<template id="items-template">
  <div class="repeat-row" data-row="items">
    <div class="row-title">
      <strong>Item <span data-index></span></strong>
      <button class="danger" type="button" data-remove>Remove</button>
    </div>
    <div class="item-grid">
      <div><label>Name</label><input data-item-field="name"></div>
      <div><label>Qty</label><input data-item-field="qty"></div>
      <div><label>Price</label><input data-item-field="price"></div>
      <div><label>Discount %</label><input data-item-field="discount"></div>
      <div><label>VAT %</label><input data-item-field="vat"></div>
      <div><label>Pre-tax (auto)</label><input data-item-field="pre_tax" readonly tabindex="-1"></div>
      <div class="item-detail-field"><label>Detail</label><textarea data-item-field="detail"></textarea></div>
    </div>
  </div>
</template>

<template id="banks-template">
  <div class="repeat-row" data-row="banks">
    <div class="row-title">
      <strong>Bank <span data-index></span></strong>
      <button class="danger" type="button" data-remove>Remove</button>
    </div>
    <div class="bank-grid">
      <div>
        <label>Logo class</label>
        <select data-bank-field="logo_class">
          <option value="kbank">kbank</option>
          <option value="scb">scb</option>
          <option value="other">other</option>
        </select>
      </div>
      <div><label>Logo text</label><input data-bank-field="logo_text"></div>
      <div><label>Bank name</label><input data-bank-field="bank_name"></div>
      <div><label>Account number</label><input data-bank-field="account_number"></div>
      <div><label>Account name</label><input data-bank-field="account_name"></div>
    </div>
  </div>
</template>

<script src="js/invoice-calc.js"></script>
<script src="js/invoice-render.js"></script>
<script src="js/admin.js"></script>
</body>
</html>
