<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/cashondelivery.php');

$confirm = Tools::getValue('confirm');

/* Validate order */
if ($confirm)
{
	$customer = new Customer(intval($cart->id_customer));
	$cashOnDelivery = new CashOnDelivery();
	$total = $cart->getOrderTotal(true, 3);
	$cashOnDelivery->validateOrder(intval($cart->id), _PS_OS_PREPARATION_, $total, $cashOnDelivery->displayName);
	$order = new Order(intval($cashOnDelivery->currentOrder));
	Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.intval($cart->id).'&id_module='.intval($cashOnDelivery->id).'&id_order='.intval($cashOnDelivery->currentOrder));
}
else
{
	/* or ask for confirmation */ 
	$smarty->assign(array(
		'total' => $cart->getOrderTotal(true, 3),
		'this_path_ssl' => Tools::getHttpHost(true, true).__PS_BASE_URI__.'modules/cashondelivery/'
	));

	$smarty->assign('this_path', __PS_BASE_URI__.'modules/cashondelivery/');
	echo Module::display(__FILE__, 'validation.tpl');
}

include(dirname(__FILE__).'/../../footer.php');