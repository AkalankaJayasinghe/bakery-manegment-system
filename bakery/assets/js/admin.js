/**
 * Admin JavaScript file for Bakery Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle product image preview
    const productImageInput = document.getElementById('product-image');
    const imagePreview = document.getElementById('image-preview');
    
    if (productImageInput && imagePreview) {
        productImageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Sales data for charts
    const salesChartElement = document.getElementById('salesChart');
    if (salesChartElement && typeof Chart !== 'undefined') {
        // Chart.js should be loaded in the page
        const salesChart = new Chart(salesChartElement, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Sales',
                    data: salesData || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // Use data from PHP or default to zeros
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return currencySymbol + context.raw.toFixed(2);
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return currencySymbol + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Handle bulk actions on product/category tables
    const bulkActionSelect = document.getElementById('bulk-action');
    const bulkActionBtn = document.getElementById('apply-bulk-action');
    
    if (bulkActionSelect && bulkActionBtn) {
        bulkActionBtn.addEventListener('click', function() {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (selectedItems.length === 0) {
                alert('Please select at least one item');
                return;
            }
            
            const action = bulkActionSelect.value;
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            // Confirm action
            let confirmMessage = 'Are you sure you want to perform this action on the selected items?';
            if (action === 'delete') {
                confirmMessage = 'Are you sure you want to delete the selected items? This action cannot be undone.';
            }
            
            if (confirm(confirmMessage)) {
                document.getElementById('bulk-action-form').submit();
            }
        });
    }
    
    // Handle select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('input[name="selected_items[]"]').forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }
    
    // Report date range quick buttons
    document.querySelectorAll('.date-range-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const range = this.dataset.range;
            const today = new Date();
            let startDate = new Date();
            
            switch (range) {
                case 'today':
                    // Start date is today
                    break;
                case 'yesterday':
                    startDate.setDate(startDate.getDate() - 1);
                    today.setDate(today.getDate() - 1);
                    break;
                case 'this_week':
                    startDate.setDate(startDate.getDate() - startDate.getDay());
                    break;
                case 'last_week':
                    startDate.setDate(startDate.getDate() - startDate.getDay() - 7);
                    today.setDate(today.getDate() - today.getDay() - 1);
                    break;
                case 'this_month':
                    startDate.setDate(1);
                    break;
                case 'last_month':
                    startDate.setMonth(startDate.getMonth() - 1);
                    startDate.setDate(1);
                    today.setMonth(today.getMonth() - 1);
                    today.setDate(new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate());
                    break;
                case 'this_year':
                    startDate.setMonth(0);
                    startDate.setDate(1);
                    break;
                case 'last_year':
                    startDate.setFullYear(startDate.getFullYear() - 1);
                    startDate.setMonth(0);
                    startDate.setDate(1);
                    today.setFullYear(today.getFullYear() - 1);
                    today.setMonth(11);
                    today.setDate(31);
                    break;
                default:
                    return;
            }
            
            // Format dates as YYYY-MM-DD
            const formatDate = function(date) {
                return date.toISOString().split('T')[0];
            };
            
            document.getElementById('start_date').value = formatDate(startDate);
            document.getElementById('end_date').value = formatDate(today);
        });
    });
});