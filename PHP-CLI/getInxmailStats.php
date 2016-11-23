<?php
/**
 * getInxmailStats.php
 * 
 * @author Niels Vornholt <nv@holzmann-medien.de>
 * @copyright 2016 Holzmann Medien GmbH & Co. KG
 * @description CLI-Anwendung, um Statistiken zu einzelenen Newsletterversaenden in eine von SiteFusion importierbare Form zu bringen.
 * @version 1.0 Siehe SVN
 * @todo Encoding-Issue von URLs loesen, siehe http://iisissues/#8347
 *       Logging / Error Handling / Monitoring via Log Files bzw. PRTG
 * 
 */

require_once 'getInxmailStats_functions.php';
require_once 'inxmail_api/Apiimpl/Loader.php';
Inx_Apiimpl_Loader::registerAutoload();
$inx_server = "https://------.com/-----";
$inx_user = "-------";
$inx_pass = "-------";

/*
Argument 1: Inxmail Name der Mailingliste 
Argument 2: Inxmail ID des Mailings
Argument 3: SiteFusion Newsletter pk
 */
$listName = addslashes($argv[1];)
$mailingID = (int)$argv[2];
$destinationPath = (int)$argv[3];

/* Sind alle Argumente übergeben worden? */
if(!$listName || !$mailingID || !$destinationPath) {
	print("Error: Missing Arguments. Usage: getInxmailstats.php <listName> <mailingID> <destinationPath>\r\n");
	die();
}

/* Inxmail-Session starten */
try {
	$oSession = Inx_Api_Session::createRemoteSession ($inx_server, $inx_user, $inx_pass );
	print("Success: Inxmail-Session created.\r\n");
}
catch (Inx_Api_LoginException $x) {
	print("Error: Inxmail-Session could not be created.\r\n");
	echo $x->getTraceAsString();
}

/* ID der übergebenen Mailingliste holen */
$listID = getMailingListID($listName);

/* Statistikreports als ZIP-Dateien von Inxmail abholen */
$aStatZipFiles = getStatfiles($listID, $mailingID, $oSession);

/* ZIP-Dateien entpacken und darin befindliche CSV-Dateien ablegen */
unzipFiles($aStatZipFiles, dirname(__FILE__).'/CSV/');

/* CSV zu XML umwandeln */
convertCSVtoXML(dirname(__FILE__).'/CSV/', $destinationPath);


/* Inxmail-Session schliessen */
$oSession->close();
?>