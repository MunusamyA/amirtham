AMIRTHAM - DETAILED REPORTS / SINGLE API
========================================

Structure
---------
sales-report.php
purchase-report.php
stock-detail-report.php
customer-outstanding-report.php
supplier-outstanding-report.php
expense-report.php
cash-bank-report.php
gst-sales-report.php
gst-purchase-report.php
non-gst-sales-report.php
non-gst-purchase-report.php
tax-expense-report.php

api/
  reports.php              <-- single API for every report above

sql/
  reports-menu.sql

No separate report include files.
No separate report CSS.
No separate report JS.
Every report page uses the existing project assets:
  assets/css/core.css
  assets/css/components.css
  assets/css/theme.css
  assets/js/app.js
  assets/js/datatable.js
  assets/js/global-select.js

IMPORTANT
---------
1. Copy the reports folder into the AMIRTHAM project root.
2. Copy api/reports.php into the existing api folder.
3. Run sql/reports-menu.sql once.
4. Assign report permissions from the existing Role Permissions page.
5. The Expense reports expect the Expense Payment migration already supplied earlier.
6. Supplier opening-balance allocation detail is used when the Supplier Payment migration is installed.

Tax behavior
------------
GST Sales / GST Purchase are fixed to tax_mode=1.
Non-GST Sales / Non-GST Purchase are fixed to tax_mode=0.
GST / Non-GST Expense starts in GST mode and Ctrl + Shift + U switches GST <-> NON GST.

Report behavior
---------------
Reports are detailed transaction reports, not summary-only pages.
KPI cards are only supplementary totals; the complete transaction/item data is shown in DataTables.
Copy / CSV / Excel / PDF / Print use the existing numeric permission actions.

Existing completed reports remain separate and unchanged:
  stock-movement.php + api/stock-movement.php
  daily-ledger.php + api/daily-ledger.php
  profit-loss.php + api/profit-loss.php
