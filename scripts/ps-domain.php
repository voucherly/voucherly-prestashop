<?php

// Development helper run by scripts/ps-tunnel.sh inside the PrestaShop container: php ps-domain.php <host> <http|https>.
if (PHP_SAPI !== 'cli' || 3 !== $argc) {
    exit(1);
}

require dirname(__DIR__, 3) . '/config/config.inc.php';

$domain = $argv[1];
$secure = 'https' === $argv[2];

$shopUrl = new ShopUrl((int) Db::getInstance()->getValue(
    'SELECT id_shop_url FROM `' . _DB_PREFIX_ . 'shop_url` WHERE main = 1 AND id_shop = ' . (int) Configuration::get('PS_SHOP_DEFAULT')
));
$shopUrl->domain = $domain;
$shopUrl->domain_ssl = $domain;
$shopUrl->save();

Configuration::updateValue('PS_SHOP_DOMAIN', $domain);
Configuration::updateValue('PS_SHOP_DOMAIN_SSL', $domain);
Configuration::updateValue('PS_SSL_ENABLED', $secure);
Configuration::updateValue('PS_SSL_ENABLED_EVERYWHERE', $secure);

Tools::generateHtaccess();
Tools::clearSmartyCache();
Media::clearCache();

// The shop is publicly reachable while the tunnel is up, so the well-known default password must not stay valid.
$password = $secure ? rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=') : 'prestashop_demo';
$employee = new Employee((int) Db::getInstance()->getValue(
    'SELECT id_employee FROM `' . _DB_PREFIX_ . 'employee` WHERE email = \'demo@prestashop.com\''
));
$employee->passwd = password_hash($password, PASSWORD_BCRYPT);
$employee->update();

$shop = ($secure ? 'https://' : 'http://') . $domain;
$productId = (int) Product::getIdByReference('VOUCHERLY-TEST');
echo PHP_EOL;
echo 'Shop:          ' . $shop . '/' . PHP_EOL;
echo 'Admin:         ' . $shop . '/admin-dev/  (demo@prestashop.com / ' . $password . ')' . PHP_EOL;
// The context link still carries the previous domain, and dev mode answers non-canonical product URLs with a debug page.
$link = Context::getContext()->link;
echo 'Test product:  ' . str_replace($link->getBaseLink(), $shop . '/', $link->getProductLink($productId)) . PHP_EOL;
