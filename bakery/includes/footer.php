        </div> <!-- End of main-content -->
        
        <!-- Footer -->
        <footer class="footer mt-5 py-3 bg-light">
            <div class="container">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start">
                        <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> | All Rights Reserved</span>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <span class="text-muted">Version 1.0</span>
                    </div>
                </div>
            </div>
        </footer>
        
        <!-- Bootstrap Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Font Awesome -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
        <!-- Custom JavaScript -->
        <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
        <?php if (function_exists('hasAdminPrivileges') && hasAdminPrivileges()): ?>
        <script src="<?php echo ASSETS_URL; ?>/js/admin.js"></script>
        <?php elseif (function_exists('hasCashierPrivileges') && hasCashierPrivileges()): ?>
        <script src="<?php echo ASSETS_URL; ?>/js/cashier.js"></script>
        <?php endif; ?>
        
        <script>
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
                    var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                });
            }, 5000);
        </script>
    </body>
</html>