<?php

/***************************************************************
*  Copyright notice
*  
*  (c) 2007 Luca Del Puppo for Webformat srl (mauro.lorenzutti@webformat.com)
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
 * 
 */

if (t3lib_extMgm::isLoaded('toi_category'))
	require_once(t3lib_extMgm::extPath('toi_category').'api/class.tx_toicategory_api.php');
class tx_extendedshop_products {

	var $product = array();
	var $conf = array();
	var $parent;
	var $comb = 1;
	var $res;		// Original query, used to manage previous and next
	var $basketArray = array();
	var $tax = 20;
	
	var $productUserInfo = array();
		
	function init($row, $parent, $res){
		$this->productUserInfo = $this->getProductUserInfo($row['uid'],$row,$parent,$parent->basketRef->basket);
		
		// every data arriving from the query is inserted in the array
		$this->product = $row;
		// mantaining the starting query. To menage the previous and next buttons.
		$this->res = $res;
		$this->conf = $parent->conf;
		$this->parent = $parent;
		// for products not in basket
		$comb = 0;
		
		if ($this->res=="")
			$numProducts = 0;
		else
			$numProducts = $GLOBALS['TYPO3_DB']->sql_num_rows($this->res);

		if ($this->conf != "") {
			
			$this->product["vat_percent"] = $this->productUserInfo['vat'];
			$this->product["original_price"] = $this->productUserInfo['original_price'];
			$this->product["original_price_notax"] = $this->productUserInfo['original_price_notax'];
			$this->product["discount"] = $this->productUserInfo['discount'];
			$this->product["showDiscount"] = $this->productUserInfo['showDiscount'];
			$this->product["price"] = $this->productUserInfo['final_price'];
			$this->product["pricenotax"] = $this->productUserInfo['final_price_B'];
			$this->product["offertprice"] = $this->productUserInfo['final_price'];
			$this->product["offertpricenotax"] = $this->productUserInfo['final_price_B'];
			
		}
		//print_r($this->product);
		if($this->conf['debug']){
			t3lib_div::debug($this->product,'extendedshop_products.php : init() : $this->product');
		}
	}
	
	/**
	 * return the price of the product
	 */
	function getPrice(){
		return $this->product["price"];		
	}
	
	
	/**
	 * returns the tax percent
	 */
	function getVatPercent()	{
		return $this->tax/100;
	}
	
	/**
	 * calculate the offer price starting to the discount
	 * @param int $price the price of the product
	 * @param int $discount the percentual discount�
	 * 
	 */
	function calculatePriceOffer($price, $discount){
		$discount = $discount / 100;
		return round(($price*(1-$discount)),2);
	}
	
	/**
	 * Get the price by quantity
	 */
	function getRowPrice($quantity, $price=0){
		if (t3lib_div :: testInt($quantity))	{
			if ($price==0)
				return $this->getPriceOffer() * $quantity;
			else
				return $price * $quantity;
		}
		else
			return 0;
	}
	
	
	/**
	 * calculate the price with discount
	 * @param int $price the price of product
	 * @param int $discount the percentual of the discount
	 * 
	 */
	function calculatePriceDiscount($price, $discount){
		$discount = $discount/100;
		return round(($price*(1-$discount)),2);	
	}
	
	
	/**
	 * Get the price offer if there is an offer. Otherwise get the price
	 */
	function getPriceOffer(){
		if ($this->product["offertprice"] == "" || $this->product["offertprice"] == 0)
			return $this->getPrice();
		else
			return 	$this->product["offertprice"];		
	}
	
	/**
	 * calculate the discount starting from the offer price (PRIVATE)
	 * @param int $price the price of the product
	 * @param int $offertprice the offer-price of the product
	 *  
	 */
	function calculateDiscount($price, $offertprice){
		if ($price != 0 && $price != ""){
			return round( ((1 - $offertprice/$price)*100) , 0 );
		}
	}
	
	/**
	 * get disount of the product if present
	 */
	function getDiscount(){
		if ($this->product["discount"] == "")
			return 0;
			else
				return 	$this->product["discount"];
	}
	
	/**
	 * get discount with no tax if present
	 */
	function getDiscountNoTax(){
		if ($this->product["discountNoTax"] == "")
			return 0;
			else
				return 	$this->product["discountNoTax"];
	}
	
	/**
	 * calculate the price without tax
	 * @param int $price the price of product
	 * @param int $tax the percentual of the tax
	 * 
	 */
	function calculatePriceNoTax($price, $tax=''){
		if ($tax!='')
			$tax = $tax/100;
		else
			$tax = $this->tax/100;
		return $priceNoTax = round(($price/(1+$tax)),2);	
	}
	
	/**
	 * calculate the price with tax starting from price without tax
	 * @param int $price the price of product without tax
	 * @param int $tax the percentual of the tax
	 * 
	 */
	function calculatePriceWithTax($price, $tax=''){
		if ($tax!='')
			$tax = $tax/100;
		else
			$tax = $this->tax/100;
		return round(($price*(1+$tax)),2);	
	}

	function getPriceTax(){
		return $this->product["price"];
	}	
	/**
	 * get price with no tax
	 */
	function getPriceNoTax(){
		return $this->product["pricenotax"];	
	}
	
	
	function getUserDiscount(){
		
	}
	
	/**
	 * get the title of the page
	 */
	function getPageTitle(){
		if ($this->product["pagetitle"] != "")
				return $this->product["pagetitle"];
			else
				return $this->product["title"];					
	}
	
	
	/**
	 * This function returns the total weight of the order row
	 * @param int 	$quantity 	The quantity of this product in the basket (or in the order)
	 * @return int The total weight
	 */
	function getRowWeight($quantity)	{
		if (t3lib_div :: testInt($quantity))
			return $this->getWeight() * $quantity;
		else
			return 0;
	}
	
	/**
	 * This function returns the weight of this product
	 * @return int The product weight
	 */
	function getWeight()	{
		return $this->product['weight'];
	}
	
	
	
	/**
	 * This function returns the total volume of the order row
	 * @param int 	$quantity 	The quantity of this product in the basket (or in the order)
	 * @return int The total volume
	 */
	function getRowVolume($quantity)	{
		if (t3lib_div :: testInt($quantity))
			return $this->getVolume() * $quantity;
		else
			return 0;
	}
	
	/**
	 * This function returns the weight of this product
	 * @return int The product weight
	 */
	function getVolume()	{
		return $this->product['volume'];
	}
	
	
	/**
	 * This function returns the code of this product
	 * @return int The product code
	 */
	function getCode()	{
		return $this->product['code'];
	}
	

	function getAvailable(){
		
	}
	
	function setAvailable(){
		
	}
	
	function getThumbType(){
		return $this->product["thumbtype"];
	}
	
	function getGroupDiscount($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_extendedshop_discount', 'fe_groups', 'uid='.(int)$key.'', '', '', '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row["tx_extendedshop_discount"];
	}
	
	function getRelated(){
		
	}
	
	function getInStock($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid='.$key.'', '', '', '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row["instock"];		
	}
	
	/**
	 * @param string 	$imgRender			type of rendering image according to the destination page (image/list/offer)
	 * @param boolean 	$linkTitle 			define if the title of the product must have or not the link to the detail page
	 * @param string	$html 				template
	 * @param int 		$basket_item 		define the position of row_basket
	 * @param string 	$disabledSelect		Defines if the selector boxes are enabled or not (possible values: "" || " disabled")
	 */
	function getTemplateProduct($imgRender = "image", $linkTitle = false, $template, $basket_item = "", $disabledSelect="", $xajax_call=false) {
		
		$markerArray = array();
		
		if ($this->conf['insertProduct_nextPage']==0)
			$markerArray["###FORM_ADD###"] = $this->parent->pi_getPageLink($this->conf['pid_basket']);
		else
			$markerArray["###FORM_ADD###"] = $this->parent->pi_linkTP_keepPIvars_url();
			
		if ($this->conf['xajax_cart_update']==1 && t3lib_extMgm::isLoaded('xajax'))	{
			$markerArray['###XAJAX_CART_UPDATE###'] = 'onsubmit="document.getElementById(\''.$this->conf['minibasket_id'].'\').value=\'\'; ' . $this->parent->prefixId . 'minibasketUpdate(xajax.getFormValues(\'tx_extendedshop_pi1_basket_'.$this->product['uid'].'\')); return false;"';
			$markerArray['###LINK_SUBMIT###'] = 'document.getElementById(\''.$this->conf['minibasket_id'].'\').value=\'\'; ' . $this->parent->prefixId . 'minibasketUpdate(xajax.getFormValues(\'tx_extendedshop_pi1_basket_'.$this->product['uid'].'\')); return false;';
		}	else	{
			$markerArray['###XAJAX_CART_UPDATE###'] = '';
			$markerArray['###LINK_SUBMIT###'] = 'javascript:document.tx_extendedshop_pi1_basket'.$this->product['uid'].'.submit(); return false;';
		}
		
		if ($xajax_call)	{
			$markerArray['###XAJAX_DISPLAY###'] = 'style="display: none;"';
		}	else	{
			$markerArray['###XAJAX_DISPLAY###'] = '';
		}
		
		if ($disabledSelect!="")
			$disabledSelect = ' disabled="'.$disabledSelect.'"';

		// menaging the disabilitation of select tag
		if (($GLOBALS["TSFE"]->id == $this->parent->config["pid_basket"]) || ($GLOBALS["TSFE"]->id == $this->parent->conf["pid_finalize"]) || ($GLOBALS["TSFE"]->id == $this->parent->conf["pid_payment"])){
			$inBasket = true;
		}
		
		// insert in an array the basket configuration
		$this->basketArray = $this->parent->basketRef->getBasketProducts();		
		
		if (t3lib_div :: testInt($basket_item)){
			$markerArray["###BASKET_UID###"] = $basket_item;
			if ($this->basketArray[$basket_item]["page"] == "")
				$markerArray["###PRODUCT_PAGE###"] = $GLOBALS["TSFE"]->id;
			else
				$markerArray["###PRODUCT_PAGE###"] = $this->basketArray[$basket_item]["page"];
		} else {
			if ($this->conf["pid_productPage"] != "" && $this->conf["pid_productPage"] != 0){
				$markerArray["###PRODUCT_PAGE###"] = $this->conf["pid_productPage"];
			}	else	{
				$markerArray["###PRODUCT_PAGE###"] = $GLOBALS["TSFE"]->id;
			}
		}
		$markerArray["###PRODUCT_TIMESTAMP###"] = time();
		// $markerArray creation in a foreach loop
		foreach ($this->product as $key => $value){
			if ($key != "image")
				$markerArray["###PRODUCT_".strtoupper($key)."###"] = $value;
		}
		
		// Correlated page management
		if ($this->product["correlatedpage"] != ""){
			$markerArray["###LABEL_PAGE_LIST###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_PAGE_LIST"));
			$pages = explode(",", $this->product["correlatedpage"]);
			$markerPage = "";
			while (list ($key, $value) = each($pages)) {
				$markerPage .= $this->parent->pi_linkToPage($this->getTitlePage($value), $value).'<br />';
			}
			$markerArray["###LINK_PAGE_LIST###"] = $markerPage;
		} else {
			$markerArray["###LABEL_PAGE_LIST###"] = "";
			$markerArray["###LINK_PAGE_LIST###"] = "";
		}
		
		// Document list management
		if ($this->product["documents"] != ""){
			$labels = array();
			if ($this->product['doc_labels']!="")	{
				$labels = explode(chr(10),$this->product['doc_labels']);
			}

			$markerArray["###LABEL_DOC_LIST###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_DOC_LIST"));
			$pages = explode(",", $this->product["documents"]);
			$markerPage = "";
			$k = 1;
			while (list ($key, $value) = each($pages)) {		
				$label = $labels[$k-1]!='' ? $labels[$k-1] : $value;
				$markerPage .= '<a href="uploads/tx_extendedshop/'.$value.'">'.$label.'</a><br />';
				$k++;
			}
			$markerArray["###LINK_DOC_LIST###"] = $markerPage;
		} else {
			$markerArray["###LABEL_DOC_LIST###"] = "";
			$markerArray["###LINK_DOC_LIST###"] = "";
		}
		
		// Supplier management
		if ($this->product["supplier"] != ""){
			$markerArray["###LABEL_SUPPLIER###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_SUPPLIER"));
			$nameSupplier = $this->parent->getNameUser($this->product["supplier"]);
			
			$params['parameter'] = $this->conf["pid_supplierPage"];
			$params['additionalParams'] = "&".$this->parent->prefixId."[supplierID]=".$this->product['supplier'];
			$params['useCacheHash'] = true;
			$markerArray["###VALUE_SUPPLIER###"] =  $this->parent->cObj->typoLink($nameSupplier, $params);
			unset($params);
			//$markerArray["###VALUE_SUPPLIER###"] = $nameSupplier;
			if ($nameSupplier == ""){
				$markerArray["###LABEL_SUPPLIER###"] = "";
			}
		} else {
			$markerArray["###LABEL_SUPPLIER###"] = "";
			$markerArray["###VALUE_SUPPLIER###"] = "";
		}
		
		// Inserting of image's data in marker array (calling an external function)
		$markerArray = $this->getTemplateImage($imgRender, $markerArray, $template, $this->res);
		
		if ($linkTitle) {
			$params['parameter'] = $markerArray["###PRODUCT_PAGE###"];
			$params['additionalParams'] = "&".$this->parent->prefixId."[productID]=".$this->product['uid']."&".$this->parent->prefixId."[pid_product]=".$this->product['pid'];
			$params['useCacheHash'] = true;
			
			if ($this->conf['xajax_preview']==1 && $this->conf['xajax_preview.']['linkTitle']==1 && t3lib_extMgm::isLoaded('xajax'))
				$params['ATagParams'] = 'onclick="' . $this->parent->prefixId . 'productPreview('.$this->product['uid'].'); return false;"';
			
			$markerArray["###PRODUCT_TITLE###"] =  $this->parent->cObj->typoLink($this->product["title"], $params);			
			unset($params);
		} else {
			$markerArray["###PRODUCT_TITLE###"] = $this->product["title"];
		}
		

		if ($this->conf['xajax_preview']==1 && t3lib_extMgm::isLoaded('xajax'))	{
			$markerArray['###XAJAX_PREVIEW###'] = $this->parent->prefixId . 'productPreview('.$this->product['uid'].'); return false;';
			$markerArray['###XAJAX_CLOSE###'] = '<a href="#" onclick="' . $this->parent->prefixId . 'productClosePreview('.$this->product['uid'].'); return false;">'.$this->parent->pi_getLL('closePreview', 'chiudi preview').'</a>';
		}	else	{
			$markerArray['###XAJAX_PREVIEW###'] = '';
			$markerArray['###XAJAX_CLOSE###'] = '';
		}
		
		
		
		// changement of some particular $markerarray
		$markerArray["###PRODUCT_DESCRIPTION###"] = $this->parent->formatStr($this->parent->cObj->stdWrap($this->product['description'], $this->conf['content_stdWrap.']));
		
		if ($this->product == "") {
			$template = $this->parent->cObj->substituteSubpart($template, "###PRICE###", "", $recursive = 0, $keepMarker = 0);
			$template = $this->cObj->substituteSubpart($template, "###PRICEDISCOUNT###", "", $recursive = 0, $keepMarker = 0);
		}
		
		// managing product prices for type of user
		if ($this->product["discount"] > 0 && $this->product["showDiscount"]) {

			$markerArray["###PRODUCT_PRICE###"] = $this->parent->priceFormat($this->product["original_price"]);
			if($this->conf['hideNoTax']){
				$markerArray["###PRODUCT_PRICE_B###"] = "";
				$markerArray["###PRODUCT_OFFERTPRICE_B###"] = "";
			}
			else{
				$markerArray["###PRODUCT_PRICE_B###"] = $this->parent->cObj->stdWrap($this->parent->priceFormat($this->product["original_price_notax"]), $this->parent->conf['price_b.']);
				$markerArray["###PRODUCT_OFFERTPRICE_B###"] = $this->parent->cObj->stdWrap($this->parent->priceFormat($this->product["pricenotax"]), $this->parent->conf['price_b.']);
			}
				
			$markerArray["###PRODUCT_DISCOUNT###"] = $this->product["discount"];
			$markerArray["###PRODUCT_OFFERTPRICE###"] = $this->parent->priceFormat($this->product["price"]);
			$markerArray["###PRODUCT_OFFERTPRICENOTAX###"] = $this->product["pricenotax"];
			$markerArray["###PRODUCT_SELLPRICE###"] = $this->product["price"];
			$markerArray["###PRODUCT_SELLPRICE_NOTAX###"] = $this->product["pricenotax"];
				
			$template = $this->parent->cObj->substituteSubpart($template, "###PRICE###", "", $recursive = 0, $keepMarker = 0);

		}
		else { //if offertprice is not set
			$markerArray["###PRODUCT_PRICE###"] = $this->parent->priceFormat($this->product["price"]);
			if ($this->conf['hideNoTax']){
				$markerArray["###PRODUCT_PRICE_B###"] = "";
			}
			else{
				$markerArray["###PRODUCT_PRICE_B###"] = $this->parent->cObj->stdWrap($this->parent->priceFormat($this->product["pricenotax"]), $this->parent->conf['price_b.']);
			}
			$markerArray["###PRODUCT_PRICENOTAX###"] = $this->parent->priceFormat($this->product["pricenotax"]);
			$markerArray["###PRODUCT_DISCOUNT###"] = $this->product["discount"];
			$markerArray["###PRODUCT_SELLPRICE###"] = $this->product["price"];
			$markerArray["###PRODUCT_SELLPRICE_NOTAX###"] = $this->product["pricenotax"];
			
			$template = $this->parent->cObj->substituteSubpart($template, "###PRICEDISCOUNT###", "", $recursive = 0, $keepMarker = 0);
		}
		
		if($this->conf['debug']){
			t3lib_div::debug($markerArray,'class.tx_extendedshop_products.php : getTemplateProduct() : $markerArray');
		}	

		// take the number of combination in the URL
		$iCombinations = $this->parent->piVars['numCombination'];
		$idUrl = $this->parent->piVars['numCombination_ID'];
		if ($iCombinations == 0)
			$iCombinations = 1;	

		// Select for size and color
		// add the case if i'm in the basket or not		
		if (!$inBasket){
			if ($iCombinations > 1){
				if ($idUrl == $this->product["uid"]){
						$this->comb = $iCombinations;
						$max = $this->comb;
					} else {
						$max = $this->comb;
					}
				} else {
					if ($this->product["sizes"] == "" && $this->product["colors"] == ""){
						$max = 0;
					}else
						$max = 1;
				}
		} else {
			// in Basket	
			if ($this->basketArray[$basket_item]["combinations"] > 0  && $basket_item != $idUrl){
				$max = $this->basketArray[$basket_item]["combinations"];
			} else {
				if ($idUrl == $basket_item){
					//t3lib_div::debug($this->basketArray[$idUrl]["combinations"] = $iCombinations);
					$this->basketArray[$idUrl]["combinations"] = $iCombinations;
					$max = $iCombinations;
				} else {
				if ($this->product["sizes"] == "" && $this->product["colors"] == "")
					$max = 0;
				else
					$max = 1;
				} 
			}
		}
			

		//t3lib_div::debug($max);
		$markerArray["###PRODUCT_COMBINATIONS###"] = $max;
		
		if ($max > 0) {
			// size and color are not empty
			// mantain a state of piVars
			//$productIDOld = $this->parent->piVars['productID'];
			$numCombinationOld = $this->parent->piVars['numCombination'];
			$numCombination_IDOld = $this->parent->piVars['numCombination_ID'];	
			//$this->parent->piVars['productID'] = $this->product["uid"];
			$succ = $max +1;
			$prec = $max -1;
			
			if ($inBasket){
				$oldPiVars = $this->parent->piVars;
				unset($this->parent->piVars);
				$this->parent->piVars['numCombination'] = $succ;
				$this->parent->piVars['numCombination_ID'] = $basket_item;
				$markerArray["###ADD_COMBINATION###"] = $this->parent->pi_linkTP_keepPIvars_url();		
				$this->parent->piVars['numCombination'] = $prec;
				$markerArray["###CLEAR_COMBINATION###"] = $this->parent->pi_linkTP_keepPIvars_url();
				unset($this->parent->piVars);
				$this->parent->piVars = $oldPiVars;	
				//t3lib_div::debug($this->basketArray);
				//$markerArray["###PRODUCT_ADDCOMBINATIONS###"] = $max;
			} else {
				$this->parent->piVars['numCombination'] = $succ;
				$this->parent->piVars['numCombination_ID'] = $this->product["uid"];
				$markerArray["###ADD_COMBINATION###"] = $this->parent->pi_linkTP_keepPIvars_url();		
				$this->parent->piVars['numCombination'] = $prec;
				$markerArray["###CLEAR_COMBINATION###"] = $this->parent->pi_linkTP_keepPIvars_url();		
				$markerArray["###PRODUCT_ADDCOMBINATIONS###"] = $max;
			}
			
			// reload the last piVars state
			//$this->parent->piVars['productID'] = $productIDOld;
			$this->parent->piVars['numCombination'] = $numCombinationOld;
			$this->parent->piVars['numCombination_ID'] = $numCombination_IDOld;
			
		} else {
			// size and color are empty
			$markerArray["###ADD_COMBINATION###"] = "";
			$markerArray["###CLEAR_COMBINATION###"] = "";
			$markerArray["###PRODUCT_ADDCOMBINATIONS###"] = 1;
		}
		
		$subTemplate = trim($this->parent->cObj->getSubpart($template, "###COMBINATIONS###"));
		$subContent = "";
		//t3lib_div::debug($max);
		for ($i = 1; $i <= $max; $i++) {
			//size management
			if ($this->product["sizes"] != "") {

				if ($inBasket){ //t3lib_div :: testInt($basket_item)){
					$markerArray["###FIELD_SIZE_NAME###"] = "tx_extendedshop_pi1[basket_product][" . $basket_item . "][sizes][" . $i . "]";
					$markerArray["###FIELD_SIZE_VALUE###"] = $this->basketArray[$basket_item]["sizes"][$i] ? $this->basketArray[$basket_item]["sizes"][$i] : "";
				} else {
					$markerArray["###FIELD_SIZE_NAME###"] = "tx_extendedshop_pi1[product][" . $this->product["uid"] . "][sizes][" . $i . "]";
				}
				$prodSizeText = '';
				$sizesList = $this->product["sizes"];
				if (strrpos($sizesList, "!") == (strlen($sizesList) - 1))
					$colorsList = substr($sizesList, 0, -1);
				$prodSizeTmp = explode('!', $sizesList);
				foreach ($prodSizeTmp as $prodSize) {
					if ($inBasket && $prodSize == $markerArray["###FIELD_SIZE_VALUE###"]) {
						$prodSizeText = $prodSizeText . '<OPTION value="' . $prodSize . '" selected>' . $prodSize . '</OPTION>';
						$markerArray["###PRODUCT_SIZES_LABEL###"] = $prodSize;
						//$markerArray["###PRODUCT_COMBINATIONS###"] = $max;
					} else {
						if ($prodSize != ""){
							$prodSizeText = $prodSizeText . '<OPTION value="' . $prodSize . '">' . $prodSize . '</OPTION>';
							//$markerArray["###PRODUCT_COMBINATIONS###"] = 1;
						}
					}
				}
				$markerArray["###PRODUCT_SIZES###"] = '<SELECT' . $disabledSelect . ' name=' . $markerArray["###FIELD_SIZE_NAME###"] . ' rows="1">' . $prodSizeText . '</SELECT>';
			} else {
				$markerArray["###PRODUCT_SIZES###"] = "";
			}

			//colors management
			if ($this->product["colors"] != "") {
				//t3lib_div::debug($this->product);
				if ($inBasket){ //t3lib_div :: testInt($basket_item)){
					$markerArray["###FIELD_COLOR_NAME###"] = "tx_extendedshop_pi1[basket_product][" . $basket_item . "][colors][" . $i . "]";
					$markerArray["###FIELD_COLOR_VALUE###"] = $this->basketArray[$basket_item]["colors"][$i] ? $this->basketArray[$basket_item]["colors"][$i] : "";
				} else {
					$markerArray["###FIELD_COLOR_NAME###"] = "tx_extendedshop_pi1[product][" . $this->product["uid"] . "][colors][" . $i . "]";	
				}
				
				$prodSizeText = '';
				$colorsList = $this->product["colors"];
				if (strrpos($colorsList, "!") == (strlen($colorsList) - 1))
					$colorsList = substr($colorsList, 0, -1);
				$prodSizeTmp = explode('!', $colorsList);
				foreach ($prodSizeTmp as $prodSize) {
					if ($inBasket && $prodSize == $markerArray["###FIELD_COLOR_VALUE###"]) {
						$prodSizeText = $prodSizeText . '<OPTION value="' . $prodSize . '" selected>' . $prodSize . '</OPTION>';
						$markerArray["###PRODUCT_COLORS_LABEL###"] = $prodSize;
						//$markerArray["###PRODUCT_COMBINATIONS###"] = $max;
					} else {
						if ($prodSize != ""){
							$prodSizeText = $prodSizeText . '<OPTION value="' . $prodSize . '">' . $prodSize . '</OPTION>';
							//$markerArray["###PRODUCT_COMBINATIONS###"] = 1;
						}
					}
				}
				$markerArray["###PRODUCT_COLORS###"] = '<SELECT' . $disabledSelect . ' name=' . $markerArray["###FIELD_COLOR_NAME###"] . ' rows="1">' . $prodSizeText . '</SELECT>';
			} else {
				$markerArray["###PRODUCT_COLORS###"] = "";
			}

			$subContent .= $this->parent->cObj->substituteMarkerArray($subTemplate, $markerArray);
			if ($i < $max)
				$subContent .= "<br />";
		}

		$template = $this->parent->cObj->substituteSubpart($template, "###COMBINATIONS###", $subContent, $recursive = 0, $keepMarker = 0);
		$subTemplate2 = trim($this->parent->cObj->getSubpart($template, "###LINKCOMBINATIONS###"));
		if ($max > 0) {
			$subContent2 = $this->parent->cObj->substituteMarkerArray($subTemplate2, $markerArray);
			$template = $this->parent->cObj->substituteSubpart($template, "###LINKCOMBINATIONS###", $subContent2, $recursive = 0, $keepMarker = 0);
		} else {
			$template = $this->parent->cObj->substituteSubpart($template, "###LINKCOMBINATIONS###", "", $recursive = 0, $keepMarker = 0);
		}

		// Managing correlated products
		if ($this->product["correlatedproducts"] != "") {
			$subTemplateCorr = trim($this->parent->cObj->getSubpart($template, "###CORRELATED_ROW###"));
			$resCP = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid IN (' . $this->product["correlatedproducts"] . ')');
			while ($rowCP = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resCP)) {
				foreach ($rowCP as $corrKey => $corrVal)
					$markerArrayCorr['###PRODUCT_CORRELATEDPRODUCTS_'.strtoupper($corrKey).'###'] = $corrVal;
				
				$imageNumCP = 0;
				$imgsCP = explode(",", $rowCP["image"]);
				$valCP = $imgsCP[$imageNumCP];
				while (list ($cCP, $valCP) = each($imgsCP)) {
					//if ($c==$imageNum)	break;
					if ($valCP) {
						$this->conf["correlatedImage."]["file"] = "uploads/tx_extendedshop/" . $valCP;
					} else {
						$this->conf["correlatedImage."]["file"] = $this->conf["noImageAvailable"];
					}
					$this->conf["correlatedImage."]["altText"] = $rowCP['title'];
					
					unset($params);
					if ($this->conf["pid_productPage"] != "" && $this->conf["pid_productPage"] != 0){
						$params['parameter'] = $this->conf["pid_productPage"];
					} else {
						$params['parameter'] = $rowCP["pid"];
					}
					$params['additionalParams'] = "&".$this->parent->prefixId."[productID]=".$rowCP['uid']."&".$this->parent->prefixId."[pid_product]=".$rowCP['pid'];
					$params['useCacheHash'] = true;
					$link1 = $this->parent->cObj->typoLink($rowCP["title"], $params);
					//$link2 = $this->parent->cObj->typoLink($this->parent->cObj->IMAGE($this->conf["correlatedImage."]), $this->conf["pid_productPage"]);
					$link2 = $this->parent->cObj->IMAGE($this->conf["correlatedImage."]);
					unset($params);
				
				}
				
				$markerArrayCorr["###PRODUCT_CORRELATEDPRODUCTS_TITLE###"] = $link1;
				$markerArrayCorr["###PRODUCT_CORRELATEDPRODUCTS###"] = $link2;
				
				// Hook that can be used to manage custom fields
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_product_corr_fields']))    {
				    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_product_corr_fields'] as $_classRef)    {
				        $_procObj = &t3lib_div::getUserObj($_classRef);
				        $markerArrayCorr = $_procObj->evaluateCustomCorrelatedFields($this->parent, $markerArrayCorr, $subTemplateCorr, $rowCP, $this->conf);
				    }
				}
				
				
				$subContentCorr .= $this->parent->cObj->substituteMarkerArray($subTemplateCorr, $markerArrayCorr);
				
				$imageNumCP++;
			}
			$template = $this->parent->cObj->substituteSubpart($template, "###CORRELATED_ROW###", $subContentCorr, $recursive = 0, $keepMarker = 0);
			
			$markerArray["###LABEL_CORRELATEDPRODUCTS###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_CORRELATEDPRODUCTS"));

			$subTemplate2 = trim($this->parent->cObj->getSubpart($template, "###CORRPRODUCTS###"));
			$subContent2 = $this->parent->cObj->substituteMarkerArray($subTemplate2, $markerArray);
			$template = $this->parent->cObj->substituteSubpart($template, "###CORRPRODUCTS###", $subContent2, $recursive = 0, $keepMarker = 0);
		
		} else {
			$markerArray["###PRODUCT_CORRELATEDPRODUCTS###"] = "";
			$markerArray["###LABEL_CORRELATEDPRODUCTS###"] = "";
			$template = $this->parent->cObj->substituteSubpart($template, "###CORRPRODUCTS###", "", $recursive = 0, $keepMarker = 0);
		}
		
		if (is_array($this->basketArray[$basket_item]))
			$markerArray["###PRODUCT_QUANTITY###"] = $this->basketArray[$basket_item]["num"];
		else
			$markerArray["###PRODUCT_QUANTITY###"] = "";
			
			
		if ($this->conf['quantity_input']==0)	{
			// SELECT
			if ($inBasket && t3lib_div :: testInt($basket_item))
				$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] = "<select id='quantity".$basket_item."' name='tx_extendedshop_pi1[basket_product][". $basket_item. "][num]'>";
			else
				$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] = "<select id='quantity".$this->product['uid']."' name='tx_extendedshop_pi1[product][" . $this->product['uid'] . "][num]'>";
	
			$iCombinations = $markerArray["###PRODUCT_COMBINATIONS###"];
			$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] .= "<option value='0'>0</option>";
			if ($iCombinations == 0)
				$iCombinations = 1;
			
			if ($this->product["max_for_order"] == 0)
				$max = 10*$iCombinations;
			else
				$max=$this->product["max_for_order"];
			
			
			for ($i = $iCombinations; $i<=$max; $i = $i+$iCombinations){
				if (t3lib_div :: testInt($basket_item)){
					if ($inBasket && $i == $this->basketArray[$basket_item]["num"])	{	// || ($i == $iCombinations && $this->basketArray[$basket_item]["num"] <= 0))
						$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] .= '<option value="' . $i . '" selected>' . $i . '</option>';
					}	else 
						if ($i == $iCombinations)
							$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] .= '<option value="' . $i . '" selected>' . $i . '</option>';
						else
							$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] .= '<OPTION value="' . $i . '">' . $i . '</option>'; 
				} else {
					if ($i == $iCombinations)
						$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] .= "<option value='" . $i . "' selected>" . $i . "</option>";
					else
						$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] .= '<OPTION value="' . $i . '">' . $i . '</option>';
				}
			}
			$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] .= "</select>";
		}	else	{
			// SIMPLE INPUT
			if ($inBasket && t3lib_div :: testInt($basket_item))
				$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] = "<input id='quantity".$basket_item."' size='3' type='text' name='tx_extendedshop_pi1[basket_product][". $basket_item. "][num]' value='".$this->basketArray[$basket_item]['num']."'>";
			else
				$markerArray["###PRODUCT_QUANTITY_SELECTOR###"] = "<input id='quantity".$this->product['uid']."' size='3' type='text' name='tx_extendedshop_pi1[product][" . $this->product['uid'] . "][num]' value=''>";
		}
		
		// Hook that can be used to manage custom fields
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_product_fields']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['custom_product_fields'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $markerArray = $_procObj->evaluateCustomProductFields($this->parent, $markerArray, $template, $this->product, $basket_item, $inBasket, $this->conf);
		    }
		}
		
		//t3lib_div::debug($this->basketArray,'BASKET ARRAY:');

		//return $markerArray;
		return $this->parent->cObj->substituteMarkerArray($template, $markerArray);
	}
	
	function getTemplateImage($imgRender = "image", $markerArray, &$template, $resAI){
		$imgRenderOld = $imgRender;
		if (($this->parent->piVars['productID'] != "" && $this->parent->conf['listModeDoesntIncludeSingleMode']!=1) || $this->conf["CMD"] == "singleView"){
			$detail = true;
		}
		if ($this->product["tx_toicategory_toi_category"] == "" || !t3lib_extMgm::isLoaded('toi_category')){
			$template = $this->parent->cObj->substituteSubpart($template, "###CATEGORY_VISUAL###", "", $recursive = 0, $keepMarker = 0);
		} else {
			$categoryApi = t3lib_div::makeInstance('tx_toicategory_api');
			$markerArray["###CATEGORY_TITLE###"] = "";
			$categories = explode(",", $this->product["tx_toicategory_toi_category"]);
			$i = 0;
			while (list ($key, $val) = each($categories)) {	
				if ($i != 0)
					$markerArray["###CATEGORY_TITLE###"] .= ", ";
				$params[$this->parent->prefixId]['categoryID'] = $val;
				
				$params['parameter'] = $this->conf["pid_categoryPage"];
				$params['additionalParams'] = "&".$this->parent->prefixId."[categoryID]=".$val;
				$params['useCacheHash'] = true;				
				$markerArray["###CATEGORY_TITLE###"] .=  $this->parent->cObj->typoLink($categoryApi->showCategorysFromList($val,$showMetainfoOutput=0,$recursiveToToplevel=0), $params);
				unset($params);
				$i++;
			}
		}
		
		// Get image
		//t3lib_div::debug($this->conf);

		$imageNum = 0;
		$theImgCode = array();
		$imgs = explode(",", $this->product["image"]);
		$thumbtype = $this->getThumbType();
		if ($thumbtype == ""){
			$notEnd = true;
			$i = 0;
			while ($notEnd){
				if ($i == 0){
					$imgRender = $imgRenderOld.".";
				} else {
					$imgRender = $imgRenderOld.$i.".";
				}
				if (is_array($this->conf[$imgRender])){
					if ($i != 0){
						$thumbtype .= "image".$i."!";
					} else {
						$thumbtype .= "image!";
					}
				} else {
					$notEnd = false;
				}
				$i++;
			}
		}
		
		$typeImg = explode("!", $thumbtype);	
		$i = 0;
		
		if (is_array($typeImg)){
			$precImg = $typeImg[0];
		} else {
			$precImg = "";
		}
		
		//$imgRenderOld = $imgRender;
		$count = 1;
		while (list ($c, $val) = each($imgs)) { // NON SI CAPISCE NIENTE [DA RIFARE]
			//t3lib_div::debug($imgs);
			if (($typeImg[0] != "")){
				// only in this case
				if ($typeImg[$i] != ""){
					$precImg = $typeImg[$i];
					$key = explode("image", $typeImg[$i]);
					$index = ($count == 1)?'':$count;
					$imgRender = $imgRenderOld.$index;
					if ($key[1] == "")
						$key[1] = 0;
				} else {
					$typeImg[$i] = $precImg;
					$key = explode("image", $typeImg[$i]);
					$index = ($count == 1)?'':$count;
					$imgRender = $imgRenderOld.$index;
					if ($key[1] == "")
						$key[1] = 0;
				}	
			} else {
				$key[1] = 0;
			}

			if ($val) {
				$this->conf[$imgRender . "."]["file"] = "uploads/tx_extendedshop/" . $val;
				
				if($count == 1){
					if($this->conf[$imgRender . "."]["show"] == '0'){
						$this->conf[$imgRender . "."]["file"] = "";
						$this->conf[$imgRender . "."]["show"] = 0;
					}
				}
				else{
					if(isset($this->conf[$imgRender . "."]["show"])){
						if($this->conf[$imgRender . "."]["show"] == '0'){
							$this->conf[$imgRender . "."]["file"] = "";
							$this->conf[$imgRender . "."]["show"] = 0;
						}
					}
					else{
						if($this->conf[$imgRenderOld.($count-1) . "."]["show"] == '0'){
							$this->conf[$imgRender . "."]["file"] = "";
							$this->conf[$imgRender . "."]["show"] = 0;
						}
					}
				}
				
			} else {
				$this->conf[$imgRender . "."]["file"] = $this->conf["noImageAvailable"];
			}
			$this->conf[$imgRender . "."]["altText"] = '"' . $this->product["title"] . '"';
			
			if($count == 1){
				$TSimageConf = $imgRenderOld;
			}
			else{
				$TSimageConf = $imgRenderOld.$count;
			}
			$back = $count;
			while(($back > 0) && (!isset($this->conf[$TSimageConf."."]["file."]))){
				$back--;
				if($back == 1)
					$TSimageConf = $imgRenderOld;
				else
					$TSimageConf = $imgRenderOld.$back;
			}
			if(isset($this->conf[$TSimageConf."."]["file."]))
				$this->conf[$imgRender . "."]["file."] = $this->conf[$TSimageConf."."]["file."];
			
			if ($this->conf['debug'])
				t3lib_div::debug($this->conf[$imgRender."."],'$this->conf['.$imgRender.'.]');
			
				
			if ($imgRenderOld == "listImage") {
				if ($this->conf[$imgRender . "."]['link_to_details']==1)	{
					// metto la pi_getPageLink che passa alla pagina
					$params['parameter'] = $markerArray["###PRODUCT_PAGE###"];
					$params['additionalParams'] = "&".$this->parent->prefixId."[productID]=".$this->product['uid']."&".$this->parent->prefixId."[pid_product]=".$this->product['pid'];
					$params['useCacheHash'] = true;
					
					if ($this->conf['xajax_preview']==1 && $this->conf['xajax_preview.']['linkTitle']==1 && t3lib_extMgm::isLoaded('xajax'))
						$params['ATagParams'] = 'onclick="' . $this->parent->prefixId . 'productPreview('.$this->product['uid'].'); return false;"';
					
					$theImgCode[$key[1]] .= $this->parent->cObj->typoLink($this->parent->cObj->IMAGE($this->conf[$imgRender . "."]), $params);
					unset($params);
				}	else	{
					$theImgCode[$key[1]] .= $this->parent->cObj->IMAGE($this->conf[$imgRender . "."]);
				}
				//break;

			} else {
				$theImgCode[$key[1]] .= $this->parent->cObj->IMAGE($this->conf[$imgRender . "."]);
				$imgZoom = $this->conf['zoomimage.'];
				$img1 = $this->conf['image1.'];
				$confZoom['bodyTag'] = '<body bgColor=white leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">';
				$confZoom['wrap'] = '<a href="javascript: close();"> | </a>';
				$confZoom['width'] = '400';
				$confZoom['JSwindow'] = '1';
				$confZoom['JSwindow.newWindow'] = '1';
				$confZoom['JSwindow.expand'] = '17,20';
				$confZoom['enable'] = '1';
				$markerArray["###PRODUCT_ZOOM###"] = $this->parent->cObj->imageLinkWrap($this->parent->cObj->IMAGE($imgZoom), "uploads/tx_extendedshop/" . $val, $confZoom);
			}
			//t3lib_div::debug($imgs);
			$count++;
			$i++;
		}
		// Management "preview" and "next"
		if ($detail) {
			// add a new queryLanguage because ther's not a detail image in BE
			// language management for the detail image page. At preview and next image thumbtail are charged only the correct language images.
			$addQueryLanguage = "AND tx_extendedshop_products.sys_language_uid = ".$GLOBALS['TSFE']->sys_language_uid." ";			

			$trovatoAI = false;
			$iAI = 0;
			$iTrovato = -1;
			if ($resAI == "")
				$numAI = 0; 
			else
				$numAI = $GLOBALS['TYPO3_DB']->sql_num_rows($resAI);
				
			if ($numAI > 1 && $this->conf["CMD"] != "singleView") {
				while ($rowAI = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resAI)) {
					if ($rowAI["uid"] == $this->product["uid"]) {
						// Sono arrivato al centro
						$trovatoAI = true;
						$iTrovato = $iAI;
					} else {
						if (!$trovatoAI || ($iAI == $numAI -1 && $iTrovato == 0)) {
							$params['parameter'] = $GLOBALS['TSFE']->id;
							$params['additionalParams'] = "&".$this->parent->prefixId."[productID]=".$rowAI['uid']."&".$this->parent->prefixId."[pid_product]=".$rowAI['pid'];
							$params['useCacheHash'] = true;
							$markerArray["###PRODUCT_LINK_PREVIOUS###"] = $this->parent->cObj->typoLink(htmlspecialchars($this->parent->pi_getLL("LABEL_PRODUCTPREVIOUS")), $params);

							// Anteprima per previous
							$imgs = explode(",", $rowAI["image"]);
							$val = $imgs[0];
							$this->conf["previous."]["file"] = "uploads/tx_extendedshop/" . $val;
							$this->conf["previous."]["altText"] = '"' . $rowAI["title"] . '"';
							$markerArray["###PRODUCT_IMG_PREVIOUS###"] = $this->parent->cObj->typoLink($this->parent->cObj->IMAGE($this->conf["previous."]), $params);
							unset($params);
						}
						if (($trovatoAI && $iAI == $iTrovato +1) || $iAI == 0) {
							$params['parameter'] = $GLOBALS['TSFE']->id;
							$params['additionalParams'] = "&".$this->parent->prefixId."[productID]=".$rowAI['uid']."&".$this->parent->prefixId."[pid_product]=".$rowAI['pid'];
							$params['useCacheHash'] = true;
							$markerArray["###PRODUCT_LINK_NEXT###"] = $this->parent->cObj->typoLink(htmlspecialchars($this->parent->pi_getLL("LABEL_PRODUCTNEXT")), $params);							

							// Anteprima per next
							$imgs = explode(",", $rowAI["image"]);
							$val = $imgs[0];
							$this->conf["next."]["file"] = "uploads/tx_extendedshop/" . $val;
							$this->conf["next."]["altText"] = '"' . $rowAI["title"] . '"';
							$markerArray["###PRODUCT_IMG_NEXT###"] = $this->parent->cObj->typoLink($this->parent->cObj->IMAGE($this->conf["next."]), $params);
							unset($params);
						}
					}
					$iAI++;
				}
			} else {
				$template = $this->parent->cObj->substituteSubpart($template, "###LINK_PRODUCTS###", "", $recursive = 0, $keepMarker = 0);
			}
		}

	if (is_array($theImgCode)){
		while (list ($key, $val) = each($theImgCode)) {
			if ($key == 0)
				$k = "";
			else
				$k = $key;
			$markerArray["###PRODUCT_IMAGE".$k."###"] = $val;
		}
	} else
		$markerArray["###PRODUCT_IMAGE###"] = $theImgCode[0];
	
	if ($this->parent->piVars['productID'] != "")
		$markerArray["###LABEL_BACK_URL###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_BACK_URL"));
	else
		$markerArray["###LABEL_BACK_URL###"] = "";
		
	return $markerArray;
	
	}
	
	
	function getTitlePage($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('title', 'pages', 'uid='.$key.'', '', '', '1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $row["title"];
	}
	
	
	
	/**
	 * This function is used to get the number of items of this product that a user can put in his cart
	 */
	function getProductLimit($uid, $conf)	{
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('max_for_order', 'tx_extendedshop_products', 'uid="'.$uid.'"');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			if ($row['max_for_order']>0)
				return $row['max_for_order'];
		}
		
		return $conf['max_for_order'];
	}
	
	
	/**
	 * This function is used to get the product name
	 */
	function getProductTitle($uid)	{
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('title', 'tx_extendedshop_products', 'uid="'.$uid.'"');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			return $row['title'];
		}
		
		return $uid;
	}
	
	/**
	 * This function is used to get a product information related to the end user.
	 * 
	 * @param int 		$product_id 	The product uid
	 * @param array 	$row			The product database informations
	 * @param object 	$parent 		The parent configurations
	 * @param object 	$basket 		The basket configuration and products
	 * 
	 * @return array 					Return the product informations (price,price_notax,vat etc...)
	 */
	function getProductUserInfo($product_id, $row = '', $parent, $basket = ''){
		
		if($row == ''){ // if product need to be search
			$prod_result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'tx_extendedshop_products.*, tx_extendedshop_vat.percent',
					'tx_extendedshop_products LEFT OUTER JOIN tx_extendedshop_vat ON tx_extendedshop_products.vat = tx_extendedshop_vat.uid',
					'tx_extendedshop_products.uid='.$product_id, '', '', '');
			
			if($prod_result != FALSE && $GLOBALS['TYPO3_DB']->sql_num_rows($prod_result)>0){
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($prod_result);
			}
		}
		elseif($row['vat'] != ''){ //search vat
			$vat_result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'percent',
					'tx_extendedshop_vat',
					'uid='.$row['vat'], '', '', '');
					
			if($vat_result != FALSE && $GLOBALS['TYPO3_DB']->sql_num_rows($vat_result)>0){
				$vat_row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($vat_result);
				$row['percent'] = $vat_row['percent'];
			}
		}
		
		if($parent->conf['taxPercent'] != '') // if tax percent is set in TS
			$product_user_info['vat'] = $parent->conf['taxPercent'];
		if($row['percent']!= '') // if tax percent is set for the product
			$product_user_info['vat'] = $row['percent'];
		
		//t3lib_div::debug($parent->conf['taxMode'],'TAX MODE');
		
		if($parent->conf['taxMode'] == 1){ // if product price is taxless
			$product_user_info['original_price_notax'] = $row['price'];
			$product_user_info['original_price'] = tx_extendedshop_products::calculatePriceWithTax($product_user_info['original_price_notax'], $product_user_info['vat']);
		}
		else{ // if product price is tax inclusive
			$product_user_info['original_price'] = $row['price'];
			$product_user_info['original_price_notax'] = tx_extendedshop_products::calculatePriceNoTax($product_user_info['original_price'], $product_user_info['vat']);
		}
		
		$product_user_info['showDiscount'] = 1;
		
		if($row['offertprice'] != '' && $row['offertprice'] > 0){ // if product have a offert price
			if($parent->conf['taxMode'] == 1){ // if product price is taxless
				$product_user_info['discount'] = tx_extendedshop_products::calculateDiscount($product_user_info['original_price_notax'], $row['offertprice']);
				$product_user_info['final_price_B'] = $row['offertprice'];
			}
			else{
				$product_user_info['discount'] = tx_extendedshop_products::calculateDiscount($product_user_info['original_price'], $row['offertprice']);
				$product_user_info['final_price'] = $row['offertprice'];
			}
			
			if($product_user_info['final_price'] == '')
				$product_user_info['final_price'] = tx_extendedshop_products::calculatePriceWithTax($product_user_info['final_price_B'], $product_user_info['vat']);
				
			if($product_user_info['final_price_B'] == '')
				$product_user_info['final_price_B'] = tx_extendedshop_products::calculatePriceNoTax($product_user_info['final_price'], $product_user_info['vat']);;
				
		}
		else{
			$product_user_info['discount'] = $row['discount'];
			if($product_user_info['discount'] == '')
				$product_user_info['discount'] = 0;
				
			// If user is logged, it's necessary to calculate user's discount
			if($product_user_info['discount'] == 0){ // if product don't have a discount, search user discount
				if ($GLOBALS["TSFE"]->fe_user->user['uid'] != "") {
					if ($GLOBALS["TSFE"]->fe_user->user['tx_extendedshop_discount'] == "" || $GLOBALS["TSFE"]->fe_user->user['tx_extendedshop_discount'] == 0){ // if user don't have discount, search group discount
						$groupDiscount = tx_extendedshop_products::getGroupDiscount((int)$GLOBALS["TSFE"]->fe_user->user['usergroup']);
						if ($groupDiscount != "" && $groupDiscount != 0){
							$product_user_info['discount'] = (int)$groupDiscount;
							
							if(!$parent->conf['showOriginalPrice']) // non visualizzare il prezzo di partenza se � settato a 0
								$product_user_info['showDiscount'] = 0;
						}
					}
					else {
						$product_user_info['discount'] = (int)$GLOBALS["TSFE"]->fe_user->user['tx_extendedshop_discount'];
						
						if(!$parent->conf['showOriginalPrice']) // non visualizzare il prezzo di partenza se � settato a 0
							$product_user_info['showDiscount'] = 0;
					}
				}
			}
			
			$product_user_info['final_price_B'] = tx_extendedshop_products::calculatePriceDiscount($product_user_info['original_price_notax'], $product_user_info['discount']);
			$product_user_info['final_price'] = tx_extendedshop_products::calculatePriceDiscount($product_user_info['original_price'],$product_user_info['discount']);
		}
		
		if($basket != '')
			$user_type = tx_extendedshop_pi1::getUserType($parent->conf,$basket);
		else
			$user_type = tx_extendedshop_pi1::getUserType($parent->conf);
		
		if($user_type == 1){ //if user don't pay tax
			$temp = $product_user_info['final_price'];
			$product_user_info['final_price'] = $product_user_info['final_price_B'];
			$product_user_info['final_price_B'] = $temp;
			
			$temp = $product_user_info['original_price'];
			$product_user_info['original_price'] = $product_user_info['original_price_notax'];
			$product_user_info['original_price_notax'] = $temp;
		}
		
		if($parent->conf['debug']){
			t3lib_div::debug($user_type, 'extendedshop_products : getProductUserInfo() : $user_type');		
			t3lib_div::debug($product_user_info, 'extendedshop_products : getProductUserInfo() : $product_user_info');
		}
		
		return $product_user_info;
	}
	
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_products.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_products.php"]);
}