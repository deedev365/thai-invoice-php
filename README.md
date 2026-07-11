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

## 📖 How It Works

### 1. Creating/Editing Documents (admin.php)
The admin panel lets you pick an existing document from a dropdown menu populated chronologically by file modification time (filemtime). Selecting "New invoice" automatically fetches today's Thai time-zone date (Asia/Bangkok), checks the highest existing index code for that day, and buffers the form with safe template parameters.

### 2. Form Saving & File Commit Workflow
Upon hitting Save Invoice, the system processes the raw input payloads (POST), sanitizes strings via clean_string(), performs array normalization on itemized data, locks the filesystem to prevent race conditions (LOCK_EX), writes the fresh JSON file, and forces a safe PRG redirection (Post-Redirect-Get) back into the dashboard.

### 3. Rendering Invoices (index.php & frontend.php)
Calling index.php?data=invoice/invoice-YYMMDD01.json handles parsing the persistent file-layer safely. The controller validates path structures to prevent directory traversal exploits, maps local variable constraints, executes inline line-break conversions (lines_html), and builds a modern, pixel-perfect layout ideal for hard-copy printing or modern PDF exporting directly through web browser engines.# Find Nearest Taxi (PHP)

## 🔗 Demo Links

- [📜 FrontEnd](http://invoice.utmlink.tech)
- [📜 Admin](http://invoice-admin.utmlink.tech)

## 💻 Interface Screenshots

Here is what the invoice generation system looks like in action:

![Invoice Frontend Page](http://utmlink.tech/screenshot/invoice_frontend.png)
![Invoice Main Page](http://utmlink.tech/screenshot/invoice_admin.png)
