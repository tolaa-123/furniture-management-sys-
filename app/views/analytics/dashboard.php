        <!-- Fifth Row Charts -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Employee Productivity</h4>
                        <div class="card-options">
                            <select id="employeeProductivityMonths" class="form-select form-select-sm" style="width: auto;">
                                <option value="3">Last 3 Months</option>
                                <option value="6" selected>Last 6 Months</option>
                                <option value="12">Last 12 Months</option>
                            </select>
                            <select id="employeeProductivityLimit" class="form-select form-select-sm ms-2" style="width: auto;">
                                <option value="5">Top 5 Employees</option>
                                <option value="8" selected>Top 8 Employees</option>
                                <option value="12">Top 12 Employees</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="employeeProductivityChart" height="180"></canvas>
                        <div id="employeeProductivityDetails" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    let employeeProductivityChart;
        // Employee Productivity Chart
        const employeeProductivityCtx = document.getElementById('employeeProductivityChart').getContext('2d');
        employeeProductivityChart = new Chart(employeeProductivityCtx, {
            type: 'bar',
            data: <?php echo json_encode($chartData['employeeProductivity']); ?>,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Orders Completed'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Avg Completion Days'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        // Employee productivity filters
        document.getElementById('employeeProductivityMonths').addEventListener('change', function() {
            updateChart('employee_productivity', employeeProductivityChart, {months: this.value, limit: document.getElementById('employeeProductivityLimit').value});
        });
        document.getElementById('employeeProductivityLimit').addEventListener('change', function() {
            updateChart('employee_productivity', employeeProductivityChart, {months: document.getElementById('employeeProductivityMonths').value, limit: this.value});
        });
    // Render employee productivity details table
    function renderEmployeeProductivityDetails(data) {
        if (!data || !data.detailed_data || data.detailed_data.length === 0) {
            document.getElementById('employeeProductivityDetails').innerHTML = '<div class="text-muted">No data available.</div>';
            return;
        }
        let html = '<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>#</th><th>Name</th><th>Orders Completed</th><th>Avg Completion Days</th></tr></thead><tbody>';
        data.detailed_data.forEach((row, idx) => {
            html += `<tr><td>${idx+1}</td><td>${row.employee_name}</td><td>${row.completed_orders}</td><td>${Number(row.avg_completion_days).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table></div>';
        document.getElementById('employeeProductivityDetails').innerHTML = html;
    }
        // Initial render of employee productivity details
        renderEmployeeProductivityDetails(<?php echo json_encode($chartData['employeeProductivity']); ?>);
        if (chartType === 'employee_productivity') {
            renderEmployeeProductivityDetails(data.data);
        }
    <!-- Fourth Row Charts -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Material Usage Trends</h4>
                    <div class="card-options">
                        <select id="materialUsageMonths" class="form-select form-select-sm" style="width: auto;">
                            <option value="6">Last 6 Months</option>
                            <option value="12" selected>Last 12 Months</option>
                            <option value="24">Last 24 Months</option>
                        </select>
                        <select id="materialUsageLimit" class="form-select form-select-sm ms-2" style="width: auto;">
                            <option value="3">Top 3 Materials</option>
                            <option value="6" selected>Top 6 Materials</option>
                            <option value="10">Top 10 Materials</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="materialUsageChart" height="180"></canvas>
                    <div id="materialUsageDetails" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
let materialUsageChart;
    // Material Usage Chart
    const materialUsageCtx = document.getElementById('materialUsageChart').getContext('2d');
    materialUsageChart = new Chart(materialUsageCtx, {
        type: 'line',
        data: <?php echo json_encode($chartData['materialUsage']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity Used'
                    }
                }
            }
        }
    });
    // Material usage filters
    document.getElementById('materialUsageMonths').addEventListener('change', function() {
        updateChart('material_usage', materialUsageChart, {months: this.value, limit: document.getElementById('materialUsageLimit').value});
    });
    document.getElementById('materialUsageLimit').addEventListener('change', function() {
        updateChart('material_usage', materialUsageChart, {months: document.getElementById('materialUsageMonths').value, limit: this.value});
    });
// Render material usage details table
function renderMaterialUsageDetails(data) {
    if (!data || !data.detailed_data || data.detailed_data.length === 0) {
        document.getElementById('materialUsageDetails').innerHTML = '<div class="text-muted">No data available.</div>';
        return;
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Material</th><th>Month</th><th>Quantity Used</th></tr></thead><tbody>';
    data.detailed_data.forEach(row => {
        html += `<tr><td>${row.material_name}</td><td>${row.month}</td><td>${Number(row.total_used).toLocaleString()}</td></tr>`;
    });
    html += '</tbody></table></div>';
    document.getElementById('materialUsageDetails').innerHTML = html;
}
    // Initial render of material usage details
    renderMaterialUsageDetails(<?php echo json_encode($chartData['materialUsage']); ?>);
    if (chartType === 'material_usage') {
        renderMaterialUsageDetails(data.data);
    }
<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Analytics Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item active">Analytics</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="row">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Total Orders</p>
                            <h4 class="mb-0" id="totalOrders"><?php echo number_format($stats['total_orders'] ?? 0); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-cart font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Pending Orders</p>
                            <h4 class="mb-0" id="pendingOrders"><?php echo number_format($stats['pending_orders'] ?? 0); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-warning">
                                <span class="avatar-title">
                                    <i class="bx bx-time font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">This Month Revenue</p>
                            <h4 class="mb-0" id="monthRevenue">ETB <?php echo number_format($stats['this_month_revenue'] ?? 0, 2); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-success">
                                <span class="avatar-title">
                                    <i class="bx bx-money font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Active Employees</p>
                            <h4 class="mb-0" id="activeEmployees"><?php echo number_format($stats['active_employees'] ?? 0); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-info">
                                <span class="avatar-title">
                                    <i class="bx bx-user font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Low Stock Items</p>
                            <h4 class="mb-0" id="lowStock"><?php echo number_format($stats['low_stock_items'] ?? 0); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-danger">
                                <span class="avatar-title">
                                    <i class="bx bx-error font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">This Month Profit</p>
                            <h4 class="mb-0" id="monthProfit">ETB <?php echo number_format($stats['this_month_profit'] ?? 0, 2); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-success">
                                <span class="avatar-title">
                                    <i class="bx bx-trending-up font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Charts Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Monthly Revenue Trend</h4>
                    <div class="card-options">
                        <select id="revenueMonths" class="form-select form-select-sm" style="width: auto;">
                            <option value="6">Last 6 Months</option>
                            <option value="12" selected>Last 12 Months</option>
                            <option value="24">Last 24 Months</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="monthlyRevenueChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Orders by Status</h4>
                </div>
                <div class="card-body">
                    <canvas id="ordersByStatusChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row Charts -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Employee Working Hours</h4>
                    <div class="card-options">
                        <select id="employeeLimit" class="form-select form-select-sm" style="width: auto;">
                            <option value="5">Top 5</option>
                            <option value="8" selected>Top 8</option>
                            <option value="10">Top 10</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="employeeHoursChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Low Stock Alerts</h4>
                </div>
                <div class="card-body">
                    <canvas id="lowStockChart" height="120"></canvas>
                    <div id="lowStockDetails" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Third Row Charts -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Top Selling Products</h4>
                    <div class="card-options">
                        <select id="productLimit" class="form-select form-select-sm" style="width: auto;">
                            <option value="5">Top 5</option>
                            <option value="10" selected>Top 10</option>
                            <option value="15">Top 15</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="topProductsChart" height="150"></canvas>
                    <div id="topProductsDetails" class="mt-3"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Top Customers</h4>
                    <div class="card-options">
                        <select id="customerLimit" class="form-select form-select-sm" style="width: auto;">
                            <option value="5">Top 5</option>
                            <option value="10" selected>Top 10</option>
                            <option value="15">Top 15</option>
                        </select>
                        <select id="customerMonths" class="form-select form-select-sm ms-2" style="width: auto;">
                            <option value="6">Last 6 Months</option>
                            <option value="12" selected>Last 12 Months</option>
                            <option value="24">Last 24 Months</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="topCustomersChart" height="150"></canvas>
                    <div id="topCustomersDetails" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <a href="/analytics/export?type=summary" class="btn btn-outline-primary">
                            <i class="bx bx-download me-1"></i>Export Summary
                        </a>
                        <a href="/analytics/export?type=detailed&data=revenue" class="btn btn-outline-success">
                            <i class="bx bx-bar-chart me-1"></i>Export Revenue Data
                        </a>
                        <a href="/analytics/export?type=detailed&data=products" class="btn btn-outline-info">
                            <i class="bx bx-package me-1"></i>Export Product Data
                        </a>
                        <?php if ($this->isAdmin()): ?>
                        <button type="button" class="btn btn-outline-warning" onclick="refreshCache()">
                            <i class="bx bx-refresh me-1"></i>Refresh Cache
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js and initialization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart instances
let monthlyRevenueChart, ordersByStatusChart, employeeHoursChart;
let lowStockChart, topProductsChart, monthlyProfitChart, topCustomersChart;

// Initialize charts with PHP data
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Revenue Chart
    const revenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
    monthlyRevenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: <?php echo json_encode($chartData['monthlyRevenue']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (ETB)'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Orders Count'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Orders by Status Chart
    const ordersCtx = document.getElementById('ordersByStatusChart').getContext('2d');
    ordersByStatusChart = new Chart(ordersCtx, {
        type: 'pie',
        data: <?php echo json_encode($chartData['ordersByStatus']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Employee Hours Chart
    const employeeCtx = document.getElementById('employeeHoursChart').getContext('2d');
    employeeHoursChart = new Chart(employeeCtx, {
        type: 'bar',
        data: <?php echo json_encode($chartData['employeeHours']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Low Stock Chart
    const lowStockCtx = document.getElementById('lowStockChart').getContext('2d');
    lowStockChart = new Chart(lowStockCtx, {
        type: 'doughnut',
        data: <?php echo json_encode($chartData['lowStockAlerts']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });


    // Top Products Chart
    const productsCtx = document.getElementById('topProductsChart').getContext('2d');
    topProductsChart = new Chart(productsCtx, {
        type: 'bar',
        data: <?php echo json_encode($chartData['topProducts']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (ETB)'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity Sold'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Top Customers Chart
    const customersCtx = document.getElementById('topCustomersChart').getContext('2d');
    topCustomersChart = new Chart(customersCtx, {
        type: 'bar',
        data: <?php echo json_encode($chartData['topCustomers']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (ETB)'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Orders Count'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Monthly Profit Chart
    const profitCtx = document.getElementById('monthlyProfitChart').getContext('2d');
    monthlyProfitChart = new Chart(profitCtx, {
        type: 'line',
        data: <?php echo json_encode($chartData['monthlyProfit']); ?>,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (ETB)'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Profit Margin %'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Setup event listeners for dynamic updates
    setupEventListeners();
    
    // Start real-time updates
    setInterval(updateDashboard, 30000); // Update every 30 seconds
});

function setupEventListeners() {
    // Revenue months filter
    document.getElementById('revenueMonths').addEventListener('change', function() {
        updateChart('monthly_revenue', monthlyRevenueChart, {months: this.value});
    });

    // Employee limit filter
    document.getElementById('employeeLimit').addEventListener('change', function() {
        updateChart('employee_hours', employeeHoursChart, {limit: this.value});
    });

    // Product limit filter
    document.getElementById('productLimit').addEventListener('change', function() {
        updateChart('top_products', topProductsChart, {limit: this.value});
    });

    // Profit months filter
    document.getElementById('profitMonths').addEventListener('change', function() {
        updateChart('monthly_profit', monthlyProfitChart, {months: this.value});
    });

    // Top customers filters
    document.getElementById('customerLimit').addEventListener('change', function() {
        updateChart('top_customers', topCustomersChart, {limit: this.value, months: document.getElementById('customerMonths').value});
    });
    document.getElementById('customerMonths').addEventListener('change', function() {
        updateChart('top_customers', topCustomersChart, {limit: document.getElementById('customerLimit').value, months: this.value});
    });
}
// Render top customers details table
function renderTopCustomersDetails(data) {
    if (!data || !data.detailed_data || data.detailed_data.length === 0) {
        document.getElementById('topCustomersDetails').innerHTML = '<div class="text-muted">No data available.</div>';
        return;
    }
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Revenue (ETB)</th></tr></thead><tbody>';
    data.detailed_data.forEach((row, idx) => {
        html += `<tr><td>${idx+1}</td><td>${row.customer_name}</td><td>${row.email}</td><td>${row.phone}</td><td>${row.orders_count}</td><td>${Number(row.total_revenue).toLocaleString()}</td></tr>`;
    });
    html += '</tbody></table></div>';
    document.getElementById('topCustomersDetails').innerHTML = html;
}

function updateChart(chartType, chartInstance, params = {}) {
    fetch(`/analytics/get-chart-data?chart=${chartType}&${new URLSearchParams(params)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                chartInstance.data = data.data;
                chartInstance.update();
                if (chartType === 'top_customers') {
                    renderTopCustomersDetails(data.data);
                }
            }
        })
        .catch(error => console.error('Error updating chart:', error));
}
    // Initial render of top customers details
    renderTopCustomersDetails(<?php echo json_encode($chartData['topCustomers']); ?>);

function updateDashboard() {
    fetch('/analytics/get-updates')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update stats
                document.getElementById('totalOrders').textContent = data.stats.total_orders?.toLocaleString() || '0';
                document.getElementById('pendingOrders').textContent = data.stats.pending_orders?.toLocaleString() || '0';
                document.getElementById('monthRevenue').textContent = 'ETB ' + (data.stats.this_month_revenue?.toLocaleString() || '0.00');
                document.getElementById('activeEmployees').textContent = data.stats.active_employees?.toLocaleString() || '0';
                document.getElementById('lowStock').textContent = data.stats.low_stock_items?.toLocaleString() || '0';
                document.getElementById('monthProfit').textContent = 'ETB ' + (data.stats.this_month_profit?.toLocaleString() || '0.00');
            }
        })
        .catch(error => console.error('Error updating dashboard:', error));
}

function refreshCache() {
    if (confirm('Are you sure you want to refresh the analytics cache?')) {
        fetch('/analytics/refresh-cache', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error refreshing cache:', error));
    }
}
</script>

<?php require_once APP_DIR . '/includes/footer.php'; ?>