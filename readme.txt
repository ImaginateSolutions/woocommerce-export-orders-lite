=== Export Orders for WooCommerce ===
Contributors: ImagiSol, dhruvin
Donate link: https://paypal.me/DhruvinS?_ga=1.134259249.1705473128.1507170477
Tags: woocommerce, export orders, order export, csv export, excel export, pdf export
Requires at least: 3.0.1
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Export WooCommerce orders to CSV, Excel, PDF, XML, or JSON with status filters, customer details, product items, and draggable column ordering.

== Description ==

**Export Orders for WooCommerce** is a WooCommerce order export plugin for store owners, administrators, and managers who need quick access to order reports, customer details, billing and shipping data, and product line item information.

Use this WooCommerce export orders tool to export orders to **CSV**, **Excel (XLSX)**, **PDF**, **XML**, or **JSON**. You can filter WooCommerce orders by status and date range, choose the exact export fields, and drag columns into the same order you want in the final export file.

Whether you need a WooCommerce order CSV export for accounting, an Excel order report for analysis, a PDF order report for sharing, or a JSON/XML export for integrations, this plugin keeps the process simple inside the WordPress admin.

**Key Features:**

- **Export WooCommerce orders to CSV** for spreadsheets, accounting, reporting, and order archives.
- **Export WooCommerce orders to Excel (XLSX)** with selected order columns and customer details.
- **Export WooCommerce orders to PDF** for printable order reports and shareable summaries.
- **Export WooCommerce orders to XML or JSON** for structured data workflows and integrations.
- Filter order exports by **order status** and **order date range**.
- Export customer details, billing details, shipping details, order totals, payment details, coupon codes, and product line items.
- Select or deselect individual WooCommerce export columns.
- Drag export columns into a custom sequence so the exported file matches the admin-selected column order.
- Export product names, SKUs, quantities, and line totals from WooCommerce orders.
- Export button appears beside the WooCommerce **Add order** button on the orders screen.
- Review selected filters, format, and columns before starting the export.
- Batch export processing with progress feedback for smoother order exports.
- Compatible with WooCommerce HPOS / custom order tables.
- Bundled PDF support includes a reduced font set with Japanese/CJK fallback coverage.

**Common Use Cases:**

- Create WooCommerce order reports for accounting or bookkeeping.
- Export WooCommerce orders by date range for monthly, weekly, or custom reports.
- Export completed, processing, pending, refunded, or other WooCommerce order statuses.
- Export customer email, phone, billing address, and shipping address details.
- Export WooCommerce order items, product SKUs, quantities, and product totals.
- Generate CSV or Excel files for analysis in spreadsheet software.
- Generate PDF order reports for internal review or sharing.
- Use XML or JSON order exports for structured data handling.

**Included export fields:**

- Order information: Order ID, status, date, totals, discount, tax, shipping total, payment method, transaction ID, customer note, and coupon codes.
- Customer and billing information: Customer ID, email, phone, billing name, company, address, city, state, postcode, and country.
- Shipping information: Shipping name, company, address, city, state, postcode, country, and shipping method.
- Product information: Product names, SKUs, quantities, and line totals.

For support, suggestions, or customizations, reach out at [info@imaginate-solutions.com](mailto:info@imaginate-solutions.com).

Some of the other **Pro Plugins**

* [File Uploads Addon for WooCommerce](https://imaginate-solutions.com/downloads/woocommerce-addon-uploads/) - Convert your WooCommerce store into an online Print on Demand (POD) store by allowing users to upload files when adding products to cart.

* [Custom Shipping Methods for WooCommerce](https://imaginate-solutions.com/downloads/custom-shipping-methods-for-woocommerce/?utm_source=wporg&utm_medium=weo&utm_campaign=readme/) - Create custom shipping methods for your WooCommerce store and manage dynamic shipping with ease.

* [Custom Payment Gateways for WooCommerce](https://imaginate-solutions.com/downloads/custom-payment-gateways-for-woocommerce/?utm_source=wporg&utm_medium=weo&utm_campaign=readme/) - Create custom payment gateways for your WooCommerce store to add more payment options for the user to choose from.

* [Payment Gateways by User Roles for WooCommerce](https://imaginate-solutions.com/downloads/payment-gateways-by-user-roles-for-woocommerce/?utm_source=wporg&utm_medium=weo&utm_campaign=readme/) - Allow payment gateways to be available or not available for only particular user roles for your WooCommerce store.

* **[Variations Radio Buttons for WooCommerce](https://imaginate-solutions.com/downloads/variations-radio-buttons-for-woocommerce/?utm_source=wporg&utm_medium=weo&utm_campaign=readme/)** - Convert your WooCommerce variations dropdown to a radio button selection.

== Installation ==

= Automatic Installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you do not need to leave your web browser. To do an automatic install of Export Orders for WooCommerce, log in to your WordPress dashboard, navigate to the Plugins menu, and click Add New.

In the search field type "Export Orders for WooCommerce" and click Search Plugins. Once you have found the plugin, click Install Now and then activate it.

= Manual installation =

The manual installation method involves downloading the plugin and uploading it to your webserver via your preferred FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Screenshots ==

1. Export Orders button beside the WooCommerce Add order button.
2. Export settings modal with status/date filters, format selection, and draggable export columns.
3. Confirmation screen showing selected filters and selected columns before export.

== Changelog ==

= 2.0.0 =
* Added CSV, XLSX, PDF, XML, and JSON export format options.
* Added selectable export columns grouped by Order Info, Customer, Shipping, and Products.
* Added draggable export column ordering so exported files match the admin-selected sequence.
* Moved the Export Orders button beside the WooCommerce Add order button.
* Added batch export processing with progress feedback.
* Added WooCommerce HPOS / custom order table compatibility.
* Optimized bundled PDF fonts to reduce plugin package size while keeping PDF export support.
* Updated admin UI styling and export confirmation flow.

= 1.2.0 =
* Performance improvements.
* Compatibility with WooCommerce HPOS.
* Compatibility with latest WooCommerce versions.
* Compatibility with PHP 8.2.

= 1.1.0 =
* Added Customer Email and Phone to be exported with the other information.
* Performance improvement in getting the order details. Now the plugin uses wc_get_orders instead of custom queries.

= 1.0 =
* Bug fixes related to new WooCommerce functions.
* UI changed to new semantic look.
* Removed the usage of flash for exporting data.

= 0.2 =
* Minor bug fixes.

= 0.1 =
* Initial launch version.

== Frequently Asked Questions ==

= Does this plugin export WooCommerce orders to CSV? =

Yes. You can export WooCommerce orders to CSV with selected order, customer, billing, shipping, and product columns.

= Can I export WooCommerce orders to Excel? =

Yes. The plugin supports Excel exports in XLSX format.

= Can I export WooCommerce orders to PDF? =

Yes. The plugin supports PDF order exports using the included mPDF library and a reduced DejaVu Sans Condensed font set.

= Does it support XML and JSON order exports? =

Yes. You can export WooCommerce order data in XML or JSON format for structured data workflows.

= Can I export customer details from WooCommerce orders? =

Yes. You can export customer ID, customer email, customer phone, billing name, billing address, shipping name, shipping address, and related order details.

= Can I export WooCommerce product items and SKUs? =

Yes. Product names, product SKUs, quantities, and line totals are available in the Products column group.

= Can I filter WooCommerce order exports by status and date? =

Yes. You can filter exports by WooCommerce order status and by a date-from/date-to range.

= Can I control the order of columns in the export file? =

Yes. In the Export Columns section, drag the selected fields into the sequence you want. The exported file uses the same column sequence.

= Is this plugin compatible with WooCommerce HPOS? =

Yes. The plugin declares compatibility with WooCommerce HPOS / custom order tables.

= Does it work without WooCommerce? =

No, this plugin requires WooCommerce to be installed and active.

= Does PDF export support Japanese characters? =

Yes. The plugin includes DejaVu Sans Condensed plus Sun-ExtA/Sun-ExtB fallback fonts for Japanese/CJK PDF output while avoiding the full default mPDF font bundle.

== Upgrade Notice ==

Backup your store before upgrading the plugin.
