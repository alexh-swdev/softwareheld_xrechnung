<?php
$attributeCode = 'leitweg_id';
$attributeDef = [
    'type' => 'varchar',
    'label' => 'Leitweg ID',
    'input' => 'text',
    'position' => 120,
    'required' => false,
    'is_system' => 0,
];

$customerSetupInstaller = Mage::getResourceModel('customer/setup', 'sales_setup');
$customerSetupInstaller->addAttribute('customer_address', $attributeCode, $attributeDef);

$salesSetupInstaller = Mage::getResourceModel('sales/setup', 'sales_setup');
$salesSetupInstaller->addAttribute('quote_address', $attributeCode, $attributeDef);
$salesSetupInstaller->addAttribute('order_address', $attributeCode, $attributeDef);

$attribute = Mage::getSingleton('eav/config')->getAttribute('customer_address', $attributeCode);
$attribute->setData('is_user_defined', 0)
    ->setData('used_in_forms', [
        'adminhtml_customer_address',
        'adminhtml_customer',
        'adminhtml_checkout',

        'customer_account_create',
        'customer_account_edit',
        'customer_address_edit',
        'customer_register_address',

        'checkout_address_edit',
        'checkout_register_address',
        'checkout_register',
    ])->save();