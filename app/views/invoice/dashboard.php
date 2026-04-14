<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Invoice Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item active">Invoices</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Total Invoices</p>
                            <h4 class="mb-0"><?php echo number_format($stats['total_invoices'] ?? 0); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-file font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Total Revenue</p>
                            <h4 class="mb-0">ETB <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h4>
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

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Outstanding</p>
                            <h4 class="mb-0">ETB <?php echo number_format($stats['total_outstanding'] ?? 0, 2); ?></h4>
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

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Paid Invoices</p>
                            <h4 class="mb-0"><?php echo number_format($stats['paid_count'] ?? 0); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-info">
                                <span class="avatar-title">
                                    <i class="bx bx-check-circle font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Recent Invoices</h4>
                    <div class="card-options">
                        <form method="GET" class="d-inline">
                            <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo ($filterStatus === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="sent" <?php echo ($filterStatus === 'sent') ? 'selected' : ''; ?>>Sent</option>
                                <option value="paid" <?php echo ($filterStatus === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                <option value="overdue" <?php echo ($filterStatus === 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </form>
                        <a href="/invoice/configuration" class="btn btn-sm btn-primary">Configuration</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($invoices)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h5>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></td>
                                        <td>
                                            <span class="<?php echo $invoice['days_until_due'] <= 0 ? 'text-danger' : 'text-muted'; ?>">
                                                <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?>
                                            </span>
                                        </td>
                                        <td>ETB <?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        <td>ETB <?php echo number_format($invoice['remaining_balance'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $invoice['status_color']; ?> font-size-12">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="/invoice/view/<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                <a href="/invoice/download/<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-success">PDF</a>
                                                <?php if ($invoice['status'] !== 'paid'): ?>
                                                    <a href="/invoice/add-payment/<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-info">Add Payment</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-file text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Invoices Found</h5>
                            <p class="text-muted">No invoices match your current filter criteria</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Overdue Invoices -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Overdue Invoices</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($overdue)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Due Date</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($overdue, 0, 5) as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                        <td class="text-danger"><?php echo date('M j', strtotime($invoice['due_date'])); ?></td>
                                        <td class="text-danger">ETB <?php echo number_format($invoice['remaining_balance'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($overdue) > 5): ?>
                            <div class="text-center mt-2">
                                <a href="/invoice/dashboard?status=overdue" class="btn btn-sm btn-outline-danger">View All <?php echo count($overdue); ?> Overdue</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bx bx-check-circle text-success" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No overdue invoices</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Quick Stats</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-warning"><?php echo $stats['sent_count'] ?? 0; ?></h3>
                            <p class="text-muted small mb-0">Sent</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-info"><?php echo $stats['draft_count'] ?? 0; ?></h3>
                            <p class="text-muted small mb-0">Draft</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-danger"><?php echo $stats['overdue_count'] ?? 0; ?></h3>
                            <p class="text-muted small mb-0">Overdue</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success"><?php echo number_format(($stats['total_revenue'] ?? 0) - ($stats['total_outstanding'] ?? 0), 2); ?></h3>
                            <p class="text-muted small mb-0">Collected</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>