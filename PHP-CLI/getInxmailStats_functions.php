<?php
/**
 * getInxmailStats_functions.php
 * 
 * @author Niels Vornholt <nv@holzmann-medien.de>
 * @copyright 2016 Holzmann Medien GmbH & Co. KG
 * @description CLI-Anwendung, um Statistiken zu einzelenen Newsletterversaenden in eine von SiteFusion importierbare Form zu bringen.
 * @version 1.0 Siehe SVN
 * @package getInxmailStats
 * 
 */



/**
 * Ermittelt anhand des Namens einer Mailinliste in Inxmail die zugehörige ID.
 * @param  String $listName Name der Liste
 * @return int+	           	ID der Liste
 */
function getMailingListID($listName){
	global $oSession;

	// Inxmail-Schnittstelle abfragen
	$oListContext = $oSession->getListContextManager();
	$oListContext = $oListContext->findByName($listName);
	$mailingListID = $oListContext->getID();
	if($mailingListID != NULL) {
		print "Success: Inxmail Mailinglist-ID found: $mailingListID\r\n";
		return $mailingListID;
	}
	else {
		return false;
	}
}

/**
 * Holt die Reports "MailingDetailOverview" und "ClickReactionLink" vom Inxmailserver ab.
 * Die Reports kommen als zip-Datei an.
 *
 * @param int+ $listId die ID der Inxmail-Mailingliste
 * @param int+ $mailingId die ID des Inxmail-Mailing, von dem die Statistiken geholt werden sollen
 * @param Object $oSession Die Inxmail-Session
 * @return bool success true bei Erfolg, false bei Fehler.
 */
function getStatfiles($listId, $mailingId, $oSession) {
	
	// Report 	ClickReactionLink holen
	$oReportRequest = new Inx_Api_Reporting_ReportRequest(
		"ClickReactionLink",
		Inx_Api_Reporting_ReportRequest::OUTPUT_FORMAT_CSV,
		"de",
		'Europe/Berlin'
	);
	$oReportRequest -> putParameter("listid", $listId);
	$oReportRequest -> putParameter("mailingid", $mailingId);

	$oReportTicket = $oSession->getReportEngine()->generate($oReportRequest, false) ;
	$oDownloadableResult = $oReportTicket->fetchDownloadableResult();

	while ( $oDownloadableResult == null ) {
		// Waiting for the report to finish ...
		sleep(3);
		$oDownloadableResult = $oReportTicket->fetchDownloadableResult();
	}
	download($oDownloadableResult->getInputStream(), "ClickReactionLink.zip");
	if( $oReportTicket != null ){
		$oReportTicket->close();
	}

	// Report 	MailingDetailOverview holen
	$oReportRequest = new Inx_Api_Reporting_ReportRequest(
		"MailingDetailOverview",
		Inx_Api_Reporting_ReportRequest::OUTPUT_FORMAT_CSV,
		"de",
		'Europe/Berlin'
	);
	$oReportRequest -> putParameter("listid", $listId);
	$oReportRequest -> putParameter("mailingid", $mailingId);

	$oReportTicket = $oSession->getReportEngine()->generate($oReportRequest, false) ;
	$oDownloadableResult = $oReportTicket->fetchDownloadableResult();

	while ( $oDownloadableResult == null ) {
		// Waiting for the report to finish ...
		sleep(3);
		$oDownloadableResult = $oReportTicket->fetchDownloadableResult();
	}
	download($oDownloadableResult->getInputStream(), "MailingDetailOverview.zip");
	if( $oReportTicket != null ){
		$oReportTicket->close();
	}

	unset($oReportRequest);
	unset($oReportTicket);
	unset($oDownloadableResult);

	return 	array(
				dirname(__FILE__).'/ClickReactionLink.zip',
				dirname(__FILE__).'/MailingDetailOverview.zip'
			);
}


/**
 * Entpackt ZIP-Dateien und legt die entpackten Dateien in ein Verzeichnis.
 * ZIP-Dateien werden anschliessend gelöscht.
 * 
 * @param Array $zipFiles numerisches Array mit absoluten Pfaden zu den zu entpackenden ZIP-Files.
 * @param String $destination Absoluter Pfad zu dem Verzeichnis, in welches ALLE Dateien aus den ZIP-Files gelegt werden sollen.
 * @param bool $overwrite sollen bereits extistierende Dateien NICHT überschrieben werden, auf false setzen.
 */
function unzipFiles($zipFiles, $destination, $overwrite=true) {
	$zip = new ZipArchive;
	foreach ($zipFiles as $zipFile) {
		$res = $zip->open($zipFile);
		if ($res === TRUE) {
			$zip->extractTo($destination);
			$zip->close();
			print "Success: $zipFile extracted to $destination\r\n";
		} else {
			print "Error: I couldn't open $zipFile\r\n";
		}
		if(@unlink($zipFile)) {
			print "Success: $zipFile deleted\r\n";
		}
		else {
			print "Error: Could not delete $zipFile\r\n";
		}
	}
	unset($zip);
}

/**
 * Schreibt die von Inxmail gelieferte Datei ins lokale Dateisystem.
 * Bereits existierende Dateien werden überschrieben.
 * 
 * @param  Object $inputStream Inxmail InputStream Objekt
 * @param  string $sFileName   Dateiname der zu speichernden Datei.
 * @return bool				   true bei Erfolg, false bei Fehler.
 */
function download($inputStream, $sFileName) {
	if(!is_object($inputStream)) {
		print("Error: Could not store ZIP File.\r\n");
		return false;
	}
	$sFileName = dirname(__FILE__).'/'.$sFileName;
	$handle = fopen($sFileName, 'w+b');
	if(is_resource($handle)) {
		while (($ch = $inputStream->read()) != -1) {
			fwrite($handle, $ch);
		}
	}
	else {
		print "Error: I couldn't write $sFileName.\r\n";
	}
	$inputStream->close();
	print("Success: $sFileName written to Filesystem.\r\n");
	fclose($handle);
	return true;
}

/**
 * erstellt aus den Inxmail-CSV-Dateien mehrere XML-Dateien, die dem eCircle-/teradata-/meme-Standard 
 * entsprechen. Die Dateien werden im angegebenen Ordner abgelegt.
 *
 * @param string $source pfad zum Quellverzeichnis der CSV-Dateien.
 * @param  String $destination absoluter Pfad zum Zielordner. Wird angelegt, falls nicht vorhanden.
 * @return [type]              [description]
 */
function convertCSVtoXML($source, $destination) {
	global $mailingID;
	$linkCSV = 'mailing_details_links.csv';
	$statCSV = 'mailing_details.csv';
	$metaInfo = getBasicInfos($source);
	$bounces = getBounces($mailingID);
	
	createLinkCountXML($source.$linkCSV, $destination, $metaInfo);
	createReportStatsXML($source.$statCSV, $destination, $metaInfo);
	createReportBounces($source.$statCSV, $destination, $metaInfo, $bounces);
	createReportBounceList($source.$statCSV, $destination, $metaInfo, $bounces);

}


/**
 * erstellt das XML für die Linkzählung.
 *
 * @param  String $csvFile Pfad zur LinkCount-CSV-Datei von Inxmail
 * @param  String $xmlOutput Pfad zu den zu erstellenden XML-Dateien
 * @param  Array $metaInfo Array mit Metainformationen zur versendeten Mailing
 * @return [type]          [description]
 */
function createLinkCountXML($csvFile, $xmlOutput, $metaInfo){
	$outputXMLFile = $xmlOutput.'campaign_report_linktracking.xml';

	/* Mapping-Tabelle. Nur Felder, die hier gelistet sind, werden ins XML übernommen. Key: Inxmail-Feld, Value: Feld im Ziel-XML */
	$fieldMapping = array(
			'Link' => 'link-url',
			'Link_alias' => 'link-desc',
			'Alle_Klicks' => 'total-clicks',
			'Eindeutige_Klicks' => 'unique-clicks',
			'CTR' => 'percentage',
			);

	$inputFile  = fopen($csvFile, 'rt');
	$headers = fgetcsv($inputFile,0,';');

	// XML Document erstellen
	$xmlDoc  = new DomDocument('1.0', 'utf-8');
	$xmlDoc->formatOutput = true;

	$root = $xmlDoc->createElement('summary-report-linktracking');
	$root = $xmlDoc->appendChild($root);
	
	// MetaInfos extrahieren und einfügen
	$metaInfo_Info = $metaInfo->getElementsByTagName('info')->item(0);
	$metaInfo_Info = $xmlDoc->importNode($metaInfo_Info, true);
	$root->appendChild($metaInfo_Info);
	$metaInfo_Report = $metaInfo->getElementsByTagName('send-report')->item(0);
	$metaInfo_Report = $xmlDoc->importNode($metaInfo_Report, true);
	$root->appendChild($metaInfo_Report);

	// Link-Tracking erzeugen
	$linkTracking = $xmlDoc->createElement('link-tracking');
	$linkTracking = $root->appendChild($linkTracking);

	// Loop through each row creating a <link> node with the correct data
	$j = 0;
	if(is_resource($inputFile)) {
		while (($row = fgetcsv($inputFile,0,';')) !== FALSE) {
			$container = $xmlDoc->createElement('link');

			// Link-ID erfinden, da sie Inxmail leider nicht mitliefert
			$linkid =  $j;
			$child = $xmlDoc->createElement('link-id');
			$child = $container->appendChild($child);
			$value = $xmlDoc->createTextNode($linkid);
			$value = $child->appendChild($value);

			foreach($headers as $i => $header) {
				$header = preg_replace(	array("#\s#", "#&.*;#"), 
										array("_", ""),
										htmlentities($header)
									);

				// Nur, wenn das Feld im MappingArray definiert ist, wird des ins XML übernommen.
				if(array_key_exists($header, $fieldMapping)) {
					if($header == 'Eindeutige_Klicks') {
						// Einrücken
						$child = $xmlDoc->createElement($fieldMapping[$header]);
						$child = $container->appendChild($child);

						$child1 = $xmlDoc->createElement('number');
						$child1 = $child->appendChild($child1);

						if($row[$i]=='' OR $row[$i]=='0,00%') { $calcValue = "0"; } else { $calcValue = $row[$i]; }
						$value = $xmlDoc->createTextNode($calcValue);
						$value = $child1->appendChild($value);
					}
					elseif ($header == 'CTR') {
						// CTR wird Kind von 'number'
						$child2 = $xmlDoc->createElement($fieldMapping[$header]);
						$child2 = $child->appendChild($child2);
						if($row[$i]=='' OR $row[$i]=='0,00%') { $calcValue = "0"; } else { $calcValue = $row[$i]; }
						$value = $xmlDoc->createTextNode(str_replace("%", "", $calcValue));
						$value = $child2->appendChild($value);
						
					}
					else {
						// Alles andere kommt unter den <link>-Knoten
						$child = $xmlDoc->createElement($fieldMapping[$header]);
						$child = $container->appendChild($child);
						$value = $xmlDoc->createTextNode($row[$i]);
						$value = $child->appendChild($value);
					}
				}
			}

			$linkTracking->appendChild($container);
			$j++;
		}
	}
	else {
		print "Error: I couldn't open $inputFile. Does it exist?\r\n";
	}

	$strxml = $xmlDoc->saveXML();
	if(!is_dir($xmlOutput)){
		mkdir($xmlOutput);
	}
	$handle = fopen($outputXMLFile, "w+");
	if(fwrite($handle, $strxml)){
		print "Success: $outputXMLFile written.\r\n";
	}
	else {
		print "Error: $outputXMLFile could not be written.\r\n";
	}
	fclose($handle);
	fclose($inputFile);
}

/**
 * erstellt das XML für die Report-Statistiken
 * 
 * @param  String $csvFile Pfad zur LinkCount-CSV-Datei von Inxmail
 * @param  String $xmlOutput Pfad zu den zu erstellenden XML-Dateien
 * @param  Array $metaInfo Array mit Metainformationen zur versendeten Mailing
 * @return [type]          [description]
 */
function createReportStatsXML($csvFile, $xmlOutput, $metaInfo){
	$outputXMLFile = $xmlOutput.'campaign_report_stats.xml';

	/* Mapping-Tabelle. Nur Felder, die hier gelistet sind, werden ins XML übernommen. Key: Inxmail-Feld, Value: Feld im Ziel-XML */
	$fieldMapping = array(
			'Anzahl_der_ffnenden_Empfnger_' => 'number-readers',
			'ffnungsrate' => 'percentage',
			'Summe_aller_Klicks' => 'total-clicks',
			'Anzahl_der_klickenden_Empfnger_' => 'unique-clicks',
			'Klickrate' => 'unique-clicks-percentage',
			'CTOR-Rate' => 'net-click-rate-percentage'
			);

	/* Welche Werte kommen in welche Knoten? */
	$field2node = array(
		'user-tracking' => array('number-readers', 'number-nonreaders', 'number-nonreaders-percentage'),
		'link-tracking' => array('total-clicks', 'unique-clicks', 'net-click-rate')
		);

	// Werte aus CSV auslesen
	$inputFile  = fopen($csvFile, 'rt');
	if(is_resource($inputFile)) {
		while (($row = fgetcsv($inputFile,0,';')) !== FALSE) {
			$inxFieldName = preg_replace(array("#\s#", "#&.*?;#", "#".uchr('x2074')."#"), array("_", "", ""), htmlentities($row[0]));
			
			if(array_key_exists($inxFieldName, $fieldMapping)) {
				$statInfo[$fieldMapping[$inxFieldName]] = $row[1];
			}
		}
	}
	else {
		print "Error: I couldn't open $inputFile. Does it exist?\r\n";
	}

	// XML Document erstellen
	$xmlDoc  = new DomDocument('1.0', 'utf-8');
	$xmlDoc->formatOutput = true;

	$root = $xmlDoc->createElement('summary-report-stats');
	$root = $xmlDoc->appendChild($root);
	
	// MetaInfos extrahieren und einfügen
	$metaInfo_Info = $metaInfo->getElementsByTagName('info')->item(0);
	$metaInfo_Info = $xmlDoc->importNode($metaInfo_Info, true);
	$root->appendChild($metaInfo_Info);
	$metaInfo_Report = $metaInfo->getElementsByTagName('send-report')->item(0);
	$metaInfo_Report = $xmlDoc->importNode($metaInfo_Report, true);
	$root->appendChild($metaInfo_Report);

	// In den Inxmail-Reports nicht vorhandene, aber benötigte Werte nachberechnen
	$recipientsNode = $xmlDoc->getElementsByTagName('number-recipients');
	//$recipients = $recipientsNode[0]->nodeValue;
	$recipients = $recipientsNode->item(0);
	$recipients = $recipients->nodeValue;
	$statInfo['number-nonreaders'] = $recipients - $statInfo['number-readers'];
	$statInfo['number-nonreaders-percentage'] = 100 * (int)$statInfo['number-readers'] / (int)$recipients;
	$statInfo['net-click-rate'] = $statInfo['unique-clicks'];

	// XML mit Knoten befüllen
	$container = $xmlDoc->createElement('progress-report');
	$container = $root->appendChild($container);

	$userTracking = $xmlDoc->createElement('user-tracking');
	$userTracking = $container->appendChild($userTracking);

	$linkTracking = $xmlDoc->createElement('link-tracking');
	$linkTracking = $container->appendChild($linkTracking);

	// Werte in user-tracking füllen
	foreach ($field2node['user-tracking'] as $field) {
		$node = strstr($field, 'percentage') ? $xmlDoc->createElement('percentage') : $xmlDoc->createElement($field);
		$value = $xmlDoc->createTextNode($statInfo[$field]);
		$value = $node->appendChild($value);
		$node = $userTracking->appendChild($node);
	}

	// Werte in link-tracking füllen
	$node = $xmlDoc->createElement('summary');
	$summary = $linkTracking->appendChild($node);
	foreach ($field2node['link-tracking'] as $field) {
		$node = $xmlDoc->createElement($field);
		$node = $summary->appendChild($node);
		$numberNode = $xmlDoc->createElement('number');
		$value = $xmlDoc->createTextNode($statInfo[$field]);
		$value = $numberNode->appendChild($value);
		$node->appendChild($numberNode);
		if(array_key_exists($field.'-percentage', $statInfo)) {
			$percentNode = $xmlDoc->createElement('percentage');
			$value = $xmlDoc->createTextNode($statInfo[$field.'-percentage']);
			$node->appendChild($percentNode);
		}
	}

	$strxml = $xmlDoc->saveXML();
	if(!is_dir($xmlOutput)){
		mkdir($xmlOutput);
	}
	$handle = fopen($outputXMLFile, "w+");
	if(fwrite($handle, $strxml)){
		print "Success: $outputXMLFile written.\r\n";
	}
	else {
		print "Error: $outputXMLFile could not be written.\r\n";
	}
	fclose($handle);
	fclose($inputFile);
}


/**
 * erstellt das XML für die Bounce-Email-Adressen
 * 
 * @param  String $csvFile Pfad zur LinkCount-CSV-Datei von Inxmail
 * @param  String $xmlOutput Pfad zu den zu erstellenden XML-Dateien
 * @param  Array $metaInfo Array mit Metainformationen zur versendeten Mailing
 * @param  Array $bounces Array mit bounce-email-adressen und weiteren Infos
 * @see  getBounces()
 * @return [type]          [description]
 */
function createReportBounceList($csvFile, $xmlOutput, $metaInfo, $bounces){
	$outputXMLFile = $xmlOutput.'campaign_report_bouncelist.xml';

	// XML Document erstellen
	$xmlDoc  = new DomDocument('1.0', 'utf-8');
	$xmlDoc->formatOutput = true;

	$root = $xmlDoc->createElement('summary-report-bouncelist');
	$root = $xmlDoc->appendChild($root);
	
	// MetaInfos extrahieren und einfügen
	$metaInfo_Info = $metaInfo->getElementsByTagName('info')->item(0);
	$metaInfo_Info = $xmlDoc->importNode($metaInfo_Info, true);
	$root->appendChild($metaInfo_Info);
	$metaInfo_Report = $metaInfo->getElementsByTagName('send-report')->item(0);
	$metaInfo_Report = $xmlDoc->importNode($metaInfo_Report, true);
	$root->appendChild($metaInfo_Report);

	// Bounces in XML einfügen
	$bounceNode = $xmlDoc->createElement('bounces');
	$bounceNode = $root->appendChild($bounceNode);
	foreach ($bounces['recipients'] as $bounce) {
		$node = $xmlDoc->createElement('recipient');
		$node = $bounceNode->appendChild($node);
		foreach ($bounce as $key => $bounceValue) {
			$valNode = $xmlDoc->createElement($key);
			if($key == 'category'){
				$value = $xmlDoc->createTextNode($bounces['mapping'][$bounceValue]);
			}
			else {
				$value = $xmlDoc->createTextNode($bounceValue);
			}
			$value = $valNode->appendChild($value);
			$valNode = $node->appendChild($valNode);
		}
	}


	$strxml = $xmlDoc->saveXML();
	if(!is_dir($xmlOutput)){
		mkdir($xmlOutput);
	}
	$handle = fopen($outputXMLFile, "w+");
	if(fwrite($handle, $strxml)){
		print "Success: $outputXMLFile written.\r\n";
	}
	else {
		print "Error: $outputXMLFile could not be written.\r\n";
	}
	fclose($handle);
}


/**
 * erstellt das XML für die Bounce-Übersicht
 * 
 * @param  String $csvFile Pfad zur LinkCount-CSV-Datei von Inxmail
 * @param  String $xmlOutput Pfad zu den zu erstellenden XML-Dateien
 * @param  Array $metaInfo Array mit Metainformationen zur versendeten Mailing
 * @param  Array $bounces Array mit bounce-email-adressen und weiteren Infos
 * @return [type]          [description]
 */
function createReportBounces($csvFile, $xmlOutput, $metaInfo, $bounces){
	$outputXMLFile = $xmlOutput.'campaign_report_bounces.xml';

	// XML Document erstellen
	$xmlDoc  = new DomDocument('1.0', 'utf-8');
	$xmlDoc->formatOutput = true;

	$root = $xmlDoc->createElement('summary-report-bounces');
	$root = $xmlDoc->appendChild($root);
	
	// MetaInfos extrahieren und einfügen
	$metaInfo_Info = $metaInfo->getElementsByTagName('info')->item(0);
	$metaInfo_Info = $xmlDoc->importNode($metaInfo_Info, true);
	$root->appendChild($metaInfo_Info);
	$metaInfo_Report = $metaInfo->getElementsByTagName('send-report')->item(0);
	$metaInfo_Report = $xmlDoc->importNode($metaInfo_Report, true);
	$root->appendChild($metaInfo_Report);

	// Magic happens here.
	$bounceNode = $xmlDoc->createElement('progress-report');
	$bounceNode = $root->appendChild($bounceNode);

	// Zählarray initialisieren
	foreach ($bounces['mapping'] as $key) {
		$bounceCategoryCount[$key] = 0;
	}

	// Anzahl in den einzelnen Kategorien zählen
	foreach ($bounces['recipients'] as $recipient) {
		$bounceCategoryCount[$bounces['mapping'][$recipient['category']]] = $bounceCategoryCount[$bounces['mapping'][$recipient['category']]] + 1;
	}

	// XML schreiben
	foreach($bounceCategoryCount as $category => $amount){
		$node = $xmlDoc->createElement('bouncing');
		$node = $bounceNode->appendChild($node);
		$numNode = $xmlDoc->createElement('number');
		$numNode = $node->appendChild($numNode);
		$value = $xmlDoc->createTextNode($amount);
		$value = $numNode->appendChild($value);
		$catNode = $xmlDoc->createElement('category');
		$catNode = $node->appendChild($catNode);
		$value = $xmlDoc->createTextNode($category);
		$value = $catNode->appendChild($value);
	}

	$strxml = $xmlDoc->saveXML();
	if(!is_dir($xmlOutput)){
		mkdir($xmlOutput);
	}
	$handle = fopen($outputXMLFile, "w+");
	if(fwrite($handle, $strxml)){
		print "Success: $outputXMLFile written.\r\n";
	}
	else {
		print "Error: $outputXMLFile could not be written.\r\n";
	}
	fclose($handle);
}

/**
 * Stellt Basisinformationen über den Newsletter zur Verfügung, die in fast allen Report-XMLs gebraucht werden.
 * 
 * @param string $source Pfad zu den CSV-Dateien von Inxmail
 * @return array Assoziatives Array mit Metainformationen
 */
function getBasicInfos($source){
	$CSVFile = $source.'mailing_clickreaction_link_metadata.csv';
	global $mailingID;

	/* Mapping-Tabelle. Nur Felder, die hier gelistet sind, werden ins XML übernommen. Key: Inxmail-Feld, Value: Feld im Ziel-XML */
	$fieldMapping = array(
			'Betreff' => 'subject',
			'Mailingname' => 'campaign-title',
			'Metadata.NettoRecipients' => 'number-recipients-afterbounces',
			'Anzahl Empfänger (netto / brutto)' => 'number-recipients',
			'Versandbeginn' => 'send-date',
			);

	// Werte aus CSV auslesen
	$inputFile  = fopen($CSVFile, 'rt');
	if(is_resource($inputFile)) {
		while (($row = fgetcsv($inputFile,0,';')) !== FALSE) {
			if(array_key_exists($row[0], $fieldMapping)) {
				if($fieldMapping[$row[0]] == 'send-date') {
					// Datum in passendes Format umwandeln
					$metaInfo[$fieldMapping[$row[0]]] = date_format(date_create_from_format('d.m.Y H:i:s', $row[1]), 'c');
				}
				else {
					$metaInfo[$fieldMapping[$row[0]]] = $row[1];
				}
			}
		}
		fclose($inputFile);
	}
	else {
		print "Error: I couldn't open $inputFile. Does it exist?\r\n";
	}

	// XML erzeugen
	$xmlDoc  = new DomDocument('1.0', 'utf-8');
	$xmlDoc->formatOutput = true;

	$root = $xmlDoc->createElement('meta-infos');
	$root = $xmlDoc->appendChild($root);

	// <info>-Knoten erzeugen
	$meta = $xmlDoc->createElement('info');
	$meta = $root->appendChild($meta);
	$title = $xmlDoc->createElement('campaign-title');
	$title = $meta->appendChild($title);
	$value = $xmlDoc->createTextNode($metaInfo['campaign-title']);
	$value = $title->appendChild($value);
	$title = $xmlDoc->createElement('campaign-no');
	$title = $meta->appendChild($title);
	$value = $xmlDoc->createTextNode($mailingID);
	$value = $title->appendChild($value);

	//<send-report>-Knoten erzeugen
	$meta = $xmlDoc->createElement('send-report');
	$meta = $root->appendChild($meta);
	foreach ($metaInfo as $key => $value) {
		if($key != 'campaign-title') {
			$node = $xmlDoc->createElement($key);
			$node = $meta->appendChild($node);
			$value = $xmlDoc->createTextNode($value);
			$value = $node->appendChild($value);
		}
	}

	$xmlDoc->saveXML();
	print "Success: Basic infos for all reports extracted.\r\n";
	return $xmlDoc;
}


/**
 * Holt die Bounces eines bestimmten Mailings
 *
 * @param int+ $mailingID die ID des Mailings
 * @return array mehrdimensionales Array mit E-Mail-Adresse, Bounce-Kategorie, Bounce-Zeitpunkt
 */

function getBounces($mailingID){
	global $oSession;

	// Übersetzung der Bounce-Kategorien in lesbaren Text
	$bounceMapping = array(
		0 => 'Hard_Bounce',
		1 => 'Soft_Bounce',
		2 => 'Unknown_Bounce',
		3 => 'Auto_Responder_Bounce',
		4 => 'Spam_Bounce'
		);
	
	// Inxmail-Schnittstelle abfragen (fluent query)
	$oRecipientContext = $oSession->createRecipientContext();
	$meta = $oRecipientContext->getMetaData();
	$aAttrs = array($meta->getEmailAttribute());
	$aMailingIds = array($mailingID);
	$oBounceQuery = $oSession->getBounceManager()->createQuery($oRecipientContext, $aAttrs);
	$oBOResultSet = $oBounceQuery->mailingIds($aMailingIds)->executeQuery();

	if(is_object($oBOResultSet)) {
		// Bounces loopen und return array zusammenbauen
		foreach($oBOResultSet as $oBounce){
			$bounces['recipients'][] = array(
				'email' => $oBounce->getMatchedEmailAddress(),
				'category' => $oBounce->getCategory(),
				'date' => $oBounce->getReceptionDate()
			);
		}
		$bounces['mapping'] = $bounceMapping;
		print("Success: Fetched Bounces for mailing $mailingID. \r\n");
	}
	else {
		$bounces[] = array();
		print("Error: Bounces for mailing $mailingID could not be feteched.\r\n" );
	}
	$oRecipientContext->close();
	$oBOResultSet->close();

	return $bounces;
}


/**
 * Hilfsfunktion. 
 * wie chr(), allerdings mit Unicode Support.
 * 
 * @param  string $codes Unicode (Hex), kommasepariert oder einzeln.
 * @return String Unicode-Darstellung des Zeichens
 */
function uchr($codes) {
	if (is_scalar($codes)) $codes= func_get_args();
	$str= '';
	foreach ($codes as $code) $str.= html_entity_decode('&#'.$code.';',ENT_NOQUOTES,'UTF-8');
	return $str;
}

?>