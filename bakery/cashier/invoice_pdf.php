<?php
require_once 'includes/compatibility.php';

class InvoicePDF {
    private $conn;
    private $sale_data;
    private $sale_items;
    
    public function __construct($sale_id) {
        $this->conn = connectDB();
        $this->loadSaleData($sale_id);
    }
    
    private function loadSaleData($sale_id) {
        // Get sale details
        $saleQuery = "SELECT s.*, u.first_name, u.last_name 
                      FROM sales s 
                      LEFT JOIN users u ON s.cashier_id = u.id 
                      WHERE s.id = ?";
        $saleStmt = $this->conn->prepare($saleQuery);
        $saleStmt->bind_param("i", $sale_id);
        $saleStmt->execute();
        $saleResult = $saleStmt->get_result();
        
        if ($saleResult->num_rows === 0) {
            throw new Exception("Sale not found");
        }
        
        $this->sale_data = $saleResult->fetch_assoc();
        
        // Get sale items
        $itemsQuery = "SELECT si.*, p.name as product_name 
                       FROM sale_items si 
                       LEFT JOIN products p ON si.product_id = p.id 
                       WHERE si.sale_id = ?";
        $itemsStmt = $this->conn->prepare($itemsQuery);
        $itemsStmt->bind_param("i", $sale_id);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        $this->sale_items = [];
        while ($row = $itemsResult->fetch_assoc()) {
            $this->sale_items[] = $row;
        }
    }
    
    public function generateHTML() {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice #' . htmlspecialchars($this->sale_data['invoice_no']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #ff6b6b; padding-bottom: 20px; }
                .logo { font-size: 24px; color: #ff6b6b; margin-bottom: 10px; }
                .company-name { font-size: 28px; font-weight: bold; color: #ff6b6b; margin: 0; }
                .invoice-title { font-size: 36px; font-weight: bold; color: #333; margin: 20px 0 10px; }
                .invoice-number { font-size: 24px; color: #666; }
                .invoice-info { display: flex; justify-content: space-between; margin: 30px 0; }
                .info-section { flex: 1; }
                .info-section h3 { color: #ff6b6b; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                .table th { background-color: #f8f9fa; font-weight: bold; color: #333; }
                .table .text-right { text-align: right; }
                .table .text-center { text-align: center; }
                .totals { margin-top: 30px; }
                .totals table { width: 50%; margin-left: auto; }
                .totals .total-row { background-color: #f8f9fa; font-weight: bold; font-size: 18px; }
                .footer { margin-top: 50px; text-align: center; border-top: 1px solid #ddd; padding-top: 20px; color: #666; }
                .thank-you { font-size: 20px; color: #ff6b6b; font-weight: bold; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo">🧁</div>
                <h1 class="company-name">Bakery Management System</h1>
                <p>Fresh Baked Goods Daily</p>
                <p>📧 info@bakery.com | 📞 (123) 456-7890</p>
                <h2 class="invoice-title">INVOICE</h2>
                <div class="invoice-number">#' . htmlspecialchars($this->sale_data['invoice_no']) . '</div>
            </div>
            
            <div class="invoice-info">
                <div class="info-section">
                    <h3>Bill To:</h3>
                    <p><strong>' . (!empty($this->sale_data['customer_name']) ? htmlspecialchars($this->sale_data['customer_name']) : 'Walk-in Customer') . '</strong></p>
                </div>
                <div class="info-section">
                    <h3>Invoice Details:</h3>
                    <p><strong>Date:</strong> ' . date('M j, Y g:i A', strtotime($this->sale_data['created_at'])) . '</p>
                    <p><strong>Cashier:</strong> ' . htmlspecialchars($this->sale_data['first_name'] . ' ' . $this->sale_data['last_name']) . '</p>
                    <p><strong>Payment:</strong> ' . ucfirst(htmlspecialchars($this->sale_data['payment_method'])) . '</p>
                </div>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        $item_no = 1;
        foreach ($this->sale_items as $item) {
            $html .= '<tr>
                        <td>' . $item_no++ . '</td>
                        <td>' . htmlspecialchars($item['product_name']) . '</td>
                        <td class="text-center">' . $item['quantity'] . '</td>
                        <td class="text-right">$' . number_format($item['unit_price'], 2) . '</td>
                        <td class="text-right">$' . number_format($item['subtotal'], 2) . '</td>
                    </tr>';
        }
        
        $html .= '</tbody>
            </table>
            
            <div class="totals">
                <table class="table">
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-right">$' . number_format($this->sale_data['subtotal'], 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Tax:</strong></td>
                        <td class="text-right">$' . number_format($this->sale_data['tax_amount'], 2) . '</td>
                    </tr>';
        
        if ($this->sale_data['discount_amount'] > 0) {
            $html .= '<tr>
                        <td><strong>Discount:</strong></td>
                        <td class="text-right">-$' . number_format($this->sale_data['discount_amount'], 2) . '</td>
                    </tr>';
        }
        
        $html .= '<tr class="total-row">
                        <td><strong>TOTAL:</strong></td>
                        <td class="text-right"><strong>$' . number_format($this->sale_data['total_amount'], 2) . '</strong></td>
                    </tr>
                </table>
            </div>';
        
        if (!empty($this->sale_data['notes'])) {
            $html .= '<div style="margin-top: 30px;">
                        <h3>Notes:</h3>
                        <p>' . nl2br(htmlspecialchars($this->sale_data['notes'])) . '</p>
                    </div>';
        }
        
        $html .= '<div class="footer">
                <div class="thank-you">Thank you for your business!</div>
                <p>Visit us again for fresh baked goods daily</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    public function downloadPDF() {
        $html = $this->generateHTML();
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Invoice_' . $this->sale_data['invoice_no'] . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Convert HTML to PDF using wkhtmltopdf if available, otherwise use simple HTML output
        if ($this->isWkhtmltopdfAvailable()) {
            $this->generatePDFWithWkhtmltopdf($html);
        } else {
            // Fallback: output HTML with print-friendly styles
            $this->outputPrintFriendlyHTML($html);
        }
    }
    
    private function isWkhtmltopdfAvailable() {
        $output = [];
        $return_var = 0;
        exec('wkhtmltopdf --version 2>&1', $output, $return_var);
        return $return_var === 0;
    }
    
    private function generatePDFWithWkhtmltopdf($html) {
        $temp_html = tempnam(sys_get_temp_dir(), 'invoice_') . '.html';
        $temp_pdf = tempnam(sys_get_temp_dir(), 'invoice_') . '.pdf';
        
        file_put_contents($temp_html, $html);
        
        $command = "wkhtmltopdf --page-size A4 --orientation Portrait --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm '$temp_html' '$temp_pdf'";
        exec($command);
        
        if (file_exists($temp_pdf)) {
            readfile($temp_pdf);
            unlink($temp_html);
            unlink($temp_pdf);
        } else {
            $this->outputPrintFriendlyHTML($html);
        }
    }
    
    private function outputPrintFriendlyHTML($html) {
        // Change content type to HTML and add JavaScript for auto-print
        header('Content-Type: text/html');
        header('Content-Disposition: inline');
        
        $printableHtml = str_replace('</body>', '
            <script>
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>', $html);
        
        echo $printableHtml;
    }
}
?>
