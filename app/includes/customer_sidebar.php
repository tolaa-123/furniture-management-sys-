<?php
// Customer Sidebar - Unified Style
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-couch"></i> FURNITURECRAFT
        </div>
        <div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 5px;">Customer Portal</div>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo BASE_URL; ?>/public/customer/dashboard" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo BASE_URL; ?>/public/customer/create_order" class="<?php echo $currentPage === 'create_order' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Create Order</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" class="<?php echo $currentPage === 'my-orders' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>My Orders</span>
                <?php
                // Get pending orders count
                if (isset($pdo) && isset($_SESSION['user_id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ? AND status IN ('pending_cost_approval', 'cost_estimated', 'waiting_for_deposit', 'awaiting_deposit')");
                        $stmt->execute([$_SESSION['user_id']]);
                        $pendingCount = $stmt->fetchColumn();
                        if ($pendingCount > 0) {
                            echo '<span class="menu-badge">' . $pendingCount . '</span>';
                        }
                    } catch (Exception $e) {
                        // Silently fail
                    }
                }
                ?>
            </a>
        </li>
        
        <li>
            <a href="<?php echo BASE_URL; ?>/public/customer/payments" class="<?php echo $currentPage === 'payments' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
        </li>
        
        <!-- Inspiration Gallery Dropdown -->
        <li class="has-submenu">
            <a href="#" class="submenu-toggle <?php echo (strpos($currentPage, 'gallery') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-images"></i>
                <span>Inspiration Gallery</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/sofas"><i class="fas fa-couch"></i> Sofas</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/chairs"><i class="fas fa-chair"></i> Chairs</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/beds"><i class="fas fa-bed"></i> Beds</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/tables"><i class="fas fa-table"></i> Tables</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/cabinets"><i class="fas fa-box"></i> Cabinets</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/wardrobes"><i class="fas fa-door-open"></i> Wardrobes</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/shelves"><i class="fas fa-th-large"></i> Shelves</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/customer/gallery/office"><i class="fas fa-briefcase"></i> Office Furniture</a></li>
                <li style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 5px; padding-top: 5px;">
                    <a href="<?php echo BASE_URL; ?>/public/customer/gallery/custom"><i class="fas fa-magic"></i> Custom Designs</a>
                </li>
            </ul>
        </li>
        
        <li>
            <a href="<?php echo BASE_URL; ?>/public/customer/wishlist" class="<?php echo $currentPage === 'wishlist' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i>
                <span>Wishlist</span>
            </a>
        </li>
        
        <li>
            <a href="<?php echo BASE_URL; ?>/public/customer/messages" class="<?php echo $currentPage === 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
                <?php
                if (isset($pdo) && isset($_SESSION['user_id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id = ? AND is_read = 0");
                        $stmt->execute([$_SESSION['user_id']]);
                        $unreadMsgs = $stmt->fetchColumn();
                        if ($unreadMsgs > 0) echo '<span class="menu-badge">' . $unreadMsgs . '</span>';
                    } catch (Exception $e) {}
                }
                ?>
            </a>
        </li>

        <li>
            <a href="<?php echo BASE_URL; ?>/public/customer/profile" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </li>
        
        <li style="padding: 10px 15px; border-top: 1px solid rgba(255,255,255,0.1);">
            <button id="darkModeToggle" class="dark-mode-toggle w-100">
                <i class="fas fa-moon"></i> Dark Mode
            </button>
        </li>
        
        <li style="margin-top: 10px;">
            <a href="<?php echo BASE_URL; ?>/public/logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>
<?php if (!empty($_SESSION['flash_message'])): ?>
<div id="flash-toast" data-message="<?php echo htmlspecialchars($_SESSION['flash_message']); ?>" data-type="<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'success'); ?>" style="display:none;"></div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>


<style>
/* Submenu Styles */
.sidebar-menu .has-submenu .submenu {
    display: none;
    list-style: none;
    padding-left: 0;
    margin: 0;
    background: rgba(0,0,0,0.2);
    border-radius: 5px;
    margin-top: 5px;
    overflow: hidden;
}

.sidebar-menu .has-submenu .submenu.active {
    display: block;
}

.sidebar-menu .has-submenu .submenu li {
    margin: 0;
}

.sidebar-menu .has-submenu .submenu li a {
    padding: 10px 15px 10px 45px;
    font-size: 13px;
    color: rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.sidebar-menu .has-submenu .submenu li a:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
    padding-left: 50px;
}

.sidebar-menu .has-submenu .submenu li a i {
    font-size: 12px;
    width: 16px;
}

.sidebar-menu .submenu-toggle {
    position: relative;
}

.sidebar-menu .submenu-arrow {
    position: absolute;
    right: 15px;
    font-size: 12px;
    transition: transform 0.3s ease;
}

.sidebar-menu .submenu-toggle.active .submenu-arrow {
    transform: rotate(180deg);
}
</style>

<script>
// Submenu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            const isActive = submenu.classList.contains('active');
            
            // Close all submenus
            document.querySelectorAll('.submenu').forEach(sm => sm.classList.remove('active'));
            document.querySelectorAll('.submenu-toggle').forEach(st => st.classList.remove('active'));
            
            // Toggle current submenu
            if (!isActive) {
                submenu.classList.add('active');
                this.classList.add('active');
            }
        });
    });
    
    // Auto-open submenu if on gallery page
    const currentPath = window.location.pathname;
    if (currentPath.includes('/gallery/')) {
        const galleryToggle = document.querySelector('.submenu-toggle');
        const gallerySubmenu = document.querySelector('.submenu');
        if (galleryToggle && gallerySubmenu) {
            galleryToggle.classList.add('active');
            gallerySubmenu.classList.add('active');
        }
    }
});
</script>
