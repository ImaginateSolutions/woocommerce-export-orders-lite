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

## WordPress Abilities API

On WordPress 6.9 and later, the plugin registers public `eowc/export-orders` and `eowc/ai-export-orders` abilities. They let authorized automation and AI clients discover and call order exports through the Abilities API, including date range, status, format, and column filters. Relative requests such as `last month`, `last four months`, and `last 4 months` are supported and use complete calendar months before the current month.

Both abilities require the `manage_options` capability and process exports in batches of 100 orders. Clients should repeat the call with the returned `next_offset` and the same `export_id` until `done` is `true`. The AI Export Assistant accepts a request such as `Export completed orders from 2026-01-01 to 2026-01-31 as CSV with order ID, customer email, and total.`

The plugin also exposes these agent-facing abilities:

- `export-orders/get-fields` returns available field keys, labels, and value types.
- `export-orders/get-formats` returns enabled formats, extensions, and batch limits.
- `export-orders/query` returns a total match count and up to 20 sample rows without creating a file.
- `export-orders/generate-export` creates a validated export job and returns progress plus a secure download URL when complete.
- `export-orders/get-export-status` returns the stored status for the current user’s export job.

These abilities require `manage_options`. Small jobs complete immediately; jobs over 100 matching records are queued through WP-Cron and reported as `queued` or `processing`. Generated files and job state expire according to the `eowc_export_retention_seconds` filter, which defaults to one day.

## MCP Server

### Connect ChatGPT with OAuth

The HTTP MCP endpoint now supports a WordPress sign-in and consent flow for ChatGPT,
using authorization code + S256 PKCE, one-hour access tokens and rotating refresh
tokens. No WordPress password or application password is entered in ChatGPT.
See [CHATGPT-SETUP.md](CHATGPT-SETUP.md) for deployment, connection and troubleshooting.

### Local STDIO clients

The plugin provides its own MCP STDIO server and does not require the MCP Adapter plugin. Start it with:

```bash
wp --path=/var/www/wp-woo-large eowc-mcp serve --user=admin
```

Configure an MCP client to run `wp` with these arguments:

```json
{
	"type": "stdio",
	"command": "wp",
	"args": [
		"--path=/var/www/wp-woo-large",
		"eowc-mcp",
		"serve",
		"--user=admin"
	]
}
```

The server exposes `eowc-get-fields`, `eowc-get-formats`, `eowc-query`, `eowc-generate-export`, `eowc-get-export-status`, `eowc-export-orders`, and `eowc-ai-export-orders`. It uses the selected WordPress user’s `manage_options` capability for authorization.

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
