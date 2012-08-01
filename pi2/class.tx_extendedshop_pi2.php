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
require_once(t3lib_extMgm::extPath('extendedshop')."/lib/class.tx_extendedshop_products.php");
require_once(t3lib_extMgm::extPath('extendedshop')."/lib/class.tx_extendedshop_basket.php");


class tx_extendedshop_pi2 extends tslib_pibase {
	var $prefixId = "tx_extendedshop_pi2"; // Same as class name.
	var $scriptRelPath = "pi1/class.tx_extendedshop_pi2.php"; // Path to this script relative to the extension dir.
	var $extKey = "extendedshop"; // The extension key.

	var $cObj = ""; // The backReference to the mother cObj object set at call time
	var $conf = ""; // The extension configuration

	var $total;
	var $basketRef = array();
	var $addQueryLanguage = "";
	
	
	/**
	 * This is an Extended Shop System.
	 */
	function main($content, $conf, $piVars="", $cObj="") {
		$this->conf = $conf;
		if ($piVars!="")	{
			$this->piVars = $piVars;
		}	else	{
			$this->pi_setPiVarDefaults();
		}

		if ($cObj!="")
			$this->cObj = $cObj;

		$this->pi_loadLL();
		
		$this->addQueryLanguage = " AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";

		// Check code from insert plugin (CMD= "singleView")
		if ($this->conf["CMD"] == "MINIBASKET")
			$codes[] = "MINIBASKET";

		// Pid of the basket
		$this->conf["pid_basket"] = trim($this->conf["pid_basket"]);
		
		$this->basketRef = t3lib_div::makeInstance("tx_extendedshop_basket");
		$this->basketRef->init($this);
		
		$clear = $this->piVars['clear'];
		if (isset ($clear)) {
			// Clear the basket
			$this->basketRef->emptyProducts();
			$GLOBALS["TSFE"]->fe_user->setKey("ses", "recs", $this->basketRef->basket);
		}
		
		
		if ($piVars!="")	{
			// It's an xajax call and I have to force session data saving:
			$GLOBALS['TSFE']->storeSessionData();
		}
		
		

		if (!count($codes))
			$codes = array ("MINIBASKET");
		while (list (, $theCode) = each($codes)) {
			$theCode = (string) strtoupper(trim($theCode));
			switch ($theCode) {
				case "MINIBASKET" :
					$content = $this->show_minibasket($piVars!="");
					break;
			}
		}

		$content = $this->manageLabels($content);
		
		if ($piVars != "") return $this->clearInput($content);
		return $this->pi_wrapInBaseClass($this->clearInput($content));
	}



	
	
	function show_minibasket($xajax_mode=false)	{
		$this->pi_USER_INT_obj = true;
		
		$template =	$this->cObj->fileResource($this->conf["templateFile"]);
		
		if ($this->basketRef->getNumberOfProductsInBasket()==0)
			$template = trim($this->cObj->getSubpart($template, "###EMPTY_MINIBASKET_TEMPLATE###"));
		elseif ($xajax_mode)
			$template = trim($this->cObj->getSubpart($template, "###XAJAX_SUBPART###"));
		else
			$template = trim($this->cObj->getSubpart($template, "###MINIBASKET_TEMPLATE###"));
		
		//if ($this->getUserType() == 0){
			$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->getProductsTotal());

			if ($this->conf['hideNoTax']!=1)
				$markerArray["###BASKET_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->calculateTotalProductsNoTax()), $this->conf['price_b.']);
			else
				$markerArray["###BASKET_TOTAL_B###"] = "";
		//} 
		/*else {
			$markerArray["###BASKET_TOTAL###"] = $this->priceFormat($this->basketRef->calculateTotalProductsNoTax());
			if ($this->conf['hideNoTax']!=1)
				$markerArray["###BASKET_TOTAL_B###"] = $this->cObj->stdWrap($this->priceFormat($this->basketRef->getProductsTotal()), $this->conf['price_b.']);
			else
				$markerArray["###BASKET_TOTAL_B###"] = "";
		}*/

		$markerArray["###BASKET_NUMPRODUCTS###"] = $this->basketRef->getNumberOfProductsInBasket();
		
		
		// Warnings management
		$warnings = $this->basketRef->getWarnings();
		if ($warnings==false)	{
			$template = $this->cObj->substituteSubpart($template, '###WARNINGS_TEMPLATE###', '', 0,0);
		}	else	{
			foreach ($warnings as $warn)
				if ($warn['error']=='limit')	{
					$error = $this->pi_getLL('WARNING_LIMIT');
					$error = $this->pi_getLL('WARNING_LIMIT');
					$error = str_replace('###LIMIT###', $warn['limit'], $error);
					$error = str_replace('###PRODUCT###', tx_extendedshop_products::getProductTitle($warn['uid']), $error);
					$markerArray['###WARNINGS_TEXT###'] .= $this->cObj->stdWrap($error, $this->conf['warnings.']);
				}
		}
		
		$markerArray["###FORM_BASKET###"] = $this->pi_getPageLink($GLOBALS['TSFE']->id);		
		if ($this->conf['pid_userinfo']>0)
			$markerArray["###BASKET_URL###"] = $this->pi_getPageLink($this->conf["pid_userinfo"]);
		else
			$markerArray["###BASKET_URL###"] = $this->pi_getPageLink($this->conf["pid_basket"]);
			
		//$markerArray["###BASKET_URL###"] = $this->pi_getPageLink($this->conf["pid_basket"]);
		
		$content = $this->cObj->substituteMarkerArray($template, $markerArray);

		return $this->manageLabels($content);
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
		$content = $this->cObj->substituteMarkerArray($content, $markerArray);

		return $content;
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

	

	
	/*
	 * This funtion determine wich type of user is using the sistem
	 * If the user is type 0 we will show prices with tax (prices no tax)
	 * If the user is type 1 we will show prices no tax (prices with tax)
	 * 
	 * return 0 or 1
	 */
	function getUserType(){
		if ($this->conf['disableVATUserCheck']==1)
			return 0;
		$user = $this->basketRef->basket['personinfo'];
		if ($user['COUNTRY']=='')
			$userinfo = $GLOBALS["TSFE"]->fe_user->user;
		else	{
			$userinfo['country'] = $user['COUNTRY'];
			$userinfo['tx_extendedshop_private'] = $user['PRIVATE'];
		}
		
		if ($userinfo["country"] == "")
			$country = $userinfo["static_info_country"];
		else
			$country = $userinfo["country"];
		
		if ($country=="")
			return 0;
			
		if ($country == 'ITA' && $GLOBALS["TSFE"]->loginUser == "")
			return 0;
		else{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('cn_currency_iso_3', 'static_countries', 'cn_iso_3="'.$country.'"', '', '', '1');
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			if ($row["cn_currency_iso_3"] != 'EUR')
				return 1;
			else{
				if ($userinfo["tx_extendedshop_private"] == 0)
					return 0;
				else
					return 1;
			}		
		}
	}
	
	
	
 	
}


if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/pi2/class.tx_extendedshop_pi2.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/pi2/class.tx_extendedshop_pi2.php"]);
}
?>