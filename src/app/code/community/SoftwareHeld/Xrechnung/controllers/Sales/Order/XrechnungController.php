<?php

class SoftwareHeld_Xrechnung_Sales_Order_XrechnungController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return SoftwareHeld_Xrechnung_Sales_Order_XrechnungController
     */
    public function createAction(): static
    {
        /** @var SoftwareHeld_Xrechnung_Model_Xrechnung $xrechnung */
        $xrechnung = Mage::getModel("xrechnung/xrechnung");

        $zipFile = Mage::getBaseDir("var") . DS . "export" . DS . "xrechnungen3_0.zip";
        $zip = new ZipArchive();
        $zipFp = $zip->open($zipFile, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        if (!$zipFp || is_numeric($zipFp)) {
            $this->_getSession()->addError(
                $this->__(sprintf("Failed to create a zip file '%s' for the XRechnungen (Error: %d)", $zipFile, $zipFp))
            );

            return $this->_redirect('*/sales_order');
        }

        $zipHasContent = false;
        $orderIds = $this->getRequest()->getPost('order_ids', []);
        if (empty($orderIds)) {
            // via order detail view?
            $orderId = $this->getRequest()->getParam('order_id', 0);
            if (!empty($orderId)) {
                $orderIds[] = $orderId;
            }
        }

        foreach ($orderIds as $orderId) {
            $invResult = $xrechnung->getInvoices($orderId);

            if (!$invResult->isSucess()) {
                foreach ($invResult->getMessages() as $invError) {
                    $this->_getSession()->addError($invError);
                }

                return $this->_redirect('*/sales_order');
            }

            foreach ($invResult->getInvoices() as $invoice) {
                $xmlResult = $xrechnung->createXml($invoice);

                if (!$xmlResult->isSucess()) {
                    foreach ($xmlResult->getMessage() as $xmlError) {
                        $this->_getSession()->addError($xmlError);
                    }

                    return $this->_redirect('*/sales_order');
                }

                $xmlInvoice = $xmlResult->getXmlInvoice();
                if (!empty($xmlInvoice)) {
                    $zip->addFromString(sprintf("%s_xrechnung3.xml", $invoice->getIncrementId()), $xmlInvoice);
                    $zipHasContent = true;
                }
            }
        }

        if ($zipHasContent) {
            $zip->close();
            return $this->_prepareDownloadResponse(sprintf("%s_xrechnung3_0.zip", (new DateTime())->format("Y-m-d")),
                ["type" => "filename", "value" => $zipFile], "application/octet-stream");
        }

        $this->_getSession()->addError("No invoices");
        return $this->_redirect('*/sales_order');
    }
}