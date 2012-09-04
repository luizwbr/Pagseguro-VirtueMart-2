<?php

if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * @version $Id: /home/components/com_virtuemart,v 1.4 2005/05/27 19:33:57 ei
 *
 * a special type of 'cash on delivey':
 * @author Max Milbers, ValÃ©rie Isaksen, Luiz F. Weber, Fábio Paiva
 * @version $Id: /home/components/com_virtuemart 5122 2011-12-18 22:24:49Z alatak $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentPagseguro extends vmPSPlugin {

    // instance of class
    public static $_this = false;

    function __construct(& $subject, $config) {
        //if (self::$_this)
        //   return self::$_this;
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = array('payment_logos' => array('', 'char'),
            'email_cobranca' => array('', 'string'),
            'tipo_frete' => array('', 'int'),
            'token' => array('', 'string'),
            'status_completo'=> array('', 'char'),
            'status_aprovado'=> array('', 'char'),
            'status_analise'=> array('', 'char'),
            'status_cancelado'=> array('', 'char'),
            'status_aguardando'=> array('', 'char'),
            'status_paga'=> array('', 'char'),
            'status_disponivel'=> array('', 'char'),
            'status_devolvida'=> array('', 'char'),
            'status_disputa'=> array('', 'char'),
            'segundos_redirecionar'=> array('', 'string'),
			'countries' => array('', 'char'),
			'min_amount' => array('', 'int'),
			'max_amount' => array('', 'int'),
			'cost_per_transaction' => array('', 'int'),
			'cost_percent_total' => array('', 'int'),
			'tax_id' => array(0, 'int'),
        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        // self::$_this = $this;
    }
    /**
     * Create the table for this plugin if it does not yet exist.
     * @author ValÃ©rie Isaksen
     */
    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Pagseguro Table');
    }

    /**
     * Fields to create the payment table
     * @return string SQL Fileds
     */
    function getTableSQLFields() {
        $SQLfields = array(
            'id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' => 'char(3) ',
            'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
            'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
            'tax_id' => 'smallint(11) DEFAULT NULL'
        );

        return $SQLfields;
    }
    
    function getPluginParams(){
        $db = JFactory::getDbo();
        $sql = "select virtuemart_paymentmethod_id from #__virtuemart_paymentmethods where payment_element = 'pagseguro'";
        $db->setQuery($sql);
        $id = (int)$db->loadResult();
        return $this->getVmPluginMethod($id);
    }

    /**
     *
     *
     * @author ValÃ©rie Isaksen
     */
    function plgVmConfirmedOrder($cart, $order) {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

        $html = "";

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        $this->getPaymentCurrency($method);
        // END printing out HTML Form code (Payment Extra Info)
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);


        $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = $this->renderPluginName($method);
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = (!empty($method->cost_per_transaction)?$method->cost_per_transaction:0);
        $dbValues['cost_percent_total'] = (!empty($method->cost_percent_total)?$method->cost_percent_total:0);
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

		$html = $this->retornaHtmlPagamento( $order, $method, 1);
		
        JFactory::getApplication()->enqueueMessage(utf8_encode(
			"Seu pedido foi realizado com sucesso. Você será direcionado para o site do Pagseguro, onde efetuará o pagamento da sua compra."
		));

		$novo_status = $method->status_aguardando;
		return $this->processConfirmedOrderPaymentResponse(1, $cart, $order, $html, $dbValues['payment_name'], $novo_status);

    }
	
	function retornaHtmlPagamento( $order, $method, $redir ) {
		$lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

		$html = '<table>' . "\n";
        $html .= $this->getHtmlRow('STANDARD_PAYMENT_INFO', $dbValues['payment_name']);
        if (!empty($payment_info)) {
            $lang = & JFactory::getLanguage();
            if ($lang->hasKey($method->payment_info)) {
                $payment_info = JTExt::_($method->payment_info);
            } else {
                $payment_info = $method->payment_info;
            }
            $html .= $this->getHtmlRow('STANDARD_PAYMENTINFO', $payment_info);
        }
		if (!class_exists('CurrencyDisplay'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php' );
        $currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);
        $html .= $this->getHtmlRow('STANDARD_ORDER_NUMBER', $order['details']['BT']->order_number);
        $html .= $this->getHtmlRow('STANDARD_AMOUNT', $currency->priceDisplay($order['details']['BT']->order_total));
        $html .= '</table>' . "\n";
		
		//buscar forma de envio
		$db = &JFactory::getDBO();
        $q = 'SELECT `shipment_element` FROM `#__virtuemart_shipmentmethods` WHERE `virtuemart_shipmentmethod_id`="' . $order["details"]["ST"]->virtuemart_shipmentmethod_id . '" ';
        $db->setQuery($q);
        $envio = $db->loadResult();

        if (stripos($envio, "sedex") === false && stripos($envio, "pac") === false) {
            $tipo_frete = $method->tipo_frete ? 'SD' : 'EN'; // Encomenda Pac ou Sedex
        } elseif (stripos($envio, "sedex") !== false) {
            $tipo_frete = "SD";
        } else {
            $tipo_frete = "EN";
		}

        $html .= '<form id="frm_pagseguro" action="https://pagseguro.uol.com.br/security/webpagamentos/webpagto.aspx" method="post" >    ';
        $html .= '  <input type="hidden" name="email_cobranca" value="' . $method->email_cobranca . '"  />
                    <input type="hidden" name="moeda" value="BRL"  />
                    <input type="hidden" name="tipo" value="CP"  />
                    <input type="hidden" name="encoding" value="utf-8"  />
                    <input type="hidden" name="ref_transacao" value="' . ($order["details"]["ST"]->order_number!=''?$order["details"]["ST"]->order_number:$order["details"]["BT"]->order_number) . '"  />
                    <input type="hidden" name="tipo_frete" value="' . $tipo_frete . '"  />';

        $html .= '<input type="hidden" name="cliente_nome" value="' . ($order["details"]["ST"]->first_name!=''?$order["details"]["ST"]->first_name:$order["details"]["BT"]->first_name) . ' ' . ($order["details"]["ST"]->last_name!=''?$order["details"]["ST"]->last_name:$order["details"]["BT"]->last_name) . '"  />
		<input type="hidden" name="cliente_cep" value="' . ($order["details"]["ST"]->zip!=''?$order["details"]["ST"]->zip:$order["details"]["BT"]->zip) . '"  />
		<input type="hidden" name="cliente_end" value="' . ($order["details"]["ST"]->address_1!=''?$order["details"]["ST"]->address_1:$order["details"]["BT"]->address_1) . ' ' . ($order["details"]["ST"]->address_2!=''?$order["details"]["ST"]->address_2:$order["details"]["BT"]->address_2) . '"  />
		<input type="hidden" name="cliente_num" value=""  />
		<input type="hidden" name="cliente_compl" value=""  />
		<input type="hidden" name="cliente_cidade" value="' . ($order["details"]["ST"]->city!=''?$order["details"]["ST"]->city:$order["details"]["BT"]->city) . '"  />';	
		$cod_estado = (!empty($order["details"]["ST"]->virtuemart_state_id)?$order["details"]["ST"]->virtuemart_state_id:$order["details"]["BT"]->virtuemart_state_id);		
		$estado = ShopFunctions::getStateByID($cod_estado, "state_2_code");				
		$html.='
		<input type="hidden" name="cliente_uf" value="' . $estado . '"  />
		<input type="hidden" name="cliente_pais" value="BRA"  />
		<input type="hidden" name="cliente_ddd" value=""  />
		<input type="hidden" name="cliente_tel" value="' . ($order["details"]["ST"]->phone_1!=''?$order["details"]["ST"]->phone_1:$order["details"]["BT"]->phone_1) . '"  />
		<input type="hidden" name="cliente_email" value="' . ($order["details"]["ST"]->email!=''?$order["details"]["ST"]->email:$order["details"]["BT"]->email) . '"  />';
		
		// total do frete
		// configurado para passar o frete do total da compra
		if (!empty($order["details"]["BT"]->order_shipment)) {
			$html .= '<input type="hidden" name="item_frete_1" value="' . number_format((($order["details"]["ST"]->order_shipment!=''?$order["details"]["ST"]->order_shipment:$order["details"]["BT"]->order_shipment)), 2, ",", "") . '">';
		} else {
			$html .= '<input type="hidden" name="item_frete_1" value="0">';
		}

		if(!class_exists('VirtueMartModelCustomfields'))require(JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'customfields.php');

		foreach ($order['items'] as $p) {
			$i++;
			$product_attribute = strip_tags(VirtueMartModelCustomfields::CustomsFieldOrderDisplay($p,'FE'));
			$html .='<input type="hidden" name="item_id_' . $i . '" value="' . $p->virtuemart_order_item_id . '">
				<input type="hidden" name="item_descr_' . $i . '" value="' . $p->order_item_name . '">
				<input type="hidden" name="item_quant_' . $i . '" value="' . $p->product_quantity . '">
				<input type="hidden" name="item_valor_' . $i . '" value="' . number_format($p->product_final_price, 2, ",", "") .'">
				<input type="hidden" name="item_peso_' . $i . '" value="' . ShopFunctions::convertWeigthUnit($p->product_weight, $p->product_weight_uom, "GR") . '">';
		}		

		$url 	= JURI::root();
		$url_lib 			= $url.DS.'plugins'.DS.'vmpayment'.DS.'pagseguro'.DS;
		$url_imagem_pagamento 	= $url_lib . 'imagens'.DS.'pagseguro.gif';

		// segundos para redirecionar para o Pagseguro
		if ($redir) {
			// segundos para redirecionar para o Pagseguro
			$segundos = $method->segundos_redirecionar;
			$html .= '<br/><br/>Voc&egrave; ser&aacute; direcionado para a tela de pagamento em '.$segundos.' segundo(s), ou ent&atilde;o clique logo abaixo:<br />';
			$html .= '<script>setTimeout(\'document.getElementById("frm_pagseguro").submit();\','.$segundos.'000);</script>';
		}
		$html .= '<div align="center"><br /><input type="image" value="Clique aqui para efetuar o pagamento" class="button" src="'.$url_imagem_pagamento.'" /></div>';
        $html .= '</form>';		
		return $html;
	}

    /**
     * Display stored payment data for an order
     *
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
                . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            vmWarn(500, $q . " " . $db->getErrorMsg());
            return '';
        }
        $this->getPaymentCurrency($paymentTable);

        $html = '<table class="adminlist">' . "\n";
        $html .=$this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        $html .= '</table>' . "\n";
        return $html;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
		$method->min_amount = (!empty($method->min_amount)?$method->min_amount:0);
		$method->max_amount = (!empty($method->max_amount)?$method->max_amount:0);
		
        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
                OR
         ($method->min_amount <= $amount AND ($method->max_amount == 0) ));
        if (!$amount_cond) {
            return false;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
            $address['virtuemart_country_id'] = 0;
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return true;
        }

        return false;
    }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author ValÃ©rie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author ValÃ©rie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$orderModel = VmModel::getModel('orders');
		$orderDetails = $orderModel->getOrder($virtuemart_order_id);
		if (!($method = $this->getVmPluginMethod($orderDetails['details']['BT']->virtuemart_paymentmethod_id))) {
			return false;
		}
	
		$view = JRequest::getVar('view');
		// somente retorna se estiver como transação pendente
		if ($method->status_aguardando == $orderDetails['details']['BT']->order_status and $view == 'orders' and $orderDetails['details']['BT']->virtuemart_paymentmethod_id == $virtuemart_paymentmethod_id) {
			JFactory::getApplication()->enqueueMessage(utf8_encode(
				"O pagamento deste pedido consta como Pendente de pagamento ainda. Clique pra  Voc&ecirc; ser&aacute; direcionado para o site do Pagseguro, onde efetuar&aacute; o pagamento da sua compra.")
			);
			
			$redir = 0;
			$html = $this->retornaHtmlPagamento( $orderDetails, $method, $redir );
			echo $html;
		}

        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers

      public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
      return null;
      }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    //Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk
     *
      public function plgVmOnUpdateOrderPayment(  $_formData) {
      return null;
      }

      /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk
     *
      public function plgVmOnUpdateOrderLine(  $_formData) {
      return null;
      }

      /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk
     *
      public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
      return null;
      }

      /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk
     *
      public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
      return null;
      }

      /**
     * This event is fired when the  method notifies you when an event occurs that affects the order.
     * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
     * such as refunds, disputes, and chargebacks.
     *
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @param $return_context: it was given and sent in the payment form. The notification should return it back.
     * Used to know which cart should be emptied, in case it is still in the session.
     * @param int $virtuemart_order_id : payment  order id
     * @param char $new_status : new_status for this order id.
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Valerie Isaksen
     *
     *
      public function plgVmOnPaymentNotification() {
      return null;
      }
	  */
	  function plgVmOnPaymentNotification() {
		
		header("Status: 200 OK");
		if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		$pagseguro_data = $_REQUEST;
		
		if (!isset($pagseguro_data['TransacaoID'])) {
			return;
		}		
		$order_number = $pagseguro_data['Referencia'];
		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		//$this->logInfo('plgVmOnPaymentNotification: virtuemart_order_id  found ' . $virtuemart_order_id, 'message');

		if (!$virtuemart_order_id) {
			return;
		}
		$vendorId = 0;
		$payment = $this->getDataByOrderId($virtuemart_order_id);
		if($payment->payment_name == '') {
			return false;
		}
		$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		//$this->_debug = $method->debug;
		if (!$payment) {
			$this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
			return null;
		}
		$this->logInfo('pagseguro_data ' . implode('   ', $pagseguro_data), 'message');

		// get all know columns of the table
		$db = JFactory::getDBO();
		$query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
		$db->setQuery($query);
		$columns = $db->loadResultArray(0);
		$post_msg = '';
		foreach ($pagseguro_data as $key => $value) {
			$post_msg .= $key . "=" . $value . "<br />";
			$table_key = 'pagseguro_response_' . $key;
			if (in_array($table_key, $columns)) {
			$response_fields[$table_key] = $value;
			}
		}

		//$response_fields[$this->_tablepkey] = $this->_getTablepkeyValue($virtuemart_order_id);
		//$response_fields['payment_name'] = $this->renderPluginName($method);
		$response_fields['payment_name'] = $payment->payment_name;
		//$response_fields['paypalresponse_raw'] = $post_msg;
		//$return_context = $pagseguro_data['custom'];
		$response_fields['order_number'] = $order_number;
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		//$preload=true   preload the data here too preserve not updated data
		//$this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);

		/*
		$error_msg = $this->_processIPN($pagseguro_data, $method);
		$this->logInfo('process IPN ' . $error_msg, 'message');		
		if (!(empty($error_msg) )) {
			$new_status = $method->status_canceled;
			$this->logInfo('process IPN ' . $error_msg . ' ' . $new_status, 'ERROR');
		} else {
			$this->logInfo('process IPN OK', 'message');
		}*/
			/*
			 * https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_html_IPNandPDTVariables
			 * The status of the payment:
			 * Canceled_Reversal: A reversal has been canceled. For example, you won a dispute with the customer, and the funds for the transaction that was reversed have been returned to you.
			 * Completed: The payment has been completed, and the funds have been added successfully to your account balance.
			 * Created: A German ELV payment is made using Express Checkout.
			 * Denied: You denied the payment. This happens only if the payment was previously pending because of possible reasons described for the pending_reason variable or the Fraud_Management_Filters_x variable.
			 * Expired: This authorization has expired and cannot be captured.
			 * Failed: The payment has failed. This happens only if the payment was made from your customer’s bank account.
			 * Pending: The payment is pending. See pending_reason for more information.
			 * Refunded: You refunded the payment.
			 * Reversed: A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance and returned to the buyer. The reason for the reversal is specified in the ReasonCode element.
			 * Processed: A payment has been accepted.
			 * Voided: This authorization has been voided.
			 *
			 */
			if (empty($pagseguro_data['cod_status']) || ($pagseguro_data['cod_status'] != '0' && $pagseguro_data['cod_status'] != '1' && $pagseguro_data['cod_status'] != '2')) {
			//return false;
			}
			
			$pagseguro_status = $pagseguro_data['StatusTransacao'];
			switch($pagseguro_status){
				case 'Completo': 	$new_status = $method->status_completo; break;
				case 'Aprovado': 	$new_status = $method->status_aprovado; break;
				case 'Em Análise': 	$new_status = $method->status_analise; break;
				case 'Cancelado': 	$new_status = $method->status_cancelado; break;
				case 'Paga': 		$new_status = $method->status_paga; break;
				case 'Disponivel': 	$new_status = $method->status_disponivel; break;
				case 'Devolvida': 	$new_status = $method->status_devolvida; break;
				case 'Aguardando Pagto':
				default: $new_status = $method->status_aguardando; break;
			}


		$this->logInfo('plgVmOnPaymentNotification return new_status:' . $new_status, 'message');

		if ($virtuemart_order_id) {
			// send the email only if payment has been accepted
			if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
			$modelOrder = new VirtueMartModelOrders();
			$orderitems = $modelOrder->getOrder($virtuemart_order_id);
			$nb_history = count($orderitems['history']);
			$order['order_status'] = $new_status;
			$order['virtuemart_order_id'] = $virtuemart_order_id;
			$order['comments'] = 'O status do seu pedido '.$order_number.' no Pagseguro foi atualizado: '.utf8_encode($pagseguro_data['StatusTransacao']);
			//JText::sprintf('VMPAYMENT_PAYPAL_PAYMENT_CONFIRMED', $order_number);
			if ($nb_history == 1) {
				$order['comments'] .= "<br />" . JText::sprintf('VMPAYMENT_PAYPAL_EMAIL_SENT');
				$order['customer_notified'] = 0;
			} else {
				$order['customer_notified'] = 1;
			}
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
			if ($nb_history == 1) {
			if (!class_exists('shopFunctionsF'))
				require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
			shopFunctionsF::sentOrderConfirmedEmail($orderitems);
			$this->logInfo('Notification, sentOrderConfirmedEmail ' . $order_number. ' '. $new_status, 'message');
			}
		}
		//// remove vmcart
		$this->emptyCart($return_context);
    }

      /**
     * plgVmOnPaymentResponseReceived
     * This event is fired when the  method returns to the shop after the transaction
     *
     *  the method itself should send in the URL the parameters needed
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @param int $virtuemart_order_id : should return the virtuemart_order_id
     * @param text $html: the html to display
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Valerie Isaksen
     *
     *
      function plgVmOnPaymentResponseReceived(, &$virtuemart_order_id, &$html) {
      return null;
      }
     */
		 // retorno da transação para o pedido específico
	 function plgVmOnPaymentResponseReceived(&$html) {

		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

		$vendorId = 0;
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		if (!class_exists('VirtueMartCart'))
				require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		$payment_data = JRequest::get('post');
		$payment_name = $this->renderPluginName($method);
		$html = $this->_getPaymentResponseHtml($payment_data, $payment_name);

		if (!empty($payment_data)) {
			vmdebug('plgVmOnPaymentResponseReceived', $payment_data);
			$order_number = $payment_data['invoice'];
			$return_context = $payment_data['custom'];
			if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

			$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
			$payment_name = $this->renderPluginName($method);
			$html = $this->_getPaymentResponseHtml($payment_data, $payment_name);

			if ($virtuemart_order_id) {

			// send the email ONLY if payment has been accepted
			if (!class_exists('VirtueMartModelOrders'))
				require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

			$modelOrder = new VirtueMartModelOrders();
			$orderitems = $modelOrder->getOrder($virtuemart_order_id);
			$nb_history = count($orderitems['history']);
			//vmdebug('history', $orderitems);
			if (!class_exists('shopFunctionsF'))
				require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
			if ($nb_history == 1) {
				if (!class_exists('shopFunctionsF'))
				require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
				shopFunctionsF::sentOrderConfirmedEmail($orderitems);
				$this->logInfo('plgVmOnPaymentResponseReceived, sentOrderConfirmedEmail ' . $order_number, 'message');
				$order['order_status'] = $orderitems['items'][$nb_history - 1]->order_status;
				$order['virtuemart_order_id'] = $virtuemart_order_id;
				$order['customer_notified'] = 0;
				$order['comments'] = JText::sprintf('VMPAYMENT_PAYPAL_EMAIL_SENT');
				$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
			}
			}
		}
		$cart = VirtueMartCart::getCart();
		//We delete the old stuff
		// get the correct cart / session
		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		return true;
		} 
}

// No closing tag
