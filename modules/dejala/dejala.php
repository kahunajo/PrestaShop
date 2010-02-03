<?php
	require_once(_PS_MODULE_DIR_ . "dejala/dejalaconfig.php");
	require_once(_PS_MODULE_DIR_. "dejala/dejalautils.php");
	require_once(_PS_MODULE_DIR_. "dejala/dejalacarrierutils.php");
	require_once(_PS_MODULE_DIR_. "dejala/dejalacart.php");
	require_once(_PS_MODULE_DIR_. "dejala/calendarutils.php");


/**
 * This module enables the interractions with dejala.fr carrier services
**/
class Dejala extends Module
{
	const INSTALL_SQL_FILE = 'install.sql';
	public $DEJALA_DEBUG = FALSE;
	public $dejalaConfig;

	public function __construct()
	{
		$this->name = 'dejala';
		$this->tab = 'Tools';
		$this->version = 1.2;
		$this->internal_version = '1.2.3';
		// Iso code of countries where the module can be used, if none module available for all countries
		$this->limited_countries = array('fr');

		parent::__construct();

		if (true !== extension_loaded('curl')) {
			$this->warning = $this->l('this module requires php extension cURL to function properly. Please install the php extension "cURL" first');
		}

		// load configuration
		$this->dejalaConfig = new DejalaConfig();
		$this->dejalaConfig->loadConfig();

	 	// The parent construct is required for translations
		$this->page = basename(__FILE__, '.php');
	 	$this->displayName = $this->l('Dejala.fr : le transport par coursier');
		$this->description = $this->l('Envoie les demandes de livraisons vers dejala.fr');
	}


	/**
		* install dejala module
	*/
  public function install()
  {
  	if (!file_exists(dirname(__FILE__).'/'.self::INSTALL_SQL_FILE))
			return (false);
		else if (!$sql = file_get_contents(dirname(__FILE__).'/'.self::INSTALL_SQL_FILE))
			return (false);
		$sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
		$sql = preg_split("/;\s*[\r\n]+/",$sql);
		foreach ($sql as $query)
		{
			if (!empty($query)) {
				if (!Db::getInstance()->Execute(trim($query)))
					return (false);
				}
			}
    if (parent::install() == false)
    	return (false);

    if ($this->registerHook('updateOrderStatus') == false
				OR $this->registerHook('extraCarrier') == false
				OR $this->registerHook('cart') == false)
			return (false);

		$this->dejalaConfig = new DejalaConfig();
  	if (!$this->dejalaConfig->saveConfig())
  		return (false);

  	return (true);
  }

	public function uninstall()
	{
		$this->dejalaConfig->uninstall();
		parent::uninstall();
	}


	/**
	 * Data validation for module configuration
	 **/
	public function _postValidation()
	{
		$errors = array();

		$method = Tools::getValue('method');
		if ($method == 'signin') {
			if (empty($_POST['login']))
				$errors[] = $this->l('login is required.');
			if (empty($_POST['password']))
				$errors[] = $this->l('password is required.');
			if (empty($_POST['country']))
				$errors[] = $this->l('country is required.');	
		}
		else if ($method == 'register')
		{
			if (empty($_POST['login']))
				$errors[] = $this->l('login is required.');
			if (!Validate::isEmail($_POST['login']))
				$errors[] = $this->l('login must be a valid e-mail address.');
			if (empty($_POST['password']))
				$errors[] = $this->l('password is required.');
			if (empty($_POST['store_name']))
				$errors[] = $this->l('Shop name is required.');
			if (empty($_POST['country']))
				$errors[] = $this->l('country is required.');
		}
		else if ($method == 'products') {
			$products = array();
			$djlUtil = new DejalaUtils();
			$responseArray = $djlUtil->getStoreProducts($this->dejalaConfig, $products);
			if ('200' != $responseArray['status'])
				$products = array();
			foreach ($_POST as $key=>$value)
			{
				if (0 === strpos($key, 'margin_'))
				{
					$this->mylog( "key=" . substr($key, 7) );
					$productID = intval(substr($key, 7));
					if ( is_null($_POST[$key]) || (0 == strlen($_POST[$key])) )
						$_POST[$key] = 0;
					$_POST[$key] = str_replace(',', '.', $_POST[$key]);
					if (!Validate::isFloat($_POST[$key]))
					{
						$errors[] = $value . ' ' . $this->l('is not a valid margin.');
					}
					$margin = floatval($_POST[$key]);
					foreach ($products as $l_product){
						if ($l_product['id'] == $productID)
						{
							$product = $l_product;
							break;
						}
					}
					if ($product) {
						$vat_factor = (1+ ($product['vat'] / 100));
						$public_price = round($product['price']*$vat_factor, 2);
						$public_price = round($public_price + $margin, 2);
						if ($public_price < 0) {
							$errors[] = $value . ' ' . $this->l('is not a valid margin.');
						}
					}
				}
			}
		}
		return ($errors);
	}

	/**
	 * Module configuration request processing
	 **/
	public function _postProcess()
	{
		global $smarty;

		$errors = array();
		$method = Tools::getValue('method');
		if ($method == 'signin')
		{
			$djlUtil = new DejalaUtils();
			$this->dejalaConfig->mode = 'TEST';
			$this->dejalaConfig->login = Tools::getValue('login');
			$this->dejalaConfig->password = Tools::getValue('password');
			$this->dejalaConfig->country = Tools::getValue('country');
			$this->dejalaConfig->serviceURL = str_replace('.fr', '.'.$this->dejalaConfig->country, $this->dejalaConfig->serviceURL);
			$this->dejalaConfig->sandboxServiceURL = str_replace('.fr', '.'.$this->dejalaConfig->country, $this->dejalaConfig->sandboxServiceURL);
			$storeAttr = array();
			$response = $djlUtil->ping($this->dejalaConfig, 'TEST');
			if (200 == $response['status'])
			{
				$this->dejalaConfig->saveConfig();
			}
			else {
				$this->dejalaConfig->login = null;
				$this->dejalaConfig->password = null;
				$errors[] = $this->l('Impossible to process the action') . '(' . $response['status'] . ')';
			}
		}
		else if ($method == 'register')
		{
			$djlUtil = new DejalaUtils();
			$this->dejalaConfig->mode = 'TEST';
			$this->dejalaConfig->login = Tools::getValue('login');
			$this->dejalaConfig->password = Tools::getValue('password');
			$this->dejalaConfig->country = Tools::getValue('country');
			$this->dejalaConfig->serviceURL = str_replace('.fr', '.'.$this->dejalaConfig->country, $this->dejalaConfig->serviceURL);
			$this->dejalaConfig->sandboxServiceURL = str_replace('.fr', '.'.$this->dejalaConfig->country, $this->dejalaConfig->sandboxServiceURL);
			$response = $djlUtil->createInstantStore($this->dejalaConfig, Tools::getValue('store_name'));
			if ($response['status'] == 201)
			{
				$this->dejalaConfig->saveConfig();
			}
			else
			{
				if (409 == $response['status']) {
					$errors[] = $this->l('Please choose another login');
				}
				else
					$errors[] = $this->l('Impossible to process the action') . '(' . $response['status'] . ')';
				$this->dejalaConfig->loadConfig();
			}
		}
		else if ($method == 'location')
		{
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->setStoreLocation($this->dejalaConfig, $_POST);
			if ($response['status'] != 200)
				$errors[] = $this->l('An error occured while updating location');
		}
		else if ($method == 'contact')
		{
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->setStoreContacts($this->dejalaConfig, $_POST);
			if ($response['status'] != 200)
				$errors[] = $this->l('An error occured while updating contacts');
		}
		else if ($method == 'processes')
		{
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->setStoreProcesses($this->dejalaConfig, $_POST);
			if ($response['status'] != 200)
				$errors[] = $this->l('An error occured while updating processes');
		}
		else if ($method == 'products') {
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->setStoreProducts($this->dejalaConfig, $_POST);
			if ($response['status'] != 200)
				$errors[] = $this->l('An error occured while updating products');
		}
		else if ($method == 'technical_options') {
			$maxSatuses = $_POST['status_max'];
			if ($maxSatuses > 30)
				$maxSatuses = 30;
			$selectedTriggers=array();
			for ($i = 0; $i < $maxSatuses; $i++) {
				$l_val = Tools::getValue('status_'.$i);
				if ($l_val) {
					$selectedTriggers[] = $l_val;
				}
			}
			$trigerringStatuses = implode(',', $selectedTriggers);
			$this->dejalaConfig->trigerringStatuses = htmlentities($trigerringStatuses, ENT_COMPAT, 'UTF-8');
			$this->dejalaConfig->saveConfig();
			$this->dejalaConfig->loadConfig();
		}
		else if ($method == 'delivery_options') {
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->setStoreCalendar($this->dejalaConfig, $_POST);
			if ($response['status'] != 200)
				$errors[] = $this->l('An error occured while updating products');

			$m_attributes['nb_days_displayed'] = htmlentities(Tools::getValue('nb_days'), ENT_COMPAT, 'UTF-8');
			$m_attributes['delivery_delay'] = htmlentities(Tools::getValue('delivery_delay'), ENT_COMPAT, 'UTF-8');
			$m_attributes['delivery_partial'] = htmlentities(Tools::getValue('delivery_partial'), ENT_COMPAT, 'UTF-8');

			$response = $djlUtil->setStoreAttributes($this->dejalaConfig, $m_attributes);
			if ($response['status'] != 200)
				$errors[] = $this->l('An error occured while updating products');

		} else if ($method == 'golive') {
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->goLive($this->dejalaConfig, $_POST);
		} else if ($method == 'switchMode') {
			$l_mode = Tools::getValue('mode');
			if ( ('PROD' == $l_mode) || ('TEST' == $l_mode) ) {
				$this->dejalaConfig->mode = $l_mode;
				$this->dejalaConfig->saveConfig();
			}
		} else if ($method == 'switchActive') {
				$l_active= intval(Tools::getValue('active_flag'));
				if (($l_active == 1) || ($l_active == 0)) {
					$this->dejalaConfig->active = $l_active;
					$this->dejalaConfig->saveConfig();
				}
			}
			else {
			$errors[] = $this->l('Impossible to process the action');
		}
		return ($errors);
	}

	public function getContent()
	{
		global $smarty;
		
		$smarty->assign('country', $this->dejalaConfig->country);
		$output = $this->display(__FILE__, 'dejala_header.tpl');
		if (!empty($_POST))
		{
			$errors = $this->_postValidation();
			if (!count($errors))
				$errors = $this->_postProcess();
			if (count($errors))
				foreach ($errors AS $err)
					$output .= '<div class="alert error">'. $err .'</div>';
			else
			{
				$method = Tools::getValue('method');
				if (($method != 'signin') && ($method != 'register')) {
					$output .= '<div class="conf confirm">
					<img src="../img/admin/ok.gif" alt="" title="" />
					'.$this->l('Settings updated').'
				</div>';
				}
			}
		}
		$output = $output . $this->displayForm();
		return ($output);
	}


	public function displayForm()
	{
		global $smarty, $cookie;

		$errors = array();
		$outputMain = '';
		$smarty->assign("djl_mode", $this->dejalaConfig->mode);
		if ($this->dejalaConfig->mode == 'PROD')
			$smarty->assign("disabled", 'disabled="disabled"');

		$registered = TRUE;
		if ((0 == strlen($this->dejalaConfig->login)) || (0 == strlen($this->dejalaConfig->password)))
			$registered= FALSE;
		if ($registered) {
			$djlUtil = new DejalaUtils();
			$responsePing = $djlUtil->ping($this->dejalaConfig, $this->dejalaConfig->mode);
			if (200 == $responsePing['status'])
				$smarty->assign("registered", $registered?"1":"0");
			else {
				if (401 == $responsePing['status'])
					$errors[] = $this->l('An error occured while authenticating your account on Dejala.fr. Your credentials were not recognized');
				else
					$errors[] = $this->l('An error occured while authenticating your account on Dejala.fr. This can be due to a temporary network or platform problem. Please try again later or contact Dejala.fr');
				unset($_GET['cat']);
				$registered= FALSE;
			}
		}


		if (!isset($_GET['cat']) || ($_GET['cat']==='home') || ($_GET['cat']===''))
			$currentTab="home";
		else
			$currentTab=$_GET['cat'];
		$smarty->assign("currentTab", $currentTab);
		$smarty->assign("moduleConfigURL", 'index.php?tab=AdminModules&configure=dejala&token='.$_GET['token']);
		$smarty->assign("formAction", $_SERVER['REQUEST_URI']);
		$outputMenu = $this->display(__FILE__, 'dejala_menu.tpl');

		if ($currentTab==='home') {
			if ($registered)
			{
				$smarty->assign("is_active", intval($this->dejalaConfig->active));				
				$smartifyErrors = $this->smartyfyStoreAttributes();
				if (isset($smartifyErrors) && count($smartifyErrors))
					$errors = $smartifyErrors;
			}
			else
			{
				$smarty->assign("login", html_entity_decode(Configuration::get('PS_SHOP_EMAIL'), ENT_COMPAT, 'UTF-8'));
				$shopName = Configuration::get('PS_SHOP_NAME');
				if (strlen($shopName) >= 15)
					$shopName = substr($shopName, 0, 15);
				$smarty->assign("store_name", html_entity_decode($shopName, ENT_COMPAT, 'UTF-8'));
			}
			$outputMain = $this->display(__FILE__, 'dejala_home.tpl');
		}
		else if ($currentTab==='contacts') {
			$contacts = array();
			$djlUtil = new DejalaUtils();
			$responseArray = $djlUtil->getStoreContacts($this->dejalaConfig, $contacts);
			if ('200' == $responseArray['status'])
			{
				foreach ($contacts as $contactName=>$contactData) {
					foreach ($contactData as $key=>$value) {
						$smarty->assign($contactName.'_'.$key, $value);
					}
				}
			}
			$outputMain = $this->display(__FILE__, 'dejala_contacts.tpl');
		}
		else if ($currentTab==='location')
		{
			$location = array();
			$djlUtil = new DejalaUtils();
			$responseArray = $djlUtil->getStoreLocation($this->dejalaConfig, $location);
			if ('200' == $responseArray['status'])
			{
				foreach ($location as $key=>$value) {
					$smarty->assign($key, $value);
				}
				$outputMain = $this->display(__FILE__, 'dejala_location.tpl');
			}
		}
		else if ($currentTab==='processes')
		{
			$processes = array();
			$djlUtil = new DejalaUtils();
			$responseArray = $djlUtil->getStoreProcesses($this->dejalaConfig, $processes);
			if ('200' == $responseArray['status'])
			{
				foreach ($processes as $key=>$value) {
					$smarty->assign($key, $value);
				}
				$outputMain = $this->display(__FILE__, 'dejala_processes.tpl');
			}
		}
		else if ($currentTab==='prices') {
			$products = array();
			$djlUtil = new DejalaUtils();
			$responseArray = $djlUtil->getStoreProducts($this->dejalaConfig, $products);
			if ('200' == $responseArray['status'])
			{
				//price = price_HT*(inv_vat)
				foreach ($products as &$product) {
					$vat_factor = (1+ ($product['vat'] / 100));
					$product['price_notax'] = number_format($product['price'], 2, '.', '');
					$product['price'] = number_format(round($product['price']*$vat_factor, 2), 2, '.', '');
					$product['public_price'] = number_format(round($product['price'] + $product['margin'], 2), 2, '.', '');
					$product['public_price_notax'] = number_format(round($product['public_price']/$vat_factor, 2), 2, '.', '');
				}
				$smarty->assign('products', $products);
				$outputMain = $this->display(__FILE__, 'dejala_products.tpl');
			}
		}
		else if ($currentTab==='accounting') {
			$smartifyErrors = $this->smartyfyStoreAttributes();
			if (isset($smartifyErrors) && count($smartifyErrors))
				$errors = $smartifyErrors;
					
			$djlUtil = new DejalaUtils();
			$deliveries = array();
			$responseArray = $djlUtil->getStoreDeliveries($this->dejalaConfig, $deliveries);
			if ('200'==$responseArray['status'])
			{
				foreach ($deliveries as &$delivery) {
					$delivery['creation_date'] = date('d/m/Y', $delivery['creation_utc']);
					$delivery['creation_time'] = date('H\hi', $delivery['creation_utc']);
					if (isset($delivery['shipping_start_utc'])) {
						$delivery['shipping_date'] = date('d/m/Y', $delivery['shipping_start_utc']);
						$delivery['shipping_start'] = date('H\hi', $delivery['shipping_start_utc']);
						$delivery['shipping_stop'] = date('H\hi', intval($delivery['shipping_start_utc']) + 3600*intval($delivery['timelimit']) );
					}
					else {
						$delivery['shipping_date'] = '';
						$delivery['shipping_start'] = '';
						$delivery['shipping_stop'] = '';
					}
					
					if (isset($delivery['delivery_utc']))
					{
						$delivery['delivery_date'] = date('d/m/Y', $delivery['delivery_utc']);
						$delivery['delivery_time'] = date('H\hi', $delivery['delivery_utc']);
					}
				}
				$smarty->assign('formAction', '/modules/'.$this->name.'/deliveries_csv.php');
				$smarty->assign('defaultDateFrom', date('01/m/Y'));
				$smarty->assign('defaultDateTo', date('d/m/Y'));
				$smarty->assign('deliveries', $deliveries);
				$outputMain = $this->display(__FILE__, 'dejala_deliveries.tpl');
			}
		}
		else if ($currentTab==='delivery_options') {
			$outputMain = $this->displayDeliveryOptions();
		}
		else if ($_GET['cat']==='technical_options') {
			$states = $this->getOrderStates();
			$triggers = explode(',', $this->dejalaConfig->trigerringStatuses);
			$orderStatuses = array();
			foreach ($states as $status){
				$m_status['id'] = $status['id_order_state'];
				$m_status['label'] = $status['name'];
				if (in_array($status['id_order_state'], $triggers))
					$m_status['checked'] = '1';
				else
					$m_status['checked'] = '0';
				$orderStatuses[] = $m_status;
			}
			$smarty->assign('statuses', $orderStatuses);

			$smarty->assign('trigerringStatuses', $this->dejalaConfig->trigerringStatuses);
			$outputMain = $this->display(__FILE__, 'dejala_technical_options.tpl');
		}

		$outputErr = '';
		if (count($errors))
			foreach ($errors AS $err)
				$outputErr .= '<div class="alert error">'. $err .'</div>';

		$output = $outputErr;
		$output = $output . $outputMenu;
		$output = $output . $outputMain;
		$output = $output . $this->display(__FILE__, 'dejala_footer.tpl');
		return $output;
	}


	// put in smarty context store attributes
	function smartyfyStoreAttributes() 
	{
		global $smarty;
		
		$errors = array();
		$djlUtil = new DejalaUtils();
		$storeAttrs = array();
		$response = $djlUtil->getStoreAttributes($this->dejalaConfig, $storeAttrs);
		if (200 != $response['status'])
			$errors[] = $this->l('An error occured while getting store, please try again later or contact Dejala.fr');
		else
		{
			$smarty->assign("account_balance", $storeAttrs['account_balance']);
			$smarty->assign("store_name", $storeAttrs['name']);

			// Check if account exists in production
			$responsePing = $djlUtil->ping($this->dejalaConfig, 'PROD');
			if ('200' == $responsePing['status'])
				$smarty->assign('isLiveReady', '1');
			else
			{
				$smarty->assign('isLiveReady', '0');
				if (isset($storeAttrs['attributes']) && isset($storeAttrs['attributes']['request_live']) && ($storeAttrs['attributes']['request_live']=='true'))
					$smarty->assign('isLiveRequested', '1');
				else
					$smarty->assign('isLiveRequested', '0');
			}
		}
		return ($errors);
	}


	function getOrderStates(){
		global $cookie;

		$id_lang = $cookie->id_lang;
		if (NULL == $id_lang) {
			$id_lang = intval(Configuration::get('PS_LANG_DEFAULT'));
		}
		$states = OrderState::getOrderStates($id_lang);
		return ($states);
	}

	function displayDeliveryOptions(){
		global $smarty;

		/*
		Au moment du choix du cr�neau
		Pour d�terminer le cr�neau de d�part propos�:
		- Aller sur le prochain cr�neau libre
		- Ajouter le d�lai de traitement de la commande
		- Aller sur le prochain cr�neau libre

		- Le marchand configure l ouverture de sa boutique en weedkay (hStart-hStop) + exception (date fermeture) tous produits confondus
		On fait le min au moment de l afichage des creneaux dispo
		   => trouver une slideBar avec deux cusrseurs
		*/
		$output = '';
		$djlUtil = new DejalaUtils();
		$response = $djlUtil->getStoreAttributes($this->dejalaConfig, $store);
		if ($response['status'] == 200) {
			$smarty->assign('nb_days', $store['attributes']['nb_days_displayed']);
			$smarty->assign('delivery_delay', $store['attributes']['delivery_delay']);
			if (isset($store['attributes']['delivery_partial']))
				$smarty->assign('delivery_partial', $store['attributes']['delivery_partial']);
		}

		$wday_labels = array($this->l('Dimanche'), $this->l('Lundi'), $this->l('Mardi'), $this->l('Mercredi'), $this->l('Jeudi'), $this->l('Vendredi'), $this->l('Samedi'));
		$wday_selected = array(1, 1, 1, 1, 1, 1, 1);

		$smarty->assign('timetable_css', _MODULE_DIR_.$this->name.'/timetable.css');
		$smarty->assign("timetable_js", _MODULE_DIR_.$this->name.'/timetable.js');
		$smarty->assign("weekdayLabels", $wday_labels);
		$smarty->assign("weekdaySelected", $wday_selected);


		$calendar = array();
		$response = $djlUtil->getStoreCalendar($this->dejalaConfig, $calendar);
		if ($response['status'] == 200) {
			$smarty->assign("calendar", $calendar);
			$smarty->assign("timetableTpl", dirname(__FILE__)."/dejala_picking_timetable.tpl");
		}
		$output = $output . $this->display(__FILE__, 'dejala_delivery_options.tpl');

		return ($output);
	}

	/**
	 * Retourne FALSE si un des produits du cart n'est pas en stock, retourne FALSE sinon
	**/
	function isCartOutOfStock() {
		global $cart;

		$products = $cart->getProducts();
		foreach ($products as $product)
		{
			$this->mylog('product:');
			$this->mylog($this->logValue($product, 1));

			$orderedQuantity = intval($product['cart_quantity']);
			$productQuantity = intval($product['stock_quantity']);
			if ( ($productQuantity < $orderedQuantity) || ($productQuantity <= 0) )
				return (TRUE);
		}
		return (FALSE);
	}


	/**
	** Affiche le transporteur Dejala.fr dans la liste des transporteurs sur le Front Office
	*/
	public function hookExtraCarrier($params) {
		global $smarty, $cart, $cookie, $defaultCountry;

		$this->hooklog("ExtraCarrier", $params);

		// Dejala n'est pas actif sur la boutique
		if ($this->dejalaConfig->active != 1)
			return ;		

		$djlUtil = new DejalaUtils();
		$responseGetStore = $djlUtil->getStoreAttributes($this->dejalaConfig, $store);
		if ($responseGetStore['status']!='200')
			return ;

		$isCartOutOfStock = '0';
		if ($this->isCartOutOfStock())
			$isCartOutOfStock = '1';
		$this->mylog('isCartOutOfStock=' . $isCartOutOfStock . '');

		$acceptPartial = true;
		if (!isset($store['attributes']) || !isset($store['attributes']['delivery_partial']) || ($store['attributes']['delivery_partial'] != '1'))
			$acceptPartial = false;
		if ( ($isCartOutOfStock == '1') && !$acceptPartial) {
			return ;
		}

		$totalCartWeight = floatval($cart->getTotalWeight());

		$address = $params['address'];
		// ask dejala.fr for a quotation
		$quotation["receiver_name"] = $address->lastname;
		$quotation["receiver_firstname"] = $address->firstname;
		$quotation["receiver_company"] = $address->company;
		$quotation["receiver_address"] = $address->address1;
		$quotation["receiver_address2"] = $address->address2;
		$quotation["receiver_zipcode"] = $address->postcode;
		$quotation["receiver_city"] = $address->city;
		$quotation["receiver_phone"] = $address->phone;
		$quotation["receiver_phone_mobile"] = $address->phone_mobile;
		$quotation["receiver_comments"] = $address->other;
		$quotation["timelimit"] = 3;
		$quotation["weight"] = $totalCartWeight;

		$this->mylog("asking for quotation=" . $this->logValue($quotation,1));

		$products = array();
		$responseArray = $djlUtil->getStoreQuotation($this->dejalaConfig, $quotation, $products);
		if ($responseArray['status']!='200')
			return ;
		$this->mylog("found quotation=" . $this->logValue($responseArray['response'],1));

		$electedProduct = NULL;
		foreach ($products as $key=>$product) {
		//	if (floatval($product['max_weight']) >= $totalCartWeight) {
				if ( is_null($electedProduct) || (intval($electedProduct['priority']) > intval($key)) )
					$electedProduct = $product;
		//	}
		}
		if (is_null($electedProduct))
			return ;
		
		$this->mylog("electedProduct=" . $this->logValue($electedProduct,1));	

		$electedCarrier = DejalaCarrierUtils::getDejalaCarrier($this->dejalaConfig, $electedProduct);
		$this->mylog("electedCarrier=" . $this->logValue($electedCarrier,1));	
		if (null == $electedCarrier) {
			$this->mylog("creating a new carrier");	
			$electedCarrier = DejalaCarrierUtils::createDejalaCarrier($this->dejalaConfig, $electedProduct);
		}

		// Calcul des dates dispo
		$productCalendar = $electedProduct['calendar']['entries'];
		// MFR090831 - add picking time : the store is open to (stop_hour - picking time), it is more natural to merchants to set opening hours instead of dejala delivery time
		if ($electedProduct['pickingtime'])
			$pickingtime = intval($electedProduct['pickingtime']);
		else
			$pickingtime = $electedProduct['timelimit'];
		$djlUtil = new DejalaUtils();
		$storeCalendar = array();
		$calendar = array();
		$response = $djlUtil->getStoreCalendar($this->dejalaConfig, $storeCalendar);
		$this->mylog("productCalendar=" . $this->logValue($productCalendar,1));
		$this->mylog("storeCalendar=" . $this->logValue($storeCalendar,1));
		$this->mylog("response['status']=" . $response['status']);
		if ($response['status'] == 200) {
			foreach ($storeCalendar['entries'] as $weekday=>$calEntry) {
				if (isset($productCalendar[$weekday])) {
					$calendar[$weekday]["weekday"] = $weekday;
					$calendar[$weekday]["start_hour"] = max(intval($productCalendar[$weekday]["start_hour"]), intval($calEntry["start_hour"]));
					// MFR090831 - manage picking time : the store is open to (stop_hour - picking time)
					$calendar[$weekday]["stop_hour"] = min(intval($productCalendar[$weekday]["stop_hour"]-1), intval($calEntry["stop_hour"] - $pickingtime));
					if ($calendar[$weekday]["stop_hour"] < $calendar[$weekday]["start_hour"]) {
						unset($calendar[$weekday]);
					}
				}
			}
		}

		// Calcul de la date de d�marrage pour les cr�neaux :
		// Avancement jusque jour dispo & ouvert
		// Ajout du temps de pr�paration : 0.5 jour ou 1 nb de jours
		// Ajustement de l'heure sur l'ouverture ou l'heure suivante xxh00 
		$deliveryDelay = $store['attributes']['delivery_delay'];
		$calUtils = new CalendarUtils();
		$all_exceptions = array_merge($storeCalendar['exceptions'], $electedProduct['calendar']['exceptions']);
		$dateUtc = $calUtils->getNextDateAvailable(time(), $calendar, $all_exceptions);
		if ($dateUtc == NULL)
			return ;
		if ($deliveryDelay > 0)
			$dateUtc = $calUtils->addDelay($dateUtc, $deliveryDelay, $calendar, $all_exceptions);
		if ($dateUtc == NULL)
			return ;		
		$dateUtc = $calUtils->adjustHour($dateUtc, $calendar);		
		$this->mylog("calendar=" . $this->logValue($calendar,1));
		$this->mylog("starting date=" . $this->logValue(date("d/m/Y - H:i:s", $dateUtc),1));
		/**
		 Dates[0] = {
		 [label]=lundi
		 [value]=23/04/2009
		 [start_hour]=9
		 [stop_hour]=17
		 }
		 **/
		$today = getDate();
		$ctime = time();
		$wday_labels = array($this->l('Dimanche'), $this->l('Lundi'), $this->l('Mardi'), $this->l('Mercredi'), $this->l('Jeudi'), $this->l('Vendredi'), $this->l('Samedi'));
		$nbDeliveryDates = $deliveryDelay = $store['attributes']['nb_days_displayed'];
		$iDate = 0;
		$dates = array();
		$balladUtc = $dateUtc;
		do {
			$dates[$iDate]['value'] = date("Y/m/d", $balladUtc);
			$wd = date("w", $balladUtc);
			$dates[$iDate]['label'] = $wday_labels[$wd] . " " . date("j", $balladUtc);
			$dates[$iDate]['start_hour'] = $calendar[$wd]['start_hour'];
			$dates[$iDate]['stop_hour'] = $calendar[$wd]['stop_hour'];
			$balladUtc += 3600*24;
			$balladUtc = $calUtils->getNextDateAvailable($balladUtc, $calendar, $all_exceptions);
			$iDate++;

		} while (($iDate < $nbDeliveryDates) && ($balladUtc));
		// impossibilit� de trouver un jour dispo
		if (!isset($dates[0]))
			return ;
		$dates[0]['start_hour'] = date("H", $dateUtc);

		$this->mylog("date$=" . $this->logValue($dates,1));
		$smarty->assign('nb_days', $nbDeliveryDates);
		$smarty->assign('dates', $dates);
		for ($i=0; $i < 24; $i++) {
			$endHour = (($i+$electedProduct['timelimit'])%24);
			if ($endHour == 0)
				$endHour = 24;
			$hourLabels[] = $i . 'h-' . $endHour . 'h';
		}
		$smarty->assign('hourLabels', $hourLabels);

		$smarty->assign('timetable_css', _MODULE_DIR_.$this->name.'/timetable.css');
		$smarty->assign("timetable_js", _MODULE_DIR_.$this->name.'/timetable.js');

		$this->mylog("electedCarrier->id=" . $this->logValue($electedCarrier->id));
		$mCarrier = $electedCarrier;
		$row['id_carrier'] = intval($electedCarrier->id);
		$row['name'] = $this->l('Dejala.fr');
		$row['delay'] = $electedCarrier->delay[$cookie->id_lang];
		$row['price'] = $cart->getOrderShippingCost($electedCarrier->id);
		$row['price_tax_exc'] = $cart->getOrderShippingCost($electedCarrier->id, false);
		$row['img'] = _MODULE_DIR_.$this->name.'/dejala_carrier.gif';
		
		$resultsArray[] = $row;
		$smarty->assign('carriers', $resultsArray);
		if ($cart->id_carrier)
			$smarty->assign('checked', $cart->id_carrier);
		$smarty->assign('product', $electedProduct);


		$djlCart = new DejalaCart($cart->id);
		$setDefaultDate = TRUE;
		if ($djlCart && isset($djlCart->shipping_date) && !empty($djlCart->shipping_date))
		{
			$mShippingDate = $djlCart->shipping_date;
			$this->mylog("shipping_date=" . $this->logValue($mShippingDate));
			$m_day = date("d", $mShippingDate);
			$m_hour = date("H", $mShippingDate);
			$deliveryDateSelected = date("Y/m/d", $mShippingDate);
			$this->mylog("shipping_date=" . $this->logValue($deliveryDateSelected));
			foreach ($dates as $l_key=>$l_date) {
				if ($l_date['value'] == $deliveryDateSelected) {
					$smarty->assign("deliveryDateIndexSelected", $l_key);
					$smarty->assign("deliveryDateSelected", $deliveryDateSelected);
					$smarty->assign("deliveryHourSelected", $m_hour);
					$setDefaultDate = FALSE;
				}
			}
		}
		if ($setDefaultDate) {
			$smarty->assign("deliveryDateIndexSelected", 0);
			$smarty->assign("deliveryDateSelected", date("Y/m/d", $dateUtc));
			$smarty->assign("deliveryHourSelected", intval(date("H", $dateUtc)));
		}

		$smarty->assign("isCartOutOfStock", $isCartOutOfStock);
		if (!$isCartOutOfStock) {
			$buffer = $this->display(__FILE__, 'dejala_carrier.tpl');
			$buffer = $buffer . $this->display(__FILE__, 'dejala_timetable.tpl');
		}
		else
		{
			$smarty->assign('nostock_info', $this->l(utf8_encode('Je choisirai mon heure de livraison quand mon colis sera pr�t.')));
			$buffer = $this->display(__FILE__, 'dejala_carrier_nostock.tpl');
		}
		return $buffer;

	}

	public function displayInfoByCart($id_cart)
	{
		$this->hooklog("displayInfoByCart", $id_cart);
		$this->myLog("POST=" . $this->logValue($_POST));

		$this->myLog('dejala_action=' . Tools::getValue('dejala_action') );
		if (Tools::getValue('dejala_action')=='order') {
			$this->myLog('inside - id_cart=' . $id_cart);
			$mOrderId = Order::getOrderByCartId($id_cart);
			$mOrder = new Order($mOrderId);
			$this->placeOrder($mOrder);
		}
		$djlCart = new DejalaCart($id_cart);
		if ($djlCart && isset($djlCart->id_dejala_product) && isset($djlCart->shipping_date))
		{
			$mDejalaProductID = $djlCart->id_dejala_product;
			$mShippingDate = $djlCart->shipping_date;
			echo utf8_encode('<h4 style="color:red;">');
			if ($djlCart->mode !== 'PROD') {
				echo 'MODE : TEST<br/>';
			}
			if (!empty($mShippingDate) && ($mShippingDate != 0))
			{
				echo utf8_encode('Livraison souhait�e le '.date('d/m/Y',$mShippingDate).' � partir de '.date('H\hi', $mShippingDate) .'<br/>');
			}
			else
			{
				echo utf8_encode('Livraison dont la date doit �tre fix�e avec le client' .'<br/>');
			}
			if ( ($djlCart->id_delivery) && Validate::isUnsignedId($djlCart->id_delivery) )
			{
				$l_delivery = array();
				$l_delivery['id'] = $djlCart->id_delivery;
				$djlUtil = new DejalaUtils();
				$response = $djlUtil->getDelivery($this->dejalaConfig, $l_delivery, $djlCart->mode);
				if ($response['status'] == 200)
				{
					if ($l_delivery && $l_delivery['status'] && $l_delivery['status']['labels'] && $l_delivery['status']['labels']['fr'])
						echo utf8_encode('Commande '.$l_delivery['status']['labels']['fr'].'<br/>');
					else
						echo utf8_encode('Commande envoy�e � Dejala.fr<br/>');
				}
			} else
			{
				$_html = '';
				//echo utf8_encode('<form Livraison souhait�e le '.date('d/m/Y',$mShippingDate).' � partir de '.date('H\hi', $mShippingDate) .'<br/>');
				$_html .= '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';
				$_html .= '<input type="hidden" name="dejala_action" value="order">';
				$_html .= '<input type="submit" value="Commander la course">';
				$_html .= '</form><br />';
				echo $_html . '';
			}

			echo utf8_encode('</h4">');

		}
	}

	/**
	 * Save information to enable delivery to be ordered after payment
	*/
	public function hookCart($param) {
		$this->hooklog("hookCart", "");

		$errors = array();
		$dejalaCarrierID = Tools::getValue('dejala_id_carrier');
		$carrierID = Tools::getValue('id_carrier');
		$dejalaProductID = Tools::getValue('dejala_id_product');
		if ( !empty($dejalaCarrierID) && !empty($carrierID) && (intval($dejalaCarrierID) == intval($carrierID)) )
		{
			$id_cart = intval($param['cart']->id);

			$product = array();
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->getStoreProductByID($this->dejalaConfig, $dejalaProductID, $product);
			if ($response['status'] != 200)
				$errors[] = 'An error occured while getting product';
			else
			{
				if (isset($product['timelimit']))
					$timelimit = intval($product['timelimit']);
				else
					$timelimit = 3;

				/* manage shipping preferences */
				$date_shipping = 'NULL';
				if ( isset($_POST['shipping_day']) AND !empty($_POST['shipping_day']) AND (10 <= strlen($_POST['shipping_day'])) )
				{
					$shippingHour = intval($_POST['shipping_hour']);
					$shipping_day = $_POST['shipping_day'];
					$ship_year = intval(substr($shipping_day, 0, 4));
					$ship_month = intval(substr($shipping_day, 5, 2));
					$ship_day = intval(substr($shipping_day, 8, 2));
					$shippingTime = mktime($shippingHour, 0, 0, $ship_month, $ship_day, $ship_year);
					// check that delivery date is in the future (5 min delay)
					if ($shippingTime > time() - 300)
						$date_shipping = $shippingTime;
				}
				$djlCart = new DejalaCart($id_cart);
				$djlCart->shipping_date = $date_shipping;
				$djlCart->id_dejala_product = $dejalaProductID;
				$djlCart->id_delivery = NULL;
				$djlCart->mode = $this->dejalaConfig->mode;
				// MFR090202 - Fix SQL injection possibility following R�mi Gaillard remarks
				$sqlQuery = 'REPLACE INTO ' . _DB_PREFIX_ . 'dejala_cart SET id_cart = '.intval($id_cart).', id_dejala_product = '. intval($djlCart->id_dejala_product) .', shipping_date = '. intval($djlCart->shipping_date)  . ', mode="'. pSQL($djlCart->mode) .'";';
				$this->mylog('cart SQLQuery='. $sqlQuery);
				Db::getInstance()->Execute($sqlQuery);
			}
		}
	}




	/**
	 * Appel� apr�s la modification d'une commande
	**/
	public function hookUpdateOrderStatus($params)
	{
		$this->hooklog("hookUpdateOrderStatus", $params);
		// class OrderState
		$newOrderStatus = $params["newOrderStatus"];
		$currentOrderStatusID = $newOrderStatus->id;
		$this->mylog("newOrderStatus=" . $this->logValue($newOrderStatus));
		$this->mylog("found currentOrderStatusID=" . $currentOrderStatusID);

		$triggeringStatusList = html_entity_decode(Configuration::get('DJL_TRIGERRING_STATUSES'), ENT_COMPAT, 'UTF-8');
		$this->mylog("triggeringStatusList=" . $triggeringStatusList);
		$triggeringStatuses = explode(",", $triggeringStatusList);
		$orderID = $params["id_order"];
		if ((NULL !== $orderID) && (TRUE === in_array($currentOrderStatusID, $triggeringStatuses)))
		{
			$mOrder = new Order($orderID);
			$this->placeOrder($mOrder);
		}
	}


	public function placeOrder($mOrder) {
		$orderID = $mOrder->id;
		$this->myLog("placeOrder()");
		$this->myLog("mOrder->id_carrier=".$mOrder->id_carrier);
		$mCarrier = new Carrier($mOrder->id_carrier);
		$this->myLog("mCarrier->name=".$mCarrier->name);
		if ($mCarrier->name != $this->name)
			return ;
		$this->myLog("placeOrder()");

		$id_cart = $mOrder->id_cart;
		$djlCart = new DejalaCart($id_cart);
		$this->myLog("djlCart->id_delivery=" . $djlCart->id_delivery);

		if (!$djlCart->id_delivery)
		{
			$this->myLog("id_delivery is not filled");
			$delivery = array();
			$this->getInfoFromOrder($orderID, $delivery);
			$this->mylog("Sending delivery=" . $this->logValue($delivery));
			$djlUtil = new DejalaUtils();
			$response = $djlUtil->orderDelivery($this->dejalaConfig, $delivery, $djlCart->mode);
			$statusCode = $response['status'];

			$this->mylog("send orderID=" . $orderID);
			$this->mylog("sendOrder status_code=" . $statusCode);
			$this->mylog("sendOrder response=" . $response['response']);
			$this->mylog("sendOrder delivery=" . $this->logValue($delivery, 1));
			// update status after sending...
			if ("201" === $statusCode)
			{
				$this->mylog("updating dejala cart cart_id=" . $id_cart);
				if (Validate::isUnsignedId($delivery['id'])) {
					$this->mylog("updating dejala cart id_delivery=" . $delivery['id']);
					$djlCart->id_delivery = $delivery['id'];
					$djlCart->update();
				}
				if (is_null($mOrder->shipping_number) || (0 === strlen($mOrder->shipping_number)))
				{
					$this->myLog('setting Order->shipping_number to ' . $delivery['tracking_number']);
					$mOrder->shipping_number = $delivery['tracking_number'];
					$mOrder->save();
				}
				$this->myLog("OK -  Order sent to dejala.fr");
			}
			else
			{
				// Do nothing : Keep previous status
				$this->myLog("NOK - Problem sending Order to dejala.fr");
			}
		}
	}

	public function getInfoFromOrder($orderID, &$delivery)
	{
		$mOrder = new Order($orderID);
		if (NULL !== $mOrder) {
			$mDeliveryAddress = new Address($mOrder->id_address_delivery);
			if (NULL !== $mDeliveryAddress)
			{
				// receiver address information
				$delivery["receiver_firstname"]=$mDeliveryAddress->firstname;
				$delivery["receiver_name"]=$mDeliveryAddress->lastname;
				if ($mDeliveryAddress->company)
					$delivery["receiver_company"]=$mDeliveryAddress->company;
				$delivery["receiver_address"]=$mDeliveryAddress->address1;
				if ($mDeliveryAddress->address2)
					$delivery["receiver_address2"]=$mDeliveryAddress->address2;
				$delivery["receiver_zipcode"]=$mDeliveryAddress->postcode;
				$delivery["receiver_city"]=$mDeliveryAddress->city;
				if ($mDeliveryAddress->phone_mobile)
					$delivery["receiver_cellphone"]=$mDeliveryAddress->phone_mobile;
				if ($mDeliveryAddress->phone)
					$delivery["receiver_phone"]=$mDeliveryAddress->phone;
				if ($mDeliveryAddress->other)
					$delivery["receiver_comments"]=$mDeliveryAddress->other;
			}
			$delivery["packet_reference"]=$mOrder->id;


			$id_cart = $mOrder->id_cart;
			/* set weight */
			$cart = new Cart($id_cart);
			$delivery['weight'] = floatval($cart->getTotalWeight());

			/* set dejalaProductID and sender_availability = shipping date */
			$djlCart = new DejalaCart($id_cart);
			if (!is_null($djlCart) && !is_null($djlCart->id))
			{
				$mDejalaProductID = $djlCart->id_dejala_product;
				$delivery["product_id"] = intval($mDejalaProductID);
				$mShippingDate = $djlCart->shipping_date;
				if ( is_null($mShippingDate) || empty($mShippingDate) ) {
					$mShippingDate = 0;
				}
				$delivery["shipping_start_utc"]=$mShippingDate;
			}
		}
		return ($delivery);
	}

	


	public function mylog($msg) {
		if ($this->DEJALA_DEBUG) {
			require_once(dirname(__FILE__) . "/MyLogUtils.php");
			$myFile = dirname(__FILE__) . "/logFile.txt";
			MyLogUtils::myLog($myFile, $msg);
		}
	}

	// get a string of a value for Log purposes
	public function logValue($mvalue, $lvl=0) {
		if (!$this->DEJALA_DEBUG)
			return ("");
		require_once(dirname(__FILE__) . "/MyLogUtils.php");
		return (MyLogUtils::logValue($mvalue, $lvl));
	}


	public function hooklog($hookname, $params) {
		$this->mylog($hookname);
		$this->mylog("\r\nparams" . $this->logValue($params), 1);
	}


}

?>
