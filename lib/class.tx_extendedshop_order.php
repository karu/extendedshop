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


class tx_extendedshop_order {

	var $conf = array();
	var $parent;
	
	
	function init($conf, $parent)	{
		$this->conf = $conf;
		$this->parent = $parent;
	}
	
	

	/**
	 * This function inserts the order, the rows and some pages to organize the BE work
	 */
	function insertOrder($idCustomer, $idDelivery, $content, $trackingcode = false, $markerArray, $basket) {

		if ($trackingcode == false)
			$trackingcode = time();
		$pid_orders = $this->conf["pid_orders"];
		
		// Folder Management
		$year = date("Y");
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'pid="' . $pid_orders . '" AND title="' . $year . '" AND deleted <>1');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) < 1) {
			$insertFields["pid"] = $pid_orders;
			$insertFields["title"] = $year;
			$time = time();
			$insertFields["tstamp"] = $time;
			$insertFields["crdate"] = $time;
			$insertFields["doktype"] = 254;
			$insertFields["perms_userid"] = $this->conf['permissions.']['userid'];
			$insertFields["perms_groupid"] = $this->conf['permissions.']['groupid'];
			$insertFields["perms_user"] = $this->conf['permissions.']['users'];
			$insertFields["perms_group"] = $this->conf['permissions.']['groups']; 
			$resP = $GLOBALS['TYPO3_DB']->exec_INSERTquery('pages', $insertFields);
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'pid="' . $pid_orders . '" AND title="' . $year . '" AND deleted <>1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$id_year = $row["uid"];
		$month = date("m");
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'pid="' . $id_year . '" AND title="' . $month . '" AND deleted <>1');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) < 1) {
			$insertFields["pid"] = $id_year;
			$insertFields["title"] = $month;
			$time = time();
			$insertFields["tstamp"] = $time;
			$insertFields["crdate"] = $time;
			$insertFields["doktype"] = 254;
			$insertFields["perms_userid"] = $this->conf['permissions.']['userid'];
			$insertFields["perms_groupid"] = $this->conf['permissions.']['groupid'];
			$insertFields["perms_user"] = $this->conf['permissions.']['users'];
			$insertFields["perms_group"] = $this->conf['permissions.']['groups']; 
			$resP = $GLOBALS['TYPO3_DB']->exec_INSERTquery('pages', $insertFields);
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'pid="' . $id_year . '" AND title="' . $month . '" AND deleted <>1');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$id_month = $row["uid"];
		
		$personinfo = $basket->getPersonInfo();
		
		$perc = $basket->getPercent();
		
		$shippingTitle = $basket->getShippingTitle($basket->getShipping()).' ('.$basket->getCountryDestination().')';
		$shippingCost =$basket->getShippingPriceTax($basket->getShipping());
		
		$paymentTitle = $basket->getPaymentTitle($basket->getPayment());
		$paymentCost = $basket->getPaymentPriceTax($basket->getPayment());
				
		//t3lib_div::debug($basket->basket);
		
		// Insert Order
		$orderCode = $this->conf["orderCode"];
		$orderFields = array (
			"code" => $orderCode,
			"customer" => (int)$idCustomer,
			"shippingcustomer" => (int)$idDelivery,
			"date" => time(), 
			"shipping" => $shippingTitle, 
			"payment" => $paymentTitle, 
			"shipping_cost" => $shippingCost,
			"payment_cost" => $paymentCost,
			"total" => $basket->getTotalPrice(),
			"total_notax" => $basket->getTotalPriceNoTax(), 
			//"pid" => $id_order, 
			"ip" => t3lib_div :: getIndpEnv("REMOTE_ADDR"), 
			"note" => $personinfo["NOTE"],
			"status" => 1
		);
		
		// Hook that can be used to save custom fields in main order record
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['save_custom_order_fields']))    {
		    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['save_custom_order_fields'] as $_classRef)    {
		        $_procObj = &t3lib_div::getUserObj($_classRef);
		        $orderFields = $_procObj->saveCustomOrderFields($orderFields, $basket, $this->parent);
		    }
		}
		
		$resO = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_extendedshop_orders', $orderFields);

		$newId = $GLOBALS['TYPO3_DB']->sql_insert_id();

		$orderCode = $this->conf["orderCode"] . $newId;
		$markerArray["###ORDERID###"] = $orderCode;

		$insertFields["pid"] = $id_month;
		$insertFields["title"] = $orderCode;
		$time = time();
		$insertFields["tstamp"] = $time;
		$insertFields["crdate"] = $time;
		$insertFields["doktype"] = 1;
		$insertFields["hidden"] = 1;
		$insertFields["perms_userid"] = $this->conf['permissions.']['userid'];
		$insertFields["perms_groupid"] = $this->conf['permissions.']['groupid'];
		$insertFields["perms_user"] = $this->conf['permissions.']['users'];
		$insertFields["perms_group"] = $this->conf['permissions.']['groups']; 
		$resP = $GLOBALS['TYPO3_DB']->exec_INSERTquery('pages', $insertFields);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', 'pid="' . $id_month . '" AND title="' . $orderCode . '"');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

		$id_order = $row["uid"];

		$insertContent["pid"] = $id_order;
		$insertContent["tstamp"] = time();
		$insertContent["header"] = $orderCode;
		$insertContent["bodytext"] = $this->parent->clearInput($this->parent->manageLabels($this->parent->cObj->substituteMarkerArray($content, $markerArray)));
		$insertContent["CType"] = "html";

		$resC = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tt_content', $insertContent);

		$updateFields = array (
			"code" => $orderCode,
			"pid" => $id_order,
			"trackingcode" => $orderCode . "_" . $trackingcode,

			
		);
		$resO = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_orders', 'uid=' . $newId, $updateFields);
		// Insert Rows
		$res = "";
		$row = "";
		$basketProducts= $basket->getBasketProducts();
		if (is_array($basketProducts))	{
			foreach ($basketProducts as $product) {
				if ($product["num"] > 0) {
					$resProd = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid='.$product['uid'], '', '', '1');
					$rowProd = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resProd);
					//t3lib_div::debug($rowProd);
					$prod = t3lib_div::makeInstance("tx_extendedshop_products");
					$prod->init($rowProd, $this->parent, $resProd);
					
					$insertRow["pid"] = $id_order;
					$insertRow["tstamp"] = time();
					$insertRow["crdate"] = time();
					$insertRow["ordercode"] = $newId;
					$insertRow["productcode"] = $product["uid"];
					$insertRow["itemcode"] = $prod->getCode();
					$insertRow["quantity"] = (int)$product["num"];
					$insertRow["price"] = $product["price"];
					$insertRow["weight"] = $prod->getRowWeight((int)$product["num"]);
					$insertRow["volume"] = $prod->getRowVolume((int)$product["num"]);
					$insertRow["options"] = "";
					
					for ($i = 1; $i <= $product["combinations"]; $i++) {
						$insertRow["options"] .= '(' . $product["sizes"][$i] . '-' . $product["colors"][$i] . ') ';
					}
					
					if ($this->conf["enable_instock_management"] != 0){
						$inStock = $prod->getInStock($product["uid"]);
						if($inStock != ""){
							// Do the update to inStock
							$up_inStock["instock"] = $inStock - $insertRow["quantity"];
							$resInStock = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_products', 'uid='.$product["uid"], $up_inStock);
							
							if ($this->conf["allert_instock_management"] != ""){
								if ($inStock = $prod->getInStock($product["uid"]) < $this->conf["allert_instock_management"]){
									$mailMessage = "Product ".$product["uid"]." under critical quantity\n" .
													"Available: $inStock \n";
									$mailObject = "Product ".$product["uid"]." under critical quantity";
									$mailTo = $this->conf["orderEmail_from"];
									mail($mailTo, $mailObject, $mailMessage);
								}
							}
						}	
					}
					
					// Hook that can be used to save custom fields in order row
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['save_custom_product_fields']))    {
					    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['save_custom_product_fields'] as $_classRef)    {
					        $_procObj = &t3lib_div::getUserObj($_classRef);
					        $insertRow = $_procObj->saveCustomFields($insertRow, $rowProd, $product);
					    }
					}
					
					$resR = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_extendedshop_rows', $insertRow);
				}
			}
		}
			
		return $orderCode;

	}
	
	/**
	 * @param int $key id of the order
	 * @tutorial This function is true if the order have not associated comments (table tx_extendedshop_comments)
	 */
	function isEmptyComment($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_comments', 'orderid='.$key.'', '', '', '');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * @param int $key id of the order
	 * @tutorial Make an array of comments
	 */
	function getComment($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_comments', 'orderid='.$key.'', '', '', '');
		$i = 1;
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
			$comments[$i]["datetime"] = $row["datetime"];
			$comments[$i]["userid"] = $row["userid"];
			$comments[$i]["message"] = $row["message"];
			$comments[$i]["beuserid"] = $row["cruser_id"];
			$i++;
		}
		return $comments;
	}
	
	/**
	 * @param int $key id of the order
	 * @tutorial Insert a comment by FE and send e-mail to the customer
	 */
	function insertComment($orderid, $comment, $userid, $pid){
		$commentData["datetime"] = strtotime ("now");
		$commentData["orderid"] = $orderid;
		$commentData["userid"] = $userid;
		$commentData["message"] = htmlspecialchars($comment);
		$commentData["pid"] = $pid;
		$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_extendedshop_comments', $commentData);
		$mailMessage = "Message inserted in".$commentData["datetime"]."\n" .
				"Order: $orderid \n" .
				"UserID: $userid \n" .
				"Message: $comment";
		$mailObject = "New message in the shop";
		$mailTo = $this->conf["orderEmail_from"];
		mail($mailTo, $mailObject, $mailMessage);
	}
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_order.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_order.php"]);
}

?>
