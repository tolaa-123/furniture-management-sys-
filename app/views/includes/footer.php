<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="<?php echo BASE_URL; ?>/public/assets/js/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script src="/NEWkoder/public/assets/js/main.js"></script>

<script>
// Handle sidebar active state
document.addEventListener('DOMContentLoaded', function() {
    // Get current page from URL
    const currentPage = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
            // Also activate parent if it's a dropdown
            const parent = link.closest('.nav-item');
            if (parent) {
                parent.classList.add('active');
            }
        }
    });
    
    // Handle sidebar toggle for mobile
    const sidebarToggle = document.querySelector('.navbar-toggler');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }
});
</script>

</body>
</html>