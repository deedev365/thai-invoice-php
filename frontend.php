<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $title; ?></title>
<link rel="stylesheet" href="tax-invoice/css/index.css">
</head>
<body>

<div class="page-wrapper">
  <div class="page">
    <div class="top-bar">
      <div class="logo-area">
        <div class="logo-fallback">THE YAMA<br>ENTERPRISE</div>
      </div>
      <div class="doc-title-area">
        <div class="page-label"><?= h($invoice['document']['page_label']) ?></div>
        <div class="doc-title"><?= h($invoice['document']['title']) ?></div>
      </div>
    </div>

    <div class="info-section">
      <div class="seller-block">
        <div class="info-row">
          <span class="info-label"><?= h($invoice['translate']['seller']) ?> :</span>
          <span class="info-value"><?= h($invoice['seller']['name']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><?= h($invoice['translate']['address']) ?> :</span>
          <span class="info-value"><?= nl2br(h($invoice['seller']['address'])) ?></span>
        </div>
        <div class="info-row tax-id-row">
          <span class="info-label"><?= h($invoice['translate']['tax_id']) ?> :</span>
          <span class="info-value"><?= h($invoice['seller']['tax_id']) ?></span>
        </div>
      </div>

      <div class="doc-meta-block">
        <table class="meta-table">
          <tr><td class="meta-label"><?= h($invoice['translate']['number']) ?> :</td><td class="meta-val doc-num"><?= h($invoice['document']['number']) ?></td></tr>
          <tr><td class="meta-label"><?= h($invoice['translate']['issue_date']) ?> :</td><td class="meta-val"><?= h($invoice['document']['issue_date']) ?></td></tr>
          <tr><td class="meta-label"><?= h($invoice['translate']['credit']) ?> :</td><td class="meta-val"><?= h($invoice['document']['credit']) ?></td></tr>
          <tr><td class="meta-label"><?= h($invoice['translate']['due_date']) ?> :</td><td class="meta-val"><?= h($invoice['document']['due_date']) ?></td></tr>
          <tr><td class="meta-label"><?= h($invoice['translate']['reference']) ?> :</td><td class="meta-val"><?= h($invoice['document']['reference']) ?></td></tr>
          <tr><td class="meta-label"><?= h($invoice['translate']['project_name']) ?> :</td><td class="meta-val"><?= h($invoice['document']['project_name']) ?></td></tr>
        </table>
      </div>
    </div>

    <div class="customer-contact-section">
      <div class="customer-block">
        <div class="info-row"><span class="info-label"><?= h($invoice['translate']['customer_name']) ?> :</span><span class="info-value"><?= h($invoice['customer']['name']) ?></span></div>
        <div class="info-row"><span class="info-label"><?= h($invoice['translate']['customer_address']) ?> :</span><span class="info-value"><?= nl2br(h($invoice['customer']['address'])) ?></span></div>
        <div class="info-row"><span class="info-label"><?= h($invoice['translate']['customer_tax_id']) ?> :</span><span class="info-value"><?= h($invoice['customer']['tax_id']) ?></span></div>
        <div class="info-row"><span class="info-label">Phone :</span><span class="info-value"><?= h($invoice['customer']['phone']) ?></span></div>
        <div class="info-row"><span class="info-label">Email :</span><span class="info-value"><?= h($invoice['customer']['email']) ?></span></div>
      </div>

      <div class="contact-block">
        <div class="contact-title"><?= h($invoice['translate']['contancts']) ?> :</div>
        <div class="contact-row"><span class="contact-icon">👤</span><span><?= h($invoice['contact']['name']) ?></span></div>
        <div class="contact-row"><span class="contact-icon">📞</span><span><?= h($invoice['contact']['phone']) ?></span></div>
        <div class="contact-row"><span class="contact-icon">✉️</span><span><?= h($invoice['contact']['email']) ?></span></div>
        <div class="contact-row">
          <span class="contact-icon">
            <?php if (!empty($invoice['contact']['line_icon_url'])): ?>
              <img src="<?= h($invoice['contact']['line_icon_url']) ?>" alt="">
            <?php endif; ?>
          </span>
          <span><?= h($invoice['contact']['line_id']) ?></span>
        </div>
      </div>
    </div>

    <table class="items-table">
      <thead>
        <tr>
            <th style="width:50%"><?= h($invoice['translate']['items_description']) ?></th>
            <th class="center" style="width:7%"><?= h($invoice['translate']['items_quantity']) ?></th>
            <th class="right" style="width:12%"><?= h($invoice['translate']['items_price']) ?></th>
            <th class="right" style="width:8%"><?= h($invoice['translate']['items_discount']) ?></th>
            <th class="center" style="width:5%"><?= h($invoice['translate']['items_vat']) ?></th>
            <th class="right" style="width:13%"><?= h($invoice['translate']['items_amount_before_tax']) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoice['items'] as $index => $item): ?>
          <tr>
            <td>
              <span class="item-name"><?= ($index + 1) ?>. <?= h($item['name']) ?></span>
              <div class="item-detail"><?= lines_html($item['detail']) ?></div>
            </td>
            <td class="center"><?= h($item['qty']) ?></td>
            <td class="right"><?= h($item['price']) ?></td>
            <td class="right"><?= h($item['discount']) ?></td>
            <td class="center"><?= h($item['vat']) ?></td>
            <td class="right"><?= h($item['pre_tax']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="summary-section">
        <div class="summary-left">
            <div class="summary-title">
                🧾 <?= h($invoice['translate']['summary']) ?>
            </div>
            <div class="summary-line">
                <span class="s-label"><?= h($invoice['translate']['taxable_amount_7']) ?></span>
                <span class="s-val"><?= h($invoice['summary']['taxable_amount']) ?></span>
            </div>
            <div class="summary-line">
                <span class="s-label"><?= h($invoice['translate']['vat_amount_7']) ?></span>
                <span class="s-val"><?= h($invoice['summary']['vat_amount']) ?></span>
            </div>
            <div class="summary-line">
                <span class="s-label"><?= h($invoice['translate']['total_amount']) ?></span>
                <span class="s-val"><?= h($invoice['summary']['total_words']) ?></span>
            </div>
        </div>

        <div class="summary-right">
            <div class="total-box">
                <div class="total-label"><?= h($invoice['translate']['total_amount']) ?></div>
                <div class="total-amount"><?= h($invoice['summary']['total_amount']) ?></div>
            </div>
            <div class="deduct-lines">
                <div class="deduct-line">
                    <span><?= h($invoice['translate']['withholding_tax']) ?></span>
                    <span><?= h($invoice['summary']['withholding']) ?></span>
                </div>
                <div class="deduct-line">
                    <span><?= h($invoice['translate']['amount_payable']) ?></span>
                    <span><strong><?= h($invoice['summary']['payable']) ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <div class="payment-section">
      <div class="payment-title">💳 <?= h($invoice['translate']['payment']) ?></div>
      <div class="payment-banks">
        <?php foreach ($invoice['banks'] as $bank): ?>
          <div class="bank-card">
            <div class="bank-logo <?= h($bank['logo_class']) ?>"><?= h($bank['logo_text']) ?></div>
            <div class="bank-info">
              <div class="bank-name"><?= h($bank['bank_name']) ?></div>
              <div class="acc-num"><?= h($bank['account_number']) ?></div>
              <div class="acc-name"><?= h($bank['account_name']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="note-footer">📝 <?= h($invoice['note']) ?></div>
  </div>
</div>

</body>
</html>
