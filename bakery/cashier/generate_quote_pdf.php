<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Get cart data from POST request
$cart_data = isset($_POST['cart_data']) ? json_decode($_POST['cart_data'], true) : null;
$customer_name = isset($_POST['customer_name']) ? $_POST['customer_name'] : '';
$subtotal = isset($_POST['subtotal']) ? floatval($_POST['subtotal']) : 0;
$tax_amount = isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : 0;
$discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
$total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';

if (!$cart_data || empty($cart_data)) {
    die('No cart data provided');
}

// Get current user info
$conn = connectDB();
$cashier_id = $_SESSION['user_id'];
$cashier_name = getUserName($conn, $cashier_id);

// Generate quote number
$quote_no = 'Q' . date('Ymd') . '-' . rand(1000, 9999);

class QuotePDF {
    private $cart_data;
    private $customer_name;
    private $subtotal;
    private $tax_amount;
    private $discount_amount;
    private $total_amount;
    private $payment_method;
    private $quote_no;
    private $cashier_name;
    
    public function __construct($cart_data, $customer_name, $subtotal, $tax_amount, $discount_amount, $total_amount, $payment_method, $quote_no, $cashier_name) {
        $this->cart_data = $cart_data;
        $this->customer_name = $customer_name;
        $this->subtotal = $subtotal;
        $this->tax_amount = $tax_amount;
        $this->discount_amount = $discount_amount;
        $this->total_amount = $total_amount;
        $this->payment_method = $payment_method;
        $this->quote_no = $quote_no;
        $this->cashier_name = $cashier_name;
    }
    
    public function generateHTML() {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Quote #' . htmlspecialchars($this->quote_no) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; line-height: 1.6; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #ff6b6b; padding-bottom: 20px; }
                .logo { font-size: 24px; color: #ff6b6b; margin-bottom: 10px; }
                .company-name { font-size: 28px; font-weight: bold; color: #ff6b6b; margin: 0; }
                .quote-title { font-size: 36px; font-weight: bold; color: #333; margin: 20px 0 10px; }
                .quote-number { font-size: 24px; color: #666; }
                .quote-info { display: flex; justify-content: space-between; margin: 30px 0; }
                .info-section { flex: 1; margin-right: 20px; }
                .info-section:last-child { margin-right: 0; }
                .info-section h3 { color: #ff6b6b; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                .table th { background-color: #ff6b6b; color: white; font-weight: bold; }
                .table .text-right { text-align: right; }
                .table .text-center { text-align: center; }
                .table tbody tr:nth-child(even) { background-color: #f8f9fa; }
                .totals { margin-top: 30px; }
                .totals table { width: 100%; max-width: 400px; margin-left: auto; }
                .totals table td { border: 1px solid #ddd; padding: 10px; }
                .totals .total-row { background-color: #ff6b6b; color: white; font-weight: bold; font-size: 18px; }
                .footer { margin-top: 50px; text-align: center; border-top: 1px solid #ddd; padding-top: 20px; color: #666; }
                .thank-you { font-size: 20px; color: #ff6b6b; font-weight: bold; margin-bottom: 10px; }
                .quote-note { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .quote-note h4 { color: #856404; margin-top: 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo">🧁</div>
                <h1 class="company-name">Bakery Management System</h1>
                <p>Fresh Baked Goods Daily</p>
                <p>📧 info@bakery.com | 📞 (123) 456-7890</p>
                <h2 class="quote-title">QUOTE</h2>
                <div class="quote-number">#' . htmlspecialchars($this->quote_no) . '</div>
            </div>
            
            <div class="quote-info">
                <div class="info-section">
                    <h3>Quote For:</h3>
                    <p><strong>' . (!empty($this->customer_name) ? htmlspecialchars($this->customer_name) : 'Valued Customer') . '</strong></p>
                </div>
                <div class="info-section">
                    <h3>Quote Details:</h3>
                    <p><strong>Date:</strong> ' . date('M j, Y g:i A') . '</p>
                    <p><strong>Prepared by:</strong> ' . htmlspecialchars($this->cashier_name) . '</p>
                    <p><strong>Payment Method:</strong> ' . ucfirst(htmlspecialchars($this->payment_method)) . '</p>
                    <p><strong>Valid Until:</strong> ' . date('M j, Y', strtotime('+7 days')) . '</p>
                </div>
            </div>
            
            <div class="quote-note">
                <h4>📋 Quote Information</h4>
                <p>This is a quote/estimate for your bakery order. Prices are subject to change based on availability. This quote is valid for 7 days from the date issued.</p>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($this->cart_data as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $html .= '<tr>
                        <td>' . htmlspecialchars($item['name']) . '</td>
                        <td class="text-center">' . $item['quantity'] . '</td>
                        <td class="text-right">$' . number_format($item['price'], 2) . '</td>
                        <td class="text-right">$' . number_format($item_total, 2) . '</td>
                      </tr>';
        }
        
        $html .= '</tbody>
            </table>
            
            <div class="totals">
                <table>
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-right">$' . number_format($this->subtotal, 2) . '</td>
                    </tr>';
        
        if ($this->discount_amount > 0) {
            $html .= '<tr>
                        <td><strong>Discount:</strong></td>
                        <td class="text-right">-$' . number_format($this->discount_amount, 2) . '</td>
                      </tr>';
        }
        
        $html .= '<tr>
                    <td><strong>Tax:</strong></td>
                    <td class="text-right">$' . number_format($this->tax_amount, 2) . '</td>
                  </tr>
                  <tr class="total-row">
                    <td><strong>Total:</strong></td>
                    <td class="text-right">$' . number_format($this->total_amount, 2) . '</td>
                  </tr>
                </table>
            </div>
            
            <div class="footer">
                <div class="thank-you">Thank you for choosing our bakery!</div>
                <p>Please contact us if you have any questions about this quote.</p>
                <p>Visit us: 123 Bakery Street, Sweet City | Call: (123) 456-7890</p>
                <hr style="margin: 20px 0;">
                <p style="font-size: 12px;">This quote was generated on ' . date('M j, Y \a\t g:i A') . '</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    public function downloadPDF() {
        $html = $this->generateHTML();
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Quote_' . $this->quote_no . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Check if wkhtmltopdf is available
        $wkhtmltopdf_path = '';
        if (file_exists('/usr/local/bin/wkhtmltopdf')) {
            $wkhtmltopdf_path = '/usr/local/bin/wkhtmltopdf';
        } elseif (file_exists('/usr/bin/wkhtmltopdf')) {
            $wkhtmltopdf_path = '/usr/bin/wkhtmltopdf';
        } elseif (file_exists('C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe')) {
            $wkhtmltopdf_path = '"C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe"';
        }
        
        if (!empty($wkhtmltopdf_path)) {
            // Use wkhtmltopdf if available
            $temp_html = tempnam(sys_get_temp_dir(), 'quote_') . '.html';
            $temp_pdf = tempnam(sys_get_temp_dir(), 'quote_') . '.pdf';
            
            file_put_contents($temp_html, $html);
            
            $command = $wkhtmltopdf_path . ' --page-size A4 --margin-top 0.75in --margin-right 0.75in --margin-bottom 0.75in --margin-left 0.75in --encoding UTF-8 --quiet ' . escapeshellarg($temp_html) . ' ' . escapeshellarg($temp_pdf);
            exec($command, $output, $return_code);
            
            if ($return_code === 0 && file_exists($temp_pdf)) {
                readfile($temp_pdf);
                unlink($temp_html);
                unlink($temp_pdf);
                return;
            }
            
            // Clean up temp files if PDF generation failed
            if (file_exists($temp_html)) unlink($temp_html);
            if (file_exists($temp_pdf)) unlink($temp_pdf);
        }
        
        // Fallback: Output HTML with PDF-like styling and print CSS
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="Quote_' . $this->quote_no . '.html"');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Quote #' . htmlspecialchars($this->quote_no) . '</title>
            <style>
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                    @page { margin: 0.5in; }
                }
                .print-header {
                    background: #ff6b6b;
                    color: white;
                    padding: 10px;
                    text-align: center;
                    margin-bottom: 20px;
                }
            </style>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 1000);
                };
            </script>
        </head>
        <body>';
        
        echo '<div class="print-header no-print">
                <h3>🖨️ Ready to Print</h3>
                <p>This page will automatically print. If not, press Ctrl+P (Windows) or Cmd+P (Mac)</p>
              </div>';
        
        echo $html;
        echo '</body></html>';
    }
}

try {
    $pdf = new QuotePDF($cart_data, $customer_name, $subtotal, $tax_amount, $discount_amount, $total_amount, $payment_method, $quote_no, $cashier_name);
    $pdf->downloadPDF();
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>
