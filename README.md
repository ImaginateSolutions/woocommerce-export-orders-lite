# Export Orders for WooCommerce

Export WooCommerce orders to CSV, Excel, PDF, XML, or JSON with status filters, customer details, product items, and draggable column ordering.

## Description

Export Orders for WooCommerce is a WooCommerce order export plugin for store owners, administrators, and managers who need quick access to order reports, customer details, billing and shipping data, and product line item information.

Use it to export WooCommerce orders to CSV, Excel (XLSX), PDF, XML, or JSON. Filter orders by status and date range, choose the exact export fields, and drag columns into the same order you want in the final export file.

## Features

- Export WooCommerce orders to CSV for spreadsheets, accounting, reporting, and order archives.
- Export WooCommerce orders to Excel (XLSX) with selected order columns and customer details.
- Export WooCommerce orders to PDF for printable order reports and shareable summaries.
- Export WooCommerce orders to XML or JSON for structured data workflows and integrations.
- Filter order exports by order status and order date range.
- Export customer details, billing details, shipping details, order totals, payment details, coupon codes, and product line items.
- Select or deselect individual WooCommerce export columns.
- Drag export columns into a custom sequence so the exported file matches the admin-selected column order.
- Export product names, SKUs, quantities, and line totals from WooCommerce orders.
- Export button appears beside the WooCommerce Add order button.
- Review selected filters, format, and columns before starting the export.
- Batch export processing with progress feedback.
- Compatible with WooCommerce HPOS / custom order tables.
- PDF support includes a reduced font set with Japanese/CJK fallback coverage.

## Common Use Cases

- Create WooCommerce order reports for accounting or bookkeeping.
- Export WooCommerce orders by date range for monthly, weekly, or custom reports.
- Export completed, processing, pending, refunded, or other WooCommerce order statuses.
- Export customer email, phone, billing address, and shipping address details.
- Export WooCommerce order items, product SKUs, quantities, and product totals.
- Generate CSV or Excel files for analysis in spreadsheet software.
- Generate PDF order reports for internal review or sharing.
- Use XML or JSON order exports for structured data handling.

## Export Fields

- Order information: Order ID, status, date, totals, discount, tax, shipping total, payment method, transaction ID, customer note, and coupon codes.
- Customer and billing information: Customer ID, email, phone, billing name, company, address, city, state, postcode, and country.
- Shipping information: Shipping name, company, address, city, state, postcode, country, and shipping method.
- Product information: Product names, SKUs, quantities, and line totals.

## Notes

The PDF export uses a reduced font bundle with DejaVu Sans Condensed plus Sun-ExtA/Sun-ExtB fallback fonts for Japanese/CJK output. Stores that require broader multilingual PDF coverage may need to add more mPDF fonts and update the mPDF font configuration.

## Changelog

### 2.0.0

- Added CSV, XLSX, PDF, XML, and JSON export format options.
- Added selectable export columns grouped by Order Info, Customer, Shipping, and Products.
- Added draggable export column ordering so exported files match the admin-selected sequence.
- Moved the Export Orders button beside the WooCommerce Add order button.
- Added batch export processing with progress feedback.
- Added WooCommerce HPOS / custom order table compatibility.
- Optimized bundled PDF fonts to reduce plugin package size while keeping PDF export support.
- Updated admin UI styling and export confirmation flow.
