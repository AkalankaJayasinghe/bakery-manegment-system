    <!-- Main content div should be closed here -->
    
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></span>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span class="text-muted">Version 1.0</span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (optional) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <?php if (function_exists('hasAdminPrivileges') && hasAdminPrivileges()): ?>
        <script src="<?php echo ASSETS_URL; ?>/js/admin.js"></script>
    <?php elseif (function_exists('hasCashierPrivileges') && hasCashierPrivileges()): ?>
        <script src="<?php echo ASSETS_URL; ?>/js/cashier.js"></script>
    <?php endif; ?>
    
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>