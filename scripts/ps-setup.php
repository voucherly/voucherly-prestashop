<?php

// Development helper run by scripts/ps-setup.sh inside the PrestaShop container.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__, 3) . '/config/config.inc.php';

const TEST_PRODUCT_REFERENCE = 'VOUCHERLY-TEST';

switch ($argv[1] ?? '') {
    case 'is-installed':
        exit(Module::isInstalled('voucherly') ? 0 : 1);

    case 'configure':
        // The container cannot send emails, and order validation would log a failure for each one.
        Configuration::updateValue('PS_MAIL_METHOD', 3);

        // The module is mounted live, so templates must be recompiled on every request.
        Configuration::updateValue('PS_SMARTY_FORCE_COMPILE', _PS_SMARTY_FORCE_COMPILE_);
        Configuration::updateValue('PS_SMARTY_CACHE', 0);
        Tools::clearSmartyCache();

        if (!Module::isEnabled('voucherly')) {
            Module::getInstanceByName('voucherly')->enable();
        }

        // On PHP 8.4+ this bundled module logs thousands of deprecations per request, burying the module's own notices.
        if (Module::isEnabled('ps_facebook')) {
            Module::getInstanceByName('ps_facebook')->disable();
        }

        $productId = (int) Product::getIdByReference(TEST_PRODUCT_REFERENCE);
        if (!$productId) {
            $productId = createTestProduct();
        }

        $context = Context::getContext();
        $shop = rtrim($context->link->getBaseLink(), '/');
        echo PHP_EOL;
        echo 'Shop:          ' . $shop . '/' . PHP_EOL;
        echo 'Admin:         ' . $shop . '/admin-dev/  (demo@prestashop.com / prestashop_demo)' . PHP_EOL;
        echo 'Test product:  ' . $context->link->getProductLink($productId) . PHP_EOL;
        exit(0);

    default:
        fwrite(STDERR, 'Usage: php ps-setup.php is-installed|configure' . PHP_EOL);
        exit(1);
}

// No image on purpose: the payment request must cope with products without a picture.
function createTestProduct(): int
{
    $taxRulesGroupId = 0;
    $taxRate = 0.0;
    foreach (TaxRulesGroup::getTaxRulesGroups(true) as $group) {
        if (false !== strpos($group['name'], '10%')) {
            $taxRulesGroupId = (int) $group['id_tax_rules_group'];
            $taxRate = 10.0;
            break;
        }
    }

    $product = new Product();
    $product->reference = TEST_PRODUCT_REFERENCE;
    foreach (Language::getLanguages(false) as $language) {
        $product->name[$language['id_lang']] = 'Pranzo di test';
        $product->link_rewrite[$language['id_lang']] = 'pranzo-di-test';
    }
    $product->id_tax_rules_group = $taxRulesGroupId;
    $product->price = round(12.50 / (1 + $taxRate / 100), 6);
    $product->id_category_default = (int) Configuration::get('PS_HOME_CATEGORY');
    $product->active = true;
    $product->add();
    $product->addToCategories([$product->id_category_default]);
    StockAvailable::setQuantity($product->id, 0, 1000);

    return (int) $product->id;
}
