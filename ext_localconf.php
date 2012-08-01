<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");
t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_extendedshop_products=1
');
t3lib_extMgm::addUserTSConfig('
    options.saveDocNew.tx_extendedshop_vat=1
');

  ## Extending TypoScript from static template uid=43 to set up userdefined tag:
t3lib_extMgm::addTypoScript($_EXTKEY,"editorcfg","
	tt_content.CSS_editor.ch.tx_extendedshop_pi1 = < plugin.tx_extendedshop_pi1.CSS_editor
",43);
t3lib_extMgm::addTypoScript($_EXTKEY,"editorcfg","
	tt_content.CSS_editor.ch.tx_extendedshop_pi2 = < plugin.tx_extendedshop_pi2.CSS_editor
",43);


t3lib_extMgm::addPItoST43($_EXTKEY,"pi1/class.tx_extendedshop_pi1.php","_pi1","list_type",0);
t3lib_extMgm::addPItoST43($_EXTKEY,"pi2/class.tx_extendedshop_pi2.php","_pi2","list_type",0);


t3lib_extMgm::addTypoScript($_EXTKEY,"setup","
	tt_content.shortcut.20.0.conf.tx_extendedshop_products = < plugin.".t3lib_extMgm::getCN($_EXTKEY)."_pi1
	tt_content.shortcut.20.0.conf.tx_extendedshop_products.CMD = singleView
",43);

t3lib_extMgm::addPageTSConfig('
    # ***************************************************************************************
    # CONFIGURATION of RTE in table "tx_extendedshop_products", field "description"
    # ***************************************************************************************
RTE.config.tx_extendedshop_products.description {
  hidePStyleItems = H1, H4, H5, H6
  proc.exitHTMLparser_db=1
  proc.exitHTMLparser_db {
    keepNonMatchedTags=1
    tags.font.allowedAttribs= color
    tags.font.rmTagIfNoAttrib = 1
    tags.font.nesting = global
  }
}
');
?>
