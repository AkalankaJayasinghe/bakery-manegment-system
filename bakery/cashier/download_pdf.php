<?php
session_start();
require_once 'invoice_pdf.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Get invoice ID from URL
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    die('Invalid invoice ID');
}

try {
    $pdf = new InvoicePDF($sale_id);
    $pdf->downloadPDF();
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>
