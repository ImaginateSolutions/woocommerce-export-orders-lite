<div class="wrap">
    <h1>WooCommerce Order Exporter</h1>
    <button id="export-orders" class="button button-primary">Export CSV</button>
    <button id="export-pdf" class="button button-secondary">Export PDF</button>
    <button id="export-excel" class="button button-primary">Export Excel</button>

    <!-- Processing Message -->
    <div id="processing-message" style="display:none; color: red; font-weight: bold;">
        Please do not refresh the page while processing...
    </div>

    <div id="progress-container" style="display:none;">
        <div id="progress-bar" style="width: 0%; height: 20px; background-color: green;"></div>
    </div>
    <p id="export-progress">Progress: <span>0%</span></p>

    <h2>Order List</h2>
    
    <table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Status</th>
            <th>Customer Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Products</th>
            <th>Total</th>
            <th>Order Date</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody id="orders-table-body">
        <tr>
            <td colspan="9">Loading orders...</td>
        </tr>
    </tbody>
    </table>
    <div id="pagination-controls" class="pagination-wrapper">
        <button id="prev-page" class="button">Prev</button>
        <span id="current-page">1</span> of <span id="total-pages">5</span>
        <input type="number" id="goto-page" value="1" min="1" style="width: 50px;" />
        <button id="goto-page-btn" class="button">Go</button>
        <button id="next-page" class="button">Next</button>
    </div>
</div>
