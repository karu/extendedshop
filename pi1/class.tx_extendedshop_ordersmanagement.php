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
 */


class tx_extendedshop_ordersmanagement {

	var $conf = array();
	var $parent;
	
	
	function init($conf, $parent)	{
		$this->conf = $conf;
		$this->parent = $parent;
	}
	
	
	function main($template)	{
		$content = $this->show_orders_list($template);
		return $content;
	}
	
	

	/**
	 * This function displays the list of orders for the logged user
	 */
	function show_orders_list($template, $checkHash=false) {
		$content = "";
		$mA["###LABEL_BASKET_SHIPPING_COST###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_BASKET_SHIPPING_COST"));
		$mA["###LABEL_BASKET_PAYMENT_COST###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_BASKET_PAYMENT_COST"));
		$mA["###LABEL_BACK_URL###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_BACK_URL"));
		$mA["###LABEL_CUSTOMER###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_CUSTOMER"));
		$mA["###LABEL_SHIPPING_TRACKING###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_SHIPPING_TRACKING"));
		
		$user = false;
		if ($GLOBALS["TSFE"]->loginUser != "") {
			$user = $GLOBALS["TSFE"]->fe_user->user;
		}	elseif ($checkHash!=false)	{
			if (t3lib_div::testInt($this->parent->piVars['orderID']))	{
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_orders', 'uid='.$this->parent->piVars['orderID'].' AND deleted=0');
				if ($res!==false && $GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
					$rowOrder = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					if ($rowOrder['customer']>0)	{
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid='.$rowOrder['customer'].' AND deleted=0');
						if ($res!==false && $GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
							$user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				
							$tryThis = $user['uid'].'_'.$user['lastlogin'].'_'.$user['pid'].'_'.sha1($user['password']).'_'.$rowOrder['crdate'].'_'.$rowOrder['pid'];
//t3lib_div::debug($tryThis);							
							if (sha1($tryThis) != $checkHash)
								$user = false;
							
						}
					}
				}
			}
		}
		
		if (is_array($user))	{
		
			$rowTemplate = trim($this->parent->cObj->getSubpart($template, "###ORDER_ROW###"));
			$contentRow = "";

			$pageNumber = $this->parent->piVars["productPage"];
			if ($pageNumber == "")
				$pageNumber = 1;

			if ($this->parent->piVars["orderID"] == "") {
				$template = $this->parent->cObj->substituteSubpart($template, "###ROW_TEMPLATE###", "", $recursive = 0, $keepMarker = 0);
				$template = $this->parent->cObj->substituteSubpart($template, "###ROW_COMMENTS###", "", $recursive = 0, $keepMarker = 0);
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_extendedshop_orders.*, sum(tx_extendedshop_rows.quantity) as numproducts', 'tx_extendedshop_orders LEFT OUTER JOIN tx_extendedshop_rows ON tx_extendedshop_orders.uid=tx_extendedshop_rows.ordercode', 'tx_extendedshop_orders.customer=' . (int)$user["uid"] . ' AND tx_extendedshop_orders.deleted<>1', 'tx_extendedshop_rows.ordercode', 'tx_extendedshop_orders.date DESC', '');
			} else {
				//$template = $this->parent->cObj->substituteSubpart($template, "###PAGE_ZONE###", "", $recursive = 0, $keepMarker = 0);
				$oldPiVars = $this->parent->piVars['orderID'];
				unset($this->parent->piVars['orderID']);
				$mA["###RETURN_LINK###"] = $this->parent->pi_linkTP_keepPIvars($mA["###LABEL_BACK_URL###"]);
				$this->parent->piVars['orderID'] = $oldPiVars;
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_extendedshop_orders.*, sum(tx_extendedshop_rows.quantity) as numproducts', 'tx_extendedshop_orders LEFT OUTER JOIN tx_extendedshop_rows ON tx_extendedshop_orders.uid=tx_extendedshop_rows.ordercode', 'tx_extendedshop_orders.uid=' . (int)$this->parent->piVars["orderID"], 'tx_extendedshop_rows.ordercode', '', '');
				$order_details = true;
			}

			$num_orders = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)<1)	{
				$template = $this->parent->cObj->substituteSubpart($template, '###ORDERS_LIST###', '', 0,0);
				$mA['###LABEL_EMPTYORDERS_LIST###'] = $this->parent->pi_getLL('LABEL_EMPTYORDERS_LIST');
				return $this->parent->cObj->substituteMarkerArray($template, $mA);
			}	else	{
				$template = $this->parent->cObj->substituteSubpart($template, '###ORDERS_EMPTY###', '', 0,0);
			}
			
			
			// orders for page
			$num_orders_for_page = $this->conf["ordersInfo."]["ordersForPage"];

			// Page management
			$num_pages = ceil($num_orders / $num_orders_for_page);
			for ($i = 1; $i <= $num_pages; $i++) {
				if ($i == $pageNumber) {
					$pageLink .= " <strong>" . $i . "</strong>";
				} else {
					$this->parent->piVars['productPage'] = $i;
					$pageLink .= " ".$this->parent->pi_linkTP_keepPIvars($i);
					//unset($this->parent->piVars['productPage']);
				}
			}
			$this->parent->piVars['productPage'] = 'all';
			$mA["###ORDER_VIEWALL###"] = $this->parent->pi_linkTP_keepPIvars_url();
			unset($this->parent->piVars['productPage']);
			
			$mA["###ORDER_PAGES###"] = $pageLink;
			$mA["###LABEL_TAB###"] = "Y";

			// Scarta i primi ordini se sono in pagine successive
			if ($pageNumber != "all" && $pageNumber > 1) {
				for ($i = 0; $i < ($pageNumber -1) * $num_orders_for_page; $i++) {
					$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				}
			} elseif ($pageNumber == "all") {
				$num_orders_for_page = $num_orders;
			}
			
			// $pid is used in case of inserting comments
			$pid = 0;

			for ($i = 0; $i < $num_orders_for_page && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res); $i++) {
				$resUser = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid=' . $row['customer'], '', '', '');
				$rowUser = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resUser);
				
				$rowShippingCustomer = array();
				if ($row['shippingcustomer'] != ""){
					$resShippingCustomer = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_address', 'uid='.$row['shippingcustomer'],'','','');
					if ($GLOBALS['TYPO3_DB']->sql_num_rows($resShippingCustomer) == 1){
						$rowShippingCustomer = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resShippingCustomer);
					}	else	{
						$rowShippingCustomer = $rowUser;
					}
				}	else	{
					$rowShippingCustomer = $rowUser;
				}
				
				$totalProducts = 0;
				$resProd = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_rows', 'ordercode=' . $row["uid"], '', '', '');
				while ($rowProd = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resProd)) {
					$totalProducts += $rowProd["price"] * $rowProd["quantity"];
				}
				
				$shippingPrice = $this->parent->priceFormat($row["shipping_cost"]);
				if ($shippingPrice == "")
					$shippingPrice = "0,00";
					
				$paymentPrice = $this->parent->priceFormat($row["payment_cost"]);
				if ($paymentPrice == "")
					$paymentPrice = "0,00";
					
				/*$totale = $this->parent->priceFormat($totalProducts + $paymentPrice + $shippingPrice);
				$tot = $totalProducts + $paymentPrice + $shippingPrice;
				// Aggiorna il totale...
				if ($tot != $row['total']) {
					$update["total"] = $tot;
					$resUpdate = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_orders', 'uid=' . $row["uid"], $update);
				}*/

				$rA = array();
				$rA["###ORDER_NUMBER###"] = $row["code"];
				$rA["###ORDER_SHIPPING_TRACKING###"] = $row["shipping_tracking"];
				$rA["###ORDER_NUMPRODUCTS###"] = $row["numproducts"];
				$rA["###ORDER_DATE###"] = date("d M Y - H:i:s", $row['date']);
				$rA["###ORDER_SHIPPING###"] = $row["shipping"];
				$rA["###ORDER_SHIPPING_COST###"] = $shippingPrice;
				$rA["###ORDER_PAYMENT###"] = $row["payment"];
				$rA["###ORDER_PAYMENT_COST###"] = $paymentPrice;
				$rA["###ORDER_TOTAL###"] = $this->parent->priceFormat($row["total"]);
				$rA["###ORDER_SELLERNOTE###"] = $row["ordernote"];
				$rA["###ORDER_NOTE###"] = $row["note"];
				if ($row['deliverydate'] == "0" || $row['deliverydate'] == "")
					$rA["###ORDER_DELIVERYDATE###"] = "";
				else
					$rA["###ORDER_DELIVERYDATE###"] = date("d M Y", $row['deliverydate']);
				
				
				if (is_array($rowShippingCustomer))	{
					foreach ($rowShippingCustomer as $key => $value)	{
						$rA["###DELIVERYTO_".strtoupper($key)."###"] = $value;
					}
				}
				if (is_array($rowUser))	{
					foreach ($rowUser as $key => $value)	{
						$rA["###CUSTOMER_".strtoupper($key)."###"] = $value;
					}
				}

				if ($rowShippingCustomer['country']!='' && strlen($rowShippingCustomer['country'])==3)	{
					$resCountry = $GLOBALS['TYPO3_DB']->exec_SELECTquery('cn_short_en', 'static_countries', 'cn_iso_3="'.$rowShippingCustomer['country'].'"', '', '', '1');
					if ($GLOBALS['TYPO3_DB']->sql_num_rows($resCountry)==1)	{
						$rowCountry = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resCountry);
						if ($rowCountry["cn_short_en"]!='')
							$rA['###DELIVERYTO_COUNTRY###'] = $rowCountry["cn_short_en"];
					}
				}
				if ($rowUser['country']!='' && strlen($rowUser['country'])==3)	{
					$resCountry = $GLOBALS['TYPO3_DB']->exec_SELECTquery('cn_short_en', 'static_countries', 'cn_iso_3="'.$rowUser['country'].'"', '', '', '1');
					if ($GLOBALS['TYPO3_DB']->sql_num_rows($resCountry)==1)	{
						$rowCountry = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resCountry);
						if ($rowCountry["cn_short_en"]!='')
							$rA['###CUSTOMER_COUNTRY###'] = $rowCountry["cn_short_en"];
					}
				}
				
				$this->parent->piVars['orderID'] = $row["uid"];			
				$rA["###ORDER_DETAILS###"] = $this->parent->pi_linkTP_keepPIvars_url();
				//unset($this->parent->piVars['productID']);

				if ($row['complete'] != 1)
					$workingTemplate = $this->parent->cObj->substituteSubpart($rowTemplate, "###ORDER_COMPLETED###", "", $recursive = 0, $keepMarker = 0);
				else
					$workingTemplate = $rowTemplate;

				// Status management
				$lingua = (int)t3lib_div :: _GP("L");
				if ($lingua > 0) {
					$resStatus = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_status', 'l18n_parent=' . $row["status"] . ' AND sys_language_uid=' . $lingua . ' AND deleted<>1 AND hidden<>1', '', '', '');
				}
				if ($resStatus == "" || $GLOBALS['TYPO3_DB']->sql_num_rows($resStatus) == 0 || $lingua <= 0) {
					$resStatus = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_status', 'uid=' . $row["status"] . ' AND deleted<>1 AND hidden<>1', '', '', '');
				}
				$rowStatus = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resStatus);
				$rA["###ORDER_STATUS###"] = $rowStatus["status"];

				// Background-color management
				if ($i % 2 == 0)
					$rA["###BACKGROUND###"] = "even";
				else
					$rA["###BACKGROUND###"] = "odd";
				
				
				// Hook that can be used to manage custom markers
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['orders_custom_markers']))    {
				    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['orders_custom_markers'] as $_classRef)    {
				        $_procObj = &t3lib_div::getUserObj($_classRef);
				        $params = array(
				        	"markers" 	=> $rA,
				        	"order"		=> $row,
				        );
				        $rA = $_procObj->evaluateOrderArray($params, $template, $this);
				    }
				}
				

				$contentRow .= $this->parent->cObj->substituteMarkerArray($workingTemplate, $rA);
				unset ($rA);
				
				$pid = $row['pid'];
			}
			$template = $this->parent->cObj->substituteSubpart($template, "###ORDER_ROW###", $contentRow, $recursive = 0, $keepMarker = 0);

			// Order details
			if ($order_details) {
				$template = $this->showOrderDetails($template);
			}

			$content = $this->parent->cObj->substituteMarkerArray($template, $mA);
		} else {
			$content = "No user logged";
		}
		return $content;
	}
	
	
	
	function showOrderDetails($template)	{
		
		$order = t3lib_div::makeInstance(t3lib_div::makeInstanceClassName("tx_extendedshop_order"));
		$order->init($this->conf, $this);
		$userObj = t3lib_div::makeInstance(t3lib_div::makeInstanceClassName("tx_extendedshop_usersmanagement"));
		$userObj->init($this->conf, $this);

		if ($this->parent->piVars["comment"] != ""){
			$order->insertComment($this->parent->piVars["orderID"], $this->parent->piVars["comment"], $user["uid"], $pid);
		}
		
		$singleRowComment = trim($this->parent->cObj->getSubpart($template, "###SINGLE_COMMENT###"));
		$mA["###LABEL_COMMENT_DATA###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_COMMENT_DATA"));
		$mA["###LABEL_COMMENT_MESSAGE###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_COMMENT_MESSAGE"));
		$mA["###LABEL_COMMENT_USERID###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_COMMENT_USERID"));
		$mA["###LABEL_COMMENT_BEID###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_COMMENT_BEID"));
		$mA["###LABEL_SEND_COMMENT###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_SEND_COMMENT"));
		$mA["###LABEL_COMMENT_AREA###"] = htmlspecialchars($this->parent->pi_getLL("LABEL_COMMENT_AREA"));
		$mA["###PERSONAL_COMMENT###"] = "";
		//$ma["###ORDER_ID###"] = (int)$this->parent->piVars["productID"];
		
		if ($order->isEmptyComment((int)$this->parent->piVars["orderID"])){
			$mA["###COMMENT_DATETIME###"] = "";
			$mA["###COMMENT_TEXT###"] = "";
			$mA["###COMMENT_USERID###"] = "";
			$mA["###COMMENT_BEID###"] = "";
		} else {
			// Comment is an array
			$comments = $order->getComment((int)$this->parent->piVars["orderID"]);
			$contentComment = "";
			while(list($key, $value) = each($comments)){
				if ($value["userid"] != "" && $value["userid"] != 0){
					$userData = $userObj->getFeUser($value["userid"]);
					$cA["###COMMENT_USERID###"] = $userData["name"];
				} else {
					$cA["###COMMENT_USERID###"] = "";
				}
				
				if ($value["cruser_id"] != "" && $value["cruser_id"] != 0){
					$userData = $userObj->getBeUser($value["cruser_id"]);
					if ($userData["realName"] != "")
						$cA["###COMMENT_BEID###"] = $userData["realName"];
					else
						$cA["###COMMENT_BEID###"] = $userData["username"];
				} else {
					$cA["###COMMENT_BEID###"] = "";
				}
				
				$cA["###COMMENT_DATETIME###"] = date("d M Y - H:i:s", $value["datetime"]);
				$cA["###COMMENT_TEXT###"] = $value["message"];
				
				// Background-color management
				if ($i % 2 == 0)
					$cA["###BACKGROUND_COMMENT###"] = "evenComment";
				else
					$cA["###BACKGROUND_COMMENT###"] = "oddComment";
				$i++;
				$contentComment .= $this->parent->cObj->substituteMarkerArray($singleRowComment, $cA);
				unset ($cA);
			}
			$template = $this->parent->cObj->substituteSubpart($template, "###SINGLE_COMMENT###", $contentComment, $recursive = 0, $keepMarker = 0);
		}
		$singleRowTemplate = trim($this->parent->cObj->getSubpart($template, "###SINGLE_ROW###"));
		$contentProducts = "";
		
		
		$resRows = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_rows', 'ordercode=' . (int)$this->parent->piVars["orderID"] . ' AND deleted<>1 AND hidden<>1', '', '', '');
		$i = 0;
		while ($rowRows = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resRows)) {
			foreach ($rowRows as $key => $value)
				$rA["###PRODUCT_".strtoupper($key)."###"] = $value;
			$rA["###PRODUCT_PRICE###"] = $this->parent->priceFormat($rowRows["price"]);
			$rA["###PRODUCT_QUANTITY###"] = $rowRows["quantity"];
			$rA["###PRODUCT_COMBINATIONS###"] = $rowRows["options"];
			$rA["###PRODUCT_CODE###"] = $rowRows["itemcode"];
			$rA["###PRODUCT_TOTAL_PRICE###"] = $this->parent->priceFormat($rowRows["price"] * $rowRows["quantity"]);
			$resProd = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid=' . $rowRows["productcode"] . ' AND deleted<>1 AND hidden<>1', '', '', '');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($resProd) == 1) {
				$rowProd = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resProd);
				$rA["###PRODUCT_TITLE###"] = $rowProd["title"];
				// Get image
				$imageNum = 0;
				$theImgCode = "";
				$imgs = explode(",", $rowProd["image"]);
				$val = $imgs[0];
				while (list ($c, $val) = each($imgs)) {
					if ($val) {
						$this->conf["ordersImage."]["file"] = "uploads/tx_extendedshop/" . $val;
					} else {
						$this->conf["ordersImage."]["file"] = $this->conf["noImageAvailable"];
					}
					$this->conf["ordersImage."]["altText"] = '"' . $rowProd["title"] . '"';
					$theImgCode .= $this->parent->cObj->IMAGE($this->conf["ordersImage."]);
				}
				$rA["###PRODUCT_IMAGE###"] = $theImgCode;
			} else {
				$rA["###PRODUCT_TITLE###"] = "";
				$rA["###PRODUCT_IMAGE###"] = "";
			}
			// Background-color management
			if ($i % 2 == 0)
				$rA["###BACKGROUND###"] = "even";
			else
				$rA["###BACKGROUND###"] = "odd";
			$i++;
			$contentProducts .= $this->parent->cObj->substituteMarkerArray($singleRowTemplate, $rA);
			unset ($rA);
		}

		$template = $this->parent->cObj->substituteSubpart($template, "###SINGLE_ROW###", $contentProducts, $recursive = 0, $keepMarker = 0);
		
		return $template;
	}
	
	
	
	
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/pi1/class.tx_extendedshop_ordersmanagement.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/pi1/class.tx_extendedshop_ordersmanagement.php"]);
}
?>
