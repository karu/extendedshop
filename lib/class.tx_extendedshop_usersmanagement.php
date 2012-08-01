<?php
/*
 * Created on 28-ago-2007
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


class tx_extendedshop_usersmanagement {

	var $conf = array();
	var $parent;
	
	
	function init($conf, $parent)	{
		$this->conf = $conf;
		$this->parent = $parent;
	}
	
	/**
	 * @param int key id user
	 */
	function getFeUser($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid="'.$key.'"', '', '', '1');
		return $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	}
	
	/**
	 * @param int key id user
	 */
	function getBeUser($key){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'be_users', 'uid="'.$key.'"', '', '', '1');
		return $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	}
	
	/**
	 * Generating a new random password
	 */
	function newPassword() {
		$length = rand(5, 8);
		$pass = "";
		for ($i = 0; $i < $length; $i++) {
			$a["1"] = rand(48, 57);
			$a["2"] = rand(65, 90);
			$a["3"] = rand(97, 122);
			$j = rand(1, 3);
			$pass .= chr($a[$j]);
		}
		return $pass;
	}
	
	
	/**
	 * This function search for the customer and delivery users. If they aren't present, the function creates two new users
	 */
	function manageUsers() {
		// Test if customer is a new user
		$personinfo = $this->parent->basketRef->getPersonInfo();
		//t3lib_div::debug($personinfo);
		$email = addslashes($personinfo["EMAIL"]);
		
		// Customer management
		if ($GLOBALS['TSFE']->loginUser!="")
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid=' . $GLOBALS['TSFE']->fe_user->user['uid'] . ' and deleted<>1');
		else
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'username="' . $email . '" and deleted<>1');
		$insertFields["name"] = addslashes($personinfo["NAME"]);
		// inserimento dei valori usando sr_user_registration
		$nameSurname = explode (" ", $personinfo["NAME"], 2);
		$insertFields['first_name'] = $nameSurname[0];
		$insertFields['last_name'] = $nameSurname[1];
		
		$insertFields["email"] = $email;
		$insertFields["telephone"] = addslashes($personinfo["PHONE"]);
		$insertFields["address"] = addslashes($personinfo["ADDRESS"]);
		$insertFields["company"] = addslashes($personinfo["COMPANY"]);
		$insertFields["fax"] = addslashes($personinfo["FAX"]);
		$insertFields["city"] = addslashes($personinfo["CITY"]);
		$insertFields["country"] = addslashes($personinfo["COUNTRY"]);
		// sr_feuser_registration
		$insertFields["static_info_country"] = addslashes($personinfo["COUNTRY"]);
		
		$insertFields["tx_extendedshop_state"] = addslashes($personinfo["STATE"]);
		// sr_feuser_registration
		$insertFields["zone"] = addslashes($personinfo["STATE"]);
		
		$insertFields["zip"] = addslashes($personinfo["ZIP"]);
		$insertFields["tx_extendedshop_mobile"] = addslashes($personinfo["MOBILE"]);
		$insertFields["www"] = addslashes($personinfo["WWW"]);
		$insertFields["tx_extendedshop_vatcode"] = addslashes($personinfo["VATCODE"]);
		$insertFields["tx_extendedshop_private"] = addslashes($personinfo["PRIVATE"]);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) != 1) {
			// New user
			$newUserTemplate = trim($this->parent->cObj->getSubpart($this->parent->config["templateCode"], "###NEWUSER_TEMPLATE###"));
			$pass = $this->newPassword();
			$markerArray["###NEWUSER_USER###"] = $email;
			$markerArray["###NEWUSER_PASS###"] = $pass;
			$newUserTemplate = $this->parent->cObj->substituteMarkerArray($newUserTemplate, $markerArray);
			$this->parent->send_email($this->parent->manageLabels($newUserTemplate), "EMAIL_NEWUSER_SUBJECT");
			
			$insertFields["pid"] = $this->conf["pid_users"];
			$insertFields["usergroup"] = $this->conf["group_customer"];
			$insertFields["username"] = $email;
			$insertFields["password"] = $pass;
			
			$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('fe_users', $insertFields);
			$user["idCustomer"] = $GLOBALS['TYPO3_DB']->sql_insert_id();
			
		} else {
			// Existing user
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('fe_users', 'uid=' . $row["uid"], $insertFields);
			$user["idCustomer"] = $row["uid"];
		}
		
		$insertFields = "";
		// Test if delivery person is a new user or empty
		$delivery = $this->parent->basketRef->getDeliveryInfo();
		if ($delivery['more']==1 && $this->checkRequired() && $name = addslashes($delivery["NAME"]) != ""){
			//t3lib_div::debug($delivery);
			$email = addslashes($delivery["EMAIL"]);
			if ($email!='')
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_address', 'email="' . $email. '" and deleted<>1');
			if ($email=='' || $GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
				$insertFields["pid"] = $this->conf["pid_delivery"];
				$insertFields["name"] = addslashes($delivery["NAME"]);
				$insertFields["email"] = addslashes($delivery["EMAIL"]);
				$insertFields["phone"] = addslashes($delivery["PHONE"]);
				$insertFields["mobile"] = addslashes($delivery["MOBILE"]);
				$insertFields["address"] = addslashes($delivery["ADDRESS"]);
				$insertFields["company"] = addslashes($delivery["COMPANY"]);
				$insertFields["city"] = addslashes($delivery["CITY"]);
				$insertFields["country"] = addslashes($delivery["COUNTRY"]);
				$insertFields["tx_extendedshop_state"] = addslashes($delivery["STATE"]);
				$insertFields["zip"] = addslashes($delivery["ZIP"]);
				$insertFields["hidden"] = 1;
				
				// Hook that can be used to save custom delivery fields
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['customDeliveryFields']))    {
				    foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extendedshop']['customDeliveryFields'] as $_classRef)    {
				        $_procObj = &t3lib_div::getUserObj($_classRef);
				        $insertFields = $_procObj->saveCustomDeliveryFields($insertFields, $delivery, $this->parent);
				    }
				}
				
				$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tt_address', $insertFields);
				$user["idDelivery"] = $GLOBALS['TYPO3_DB']->sql_insert_id();
			}	else	{
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$user["idDelivery"] = $row["uid"];
			}
		}
		
		
		return $user;
	}
	
	
	/**
	*	Checks if all required personal info fileds are filled
	*
	*/
	function checkRequired() {
		// Checks the email address
		$personinfo = $this->parent->basketRef->getPersonInfo();
		if (strpos($personinfo['EMAIL'],',')>0 || strpos($personinfo['EMAIL'],';')>0)
			$personinfo['EMAIL'] = '';
		if (!t3lib_div::validEmail($personinfo['EMAIL']))	{
			return false;
		}
			
		$deliveryinfo = $this->parent->basketRef->getDeliveryInfo();
		if ($deliveryinfo["more"]==1) {
			$listRequired = explode(",", trim($this->conf["requiredDeliveryFields"]));
			while (list ($key, $field) = each($listRequired)) {
				if ($deliveryinfo[strtoupper($field)] == "") {
					return false;
				}
			}
		}
				
		$listRequired = explode(",", trim($this->conf["requiredFields"]));
		while (list ($key, $field) = each($listRequired)) {
			if ($field!='' && $personinfo[strtoupper($field)] == "") {
				return false;
			}
		}
		
		return true;
	}
	
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_usersmanagement.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/lib/class.tx_extendedshop_usersmanagement.php"]);
}
?>