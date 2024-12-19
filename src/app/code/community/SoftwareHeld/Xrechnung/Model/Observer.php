<?php
class SoftwareHeld_Xrechnung_Model_Observer
{
    public function addMassActionForOrdersGrid($observer) {
        $block = $observer->getEvent()->getBlock();

        // order overview
        if(($block instanceof Mage_Adminhtml_Block_Widget_Grid_Massaction || $block instanceof Enterprise_SalesArchive_Block_Adminhtml_Sales_Order_Grid_Massaction)
            && strstr( $block->getRequest()->getControllerName(), 'sales_order'))
        {
            $block->addItem('swh_xrechnung', [
                'label' => Mage::helper('sales')->__('Create XRechnung'),
                'url' => Mage::getModel('adminhtml/url')->getUrl('*/sales_order_xrechnung/create'),
            ]);
        }

        // order detail
        if(($block instanceof Mage_Adminhtml_Block_Sales_Order_View)) {
            $block->addButton("swh_xrechnung_order", [
               "label" => Mage::helper('sales')->__('Create XRechnung'),
                "class" => "save",
                "onclick" => Mage::helper("core/js")->getSetLocationJs($block->getUrl("*/sales_order_xrechnung/create"))
            ]);
        }
    }

    private function log($message, $level = Zend_Log::INFO)
    {
        Mage::log($message, $level, sprintf("%s.log", __CLASS__));
    }
}
