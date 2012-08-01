<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 2004 Mauro Lorenzutti for Webformat srl (mauro.lorenzutti@webformat.com)
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
 */

require_once (PATH_tslib . "class.tslib_pibase.php");
require_once (PATH_t3lib . "class.t3lib_parsehtml.php");
require_once (PATH_t3lib . "class.t3lib_htmlmail.php");
require_once(t3lib_extMgm::extPath('extendedshop')."/lib/class.tx_extendedshop_products.php");
require_once(t3lib_extMgm::extPath('extendedshop')."/lib/class.tx_extendedshop_basket.php");
require_once(t3lib_extMgm::extPath('extendedshop')."/lib/class.tx_extendedshop_order.php");
require_once(t3lib_extMgm::extPath('extendedshop')."/pi1/class.tx_extendedshop_ordersmanagement.php");
require_once(t3lib_extMgm::extPath('extendedshop')."/lib/class.tx_extendedshop_usersmanagement.php");
if (t3lib_extMgm::isLoaded('toi_category'))
	require_once(t3lib_extMgm::extPath('toi_category')."api/class.tx_toicategory_api.php");


class tx_extendedshop_pi1 extends tslib_pibase {
	var $prefixId = "tx_extendedshop_pi1"; // Same as class name.
	var $scriptRelPath = "pi1/class.tx_extendedshop_pi1.php"; // Path to this script relative to the extension dir.
	var $extKey = "extendedshop"; // The extension key.

	var $cObj = ""; // The backReference to the mother cObj object set at call time
	var $conf = ""; // The extension configuration
	var $config = ""; // The personalized configuration
	// Internal
	var $pid_list = "";
	var $uid_list = ""; // List of existing uid's from the basket, set by initBasket()
	var $categories = array (); // Is initialized with the categories of the shopping system
	var $pageArray = array (); // Is initialized with an array of the pages in the pid-list
	var $orderRecord = array (); // Will hold the order record if fetched.

	var $globalMarkerArray = ""; // Marker Array to substitute
	var $langMarkerArray = ""; // Marker Array for localization

	var $total;
	var $basketRef = array();
	var $basketExtra; // initBasket() uses this for additional information like the current payment/shipping methods
	var $finalize; // Set by show_finalize() when clears the basket
	var $orderBy;
	var $addQueryEnableStock; // This adds a where clause to check if product is available (if the stocking management is enabled (from constant editor))
	var $addQueryLanguage;	// This adds a where clause if "Hide default translation of page" (a page option) is enabled
	
	var $errorPayment = false;	
	var $errorShipping = false;
	
	var $usersManagementObj;  // Users functions library
	var $doNotFinalize = 0;
	
	var $xajax;		// XAJAX object
	
	
	/**
	 * This is an Extended Shop System.
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		
		// Raccolta dei possibili valori di input		
		$isEmpty = true;
		$clear = $this->piVars['clear'];
		$proceed = $this->piVars['proceed'];
		$datiPersonali = $this->piVars['datiPersonali'];
		$clearPerson = $this->piVars['clearPerson'];
		$new = $this->piVars['new'];
		$shipping = $this->piVars['shipping'];
		$finalize = $this->piVars['finalize'];
		$backFromBank = false;

		//Query adding
		// String added for query in different enable system and different languages
		if ($this->conf['enable_instock_management'] == 1)
			// the instock management is enabled
			$this->addQueryEnableStock = " AND (tx_extendedshop_products.instock > 0 OR tx_extendedshop_products.instock = -999)";
		else
			$this->addQueryEnableStock = "";
		
		$this->addQueryLanguage = " AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";
		
		
		$this->taxPercent = $this->conf["taxPercent"];
		// Load the templateCode
		$this->config["templateCode"] = $this->cObj->fileResource($this->conf["templateFile"]);
		
		if ($this->conf["cssFile"] != "")	{
			$this->config['cssCode'] = $this->cObj->fileResource($this->conf["cssFile"]);
			$GLOBALS["TSFE"]->setCSS($this->extKey, $this->config['cssCode']);
		}
		
		$this->config["limit"] = t3lib_div :: intInRange($this->conf["limit"], 0, 1000);
		$this->config["limit"] = $this->config["limit"] ? $this->config["limit"] : 50;

		$this->config["pid_list"] = trim($this->cObj->stdWrap($this->conf["pid_list"], $this->conf["pid_list."]));
		$this->config["pid_list"] = $this->config["pid_list"] ? $this->config["pid_list"] : $GLOBALS["TSFE"]->id;

		if ($this->piVars['pid_product'] != "")
			$this->config["pid_list"] = (int) $this->piVars['pid_product']; 

		$this->config["recursive"] = $this->cObj->stdWrap($this->conf["recursive"], $this->conf["recursive."]);
		$this->config["storeRootPid"] = $this->conf["PIDstoreRoot"] ? $this->conf["PIDstoreRoot"] : $GLOBALS["TSFE"]->tmpl->rootLine[0][uid];

		// Evaluate the visualization code for actual page
		$this->config["code"] = strtolower(trim($this->cObj->stdWrap($this->conf["code"], $this->conf["code."])));
		$codes = t3lib_div :: trimExplode(",", $this->config["code"] ? $this->config["code"] : $this->conf["defaultCode"], 1);
		
		$this->pi_initPIflexForm();
		$FXConf = array ();

		if (is_array($this->cObj->data['pi_flexform']['data'])) {
			foreach ($this->cObj->data['pi_flexform']['data'] as $sheet => $data)
				foreach ($data as $lang => $value)
					foreach ($value as $key => $val) {
						$FXConf[$key] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key, $sheet);
					}
		}
		if ($FXConf['view_mode'] != "") {
			$codes = array ();
			$codes[] = $FXConf['view_mode'];
		}

		// Check code from insert plugin (CMD= "singleView")
		if ($this->conf["CMD"] == "singleView")
			$codes[] = "SINGLE";

		// Pid of the basket
		$this->config["pid_basket"] = trim($this->conf["pid_basket"]);
		$this->config["pid_orders"] = trim($this->conf["pid_orders"]);

		//$this->setPidlist($this->config["storeRootPid"]);
		$this->pid_list = $this->config["pid_list"];
		$this->initRecursive($this->config["recursive"]);
		$this->generatePageArray();
		//t3lib_div::debug($GLOBALS["TSFE"]->fe_user->getKey("ses", "recs"));
		$this->initPaymentConfArray();
		
		$this->basketRef = t3lib_div::makeInstance("tx_extendedshop_basket");
		$this->basketRef->init($this);
		
		if (isset ($clear)) {
			// Clear the basket
			$this->basketRef->emptyProducts();
			$this->basketRef->clearShippingAndPayment();
			$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basketRef->basket);
		}
		
		
		// Hook that can be used to do register parameters to be intercepted to decide if user is coming back from an online payment
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        //$_procObj->init($this->cObj, $this->basketRef, $this);
		        $backFromBank = $_procObj->checkReturnValues();
		    }
		}

//t3lib_div::debug($this->conf['payment.']);
//t3lib_div::debug($this->basketRef->getPaymentBankcode($this->basketRef->getPayment()));
		// analize the required fields
		$this->usersManagementObj = t3lib_div::makeInstance("tx_extendedshop_usersmanagement");
		$this->usersManagementObj->init($this->conf, $this);
		$requiredOK = $this->usersManagementObj->checkRequired();
		$show_required_message = false;
		
		// Initialize the XAJAX object
		if (t3lib_extMgm::isLoaded('xajax'))	{
			// Include xaJax
			require_once (t3lib_extMgm::extPath('xajax') . 'class.tx_xajax.php');
			// Make the instance
			$this->xajax = t3lib_div::makeInstance('tx_xajax');
			// nothing to set, we send to the same URI
			//$this->xajax->setRequestURI('xxx');
			// Decode form vars from utf8 ???
			$this->xajax->decodeUTF8InputOn();
			// Encode of the response to utf-8 ???
			$this->xajax->setCharEncoding('utf-8');
			//$this->xajax->setCharEncoding('ISO-8859-1');
			$this->xajax->outputEntitiesOn();
			// To prevent conflicts, prepend the extension prefix
			$this->xajax->setWrapperPrefix($this->prefixId);
			// Do you wnat messages in the status bar?
			$this->xajax->statusMessagesOn();
			// Turn only on during testing
			//$this->xajax->debugOn();
			
			$reqURI = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');
			if (strpos($reqURI, '?')>0)
				$reqURI .= '&no_cache=1';
			else
				$reqURI .= '?no_cache=1';
			$this->xajax->setRequestURI($reqURI);

			// Register the names of the PHP functions you want to be able to call through xajax
			// $xajax->registerFunction(array('functionNameInJavascript', &$object, 'methodName'));
			$this->xajax->registerFunction(array('productPreview', &$this, 'productPreview'));
			$this->xajax->registerFunction(array('productClosePreview', &$this, 'productClosePreview'));
			$this->xajax->registerFunction(array('minibasketUpdate', &$this, 'minibasketUpdate'));
			
			// If this is an xajax request, call our registered function, send output and exit
			$this->xajax->processRequests();
			// Else create javascript and add it to the header output
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId] = $this->xajax->getJavascript(t3lib_extMgm::siteRelPath('xajax'));
		}		

		if ($this->piVars['orderBy'] != "")
			$orderBy = str_replace("_", " ", $this->piVars['orderBy']);
		else
			$orderBy = 'sorting';

		if (!count($codes))
			$codes = array ("");
		while (list (, $theCode) = each($codes)) {
			$theCode = (string) strtoupper(trim($theCode));

			// Overwrites the code (in case of basket)
			if ($theCode == "BASKET"){
				if ( (isset ($new) && !$requiredOK) || (isset ($proceed) || ($datiPersonali == 1 && !isset ($new) && !isset ($clearPerson))) || (isset ($clearPerson)) ) {
					$theCode = "USERINFO";
					$show_required_message = true;
				} elseif ( (isset ($new) || (isset ($shipping) && !isset ($finalize) && !$backFromBank)) || (isset ($finalize) && $this->basketRef->getPaymentBankcode($this->basketRef->getPayment()) != "") ){
					$theCode = "PAYMENT";
				} elseif ( (isset ($finalize) && $this->basketRef->getPaymentBankcode($this->basketRef->getPayment()) == "") || $backFromBank ) {
					$theCode = "FINALIZE";
				}
			}
			
			// This checks if the user wants to clear the basket
			if ($theCode == "USERINFO" && isset($clear))
				$theCode = "BASKET";
			
			// This checks if all the required fields for the customer are filled in, if not it will redirect you to the userinfo page
			if (($theCode == "PAYMENT" || $theCode == "FINALIZE") && !$requiredOK)	{
				if ($this->conf['enableUserManagement']==1)
					$theCode = "USERREGISTER";
				else
					$theCode = "USERINFO";
				$show_required_message = true;
			}
			
			if ($theCode == "FINALIZE" && ($this->basketRef->getShipping()=='' || $this->basketRef->getShipping()=='ok' || $this->basketRef->getPayment()==''))	{
				if ($this->basketRef->getShipping()=='' || $this->basketRef->getShipping()=='ok')
					$this->errorShipping = true;
				if ($this->basketRef->getPayment()=='')
					$this->errorPayment = true;
				$theCode = "PAYMENT";
				unset($finalize);
			}
			
			if ($theCode == "FINALIZE" && isset($finalize) && $this->basketRef->getPaymentBankcode($this->basketRef->getPayment())!="")
				$theCode = "PAYMENT";
				
			if (($theCode == "FINALIZE" || $theCode == "PAYMENT" || $theCode == "USERINFO" || $theCode=="USERREGISTER") && $this->basketRef->isEmptyBasket())
				$theCode = "BASKET";
			
			switch ($theCode) {
				case "BASKET" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					if (is_array($this->basketRef->basket["products"])) {
						$list = array_keys($this->basketRef->basket["products"]);
						$where = "uid IN (";
						foreach ($list as $id) {
							if ($this->basketRef->basket["products"][$id]["num"] > 0) {
								$where .= ((int) $this->basketRef->basket["products"][$id]["uid"]) . ",";
								$isEmpty = false;
							}
						}
						if (!$isEmpty) {
							$where = substr($where, 0, -1) . ")";
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', $where, '', '' . $orderBy, '' . $this->config["limit"]);
							
							$basketArray = $this->basketRef->getBasketProducts();
							$content .= $this->show_basket($basketArray);
						}
					}
					if ((!is_array($this->basketRef->basket["products"]) || $isEmpty) && !$this->finalize) {
						$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###EMPTY_BASKET_TEMPLATE###"));
						$content = $this->manageLabels($template);
					}
					$this->finalize = false;
					break;
				case "USERINFO" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					//t3lib_div::debug($this->basketRef->basket);
					if (isset ($new) && !$requiredOK) {
						$isEmpty = false;
						$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###PERSONAL_INFO_TEMPLATE###"));
						$content .= $this->show_personal_info($template, false);
					} elseif (isset ($proceed) || ($datiPersonali == 1 && !isset ($new) && !isset ($clearPerson))) {
						// Go to personal info page
						$isEmpty = false;
						$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###PERSONAL_INFO_TEMPLATE###"));
						$content .= $this->show_personal_info($template);
					} elseif (isset ($clearPerson)) {
							// Go to personal info page							
							$isEmpty = false;
							//$this->basketRef->resetPersonalInfo(true);
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###PERSONAL_INFO_TEMPLATE###"));
							$content .= $this->show_personal_info($template);
						} else {
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###PERSONAL_INFO_TEMPLATE###"));
							$content .= $this->show_personal_info($template);
						}
					
					
					break;
				case "USERREGISTER" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###USER_REGISTRED_TEMPLATE###"));
					$content .= $this->show_personal_info_registred($template, !$show_required_message);
					break;
				case "PAYMENT" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					if (isset ($new) || (isset ($shipping) && !isset ($finalize) && !$backFromBank)) {
						// Go to personal info page
						$isEmpty = false;
						$content .= $this->show_payment();
					} elseif (isset ($finalize) && $this->basketRef->getPaymentBankcode($this->basketRef->getPayment()) != "") {
							$isEmpty = false;
							$content .= $this->show_bank();
						} else {
							$isEmpty = false;
							$content .= $this->show_payment();
						}
					break;
				case "FINALIZE" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					if (isset ($finalize) && $this->basketRef->getPaymentBankcode($this->basketRef->getPayment()) == "") {
						$isEmpty = false;
						$content .= $this->show_finalize();
					} elseif ($backFromBank) {
							$isEmpty = false;
							$content .= $this->show_finalize();
						} else {
							$isEmpty = false;
							$content .= $this->show_finalize();
						}
					break;
				case "OFFER" :
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'tx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', '1=1 AND (a.offertprice!=0 OR a.discount!=0) '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock).'AND a.pid IN (' . $this->pid_list . ')', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
	
					if ($this->piVars['productID'] != '') {						
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int) $this->piVars['productID'] .$this->cObj->enableFields('tx_extendedshop_products'). ' AND (offertprice!=0 OR discount!=0) AND pid IN (' . $this->pid_list . ')', '', '', '');
						$content .= $this->show_product($resDetail, $res);
					} else {
					//$template = trim($this->cObj->getSubpart($this->config["templateCode"],"###ITEM_LIST_TEMPLATE###"));
						
						if ($this->conf["list."]["modeImage"] == 1) {
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_IMAGE_TEMPLATE###"));
							$content .= $this->show_image_list($res, $template);
						} else {
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_TEMPLATE###"));
							$content .= $this->show_list($res, $template, true);
						}
					}
					break;
				case "SEARCH" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					if ($this->piVars['productID'] != '') {
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int) $this->piVars['productID'].' AND pid IN (' . $this->pid_list . ')', '', '', '');
						$content .= $this->show_product($resDetail, $res);
					} else {
						
						if ($this->piVars['swords'] != ""){
							// If user search for some words then the result page is shown
							$where = "";
							$searchFields = explode(",", $this->conf["searchFields"]);
							// explode search string in separate words
							$searchWords = explode(" ", $this->piVars['swords']);
							foreach ($searchFields as $field){
								if (count($searchWords) > 1){
									$where .= '(';
									foreach ($searchWords as $word){
										$where .= "tx_extendedshop_products.".$field." LIKE '%".$word."%' AND ";
									}
									$where = substr($where, 0, -5);
									$where .= ')';
								}
								else{
									$where .= "tx_extendedshop_products.".$field." LIKE '%".$searchWords[0]."%'";
								}
								$where .= ' OR ';
							}
							$where = substr($where, 0, -4);
							
							// with a join statment i need only a query
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'tx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', '1=1 '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "b", $this->addQueryEnableStock).'AND a.pid IN (' . $this->pid_list . ') AND (' . str_replace("tx_extendedshop_products", "a", $where) . ')', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
							/*if ($GLOBALS['TSFE']->sys_language_uid == 0){
								// if language is the default is a simple query
								$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', '1=1'. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock.' AND pid IN (' . $this->pid_list . ') AND (' . $where . ')', '', '' . $orderBy, '' . $this->config["limit"]);
							}
							else{
								// is not a default language
								if ($this->hideLanguage){
									//show only translations
									$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.instock, b.*', 'tx_extendedshop_products a LEFT JOIN tx_extendedshop_products b on a.uid = b.l18n_parent', 'b.uid <> ""'. str_replace("tx_extendedshop_products", "b", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "b", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock).'AND a.pid IN (' . $this->pid_list . ') AND (' . str_replace("tx_extendedshop_products", "b", $where) . ')', '', '' . $orderBy, '' . $this->config["limit"]);
								}
							}*/
							if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
								//$template = trim($this->cObj->getSubpart($this->config["templateCode"],$this->spMarker("###ITEM_LIST_TEMPLATE###")));
								//$content.=$this->show_list($res,$template);
								//$content.=$this->show_image_list($res,$template);
								if ($this->conf["list."]["modeImage"] == 1) {
									$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_IMAGE_TEMPLATE###"));
									$content .= $this->show_image_list($res, $template);
								} else {
									$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_TEMPLATE###"));
									$content .= $this->show_list($res, $template, true);
								}
							} else {
								$content .= $this->manageLabels(trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_SEARCH_TEMPLATE###")));
							}
						} else {
							// Else the search form is shown
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_SEARCH_TEMPLATE###"));
							$template = $this->cObj->substituteSubpart($template, "###NORESULTS###", "", $recursive = 0, $keepMarker = 0);
							$content .= $this->manageLabels($template);
						}
					}
					break;

				case "ADVANCEDSEARCH" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					
					// Hook that can be used to manage the advanced search behaviour
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['advancedSearchHook']))    {
					    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['advancedSearchHook'] as $_classRef)    {
					        $_procObj = &t3lib_div::getUserObj($_classRef);
					        $content = $_procObj->processAdvancedSearch($this, $this->conf, $this->config, $this->piVars, $content, $orderBy);
					    }
					}
					else{
						$mS["###S_KEYWORDS###"] = "";
						$mS["###S_SIZE###"] = "";
						$mS["###S_COLORS###"] = "";
						$mS["###FORM_ADVANCEDSEARCH###"] = $this->pi_getPageLink($GLOBALS["TSFE"]->id); //"index.php?id=".$GLOBALS["TSFE"]->id;
						if ($this->piVars['search'] != "") {
								$search = $this->piVars['search'];
								$mS["###S_KEYWORDS###"] = $search['keywords'];
								$mS["###S_SIZE###"] = $search['size'];
								$mS["###S_COLORS###"] = $search['color'];
								$mS["###S_PRICE_" . $search['price'] . "###"] = " selected";
								// If user search for some words then the result page is shown
								$where = '(';
								$searchFields = explode(",", $this->conf["searchFields"]);
								$keywords = explode(' ', $search["keywords"]);
								if (!is_array($keywords))
									$keywords[0] = $search["keywords"];
								foreach ($searchFields as $field) {
									foreach ($keywords as $keyword)
										$where .= 'tx_extendedshop_products.'.$field . ' LIKE \'%' . $GLOBALS['TYPO3_DB']->quoteStr((strip_tags($keyword)), 'tx_extendedshop_products') . '%\' OR ';
								}
								$where = substr($where, 0, -4);
								$where .= ')';
								if ($search["size"] != "")
									$where .= ' AND tx_extendedshop_products.sizes LIKE \'%' . $GLOBALS['TYPO3_DB']->quoteStr((strip_tags($search['size'])), 'tx_extendedshop_products') . '%\'';
								if ($search["color"] != "")
									$where .= ' AND tx_extendedshop_products.colors LIKE \'%' . $GLOBALS['TYPO3_DB']->quoteStr((strip_tags($search['color'])), 'tx_extendedshop_products') . '%\'';
								if ($search["price"] != "_") {
									$range = explode("_", $search["price"]);
									$where .= ' AND tx_extendedshop_products.price >=' . (int)$range[0];
									if ($range[1] != "")
										$where .= ' AND tx_extendedshop_products.price <=' . (int)$range[1];
								}
								$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'tx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', '1=1 '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "b", $this->addQueryEnableStock).'AND a.pid IN (' . $this->pid_list . ') AND (' . str_replace("tx_extendedshop_products", "a", $where) . ')', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
						
								/*if ($GLOBALS['TSFE']->sys_language_uid == 0){
									$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', '1=1'.$this->cObj->enableFields('tx_extendedshop_products').' '.$this->addQueryLanguage.' '.$this->addQueryEnableStock.' AND pid IN (' . $this->pid_list . ') AND '.$where, '', '', '');
									//echo "SELECT * FROM tx_extendedshop_products WHERE 1=1".$this->cObj->enableFields('tx_extendedshop_products').$this->addQueryLanguage.$this->addQueryEnableStock.' AND '.$where;
								}
								else
									$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.uid', 'tx_extendedshop_products a LEFT JOIN tx_extendedshop_products b on a.uid = b.l18n_parent', 'b.uid<>'. str_replace("tx_extendedshop_products", "b", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "b", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock) . 'AND a.pid IN (' . $this->pid_list . ') AND'. str_replace("tx_extendedshop_products", "a", $where), '', ''. $orderBy, '' . $this->config["limit"]);								
							*/
						}
						
						if ($this->piVars['productID'] != '') {
							$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$this->piVars['productID'].' AND pid IN (' . $this->pid_list . ')', '', '', '');
							$content .= $this->show_product($resDetail, $res);
						} else {
							if ($this->piVars['search'] != "") {
								$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
								//$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'deleted<>1 ' . $timeCode . ' ' . $testLingua . ' AND hidden<>1 AND pid IN (' . $this->pid_list . ') AND ' . $where, '', '' . $orderBy, '' . $this->config["limit"]);
								if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
									//$template = trim($this->cObj->getSubpart($this->config["templateCode"],$this->spMarker("###ITEM_LIST_TEMPLATE###")));
									//$content.=$this->show_list($res,$template);
									//$content.=$this->show_image_list($res,$template);
									if ($this->conf["list."]["modeImage"] == 1) {
										$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_IMAGE_TEMPLATE###"));
										$content .= $this->show_image_list($res, $template);
									} else {
										$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_TEMPLATE###"));
										$content .= $this->show_list($res, $template, true);
									}
								} else {
									$content .= $this->manageLabels(trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_ADVANCEDSEARCH_TEMPLATE###")));
								}
							} else {
								// Else the search form is shown
								$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_ADVANCEDSEARCH_TEMPLATE###"));
								$template = $this->cObj->substituteSubpart($template, "###NORESULTS###", "", $recursive = 0, $keepMarker = 0);
								$content .= $this->manageLabels($template);
							}
						}
						$content = $this->cObj->substituteMarkerArray($content, $mS);
						$content = preg_replace("/\#\#\#S\_PRICE\_([0-9]*)\_([0-9]*)\#\#\#/", "", $content);
					}
					break;

				case "SINGLE" :
					// TODO: l'istruzione sotto sar� da togliere. Vedere con Mauro come gestire al meglio la cosa. Anche per tutti coloro che installeranno l'estensione su un progetto gi� esistente.
					$this->addQueryEnableStock = "";
					$this->addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";		
					/*$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'atx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', 'a.uid='.(int)$this->cObj->data["uid"].' '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "b", $this->addQueryEnableStock).'AND a.pid IN (' . $this->pid_list . ')', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
					*/
					
					if ($GLOBALS['TSFE']->sys_language_uid == 0){
						// if language is the default is a simple query
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$this->cObj->data["uid"].' '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock, '', '');
						//if ($row["uid"] != $this->piVars[])
						//echo 'SELECT * FROM tx_extendedshop_products WHERE uid="' . (int)$this->cObj->data["uid"].' '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock;
					}
					else{
						//echo ($GLOBALS['TSFE']->sys_language_uid);
						// if language is different from the default make a join to permit the stock management
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.uid', 'tx_extendedshop_products a LEFT JOIN tx_extendedshop_products b on a.uid = b.l18n_parent', 'b.uid='.(int)$this->cObj->data["uid"].' AND a.pid IN (' . $this->pid_list . ') '. str_replace("tx_extendedshop_products", "b", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "b", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock), '', '', '');
					}
					$content = $this->show_product($resDetail, $resDetail);
					
					break;
				
				case "PRODUCTPAGE" :		
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'tx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', '1=1 '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "b", $this->addQueryEnableStock).'AND a.pid IN (' . $this->pid_list . ')', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
					
					if ($this->piVars['productID'] != '') {
						// Query for the detail
						$this->addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$this->piVars['productID']. ' AND pid IN (' . $this->pid_list . ') '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock, '', '', '');
						$content .= $this->show_product($resDetail, $res);
					} 
					elseif($this->conf['detail_showListIfEmptyProduct']) {
						//$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products a left JOIN tx_extendedshop_products b on a.uid = b.l18n_parent', 'b.uid <> "" AND b.sys_language_uid=1 AND (a.instock >0 OR a.instock = -1)'. $this->cObj->enableFields('tx_extendedshop_products'), '', '', '');
						
						//$strReplaced = str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products'));
						
						if ($this->conf["list."]["modeImage"] == 1) {
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_IMAGE_TEMPLATE###"));
							$content .= $this->show_image_list($res, $template);
						} else {
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_TEMPLATE###"));
							$content .= $this->show_list($res, $template, true);
						}
					}
					
					break;

				case "ORDERSINFO" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					if ($this->piVars["orderID"] != ""){
						$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ORDERSINFO_TEMPLATE_DETAIL###"));
						$ordersManagement = t3lib_div::makeInstance("tx_extendedshop_ordersmanagement");
						$ordersManagement->init($this->conf, $this);
						$content = $ordersManagement->show_orders_list($template);
						$content = $this->manageLabels($content);
					} else {
						$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ORDERSINFO_TEMPLATE###"));
						$ordersManagement = t3lib_div::makeInstance("tx_extendedshop_ordersmanagement");
						$ordersManagement->init($this->conf, $this);
						$content = $ordersManagement->show_orders_list($template);
						$content = $this->manageLabels($content);
					}
					break;
				
				case "PDF_EMAIL_ATTACHMENT" :
					$GLOBALS["TSFE"]->set_no_cache(); // Cache not allowed!
					if ($this->piVars["orderID"] != "" && ($this->piVars['checkHash'] != "" || $GLOBALS['TSFE']->fe_user->user['uid']>0)){
						$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###PDF_EMAIL_ATTACHMENT###"));
						$ordersManagement = t3lib_div::makeInstance("tx_extendedshop_ordersmanagement");
						$ordersManagement->init($this->conf, $this);
						$content = $ordersManagement->show_orders_list($template, $this->piVars['checkHash']);
						$content = $this->manageLabels($content);
					} else {
						$content = 'There has been an error with your order, please contact the customer care.';
					}
					break;
				
				case "CATEGORY" :
					if (!t3lib_extMgm::isLoaded('toi_category'))
						break;
					if ($this->piVars['productID'] != ''  && $this->conf['listModeDoesntIncludeSingleMode']!=1) {
						// Query for the detail
						$this->addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$this->piVars['productID']. ' AND pid IN (' . $this->pid_list . ') '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock, '', '', '');
						$content .= $this->show_product($resDetail, $res);
					}	else	{
						if ($this->piVars['categoryID'] != '')	{
							$categoryQuery = 'AND (a.tx_toicategory_toi_category="'.$this->piVars['categoryID'].'" OR a.tx_toicategory_toi_category LIKE "'.$this->piVars['categoryID'].',%" OR a.tx_toicategory_toi_category LIKE "%,'.$this->piVars['categoryID'].',%" OR a.tx_toicategory_toi_category LIKE "%,'.$this->piVars['categoryID'].'")';
						}	elseif ($FXConf['category']!="")	{
							$cat_array = explode(",",$FXConf['category']);
							$categoryQuery = 'AND (1=0';
							foreach ($cat_array as $cat)	{
								$categoryQuery .= ' OR (a.tx_toicategory_toi_category="'.$cat.'" OR a.tx_toicategory_toi_category LIKE "'.$cat.',%" OR a.tx_toicategory_toi_category LIKE "%,'.$cat.',%" OR a.tx_toicategory_toi_category LIKE "%,'.$cat.'")';
							}
							$categoryQuery .= ')';
						}	else	{
							$categoryQuery = 'AND (a.tx_toicategory_toi_category="'.$GLOBALS['TSFE']->id.'" OR a.tx_toicategory_toi_category LIKE "'.$GLOBALS['TSFE']->id.',%" OR a.tx_toicategory_toi_category LIKE "%,'.$GLOBALS['TSFE']->id.',%" OR a.tx_toicategory_toi_category LIKE "%,'.$GLOBALS['TSFE']->id.'")';
						}
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'tx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', '1=1 '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock). '' . $categoryQuery . '', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
						
						
						if($this->conf['list.']['modeImage'] == 0 ){ // sequential listing
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_TEMPLATE###"));
							$content .= $this->show_list($res, $template, true);
						}
						elseif($this->conf['list.']['modeImage'] == 1){ // table mode
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_IMAGE_TEMPLATE###"));
							$content .= $this->show_image_list($res, $template);
						}
						
						//$content .= $this->show_image_list($res, $template);	
					}
					break;
				
				case "SUPPLIER" :
					if ($this->piVars['productID'] != '') {
						// Query for the detail
						$this->addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$this->piVars['productID']. ' AND pid IN (' . $this->pid_list . ') '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock, '', '', '');
						$content .= $this->show_product($resDetail, $res);
					}
					else {
						if ($this->piVars['supplierID'] != '')
							$supplierQuery = 'AND a.supplier='.$this->piVars['supplierID'];
						else
						$supplierQuery = "";
						
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'tx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', '1=1 '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock). '' . $supplierQuery . '', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
						
						if($this->conf['list.']['modeImage'] == 0 ){ // sequential listing
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_TEMPLATE###"));
							$content .= $this->show_list($res, $template, true);
						}
						elseif($this->conf['list.']['modeImage'] == 1){ // table mode
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_IMAGE_TEMPLATE###"));
							$content .= $this->show_image_list($res, $template);
						}	
					}
					break;
					
				default :
				case "LIST" :
				case "LATEST" :				
					
					if ($theCode=='LATEST')	{
						$orderBy = 'crdate DESC';
						$this->config['limit'] = $FXConf['latest_limit'];
					}
						
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.instock', 'tx_extendedshop_products a LEFT OUTER JOIN tx_extendedshop_products b on a.l18n_parent = b.uid', '1=1 '.str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "a", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock).'AND a.pid IN (' . $this->pid_list . ')', '', '' . "a.".$orderBy, '' . $this->config["limit"]);
					
					if ($this->piVars['productID'] != '' && $this->conf['listModeDoesntIncludeSingleMode']!=1) {
						// Query for the detail
						$this->addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";
						$resDetail = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$this->piVars['productID']. ' AND pid IN (' . $this->pid_list . ') '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock, '', '', '');
						$content .= $this->show_product($resDetail, $res);
					} else {
						//$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products a left JOIN tx_extendedshop_products b on a.uid = b.l18n_parent', 'b.uid <> "" AND b.sys_language_uid=1 AND (a.instock >0 OR a.instock = -1)'. $this->cObj->enableFields('tx_extendedshop_products'), '', '', '');
						
						//$strReplaced = str_replace("tx_extendedshop_products", "a", $this->cObj->enableFields('tx_extendedshop_products'));
						
						if ($this->conf["list."]["modeImage"] == 1) {
							$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_IMAGE_TEMPLATE###"));
							$content .= $this->show_image_list($res, $template);
						} else {
							if ($theCode=='LATEST')
								$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LATEST_TEMPLATE###"));
							else
								$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_LIST_TEMPLATE###"));
							$content .= $this->show_list($res, $template, true);
						}
					}
					break;
			}
		}

		$content = $this->manageLabels($content);
		
		return $this->pi_wrapInBaseClass($this->clearInput($content));
	}
	
	
	
	
	function productPreview($uid)	{
		$content = '';
		
		if (intval($uid))	{
			$this->addQueryEnableStock = "";
			$this->addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";
			
			if ($GLOBALS['TSFE']->sys_language_uid == 0){
				// if language is the default is a simple query
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$uid.' '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock, '', '');
			}
			else{
				// if language is different from the default make a join to permit the stock management
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.uid', 'tx_extendedshop_products a LEFT JOIN tx_extendedshop_products b on a.uid = b.l18n_parent', 'b.uid='.(int)$this->cObj->data["uid"].' AND a.pid IN (' . $this->pid_list . ') '. str_replace("tx_extendedshop_products", "b", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "b", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock), '', '', '');
			}
			
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res))	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_PREVIEW_TEMPLATE###"));
				$subTemplate = trim($this->cObj->getSubpart($template, "###DETAIL###"));
				// prepare the calling to products class
				$markerArray["###PRODUCT_UID###"] = $row["uid"];
				$subTemplate = trim($this->cObj->getSubpart($template, "###DETAIL###"));
				if ($this->conf['insertProduct_nextPage']==0)
					$markerArray["###FORM_ADD###"] = $this->pi_getPageLink($this->conf['pid_basket']);
				else
					$markerArray["###FORM_ADD###"] = $this->pi_linkTP_keepPIvars_url();
				
				if ($this->conf['xajax_cart_update']==1 && t3lib_extMgm::isLoaded('xajax'))	{
					$markerArray['###XAJAX_CART_UPDATE###'] = 'onsubmit="document.getElementById(\''.$this->conf['minibasket_id'].'\').value=\'\'; ' . $this->prefixId . 'minibasketUpdate(xajax.getFormValues(\'tx_extendedshop_pi1_basket_'.$row["uid"].'\')); return false;"';
				}	else	{
					$markerArray['###XAJAX_CART_UPDATE###'] = '';
				}
				$headerTemplate = trim($this->cObj->getSubpart($template, "###HEADER###"));
				$content .= $this->manageLabels($this->cObj->substituteMarkerArray($headerTemplate, $markerArray));
				$prod = t3lib_div::makeInstance("tx_extendedshop_products");
				$prod->init($row, $this, $res);
					
				$content .= $prod->getTemplateProduct("image", false, $subTemplate);
				$content = $this->manageLabels($content);
				$content = preg_replace("/\#\#\#PRODUCT_IMAGE+[0-9]\#\#\#/", "", $content);
			}
		}
		
		//$content = utf8_encode($content);
		$objResponse = new tx_xajax_response();
		$objResponse->setCharEncoding('ISO-8859-1');
		$objResponse->addAssign($this->conf['xajax_preview.']['id'].$uid, 'innerHTML', $content);
		$objResponse->addScript("dettagli('".$this->conf['xajax_preview.']['id'].'preview_'.$uid."');");
		$objResponse->addScript("showCorrelated();");
		return $objResponse->getXML();
	}
	
	
	
	
	function productClosePreview($uid)	{
		$content = '';
		
		if (intval($uid))	{
			$this->addQueryEnableStock = "";
			$this->addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";
			
			if ($GLOBALS['TSFE']->sys_language_uid == 0){
				// if language is the default is a simple query
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . (int)$uid.' '. $this->cObj->enableFields('tx_extendedshop_products') .' '. $this->addQueryLanguage .' '. $this->addQueryEnableStock, '', '');
			}
			else{
				// if language is different from the default make a join to permit the stock management
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('a.*, b.uid', 'tx_extendedshop_products a LEFT JOIN tx_extendedshop_products b on a.uid = b.l18n_parent', 'b.uid='.(int)$this->cObj->data["uid"].' AND a.pid IN (' . $this->pid_list . ') '. str_replace("tx_extendedshop_products", "b", $this->cObj->enableFields('tx_extendedshop_products')) .' '. str_replace("tx_extendedshop_products", "b", $this->addQueryLanguage) . ' '. str_replace("tx_extendedshop_products", "a", $this->addQueryEnableStock), '', '', '');
			}
			
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res))	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###XAJAX_CLOSEPREVIEW###"));
				// prepare the calling to products class
				$markerArray["###PRODUCT_UID###"] = $row["uid"];
				$prod = t3lib_div::makeInstance("tx_extendedshop_products");
				$prod->init($row, $this, $res);
					
				$content .= $prod->getTemplateProduct("listImage", true, $template, '', '', true);
				$content = $this->manageLabels($content);
				$content = preg_replace("/\#\#\#PRODUCT_IMAGE+[0-9]\#\#\#/", "", $content);
			}
		}
		
		
		//$content = utf8_encode($content);
		$objResponse = new tx_xajax_response();
		$objResponse->setCharEncoding('ISO-8859-1');
		$objResponse->addAssign($this->conf['xajax_preview.']['id'].$uid, 'innerHTML', $content);
		$objResponse->addScript("dettagli('product_list_".$uid."');");
		return $objResponse->getXML();
	}
	
	
	
	
	function minibasketUpdate($data)	{
		$content = '';
		$piVars = $data[$this->prefixId];

		require_once(t3lib_extMgm::extPath('extendedshop')."/pi2/class.tx_extendedshop_pi2.php");
		$basket = t3lib_div::makeInstance("tx_extendedshop_pi2");
		$content = $basket->main($content, $this->conf['minibasket_conf.'], $piVars, $this->cObj);
		
		$content = utf8_encode($content);
		$objResponse = new tx_xajax_response();
		//$objResponse->setCharEncoding('ISO-8859-1');
		$objResponse->setCharEncoding('utf-8');
		$objResponse->addAssign($this->conf['minibasket_id'], 'innerHTML', $content);
		if ($this->conf['minibasket_lightbox']==0){
			$objResponse->addScript("addCart('".$this->conf['minibasket_id']."');");
		}elseif ($this->conf['minibasket_lightbox']==1){
			$objResponse->addScript("addCart2('".$this->conf['minibasket_id']."');");
		}
		return $objResponse->getXML();
	}




	/**
	 * This function is used to list products in a TABLE template
	 */
	function show_image_list($res, $template) {
		$content = "";
		if ($this->conf['insertProduct_nextPage']==0)
			$markerArray["###FORM_ADD###"] = $this->pi_getPageLink($this->conf['pid_basket']);
		else
			$markerArray["###FORM_ADD###"] = $this->pi_linkTP_keepPIvars_url();
			
			
		if ($this->piVars['orderBy'] == "title") {
			$this->piVars['orderBy'] = "title_desc";
			$markerArrayLabel["###LABEL_ORDER_TITLE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_TITLE")), array(), 1);
			$this->piVars['orderBy'] = "price";
			$markerArrayLabel["###LABEL_ORDER_PRICE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_PRICE")), array(), 1);
			unset($this->piVars['orderBy']);
			$this->piVars['orderBy'] = "title"; 
		}	elseif ($this->piVars["orderBy"] == "price") {
			$this->piVars['orderBy'] = "price_desc";
			$markerArrayLabel["###LABEL_ORDER_PRICE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_PRICE")), array(), 1);
			$this->piVars['orderBy'] = "title";
			$markerArrayLabel["###LABEL_ORDER_TITLE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_TITLE")), array(), 1);
			unset($this->piVars['orderBy']);
			$this->piVars['orderBy'] = "price";		
		} else {
			$this->piVars['orderBy'] = "price";
			$markerArrayLabel["###LABEL_ORDER_PRICE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_PRICE")), array(), 1);
			unset($this->piVars['orderBy']);
			$this->piVars['orderBy'] = "title";
			$markerArrayLabel["###LABEL_ORDER_TITLE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_TITLE")), array(), 1);
			unset($this->piVars['orderBy']);
		}
		

		$numProducts = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		$productsForRow = $this->conf["list."]["productsForRow"];
		$productsRowNumbers = $this->conf["list."]["productsRowNumbers"];
		$productsForPages = $productsRowNumbers * $productsForRow;
		$numOfPages = ceil($numProducts / $productsForPages);
		if ($numOfPages == 1){
			$markerArray["###LABEL_SHOW_ALL###"] = "";
			$markerArray["###LABEL_TAB###"] = "";
		}
		else {
			$params[$this->prefixId]['swords'] = $this->piVars['swords'];
			$params[$this->prefixId]['productPage'] = 'all';
			//$params[$this->prefixId]['detail'] = false;
			$markerArray["###LABEL_SHOW_ALL###"] = $this->pi_linkTP($this->pi_getLL('LABEL_SHOW_ALL'), $params, 1);
			unset($params);
			$markerArray["###LABEL_TAB###"] = "|";
		}
		$markerArray["###PRODUCT_PAGES###"] = "";
		if ($numOfPages > 1){
			for ($i = 1; $i <= $numOfPages; $i++) {
				if ($this->piVars['productPage'] == $i || ($this->piVars['productPage'] == "" && $i == 1)) {
					$oldProductPage = $this->piVars['productPage'];
					$this->piVars['productPage'] = $i;
					//$this->piVars['detail'] = false;
					$markerArray["###PRODUCT_PAGES###"] .= "<span class='shop_selectedPage'>" . $this->pi_linkTP_keepPIvars($i, array(), 1) . "</span>";
					//print_r($this->piVars);
					//unset($this->piVars['productPage'], $this->piVars['detail']);
					$this->piVars['productPage'] = $oldProductPage;
				} else {
					$oldProductPage = $this->piVars['productPage'];
					$this->piVars['productPage'] = $i;
					//$this->piVars['detail'] = false;
					$markerArray["###PRODUCT_PAGES###"] .= "<span class='shop_notSelectedPage'>" . $this->pi_linkTP_keepPIvars($i, array(), 1) . "</span>";
					//unset($this->piVars['productPage'], $this->piVars['detail']);
					$this->piVars['productPage'] = $oldProductPage;
				}
			}
		}
		
		if ($this->piVars["categoryID"] != "" && t3lib_extMgm::isLoaded('toi_category')){
			$categoryApi = t3lib_div::makeInstance('tx_toicategory_api');
			$markerArray["###CATEGORY_TITLE###"] = $categoryApi->showCategorysFromList($this->piVars["categoryID"],$showMetainfoOutput=0,$recursiveToToplevel=0);
			$markerArray["###LABEL_CATEGORY_HEADER###"] = htmlspecialchars($this->pi_getLL("LABEL_CATEGORY_HEADER"));
		} else {
			$template = $this->cObj->substituteSubpart($template, "###CATEGORY_VISUAL###", "", $recursive = 0, $keepMarker = 0);
		}
		
		if ($this->piVars["supplierID"] != ""){
			$markerArray["###VALUE_SUPPLIER###"] = $this->getNameUser($this->piVars["supplierID"]);
			$markerArray["###LABEL_SUPPLIER_HEADER###"] = htmlspecialchars($this->pi_getLL("LABEL_SUPPLIER_HEADER"));
		} else {
			$template = $this->cObj->substituteSubpart($template, "###SUPPLIER_VISUAL###", "", $recursive = 0, $keepMarker = 0);
		}
			
		$headerTemplate = trim($this->cObj->getSubpart($template, "###HEADER###"));
		$content .= $this->manageLabels($this->cObj->substituteMarkerArray($headerTemplate, $markerArray));

		$partial = 0;
		$rowStartTemplate = trim($this->cObj->getSubpart($template, "###ROW_START###"));
		$rowEndTemplate = trim($this->cObj->getSubpart($template, "###ROW_END###"));
		$colTemplate = trim($this->cObj->getSubpart($template, "###COLUMN###"));

		if ($this->piVars['productPage'] == 'all') {
			$productsRowNumbers = ceil($numProducts / $productsForRow);
		} else
			if ($this->piVars['productPage'] != "") {
				$pag = (int)$this->piVars["productPage"];
				for ($i = 0; $i < ($pag -1) * $productsForPages; $i++) {
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				}
			}

		
		for ($q = 0; $q < $productsRowNumbers; $q++) {
			$content .= $this->manageLabels($rowStartTemplate);
			$n = 0;
			for ($i = 0; $i < $productsForRow && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res); $i++) {
				$markerArray["###COLUMN_WIDTH###"] = (100 / $productsForRow) . "%";
				$markerArray["###COLUMN_COLSPAN###"] = 1;
				$markerArray["###COLUMN_CLASS###"] = "shop_columnFull";
				$partial += $this->basket["products"][$row['uid']]["price"] * $this->basket["products"][$row['uid']]["num"];
				
				$prod = t3lib_div::makeInstance("tx_extendedshop_products");
				$prod->init($row, $this, $res);
											
				$content .= $this->manageLabels($this->cObj->substituteMarkerArray($prod->getTemplateProduct("listImage", true, $colTemplate), $markerArray));
				//$content .= $this->manageLabels($this->cObj->substituteMarkerArray($this->getProduct($row, "listImage", true, $colTemplate), $markerArray));
				$n++;
			}
		
			if ($this->conf['list.']['lastCol']==1 && $n < $productsForRow) {
				$markerArray["###COLUMN_WIDTH###"] = ((100 / $productsForRow) * ($productsForRow - $n)) . "%";
				$markerArray["###COLUMN_COLSPAN###"] = $productsForRow - $n;
				$markerArray["###COLUMN_CLASS###"] = "shop_columnEmpty";
								
				//$prod = t3lib_div::makeInstance(t3lib_div::makeInstanceClassName("tx_extendedshop_products"));
				//$prod->init($row, $this, $res);
				
				$lastCol = $this->cObj->substituteSubpart($colTemplate, '###PRICE###', '', 0,0);
				$lastCol = $this->cObj->substituteSubpart($lastCol, '###PRICEDISCOUNT###', '', 0,0);
				$lastCol = $this->clearInput($lastCol);
				
				$content .= $this->manageLabels($this->cObj->substituteMarkerArray($lastCol, $markerArray));
				$content .= $this->manageLabels($rowEndTemplate);
				break;
			}
			$content .= $this->manageLabels($rowEndTemplate);
			if (($GLOBALS['TYPO3_DB']->sql_num_rows($res) - $n) < 1)
				break;
		}

		$footerTemplate = trim($this->cObj->getSubpart($template, "###FOOTER###"));
		$content .= $this->manageLabels($this->cObj->substituteMarkerArray($footerTemplate, $markerArray));
		
		//$content = ereg_replace("%", " ", $content);
  		//$content = ereg_replace("[(,))]", " ", $content);

		//$template = $this->cObj->substituteMarkerArray($template, $markerArray);
		//$content.= $this->manageLabels($template);
		return $content;
	}
	
	
	
	

	/**
	 * This function shows the list of products
	 */
	function show_list($res, $template, $limitedItems = false) {
		$headerTemplate = trim($this->cObj->getSubpart($template, "###HEADER###"));
		
		$markerArrayLabel["###LABEL_ORDER_BY###"] = htmlspecialchars($this->pi_getLL("LABEL_ORDER_BY"));
		
		if ($this->piVars['orderBy'] == "title") {
			$this->piVars['orderBy'] = "title_desc";
			$markerArrayLabel["###LABEL_ORDER_TITLE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_TITLE")), array(), 1);
			$this->piVars['orderBy'] = "price";
			$markerArrayLabel["###LABEL_ORDER_PRICE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_PRICE")), array(), 1);
			unset($this->piVars['orderBy']);
			$this->piVars['orderBy'] = "title"; 
		}	elseif ($this->piVars["orderBy"] == "price") {
			$this->piVars['orderBy'] = "price_desc";
			$markerArrayLabel["###LABEL_ORDER_PRICE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_PRICE")), array(), 1);
			$this->piVars['orderBy'] = "title";
			$markerArrayLabel["###LABEL_ORDER_TITLE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_TITLE")), array(), 1);
			unset($this->piVars['orderBy']);
			$this->piVars['orderBy'] = "price";		
		} else {
			$this->piVars['orderBy'] = "price";
			$markerArrayLabel["###LABEL_ORDER_PRICE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_PRICE")), array(), 1);
			unset($this->piVars['orderBy']);
			$this->piVars['orderBy'] = "title";
			$markerArrayLabel["###LABEL_ORDER_TITLE###"] = $this->pi_linkTP_keepPIvars(htmlspecialchars($this->pi_getLL("LABEL_ORDER_TITLE")), array(), 1);
			unset($this->piVars['orderBy']);
		}
		
		
		$markerArrayLabel["###LABEL_IMAGE###"] = htmlspecialchars($this->pi_getLL("LABEL_IMAGE"));
		$markerArrayLabel["###LABEL_SUMMARY###"] = htmlspecialchars($this->pi_getLL("LABEL_SUMMARY"));

		if ($limitedItems) {
			$numProducts = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			$productsForPages = $this->conf["list."]["maxItems"];
			$numOfPages = ceil($numProducts / $productsForPages);
			if ($numOfPages == 1){
				$markerArrayLabel["###LABEL_SHOW_ALL###"] = "";
				$markerArrayLabel["###LABEL_TAB###"] = "";
			}
			else{
				$params[$this->prefixId]['swords'] = $this->piVars['swords'];
				$params[$this->prefixId]['productPage'] = 'all';
				//$params[$this->prefixId]['detail'] = false;
				$markerArrayLabel["###LABEL_SHOW_ALL###"] = $this->pi_linkTP($this->pi_getLL('LABEL_SHOW_ALL'), $params, 1);
				$markerArrayLabel["###LABEL_TAB###"] = "|";
				unset($params);
			}

			$markerArrayLabel["###PRODUCT_PAGES###"] = "";
			if ($numOfPages > 1){
				/*for ($i = 1; $i <= $numOfPages; $i++) {
					if ($this->piVars["productPage"] == $i || ($this->piVars["productPage"] == "" && $i == 1)) {
						$oldProductPage = $this->piVars['productPage'];
						$this->piVars['productPage'] = $i;
						$markerArrayLabel["###PRODUCT_PAGES###"] .= "<span class='shop_selectedPage'>" . $this->pi_linkTP_keepPIvars($i, array(), 1) . "</span>";
						$this->piVars['productPage'] = $oldProductPage;				
					} else {
						$oldProductPage = $this->piVars['productPage']; 
						$this->piVars['productPage'] = $i;					
						$markerArrayLabel["###PRODUCT_PAGES###"] .= "<span class='shop_notSelectedPage'>" . $this->pi_linkTP_keepPIvars($i, array(), 1) . "</span>";
						$this->piVars['productPage'] = $oldProductPage;
					}
				}*/
				
				$markerArrayLabel['###PRODUCT_PAGES###'] = $this->makePageBrowser($numProducts);
				
			}
			if ($this->piVars["productPage"] == 'all') {
				$productsForPages = $numProducts;
			} else
				if ($this->piVars["productPage"] != "") {
					$pag = $this->piVars["productPage"];
					for ($i = 0; $i < ($pag) * $productsForPages; $i++) {
						$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					}
				}
		}
		
		if ($this->piVars["categoryID"] != "" && t3lib_extMgm::isLoaded('toi_category')){
			$categoryApi = t3lib_div::makeInstance('tx_toicategory_api');
			$markerArrayLabel["###CATEGORY_TITLE###"] = $categoryApi->showCategorysFromList($this->piVars["categoryID"],$showMetainfoOutput=0,$recursiveToToplevel=0);
			$markerArrayLabel["###LABEL_CATEGORY_HEADER###"] = htmlspecialchars($this->pi_getLL("LABEL_CATEGORY_HEADER"));
		} else {
			$headerTemplate = $this->cObj->substituteSubpart($headerTemplate, "###CATEGORY_VISUAL###", "", $recursive = 0, $keepMarker = 0);
		}
		
		if ($this->piVars["supplierID"] != ""){
			$markerArrayLabel["###VALUE_SUPPLIER###"] = $this->getNameUser($this->piVars["supplierID"]);
			$markerArrayLabel["###LABEL_SUPPLIER_HEADER###"] = htmlspecialchars($this->pi_getLL("LABEL_SUPPLIER_HEADER"));
		} else {
			$headerTemplate = $this->cObj->substituteSubpart($headerTemplate, "###SUPPLIER_VISUAL###", "", $recursive = 0, $keepMarker = 0);
		}
		
		$content = "";
		$content .= $this->cObj->substituteMarkerArray($headerTemplate, $markerArrayLabel);
		$content = $this->manageLabels($content);

		$subTemplate = trim($this->cObj->getSubpart($template, "###LIST###"));

		if ($limitedItems) {
			for ($i = 0; $i < $productsForPages && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res); $i++) {
				$prod = t3lib_div::makeInstance("tx_extendedshop_products");
				$prod->init($row, $this, $res);
				if ($this->conf['list.']['linkTitle']==1)
					$link_title = true;
				else
					$link_title = false;				
				$content .= $prod->getTemplateProduct("listImage", $link_title, $subTemplate);			
			}
		} else {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$prod = t3lib_div::makeInstance("tx_extendedshop_products");
				$prod->init($row, $this, $res);
				
				if ($this->conf['list.']['linkTitle']==1)
					$link_title = true;
				else
					$link_title = false;
				$content .= $prod->getTemplateProduct("listImage", $link_title, $subTemplate);				
	
			}

		}

		$footerTemplate = trim($this->cObj->getSubpart($template, "###FOOTER###"));
		$markerArrayLabel["###PERSONAL_NOTE###"] = "";
		if ($this->basketRef->basket["personinfo"]["NOTE"] != "")
			$markerArrayLabel["###PERSONAL_NOTE###"] = $this->basketRef->basket["personinfo"]["NOTE"];
		$footerTemplate = $this->cObj->substituteMarkerArray($footerTemplate, $markerArrayLabel);
		$content .= $this->manageLabels($footerTemplate);
		$content = preg_replace("/\#\#\#PRODUCT_IMAGE+[0-9]\#\#\#/", "", $content);
		return $content;
	}
	
	
	
	

	/**
	 * This function shows the details of a single product
	 */
	function show_product($resDetail, $res="") {
		$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ITEM_SINGLE_TEMPLATE###"));
		
		$subTemplate = trim($this->cObj->getSubpart($template, "###DETAIL###"));
		if ($this->conf['insertProduct_nextPage']==0)
			$markerArray["###FORM_ADD###"] = $this->pi_getPageLink($this->conf['pid_basket']);
		else
			$markerArray["###FORM_ADD###"] = $this->pi_linkTP_keepPIvars_url();
		
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resDetail)) {
			// prepare the calling to products class
			$markerArray["###PRODUCT_UID###"] = $row["uid"];
			
			if ($this->conf['xajax_cart_update']==1 && t3lib_extMgm::isLoaded('xajax'))	{
				$markerArray['###XAJAX_CART_UPDATE###'] = 'onsubmit="document.getElementById(\''.$this->conf['minibasket_id'].'\').value=\'\'; ' . $this->prefixId . 'minibasketUpdate(xajax.getFormValues(\'tx_extendedshop_pi1_basket_'.$row["uid"].'\')); return false;"';
			}	else	{
				$markerArray['###XAJAX_CART_UPDATE###'] = '';
			}
			
			$headerTemplate = trim($this->cObj->getSubpart($template, "###HEADER###"));
			$content .= $this->manageLabels($this->cObj->substituteMarkerArray($headerTemplate, $markerArray));
			$prod = t3lib_div::makeInstance("tx_extendedshop_products");
			$prod->init($row, $this, $res);
			
			$content .= $prod->getTemplateProduct("image", false, $subTemplate);
		}

		$content = preg_replace("/\#\#\#PRODUCT_IMAGE+[0-9]\#\#\#/", "", $content);
		return $content;
	}
	
	
	
	

	/**
	 * This function shows the list of products in the basket
	 */
	function show_basket($basketArray, $template="", $disabledSelect="", $addPayShip = false) {
		if ($template=="")
			$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###BASKET_TEMPLATE###"));
		
		if ($this->conf['cart.']['linkTitle']==0)
			$linkTitle=false;
		else
			$linkTitle=true;
		
		$introTemplate = trim($this->cObj->getSubpart($template, "###INTRO###"));
		//$content = "";
		//$content .= $this->manageLabels($introTemplate);
		$template = $this->cObj->substituteSubpart($template, '###INTRO###', $this->manageLabels($introTemplate), 0,0);

		$headerTemplate = trim($this->cObj->getSubpart($template, "###HEADER###"));

		$markerArrayLabel["###LABEL_TITLE###"] = htmlspecialchars($this->pi_getLL("LABEL_TITLE"));
		$markerArrayLabel["###LABEL_IMAGE###"] = htmlspecialchars($this->pi_getLL("LABEL_IMAGE"));
		$markerArrayLabel["###LABEL_SUMMARY###"] = htmlspecialchars($this->pi_getLL("LABEL_SUMMARY"));

		$markerArrayLabel["###LABEL_PRICE###"] = htmlspecialchars($this->pi_getLL("LABEL_PRICE"));
		
		// Warnings management
		$warnings = $this->basketRef->getWarnings();
		if ($warnings==false)	{
			$headerTemplate = $this->cObj->substituteSubpart($headerTemplate, '###WARNINGS_TEMPLATE###', '', 0,0);
		}	else	{
			foreach ($warnings as $warn)
				if ($warn['error']=='limit')	{
					$error = $this->pi_getLL('WARNING_LIMIT');
					$error = str_replace('###LIMIT###', $warn['limit'], $error);
					$error = str_replace('###PRODUCT###', tx_extendedshop_products::getProductTitle($warn['uid']), $error);
					$markerArrayLabel['###WARNINGS_TEXT###'] .= $this->cObj->stdWrap($error, $this->conf['warnings.']);
				}
		}

		$template = $this->cObj->substituteSubpart($template, '###HEADER###', $this->cObj->substituteMarkerArray($headerTemplate, $markerArrayLabel),0,0);
		
		$contentBasket = '';
		$productsList = '';

		if (is_array($basketArray)){
			//reset($basketArray);
			$order_product_num = 0;
			foreach ($basketArray as $autoinc){
				$arrayKey = key($basketArray); 
				each($basketArray);
				if (t3lib_div :: testInt($autoinc["uid"]) && $autoinc["num"] > 0) {
					$order_product_num++;
					$markerBasket['###ORDER_PRODUCTS_NUM###'] = $order_product_num;
					
					$subTemplate = $this->cObj->getSubpart($template, "###LIST###");
					$resProd = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid='.$autoinc['uid'], '', '', '1');
					$rowProd = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resProd);
					
					$prod = t3lib_div::makeInstance("tx_extendedshop_products");
					$prod->init($rowProd, $this, $resProd);
					//$rowPrice = $prod->getRowPrice($autoinc['num'], $autoinc['price']);
					
					$product_user_info = tx_extendedshop_products::getProductUserInfo($autoinc['uid'],'',$this);
					
					$markerBasket['###PRODUCT_BASKETPRICE###'] = $this->priceFormat($product_user_info['final_price']);
					//if ($this->getUserType() == 0){
						$markerBasket['###PRODUCT_TOTAL_PRICE_RAW###'] = $product_user_info['final_price'];
						$markerBasket['###PRODUCT_TOTAL_PRICE###'] = $this->priceFormat($product_user_info['final_price']*$autoinc['num']);
						$markerBasket['###PRODUCT_TOTAL_PRICE_B_RAW###'] = $product_user_info['final_price_B'];
						if ($this->conf['hideNoTax']!=1)	{
							$markerBasket['###PRODUCT_TOTAL_PRICE_B###'] = $this->cObj->stdWrap($this->priceFormat($product_user_info['final_price_B']*$autoinc['num']), $this->conf['price_b.']);
						}	else	{
							$markerBasket['###PRODUCT_TOTAL_PRICE_B###'] = "";
						}
					//}
					/*
					else {
						$markerBasket['###PRODUCT_TOTAL_PRICE###'] = $this->priceFormat($prod->calculatePriceNoTax($rowPrice));
						$markerBasket['###PRODUCT_TOTAL_PRICE_RAW###'] = $prod->calculatePriceNoTax($rowPrice);
						$markerBasket['###PRODUCT_TOTAL_PRICE_B_RAW###'] = $rowPrice;
						if ($this->conf['hideNoTax']!=1)	{
							$markerBasket['###PRODUCT_TOTAL_PRICE_B###'] = $this->cObj->stdWrap($this->priceFormat($rowPrice), $this->conf['price_b.']);
						}	else	{
							$markerBasket['###PRODUCT_TOTAL_PRICE_B###'] = "";
						}
					}
					*/
					
					// Hook that can be used to manage custom total prices
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_basket_totals']))    {
					    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_basket_totals'] as $_classRef)    {
					        $_procObj = &t3lib_div::getUserObj($_classRef);
					        $markerBasket = $_procObj->evaluateCustomBasketTotals($this, $markerBasket, $subTemplate, $rowProd, $autoinc, $this->conf);
					    }
					}
					
					$contentBasket = $this->cObj->substituteMarkerArray($subTemplate, $markerBasket);	
					$productsList .= $prod->getTemplateProduct("listImage", $linkTitle, $contentBasket, $arrayKey, $disabledSelect);
				}
			}
			$template = $this->cObj->substituteSubpart($template, "###LIST###", $productsList, $recursive = 0, $keepMarker = 0);
			//$content = $this->manageLabels($content);
		}

		if (!$addPayShip)	{
			//if ($this->getUserType() == 0) {
				$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->getProductsTotal());
				$markerArray["###BASKET_TOTAL_RAW###"] = $this->basketRef->getProductsTotal();
				$markerArray['###BASKET_TOTAL_B_RAW###'] = $this->basketRef->getProductsTotalNoTax();
				if ($this->conf['hideNoTax']!=1)	{
					$markerArray['###BASKET_TOTAL_B###'] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getProductsTotalNoTax()), $this->conf['price_b.']);
				}	else	{
					$markerArray["###BASKET_TOTAL_B###"] = "";
				}
			//} 
			/*
			else {
				$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->getProductsTotalNoTax());
				$markerArray["###BASKET_TOTAL_RAW###"] = $this->basketRef->getProductsTotalNoTax();
				$markerArray["###BASKET_TOTAL_B_RAW###"] = $this->basketRef->getProductsTotal();
				if ($this->conf['hideNoTax']!=1)	{
					$markerArray["###BASKET_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getProductsTotal()), $this->conf['price_b.']);
				}	else	{
					$markerArray["###BASKET_TOTAL_B###"] = "";
				}
				
			}
			*/
			$markerArray["###BASKET_PRODUCTS_TOTAL###"] = $markerArray["###BASKET_TOTAL###"];
			$markerArray["###BASKET_PRODUCTS_TOTAL_B###"] = $markerArray["###BASKET_TOTAL_B###"];
			$markerArray["###BASKET_PRODUCTS_TOTAL_RAW###"] = $markerArray["###BASKET_TOTAL_RAW###"];
			$markerArray["###BASKET_PRODUCTS_TOTAL_B_RAW###"] = $markerArray["###BASKET_TOTAL_B_RAW###"];
		}	else {
			
			/*
			$calculatedSums_tax["total"] = $this->basketRef->getProductsTotal();
			$calculatedSums_no_tax["total"] = $this->basketRef->getProductsTotalNoTax();
			$calculatedSums_tax["payment"] = $this->basketRef->getPaymentPriceTax($this->basketRef->getPayment());
			$calculatedSums_no_tax["payment"] = $this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment());
			$calculatedSums_tax["shipping"] = $this->basketRef->getShippingPriceTax($this->basketRef->getShipping());
			$calculatedSums_no_tax["shipping"] = $this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping());
			
			if ($this->getUserType() == 1){
				$temp = $calculatedSums_tax["payment"];
				$calculatedSums_tax["payment"] = $calculatedSums_no_tax["payment"];
				$calculatedSums_no_tax["payment"] = $temp;
				
				$temp = $calculatedSums_no_tax["shipping"];
				$calculatedSums_tax["shipping"] = $calculatedSums_no_tax["shipping"];
				$calculatedSums_no_tax["shipping"] = $temp;
			}
			
			// This is the total for everything
			$calculatedSums_tax["total"] += $calculatedSums_tax["payment"];
			$calculatedSums_tax["total"] += $calculatedSums_tax["shipping"];
			$calculatedSums_no_tax["total"] += $calculatedSums_no_tax["payment"];
			$calculatedSums_no_tax["total"] += $calculatedSums_no_tax["shipping"];
			//$this->total = $calculatedSums_tax["total"] + ($this->basketRef->getPercent()/100) * $calculatedSums_tax["total"];
			
			if($this->conf['debug']){
				t3lib_div::debug($calculatedSums_tax["total"],'extendedshop_pi1.php : show_basket() : $calculatedSums_tax["total"]');
				t3lib_div::debug($calculatedSums_tax["payment"],'extendedshop_pi1.php : show_basket() : $calculatedSums_tax["payment"]');
				t3lib_div::debug($calculatedSums_tax["shipping"],'extendedshop_pi1.php : show_basket() : $calculatedSums_tax["shipping"]');
			}
			*/
			
			//if ($this->getUserType() == 0) {
				$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->getTotalPrice());
				$markerArray["###BASKET_TOTAL_RAW###"] = $this->basketRef->getTotalPrice();
				$markerArray["###BASKET_PRODUCTS_TOTAL###"] = $this->priceFormat($this->basketRef->getProductsTotal());
				$markerArray["###BASKET_PRODUCTS_TOTAL_RAW###"] = $this->basketRef->getProductsTotal();
				$markerArray["###BASKET_PRODUCTS_TOTAL_B_RAW###"] = $this->basketRef->getProductsTotalNoTax();
				$markerArray["###BASKET_TOTAL_B_RAW###"] = $this->basketRef->getTotalPriceNoTax();
				if ($this->conf['hideNoTax']!=1)	{
					$markerArray["###BASKET_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getTotalPriceNoTax()), $this->conf['price_b.']);
					$markerArray["###BASKET_PRODUCTS_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getProductsTotalNoTax()), $this->conf['price_b.']);
				}	else	{
					$markerArray['###BASKET_TOTAL_B###'] = "";
					$markerArray['###BASKET_PRODUCTS_TOTAL_B###'] = "";
				}
				if($this->conf['debug']){
					t3lib_div::debug($markerArray["###BASKET_TOTAL###"],'extendedshop_pi1.php : show_basket() : $markerArray["###BASKET_TOTAL###"]');
					t3lib_div::debug($markerArray["###BASKET_TOTAL_B###"],'extendedshop_pi1.php : show_basket() : $markerArray["###BASKET_TOTAL_B###"]');
				}
			//}
			/*else {
				$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($calculatedSums_no_tax["total"]);
				$markerArray["###BASKET_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($calculatedSums_tax["total"]), $this->conf['price_b.']);
				$markerArray["###BASKET_TOTAL_RAW###"] = $calculatedSums_no_tax["total"];
				$markerArray["###BASKET_TOTAL_B_RAW###"] = $calculatedSums_tax["total"];
				$markerArray["###BASKET_PRODUCTS_TOTAL###"] = $this->priceFormat($this->basketRef->getProductsTotalNoTax());
				$markerArray["###BASKET_PRODUCTS_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getProductsTotal()), $this->conf['price_b.']);
				$markerArray["###BASKET_PRODUCTS_TOTAL_RAW###"] = $this->basketRef->getProductsTotalNoTax();
				$markerArray["###BASKET_PRODUCTS_TOTAL_B_RAW###"] = $this->basketRef->getProductsTotal();
				if ($this->conf['hideNoTax']!=1)	{
					$markerArray["###BASKET_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($calculatedSums_tax["total"]), $this->conf['price_b.']);
					$markerArray["###BASKET_PRODUCTS_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getProductsTotal()), $this->conf['price_b.']);
				}	else	{
					$markerArray['###BASKET_TOTAL_B###'] = "";
					$markerArray['###BASKET_PRODUCTS_TOTAL_B###'] = "";
				}
			}
			*/
		}
		
		if ($markerArray["###BASKET_TOTAL_B_RAW###"] > $markerArray["###BASKET_TOTAL_RAW###"])	{
			$markerArray["###BASKET_TOTAL_VAT_RAW###"] = $markerArray["###BASKET_TOTAL_B_RAW###"] - $markerArray["###BASKET_TOTAL_RAW###"];
		}	else	{
			$markerArray["###BASKET_TOTAL_VAT_RAW###"] = $markerArray["###BASKET_TOTAL_RAW###"] - $markerArray["###BASKET_TOTAL_B_RAW###"];
		}
		$markerArray["###BASKET_TOTAL_VAT###"] = $this->priceFormat($markerArray["###BASKET_TOTAL_VAT_RAW###"]);
		
		// Hook that can be used to manage the basket settings
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['basket_management']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['basket_management'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $markerArray = $_procObj->evaluateBasketArray($this, $markerArray, $template, $addPayShip);
		    }
		}
		
		$footerTemplate = trim($this->cObj->getSubpart($template, "###FOOTER###"));

		$markerArray["###BASKET_PROCEED###"] = "";
		if ($this->conf["minOrder"] > 0 && $this->basketRef->getProductsTotal() < $this->conf["minOrder"]) {
			$markerArray["###BASKET_PROCEED###"] = " disabled='disabled'";
		} else {
			$markerArray["###BASKET_PROCEED###"] = "";
		}
		
		if ($this->conf['pid_userinfo']>0)
			$markerArray["###FORM_BASKET###"] = $this->pi_getPageLink($this->conf["pid_userinfo"]);
		else
			$markerArray["###FORM_BASKET###"] = $this->pi_getPageLink($this->conf["pid_basket"]);
		
		$markerArray["###FORM_CLEAR###"] = $this->pi_getPageLink($this->conf["pid_basket"]);

		$template = $this->cObj->substituteSubpart($template, '###FOOTER###', $this->cObj->substituteMarkerArray($footerTemplate, $markerArray),0,0);
		//$content .= $this->manageLabels($footerTemplate);

		return $this->manageLabels($template);
	}
	
	

	/**
	 * This function shows personal info template to ask to the client who is him.
	 */
	function show_personal_info($template, $complete = true, $finalize = 0) {
		$personinfo = $this->basketRef->getPersonInfo();
		$deliveryinfo = $this->basketRef->getDeliveryInfo($finalize);
		if (is_array($personinfo))	{
			while (list ($marker, $value) = each($personinfo)) {
				if (substr($value, 0, 3) == "###") {
					$markerArray["###PERSONAL_" . $marker . "###"] = "";
					$markerArray["###DELIVERY_" . $marker . "###"] = "";
				} else {
					$markerArray["###PERSONAL_" . $marker . "###"] = strip_tags($value);
					$markerArray["###DELIVERY_" . $marker . "###"] = "";
				}
			}
		}
		$markerArray["###LABEL_DIFFERENT_ADDRESS###"] = $this->pi_getLL("LABEL_DIFFERENT_ADDRESS");
		
		$shipping = $this->basketRef->getShipping();
		$userInfo = $this->basketRef->getPersonInfo();
		$user = $GLOBALS["TSFE"]->fe_user->user;
		if ($personinfo["COUNTRY"] == "")
			$this->basketRef->setCountryInfo($user["static_info_country"]);
		if ($personinfo["STATE"] == ""){
			$this->basketRef->setStateInfo($user["zone"]);
			$markerArray["###PERSONAL_STATE###"] = strip_tags($user["zone"]); 
		}

		$markerArray["###PERSONAL_COUNTRY###"] = $this->basketRef->getCountryName($personinfo["COUNTRY"])=='' ? $personinfo["COUNTRY"] : $this->basketRef->getCountryName($personinfo["COUNTRY"]);

		if (is_array($deliveryinfo)) {
			while (list ($marker, $value) = each($deliveryinfo)) {
				if (substr($value, 0, 3) == "###" || $value == "") {
					$markerArray["###DELIVERY_" . $marker . "###"] = "";
				} else {
					$markerArray["###DELIVERY_" . $marker . "###"] = strip_tags($value);
				}
			}
		}
		$markerArray["###DELIVERY_COUNTRY###"] = $this->basketRef->getCountryName($deliveryinfo["COUNTRY"])=='' ? $deliveryinfo["COUNTRY"] : $this->basketRef->getCountryName($deliveryinfo["COUNTRY"]);
		//t3lib_div::debug($this->conf);		
		
		if ($complete) {
			$markerArray["###LABEL_PERSONAL_INFO_NOTCOMPLETE###"] = "";
			$template = $this->cObj->substituteSubpart($template, "###INCOMPLETE###", "", $recursive = 0, $keepMarker = 0);
		}

		if ($markerArray["###PERSONAL_PRIVATE###"] == 0) {
			$markerArray["###PERSONAL_PRIVATE_P###"] = " checked ";
			$markerArray["###PERSONAL_PRIVATE_A###"] = "";
			$markerArray["###PERSONAL_PRIVATE###"] = htmlspecialchars($this->pi_getLL("LABEL_PERSONAL_INFO_PRIVATE_P"));
		} else {
			$markerArray["###PERSONAL_PRIVATE_P###"] = "";
			$markerArray["###PERSONAL_PRIVATE_A###"] = " checked ";
			$markerArray["###PERSONAL_PRIVATE###"] = htmlspecialchars($this->pi_getLL("LABEL_PERSONAL_INFO_PRIVATE_A"));
		}

		if ($markerArray["###PERSONAL_AUTHORIZATION###"] == 1) {
			$markerArray["###PERSONAL_AUTHORIZATION_V###"] = " checked ";
		} else {
			$markerArray["###PERSONAL_AUTHORIZATION_V###"] = "";
		}

		if ($markerArray["###PERSONAL_CONDITIONS###"] == 1) {
			$markerArray["###PERSONAL_CONDITIONS_V###"] = " checked ";
		} else {
			$markerArray["###PERSONAL_CONDITIONS_V###"] = "";
		}

		$listRequired = explode(",", trim($this->conf["requiredFields"]));
		foreach ($listRequired as $field) {
			$markerArray["###REQUIRED_" . strtoupper($field) . "###"] = trim($this->conf["requiredFieldsSymbol"]);
		}
		$listDeliveryRequired = explode(",", trim($this->conf["requiredDeliveryFields"]));
		foreach ($listDeliveryRequired as $field) {
			$markerArray["###REQUIRED_DELIVERY_INFO_" . strtoupper($field) . "###"] = trim($this->conf["requiredFieldsSymbol"]);
		}

		if ($this->conf['enableStaticInfoTable'] == 0){
			$markerArray["###PERSONAL_COUNTRY_SELECT###"] = $this->generateSelectForCountry("personal", "personinfo");
			$markerArray["###SHIPPING_SELECTOR###"] = $this->generateSelectForCountry("delivery", "delivery");
		} else {
			$markerArray["###PERSONAL_COUNTRY_SELECT###"] = $this->generateSelectForCountryS("personal", "personinfo");
			$markerArray["###SHIPPING_SELECTOR###"] = $this->generateSelectForCountryS("delivery", "delivery");
		}

		// Shipping
		$basketExtra = $this->basketRef->basketExtra;

		$markerArray["###SHIPPING_IMAGE###"] = $this->cObj->IMAGE($basketExtra["shipping."]["image."]);
		$markerArray["###SHIPPING_TITLE###"] = $this->basketRef->getShippingTitle($this->basketRef->getShipping());

		$markerArray["###PERSONAL_NOTE###"] = "";
		if ($personinfo["NOTE"] != "")
			$markerArray["###PERSONAL_NOTE###"] = $personinfo["NOTE"];
		
		// This code is used to show or hide the delivery address
		if ($this->basketRef->basket["delivery"]["more"] == 0 && !$finalize){		
			$markerArray["###DELIVERY_MORE_CHECKED###"] = "";
			$markerArray["###DELIVERY_DISPLAY###"] = "none";
			$template = $this->cObj->substituteSubpart($template, "###PERSONAL_INFO_DELIVERY###", "", $recursive = 0, $keepMarker = 0);					
		} else {
			$markerArray["###DELIVERY_MORE_CHECKED###"] = " checked=\"checked\"";
			$markerArray["###DELIVERY_DISPLAY###"] = "block";
			if ($this->conf['switchDeliveryIfEmpty']==0 && $finalize && $this->basketRef->basket["delivery"]["more"] == 0)
				$template = $this->cObj->substituteSubpart($template, "###PERSONAL_INFO_DELIVERY###", "", $recursive = 0, $keepMarker = 0);
		}
		
		if ($this->conf['pid_payment']>0)
			$markerArray["###FORM_BASKET###"] = $this->pi_getPageLink($this->conf["pid_payment"]);
		else
			$markerArray["###FORM_BASKET###"] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
			
		$markerArray["###TO_BASKET###"] = $this->pi_getPageLink($this->conf["pid_basket"]);
		
		$markerArray["###CLEAR_PERSON###"] = ' onclick="document.extendedshop_userinfo.action=\''.$this->pi_getPageLink($GLOBALS["TSFE"]->id).'\'; document.extendedshop_userinfo.submit();" ';
		
		// This is used in the payment page to go back to the userinfo page
		if ($this->conf['pid_userinfo']>0)
			$markerArray["###ONCLICK_BACK###"] = 'onclick="document.extendedshop_payment.action=\''.$this->pi_getPageLink($this->conf["pid_userinfo"]).'\'; document.extendedshop_payment.submimt();"';
		else
			$markerArray["###ONCLICK_BACK###"] = "AAA";

		$template = $this->cObj->substituteMarkerArray($template, $markerArray);

		$template = preg_replace("/(\#\#\#REQUIRED\_)[A-Z]+(\#\#\#)/", "", $template);
		$template = preg_replace("/(\#\#\#REQUIRED\_DELIVERY\_INFO\_)[A-Z]+(\#\#\#)/", "", $template);
		$template = preg_replace("/(\#\#\#PERSONAL\_)[A-Z]+(\#\#\#)/", "", $template);

		$content .= $this->manageLabels($template);
		return $content;
	}
	
	
	
	
	
	function show_personal_info_registred($template, $complete = true, $finalize=0) {
		//t3lib_div::debug($GLOBALS["TSFE"]->fe_user->user);
		$personinfo = $this->basketRef->getPersonInfo();
		$delivery = $this->basketRef->getDeliveryInfo($finalize);
		
		if ($this->conf['pid_payment']>0)
			$markerArray["###FORM_BASKET###"] = $this->pi_getPageLink($this->conf["pid_payment"]);
		else
			$markerArray["###FORM_BASKET###"] = $this->pi_getPageLink($GLOBALS['TSFE']->id);

		if (is_array($personinfo))	{
			while (list ($marker, $value) = each($personinfo)) {
				if (substr($value, 0, 3) == "###") {
					$markerArray["###PERSONAL_" . $marker . "###"] = "";
					$markerArray["###DELIVERY_" . $marker . "###"] = "";
				} else {
					$markerArray["###PERSONAL_" . $marker . "###"] = strip_tags($value);
					$markerArray["###DELIVERY_" . $marker . "###"] = "";
				}
			}
		}
		
		
		$userInfo = $this->basketRef->getPersonInfo();
		$markerArray["###PERSONAL_COUNTRY###"] = $this->basketRef->getCountryName($userInfo["COUNTRY"]);
		$markerArray["###PERSONAL_COUNTRY_CODE###"] = $userInfo["COUNTRY"];
		
		$deliveryinfo = $this->basketRef->getDeliveryInfo();
		if (is_array($deliveryinfo)) {
			while (list ($marker, $value) = each($deliveryinfo)) {
				if (substr($value, 0, 3) == "###" || $value == "") {
					$markerArray["###DELIVERY_" . $marker . "###"] = "";
				} else {
					$markerArray["###DELIVERY_" . $marker . "###"] = strip_tags($value);
				}
			}
		}
		$markerArray["###DELIVERY_COUNTRY###"] = $this->basketRef->getCountryName($deliveryinfo["COUNTRY"]);
		//t3lib_div::debug($this->conf);
		
		if ($complete) {
			$markerArray["###LABEL_PERSONAL_INFO_NOTCOMPLETE###"] = "";
			$template = $this->cObj->substituteSubpart($template, "###INCOMPLETE###", "", $recursive = 0, $keepMarker = 0);
		}

		if ($markerArray["###PERSONAL_PRIVATE###"] == 0) {
			$markerArray["###PERSONAL_PRIVATE_P###"] = " checked ";
			$markerArray["###PERSONAL_PRIVATE_A###"] = "";
			$markerArray["###PERSONAL_PRIVATE###"] = htmlspecialchars($this->pi_getLL("LABEL_PERSONAL_INFO_PRIVATE_P"));
		} else {
			$markerArray["###PERSONAL_PRIVATE_P###"] = "";
			$markerArray["###PERSONAL_PRIVATE_A###"] = " checked ";
			$markerArray["###PERSONAL_PRIVATE###"] = htmlspecialchars($this->pi_getLL("LABEL_PERSONAL_INFO_PRIVATE_A"));
		}

		if ($markerArray["###PERSONAL_AUTHORIZATION###"] == 1) {
			$markerArray["###PERSONAL_AUTHORIZATION_V###"] = " checked ";
		} else {
			$markerArray["###PERSONAL_AUTHORIZATION_V###"] = "";
		}

		if ($markerArray["###PERSONAL_CONDITIONS###"] == 1) {
			$markerArray["###PERSONAL_CONDITIONS_V###"] = " checked ";
		} else {
			$markerArray["###PERSONAL_CONDITIONS_V###"] = "";
		}

		$listRequired = explode(",", trim($this->conf["requiredFields"]));
		foreach ($listRequired as $field) {
			$markerArray["###REQUIRED_" . strtoupper($field) . "###"] = trim($this->conf["requiredFieldsSymbol"]);
		}
		$listDeliveryRequired = explode(",", trim($this->conf["requiredDeliveryFields"]));
		foreach ($listDeliveryRequired as $field) {
			$markerArray["###REQUIRED_DELIVERY_INFO_" . strtoupper($field) . "###"] = trim($this->conf["requiredFieldsSymbol"]);
		}

		if ($this->conf['enableStaticInfoTable'] == 0){
			$markerArray["###PERSONAL_COUNTRY_SELECT###"] = $this->generateSelectForCountry("personal", "personinfo");
			$markerArray["###SHIPPING_SELECTOR###"] = $this->generateSelectForCountry("delivery", "delivery");
		} else {
			$markerArray["###PERSONAL_COUNTRY_SELECT###"] = $this->generateSelectForCountryS("personal", "personinfo");
			$markerArray["###SHIPPING_SELECTOR###"] = $this->generateSelectForCountryS("delivery", "delivery");
		}

		// Shipping
		$basketExtra = $this->basketRef->basketExtra;
		$markerArray["###SHIPPING_IMAGE###"] = $this->cObj->IMAGE($basketExtra["shipping."]["image."]);
		$markerArray["###SHIPPING_TITLE###"] = $this->basketRef->getShippingTitle($this->basketRef->getShipping());

		$markerArray["###PERSONAL_NOTE###"] = "";
		if ($personinfo["NOTE"] != "")
			$markerArray["###PERSONAL_NOTE###"] = $personinfo["NOTE"];
			
		// This code is used to show or hide the delivery address
		if ($this->basketRef->basket["delivery"]["more"] == 0){
			$markerArray["###DELIVERY_MORE_CHECKED###"] = "";
			$markerArray["###DELIVERY_DISPLAY###"] = "none";
			$template = $this->cObj->substituteSubpart($template, "###PERSONAL_INFO_DELIVERY###", "", $recursive = 0, $keepMarker = 0);	
		} else {
			$markerArray["###DELIVERY_MORE_CHECKED###"] = " checked=\"checked\"";
			$markerArray["###DELIVERY_DISPLAY###"] = "block";
		}
		
		$markerArray["###TO_BASKET###"] = $this->pi_getPageLink($this->conf["pid_basket"]);


		$template = $this->cObj->substituteMarkerArray($template, $markerArray);

		$template = preg_replace("/(\#\#\#REQUIRED\_)[A-Z]+(\#\#\#)/", "", $template);
		$template = preg_replace("/(\#\#\#REQUIRED\_DELIVERY\_INFO\_)[A-Z]+(\#\#\#)/", "", $template);

		$content = $this->manageLabels($template);

		return $content;
	}	
	
	
	
	

	/**
	 * This function shows shipping and payment template
	 */
	function show_payment() {
		$basketProducts = $this->basketRef->getBasketProducts();
		$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###SHIPPING_TEMPLATE###"));
		
		if ($this->errorPayment==false && $this->errorShipping==false)
			$template = $this->cObj->substituteSubpart($template, '###ERRORS_SUBPART###', '',0,0);
		else	{
			$mainMarkerArray['###ERRORS_SHIPPING_STEP###'] = '';
			if ($this->errorPayment)
				$mainMarkerArray['###ERRORS_SHIPPING_STEP###'] .= $this->pi_getLL('ERROR_PAYMENT', 'Payment method not available or not selected');
			if ($mainMarkerArray['###ERRORS_SHIPPING_STEP###']!='')
				$mainMarkerArray['###ERRORS_SHIPPING_STEP###'] .= '<br />';
			if ($this->errorShipping)
				$mainMarkerArray['###ERRORS_SHIPPING_STEP###'] .= $this->pi_getLL('ERROR_SHIPPING', 'Shipping method not available or not selected');
		}
		
		if ($this->conf['enableStaticInfoTable']==0)	{
			$markerArray["###SHIPPING_TITLE###"] = $this->basketRef->getShippingTitle($this->basketRef->getShipping());
			$markerArray["###SHIPPING_SELECTOR###"] = '';
			$shippingAvailable = true;
		}	else	{
			$shOut = $this->generateShippingSelector("shipping");
			$markerArray["###SHIPPING_SELECTOR###"] = $shOut[1];
			$markerArray["###SHIPPING_TITLE###"] = '';
			$shippingAvailable = $shOut[0];
		}
		
		$template = $this->show_basket($basketProducts, $template, " disabled", true);
		
		if ($this->conf['pid_finalize']>0)
			$mainMarkerArray["###FORM_BASKET###"] = $this->pi_getPageLink($this->conf["pid_finalize"]);
		else
			$mainMarkerArray["###FORM_BASKET###"] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
		
		$basketExtra = $this->basketRef->basketExtra;
		$markerArray["###PAYMENT_SELECTOR###"] = $this->generatePaymentSelector("payment");
		$markerArray["###PAYMENT_IMAGE###"] = $this->cObj->IMAGE($basketExtra["payment."]["image."]);
		$markerArray["###PAYMENT_TITLE###"] = $this->basketRef->getPaymentTitle($this->basketRef->getPayment());

		//$pay = $this->basketRef->getPayment().".";
		//t3lib_div::debug($this->conf["payment."]);
		$perc = $this->conf["payment."][$this->basketRef->getPayment()."."]["perc"];
		if ($perc == ""){
			$perc = 0;
		}
		$this->basketRef->setPercent($perc);
		if ($this->getUserType($this->conf,$this->basketRef->basket) == 0){
			$percAmount = $this->basketRef->getPercentAmount($this->basketRef->getTotalPrice(), $perc);
			$percAmountNoTax = $this->basketRef->getPercentAmount($this->basketRef->getTotalPriceNoTax(), $perc);
			$markerArray["###PRICE_PAYMENT_TAX###"] = $this->priceFormat($this->basketRef->getPaymentPriceTax($this->basketRef->getPayment())+$percAmount);
			$markerArray["###PRICE_SHIPPING_TAX###"] = $this->priceFormat($this->basketRef->getShippingPriceTax($this->basketRef->getShipping()));
			if ($this->conf['hideNoTax']!=1)	{
				$markerArray["###PRICE_PAYMENT_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment())+$percAmountNoTax), $this->conf['price_b.']);
				$markerArray["###PRICE_SHIPPING_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping())), $this->conf['price_b.']);
			}	else	{
				$markerArray["###PRICE_PAYMENT_TAX_B###"] = "";
				$markerArray["###PRICE_SHIPPING_TAX_B###"] = "";
			}
		}
		
		else {
			$percAmount = $this->basketRef->getPercentAmount($this->basketRef->getTotalPrice(), $perc);
			$percAmountNoTax = $this->basketRef->getPercentAmount($this->basketRef->getTotalPriceNoTax(), $perc);  
			$markerArray["###PRICE_PAYMENT_TAX###"] = $this->priceFormat($this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment()));
			$markerArray["###PRICE_SHIPPING_TAX###"] = $this->priceFormat($this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping()));
			if ($this->conf['hideNoTax']!=1)	{
				$markerArray["###PRICE_PAYMENT_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getPaymentPriceTax($this->basketRef->getPayment())+$percAmount), $this->conf['price_b.']);
				$markerArray["###PRICE_SHIPPING_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getShippingPriceTax($this->basketRef->getShipping())), $this->conf['price_b.']);				
			}	else	{
				$markerArray["###PRICE_PAYMENT_TAX_B###"] = '';
				$markerArray["###PRICE_SHIPPING_TAX_B###"] = '';
			}
		}
		
		
		$headerTemplate = trim($this->cObj->getSubpart($template, "###SHIPPING###"));
		if ($this->basketRef->isFreeShipping() && $this->conf['freeShippingMessage']==1)	{
			$markerArray["###PRICE_SHIPPING_TAX###"] = $this->pi_getLL('free_shipping_message', 'Free shipping');
			$headerTemplate = $this->cObj->substituteSubpart($headerTemplate, '###FREESHIPPING_NO###','',0,0);
		}	else	{
			$headerTemplate = $this->cObj->substituteSubpart($headerTemplate, '###FREESHIPPING_YES###','',0,0);
		}
		
		$template = $this->cObj->substituteSubpart($template, '###SHIPPING###', $this->cObj->substituteMarkerArray($headerTemplate, $markerArray),0,0);
		
		//$this->basketRef->getTotalPrice();
		
		//$listTemplate = trim($this->cObj->getSubpart($template, "###PRODUCTS###"));
		//$template = $this->cObj->substituteSubpart($template, '###PRODUCTS###', $this->show_basket($basketProducts, $template, " disabled", true),0,0);
		
		
		$infoTemplate = trim($this->cObj->getSubpart($template, "###PERSONAL_INFO###"));
		$template = $this->cObj->substituteSubpart($template, '###PERSONAL_INFO###', $this->show_personal_info($infoTemplate, true, 1),0,0);
		
		
		if ($this->conf['pid_userinfo']>0)
			$mainMarkerArray["###TO_USERINFO###"] = $this->pi_getPageLink($this->conf["pid_userinfo"]);
		else
			$mainMarkerArray["###TO_USERINFO###"] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
		
		
		$template = $this->cObj->substituteMarkerArray($template, $mainMarkerArray);
		
		return $template;
	}
	
	
	
	

	/**
	 * This function finalizes the order.
	 */
	function show_finalize() {
		$transactionBS = array();
		
		$confArray = array();
		$paymentMethod = "";
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $bankCode = $_procObj->getBankCode();
		        if ($this->basketRef->getPaymentBankcode($this->basketRef->getPayment()) == $bankCode)	{
		        	$paymentMethod = $_procObj;
		        }
		    }
		}
		
		if ($paymentMethod!="")	{
			$confArray = $_procObj->getDefaultConfiguration();
			$confArray = array_merge($confArray, $this->conf['payment.'][$this->basketRef->getPayment().'.']);
			$transactionBS = $_procObj->decryptPaymentParameters($confArray, $this->basketRef);
		}
		
		if ($transactionBS['failed']===true)	{
			$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###BANK_FAILED_TEMPLATE###"));
			return $this->manageLabels($template);
		}
		
		$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###FINALIZE_TEMPLATE###"));
		
		$orderID = "";
		$markerArray = array();
		$content = $this->getFinalizeContent($template, $transactionBS, $orderID, $markerArray);

		if (!$this->doNotFinalize)	{
			if ($this->conf['plainTextEmail']==1)
				$mailTemplate = trim($this->cObj->getSubpart($this->config["templateCode"], "###FINALIZE_EMAIL_TEMPLATE_PLAINTEXT###"));
			else
				$mailTemplate = trim($this->cObj->getSubpart($this->config["templateCode"], "###FINALIZE_EMAIL_TEMPLATE###"));
			$mailContent = $this->getEmailContent($mailTemplate, $transactionBS, $orderID, $markerArray);
			$pdf_attach = '';
			$pdf_created = false;
			if ($this->conf['send_pdf']==1 && $this->conf['send_pdf.']['pdf_page']!='' && $this->conf['send_pdf.']['temp_folder']!='')	{
				//$url_pdf = t3lib_div::getIndpEnv('TYPO3_SITE_URL').'index.php?id='.$this->conf['send_pdf.']['pdf_page'].($this->conf['send_pdf.']['pdf_page_typenum']!='' ? '&type='.$this->conf['send_pdf.']['pdf_page_typenum'] : '').'&no_cache=1&tx_extendedshop_pi1[orderID]='.str_replace($this->conf['orderCode'], '', $orderID);
				$hash = false;
				$id_order = str_replace($this->conf['orderCode'], '', $orderID);
				if (t3lib_div::testInt($id_order) && $GLOBALS['TSFE']->fe_user->user['uid']>0)	{
					$resOrder =$GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_orders', 'uid='.$id_order.' AND deleted=0');
					if ($resOrder!==false && $GLOBALS['TYPO3_DB']->sql_num_rows($resOrder)==1)	{
						$rowOrder = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resOrder);
						$user = $GLOBALS['TSFE']->fe_user->user;
						$hash = ($user['uid'].'_'.$user['lastlogin'].'_'.$user['pid'].'_'.sha1($user['password']).'_'.$rowOrder['crdate'].'_'.$rowOrder['pid']);
						$hash = sha1($hash);
					}
				}
				
				$linkconf['parameter'] = $this->conf['send_pdf.']['pdf_page'];
				$linkconf['additionalParams'] = '&tx_extendedshop_pi1[checkHash]='.$hash.'&;no_cache=1&tx_extendedshop_pi1[orderID]='.str_replace($this->conf['orderCode'], '', $orderID).($this->conf['send_pdf.']['pdf_page_typenum']!='' ? '&type='.$this->conf['send_pdf.']['pdf_page_typenum'] : '');
				$url_pdf = t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->typolink_URL($linkconf);
				
				//$pdf_data = file_get_contents($url_pdf);
				/*
				$handle = fopen($url_pdf, 'rb');
				$pdf_data = stream_get_contents($handle);
				fclose($handle);
				*/
				$pdf_data = file($url_pdf);
				$pdf_data = implode('',$pdf_data);
				
				$pdf_attach = $this->conf['send_pdf.']['temp_folder'].$orderID.'.pdf';
				$handle = fopen($pdf_attach,'w');
				fwrite($handle,$pdf_data);
				fclose($handle);
				
				$pdf_created = true;
			}
			$this->send_email($this->manageLabels($mailContent), "EMAIL_ORDER_SUBJECT", $orderID, '', $pdf_attach, $this->conf['plainTextEmail']);
			if ($pdf_created && $this->conf['send_pdf.']['delete_file_after_email']==1)
				unlink($pdf_attach);
			
			//t3lib_div::debug($url_pdf);
			//t3lib_div::debug($pdf_data);
				
			// Hook that can be used to do something after order finalization
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['after_finalization_process']))    {
			    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['after_finalization_process'] as $_classRef)    {
			        $_procObj = &t3lib_div::getUserObj($_classRef);
			        $_procObj->init($this);
			        $_procObj->after_finalization_process($markerArray, $orderID);
			    }
			}
	
			// Clear the basket
			$this->basketRef->emptyProducts();
			$this->basketRef->clearShippingAndPayment();
			$this->finalize = true;
			
			$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basketRef->basket);
		}

		return $content;
	}
	
	
	/**
	 * Function used to compile the finalize email content
	 */
	function getEmailContent($mailTemplate, $transactionBS, &$orderID, &$markerArray)	{
		$mailContent = $this->getFinalizeContent($mailTemplate, $transactionBS, $orderID, $markerArray);
		
		$markerArray['###LABEL_FINALIZE_EMAIL_TEXT###'] = $this->pi_getLL('LABEL_FINALIZE_EMAIL_TEXT');
		$markerArray['###LABEL_FINALIZE_EMAIL_FOOTER###'] = $this->pi_getLL('LABEL_FINALIZE_EMAIL_FOOTER');
		$markerArray['###LABEL_FINALIZE_EMAIL_TITLE###'] = $this->pi_getLL('LABEL_FINALIZE_EMAIL_TITLE');
		
		$userArray = array();
		$userArray['###ORDERID###'] = $orderID;
		foreach ($GLOBALS['TSFE']->fe_user->user as $key => $value)
			$userArray['###USER_'.strtoupper($key).'###'] = $value;
			
		$markerArray['###LABEL_FINALIZE_EMAIL_TEXT###'] = $this->cObj->substituteMarkerArray($markerArray['###LABEL_FINALIZE_EMAIL_TEXT###'], $userArray);
		$markerArray['###LABEL_FINALIZE_EMAIL_FOOTER###'] = $this->cObj->substituteMarkerArray($markerArray['###LABEL_FINALIZE_EMAIL_FOOTER###'], $userArray);
		$markerArray['###LABEL_FINALIZE_EMAIL_TITLE###'] = $this->cObj->substituteMarkerArray($markerArray['###LABEL_FINALIZE_EMAIL_TITLE###'], $userArray);
		
		$mailContent = $this->cObj->substituteMarkerArray($mailContent, $markerArray);		
		return $mailContent;
	}
	
	
	
	/**
	 * This function is used to compile the finalize content. This is used both for final page and final email
	 */
	function getFinalizeContent($template, $transactionBS, &$orderID, &$markerArray)	{
		$markerArray["###ORDER_DATE###"] = date($this->conf['dateFormat'], time());
		$markerArray["###PAYMENT_TITLE###"] = $this->basketRef->getPaymentTitle($this->basketRef->getPayment());
		
		$markerArray["###SHIPPING_TITLE###"] = $this->basketRef->getShippingTitle($this->basketRef->getShipping());
		$perc = $this->conf["payment."][$this->basketRef->getPayment()."."]["perc"];
		if ($perc == ""){
			$perc = 0;
		}
		if ($this->getUserType($this->conf,$this->basketRef->basket) == 0){
			$percAmount = $this->basketRef->getPercentAmount($this->basketRef->getTotalPrice(), $perc);
			$percAmountNoTax = $this->basketRef->getPercentAmount($this->basketRef->getTotalPriceNoTax(), $perc);
			$markerArray["###PRICE_PAYMENT_TAX###"] = $this->priceFormat($this->basketRef->getPaymentPriceTax($this->basketRef->getPayment())+$percAmount);
			$markerArray["###PRICE_SHIPPING_TAX###"] = $this->priceFormat($this->basketRef->getShippingPriceTax($this->basketRef->getShipping()));
			$markerArray["###PRICE_PAYMENT_TAX_RAW###"] = $this->priceFormat($this->basketRef->getPaymentPriceTax($this->basketRef->getPayment())+$percAmount);
			$markerArray["###PRICE_SHIPPING_TAX_RAW###"] = $this->priceFormat($this->basketRef->getShippingPriceTax($this->basketRef->getShipping()));
			if ($this->conf['hideNoTax']!=1)	{
				$markerArray["###PRICE_PAYMENT_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment())+$percAmountNoTax), $this->conf['price_b.']);
				$markerArray["###PRICE_SHIPPING_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping())), $this->conf['price_b.']);
				$markerArray["###PRICE_PAYMENT_TAX_B_RAW###"] = $this->priceFormat($this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment())+$percAmountNoTax);
				$markerArray["###PRICE_SHIPPING_TAX_B_RAW###"] = $this->priceFormat($this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping()));
			}	else	{
				$markerArray["###PRICE_PAYMENT_TAX_B###"] = "";
				$markerArray["###PRICE_SHIPPING_TAX_B###"] = "";
				$markerArray["###PRICE_PAYMENT_TAX_B_RAW###"] = "";
				$markerArray["###PRICE_SHIPPING_TAX_B_RAW###"] = "";
			}
		} 
		else {
			$percAmount = $this->basketRef->getPercentAmount($this->basketRef->getTotalPrice(), $perc);
			$percAmountNoTax = $this->basketRef->getPercentAmount($this->basketRef->getTotalPriceNoTax(), $perc);
			$markerArray["###PRICE_PAYMENT_TAX###"] = $this->priceFormat($this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment())+$percAmountNoTax);
			$markerArray["###PRICE_PAYMENT_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getPaymentPriceTax($this->basketRef->getPayment())),$this->conf['price_b.']);
			$markerArray["###PRICE_SHIPPING_TAX###"] = $this->priceFormat($this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping())+$percAmount);
			$markerArray["###PRICE_SHIPPING_TAX_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getShippingPriceTax($this->basketRef->getShipping())),$this->conf['price_b.']);
			
			$markerArray["###PRICE_PAYMENT_TAX_RAW###"] = $this->priceFormat($this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment())+$percAmountNoTax);
			$markerArray["###PRICE_PAYMENT_TAX_B_RAW###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getPaymentPriceTax($this->basketRef->getPayment())),$this->conf['price_b.']);
			$markerArray["###PRICE_SHIPPING_TAX_RAW###"] = $this->priceFormat($this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping())+$percAmount);
			$markerArray["###PRICE_SHIPPING_TAX_B_RAW###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getShippingPriceTax($this->basketRef->getShipping())),$this->conf['price_b.']);
		}

		$infoTemplate = trim($this->cObj->getSubpart($template, "###PERSONAL_INFO###"));
		if ($this->basketRef->getPaymentMessage($this->basketRef->getPayment()) == "") {
			$infoTemplate = $this->cObj->substituteSubpart($infoTemplate, "###PAYMENT_INFO###", "", $recursive = 0, $keepMarker = 0);
		} else {
			$paymentTemplate = trim($this->cObj->getSubpart($infoTemplate, "###PAYMENT_INFO###"));
			$mA["###INFO_PAGAMENTO###"] = $this->basketRef->getPaymentMessage($this->basketRef->getPayment());
			$paymentTemplate = $this->cObj->substituteMarkerArray($paymentTemplate, $mA);
			$infoTemplate = $this->cObj->substituteSubpart($infoTemplate, "###PAYMENT_INFO###", $paymentTemplate, $recursive = 0, $keepMarker = 0);
		}
		$template = $this->cObj->substituteSubpart($template, '###PERSONAL_INFO###', $this->show_personal_info($infoTemplate, true, 1), 0,0);


		$listTemplate = trim($this->cObj->getSubpart($template, "###PRODUCTS###"));
		$template = $this->cObj->substituteSubpart($template, '###PRODUCTS###', $this->show_basket($this->basketRef->getBasketProducts(), $listTemplate, " disabled", true), 0,0);
		
		/*
		$calculatedSums_tax["total"] = $this->basketRef->getProductsTotal();
		$calculatedSums_no_tax["total"] = $this->basketRef->getProductsTotalNoTax();
		$calculatedSums_tax["payment"] = $this->basketRef->getPaymentPriceTax($this->basketRef->getPayment());
		$calculatedSums_no_tax["payment"] = $this->basketRef->getPaymentPriceNoTax($this->basketRef->getPayment());
		$calculatedSums_tax["shipping"] = $this->basketRef->getShippingPriceTax($this->basketRef->getShipping());
		$calculatedSums_no_tax["shipping"] = $this->basketRef->getShippingPriceNoTax($this->basketRef->getShipping());

		// This is the total for everything
		$calculatedSums_tax["total"] += $calculatedSums_tax["payment"];
		$calculatedSums_tax["total"] += $calculatedSums_tax["shipping"];
		$calculatedSums_no_tax["total"] += $calculatedSums_no_tax["payment"];
		$calculatedSums_no_tax["total"] += $calculatedSums_no_tax["shipping"];
		*/
		
		//if ($this->getUserType() == 0){
			$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->getTotalPrice());
			$markerArray["###BASKET_TOTAL_RAW###"] = $this->basketRef->getTotalPrice();
			if ($this->conf['hideNoTax']!=1)	{
				$markerArray["###BASKET_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getTotalPriceNoTax()), $this->conf['price_b.']);
				$markerArray["###BASKET_TOTAL_B_RAW###"] = $this->basketRef->getTotalPriceNoTax();
			}	else	{
				$markerArray["###BASKET_TOTAL_B###"] = '';
				$markerArray["###BASKET_TOTAL_B_RAW###"] = $this->basketRef->getTotalPriceNoTax();
			}
		//}
		/*
		else {
			$markerArray["###BASKET_TOTAL###"] = $this->cObj->stdWrap($this->priceFormat($calculatedSums_no_tax["total"]), $this->conf['price_b.']);
			$markerArray["###BASKET_TOTAL_B###"] = $this->priceFormat($calculatedSums_tax["total"]);
			$markerArray["###BASKET_TOTAL_RAW###"] = $calculatedSums_no_tax["total"];
			$markerArray["###BASKET_TOTAL_B_RAW###"] = $calculatedSums_tax["total"];
		}
		*/
		
		if ($markerArray["###BASKET_TOTAL_B_RAW###"] > $markerArray["###BASKET_TOTAL_RAW###"])	{
			$markerArray["###BASKET_TOTAL_VAT_RAW###"] = $markerArray["###BASKET_TOTAL_B_RAW###"] - $markerArray["###BASKET_TOTAL_RAW###"];
		}	else	{
			$markerArray["###BASKET_TOTAL_VAT_RAW###"] = $markerArray["###BASKET_TOTAL_RAW###"] - $markerArray["###BASKET_TOTAL_B_RAW###"];
		}
		$markerArray["###BASKET_TOTAL_VAT###"] = $this->priceFormat($markerArray["###BASKET_TOTAL_VAT_RAW###"]);
		
		// Hook that can be used to manage the basket settings
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['basket_management']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['basket_management'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $markerArray = $_procObj->evaluateBasketArray($this, $markerArray, $template, 1);
		    }
		}
		
		
		
		$markerArray["###PERSONAL_NOTE###"] = "";
		if ($this->basketRef->basket["personinfo"]["NOTE"] != "")
			$markerArray["###PERSONAL_NOTE###"] = strip_tags($this->basketRef->basket["personinfo"]["NOTE"]);
		//$content .= $this->cObj->substituteMarkerArray($footerTemplate, $markerArray);
		

		$markerArray["###PERSONAL_EMAIL###"] = strip_tags($this->basketRef->basket["personinfo"]["EMAIL"]);

		if (!$this->doNotFinalize)	{
			if ($orderID=="")	{
				$user = $this->usersManagementObj->manageUsers();
				//t3lib_div::debug($user);
				$order = t3lib_div::makeInstance("tx_extendedshop_order");
				$order->init($this->conf, $this);
				$orderID = $order->insertOrder($user["idCustomer"], $user["idDelivery"], $this->manageLabels($template), $transactionBS["###PAY1_SHOPTRANSACTIONID###"], $markerArray, $this->basketRef);
			}
		}

		$markerArray["###ORDERID###"] = $orderID;
		$markerArray["###ORDER_NUM_ROWS###"] = $this->basketRef->getNumRows();
		
		if ($this->basketRef->isFreeShipping() && $this->conf['freeShippingMessage']==1)	{
			$markerArray["###PRICE_SHIPPING_TAX###"] = $this->pi_getLL('free_shipping_message', 'Free shipping');
			$template = $this->cObj->substituteSubpart($template, '###FREESHIPPING_NO###','',0,0);
		}	else	{
			$template = $this->cObj->substituteSubpart($template, '###FREESHIPPING_YES###','',0,0);
		}

		$content = $this->manageLabels($template);
		$content = $this->cObj->substituteMarkerArray($content, $markerArray);
		
		if (is_array($transactionBS))
			$content = $this->cObj->substituteMarkerArray($content, $transactionBS);
		
		return $content;
	}
	
	
	

	/**
	 * This function shows the page that allows the user to go to the online payment gateway
	 */
	function show_bank() {
		$template = '';
		
		//if ($this->getUserType()==0)	{
			$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->getTotalPrice());
			$markerArray["###BASKET_TOTAL_NOFORMAT###"] = $this->basketRef->getTotalPrice();
			//$totalAmount = $this->basketRef->getTotalPrice();
		//}
		/*
		else	{
			$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->getTotalPriceNoTax());
			$markerArray["###BASKET_TOTAL_NOFORMAT###"] = $this->basketRef->getTotalPriceNoTax();
			//$totalAmount = $this->basketRef->getTotalPriceNoTax();
		}
		*/
		
		// Hook that can be used to manage the basket settings
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['basket_management']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['basket_management'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $markerArray = $_procObj->evaluateBasketArray($this, $markerArray, $template, 1);
		    }
		}
		
		$totalAmount = $markerArray["###BASKET_TOTAL_NOFORMAT###"];
		
		
		$customTemplate = "";
		 
		$confArray = array();
		$paymentMethod = "";
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $_procObj->init($this->cObj, $this->basketRef, $this);
		        $bankCode = $_procObj->getBankCode();
		        if ($this->basketRef->getPaymentBankcode($this->basketRef->getPayment()) == $bankCode)	{
		        	$paymentMethod = $_procObj;
		        }
		    }
		}
		
		if ($paymentMethod!="")	{
			$confArray = $paymentMethod->getDefaultConfiguration();
			$confArray = array_merge($confArray, $this->conf['payment.'][$this->basketRef->getPayment().'.']);
			$markerArray = $paymentMethod->calculatePaymentParameters($markerArray, $confArray, $this->basketRef, $totalAmount, $this);
        	$customTemplate = $paymentMethod->getCustomTemplate($this, $confArray);
		}

		if ($customTemplate!="")
			$template = $customTemplate;
		else
			$template = trim($this->cObj->getSubpart($this->config["templateCode"], "###ONLINEBANK_TEMPLATE###"));

		return $this->cObj->substituteMarkerArray($this->manageLabels($template), $markerArray);
	}
	
	
	

	/**
	 * This function returns the product title. Useful for replace the page title in the product detail page
	 */
	function product_title($content = "", $conf = "") {
		// TODO: To insert the language control
		
		global $TSFE;
		//t3lib_div::debug($TSFE->sys_language_uid);
		$prefix = preg_replace("/\:.*/", ':', $content);

		$title = "";
		if ($this->piVars['productID'] != '') {
			if (is_int($this->piVars['productID'])){
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . $this->piVars['productID'].' '.$this->addQueryLanguage.' '.$this->addQueryEnableStock.' '.$this->cObj->enableFields('tx_extendedshop_products'), '', '', '');
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);				
			} else {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'deleted<>1 AND uid=' . (int)$this->piVars['productID'], '', '', '');
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);				
			}
			
			// creation of an instance of product class to take the page title		
			$prod = t3lib_div::makeInstance("tx_extendedshop_products");
			$prod->init($row, $this, $res);
			$title = $prod->getPageTitle();
		}
		
		if ($title == "") {
			$title = $TSFE->page['title'];
		}
		
		return $title;
	}
	
	
	

	/**
	 * Extends the internal pid_list by the levels given by $recursive
	 */
	function initRecursive($recursive) {
		if ($recursive) { // get pid-list if recursivity is enabled
			$pid_list_arr = explode(",", $this->pid_list);
			$this->pid_list = "";
			while (list (, $val) = each($pid_list_arr)) {
				$this->pid_list .= $val . "," . $this->cObj->getTreeList($val, intval($recursive));
			}
			$this->pid_list = preg_replace("/\,$/", "", $this->pid_list);
		}
	}


	/**
	 * Generates an array, ->pageArray of the pagerecords from ->pid_list
	 */
	function generatePageArray() {
		// Get pages (for category titles)		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('title,uid', 'pages', 'uid IN (' . $this->pid_list . ')');
		$this->pageArray = array ();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$this->pageArray[$row["uid"]] = $row;
		}
	}

	/**
	 * Formatting a price
	 */
	function priceFormat($double, $priceDecPoint = "", $priceThousandPoint = "") {
		if ($priceDecPoint == "")
			$priceDecPoint = $this->conf["priceDecPoint"];
		if ($priceThousandPoint == "")
			$priceThousandPoint = $this->conf["priceThousandPoint"];
		//echo($double."-".intval($this->conf["priceDec"])."-".$priceDecPoint."-".$this->conf["priceThousandPoint"]);
		return number_format($double, intval($this->conf["priceDec"]), $priceDecPoint, $this->conf["priceThousandPoint"]);
	}

	

	/**
	 * Substitute all labes with the correct language label
	 */
	function manageLabels($content) {
		$lang = $this->pi_getLL("LABELS");
		$markers = explode(",", $lang);
		foreach ($markers as $marker) {
			$markerArray["###LABEL_" . $marker . "###"] = $this->pi_getLL("LABEL_" . $marker);
		}

		$markerArray["###SWORDS###"] = $this->piVars["swords"] ? $this->piVars["swords"] : "";
		$content = $this->cObj->substituteMarkerArray($content, $markerArray);

		$content = $this->manageFormLinks($content);
		return $content;
	}

	/**
	 * Substitute all labes with the correct language label
	 */
	function manageFormLinks($content) {
		//print_r($this->config["pid_basket"]);
		$markerArray["###FORM_SEARCH###"] = $this->pi_getPageLink($GLOBALS["TSFE"]->id);
		$markerArray["###FORM_ADD_COMMENT###"] = $this->pi_linkTP_keepPIvars_url();
		$content = $this->cObj->substituteMarkerArray($content, $markerArray);
		return $content;
	}


	/**
	 * This function is used to init the payment options with external payment extensions
	 */
	function initPaymentConfArray()	{
		$confArr = array();
		// This hook is used to retrieve default configuration from external payment methods
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['payment_method'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        //$_procObj->init($this->cObj, $this->basketRef, $this);
		        $confArr[$_procObj->getBankCode()."."] = $_procObj->getDefaultConfiguration();
		    }
		}

		$this->conf['payment.'] = array_merge($confArr, $this->conf["payment."]);
	}


	

	/**
	 * Generates a radio or selector box for payment
	 */
	function generatePaymentSelector($key) {

		$type = $this->conf[$key . "."]["radio"];
		$userInfo = $this->basketRef->getPersonInfo();
		$out = "";
		$valueSelected = $this->basketRef->getPayment();
		$template = $this->conf[$key . "."]["template"] ? $this->conf[$key . "."]["template"] : '<nobr>###IMAGE### <input type="radio" name="tx_extendedshop_pi1[' . $key . ']" onclick="###ONCLICK###" value="###VALUE###"###CHECKED###> ###TITLE###</nobr><BR>';
		//$wrap = $this->conf[$key . "."]["wrap"] ? $this->conf[$key . "."]["wrap"] : '<select name="tx_extendedshop_pi1[recs][final][' . $key . ']" onChange="submit()">|</select>';

		// Ordering
		$order = array();
		foreach ($this->conf['payment.'] as $key => $value)	{
			if ($key=='radio' || $key=='select')	{
				$order[$key] = $value;
				continue;
			}
			$key = substr($key,0,-1);
			if ($this->conf['payment.'][$key.'.']['position']=='')
				$pos = $key;
			else
				$pos = $this->conf['payment.'][$key.'.']['position'];
			while (isset($order[$pos]))
				$pos++;
			$order[$pos] = $value;
			$order[$pos]['keyvalue'] = $key;
		}
		ksort($order);
		$confArr = $order;

		if ($valueSelected=='')	{
			$valueSelected = $this->conf['payment.']['default'];
			$this->basketRef->setPayment($valueSelected);
		}
	
		//reset $confArr;
		foreach ($confArr as $keyArr=>$valueArr){
			if ($keyArr != "radio"){
				if ($valueArr['minAmount']!="" && $valueArr['minAmount']>$this->basketRef->getProductsTotal())
					continue;
				if ($valueArr['maxAmount']!="" && $valueArr['maxAmount']<$this->basketRef->getProductsTotal())
					continue;
				$markerArray = array ();
				$markerArray["###VALUE###"] = $valueArr['keyvalue'];
				
				if ($this->conf['pid_finalize']>0)
					$markerArray['###ONCLICK###'] = 'document.extendedshop_payment.action=\''.$this->pi_getPageLink($GLOBALS['TSFE']->id).'\'; document.extendedshop_payment.submit();';
				else
					$markerArray['###ONCLICK###'] = 'submit()';
				
				if ($valueSelected == $valueArr['keyvalue'])
					$markerArray["###CHECKED###"] = "checked"; //(intval($keyArr) == $active ? " checked" : "");
					else
						$markerArray["###CHECKED###"] ="";
				$markerArray["###TITLE###"] = $valueArr["title"];
				$markerArray["###IMAGE###"] = $this->cObj->IMAGE($valueArr["image."]);
				$out .= $this->cObj->substituteMarkerArrayCached($template, $markerArray);
			}
		}
		
		return $out;
	}

	
	/**
	 * Old TS based country selector
	 */
	function generateSelectForCountry($type, $typeBasket) {
		
		$confArr = $this->cleanConfArr($this->conf["shipping."]);
		$out = "";

		$wrap = '<select name="tx_extendedshop_pi1['.$type.'][COUNTRY]" onChange="submit()">|</select>';
		$active = "";

		while (list ($key, $val) = each($confArr)) {
			if ($this->basketRef->basket[$typeBasket]["COUNTRY"] == $key) {
				$active = $key;
			}
			$out .= '<option value="' . htmlspecialchars($key) . '"' . (intval($key) == $active ? " selected" : "") . '>' . htmlspecialchars($val["title"]) . '</option>';
		}
 
		$out = $this->cObj->wrap($out, $wrap);
		return $out;
	}
	
	/**
	 * New static info based country selector
	 */
	function generateSelectForCountryS($type, $typeBasket){
		$confArr = $this->cleanConfArr($this->conf["shipping."]);
		$out = "";

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'static_countries', '', '', 'cn_short_en ASC', '');
		
		$wrap = '<select tabindex="'.$this->conf['countryTabIndex.'][$type].'" name="tx_extendedshop_pi1['.$type.'][COUNTRY]">|</select>';
		$active = "";

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			// TODO: ci vuole la funzione getBasketCountry
			if ($this->basketRef->basket[$typeBasket]["COUNTRY"] == $row["cn_iso_3"] || ($this->basketRef->basket[$typeBasket]["COUNTRY"]=='' && $row["cn_iso_3"]==$this->conf['defaultCountry'])) {
				$active = $row["cn_iso_3"];
				$out .= '<option value="'.htmlspecialchars($row["cn_iso_3"]).'" selected="">'.htmlspecialchars($row["cn_short_en"]).'</option>';
			} else {
				$out .= '<option value="' . htmlspecialchars($row["cn_iso_3"]) . '">' . htmlspecialchars($row["cn_short_en"]) . '</option>';
			}
			//$out .= '<option value="' . htmlspecialchars($row["cn_iso_3"]) . '"' . (($row["cn_iso_3"]) == $active ? " selected" : "") . '>' . htmlspecialchars($row["cn_short_en"]) . '</option>';
		}
		if ($active == "" && $this->conf['deliveryEmptyCountry']!=''){
			$out .= '<option value="" selected="">'.$this->conf['deliveryEmptyCountry'].'</option>';
		}
		$out = $this->cObj->wrap($out, $wrap);
		return $out;		
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
	 * Generates a radio or selector box for shipping
	 */
	function generateShippingSelector($key) {
		$type = $this->conf[$key . "."]["radio"];
		$userInfo = $this->basketRef->getPersonInfo();
		$out = "";
		$valueSelected = $this->basketRef->getShipping();

		$origCountry = $this->basketRef->getCountryDestinationUid();
		$template = $this->conf[$key . "."]["template"] ? $this->conf[$key . "."]["template"] : '###IMAGE### <input type="radio" name="tx_extendedshop_pi1[' . $key . ']" onclick="###ONCLICK###" value="###VALUE###"###CHECKED###> ###TITLE### ###DESCRIPTION###<BR>';
		//$wrap = $this->conf[$key . "."]["wrap"] ? $this->conf[$key . "."]["wrap"] : '<select name="tx_extendedshop_pi1[recs][final][' . $key . ']" onChange="submit()">|</select>';

		$confArr = array();
		if ($this->conf['enableStaticInfoTable']==0)	{
			$confArr = $this->conf["shipping."];	
		}	else	{
			// This will retrieve all the shipping methods available for the selected country
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('shipping', 'tx_extendedshop_shippingplace', 'country="'.$origCountry.'" '.$this->cObj->enableFields('tx_extendedshop_shippingplace'));
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)<1)	{
				$out = $this->pi_getLL('shipping_not_available');
				return array(false, $out);
			}	else	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if ($row['shipping']!='')	{
					if (!t3lib_div::inList($row['shipping'], $valueSelected))
						$valueSelected = '';
					$resMet = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_shipping', 'uid IN ('.$row['shipping'].') '.$this->cObj->enableFields('tx_extendedshop_shipping'));
					if ($GLOBALS['TYPO3_DB']->sql_num_rows($resMet)>0)	{
						while ($rowMet = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resMet))
							$confArr[$rowMet['uid'].'.'] = $rowMet;
					}
				}	else	{
					$valueSelected = '';
				}
			}
		}
		
		// Hook that can be used to modify the shipping confArray before showing it to the user
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['shipping_methods'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $confArr = $_procObj->evaluateShippingConfArray($confArr, $this, $valueSelected);
		    }
		}

		if (sizeof($confArr)==0)	{
			$out = $this->pi_getLL('shipping_not_available');
			return array(false, $out);
		}
		
		if ($valueSelected=='' && in_array($this->conf['shipping.']['default'].'.', $confArr))	{
			$valueSelected = $this->conf['shipping.']['default'];
			$this->basketRef->setShipping($valueSelected);
		}
		
		//reset $confArr;
		foreach ($confArr as $keyArr=>$valueArr){
			if ($keyArr != "radio"){
				if ($valueArr['minAmount']!="" && $valueArr['minAmount']>$this->basketRef->getProductsTotal())
					continue;
				if ($valueArr['maxAmount']!="" && $valueArr['maxAmount']<$this->basketRef->getProductsTotal())
					continue;
				$markerArray = array ();
				$markerArray["###VALUE###"] = substr($keyArr,0,-1);
				
				if ($valueSelected=='')	{
					// The first becames the default one
					$valueSelected = substr($keyArr,0,-1);
					$this->basketRef->setShipping(substr($keyArr,0,-1));
				}
				
				
				if ($this->conf['pid_finalize']>0)
					$markerArray['###ONCLICK###'] = 'document.extendedshop_payment.action=\''.$this->pi_getPageLink($this->conf['pid_payment']).'\'; document.extendedshop_payment.submit();';
				else
					$markerArray['###ONCLICK###'] = 'submit()';
				
				if ($valueSelected == substr($keyArr,0,-1))
					$markerArray["###CHECKED###"] = "checked"; //(intval($keyArr) == $active ? " checked" : "");
					else
						$markerArray["###CHECKED###"] ="";
				$markerArray["###TITLE###"] = $valueArr["title"];
				
				$image = $this->conf['shipping.']['image.'];
				$basePath = $valueArr["imagePath"]!='' ? $valueArr["imagePath"] : 'uploads/tx_extendedshop/';
				$image['file'] = $basePath.$valueArr["image"];		
				$markerArray["###IMAGE###"] = $this->cObj->IMAGE($image);
				if ($valueArr['description']!='')
					$markerArray["###DESCRIPTION###"] = '('.$valueArr["description"].')';
				else
					$markerArray["###DESCRIPTION###"] = '';
				$out .= $this->cObj->substituteMarkerArrayCached($template, $markerArray);
			}
		}

		return array(true, $out);
	}
	

	
	/**
	* Invokes the HTML mailing class
	*
	* @param string  $HTMLContent: HTML version of the message
	* @param string  $label: the label used for the subject
	* @param string  $orderID: uid of the order
	* @param string  $email: recipients
	* @param string  $fileAttachment: file name
	* @return void
	*/
	function send_email($HTMLContent, $label, $orderID = "", $email = "", $fileAttachment = '', $plainText = 0) {
		// si effettua il controllo della validit� della mail. Se non � valida non si invia niente.
		// questo controllo in futuro dovr� essere fatto sull'inserimento dati (a monte)
		
		$HTMLContent = $this->clearInput($HTMLContent);

		if (t3lib_div::validEmail($this->basketRef->basket["personinfo"]["EMAIL"])){
			$fromName = $this->conf["orderEmail_fromName"];
			$fromEmail = $this->conf["orderEmail_from"];
			$replyTo = '';
			
			$recipients = $this->conf["orderEmail_to"];
			if ($email != "") {
				$recipients .= "," . $email;
			} else {
				$recipients .= "," . $this->basketRef->basket["personinfo"]["EMAIL"];
			}
			$recipients = t3lib_div :: trimExplode(",", $recipients, 1);
			
			$mA["###ORDERID###"] = $orderID;
			$subject = $this->cObj->substituteMarkerArray($this->pi_getLL($label), $mA);
			
			if ($this->conf["cssMail"] != "")	{
				$cssCode = "<style type='text/css'>".$this->cObj->fileResource($this->conf["cssMail"])."</style>";
			}
			
			// HTML
			if (is_array($recipients)) {
				$defaultSubject = 'Order data';
				
				if (!$plainText)	{
					$HTMLContent = "<html><body>" . $cssCode . $HTMLContent . "</body></html>";
					if ($HTMLContent)	{
						$parts = spliti('<title>|</title>', $HTMLContent, 3);
					}
				}
	
				$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_htmlmail');
				$Typo3_htmlmail->start();
				$Typo3_htmlmail->mailer = '';
				$Typo3_htmlmail->subject = $subject;
				$Typo3_htmlmail->from_email = $fromEmail;
				$Typo3_htmlmail->returnPath = $fromEmail;
				$Typo3_htmlmail->from_name = $fromName;
				$Typo3_htmlmail->from_name = implode(' ' , t3lib_div::trimExplode(',', $Typo3_htmlmail->from_name));
				$Typo3_htmlmail->replyto_email = $replyTo ? $replyTo :$fromEmail;
				$Typo3_htmlmail->replyto_name = $replyTo ? '' : $fromName;
				$Typo3_htmlmail->replyto_name = implode(' ' , t3lib_div::trimExplode(',', $Typo3_htmlmail->replyto_name));
				$Typo3_htmlmail->organisation = '';
				$Typo3_htmlmail->priority = 3;
	
				// ATTACHMENT
				if ($fileAttachment && file_exists($fileAttachment)) {
					$Typo3_htmlmail->addAttachment($fileAttachment);
				}
	
				// HTML
				if (!$plainText && trim($HTMLContent)) {
					$Typo3_htmlmail->theParts['html']['content'] = $HTMLContent;
					$Typo3_htmlmail->theParts['html']['path'] = '';
					$Typo3_htmlmail->extractMediaLinks();
					$Typo3_htmlmail->extractHyperLinks();
					$Typo3_htmlmail->fetchHTMLMedia();
					$Typo3_htmlmail->substMediaNamesInHTML(0); // 0 = relative
					$Typo3_htmlmail->substHREFsInHTML();
						
					$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
				}
				
				// PLAIN
				if ($plainText)	{
					$Typo3_htmlmail->addPlain(strip_tags($HTMLContent));
				}
				// SET Headers and Content
				$Typo3_htmlmail->setHeaders();
				$Typo3_htmlmail->setContent();
				$Typo3_htmlmail->setRecipient($recipients);
				$Typo3_htmlmail->sendtheMail();				
			}			
		} //end first if		
	}
	
	
	

	// **************************

	// GestPay (Banca Sella) interaction

	// **************************
	/**
	 * Returns the complete link to Banca Sella
	 * https://ecomm.sella.it/gestpay/pagam.asp
	 */
	function calculateGestPayParameters($markerArray) {
		$link = $this->basketRef->basket["payment"]["paylink"];

		$objCrypt = new Java("GestPayCrypt");

		if (!$objCrypt)
			echo ("Exception: " .
			java_last_exception_get());
		else {

			$myshoplogin = $this->basketRef->basket["payment"]["ShopLogin"];
			$mycurrency = $this->basketRef->basket["payment"]["UICCODE"];
			$myamount = $this->priceFormat($this->calculatedSums_tax['total'], ".");
			$mytransactionID = time();
			$myerrpage = $this->basketRef->basket["payment"]["errpage"];

			$mybuyername = $this->basketRef->basket["personinfo"]["NAME"];
			$mybuyeremail = $this->basketRef->basket["personinfo"]["EMAIL"];
			$mylanguage = $this->basketRef->basket["payment"]["language"];
			$mycustominfo = "";

			$return = $this->basketRef->basket["payment"]["return"];

			$objCrypt->SetShopLogin($myshoplogin);
			$objCrypt->SetCurrency($mycurrency);
			$objCrypt->SetAmount($myamount);
			$objCrypt->SetShopTransactionID($mytransactionID);
			$objCrypt->SetBuyerName($mybuyername);
			$objCrypt->SetBuyerEmail($mybuyeremail);
			$objCrypt->SetLanguage($mylanguage);
			$objCrypt->SetCustomInfo($mycustominfo);

			$objCrypt->Encrypt();

			if (!java_last_exception_get()) {
				$ed = $objCript->GetErrorDescription();
				if ($ed != "") {
					echo ("Errore di encoding: " . $objCrypt->GetErrorCode() . " " . $ed . " <br />");
				} else {
					$b = $objCrypt->GetEncryptedString();
					$a = $objCrypt->GetShopLogin();
				}
			}
			return $link . "?a=" . $a . "&b=" . $b;
		}
	}

	/**
	* Returns an array with all the information of the response of PayPal
	*/
	function decryptDataFromGestPay($transactionBS) {
		/*$business = $this->basketExtra["payment"]["ShopLogin"];
		$transactionBS["###PAY1_SHOPTRANSACTIONID###"] = t3lib_div::_GET('item_name');
		$transactionBS["###ORDERTRACKINGNO###"] = t3lib_div::_GET('item_number');
		$transactionBS["###ORDERDATE###"] = t3lib_div::_GET('on0');
		$transactionBS["ShopLogin"] = $business;*/

		$parametro_a = trim(t3lib_div :: _GET('a'));
		$parametro_b = trim(t3lib_div :: _GET('b'));

		$objdeCrypt = new Java("GestPayCrypt");

		if ($objdeCrypt) {
			echo ("Exception: " . java_last_exception_get());
		} else {
			$objdeCrypt->SetShopLogin($parametro_a);
			$objdeCrypt->SetEncryptedString($parametro_b);
			$objdeCrypt->Decrypt();

			$transactionBS["###MYSHOPLOGIN###"] = trim($objdeCrypt->GetShopLogin());
			$transactionBS["###MYCURRENCY###"] = trim($objdeCrypt->GetCurrency());
			$transactionBS["###MYAMOUNT###"] = trim($objdeCrypt->GetAmount());
			$transactionBS["###MYSHOPTRANSACTIONID###"] = trim($objdeCrypt->GetShopTransactionID());
			$transactionBS["###MYBUYERNAME###"] = trim($objdeCrypt->GetBuyerName());
			$transactionBS["###MYBUYEREMAIL###"] = trim($objdeCrypt->GetBuyerEmail());
			$transactionBS["###MYTRANSACTIONRESULT###"] = trim($objdeCrypt->GetTransactionResult());
			$transactionBS["###MYAUTHORIZATIONCODE###"] = trim($objdeCrypt->GetAuthorizationCode());
			$transactionBS["###MYERRORCODE###"] = trim($objdeCrypt->GetErrorCode());
			$transactionBS["###MYERRORDESCRIPTION###"] = trim($objdeCrypt->GetErrorDescription());
			$transactionBS["###MYBANKTRANSACTIONID###"] = trim($objdeCrypt->GetBankTransactionID());
			$transactionBS["###MYALERTCODE###"] = trim($objdeCrypt->GetAlertCode());
			$transactionBS["###MYALERTDESCRIPTION###"] = trim($objdeCrypt->GetAlertDescription());
			$transactionBS["###MYCUSTOMINFO###"] = trim($objdeCrypt->GetCustomInfo());
			$transactionBS["###PAY1_SHOPTRANSACTIONID###"] = $transactionBS["###MYTRANSACTIONRESULT###"];
		}

		//t3lib_div::debug($transactionBS);
		return $transactionBS;
	}


	

	// **************************
	// Authorize.net interaction
	// **************************

	/**
	 * Returns the complete form to Authorize.net
	 */
	function calculateAuthorizeParameters($markerArray) {
		$linkErrore = $this->basketRef->basket["payment"]["linkError"];
		$link = $this->basketRef->basket["payment"]["paylink"];
		$ShopLogin = $this->basketRef->basket["payment"]["ShopLogin"];
		$TransactionKey = $this->basketRef->basket["payment"]["TransactionKey"];

		$markerArray["###LOGIN_ID###"] = $ShopLogin;
		$markerArray["###FORM_URL_ONLINEBANK###"] = $link;
		$markerArray["###RETURN_LINK###"] = $this->basketRef->basket["payment"]["returnUrl"];

		$markerArray["###ORDER_TRACKING_NO###"] = time();
		srand(time());
		$sequence = rand(1, 1000);

		$markerArray["###FINGERPRINT_FIELDS###"] = InsertFP($ShopLogin, $TransactionKey, $markerArray["###BASKET_TOTAL_NOFORMAT###"], $sequence);

		return $markerArray;
	}

	/**
	* Returns an array with all the information of the response of Authorize.net gateway
	*/
	function decryptDataFromAuthorize($transaction) {
		$transaction["response"] = t3lib_div :: _GP('x_response_code');
		$transaction["reason"] = t3lib_div :: _GP('x_response_reason_code');
		$transaction["reason_text"] = t3lib_div :: _GP('x_response_reason_text');
		$transaction["###PAY1_SHOPTRANSACTIONID###"] = t3lib_div :: _GP('x_trans_id');
		return $transaction;
	}

	/**
	* Format string with general_stdWrap from configuration
	*
	* @param	string		$string to wrap
	* @return	string		wrapped string
	*/
	function formatStr($str) {
		if (is_array($this->conf['general_stdWrap.'])) {
			$str = $this->cObj->stdWrap($str, $this->conf['general_stdWrap.']);
		}
		return $str;
	}
	
	
	/**
	 * html without hidden non-associated tags
	 */	
	function clearInput($html) {
  		$html = preg_replace("/(\#\#\#)+[a-z,A-Z,0-9,\@,\!,\%\_]+(\#\#\#)/", "", $html);
  		//$html = ereg_replace("%", " ", $html);
  		//$html = ereg_replace("[(,))]", " ", $html);
  		return $html;
 	}

	function enableStaticInfoTable(){
		if ($this->conf['enableStaticInfoTable'] == 1){
			return true;
		} else {
			return false;
		}
	}
	
	function setRequired($array, $listRequired){
		$count = 0;
		while (list ($key, $field) = each($listRequired)) {
			$field = strtoupper($field);
			if ($array[$field] == "") {
				$requiredOK = false;
			} else
				$count++;
		}
		if ($count == 0)
			return true;
		else
			return false;
	}
	
	/*
	 * This funtion determine wich type of user is using the sistem
	 * If the user is type 0 we will show prices with tax (prices no tax)
	 * If the user is type 1 we will show prices no tax (prices with tax)
	 * 
	 * return 0 or 1
	 */
	function getUserType($conf, $basket = ''){
		//t3lib_div::debug($conf, 'tx_estendedshop_pi1.php : getUserType() : $conf');
		if ($conf['disableVATUserCheck']==1){
			if($conf['debug']){
				t3lib_div::debug($conf['disableVATUserCheck'], 'extendedshop_pi1.php : getUserType() : $conf[disableVATUserCheck]');
			}
			return 0;
		}
		
		if($basket != '')
			$delivery = $basket['delivery'];
		else
			$delivery = $this->basketRef->basket['delivery'];
		
		if($conf['debug']){
			t3lib_div::debug($basket,'extendedshop_pi1.php : getUserType() : $basket');
			t3lib_div::debug($this->basketRef->basket['delivery'], 'extendedshop_pi1.php : getUserType() : $this->basketRef->basket[delivery]');
			t3lib_div::debug($delivery, 'extendedshop_pi1.php : getUserType() : $delivery');
		}
		
		if ($delivery['COUNTRY']=='')	{
			
			if($basket != '')
				$user = $basket['personinfo'];
			else
				$user = $this->basketRef->basket['personinfo'];
			
			if ($user['COUNTRY']==''){
				$userinfo = $GLOBALS["TSFE"]->fe_user->user;
			}
			else	{
				$userinfo['country'] = $user['COUNTRY'];
				$userinfo['tx_extendedshop_private'] = $user['PRIVATE'];
			}
		}
		else	{
			$userinfo['country'] = $delivery['COUNTRY'];
			if($basket != '')
				$user = $basket['personinfo'];
			else
				$user = $this->basketRef->basket['personinfo'];
			
			$userinfo['tx_extendedshop_private'] = $user['PRIVATE'];
		}
		
		if ($userinfo["country"] == "")
			$country = $userinfo["static_info_country"];
		else
			$country = $userinfo["country"];
		
		
		if($conf['debug'])
			t3lib_div::debug($country, 'extendedshop_pi1.php : getUserType() : $country');
		
		if($country == 'ITA'){
			return 0;
		}
		elseif($country == ''){
			return 0;
		}
		else{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('cn_currency_iso_3', 'static_countries', 'cn_iso_3="'.$country.'"', '', '', '1');
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			
			if($conf['debug']){
				t3lib_div::debug($row,'extendedshop_pi1.php : getUserType() : $row');
				t3lib_div::debug($userinfo["tx_extendedshop_private"],'extendedshop_pi1.php : getUserType() : $userinfo[tx_extendedshop_private]');
			}
				
			if($row["cn_currency_iso_3"] == 'EUR' && $userinfo["tx_extendedshop_private"] == 0){
				return 0;
			}
			else{
				return 1;
			}
		}


		/*if ($country == 'ITA' && $GLOBALS["TSFE"]->loginUser == "")	{
			return 0;
		}	elseif ($country == '')	{
			return 0;
		}	else	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('cn_currency_iso_3', 'static_countries', 'cn_iso_3="'.$country.'"', '', '', '1');
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			if ($row["cn_currency_iso_3"] != 'EUR')
				return 1;
			else{
				if ($userinfo["tx_extendedshop_private"] == 0)	{
					return 0;
				}	else	{
					return 1;
				}
			}		
		}*/
	}
	
	function calculatePriceNoTax($price, $tax){
		$tax = $tax/100;
		return $priceNoTax = round(($price/(1+$tax)),2);	
	}
	
	
	function getNameUser($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('name', 'fe_users', 'uid='.$key.'', '', '', '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row["name"];
	}
	
	
	
	
	
	
	function makePageBrowser($res_count=0, $pointerName='productPage') {
  		
  		$this->internal['res_count'] = $res_count;
  		$this->internal['pagefloat'] = $this->conf['pageBrowser.']['pagefloat'];
		$this->internal['showFirstLast'] = $this->conf['pageBrowser.']['showFirstLast'];
		$this->internal['showRange'] = $this->conf['pageBrowser.']['showRange'];
		$this->internal['dontLinkActivePage'] = $this->conf['pageBrowser.']['dontLinkActivePage'];
		$this->internal['results_at_a_time'] = $this->conf['list.']['maxItems'];
		$this->internal['maxPages'] = $this->conf['pageBrowser.']['maxPages'];

		$wrapArrFields = explode(',', 'disabledLinkWrap,inactiveLinkWrap,activeLinkWrap,browseLinksWrap,showResultsWrap,showResultsNumbersWrap,browseBoxWrap');
		$wrapArr = array();
		foreach($wrapArrFields as $key) {
			if ($this->conf['pageBrowser.'][$key]) {
				$wrapArr[$key] = $this->conf['pageBrowser.'][$key];
			}
		}

		if ($wrapArr['showResultsNumbersWrap'] && strpos($this->LOCAL_LANG[$this->LLkey]['pi_list_browseresults_displays'],'%s')) {
		// if the advanced pagebrowser is enabled and the "pi_list_browseresults_displays" label contains %s it will be replaced with the content of the label "pi_list_browseresults_displays_advanced"
			$this->LOCAL_LANG[$this->LLkey]['pi_list_browseresults_displays'] = $this->LOCAL_LANG[$this->LLkey]['pi_list_browseresults_displays_advanced'];
		}

		$this->pi_alwaysPrev = $this->conf['pageBrowser.']['alwaysPrev'];

		// if there is a GETvar in the URL that is not in this list, caching will be disabled for the pagebrowser links
		$this->pi_isOnlyFields = $pointerName.',tx_extendedshop_pi1';

		// pi_lowerThan limits the amount of cached pageversions for the list view. Caching will be disabled if one of the vars in $this->pi_isOnlyFields has a value greater than $this->pi_lowerThan

// 							$this->pi_lowerThan = ceil($this->internal['res_count']/$this->internal['results_at_a_time']);
		$pi_isOnlyFieldsArr = explode(',',$this->pi_isOnlyFields);
		$highestVal = 0;
		foreach ($pi_isOnlyFieldsArr as $k => $v) {
			if ($this->piVars[$v] > $highestVal) {
				$highestVal = $this->piVars[$v];
			}
		}
		$this->pi_lowerThan = $highestVal+1;

		// render pagebrowser
		return $this->pi_list_browseresults($this->conf['pageBrowser.']['showResultCount'], $this->conf['pageBrowser.']['tableParams'],$wrapArr, $pointerName, $this->conf['pageBrowser.']['hscText']);
	}
	
	
 	
}


if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/pi1/class.tx_extendedshop_pi1.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/pi1/class.tx_extendedshop_pi1.php"]);
}
?>