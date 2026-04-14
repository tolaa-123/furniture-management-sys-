<?php
/**
 * Invoice Controller
 * Handles invoice generation, PDF creation, and management
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/models/InvoiceModel.php';

// Include TCPDF library
require_once dirname(__DIR__) . '/../vendor/tcpdf/tcpdf.php';

class InvoiceController extends BaseController {
    private $invoiceModel;
    
    public function __construct() {
        parent::__construct();
        $this->invoiceModel = new InvoiceModel();
    }
    
    /**
     * Invoice dashboard
     */
    public function dashboard() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $status = $_GET['status'] ?? null;
        $invoices = $this->invoiceModel->getInvoices($status, 50);
        $stats = $this->invoiceModel->getInvoiceStats();
        $overdue = $this->invoiceModel->getOverdueInvoices();
        
        $data = [
            'invoices' => $invoices,
            'stats' => $stats,
            'overdue' => $overdue,
            'filterStatus' => $status
        ];
        
        $this->render('invoice/dashboard', $data);
    }
    
    /**
     * Generate invoice for order
     */
    public function generate($orderId) {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $customerId = $_POST['customer_id'] ?? null;
                if (!$customerId) {
                    throw new Exception('Customer ID is required');
                }
                
                $invoiceId = $this->invoiceModel->generateInvoice($orderId, $customerId);
                
                if ($invoiceId) {
                    $this->logAudit('invoice_generated', 'furn_invoices', $invoiceId, ['order_id' => $orderId]);
                    $this->setFlashMessage('success', 'Invoice generated successfully');
                    $this->redirect('/invoice/view/' . $invoiceId);
                }
            } catch (Exception $e) {
                $this->setFlashMessage('error', $e->getMessage());
                $this->redirect('/orders/view/' . $orderId);
            }
        }
        
        // Show generate invoice confirmation
        $order = $this->getOrderById($orderId);
        if (!$order) {
            $this->setFlashMessage('error', 'Order not found');
            $this->redirect('/orders');
            return;
        }
        
        $data = ['order' => $order];
        $this->render('invoice/generate', $data);
    }
    
    /**
     * View invoice details
     */
    public function view($invoiceId) {
        $invoice = $this->invoiceModel->getInvoiceDetails($invoiceId);
        if (!$invoice) {
            $this->setFlashMessage('error', 'Invoice not found');
            $this->redirect('/invoice/dashboard');
            return;
        }
        
        $items = $this->invoiceModel->getInvoiceItems($invoiceId);
        $payments = $this->invoiceModel->getInvoicePayments($invoiceId);
        
        $data = [
            'invoice' => $invoice,
            'items' => $items,
            'payments' => $payments
        ];
        
        $this->render('invoice/view', $data);
    }
    
    /**
     * Generate and download PDF invoice
     */
    public function download($invoiceId) {
        $invoice = $this->invoiceModel->getInvoiceDetails($invoiceId);
        if (!$invoice) {
            $this->setFlashMessage('error', 'Invoice not found');
            $this->redirect('/invoice/dashboard');
            return;
        }
        
        $items = $this->invoiceModel->getInvoiceItems($invoiceId);
        
        // Create PDF
        $pdf = $this->generateInvoicePDF($invoice, $items);
        
        // Set headers for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="invoice_' . $invoice['invoice_number'] . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output PDF
        $pdf->Output('invoice_' . $invoice['invoice_number'] . '.pdf', 'D');
        exit;
    }
    
    /**
     * View invoice as PDF in browser
     */
    public function viewPdf($invoiceId) {
        $invoice = $this->invoiceModel->getInvoiceDetails($invoiceId);
        if (!$invoice) {
            $this->setFlashMessage('error', 'Invoice not found');
            $this->redirect('/invoice/dashboard');
            return;
        }
        
        $items = $this->invoiceModel->getInvoiceItems($invoiceId);
        
        // Create PDF
        $pdf = $this->generateInvoicePDF($invoice, $items);
        
        // Set headers for inline view
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="invoice_' . $invoice['invoice_number'] . '.pdf"');
        
        // Output PDF
        $pdf->Output('invoice_' . $invoice['invoice_number'] . '.pdf', 'I');
        exit;
    }
    
    /**
     * Add payment to invoice
     */
    public function addPayment($invoiceId) {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $amount = floatval($_POST['amount'] ?? 0);
                $paymentMethod = $_POST['payment_method'] ?? '';
                $referenceNumber = $_POST['reference_number'] ?? null;
                $notes = $_POST['notes'] ?? null;
                
                if ($amount <= 0) {
                    throw new Exception('Payment amount must be greater than 0');
                }
                
                if (empty($paymentMethod)) {
                    throw new Exception('Payment method is required');
                }
                
                $result = $this->invoiceModel->addPayment($invoiceId, $amount, $paymentMethod, $referenceNumber, $notes);
                
                if ($result) {
                    $this->logAudit('invoice_payment_added', 'furn_invoice_payments', null, [
                        'invoice_id' => $invoiceId,
                        'amount' => $amount,
                        'payment_method' => $paymentMethod
                    ]);
                    $this->setFlashMessage('success', 'Payment recorded successfully');
                }
            } catch (Exception $e) {
                $this->setFlashMessage('error', $e->getMessage());
            }
            
            $this->redirect('/invoice/view/' . $invoiceId);
        }
        
        // Show add payment form
        $invoice = $this->invoiceModel->getInvoiceDetails($invoiceId);
        if (!$invoice) {
            $this->setFlashMessage('error', 'Invoice not found');
            $this->redirect('/invoice/dashboard');
            return;
        }
        
        $data = ['invoice' => $invoice];
        $this->render('invoice/add_payment', $data);
    }
    
    /**
     * Update invoice configuration
     */
    public function configuration() {
        if (!$this->isAdmin()) {
            $this->setFlashMessage('error', 'Access denied. Admin access required.');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $configData = [
                'company_name' => $_POST['company_name'] ?? '',
                'company_address' => $_POST['company_address'] ?? '',
                'company_phone' => $_POST['company_phone'] ?? '',
                'company_email' => $_POST['company_email'] ?? '',
                'bank_name' => $_POST['bank_name'] ?? '',
                'bank_account_number' => $_POST['bank_account_number'] ?? '',
                'bank_account_name' => $_POST['bank_account_name'] ?? '',
                'bank_branch' => $_POST['bank_branch'] ?? null,
                'swift_code' => $_POST['swift_code'] ?? null,
                'notes' => $_POST['notes'] ?? null
            ];
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                require_once __DIR__ . '/../../core/SecurityUtil.php';
                $uploadDir = '../public/uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $uploadResult = SecurityUtil::validateUpload(
                    $_FILES['logo'],
                    ['jpg', 'jpeg', 'png', 'gif'],
                    2 * 1024 * 1024
                );
                if ($uploadResult && $uploadResult['valid']) {
                    $fileName = 'company_logo.' . $uploadResult['extension'];
                    $uploadPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                        $configData['logo_path'] = '/uploads/logos/' . $fileName;
                    }
                }
            }
            
            $result = $this->invoiceModel->updateInvoiceConfig($configData);
            
            if ($result) {
                $this->logAudit('invoice_config_updated', 'furn_invoice_config', 1, $configData);
                $this->setFlashMessage('success', 'Invoice configuration updated successfully');
            } else {
                $this->setFlashMessage('error', 'Failed to update configuration');
            }
            
            $this->redirect('/invoice/configuration');
        }
        
        $config = $this->invoiceModel->getInvoiceConfig();
        $data = ['config' => $config];
        $this->render('invoice/configuration', $data);
    }
    
    /**
     * Generate professional PDF invoice
     */
    private function generateInvoicePDF($invoice, $items) {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($invoice['company_name']);
        $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);
        $pdf->SetSubject('Invoice');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 12);
        
        // Company Logo and Header
        $this->addCompanyHeader($pdf, $invoice);
        
        // Invoice details
        $this->addInvoiceDetails($pdf, $invoice);
        
        // Customer information
        $this->addCustomerInfo($pdf, $invoice);
        
        // Invoice items table
        $this->addInvoiceItemsTable($pdf, $items, $invoice);
        
        // Payment summary
        $this->addPaymentSummary($pdf, $invoice);
        
        // Bank details
        $this->addBankDetails($pdf, $invoice);
        
        // Signature section
        $this->addSignatureSection($pdf);
        
        // Terms and notes
        $this->addTermsAndNotes($pdf, $invoice);
        
        return $pdf;
    }
    
    private function addCompanyHeader($pdf, $invoice) {
        $html = '
        <table width="100%">
            <tr>
                <td width="50%">';
        
        // Company logo
        if (!empty($invoice['logo_path'])) {
            $logoPath = dirname(__DIR__) . '/..' . $invoice['logo_path'];
            if (file_exists($logoPath)) {
                $html .= '<img src="' . $logoPath . '" height="50">';
            }
        }
        
        $html .= '</td>
                <td width="50%" align="right">
                    <h1 style="color: #333; margin: 0;">INVOICE</h1>
                    <h3 style="color: #666; margin: 5px 0 0 0;">' . $invoice['invoice_number'] . '</h3>
                </td>
            </tr>
        </table>
        
        <table width="100%" style="margin-top: 20px;">
            <tr>
                <td width="50%">
                    <h2 style="color: #333; margin: 0;">' . htmlspecialchars($invoice['company_name']) . '</h2>
                    <p style="margin: 5px 0; color: #666;">' . nl2br(htmlspecialchars($invoice['company_address'])) . '</p>';
        
        if (!empty($invoice['company_phone'])) {
            $html .= '<p style="margin: 2px 0; color: #666;">Phone: ' . htmlspecialchars($invoice['company_phone']) . '</p>';
        }
        
        if (!empty($invoice['company_email'])) {
            $html .= '<p style="margin: 2px 0; color: #666;">Email: ' . htmlspecialchars($invoice['company_email']) . '</p>';
        }
        
        $html .= '</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addInvoiceDetails($pdf, $invoice) {
        $html = '
        <table width="100%" style="margin-top: 30px; border: 1px solid #ddd;">
            <tr style="background-color: #f8f9fa;">
                <td width="25%" style="padding: 10px; border-right: 1px solid #ddd;"><strong>Invoice Date:</strong></td>
                <td width="25%" style="padding: 10px; border-right: 1px solid #ddd;">' . date('M j, Y', strtotime($invoice['invoice_date'])) . '</td>
                <td width="25%" style="padding: 10px; border-right: 1px solid #ddd;"><strong>Due Date:</strong></td>
                <td width="25%" style="padding: 10px;">' . date('M j, Y', strtotime($invoice['due_date'])) . '</td>
            </tr>
            <tr>
                <td width="25%" style="padding: 10px; border-right: 1px solid #ddd; border-top: 1px solid #ddd;"><strong>Order Number:</strong></td>
                <td width="25%" style="padding: 10px; border-right: 1px solid #ddd; border-top: 1px solid #ddd;">' . htmlspecialchars($invoice['order_number']) . '</td>
                <td width="25%" style="padding: 10px; border-right: 1px solid #ddd; border-top: 1px solid #ddd;"><strong>Status:</strong></td>
                <td width="25%" style="padding: 10px; border-top: 1px solid #ddd;">
                    <span style="background-color: ' . $this->getStatusColor($invoice['status']) . '; color: white; padding: 3px 8px; border-radius: 3px; font-size: 10px;">' . 
                    strtoupper($invoice['status']) . '</span>
                </td>
            </tr>
        </table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addCustomerInfo($pdf, $invoice) {
        $html = '
        <table width="100%" style="margin-top: 20px;">
            <tr>
                <td width="50%">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Bill To:</h3>
                    <p style="margin: 0 0 5px 0;"><strong>' . htmlspecialchars($invoice['customer_name']) . '</strong></p>
                    <p style="margin: 0 0 5px 0;">' . nl2br(htmlspecialchars($invoice['customer_address'])) . '</p>';
        
        if (!empty($invoice['customer_phone'])) {
            $html .= '<p style="margin: 0 0 5px 0;">Phone: ' . htmlspecialchars($invoice['customer_phone']) . '</p>';
        }
        
        if (!empty($invoice['customer_email'])) {
            $html .= '<p style="margin: 0 0 5px 0;">Email: ' . htmlspecialchars($invoice['customer_email']) . '</p>';
        }
        
        $html .= '</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addInvoiceItemsTable($pdf, $items, $invoice) {
        $html = '
        <table width="100%" style="margin-top: 20px; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #343a40; color: white;">
                    <th width="50%" style="padding: 10px; text-align: left; border: 1px solid #ddd;">Description</th>
                    <th width="15%" style="padding: 10px; text-align: center; border: 1px solid #ddd;">Quantity</th>
                    <th width="15%" style="padding: 10px; text-align: right; border: 1px solid #ddd;">Unit Price</th>
                    <th width="20%" style="padding: 10px; text-align: right; border: 1px solid #ddd;">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($items as $item) {
            $html .= '
                <tr>
                    <td width="50%" style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['description']) . '</td>
                    <td width="15%" style="padding: 8px; text-align: center; border: 1px solid #ddd;">' . $item['quantity'] . '</td>
                    <td width="15%" style="padding: 8px; text-align: right; border: 1px solid #ddd;">ETB ' . number_format($item['unit_price'], 2) . '</td>
                    <td width="20%" style="padding: 8px; text-align: right; border: 1px solid #ddd;">ETB ' . number_format($item['total_price'], 2) . '</td>
                </tr>';
        }
        
        // Add totals
        $html .= '
                <tr style="background-color: #f8f9fa;">
                    <td width="65%" colspan="2" style="padding: 10px; border: 1px solid #ddd;"><strong>Subtotal</strong></td>
                    <td width="15%" style="padding: 10px; border: 1px solid #ddd;"></td>
                    <td width="20%" style="padding: 10px; text-align: right; border: 1px solid #ddd;"><strong>ETB ' . number_format($invoice['subtotal'], 2) . '</strong></td>
                </tr>';
        
        if ($invoice['tax_amount'] > 0) {
            $html .= '
                <tr style="background-color: #f8f9fa;">
                    <td width="65%" colspan="2" style="padding: 10px; border: 1px solid #ddd;"><strong>Tax</strong></td>
                    <td width="15%" style="padding: 10px; border: 1px solid #ddd;"></td>
                    <td width="20%" style="padding: 10px; text-align: right; border: 1px solid #ddd;"><strong>ETB ' . number_format($invoice['tax_amount'], 2) . '</strong></td>
                </tr>';
        }
        
        $html .= '
                <tr style="background-color: #e9ecef;">
                    <td width="65%" colspan="2" style="padding: 12px; border: 1px solid #ddd;"><strong>TOTAL</strong></td>
                    <td width="15%" style="padding: 12px; border: 1px solid #ddd;"></td>
                    <td width="20%" style="padding: 12px; text-align: right; border: 1px solid #ddd;"><strong style="font-size: 16px;">ETB ' . number_format($invoice['total_amount'], 2) . '</strong></td>
                </tr>
            </tbody>
        </table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addPaymentSummary($pdf, $invoice) {
        $html = '
        <table width="100%" style="margin-top: 20px; border-collapse: collapse;">
            <tr>
                <td width="50%"></td>
                <td width="50%">
                    <table width="100%" style="border: 1px solid #ddd;">
                        <tr style="background-color: #f8f9fa;">
                            <td width="60%" style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Deposit Paid:</strong></td>
                            <td width="40%" style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">ETB ' . number_format($invoice['deposit_paid'], 2) . '</td>
                        </tr>
                        <tr style="background-color: #fff3cd;">
                            <td width="60%" style="padding: 10px;"><strong>Remaining Balance:</strong></td>
                            <td width="40%" style="padding: 10px; text-align: right;"><strong>ETB ' . number_format($invoice['remaining_balance'], 2) . '</strong></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addBankDetails($pdf, $invoice) {
        $html = '
        <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff;">
            <h3 style="color: #333; margin: 0 0 10px 0;">Bank Transfer Details</h3>
            <table width="100%">
                <tr>
                    <td width="30%"><strong>Bank Name:</strong></td>
                    <td width="70%">' . htmlspecialchars($invoice['bank_name']) . '</td>
                </tr>
                <tr>
                    <td width="30%"><strong>Account Name:</strong></td>
                    <td width="70%">' . htmlspecialchars($invoice['bank_account_name']) . '</td>
                </tr>
                <tr>
                    <td width="30%"><strong>Account Number:</strong></td>
                    <td width="70%">' . htmlspecialchars($invoice['bank_account_number']) . '</td>
                </tr>';
        
        if (!empty($invoice['bank_branch'])) {
            $html .= '
                <tr>
                    <td width="30%"><strong>Branch:</strong></td>
                    <td width="70%">' . htmlspecialchars($invoice['bank_branch']) . '</td>
                </tr>';
        }
        
        if (!empty($invoice['swift_code'])) {
            $html .= '
                <tr>
                    <td width="30%"><strong>SWIFT Code:</strong></td>
                    <td width="70%">' . htmlspecialchars($invoice['swift_code']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>
        </div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addSignatureSection($pdf) {
        $html = '
        <table width="100%" style="margin-top: 40px;">
            <tr>
                <td width="50%">
                    <div style="border-top: 1px solid #333; padding-top: 5px; text-align: center;">
                        <strong>Customer Signature</strong>
                    </div>
                </td>
                <td width="50%">
                    <div style="border-top: 1px solid #333; padding-top: 5px; text-align: center;">
                        <strong>Authorized Signature</strong>
                    </div>
                </td>
            </tr>
        </table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function addTermsAndNotes($pdf, $invoice) {
        $html = '
        <div style="margin-top: 30px; padding: 15px; background-color: #e9ecef; border-radius: 5px;">
            <h4 style="color: #333; margin: 0 0 10px 0;">Terms & Conditions</h4>';
        
        if (!empty($invoice['company_notes'])) {
            $html .= '<p style="margin: 0; font-size: 10px; color: #666;">' . nl2br(htmlspecialchars($invoice['company_notes'])) . '</p>';
        } else {
            $html .= '<p style="margin: 0; font-size: 10px; color: #666;">Thank you for your business. Payment is due within 30 days.</p>';
        }
        
        $html .= '</div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    }
    
    private function getStatusColor($status) {
        $colors = [
            'draft' => '#6c757d',
            'sent' => '#007bff',
            'paid' => '#28a745',
            'overdue' => '#dc3545',
            'cancelled' => '#ffc107'
        ];
        return $colors[$status] ?? '#6c757d';
    }
    
    private function getOrderById($orderId) {
        $stmt = $this->db->prepare("
            SELECT o.*, u.id as customer_id, CONCAT(u.first_name, ' ', u.last_name) as customer_name
            FROM furn_orders o
            JOIN furn_users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }
}