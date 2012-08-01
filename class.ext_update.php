<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005-2006 Mauro Lorenzutti <mauro.lorenzutti@webformat.com>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
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
 * Class for updating the db
 * This class is needed to convert customers from tt_address to fe_users
 *
 * @author	 Mauro Lorenzutti <mauro.lorenzutti@webformat.com>
 */
class ext_update  {

	var $debug = 0;

	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string		HTML
	 */
	function main()	{

		$tables = array ('static_countries', 'static_country_zones', 'static_languages', 'static_currencies');

		$content = '';

		$content.= '<br />Convert customers from tt_address to fe_users.';
		$content.= '<br />This conversion is needed while upgrading from versions 0.x and 1.x to 2.x.';

		if(t3lib_div::_GP('convert')) {
			$content .= $this->convert();
		} else {

			$content .= '</form>';
			$content .= '<form action="'.htmlspecialchars(t3lib_div::linkThisScript()).'" method="post">';
			$content .= '<br /><br />';
			$content .= '<span style="color: red; font-size: 20px; font-weight: bold;">Pay attention to this conversion tool!</span>';
			$content .= '<br /><span style="color: red;">Try it in a development site, before upgrading your production site.</span>';
			$content .= '<br /><br />';
            
            $content .= '<strong>FE-usergroup to attach to customers:</strong> ';
            $content .= $this->getFEGroups();
			$content .= '<br /><br />';
			
			$content .= '<strong>Customers from tt_address:</strong><br /><br />';
			$content .= $this->listCustomers();
			$content .= '<br /><br />';
			
			$content .= '<input type="submit" name="convert" value="Convert" />';
			$content .= '</form>';
		}

		return $content;
	}


	function access() {
		return true;
	}

	function convert()	{
		$content = '';
		$content .= '<br />Customers converted:';
		$content .= '<br /><br />';
		
		$content .= t3lib_div::view_array(t3lib_div::_GP('address'));
		
		if (trim(t3lib_div::_GP('newgroup'))!="")	{
			// Create new usergroup
			$new['tstamp'] = time();
			$new['title'] = trim(t3lib_div::_GP('newgroup'));
			$new['description'] = 'Created by the Webformat Shop Upgrading Tool';
			$new['pid'] = 0;
			if ($this->debug)	{
				$content .= 'New FE-group: '.t3lib_div::view_array($new)." <br /><br />";
				$fegrp = -1;
			}	else	{
				$GLOBALS['TYPO3_DB']->exec_INSERTquery('fe_groups', $new);
				$fegrp = $GLOBALS['TYPO3_DB']->sql_insert_id();
			}
			unset($new);
		}	else	{
			$fegrp = t3lib_div::_GP('group');
		}
		
		foreach (t3lib_div::_GP('address') as $uidAddress)	{
			$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid, tstamp, hidden AS disable, name, title, email, phone AS telephone, mobile AS tx_extendedshop_mobile, www, address, company, city, zip, country, image, fax, tx_extendedshop_vatcode, tx_extendedshop_state, tx_extendedshop_private, tx_extendedshop_login AS username, tx_extendedshop_password AS password', 'tt_address', 'uid="'.$uidAddress.'"');
			$row = $row[0];
			$row['usergroup'] = $fegrp;
			$row['crdate'] = time();
			
			if ($this->debug)	{
				$content .= 'New FE user: '.t3lib_div::view_array($row);
			}	else	{
				// Search for a user with the same username
				$resUsr = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'fe_users', 'username="'.$row["username"].'" AND disable=0 AND deleted=0');
				if ($GLOBALS['TYPO3_DB']->sql_num_rows($resUsr)>0)	{
					// Found - it uses the already existing FE user
					$rowUsr = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resUsr);
					$feusr = $rowUsr['uid'];
				}	else	{
					// Not found - It creates a new FE user
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('fe_users', $row);
					$feusr = $GLOBALS['TYPO3_DB']->sql_insert_id();
				}
				
				if ($feusr!="")	{
					// Delete old tt_address user
					$del['deleted'] = '1';
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_address', 'uid="'.$uidAddress.'"', $del);
					
					// Update Orders
					$up['customer'] = $feusr;
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_extendedshop_orders', 'customer="'.$uidAddress.'"', $up);
				}
				
				unset ($feusr, $del, $up);
			}
		}
		
		$content .= '<br /><br />';
		$content .= '<br /><a href="javascript:history.back();">Back</a>';
		return $content;
	}
	
	
	function getFEGroups()	{
		$content = '<select id="fegroups" name="group">';
		$content .= '<option value=""></option>';
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid, title', 'fe_groups', 'deleted!=1', '', 'title ASC');
		//$content = t3lib_div::view_array($rows);
		foreach ($rows as $row)	{
			$content .= '<option value="'.$row['uid'].'">'.$row['title'].'</option>';
		}
		
		$content .= '</select>';
		$content .= ' - Create a new one: <input type="text" name="newgroup" value="" />';
		return $content;
	}
	
	function listCustomers()	{
		$content = '<table width="100%" cellpadding="2" cellspacing="2" border="1px">';
		$content .= '<tr><th>uid</th><th>Customer</th><th>Orders</th><th>Select</th></tr>';
		
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('A.uid AS uid, A.name AS name, O.code AS code, O.uid AS orderuid', 'tx_extendedshop_orders AS O JOIN tt_address AS A ON O.customer=A.uid', 'A.deleted!=1 AND O.deleted!=1', '', 'A.name ASC');
		if (is_array($rows))
			foreach ($rows as $row)
				$content .= '<tr><td>'.$row['uid'].'</td><td>'.$row['name'].'</td><td>'.$row['code'].' ('.$row['orderuid'].')</td><td><input name="address[]" value="'.$row['uid'].'" type="checkbox" checked="true" /></td></tr>';
		
		$content .= '</table>';
		return $content;
	}

}

// Include extension?
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/extendedshop/class.ext_update.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/extendedshop/class.ext_update.php']);
}


?>
