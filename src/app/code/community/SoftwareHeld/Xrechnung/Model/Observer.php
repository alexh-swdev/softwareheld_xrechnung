<?php

class SoftwareHeld_Xrechnung_Model_Observer
{
    /**
     * Add XRechnung buttons to the mass actions and the order detail view
     *
     * @param $observer
     * @return void
     * @throws Exception
     */
    public function addMassActionForOrdersGrid($observer): void
    {
        $block = $observer->getEvent()->getBlock();

        // order overview
        if (($block instanceof Mage_Adminhtml_Block_Widget_Grid_Massaction || $block instanceof Enterprise_SalesArchive_Block_Adminhtml_Sales_Order_Grid_Massaction)
            && strstr($block->getRequest()->getControllerName(), 'sales_order')) {
            $block->addItem('swh_xrechnung', [
                'label' => Mage::helper('sales')->__('Create XRechnung'),
                'url' => Mage::getModel('adminhtml/url')->getUrl('*/sales_order_xrechnung/create'),
            ])->addItem('swh_xgutschrift', [
                'label' => Mage::helper('sales')->__('Create XGutschrift'),
                'url' => Mage::getModel('adminhtml/url')->getUrl('*/sales_order_xrechnung/createCredit'),
            ]);
        }

        // order detail
        if (($block instanceof Mage_Adminhtml_Block_Sales_Order_View)) {
            $block->addButton("swh_xrechnung_order", [
                "label" => Mage::helper('sales')->__('Create XRechnung'),
                "class" => "save",
                "onclick" => Mage::helper("core/js")->getSetLocationJs($block->getUrl("*/sales_order_xrechnung/create"))
            ])->addButton("swh_xgutschrift_order", [
                "label" => Mage::helper('sales')->__('Create XGutschrift'),
                "class" => "save",
                "onclick" => Mage::helper("core/js")->getSetLocationJs($block->getUrl("*/sales_order_xrechnung/createCredit"))
            ]);
        }

        // order detail
        if (($block instanceof Mage_Adminhtml_Block_Sales_Order_Creditmemo_View)) {
            $creditMemoId = $block->getRequest()->getParam("creditmemo_id");
            $block->addButton("swh_xgutschrift_order", [
                "label" => Mage::helper('sales')->__('Create XGutschrift'),
                "class" => "save",
                "onclick" => Mage::helper("core/js")->getSetLocationJs($block->getUrl("*/sales_order_xrechnung/createCredit", ["creditmemo_id" => $creditMemoId]))
            ]);
        }
    }

    /**
     * Patch the quote address in the checkout process with the Leitweg ID from the addressbook address
     *
     * @param $observer
     * @return void
     */
    public function salesQuoteAddressCollectionLoadAfter($observer): void
    {
        $evt = $observer->getEvent();
        $quoteAddresses = $evt->getQuoteAddressCollection();
        foreach ($quoteAddresses as $quoteAddress) {
            switch ($quoteAddress->getAddressType()) {
                case "billing":
                    break;

                default:
                    continue 2;
            }

            $customerAdressId = $quoteAddress->getCustomerAddressId();
            // Note: Guest orders don't have an addressbook address
            if ($customerAdressId != null && $customerAdressId > 0) {
                $customerAddress = Mage::getModel('customer/address')->load($customerAdressId);
                $quoteAddress->setLeitwegId($customerAddress->getLeitwegId());
            }
        }
    }

    /**
     * Transfer the Leitweg ID from the quote address to the order address
     *
     * @param $observer
     * @return void
     */
    public function salesConvertQuoteAddressToOrderAddress($observer): void
    {
        $evt = $observer->getEvent();
        $quoteAddress = $evt->getAddress();
        switch ($quoteAddress->getAddressType()) {
            case "billing":
                break;

            default:
                return;
        }

        $orderAddress = $evt->getOrderAddress();
        $orderAddress->setLeitwegId($quoteAddress->getLeitwegId());
    }
}
