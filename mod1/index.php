<?php

/***************************************************************
*  Copyright notice
*  
*  (c) 2007  (Mauro Lorenzutti <mauro.lorenzutti@webformat.com>)
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
 * Module 'Orders Management' for the 'extendedshop' extension.
 *
 * @author	Mauro Lorenzutti <mauro.lorenzutti@webformat.com>
 */
 
// DEFAULT initialization of a module [BEGIN]
unset ($MCONF);
require ("conf.php");
require ($BACK_PATH . "init.php");
require ($BACK_PATH . "template.php");
$LANG->includeLLFile("EXT:extendedshop/mod1/locallang.php");
#include ("locallang.php");
require_once (PATH_t3lib . "class.t3lib_scbase.php");
$BE_USER->modAccess($MCONF, 1); // This checks permissions and exits if the users has no permission for entry.
// DEFAULT initialization of a module [END]

class tx_extendedshop_module1 extends t3lib_SCbase {
	var $pageinfo;

	var $daysToDelivery = 10; // Default days from the order date and the delivery date
	var $num_orders_for_page = 10; // Number of orders for page
	var $currency = "\$"; // Currency

	/**
	 * 
	 */
	function init() {
		global $BE_USER, $LANG, $BACK_PATH, $TCA_DESCR, $TCA, $CLIENT, $TYPO3_CONF_VARS;

		parent :: init();

		/*
		if (t3lib_div::_GP("clear_all_cache"))	{
			$this->include_once[]=PATH_t3lib."class.t3lib_tcemain.php";
		}
		*/
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 */
	function menuConfig() {
		global $LANG;
		$this->MOD_MENU = Array (
			"function" => Array (
				"1" => $LANG->getLL("function1"),
				"2" => $LANG->getLL("function2"),
				"3" => $LANG->getLL("function3"),
				"4" => $LANG->getLL("function4"),
			));
		parent :: menuConfig();
	}

	// If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	/**
	 * Main function of the module. Write the content to $this->content
	 */
	function main() {
		global $BE_USER, $LANG, $BACK_PATH, $TCA_DESCR, $TCA, $CLIENT, $TYPO3_CONF_VARS;

		$this->doc = t3lib_div :: makeInstance("bigDoc");
		$this->doc->backPath = $BACK_PATH;
		$this->doc->form = '<form action="" method="POST">';

		// JavaScript
		$this->doc->JScode = '
						<script language="javascript" type="text/javascript">
							script_ended = 0;
							function jumpToUrl(URL)	{
								document.location = URL;
							}
							
							function displayConfirm(testo)
							{
								if (confirm(testo)) {return(true)}
								else {return(false)}
							}
						</script>
					';
		$this->doc->postCode = '
						<script language="javascript" type="text/javascript">
							script_ended = 1;
							if (top.fsMod) top.fsMod.recentIds["web"] = ' . intval($this->id) . ';
						</script>
					';

		$headerSection = $this->doc->getHeader("pages", $this->pageinfo, $this->pageinfo["_thePath"]) . "<br>" . $LANG->sL("LLL:EXT:lang/locallang_core.php:labels.path") . ": " . t3lib_div :: fixed_lgd_pre($this->pageinfo["_thePath"], 50);

		$this->content .= $this->doc->startPage($LANG->getLL("title"));
		$this->content .= $this->doc->header($LANG->getLL("title"));
		$this->content .= $this->doc->spacer(5);
		$this->content .= $this->doc->section("", $this->doc->funcMenu($headerSection, t3lib_BEfunc :: getFuncMenu($this->id, "SET[function]", $this->MOD_SETTINGS["function"], $this->MOD_MENU["function"])));
		$this->content .= $this->doc->divider(5);

		// Render content:
		$this->moduleContent();
		$this->content .= $this->doc->spacer(10);
	}

	/**
	 * Prints out the module HTML
	 */
	function printContent() {

		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 */
	function moduleContent() {
		switch ((string) $this->MOD_SETTINGS["function"]) {
			case 1 :
				$content = $this->statistics();
				$this->content .= $this->doc->section("Orders statistics:", $content, 0, 1);
				break;
			case 2 :
				$content = $this->listOrders();
				$this->content .= $this->doc->section("Orders:", $content, 0, 1);
				break;
			case 3 :
				$content = $this->listCompletedOrders();
				$this->content .= $this->doc->section("Completed orders:", $content, 0, 1);
				break;
			case 4 :
				if (t3lib_div::testInt(t3lib_div::_GP('productID')))
					$content = $this->detailsProductStatistics();
				else
					$content = $this->listProductStatistics();
				$this->content .= $this->doc->section("Product statistics:", $content, 0, 1);
				break;
		}
	}

	function statistics() {
		$content .= '<form action="index.php?id=0&SET[function]=1" method="post">';
		$content .= '<label form="month">Month</label>: ';
		$content .= '<select id="month" name="month">';
		$content .= '<option value=""></option>';
		for ($i=1; $i<13; $i++)	{
			$content .= '<option value="'.$i.'"'.($i==t3lib_div::_GP('month') ? ' selected="selected"' : '').'>'.date('F', mktime(0,0,0,$i,1,2000)).'</option>';
		}
		$content .= '</select>';
		
		$content .= ' - <label form="year">Year</label>: ';
		$content .= '<select id="year" name="year">';
		$content .= '<option value=""></option>';
		for ($i=date("Y", time()); $i>1999; $i--)
			$content .= '<option value="'.$i.'"'.($i==t3lib_div::_GP('year') ? ' selected="selected"' : '').'>'.$i.'</option>';
		$content .= '</select>';
		$content .= ' - <input type="submit" name="search" value="Go" />';
		$content .= '</form>';
		
		$where = ' AND tx_extendedshop_orders.deleted=0';
		if (t3lib_div::testInt(t3lib_div::_GP('month')) && t3lib_div::testInt(t3lib_div::_GP('year')))	{
			$where .= ' AND tx_extendedshop_orders.date>='.mktime(0,0,0,t3lib_div::_GP('month'),1,t3lib_div::_GP('year')).' AND tx_extendedshop_orders.date<='.mktime(23,59,59,t3lib_div::_GP('month')+1,0,t3lib_div::_GP('year'));
		}	elseif (t3lib_div::_GP('month')=='' && t3lib_div::testInt(t3lib_div::_GP('year')))	{
			$where .= ' AND tx_extendedshop_orders.date>='.mktime(0,0,0,1,1,t3lib_div::_GP('year')).' AND tx_extendedshop_orders.date<='.mktime(23,59,59,12,31,t3lib_div::_GP('year'));
		}	elseif (t3lib_div::_GP('month')!='' && t3lib_div::_GP('year')=='')	{
			$content .= ' - <span style="color: red">You must specify a year</span>';
		}
		
		$content .= "<div align=center><strong>Orders statistics</strong></div><br /><br />";
		$res2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tx_extendedshop_orders', 'complete<>1'.$where, '', '', '');
		$num = $GLOBALS['TYPO3_DB']->sql_num_rows($res2);
		$content .= "Orders not yet completed: " . $num . "<br />";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('SUM(total) AS totale', 'tx_extendedshop_orders', 'complete<>1'.$where, '', '', '');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$content .= "Total for orders not yet completed: " . $this->priceFormat($row["totale"]) . "<br />";
		$content .= "<br /><hr /><br /><br />";
		$res2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tx_extendedshop_orders', 'complete=1'.$where, '', '', '');
		$num = $GLOBALS['TYPO3_DB']->sql_num_rows($res2);
		$content .= "Completed orders: " . $num . "<br />";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('SUM(total) AS totale', 'tx_extendedshop_orders', 'complete=1'.$where, '', '', '');
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$content .= "Total for completed orders: " . $this->priceFormat($row["totale"]) . "<br />";
		$content .= "<br /><hr /><br /><br />";
		return $content;
	}

	/**
	 * This function lists the orders
	 */
	function listOrders() {
		$delete = t3lib_div :: _GP("delete");
		$del_order = t3lib_div :: _GP("del_order");
		$complete = t3lib_div :: _GP("complete");
		$pageNumber = t3lib_div :: _GP("pageNumber");
		$orderId = t3lib_div :: _GP("orderId");
		$ordersubmit = t3lib_div :: _GP("ordersubmit");
		$order = t3lib_div :: _GP("order");

		if ($pageNumber == "")
			$pageNumber = 1;

		if ($delete) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_orders', 'uid=' . $del_order . ' AND deleted<>1', '', '', '');
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('tt_content', 'pid=' . $row["pid"]);
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('pages', 'uid=' . $row["pid"]);
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_extendedshop_orders', 'uid=' . $del_order);
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_extendedshop_rows', 'ordercode=' . $del_order);
		}
		if ($complete) {
			$updateFields["complete"] = "1";
			$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_orders', 'uid=' . $orderId, $updateFields);
		}
		if (isset ($ordersubmit)) {
			foreach ($order as $key => $value) {
				$updateField["status"] = $value["status"];
				$updateField["ordernote"] = $value["ordernote"];
				$updateField["shipping_tracking"] = $value["shipping_tracking"];
				$data = explode("-", $value["deliverydate"]);
				if ($data[0] > 0 && $data[0] < 13 && $data[1] > 0 && $data[1] < 32 && $data[2] > 1970 && $data[2] < 2200)
					$deliveryDate = mktime(0, 0, 0, $data[0], $data[1], $data[2]);
				else
					$deliveryDate = 0;
				$updateField["deliverydate"] = $deliveryDate;
				$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_orders', 'uid=' . $key, $updateField);
			}
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_orders', 'complete<>1 AND deleted<>1', 'date DESC', '', '');

		$num_orders = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		// NUMERO DI ORDINI PER PAGINA
		$num_orders_for_page = $this->num_orders_for_page;

		// Numero di pagine
		$num_pages = ceil($num_orders / $num_orders_for_page);

		$content = "<form name='ordermanagement' method='post'><table style='border-collapse: collapse;' width=100% padding=3>";

		$pageLink = "<a href='index.php?id=0&pageNumber=all&SET[function]=2'>show all</a> |";
		for ($i = 1; $i <= $num_pages; $i++) {
			if ($i == $pageNumber) {
				$pageLink .= " <b>" . $i . "</b>";
			} else {
				$pageLink .= " <a href='index.php?id=0&pageNumber=" . $i . "&SET[function]=2'>" . $i . "</a>";
			}
		}
		$content .= "<tr><td colspan='7' align='right'>" . $pageLink . "</td></tr>";
		$content .= "<tr><td colspan=7><hr /></td></tr>";
		$content .= "<tr><td><b>Number</b></td><td><b>Order date</b></td><td><b>Customer name</b></td><td><b>Shipping</b></td><td><b>Payment</b></td><td align=right><b>Total</b></td><td></td></tr>";
		$content .= "<tr><td colspan=7><hr /></td></tr>";

		// Scarta i primi ordini se sono in pagine successive
		if ($pageNumber != "all" && $pageNumber > 1) {
			for ($i = 0; $i < ($pageNumber -1) * $num_orders_for_page; $i++) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			}
		} else
			if ($pageNumber == "all") {
				$num_orders_for_page = $num_orders;
			}

		// Prepara la select per gli stati degli ordini
		$resStatus = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_status', 'sys_language_uid=0 AND hidden<>1 AND deleted<>1', 'priority ASC', '', '');
		$selectStatus = "<select name='order[###UID###][status]'>";
		while ($rowStatus = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resStatus)) {
			$selectStatus .= "<option value='" . $rowStatus["uid"] . "'###" . $rowStatus["uid"] . "###>" . $rowStatus["status"] . "</option>";
		}
		$selectStatus .= "</select>";

		//while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
		for ($i = 0; $i < $num_orders_for_page && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res); $i++) {
			$resUser = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid=' . $row['customer'], '', '', '');
			$rowUser = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resUser);

			$totalProducts = 0;
			$resProd = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_rows', 'ordercode=' . $row["uid"], '', '', '');
			while ($rowProd = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resProd)) {
				$totalProducts += $rowProd["price"] * $rowProd["quantity"];
			}
			/*$priceShipping = trim(substr($row["shipping"], strrpos($row["shipping"], "-") + 1));
			if ($priceShipping == "")
				$priceShipping = "0,00";*/
			$priceShipping = $row["shipping_cost"];
			/*$pricePayment = trim(substr($row["payment"], strrpos($row["payment"], "-") + 1));
			if ($pricePayment == "")
				$pricePayment = "0,00";
			*/
			$pricePayment = $row["payment_cost"];
			$totale = $this->priceFormat($totalProducts + $pricePayment + $priceShipping);
			$tot = $totalProducts + $pricePayment + $priceShipping;
			// Aggiorna il totale...
			if ($tot != $row['total']) {
				$update["total"] = $tot;
				$resUpdate = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_orders', 'uid=' . $row["uid"], $update);
			}

			// Gestione dello status
			$status = str_replace("###UID###", $row["uid"], $selectStatus);
			$status = str_replace("###" . $row["status"] . "###", " selected", $status);
			$status = preg_replace("/\#\#\#[0-9]\#\#\#/", '', $status);

			if ($i % 2 == 0)
				$color = "#CCCCCC";
			else
				$color = "#FFFFFF";

			$deliveryDate = $row["deliverydate"];
			if ($deliveryDate == 0 || $deliveryDate == "") {
				$deliveryDate = mktime(0, 0, 0, date("m", $row["date"]), date("d", $row["date"]) + $this->daysToDelivery, date("Y", $row["date"]));
			}
			if ($deliveryDate < time())
				$colorAlert = " style='background-color: #FF3333;'";
			else
				$colorAlert = "";

			$content .= "<tr style='background-color:" . $color . ";'><td><b>" . $row['code'] . "</b></td><td>" . date("d M Y - H:i:s", $row['date']) . "</td><td>" . $rowUser['name'] . "</td><td>" . $row["shipping"] . "</td><td>" . $row["payment"] . "</td><td align=right>" . $this->currency . " " . $totale . "</td>";
			if ($row["tx_wfinvoice_num_fatture"] == '') {
				$content .= "<td><a href='index.php?id=0&delete=true&del_order=" . $row['uid'] . "&SET[function]=2' title='Delete' onClick='return displayConfirm(\"Delete order?\");'><img src='../res/delete.gif' border=0></a> <a href='index.php?id=0&complete=true&orderId=" . $row['uid'] . "&SET[function]=2' title='Complete' onClick='return displayConfirm(\"Order completed?\");'><img src='../res/check.gif' border=0></a></td>";
			} else {
				$content .= "<td></td>";
			}
			$content .= "</tr>";
			$content .= "<tr style='background-color:" . $color . ";'><td></td><td colspan='2'>Order status: " . $status . "</td><td colspan='2'" . $colorAlert . ">Expected delivery date: <input type='text' size='12' maxlength='10' name='order[" . $row["uid"] . "][deliverydate]' value='" . date("m-d-Y", $deliveryDate) . "'> <i>(mm-dd-yyyy)</i></td><td align=right></td><td><a href='../../../../index.php?id=" . $row["pid"] . "' target=_blank><img src='../res/page.gif' border=0></a></td></tr>";
			$content .= "<tr style='background-color:" . $color . ";'><td></td><td colspan='4'>Shipping tracking code: <input type='text' size='42' maxlength='50' name='order[" . $row["uid"] . "][shipping_tracking]' value='" . $row['shipping_tracking'] . "'></td><td align=right></td><td></td></tr>";
			$content .= "<tr style='vertical-align: top; background-color:" . $color . ";'><td></td><td colspan='4'>Seller note:&nbsp;&nbsp; <textarea style='vertical-align:top;' cols='74' name='order[" . $row["uid"] . "][ordernote]'>" . $row["ordernote"] . "</textarea></td><td align=right></td><td></td></tr>";
			$content .= "<tr><td colspan=7><hr /></td></tr>";
		}
		$content .= "</table><br /><input type='submit' name='ordersubmit' value='Update'></form>";
		return $content;
	}

	/**
	 * This function lists the orders
	 */
	function listCompletedOrders() {
		$pageNumber = t3lib_div :: _GP("pageNumber");
		if ($pageNumber == "")
			$pageNumber = 1;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_orders', 'complete=1 AND deleted<>1', 'date DESC', '', '');

		$num_orders = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		// NUMERO DI ORDINI PER PAGINA
		$num_orders_for_page = $this->num_orders_for_page;

		// Numero di pagine
		$num_pages = ceil($num_orders / $num_orders_for_page);

		$content = "<table width=100% padding=3>";

		$pageLink = "<a href='index.php?id=0&pageNumber=all&SET[function]=2'>show all</a> |";
		for ($i = 1; $i <= $num_pages; $i++) {
			if ($i == $pageNumber) {
				$pageLink .= " <b>" . $i . "</b>";
			} else {
				$pageLink .= " <a href='index.php?id=0&pageNumber=" . $i . "&SET[function]=2'>" . $i . "</a>";
			}
		}
		$content .= "<tr><td colspan='7' align='right'>" . $pageLink . "</td></tr>";
		$content .= "<tr><td><b>Number</b></td><td><b>Order date</b></td><td><b>Customer name</b></td><td><b>Shipping</b></td><td><b>Payment</b></td><td align=right><b>Total</b></td></tr>";

		// Scarta i primi ordini se sono in pagine successive
		if ($pageNumber != "all" && $pageNumber > 1) {
			for ($i = 0; $i < ($pageNumber -1) * $num_orders_for_page; $i++) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			}
		} else
			if ($pageNumber == "all") {
				$num_orders_for_page = $num_orders;
			}

		//while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))		{
		for ($i = 0; $i < $num_orders_for_page && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res); $i++) {
			$resUser = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', 'uid=' . $row['customer'], '', '', '');
			$rowUser = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resUser);

			$totalProducts = 0;
			$resProd = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_rows', 'ordercode=' . $row["uid"], '', '', '');
			while ($rowProd = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resProd)) {
				$totalProducts += $rowProd["price"] * $rowProd["quantity"];
			}
			/*$priceShipping = trim(substr($row["shipping"], strrpos($row["shipping"], "-") + 1));
			if ($priceShipping == "")
				$priceShipping = "0,00";
			*/
			$priceShipping = $row["shipping_cost"];
			/*$pricePayment = trim(substr($row["payment"], strrpos($row["payment"], "-") + 1));
			if ($pricePayment == "")
				$pricePayment = "0,00";
			*/
			$pricePayment = $row["payment_cost"];
			$totale = $this->priceFormat($totalProducts + $pricePayment + $priceShipping);
			$tot = $totalProducts + $pricePayment + $priceShipping;
			// Aggiorna il totale...
			if ($tot != $row['total']) {
				$update["total"] = $tot;
				$resUpdate = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_orders', 'uid=' . $row["uid"], $update);
			}

			if ($i % 2 == 0)
				$color = "#CCCCCC";
			else
				$color = "#FFFFFF";
			$content .= "<tr style='background-color:" . $color . ";'><td><b>" . $row['code'] . "</b></td><td>" . date("d M Y - H:i:s", $row['date']) . "</td><td>" . $rowUser['name'] . "</td><td>" . $row["shipping"] . "</td><td>" . $row["payment"] . "</td><td align=right>" . $this->currency . " " . $totale . "</td></tr>";
		}
		$content .= "</table>";
		return $content;
	}
	
	
	
	/**
	 * This function displays products statistics
	 */
	function listProductStatistics() {
		$content = '';
		global $BACK_PATH;
		
		$content .= '<form action="index.php?id=0&SET[function]=4" method="post">';
		$content .= '<label form="month">Month</label>: ';
		$content .= '<select id="month" name="month">';
		$content .= '<option value=""></option>';
		for ($i=1; $i<13; $i++)	{
			$content .= '<option value="'.$i.'"'.($i==t3lib_div::_GP('month') ? ' selected="selected"' : '').'>'.date('F', mktime(0,0,0,$i,1,2000)).'</option>';
		}
		$content .= '</select>';
		
		$content .= ' - <label form="year">Year</label>: ';
		$content .= '<select id="year" name="year">';
		$content .= '<option value=""></option>';
		for ($i=date("Y", time()); $i>1999; $i--)
			$content .= '<option value="'.$i.'"'.($i==t3lib_div::_GP('year') ? ' selected="selected"' : '').'>'.$i.'</option>';
		$content .= '</select>';
		
		$content .= ' - <label for="completed">Only completed orders</label>: ';
		$content .= '<input type="radio" name="completed" value="1"'.(t3lib_div::_GP('completed')==1 ? ' checked="checked"' : '').' /> ';
		$content .= ' - <label for="completed">Only not completed orders</label>: ';
		$content .= '<input type="radio" name="completed" value="2"'.(t3lib_div::_GP('completed')==2 ? ' checked="checked"' : '').' /> ';
		$content .= ' - <label for="completed">All orders</label>: ';
		$content .= '<input type="radio" name="completed" value="3"'.((t3lib_div::_GP('completed')==3 || t3lib_div::_GP('completed')=='') ? ' checked="checked"' : '').' /> ';
		$content .= ' - <input type="submit" name="search" value="Go" />';
		$content .= '</form>';
		
		$orderBy = 'sell DESC';
		if (t3lib_div::_GP('sold')==1)	{
			if (t3lib_div::_GP('orderBy')!='' && t3lib_div::_GP('mode')!='')
				$orderBy = t3lib_div::_GP('orderBy').' '.t3lib_div::_GP('mode');
		}
		$link = '';
		$where = 'tx_extendedshop_rows.deleted=0 AND tx_extendedshop_orders.deleted=0';
		if (t3lib_div::testInt(t3lib_div::_GP('month')) && t3lib_div::testInt(t3lib_div::_GP('year')))	{
			$where .= ' AND tx_extendedshop_rows.crdate>='.mktime(0,0,0,t3lib_div::_GP('month'),1,t3lib_div::_GP('year')).' AND tx_extendedshop_rows.crdate<='.mktime(23,59,59,t3lib_div::_GP('month')+1,0,t3lib_div::_GP('year'));
			$link = '&month='.t3lib_div::_GP('month').'&year='.t3lib_div::_GP('year');
		}	elseif (t3lib_div::_GP('month')=='' && t3lib_div::testInt(t3lib_div::_GP('year')))	{
			$where .= ' AND tx_extendedshop_rows.crdate>='.mktime(0,0,0,1,1,t3lib_div::_GP('year')).' AND tx_extendedshop_rows.crdate<='.mktime(23,59,59,12,31,t3lib_div::_GP('year'));
			$link = '&month='.t3lib_div::_GP('month').'&year='.t3lib_div::_GP('year');
		}	elseif (t3lib_div::_GP('month')!='' && t3lib_div::_GP('year')=='')	{
			$content .= ' - <span style="color: red">You must specify a year</span>';
		}
		
		if (t3lib_div::_GP('completed')==1)
			$where .= ' AND tx_extendedshop_orders.complete=1';
		elseif (t3lib_div::_GP('completed')==2)
			$where .= ' AND tx_extendedshop_orders.complete=0';
		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_extendedshop_products.uid, tx_extendedshop_products.title, SUM(tx_extendedshop_rows.quantity) AS quantity, SUM(FORMAT(tx_extendedshop_rows.quantity*tx_extendedshop_rows.price, 2)) AS sell', '(tx_extendedshop_rows JOIN tx_extendedshop_orders ON tx_extendedshop_rows.ordercode = tx_extendedshop_orders.uid) LEFT OUTER JOIN tx_extendedshop_products ON tx_extendedshop_products.uid = tx_extendedshop_rows.productcode', $where, 'tx_extendedshop_products.uid', $orderBy);
		$sold_products = array();
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)>0)	{
			$content .= '<hr /><br /><h2>Sold products</h2>';
			$content .= '<table width="100%" padding="3">';
			$content .= '<tr><td><b>Product</b> <a href="index.php?id=0&sold=1&orderBy=title&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=1&orderBy=title&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></td><td><b>Quantity sold <a href="index.php?id=0&sold=1&orderBy=quantity&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=1&orderBy=quantity&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></b><td><b>Amount <a href="index.php?id=0&sold=1&orderBy=sell&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=1&orderBy=sell&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></b></td></td></tr>';
			$i = 0;
			$quantity = 0;
			$amount = 0;
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$sold_products[] = $row['uid'];
				$i++;
				if ($i % 2 == 0)
					$color = "#CCCCCC";
				else
					$color = "#FFFFFF";
				$content .= '<tr style="background-color: '.$color.'"><td><a style="text-decoration: underline;" href="index.php?id=0&productID='.$row['uid'].'&SET[function]=4">'.$row['title'].'</a></td><td align="right">'.$row['quantity'].'</td><td align="right">'.$row['sell'].'</td></tr>';
				$quantity += $row['quantity'];
				$amount += $row['sell'];
			}
			$content .= '<tr><td><b>Totals:</b></td><td align="right"><b>'.$quantity.'</b><td align="right"><b>'.number_format($amount,2).'</b></td></td></tr>';
			$content .= '</table>';
		}
		
		$orderBy = 'title ASC';
		if (t3lib_div::_GP('sold')==2)	{
			if (t3lib_div::_GP('orderBy')!='' && t3lib_div::_GP('mode')!='')
				$orderBy = t3lib_div::_GP('orderBy').' '.t3lib_div::_GP('mode');
		}
		if (!is_array($sold_products) || count($sold_products)==0)	{
			$sold_products = array();
			$sold_products[] = 0;
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_extendedshop_products.uid, tx_extendedshop_products.title, tx_extendedshop_products.price, tx_extendedshop_products.hidden', 'tx_extendedshop_products', 'tx_extendedshop_products.deleted=0 AND tx_extendedshop_products.uid NOT IN ('.implode(',', $sold_products).')', '', $orderBy);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)>0)	{
			$content .= '<hr /><br /><h2>Products not sold</h2>';
			$content .= '<table width="100%" padding="3">';
			$content .= '<tr><td><b>Product <a href="index.php?id=0&sold=2&orderBy=title&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=2&orderBy=title&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></b></td><td><b>Hidden? <a href="index.php?id=0&sold=2&orderBy=hidden&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=2&orderBy=hidden&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></b><td><b>Price <a href="index.php?id=0&sold=2&orderBy=price&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=2&orderBy=price&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></b></td></td></tr>';
			$i = 0;
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$sold_products[] = $row['uid'];
				$i++;
				if ($i % 2 == 0)
					$color = "#CCCCCC";
				else
					$color = "#FFFFFF";
				$content .= '<tr style="background-color: '.$color.'"><td>'.$row['title'].'</td><td>'.($row['hidden'] ? 'hidden' : '').'</td><td align="right">'.number_format($row['price'],2).'</td></tr>';
			}
			$content .= '</table>';
		}
		
		return $content;
	}
	
	
	
	
	/**
	 * This function displays single product statistics
	 */
	function detailsProductStatistics() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_extendedshop_products', 'uid="'.t3lib_div::_GP('productID').'"');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)!=1)	{
			$content = 'Error in the selected product...';
			$content .= '<br /><br /><a href="javascript:back();">Back to the list</a>';
			return $content;
		}
		$product = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		global $BACK_PATH;
		
		$content .= '<form action="index.php?id=0&SET[function]=4" method="post">';
		$content .= '<label form="month">Month</label>: ';
		$content .= '<select id="month" name="month">';
		$content .= '<option value=""></option>';
		for ($i=1; $i<13; $i++)	{
			$content .= '<option value="'.$i.'"'.($i==t3lib_div::_GP('month') ? ' selected="selected"' : '').'>'.date('F', mktime(0,0,0,$i,1,2000)).'</option>';
		}
		$content .= '</select>';
		
		$content .= ' - <label form="year">Year</label>: ';
		$content .= '<select id="year" name="year">';
		$content .= '<option value=""></option>';
		for ($i=date("Y", time()); $i>1999; $i--)
			$content .= '<option value="'.$i.'"'.($i==t3lib_div::_GP('year') ? ' selected="selected"' : '').'>'.$i.'</option>';
		$content .= '</select>';
		
		$content .= ' - <label for="completed">Only completed orders</label>: ';
		$content .= '<input type="radio" name="completed" value="1"'.(t3lib_div::_GP('completed')==1 ? ' checked="checked"' : '').' /> ';
		$content .= ' - <label for="completed">Only not completed orders</label>: ';
		$content .= '<input type="radio" name="completed" value="2"'.(t3lib_div::_GP('completed')==2 ? ' checked="checked"' : '').' /> ';
		$content .= ' - <label for="completed">All orders</label>: ';
		$content .= '<input type="radio" name="completed" value="3"'.((t3lib_div::_GP('completed')==3 || t3lib_div::_GP('completed')=='') ? ' checked="checked"' : '').' /> ';
		
		$content .= '<input type="hidden" name="productID" value="'.$product['uid'].'" />';
		$content .= ' <input type="submit" name="search" value="Go" />';
		$content .= '</form>';
		
		$orderBy = 'sell DESC';
		if (t3lib_div::_GP('sold')==1)	{
			if (t3lib_div::_GP('orderBy')!='' && t3lib_div::_GP('mode')!='')
				$orderBy = t3lib_div::_GP('orderBy').' '.t3lib_div::_GP('mode');
		}
		$link = '';
		$where1 = 'tx_extendedshop_rows.deleted=0';
		if (t3lib_div::testInt(t3lib_div::_GP('month')) && t3lib_div::testInt(t3lib_div::_GP('year')))	{
			$where1 .= ' AND tx_extendedshop_rows.crdate>='.mktime(0,0,0,t3lib_div::_GP('month'),1,t3lib_div::_GP('year')).' AND tx_extendedshop_rows.crdate<='.mktime(23,59,59,t3lib_div::_GP('month')+1,0,t3lib_div::_GP('year'));
			$link = '&month='.t3lib_div::_GP('month').'&year='.t3lib_div::_GP('year');
		}	elseif (t3lib_div::_GP('month')=='' && t3lib_div::testInt(t3lib_div::_GP('year')))	{
			$where1 .= ' AND tx_extendedshop_rows.crdate>='.mktime(0,0,0,1,1,t3lib_div::_GP('year')).' AND tx_extendedshop_rows.crdate<='.mktime(23,59,59,12,31,t3lib_div::_GP('year'));
			$link = '&month='.t3lib_div::_GP('month').'&year='.t3lib_div::_GP('year');
		}	elseif (t3lib_div::_GP('month')!='' && t3lib_div::_GP('year')=='')	{
			$content .= ' - <span style="color: red">You must specify a year</span>';
		}
		
		$where = $where1.' AND tx_extendedshop_orders.deleted=0';
		if (t3lib_div::_GP('completed')==1)
			$where .= ' AND tx_extendedshop_orders.complete=1';
		elseif (t3lib_div::_GP('completed')==2)
			$where .= ' AND tx_extendedshop_orders.complete=0';
		
		// Product details
		$content .= '<hr /><h1>'.$product['title'].'</h1>';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_extendedshop_products.uid, tx_extendedshop_products.title, SUM(tx_extendedshop_rows.quantity) AS quantity, SUM(FORMAT(tx_extendedshop_rows.quantity*tx_extendedshop_rows.price, 2)) AS sell', '(tx_extendedshop_rows JOIN tx_extendedshop_orders ON tx_extendedshop_rows.ordercode=tx_extendedshop_orders.uid) LEFT OUTER JOIN tx_extendedshop_products ON tx_extendedshop_rows.productcode = tx_extendedshop_products.uid', $where.' AND tx_extendedshop_products.uid="'.$product['uid'].'"', 'tx_extendedshop_products.uid', $orderBy);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)==1)	{
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$content .= 'Quantity: '.$row['quantity'].'<br />';
			$content .= 'Amount: '.$row['sell'];
		}	else	{
			$content .= 'Product not sold';
		}
		
		$where = $where1;
		if (t3lib_div::_GP('completed')==1)
			$where2 = ' AND tx_extendedshop_orders.complete=1';
		elseif (t3lib_div::_GP('completed')==2)
			$where2 = ' AND tx_extendedshop_orders.complete=0';
		else
			$where2 = '';
		$where .= ' AND tx_extendedshop_rows.ordercode IN (SELECT tx_extendedshop_orders.uid FROM tx_extendedshop_orders JOIN tx_extendedshop_rows ON tx_extendedshop_orders.uid=tx_extendedshop_rows.ordercode WHERE tx_extendedshop_rows.productcode="'.$product['uid'].'" AND tx_extendedshop_rows.deleted=0 AND tx_extendedshop_orders.deleted=0'.$where2.')';
		$where .= ' AND tx_extendedshop_rows.productcode!="'.$product['uid'].'"';
		
		// Related products
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_extendedshop_products.uid, tx_extendedshop_products.title, SUM(tx_extendedshop_rows.quantity) AS quantity, SUM(FORMAT(tx_extendedshop_rows.quantity*tx_extendedshop_rows.price, 2)) AS sell', 'tx_extendedshop_rows LEFT OUTER JOIN tx_extendedshop_products ON tx_extendedshop_products.uid = tx_extendedshop_rows.productcode', $where, 'tx_extendedshop_products.uid', $orderBy);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)>0)	{
			$content .= '<hr /><br /><h2>Related sold products</h2>';
			$content .= '<table width="100%" padding="3">';
			$content .= '<tr><td><b>Product</b> <a href="index.php?id=0&sold=1&productID='.$product['uid'].'&orderBy=title&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=1&productID='.$product['uid'].'&orderBy=title&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></td><td><b>Quantity sold <a href="index.php?id=0&sold=1&productID='.$product['uid'].'&orderBy=quantity&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=1&productID='.$product['uid'].'&orderBy=quantity&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></b><td><b>Amount <a href="index.php?id=0&sold=1&productID='.$product['uid'].'&orderBy=sell&mode=DESC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_down.gif" /></a> <a href="index.php?id=0&sold=1&productID='.$product['uid'].'&orderBy=sell&mode=ASC&SET[function]=4'.$link.'"><img src="'.$BACK_PATH.'sysext/t3skin/icons/gfx/button_up.gif" /></a></b></td></td></tr>';
			$i = 0;
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$i++;
				if ($i % 2 == 0)
					$color = "#CCCCCC";
				else
					$color = "#FFFFFF";
				$content .= '<tr style="background-color: '.$color.'"><td><a style="text-decoration: underline;" href="index.php?id=0&productID='.$row['uid'].'&SET[function]=4">'.$row['title'].'</a></td><td align="right">'.$row['quantity'].'</td><td align="right">'.$row['sell'].'</td></tr>';
			}
			$content .= '</table>';
		}
		
		$content .= '<br /><br /><a href="index.php?id=0&SET[function]=4">Back to the list</a>';
		
		return $content;
	}
	
	
	

	/**
	 * Formatting a price
	 */
	function priceFormat($double, $priceDecPoint = ",", $priceThousandPoint = ".") {
		return number_format($double, 2, $priceDecPoint, $priceThousandPoint);
	}

}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/mod1/index.php"]) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/extendedshop/mod1/index.php"]);
}

// Make instance:
$SOBE = t3lib_div :: makeInstance("tx_extendedshop_module1");
$SOBE->init();

// Include files?
foreach ($SOBE->include_once as $INC_FILE)
	include_once ($INC_FILE);

$SOBE->main();
$SOBE->printContent();
?>