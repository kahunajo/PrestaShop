<?php
include(dirname(__FILE__).'/../../config/config.inc.php');
require_once(_PS_MODULE_DIR_.'/buyster/buyster.php');
require_once(_PS_MODULE_DIR_.'/buyster/classes/BuysterWebService.php');
require_once(_PS_MODULE_DIR_.'/buyster/classes/BuysterOperation.php');

global $smarty, $cookie;

/*$iso_code = strtolower(Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT')));
echo $iso_code;*/
$cookie->id_lang = '2';

$orderId = (int)$_GET['id_order'];

$buyster = new Buyster(); 

$smarty->assign('content', $buyster->getContentAdminOrder($orderId));

$smarty->display('tpl/adminOrder.tpl');
?>