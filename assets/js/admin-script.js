jQuery(document).ready(function ($) {
    let currentPage = 1; // Track the current page
    const perPage = 10; // Number of orders per page
    let totalPages = 1; // Total pages (will update dynamically)
   

    function loadOrders(page) {
        $.post(WooExportData.ajax_url, {
            action: 'fetch_paginated_orders',
            nonce: WooExportData.nonce,
            page,
            per_page: perPage,
        }, function (res) {
            console.log(res);
            const tbody = $("#orders-table-body").empty();
    
            if (res.success) {
                if (res.data.orders.length > 0) {
                    // Populate the table body
                    res.data.orders.forEach(order => {
                        tbody.append(`
                            <tr>
                                <td>${order.id}</td>
                                <td>${order.status}</td>
                                <td>${order.customer_name}</td>
                                <td>${order.customer_email}</td>
                                <td>${order.customer_phone}</td>
                                <td>${order.products}</td>
                                <td>${order.total}</td>
                                <td>${order.date}</td>
                                <td><a href="${order.view_link}" target="_blank" class="button button-small">View Order</a></td>
                            </tr>
                        `);
                    });
                } else {
                    // Handle case when there are no orders
                    tbody.html('<tr><td colspan="9">No orders found.</td></tr>');
                }
    
                // Update pagination controls only if total orders are greater than perPage
                if (res.data.total_pages > 1) {
                    $("#pagination-controls").show();
                    $("#current-page").text(page);
                    $("#total-pages").text(res.data.total_pages);
                    $("#goto-page").attr("max", res.data.total_pages);
                    $("#prev-page").prop("disabled", page <= 1);
                    $("#next-page").prop("disabled", page >= res.data.total_pages);
                } else {
                    $("#pagination-controls").hide();
                }
    
                // Update global page count
                currentPage = page;
                totalPages = res.data.total_pages;
            } else {
                tbody.html('<tr><td colspan="9">No orders found.</td></tr>');
                $("#pagination-controls").hide(); // Hide pagination if no orders found
            }
        }).fail(() => {
            $("#orders-table-body").html('<tr><td colspan="9">Failed to load orders. Please try again.</td></tr>');
            $("#pagination-controls").hide();
        });
    }
    

    // Event Listeners for Pagination
    $("#prev-page").on("click", () => {
        if (currentPage > 1) {
            currentPage--;
            loadOrders(currentPage, $("#search-orders").val());
        }
    });

    $("#next-page").on("click", () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadOrders(currentPage, $("#search-orders").val());
        }
    });

    $("#goto-page-btn").on("click", function () {
        let gotoPage = parseInt($("#goto-page").val());
        if (gotoPage >= 1 && gotoPage <= totalPages) {
            currentPage = gotoPage;
            loadOrders(currentPage, $("#search-orders").val());
        }
    });

    // Initial Load
    loadOrders(currentPage);

    // Export functionality
    // Export functionality with confirmation, disabling buttons, and processing message
    function exportOrders(type) {
        let offset = 0;
        let total = 0;

        // Disable export buttons during the process
        $("#export-orders, #export-pdf, #export-excel").prop("disabled", true);

        // Show processing message
        $("#processing-message").show();

        // Confirm before proceeding with export
        if (!confirm("Are you sure you want to export the orders? This might take a few moments.")) {
            // Enable buttons if user cancels
            $("#export-orders, #export-pdf, #export-excel").prop("disabled", false);
            return;
        }

        $.post(WooExportData.ajax_url, {
            action: 'woocommerce_order_export_total',
            nonce: WooExportData.nonce
        }, function (res) {
            total = res.data.total;
            $("#progress-container").show();

            function processExport() {
                const actionMap = {
                    csv: 'woocommerce_order_export',
                    pdf: 'woocommerce_order_export_pdf',
                    excel: 'woocommerce_order_export_excel'
                };
                const action = actionMap[type];

                $.post(WooExportData.ajax_url, {
                    action,
                    offset,
                    nonce: WooExportData.nonce
                }, function (res) {
                    if (res.success) {
                        offset += res.data.batch_size;
                        const percentage = Math.min(100, Math.round((offset / total) * 100));
                        $("#export-progress span").text(`${percentage}%`);
                        $("#progress-bar").css("width", `${percentage}%`);

                        if (offset < total) {
                            setTimeout(processExport, 500);
                        } else if (res.data.file_url) {
                            // Trigger file download
                            const link = document.createElement('a');
                            link.href = res.data.file_url;
                            link.download = res.data.file_name || ''; // You can set a default filename or use the response data if available
                            link.click(); // Trigger the download

                            // Enable buttons and hide progress message after completion
                            $("#export-orders, #export-pdf, #export-excel").prop("disabled", false);
                            $("#progress-container").hide();
                            $("#processing-message").hide();
                        }
                    }
                });
            }
            processExport();
        });
    }

    $("#export-orders").click(() => exportOrders('csv'));
    $("#export-pdf").click(() => exportOrders('pdf'));
    $("#export-excel").click(() => exportOrders('excel'));
});