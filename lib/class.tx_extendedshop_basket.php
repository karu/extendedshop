<?php
/*
 * Created on 9-ago-2007
/***************************************************************
*  Copyright notice
*  
*  (c) 2007 Luca Del Puppo for Webformat srl (luca.delpuppo@webformat.com)
*  (c) 2007 Mauro Lorenzutti for Webformat srl (mauro.lorenzutti@webformat.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is 
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
* 
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
* 
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/** 
 * Plugin 'Webformat Shop System' for the 'extendedshop' extension.
 * 
 * @author	Mauro Lorenzutti for Webformat srl <mauro.lorenzutti@webformat.com>
 * @author	Luca Del Puppo for Webformat srl <info@webformat.com>
 */
 
global $extendedshop_warnings;

require_once(t3lib_extMgm::extPath('extendedshop')."/lib/class.tx_extendedshop_products.php");

class tx_extendedshop_basket {

	var $basket = array();
	var $conf = array();
	var $parent;
	var $recs = Array (); 	// in initBasket this is set to the recs-array of fe_user.
	var $basketExtra; 		// init() uses this for additional information like the current payment/shipping methods
	
	var $total_products = 0;
	var $total_products_notax = 0;
	var $orig_shipping_cost = 0;
	var $orig_shipping_cost_no_tax = 0;
	var $freeshipping = false;
	
	/**
	 * Function used to initialize the basket array
	 */
	function init($parent){
		global $extendedshop_warnings;
		
		// set in basket the cookie array
		$this->basket = $GLOBALS["TSFE"]->fe_user->getKey("ses", "recs");
		$this->conf = $parent->conf;
		$this->parent = $parent;
		
		if ($GLOBALS['TSFE']->fe_user->user['uid']!='')
			$this->basket['loggedInUser'] = $GLOBALS['TSFE']->fe_user->user['uid'];
		elseif ($this->basket['loggedInUser']>0 && $this->conf['clearBasketOnLogout']=='')
			unset($this->basket);
		

		$products = $this->parent->piVars['product'];
		$basket_products = $this->parent->piVars['basket_product'];			

		// insert in array
		if (is_array($products)) {
			$extendedshop_warnings = array();
			$autoinc = $this->lastKey($this->basket['products']) + 1;
			foreach ($products as $prod) {
				
				//if (!$this->alreadyInBasket($this->basket['products'], $prod["timestamp"])){
					if (t3lib_div :: testInt($prod["uid"])) {
						$count = t3lib_div :: intInRange($prod["num"], 0, 100000);
						if ($count>0) {
							$count = $this->checkMaxItems($count, $prod["uid"]);
							$this->basket['products'][$autoinc] = $prod;
							$this->basket["products"][$autoinc]["num"] = $count;
							for ($i = 1; $i <= $prod["combinations"]; $i++) {
								$this->basket["products"][$autoinc]["sizes"][$i] = $prod["sizes"][$i];
								$this->basket["products"][$autoinc]["colors"][$i] = $prod["colors"][$i];
							}
						}
					}
				//}
			}
		}
		// update the basket when the quantity is changed
		if (is_array($basket_products)) {
			$extendedshop_warnings = array();
			foreach ($basket_products as $arrayKey => $b_prod) {
				if (t3lib_div :: testInt($b_prod["uid"])) {
						$basket_products[$arrayKey]["num"] = t3lib_div :: intInRange($basket_products[$arrayKey]["num"], 0, 100000);
						if ($basket_products[$arrayKey]["num"]>=0)	{
							$basket_products[$arrayKey]["num"] = $this->checkMaxItems($basket_products[$arrayKey]["num"], $b_prod["uid"], $arrayKey);
							$this->basket["products"][$arrayKey]["num"] = $basket_products[$arrayKey]["num"];
							$this->basket["products"][$arrayKey]["combinations"] = $basket_products[$arrayKey]["combinations"];
							for ($i = 1; $i <= $b_prod["combinations"]; $i++) {
								$this->basket["products"][$arrayKey]["sizes"][$i] = $basket_products[$arrayKey]["sizes"][$i];
								$this->basket["products"][$arrayKey]["colors"][$i] =$basket_products[$arrayKey]["colors"][$i];
							}
						}
					} 
				}
			}
		
		// Management of personal data
		$new = $this->parent->piVars['new'];
		$datiPersonali = $this->parent->piVars['datiPersonali'];

		$personal = $this->parent->piVars['personal'];
		$delivery = $this->parent->piVars['delivery'];
		$clearPerson = $this->parent->piVars['clearPerson'];
		
		
		if (is_array($personal))	{
			if (isset($personal['AUTHORIZATION']))
				$this->basket['personinfo']['AUTHORIZATION'] = $personal['AUTHORIZATION'];
			else
				$this->basket['personinfo']['AUTHORIZATION'] = '';
			if (isset($personal['CONDITIONS']))
				$this->basket['personinfo']['CONDITIONS'] = $personal['CONDITIONS'];
			else
				$this->basket['personinfo']['CONDITIONS'] = '';
		}
		
		
		if ($this->conf['enableUserManagement']==1)	{
			if ($clearPerson!="")	{
				$this->resetPersonalInfo();
			}	elseif ($GLOBALS["TSFE"]->loginUser != "")	{
				$this->setPersonInfoFromLoggedUser();
			}
		}	else	{
			if ($clearPerson!="")	{
			$this->resetPersonalInfo();
			}	elseif (is_array($personal))	{
				foreach ($personal as $key => $value)
					$this->basket["personinfo"][$key] = strip_tags($value);	
			}	elseif (!is_array($this->basket["personinfo"]) && $GLOBALS["TSFE"]->loginUser != "")	{
				$this->setPersonInfoFromLoggedUser();
			}
		}
		
		
		if ($personal["NOTE"] != "")
			$this->basket["personinfo"]["NOTE"] = strip_tags($personal["NOTE"]);
		
		// crea l'array delivery nel basket'
		if (is_array($delivery)){
			foreach ($delivery as $key => $value)
				$this->basket["delivery"][$key] = $value;
		}

		if ($datiPersonali && $this->parent->piVars["delivery"]["more"]!=1)
			$this->basket["delivery"]["more"] = 0;
			
		// Saving shipping and payment settings	
		if ($this->conf['enableStaticInfoTable']==0)	{
			if ($this->basket["delivery"]["COUNTRY"] != "" && $this->basket["delivery"]["more"]==1)
				$this->basket["shipping"] = $this->basket["delivery"]["COUNTRY"];
			else
				$this->basket["shipping"] = $this->basket["personinfo"]["COUNTRY"];
		}	else	{
			if ($this->parent->piVars['shipping']!="")	{
				$this->basket["shipping"] = $this->parent->piVars['shipping'];
			}
		}
		
		if ($this->parent->piVars['payment']!="")
			$this->basket["payment"] = $this->parent->piVars['payment'];
		
		// Hook that can be used to save custom fields in the cookie
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['save_custom_basket_fields']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['save_custom_basket_fields'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $this->basket = $_procObj->saveCustomFieldsInBasket($this->basket, $this->parent);
		    }
		}
		
		
		// write in cookie
		$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basket);
		//t3lib_div::debug($this->basket,'BASKET COOKIE');
		$this->total_products = $this->calculateTotalProducts();
		$this->total_products_notax = $this->calculateTotalProductsNoTax();
	}

	/**
	 * Setting shipping and payment methods
	 */
	function setBasketExtras($basket) {
		// shipping
		ksort($this->conf["shipping."]);
		reset($this->conf["shipping."]);
		$k = intval($this->basket["shipping"]["key"]);
		if (!$this->checkExtraAvailable("shipping", $k)) {
			$k = intval(key($this->cleanConfArr($this->conf["shipping."], 1)));
		}
		//$this->basket["shipping"] = $k;
		// Forse tutto il resto non serve
		$this->basketExtra["shipping"] = $k;
		$this->basketExtra["shipping."] = $this->conf["shipping."][$k . "."];
		$excludePayment = trim($this->basketExtra["shipping."]["excludePayment"]);

		// payment
		if ($excludePayment) {
			$exclArr = t3lib_div :: intExplode(",", $excludePayment);
			while (list (, $theVal) = each($exclArr)) {
				unset ($this->conf["payment."][$theVal]);
				unset ($this->conf["payment."][$theVal . "."]);
			}
		}
		
		ksort($this->conf["payment."]);
		reset($this->conf["payment."]);
		$k = $this->basket["payment"]["key"];
		if (!$this->checkExtraAvailable("payment", $k)) {
			$k = intval(key($this->cleanConfArr($this->conf["payment."], 1)));
		}
		//$this->basket["payment"] = $k;
		// forse le due istruzioni sotto non servono
		$this->basketExtra["payment"] = $k;
		$this->basketExtra["payment."] = $this->conf["payment."][$k . "."];

		//t3lib_div::debug($this->basketExtra);
		//		debug($this->conf);
	}
	
	
	/**
	 * This function returns the warnings created in the init function and can be used to show 
	 * this warnings in the basket and in the minibasket
	 */
	function getWarnings()	{
		global $extendedshop_warnings;
		
		if (is_array($extendedshop_warnings) && count($extendedshop_warnings)>0)
			return $extendedshop_warnings;
		else
			return false;
	}
	
	
	function checkMaxItems($count, $uid, $updateKey = 0)	{
		global $extendedshop_warnings;
		
		if (!$count>0)
			return 0;
		$limit = tx_extendedshop_products::getProductLimit($uid, $this->parent->conf);
		$inbasket = 0;
		
		if (is_array($this->basket['products']))	{
			foreach ($this->basket['products'] as $key => $prod)	{
				if ($prod['uid']==$uid)	{
					if ($updateKey==0 || ($updateKey>0 && $updateKey!=$key))
						$inbasket += $prod['num'];
				}
			}
		}
		
		if ($count > ($limit - $inbasket))	{
			$count = $limit - $inbasket;
			$error = array('error' => 'limit', 'uid' => $uid, 'limit' => $limit);
			$extendedshop_warnings[] = $error;
		}

		return $count;		
	}
	
	
	
	/**
	 * set the personinfo array with the logged-in user
	 */
	function setPersonInfoFromLoggedUser(){
		$user = $GLOBALS["TSFE"]->fe_user->user;
		$this->basket["personinfo"]["NAME"] = strip_tags($user["name"]);
		$this->basket["personinfo"]["ADDRESS"] = strip_tags($user["address"]);
		$this->basket["personinfo"]["CITY"] = strip_tags($user["city"]);
		$this->basket["personinfo"]["ZIP"] = strip_tags($user["zip"]);
		$this->basket["personinfo"]["STATE"] = strip_tags($user["tx_extendedshop_state"]!="" ? $user["tx_extendedshop_state"] : $user["zone"]);
		$this->basket["personinfo"]["COUNTRY"] = strip_tags($user["static_info_country"]!="" ? $user["static_info_country"] : $user["country"]);
		$this->basket["personinfo"]["COMPANY"] = strip_tags($user["company"]);
		$this->basket["personinfo"]["VATCODE"] = strip_tags($user["tx_extendedshop_vatcode"]);
		$this->basket["personinfo"]["PRIVATE"] = strip_tags($user["tx_extendedshop_private"]);
		$this->basket["personinfo"]["WWW"] = strip_tags($user["www"]);
		$this->basket["personinfo"]["PHONE"] = strip_tags($user["telephone"]);
		$this->basket["personinfo"]["MOBILE"] = strip_tags($user["tx_extendedshop_mobile"]);
		$this->basket["personinfo"]["FAX"] = strip_tags($user["fax"]);
		$this->basket["personinfo"]["EMAIL"] = strip_tags($user["email"]);
		//$this->basket["personinfo"]["NEW"] = 0;
	}
	
	
	/**
	 * reset the personal info
	 */
	function resetPersonalInfo($setCookie=false) {
		unset($this->basket["delivery"], $this->basket["personinfo"]);
		if ($setCookie)
			$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basket);
	}
	
	function checkExtraAvailable($name, $key) {
		if (is_array($this->conf[$name . "."][$key . "."]) && (!isset ($this->conf[$name . "."][$key . "."]["show"]) || $this->conf[$name . "."][$key . "."]["show"])) {
			return true;
		}
	}

	function cleanConfArr($confArr, $checkShow = 0) {
		$outArr = array ();
		if (is_array($confArr)) {
			reset($confArr);
			while (list ($key, $val) = each($confArr)) {
				if (!t3lib_div :: testInt($key) && intval($key) && is_array($val) && (!$checkShow || $val["show"] || !isset ($val["show"]))) {
					$outArr[intval($key)] = $val;
				}
			}
		}
		ksort($outArr);
		reset($outArr);
		return $outArr;
	}
	
	
	/**
	 * Returns the products array
	 */
	function getProduct(){
		return $this->getBasketProducts();
	}
	
	
	/**
	 * This function returns the total number of products in the basket
	 */
	function getNumberOfProductsInBasket()	{
		$prod = $this->getProduct();
		if (is_array($prod))	{
			$num = 0;
			foreach ($prod as $key => $value)
				$num += $value['num'];
			return $num;
		}	else	{
			return 0;
		}
	}
	
	
	/**
	 * This function returns the number of rows in the basket/order
	 */
	function getNumRows()	{
		$prod = $this->getProduct();
		if (is_array($prod))	{
			$num = 0;
			foreach ($prod as $key => $value)
				$num ++;
			return $num;
		}	else	{
			return 0;
		}
	}
	
	
	/**
	 * This function is used to retrive the products total amount
	 */
	function getProductsTotal(){
		if ($this->total_products<1)
			$this->total_products = $this->calculateTotalProducts();
			
		return $this->total_products;
	}
	
	/**
	 * This function is used to calculate the products total amount (PRIVATE)
	 */
	function calculateTotalProducts()	{
		$total = 0;
		$products = $this->getProduct();
		if (!is_array($products))
			return 0;
		/*while (list ($autoinc, $val) = each($products)) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid='.$val['uid'], '', '', '1');
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$prod = t3lib_div::makeInstance(t3lib_div::makeInstanceClassName("tx_extendedshop_products"));
			$prod->init($row, $this, $res);
			$total += $prod->getPriceOffer() * $val['num'];
		} */
		
		foreach ($products as $key => $value){
			
			$product_user_info = tx_extendedshop_products::getProductUserInfo($value['uid'],'',$this->parent,$this->basket);
			
			$total += $product_user_info['final_price'] * $value['num'];
		}
			
		return $total;
	}
	
	/**
	 * This function is used to retrive the products total amount without taxes (PRIVATE)
	 */
	function calculateTotalProductsNoTax() {
		$total = 0;
		$products = $this->getProduct();
		if (!is_array($products))
			return 0;
		foreach ($products as $key => $value){
			$product_user_info = tx_extendedshop_products::getProductUserInfo($value['uid'],'',$this->parent,$this->basket);
			$total += $product_user_info['final_price_B'] * $value['num'];
		}
			
		return $total;
		
		/* $origin = $this->getProductsTotal();
		$tax = $this->conf["taxPercent"];
		$tax = $tax/100;
		return round(($origin/(1+$tax)),2); */
	}
	
	
	/**
	 * This function is used to retrive the products total amount without taxes 
	 */
	function getProductsTotalNoTax(){
		return $this->total_products_notax;
	}
	
	/**
	 * This function is used to retrive the shipping amount
	 */
	function getShippingAmount()	{
		return $this->getShippingPriceTax($this->getShipping());
	}
	
	/**
	 * In case of free shipping, you can use this function to know the original shipping costs
	 */
	function getOrigShippingCost()	{
		if ($this->orig_shipping_cost<=0)
			$this->getShippingPriceTax();
		return $this->orig_shipping_cost;
	}
	
	
	
	/**
	 * This function is used to retrive the shipping amount
	 */
	function getShippingAmountNoTax()	{
		return $this->getShippingPriceNoTax($this->getShipping());
	}
	
	/**
	 * In case of free shipping, you can use this function to know the original shipping costs
	 */
	function getOrigShippingCostNoTax()	{
		if ($this->orig_shipping_cost_no_tax<=0)
			$this->getShippingPriceNoTax();
		return $this->orig_shipping_cost_no_tax;
	}
	
	
	/**
	 * This function is used to retrive the payment amount
	 */
	function getPaymentAmount()	{
		return $this->getPaymentPriceTax($this->getPayment());
	}
	
	function lastKey($array)
	{
     if (is_array($array)){
	      end($array);
	      return key($array);
     } else
     	return 0;
	}
	
	function updateSize($autoinc, $newSize){
		$this->basket[$autoinc]['num'] = $newSize;
		return true;
	}
	
	/**
	 * return the product in the basket
	 */
	function getBasketProducts(){
		return $this->basket['products'];
	}
	
	/*
	 * return true if the basket is empty
	 */
	function isEmptyBasket(){
		if (!is_array($this->basket['products']))
			return true;
		else
			return false;
	}
	
	function setCountryInfo($val){
		$this->basket["personinfo"]["COUNTRY"] = $val;
	}

	function setStateInfo($val){
		$this->basket["personinfo"]["STATE"] = $val;
	}	
	
	function clearDelivery(){
		$this->basket["delivery"] = "";
	}
	
	/**
	 * This function returns the array with the details of the customer
	 */
	function getPersonInfo(){
		return $this->basket['personinfo'];
	}
	
	/**
	 * This function returns the array with the details of the delivery address
	 */
	function getDeliveryInfo($switch=0){
		if ($switch && $this->basket['delivery']['more']==0)
			return $this->basket['personinfo'];
		return $this->basket['delivery'];
	}
	
	
	/**
	 * This function returns the uid of the destination country, based on delivery and personinfo data 
	 */
	function getCountryDestinationUid()	{
		if ($this->basket["delivery"]["COUNTRY"] != "" && $this->basket["delivery"]["more"]==1)
			$country = $this->basket["delivery"]["COUNTRY"];
		else
			$country = $this->basket["personinfo"]["COUNTRY"];
		
		if (!t3lib_div::testInt($country) && $country!="")	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'static_countries', 'cn_iso_3="'.$country.'"');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$country = $row['uid'];
				unset($res, $row);
			}
		}
		
		return $country;
	}
	
	
	/**
	 * This function returns the name of the destination country, based on delivery and personinfo data 
	 */
	function getCountryDestination()	{
		if ($this->basket["delivery"]["COUNTRY"] != "" && $this->basket["delivery"]["more"]==1)
			$country = $this->basket["delivery"]["COUNTRY"];
		else
			$country = $this->basket["personinfo"]["COUNTRY"];
		
		if (!t3lib_div::testInt($country) && $country!="")	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('cn_short_en', 'static_countries', 'cn_iso_3="'.$country.'"');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$country = $row['cn_short_en'];
				unset($res, $row);
			}
		}
		
		return $country;
	}
	
	
	
	/**
	 * This function returns the array with the shipping details
	 */
	function getShipping()	{
		return $this->basket['shipping'];
	}
	
	/**
	 * This function sets a shipping method
	 */
	function setShipping($key) {
		$this->basket['shipping'] = $key;
	}
	
	/**
	 * This function sets a payment method
	 */
	function setPayment($key)	{
		$this->basket['payment'] = $key;
	}
	
	/**
	 * This function returns the array with the payment details
	 */
	function getPayment()	{
		if($this->basket['payment'] == '')
			$this->setPayment($this->conf['payment.']['default']);
		return $this->basket['payment'];
	}
	
	
	/**
	 * This function remove all the products from the basket
	 */
	function emptyProducts()	{
		unset($this->basket['products']);
		$this->total_products = 0;
		$this->total_products_notax = 0;
		//$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basket);
	}
	
	
	/**
	 * This function is used to clear shipping and payment selections
	 */
	function clearShippingAndPayment()	{
		unset($this->basket['shipping'], $this->basket['payment'], $this->basket['additional_payment_data'], $this->basket['personinfo']['NOTE']);
	}
	
	
	/**
	 * This function returns the title of the selected shipping method
	 * @param int $key: The shipping key
	 * @return string The shipping title
	 */
	function getShippingTitle($key=''){
		if ($key=='')
			$key = $this->getShipping();
		if ($this->conf['enableStaticInfoTable'] == 0){
			return $this->conf["shipping."][$key."."]["title"];
		} elseif (t3lib_div::testInt($key)) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_shipping', 'uid="'.$key.'"', '', '');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				return $row["title"];
			}	else	{
				return '';
			}
		}	else	{
			$title = "";
			// Hook that can be used to modify the shipping confArray before showing it to the user
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods']))    {
			    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods'] as $_classRef)    {
			        $_procObj = &t3lib_div::getUserObj($_classRef);
			        $title = $_procObj->getShippingTitle($key, $this->parent);
			        if ($title!="")
			        	return $title;
			    }
			}
			return $title;
		}
	}
	
	

	/**
	 * This function returns the shipping cost
	 */
	function getShippingPriceTax($key){
		$price = 0;
		
		if ($this->conf['enableStaticInfoTable'] == 0){
			$price = $this->conf["shipping."][$key."."]["priceTax"];
		} elseif (t3lib_div::testInt($key)) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_shipping', 'uid="'.$key.'"', '', '');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$price = $row["price"];
			}
		}	else	{
			// Hook that can be used to modify the shipping confArray before showing it to the user
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods']))    {
			    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods'] as $_classRef)    {
			        if ($price==0)	{
			        	$_procObj = &t3lib_div::getUserObj($_classRef);
			        	$price = $_procObj->getShippingPriceTax($key, $this->parent);
			    	}
			    }
			}
		}
		
		$this->orig_shipping_cost = $price;
		
		$this->freeshipping = false;
		// Hook that can be used to modify the shipping confArray before showing it to the user
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_free_shipping']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_free_shipping'] as $_classRef)    {
	        	$_procObj = &t3lib_div::getUserObj($_classRef);
	        	$this->freeshipping = $_procObj->checkFreeShipping($key, $this->getProductsTotal(), $this->parent);
	        	if ($this->freeshipping)	{
	        		return 0;
	        	}
		    }
		}
		elseif (intval($this->conf['freeDelivery'])<=$this->getProductsTotal())	{
			$this->freeshipping = true;
			return 0;
		}
		return $price;
	}
	
	
	function isFreeShipping()	{
		return $this->freeshipping;
	}
	
	
	
	/**
	 * This function returns the shipping cost VAT excluded
	 */
	function getShippingPriceNoTax($key){
		$price = 0;
		if ($this->conf['enableStaticInfoTable'] == 0){
			$price = $this->conf["shipping."][$key."."]["priceNoTax"];
		} elseif (t3lib_div::testInt($key)) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_shipping', 'uid="'.$key.'"', '', '');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($row["pricenotax"]>0)
					$price = $row['pricenotax'];
				else	{
					$price = $row['price'];
					$price = $price / (1+($this->conf["taxPercent"]/100));
				}
			}
		}	else	{
			// Hook that can be used to modify the shipping confArray before showing it to the user
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods']))    {
			    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods'] as $_classRef)    {
			        if ($price==0)	{
			        	$_procObj = &t3lib_div::getUserObj($_classRef);
			        	$price = $_procObj->getShippingPriceNoTax($key, $this->parent);
			        }
			    }
			}
		}
		
		$this->orig_shipping_cost_no_tax = $price;
		
		$freeshipping = false;
		// Hook that can be used to modify the shipping confArray before showing it to the user
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_free_shipping']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_free_shipping'] as $_classRef)    {
	        	$_procObj = &t3lib_div::getUserObj($_classRef);
	        	$freeshipping = $_procObj->checkFreeShipping($key, $this->getProductsTotal(), $this->parent);
	        	if ($freeshipping)
	        		return 0;
		    }
		}
		elseif (intval($this->conf['freeDelivery'])<=$this->getProductsTotal())
			return 0;
			
		return $price;
	}
	
	
	
	
	function getCountryCode($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'static_countries', 'cn_short_en="'.$key.'"', '', '', '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row["cn_iso_3"];
	}
	
	function getCountryName($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'static_countries', 'cn_iso_3="'.$key.'"', '', '', '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row["cn_short_en"];
	}
	
	
	function getPaymentTitle($key){
		return $this->conf["payment."][$key."."]["title"];
	}
	
	function getPaymentPriceTax($key, $perc = 0){
		return $this->conf["payment."][$key."."]["priceTax"] + ($perc/100) * $this->conf["payment."][$key."."]["priceTax"];
	}
	
	function getPaymentPriceNoTax($key){
		
		if ($this->conf["payment."][$key."."]["priceNoTax"]>0){
			return $this->conf["payment."][$key."."]["priceNoTax"];
		}
		else	{
			$price = $this->conf["payment."][$key."."]["priceTax"];
			$price = $price / (1 + $this->conf["taxPercent"]/100);
			return $price;
		}
	}
	
	function getPaymentBankcode($key){
		return $this->conf["payment."][$key."."]["bankcode"];
	}	
	
	function getPaymentMessage($key){
		return $this->conf["payment."][$key."."]["message"];
	}		
	
	function setBasketInfo($array, $type){
		$this->basket[$type] = $array;
		$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basket);
	}
	
	function setPercent($perc){
		$this->basket["perc"] = $perc;
		$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basket);
	}
	
	function getPercent(){
		$value = $this->basket["perc"];
		if ($value != "")
			return (int)$value;
		else
			return 0;
	}
	
	/**
	 * Calculate the total basket price
	 * @param int $perc is the optional percentual to sum to the total price
	 */
	function getTotalPrice($perc=0){
		$total = 0;
		//t3lib_div::debug($products);
		$total = $this->getProductsTotal();
		if(tx_extendedshop_pi1::getUserType($this->conf,$this->basket) == 1){
			$total += $this->getShippingPriceNoTax($this->getShipping());
			$total += $this->getPaymentPriceNoTax($this->getPayment());
		}
		else{
			$total += $this->getShippingPriceTax($this->getShipping());
			$total += $this->getPaymentPriceTax($this->getPayment());
		}
		$total += ($perc/100)*$total;
		
		return $total;
		
	}
	
	/**
	 * Calculate the total price of the basket without tax (setted in TS)
	 */
	function getTotalPriceNoTax(){
		$total = 0;
		//t3lib_div::debug($products);
		$total = $this->getProductsTotalNoTax();
		if(tx_extendedshop_pi1::getUserType($this->conf,$this->basket) == 1){
			$total += $this->getShippingPriceTax($this->getShipping());
			$total += $this->getPaymentPriceTax($this->getPayment());
		}
		else{
			$total += $this->getShippingPriceNoTax($this->getShipping());
			$total += $this->getPaymentPriceNoTax($this->getPayment());
		}
		return $total;
	}
	
	/**
	 * Get the percentual amount
	 */
	function getPercentAmount($amount, $percent){
		return ($percent/100) * $amount;
	}
	
	/**
	 * Verify if a product is inserted jet in the array
	 * @param array $array the array to inspect
	 * @param int $timestamp timestamp that permit to understand if the element is inserted jet or not. It is a key
	 */
	 function alreadyInBasket($array, $timestamp){
	 	$result = false;
	 	if (is_array($array)){
		 	foreach ($array as $element) {
		 		if ($element["timestamp"] == $timestamp){	
		 			return $result = true;
		 		}
		 	}
	 	}
	 	return $result;
	 }
	 
	 
	 /**
	  * Function used to calculate the total weight of all the products in the cart
	  */
	 function getTotalWeight()	{
	 	$totalWeight = 0;
	 	$products = $this->getBasketProducts();
	 	if (is_array($products))	{
	 		foreach ($products as $prod)	{
	 			if (is_array($prod) && $prod['uid']>0 && $prod['num']>0 && t3lib_div::testInt($prod['uid']) && t3lib_div::testInt($prod['num']))	{
	 				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid="'.$prod['uid'].'" '.$this->parent->cObj->enableFields('tx_extendedshop_products'));
	 				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
	 					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	 					$product = t3lib_div::makeInstance("tx_extendedshop_products");
						$product->init($row, $this->parent, $res);
						$totalWeight += $product->getRowWeight($prod['num']);
	 				}
	 			}
	 		}
	 	}
	 	
	 	return $totalWeight;
	 }
	 
	 /**
	  * Function used to calculate the total volume of all the products in the cart
	  */
	 function getTotalVolume()	{
	 	$totalVolume = 0;
	 	$products = $this->getBasketProducts();
	 	if (is_array($products))	{
	 		foreach ($products as $prod)	{
	 			if (is_array($prod) && $prod['uid']>0 && $prod['num']>0 && t3lib_div::testInt($prod['uid']) && t3lib_div::testInt($prod['num']))	{
	 				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid="'.$prod['uid'].'" '.$this->parent->cObj->enableFields('tx_extendedshop_products'));
	 				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
	 					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	 					$product = t3lib_div::makeInstance("tx_extendedshop_products");
						$product->init($row, $this->parent, $res);
						$totalVolume += $product->getRowVolume($prod['num']);
	 				}
	 			}
	 		}
	 	}
	 	return $totalVolume;
	 }
	 
}


if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_basket.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_basket.php"]);
}

?>
