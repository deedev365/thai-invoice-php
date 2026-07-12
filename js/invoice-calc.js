// invoice-calc.js — client mirror of the PHP calculation engine in lib.php.
// Used by the admin editor for live totals/preview before saving. The server
// recomputes on every save/get, so these numbers are only a preview and any
// drift self-heals the moment the invoice is saved.
(function () {
  'use strict';

  function roundMoney(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
  }

  // Parse a loose numeric input ("15,000", "10%", "1 200.50") into a number.
  function parseNumber(value) {
    var match = String(value == null ? '' : value).replace(/,/g, '').match(/-?\d+(\.\d+)?/);
    return match ? parseFloat(match[0]) : 0;
  }

  // Plain money amount ("9,600.00"), matching PHP number_format($n, 2).
  function formatAmount(value) {
    var num = roundMoney(value);
    var negative = num < 0;
    var parts = Math.abs(num).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return (negative ? '-' : '') + parts.join('.');
  }

  function formatMoney(value, currency) {
    currency = (currency == null ? '' : String(currency)).trim();
    return currency === '' ? formatAmount(value) : formatAmount(value) + ' ' + currency;
  }

  // ---- Thai baht text -----------------------------------------------------

  var THAI_DIGITS = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
  var THAI_PLACES = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

  function thaiReadInteger(number) {
    var n = parseInt(number, 10);
    if (!n || n < 0) {
      return '';
    }
    number = String(n);
    var length = number.length;

    if (length > 6) {
      var head = number.slice(0, length - 6);
      var tail = number.slice(length - 6);
      var tailWords = tail.replace(/^0+/, '') === '' ? '' : thaiReadInteger(tail);
      return thaiReadInteger(head) + 'ล้าน' + tailWords;
    }

    var words = '';
    for (var i = 0; i < length; i++) {
      var digit = parseInt(number[i], 10);
      var place = length - i - 1;
      if (digit === 0) {
        continue;
      }
      if (place === 0) {
        words += (digit === 1 && length > 1) ? 'เอ็ด' : THAI_DIGITS[digit];
      } else if (place === 1) {
        words += digit === 1 ? 'สิบ' : (digit === 2 ? 'ยี่สิบ' : THAI_DIGITS[digit] + 'สิบ');
      } else {
        words += THAI_DIGITS[digit] + THAI_PLACES[place];
      }
    }
    return words;
  }

  function thaiBahtText(amount) {
    var fixed = roundMoney(amount).toFixed(2).split('.');
    var baht = parseInt(fixed[0], 10);
    var satang = parseInt(fixed[1], 10);

    if (baht === 0 && satang === 0) {
      return 'ศูนย์บาทถ้วน';
    }

    var text = '';
    if (baht > 0) {
      text += thaiReadInteger(baht) + 'บาท';
    }
    text += satang > 0 ? thaiReadInteger(satang) + 'สตางค์' : 'ถ้วน';
    return text;
  }

  // ---- English baht text --------------------------------------------------

  var EN_ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
    'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
    'seventeen', 'eighteen', 'nineteen'];
  var EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
  var EN_SCALES = ['', 'thousand', 'million', 'billion', 'trillion'];

  function englishReadInteger(num) {
    num = Math.trunc(Math.abs(Number(num))) || 0;
    if (num === 0) {
      return 'zero';
    }

    var chunks = [];
    while (num > 0) {
      chunks.push(num % 1000);
      num = Math.floor(num / 1000);
    }

    var parts = [];
    for (var i = chunks.length - 1; i >= 0; i--) {
      var chunk = chunks[i];
      if (chunk === 0) {
        continue;
      }
      var words = '';
      var hundreds = Math.floor(chunk / 100);
      var rest = chunk % 100;
      if (hundreds > 0) {
        words += EN_ONES[hundreds] + ' hundred';
      }
      if (rest > 0) {
        if (words !== '') {
          words += ' ';
        }
        if (rest < 20) {
          words += EN_ONES[rest];
        } else {
          words += EN_TENS[Math.floor(rest / 10)];
          if (rest % 10 > 0) {
            words += '-' + EN_ONES[rest % 10];
          }
        }
      }
      parts.push(EN_SCALES[i] !== '' ? words + ' ' + EN_SCALES[i] : words);
    }
    return parts.join(' ');
  }

  // Capitalize each word (mirrors PHP ucwords($s, " -")).
  function titleCase(text) {
    return text.replace(/(^|[ -])([a-z])/g, function (m, sep, ch) {
      return sep + ch.toUpperCase();
    });
  }

  function englishBahtText(amount) {
    var fixed = roundMoney(amount).toFixed(2).split('.');
    var baht = parseInt(fixed[0], 10);
    var satang = parseInt(fixed[1], 10);

    var text = titleCase(englishReadInteger(baht)) + ' Baht';
    if (satang > 0) {
      text += ' and ' + titleCase(englishReadInteger(satang)) + ' Satang';
    } else {
      text += ' Only';
    }
    return text;
  }

  function amountInWords(amount, language) {
    return language === 'en' ? englishBahtText(amount) : thaiBahtText(amount);
  }

  function emptySummary() {
    return {
      taxable_amount: '', vat_amount: '', total_words: '',
      total_amount: '', withholding: '', payable: ''
    };
  }

  // Derive line totals + summary from raw item inputs. Returns { items, summary }.
  function computeTotals(invoice) {
    var items = (invoice.items || []).map(function (item) {
      var copy = {};
      for (var k in item) { if (Object.prototype.hasOwnProperty.call(item, k)) { copy[k] = item[k]; } }
      return copy;
    });
    var currency = (invoice.currency || '').trim();
    var language = invoice.language === 'en' ? 'en' : 'th';
    var withholdingRate = parseNumber(invoice.withholding_rate != null ? invoice.withholding_rate : '0');

    if (items.length === 0) {
      // No line items: preserve any manually-entered summary (legacy invoices)
      // rather than blanking it. Items always drive the calculation otherwise.
      var kept = (invoice.summary && typeof invoice.summary === 'object')
        ? Object.assign(emptySummary(), invoice.summary)
        : emptySummary();
      return { items: items, summary: kept };
    }

    var taxable = 0;
    var vatAmount = 0;

    items.forEach(function (item) {
      var lineNet = roundMoney(parseNumber(item.qty) * parseNumber(item.price) * (1 - parseNumber(item.discount) / 100));
      item.pre_tax = formatAmount(lineNet);
      taxable += lineNet;
      vatAmount += lineNet * parseNumber(item.vat) / 100;
    });

    taxable = roundMoney(taxable);
    vatAmount = roundMoney(vatAmount);
    var total = roundMoney(taxable + vatAmount);
    var withholding = roundMoney(taxable * withholdingRate / 100);
    var payable = roundMoney(total - withholding);

    return {
      items: items,
      summary: {
        taxable_amount: formatMoney(taxable, currency),
        vat_amount: formatMoney(vatAmount, currency),
        total_words: amountInWords(total, language),
        total_amount: formatMoney(total, currency),
        withholding: formatMoney(withholding, currency),
        payable: formatMoney(payable, currency)
      }
    };
  }

  window.InvoiceCalc = {
    parseNumber: parseNumber,
    formatAmount: formatAmount,
    formatMoney: formatMoney,
    thaiBahtText: thaiBahtText,
    englishBahtText: englishBahtText,
    amountInWords: amountInWords,
    computeTotals: computeTotals
  };
})();
