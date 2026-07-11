# Thai Tax Invoice & Billing System

A lightweight, self-contained PHP web application designed to manage, generate, and view compliant Thai Tax Invoices and Billing Documents. The system utilizes structured JSON files as a flat-file database, removing the need for a heavy relational database setup like MySQL, making it extremely fast and highly portable.

## 🛠️ Tech Stack
Backend Engine: Pure PHP 8.x (Native File I/O, DateTimeImmutable workflows, JSON encoding/decoding matrices).

Frontend Layer: Semantic HTML5 layouts optimized for clean user entry, utilizing CSS grid/flexbox components.

Client Scripts: Vanilla JS (Zero dependencies) handling real-time sequential DOM modifications, automatic form reindexing, dynamic multi-item row generation, and real-time dates modifications.

## 🚀 Features
Flat-File JSON Architecture: No MySQL or external database required. Invoices are stored as independent, revision-friendly JSON data structures under the invoice/ directory.

Automated Sequential Numbering: Dynamic ID generation (e.g., DL-YYMMDDXXX) that automatically tracks, calculates, and locks the next logical document number based on the current date and previously saved sequential sequences.

Dynamic Due-Date Calculation: Automatically recalculates Due Date instantly based on the selected Issue Date and specified Credit Days (supports live client-side JavaScript updates as well as backend validation).

Comprehensive Data Formatting: Built-in calculation pipelines and clean-string sanitizers handles numeric outputs, credits, and formatting natively mapped out for the Thai market standards.

Dynamic Bank & Item Repeaters: Interactive Admin Panel layout allowing users to dynamically add, remove, and reindex multiple line-items, item descriptions, and banking payout details using native HTML5 templates.

Bi-lingual Translations Ready: Decoupled translation context stored within invoice_data.json parameters allows easy localization adjustments for document labels (Seller, Customer, VAT indicators, Payment instructions).

Secure XSS Escaping out-of-the-box: Implements context-aware HTML entity sanitization (h() encapsulation wrappers) across templates to mitigate security vulnerabilities on untrusted inputs.
