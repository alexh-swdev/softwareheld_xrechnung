<?php

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\codelists\ZugferdPaymentMeans;

class SoftwareHeld_Xrechnung_Model_Xrechnung
{
    private const string QTY_UNIT_CODE_PIECE = "H87";

    // See: https://leitweg-id.de
    private const string DEFAULT_SAMPLE_LEITWEG_ID = "N/A";// testing: "04011000-1234512345-06";

    /**
     * Collect the invoices for the given order. Create one, if none exists.
     *
     * @param int $orderId
     * @return SoftwareHeld_Xrechnung_Model_Result
     */
    public function getInvoices(int $orderId): SoftwareHeld_Xrechnung_Model_Result
    {
        /** @var SoftwareHeld_Xrechnung_Model_Result $res */
        $res = Mage::getModel("xrechnung/result");

        $invoices = Mage::getResourceModel('sales/order_invoice_collection')
            ->addAttributeToSelect('*')
            ->setOrderFilter($orderId)
            ->load();

        $orderInfoForException = $orderId;
        try {
            if ($invoices->getSize() == 0) {
                $order = $this->getOrder($orderId);

                if ($order == null) {
                    $res->addMessage("Order " . $orderInfoForException . " not found.");
                } else {

                    $orderInfoForException = $order->getIncrementId();

                    $generatedInvoice = $this->generateInvoices($order);
                    if ($generatedInvoice == null) {

                        $res->addMessage("Order " . $orderInfoForException . " failed to generate invoice.");
                    } else {

                        $this->processInvoice($generatedInvoice);

                        $invoices->addItem($generatedInvoice);
                        $invoices->save();

                        $invoices = Mage::getResourceModel('sales/order_invoice_collection')
                            ->addAttributeToSelect('*')
                            ->setOrderFilter($orderId)
                            ->load();
                    }
                }
            }
        } catch (Exception $ex) {
            $res->addMessage($orderInfoForException . "failed to generate invoice automatically with execption: " . $ex->getMessage());
            Mage::logException($ex);
        }

        if ($invoices->getSize() > 0) {
            foreach ($invoices as $invoice) {
                $res->addInvoice($invoice);
            }
        } else {
            $res->addMessage("No invoices found.");
        }


        $res->setSucess(empty($res->getMessage()) && !empty($res->getInvoices()));
        return $res;
    }

    /**
     * Create the XRechnung xml for the given invoice.
     * At time of implementation, the result valdiates green against this validator:
     * https://erechnungsvalidator.service-bw.de
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return SoftwareHeld_Xrechnung_Model_Result
     * @throws DateMalformedStringException
     */
    public function createXml(Mage_Sales_Model_Order_Invoice $invoice): SoftwareHeld_Xrechnung_Model_Result
    {
//        return $this->getSample();
        if ($invoice->getStoreId()) {
            Mage::app()->getLocale()->emulate($invoice->getStoreId());
        }

        $order = $invoice->getOrder();
        $storeId = $order->getStoreId();

        /** @var SoftwareHeld_Xrechnung_Model_Result $res */
        $res = Mage::getModel("xrechnung/result");

        $document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
        $document = $document->setDocumentInformation($invoice->getIncrementId(), "380",
            new DateTime($invoice->getCreatedAt()),
            "EUR")
            ->setDocumentBuyerOrderReferencedDocument($order->getRealOrderId())// "shall" not according to validator: , new DateTime($order->getCreatedAt()))
            ->addDocumentNote('Rechnungsdatum entspricht dem Lieferdatum');

        $document = $this->addASeller($document, $storeId);
        $document = $this->addBuyer($document, $storeId, $order);

        $document = $this->addOrderItems($document, $storeId, $order);
        $document = $this->addTaxInfo($document, $storeId, $order);

        $res->setXmlInvoice($document->getContent());
        $res->setSucess(true);

        return $res;
    }

    private function addASeller(ZugferdDocumentBuilder $document, int $storeId): ZugferdDocumentBuilder
    {
        $name = Mage::getStoreConfig("general/store_information/name", $storeId);
        $vat = Mage::getStoreConfig("general/store_information/merchant_vat_number", $storeId);
        $tax = Mage::getStoreConfig("general/imprint/tax_number", $storeId);

        $street = Mage::getStoreConfig("general/imprint/street", $storeId);
        $zip = Mage::getStoreConfig("general/imprint/zip", $storeId);
        $city = Mage::getStoreConfig("general/imprint/city", $storeId);

        $phone = Mage::getStoreConfig("general/store_information/phone", $storeId);
        $fax = Mage::getStoreConfig("general/imprint/fax", $storeId);
        $contactName = Mage::getStoreConfig("trans_email/ident_general/name", $storeId);
        $email = Mage::getStoreConfig("trans_email/ident_general/email", $storeId);

        $court = Mage::getStoreConfig("general/imprint/court", $storeId);
        $taxOffice = Mage::getStoreConfig("general/imprint/financial_office", $storeId);

        $legal = $name . PHP_EOL . $street . PHP_EOL . $zip . " " . $city . PHP_EOL . "Deutschland";
        $legal .= PHP_EOL . "Inhaber: " . $contactName . PHP_EOL . "Zuständiges Gericht: " . $court . PHP_EOL . "Zuständiges Finanzamt: " . $taxOffice;


        $document = $document->addDocumentNote($legal . PHP_EOL . PHP_EOL, null, 'REG')
            ->setDocumentSeller($name, null)
            //->addDocumentSellerGlobalId("4000001123452", "0088")
            ->addDocumentSellerTaxRegistration("FC", $tax)
            ->addDocumentSellerTaxRegistration("VA", "DE" . $vat)
            ->setDocumentSellerAddress($street, "", "", $zip, $city, "DE")
            ->setDocumentSellerContact($contactName, null, $phone, $fax, $email)
            ->setDocumentSellerCommunication("EM", $email);

        return $document;
    }

    private function addBuyer(ZugferdDocumentBuilder $document,
        int $storeId,
        Mage_Sales_Model_Order $order): ZugferdDocumentBuilder
    {
        // Shipping Address
        $shippingAddress = $order->getShippingAddress();
        $document = $document->setDocumentShipTo($shippingAddress->getName())
            ->setDocumentShipToAddress($shippingAddress->getStreetFull(), "", "", $shippingAddress->getPostcode(),
                $shippingAddress->getCity(), $shippingAddress->getCountryId());

        // Payment
        $paymentInfo = $order->getPayment();
        $methodInfo = null;
        $method = $paymentInfo->getMethod();
        $swift = null;
        $iban = null;
        $accountOwner = null;
        $typeCode = ZugferdPaymentMeans::UNTDID_4461_42; // bank transfer
        $iban = Mage::getStoreConfig("general/imprint/iban", $storeId);
        switch ($method) {
            case "iways_paypalinstalments":
            case "iways_paypalplus_payment":
                $typeCode = ZugferdPaymentMeans::UNTDID_4461_30; // transfer
                $methodInfo = "Paypal, transaction-id: " . $paymentInfo->getLastTransId();
                break;

            case "bankpayment":
                $methodInfo = "Bank: " . Mage::getStoreConfig("general/imprint/bank_name", $storeId);
                $swift = Mage::getStoreConfig("general/imprint/swift", $storeId);
                $accountOwner = Mage::getStoreConfig("general/imprint/bank_account_owner", $storeId);
                break;

            default:
                Mage::log(__METHOD__ . " method '" . $method . "' not implemented", Zend_Log::ERR);
                break;
        }

        $document->addDocumentPaymentMean($typeCode, $methodInfo,
            null, null, null, null,
            $iban, $accountOwner, null, $swift);

        // Billing Address
        $billingAddress = $order->getBillingAddress();

        $buyerEmail = $billingAddress->getEmail();
        if (empty($buyerEmail)) {
            $buyerEmail = $order->getCustomerEmail();
        }

        $leitweg = $billingAddress->getLeitwegId();
        if (empty($leitweg)) {
            $leitweg = self::DEFAULT_SAMPLE_LEITWEG_ID;
        }

        $document = $document->setDocumentBuyer($order->getCustomerName(), $order->getCustomerId())
            ->setDocumentBuyerReference($leitweg)
            ->setDocumentBuyerAddress($billingAddress->getStreetFull(), "", "", $billingAddress->getPostcode(),
                $billingAddress->getCity(), $billingAddress->getCountryId())
            ->setDocumentBuyerCommunication("EM", $buyerEmail);

        if ($billingAddress->getVatIsValid()) {
            $vatId = trim($billingAddress->getVatId());
            if (!empty($vatId)) {
                $document = $document->addDocumentBuyerTaxRegistration("VA", $vatId);
            }
        }

        return $document;
    }

    private function getOrderTaxRate(Mage_Sales_Model_Order $order): float
    {
        foreach ($order->getFullTaxInfo() as $taxInfo) {
            return $taxInfo["percent"];
        }

        return 0.0;
    }

    private function addOrderItems(ZugferdDocumentBuilder $document,
        int $storeId,
        Mage_Sales_Model_Order $order): ZugferdDocumentBuilder
    {
        $row = 1;
        $items = $order->getAllItems();
        foreach ($items as $item) {
            $pi = $item->getParentItem();
            if ($pi) {
                continue;
            }

            $document = $document->addNewPosition(($row++) . "")
                ->setDocumentPositionProductDetails($item->getName(), "", $item->getSku())
                ->setDocumentPositionNetPrice($item->getPrice())
                ->setDocumentPositionQuantity($item->getQtyOrdered(), self::QTY_UNIT_CODE_PIECE)
                ->addDocumentPositionTax('S', 'VAT',
                    $item->getTaxPercent())// "should" not according to validator, $item->getTaxAmount())
                ->setDocumentPositionLineSummation($item->getRowTotal());
        }


        $document = $document->addNewPosition(($row++) . "")
            ->setDocumentPositionProductDetails("Shipping", "", "")
            ->setDocumentPositionNetPrice($order->getShippingAmount())
            ->setDocumentPositionQuantity(1, self::QTY_UNIT_CODE_PIECE)
            ->addDocumentPositionTax('S', 'VAT',
                $this->getOrderTaxRate($order)) // "should" not according to validator, $order->getShippingTaxAmount())
            ->setDocumentPositionLineSummation($order->getShippingAmount());

        $surchargeData = $this->getSurcharge($order);
        if (!empty($surchargeData["amt"])) {
            $document = $document->addNewPosition(($row++) . "")
                ->setDocumentPositionProductDetails($surchargeData["desc"], "", "")
                ->setDocumentPositionNetPrice($surchargeData["amt"])
                ->setDocumentPositionQuantity(1, self::QTY_UNIT_CODE_PIECE)
                ->addDocumentPositionTax('S', 'VAT', $this->getOrderTaxRate($order))
                ->setDocumentPositionLineSummation($surchargeData["amt"]);
        }

        return $document;
    }

    private function addTaxInfo(ZugferdDocumentBuilder $document,
        int $storeId,
        Mage_Sales_Model_Order $order): ZugferdDocumentBuilder
    {
        // Exemption codes:
        //https://www.xrepository.de/details/urn:xoev-de:kosit:codeliste:vatex_1

        $gross = $order->getTotalInvoiced();
        $tax = $order->getTaxAmount();
        $net = $order->getSubtotal() + $order->getShippingAmount();
        $surchargeData = $this->getSurcharge($order);
        if (!empty($surchargeData["amt"])) {
            $net += $surchargeData["amt"];
        }

        $document = $document->addDocumentTax("S", "VAT", $net, $tax, $this->getOrderTaxRate($order))
            ->setDocumentSummation($gross, $order->getTotalDue(), $net, 0.0, 0.0, $net, $tax, null,
                $order->getTotalPaid());

        return $document;
    }

    private function getSurcharge(Mage_Sales_Model_Order $order): array
    {
        $surchargeData = ["amt" => 0.0, "desc" => ""];

        if (!Mage::helper('core')->isModuleEnabled('Fooman_Surcharge')) {
            return $surchargeData;
        }

        $surcharge = floatval($order->getFoomanSurchargeAmount() ?? 0);
        if (empty($surcharge)) {
            return $surchargeData;
        }

        $surchargeData["amt"] = $surcharge;
        $surchargeData["desc"] = $order->getFoomanSurchargeDescription();
        return $surchargeData;
    }

    private function getOrder(int $orderId): ?Mage_Sales_Model_Order
    {
        $order = Mage::getModel('sales/order')->load($orderId);
        return $order;
    }

    private function generateInvoices(Mage_Sales_Model_Order $order): ?Mage_Sales_Model_Order_Invoice
    {
        $orderItemsMap = [];
        foreach ($order->getAllItems() as $orderItem) {
            $orderItemsMap[$orderItem->getId()] = $orderItem->getQtyOrdered();
        }

        /** @var Mage_Sales_Model_Service_Order $orderServiceModel */
        $orderServiceModel = Mage::getModel('sales/service_order', $order);
        return $orderServiceModel->prepareInvoice($orderItemsMap);
    }

    private function processInvoice(Mage_Sales_Model_Order_Invoice $invoice): Mage_Sales_Model_Order_Invoice
    {
        $invoice->register();
        $invoice->getOrder()->setCustomerNoteNotify(false);
        $invoice->getOrder()->setIsInProcess(true);

        $tx = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $tx->save();

        return $invoice;
    }

    /**
     * Creates a XRechnung xml with the sample from the lib vendor
     * @return SoftwareHeld_Xrechnung_Model_Result
     */
    private function getSample(): SoftwareHeld_Xrechnung_Model_Result
    {
        /** @var SoftwareHeld_Xrechnung_Model_Result $res */
        $res = Mage::getModel("xrechnung/result");

        $document = ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
        $document
            ->setDocumentInformation("471102", "380", \DateTime::createFromFormat("Ymd", "20180305"), "EUR")
            ->addDocumentNote('Rechnung gemäß Bestellung vom 01.03.2018.')
            ->addDocumentNote('Lieferant GmbH' . PHP_EOL . 'Lieferantenstraße 20' . PHP_EOL . '80333 München' . PHP_EOL . 'Deutschland' . PHP_EOL . 'Geschäftsführer: Hans Muster' . PHP_EOL . 'Handelsregisternummer: H A 123' . PHP_EOL . PHP_EOL,
                null, 'REG')
            ->setDocumentSupplyChainEvent(\DateTime::createFromFormat('Ymd', '20180305'))
            ->addDocumentPaymentMean(ZugferdPaymentMeans::UNTDID_4461_58, null, null, null, null, null,
                "DE12500105170648489890", null, null, null)
            ->setDocumentSeller("Lieferant GmbH", "549910")
            ->addDocumentSellerGlobalId("4000001123452", "0088")
            ->addDocumentSellerTaxRegistration("FC", "201/113/40209")
            ->addDocumentSellerTaxRegistration("VA", "DE123456789")
            ->setDocumentSellerAddress("Lieferantenstraße 20", "", "", "80333", "München", "DE")
            ->setDocumentSellerContact("Heinz Mükker", "Buchhaltung", "+49-111-2222222", "+49-111-3333333",
                "info@lieferant.de")
            ->setDocumentSellerCommunication("EM", "info@seller.org")
            ->setDocumentBuyer("Kunden AG Mitte", "GE2020211")
            ->setDocumentBuyerReference("34676-342323")
            ->setDocumentBuyerAddress("Kundenstraße 15", "", "", "69876", "Frankfurt", "DE")
            ->setDocumentBuyerCommunication("EM", "buyer@buyer.com")
            ->addDocumentTax("S", "VAT", 275.0, 19.25, 7.0)
            ->addDocumentTax("S", "VAT", 198.0, 37.62, 19.0)
            ->setDocumentSummation(529.87, 529.87, 473.00, 0.0, 0.0, 473.00, 56.87, null, 0.0)
            ->addDocumentPaymentTermXRechnung("Zahlungsbedingungen", [30, 28, 14], [0, 14, 7], [529.87, 529.87, 529.87])
            ->addNewPosition("1")
            ->setDocumentPositionNote("Bemerkung zu Zeile 1")
            ->setDocumentPositionProductDetails("Trennblätter A4", "", "TB100A4")
            ->setDocumentPositionNetPrice(9.9000)
            ->setDocumentPositionQuantity(20, "H87")
            ->addDocumentPositionTax('S', 'VAT', 19)
            ->setDocumentPositionLineSummation(198.0)
            ->addNewPosition("2")
            ->setDocumentPositionNote("Bemerkung zu Zeile 2")
            ->setDocumentPositionProductDetails("Joghurt Banane", "", "ARNR2", null, "0160", "4000050986428")
            ->SetDocumentPositionNetPrice(5.5000)
            ->SetDocumentPositionQuantity(50, "H87")
            ->AddDocumentPositionTax('S', 'VAT', 7)
            ->SetDocumentPositionLineSummation(275.0)
            ->writeFile(dirname(__FILE__) . "/factur-x.xml");

        $res->setXmlInvoice($document->getContent());
        $res->setSucess(true);

        return $res;
    }
}