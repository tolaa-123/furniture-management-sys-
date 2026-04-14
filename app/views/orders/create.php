<?php
// Don't use the header that has redirect logic - build our own
$pageTitle = 'Create Order';
$userName = $_SESSION['username'] ?? 'Customer';
$userRole = $_SESSION['user_role'] ?? 'customer';
$userPhone = $_SESSION['phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Furniture ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>/public/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="<?php echo BASE_URL; ?>/public/customer/dashboard">
            <i class="fas fa-couch"></i> Furniture ERP
        </a>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="<?php echo BASE_URL; ?>/public/logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/public/customer/dashboard">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?php echo BASE_URL; ?>/public/orders/create">
                                <i class="fas fa-plus-circle"></i> Create Order
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/public/orders/my-orders">
                                <i class="fas fa-list"></i> My Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/public/payments">
                                <i class="fas fa-credit-card"></i> Payments
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Create New Order</h1>
                    <a href="<?php echo BASE_URL; ?>/public/orders/my-orders" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list"></i> My Orders
                    </a>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Customer Info -->
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-user"></i> Customer Information
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($userName); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($userPhone); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Delivery Address *</label>
                                        <textarea class="form-control" id="deliveryAddress" rows="2" required></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Alternate Phone</label>
                                        <input type="text" class="form-control" id="alternatePhone">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Products -->
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-shopping-cart"></i> Select Products
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="searchProduct" placeholder="Search products...">
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" id="categoryFilter">
                                            <option value="all">All Categories</option>
                                            <option value="Bed">Beds</option>
                                            <option value="Table">Tables</option>
                                            <option value="Chair">Chairs</option>
                                            <option value="Sofa">Sofas</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="productGrid" class="row">
                                    <div class="col-12 text-center py-3">
                                        <div class="spinner-border"></div>
                                        <p>Loading products...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Cart -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-shopping-bag"></i> Cart
                            </div>
                            <div class="card-body">
                                <div id="cartItems">
                                    <p class="text-muted text-center">Cart is empty</p>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Subtotal:</strong>
                                    <strong id="cartSubtotal">ETB 0.00</strong>
                                </div>
                                <hr>
                                <h6>Payment Terms</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total:</span>
                                    <strong id="totalAmount">ETB 0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-warning">
                                    <span>Deposit (40%):</span>
                                    <strong id="depositAmount">ETB 0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-3 text-muted">
                                    <span>Balance (60%):</span>
                                    <span id="balanceAmount">ETB 0.00</span>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Payment Method *</label>
                                    <select class="form-select" id="paymentMethod">
                                        <option value="">Select...</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="mobile">Mobile Money</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Special Instructions</label>
                                    <textarea class="form-control" id="orderNotes" rows="2"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Delivery Date</label>
                                    <input type="date" class="form-control" id="preferredDeliveryDate" min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" id="placeOrderBtn" disabled>
                                        <i class="fas fa-check"></i> Place Order
                                    </button>
                                    <button class="btn btn-outline-danger" id="clearCartBtn" disabled>
                                        <i class="fas fa-trash"></i> Clear Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <template id="productCardTemplate">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="product-name"></h6>
                    <p class="small text-muted product-category"></p>
                    <p class="text-primary product-price"></p>
                    <div class="input-group input-group-sm mb-2">
                        <button class="btn btn-outline-secondary btn-qty-minus">-</button>
                        <input type="number" class="form-control text-center product-qty" value="1" min="1">
                        <button class="btn btn-outline-secondary btn-qty-plus">+</button>
                    </div>
                    <button class="btn btn-success btn-sm w-100 btn-add-to-cart">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </template>

    <template id="cartItemTemplate">
        <div class="cart-item mb-2 p-2 border rounded">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="mb-0 cart-item-name"></h6>
                    <small class="text-muted">ETB <span class="cart-item-price"></span> x <span class="cart-item-qty"></span></small>
                </div>
                <button class="btn btn-sm btn-outline-danger btn-remove-item">×</button>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary btn-cart-minus">-</button>
                    <button class="btn btn-outline-secondary btn-cart-plus">+</button>
                </div>
                <strong>ETB <span class="cart-item-subtotal"></span></strong>
            </div>
        </div>
    </template>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        let products = [];
        let cart = [];
        const BASE_URL = '<?php echo BASE_URL; ?>';
        
        function loadProducts() {
            $.ajax({
                url: BASE_URL + '/public/api/get_products.php',
                data: { category: $('#categoryFilter').val(), search: $('#searchProduct').val() },
                success: function(response) {
                    if (response.success) {
                        products = response.products;
                        renderProducts();
                    }
                },
                error: function() {
                    $('#productGrid').html('<div class="col-12"><div class="alert alert-danger">Failed to load products</div></div>');
                }
            });
        }
        
        function renderProducts() {
            const grid = $('#productGrid');
            grid.empty();
            
            if (products.length === 0) {
                grid.html('<div class="col-12"><p class="text-center text-muted">No products found</p></div>');
                return;
            }
            
            products.forEach(product => {
                const template = $('#productCardTemplate').prop('content').cloneNode(true);
                const card = $(template);
                
                card.find('.product-name').text(product.name);
                card.find('.product-category').text(product.category_name);
                card.find('.product-price').text('ETB ' + parseFloat(product.base_price).toFixed(2));
                
                card.find('.btn-qty-minus').on('click', function() {
                    const input = $(this).siblings('.product-qty');
                    if (parseInt(input.val()) > 1) input.val(parseInt(input.val()) - 1);
                });
                
                card.find('.btn-qty-plus').on('click', function() {
                    const input = $(this).siblings('.product-qty');
                    input.val(parseInt(input.val()) + 1);
                });
                
                card.find('.btn-add-to-cart').on('click', function() {
                    const qty = parseInt(card.find('.product-qty').val());
                    addToCart(product, qty);
                });
                
                grid.append(card);
            });
        }
        
        function addToCart(product, quantity) {
            const existing = cart.find(item => item.product_id === product.id);
            if (existing) {
                existing.quantity += quantity;
            } else {
                cart.push({
                    product_id: product.id,
                    name: product.name,
                    price: parseFloat(product.base_price),
                    quantity: quantity
                });
            }
            renderCart();
            updateTotals();
        }
        
        function renderCart() {
            const container = $('#cartItems');
            container.empty();
            
            if (cart.length === 0) {
                container.html('<p class="text-muted text-center">Cart is empty</p>');
                $('#placeOrderBtn, #clearCartBtn').prop('disabled', true);
                return;
            }
            
            cart.forEach((item, index) => {
                const template = $('#cartItemTemplate').prop('content').cloneNode(true);
                const cartItem = $(template);
                
                cartItem.find('.cart-item-name').text(item.name);
                cartItem.find('.cart-item-price').text(item.price.toFixed(2));
                cartItem.find('.cart-item-qty').text(item.quantity);
                cartItem.find('.cart-item-subtotal').text((item.price * item.quantity).toFixed(2));
                
                cartItem.find('.btn-cart-minus').on('click', function() {
                    if (item.quantity > 1) {
                        item.quantity--;
                        renderCart();
                        updateTotals();
                    }
                });
                
                cartItem.find('.btn-cart-plus').on('click', function() {
                    item.quantity++;
                    renderCart();
                    updateTotals();
                });
                
                cartItem.find('.btn-remove-item').on('click', function() {
                    cart.splice(index, 1);
                    renderCart();
                    updateTotals();
                });
                
                container.append(cartItem);
            });
            
            $('#placeOrderBtn, #clearCartBtn').prop('disabled', false);
        }
        
        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const deposit = subtotal * 0.40;
            const balance = subtotal * 0.60;
            
            $('#cartSubtotal').text('ETB ' + subtotal.toFixed(2));
            $('#totalAmount').text('ETB ' + subtotal.toFixed(2));
            $('#depositAmount').text('ETB ' + deposit.toFixed(2));
            $('#balanceAmount').text('ETB ' + balance.toFixed(2));
        }
        
        $('#placeOrderBtn').on('click', function() {
            const address = $('#deliveryAddress').val().trim();
            const method = $('#paymentMethod').val();
            
            if (!address) { alert('Please enter delivery address'); return; }
            if (!method) { alert('Please select payment method'); return; }
            if (!confirm('Place this order?')) return;
            
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            $.ajax({
                url: BASE_URL + '/public/api/submit_order.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    items: cart,
                    delivery_address: address,
                    alternate_phone: $('#alternatePhone').val(),
                    order_notes: $('#orderNotes').val(),
                    preferred_delivery_date: $('#preferredDeliveryDate').val(),
                    payment_method: method
                }),
                success: function(response) {
                    if (response.success) {
                        window.location.href = BASE_URL + '/public/orders/success?order_id=' + response.order_id;
                    } else {
                        alert(response.message);
                        $('#placeOrderBtn').prop('disabled', false).html('<i class="fas fa-check"></i> Place Order');
                    }
                },
                error: function() {
                    alert('Error placing order');
                    $('#placeOrderBtn').prop('disabled', false).html('<i class="fas fa-check"></i> Place Order');
                }
            });
        });
        
        $('#clearCartBtn').on('click', function() {
            if (confirm('Clear cart?')) {
                cart = [];
                renderCart();
                updateTotals();
            }
        });
        
        $('#searchProduct').on('input', function() {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(loadProducts, 300);
        });
        
        $('#categoryFilter').on('change', loadProducts);
        
        loadProducts();
    });
    </script>
</body>
</html>
