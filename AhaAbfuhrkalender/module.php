<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/WebHookModule.php';

	class AhaAbfuhrkalender extends WebHookModule
	{
		private const URL = 'https://www.aha-region.de/abholtermine/abfuhrkalender/';

		public function __construct($InstanceID)
		{
			parent::__construct($InstanceID, 'ahaabfuhrkalender/' . $InstanceID);
		}

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString('City', '');
			$this->RegisterPropertyString('StreetsFirstLetter', '');
			$this->RegisterPropertyString('Street', '');
			$this->RegisterPropertyString('HouseNumber', '');
			$this->RegisterPropertyString('HouseNumberAddon', '');

			$this->RegisterPropertyBoolean('VariableTimestamp', true);
			$this->RegisterPropertyBoolean('VariableDays', true);
			$this->RegisterPropertyBoolean('SortVariables', false);
			$this->RegisterPropertyBoolean('EnableWebHook', false);

			$this->RegisterAttributeString('WasteTypes', '');
			$this->RegisterAttributeString('LocationID', '');
			$this->RegisterAttributeString('LocationName', '');
			$this->RegisterAttributeString('AccessToken', '');

			$this->CreateVariableProfileInteger(
				'AhaAbfuhrkalender.Days',
				'trash-can',
				'',
				'',
				0,
				2,
				1,
				[
					[0, 'Heute',  '', 0xEE0000],
					[1, 'Morgen', '', 0xFFAA00],
					[2, '%d Tage',  '', 0x58A906],
					[7, '%d Tage',  '', 0xC0BFBF]
				]
			);

			$this->RegisterTimer('UpdateTimer', 0, "AHA_Update($this->InstanceID);");
		}


		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$this->GetCollectionLocation();
			$this->Update();

			if ($this->ReadAttributeString('AccessToken') == ''){
				$this->RefreshAccessToken();
			}

		}

		public function RefreshAccessToken()
		{
			$token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
			$this->WriteAttributeString('AccessToken', $token);
			$this->UpdateFormField('Link', 'caption', "\t\tiCal Link: ".$this->GetIcalLink());
		}

		public function GetIcalLink(){
			$ip = gethostbyname(gethostname());
			$url = 'http://'.$ip.':3777'.$this->GetHook().'?token='.$this->ReadAttributeString('AccessToken');
			return $url;
		}

		public function Update()
		{
			$dates = $this->GetDates();

			$wasteTypes = array();

			foreach ($dates as $wasteType => $date){
				$wasteTypes[] = $wasteType;
				$days = $this->calulateDays($date[0]);

				$this->MaintainVariable($wasteType.'_Timestamp', $wasteType,VARIABLETYPE_INTEGER,  "~UnixTimestampDate", 1, $this->ReadPropertyBoolean('VariableTimestamp'));
				if ($this->ReadPropertyBoolean('VariableTimestamp'))
					$this->SetValue($wasteType.'_Timestamp', strtotime($date[0]));

				if ($this->ReadPropertyBoolean('SortVariables'))
					IPS_SetPosition($this->GetIDForIdent ($wasteType.'_Timestamp'), 100+$days);

				$this->MaintainVariable($wasteType.'_days', $wasteType, VARIABLETYPE_INTEGER, "AhaAbfuhrkalender.Days", 0, $this->ReadPropertyBoolean('VariableDays'));
				if ($this->ReadPropertyBoolean('VariableDays'))
					$this->SetValue($wasteType.'_days', $this->calulateDays($date[0]));	

				if ($this->ReadPropertyBoolean('SortVariables'))
					IPS_SetPosition($this->GetIDForIdent ($wasteType.'_days'), $days);			
			}

			$this->WriteAttributeString('WasteTypes', implode(", ", $wasteTypes));

			$this->UpdateTimerInterval('UpdateTimer', 0, rand(0,5), rand(0,59));
		}
		
		
		private function calulateDays($date){
			$date = new DateTime($date);
			$today = new DateTime('today');

			$difference = $date->diff($today);
			$days = $difference->days;
			return $days;
		}

		private function GetHook()
		{
			return '/hook/ahaabfuhrkalender/'.$this->InstanceID;
		}


		/**
		 * This function will be called by the hook control. Visibility should be protected!
		 */
		protected function ProcessHookData()
		{
			if (!$this->ReadPropertyBoolean('EnableWebHook')){
				http_response_code(404);
				die("File not found!");
			}
			
			if (!isset($_GET['token']) || $_GET['token'] != $this->ReadAttributeString('AccessToken')){
				header('HTTP/1.0 401 Unauthorized');
				die("Authorization required");
			}

			//header('Content-Type: text/calendar; charset=utf-8');
			//eader('Content-Disposition: attachment; filename="abfall.ics"');
			echo $this->GetICAL();

		}

		
		private function HttpRequest($url, $method = 'GET', $data = null)
		{

			if ($method == 'GET' && $data != null){
				$url = $url.'?'.http_build_query($data);
			}

			$curlHandle = curl_init($url);

			if ($method == 'POST' && $data != null){
				curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $data);
			}
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);

			$curlResponse = curl_exec($curlHandle);

			return $curlResponse;      
		}

		private function GetCities()
		{
			$html = $this->HttpRequest(self::URL);
		
			if ($html === false) {
				die("Fehler beim Abrufen der Seite:". self::URL);
			}

			// DOM-Dokument erstellen und laden
			libxml_use_internal_errors(true); // Fehler unterdrücken
			$dom = new DOMDocument();
			$dom->loadHTML($html);
			libxml_clear_errors();

			// XPath zur gezielten Suche
			$xpath = new DOMXPath($dom);

			// Das <select>-Element mit der ID "strasse" suchen
			$select = $xpath->query('//select[@id="gemeinde"]')->item(0);

			if (!$select) {
				die("Select-Element mit der ID 'gemeinde' nicht gefunden.");
			}

			// Alle <option>-Elemente extrahieren
			$options = $select->getElementsByTagName('option');

			// Optionen ausgeben
			$cities = array();

			foreach ($options as $option) {
				$value = $option->getAttribute('value');
				$text = trim($option->nodeValue);
				if ($value !== '') {
					$cities[] = array(
						'value' => $value,
						'caption' => $text
					);
				}
			}
			return $cities;
		}

		private function GetStreets( $city, $firstLetter)
		{
			$data = array(
				'gemeinde' => $city,
				'aktuelle_gemeinde' => $city,
				'von' => $firstLetter,
				'strasse' => '',
				'hausnr' => '',
				'hausnraddon' => ''
			);

			$html = $this->HttpRequest(self::URL, 'POST', $data);
		
			if ($html === false) {
				die("Fehler beim Abrufen der Seite:". self::URL);
			}

			// DOM-Dokument erstellen und laden
			libxml_use_internal_errors(true); // Fehler unterdrücken
			$dom = new DOMDocument();
			$dom->loadHTML($html);
			libxml_clear_errors();

			// XPath zur gezielten Suche
			$xpath = new DOMXPath($dom);

			// Das <select>-Element mit der ID "strasse" suchen
			$select = $xpath->query('//select[@id="strasse"]')->item(0);

			if (!$select) {
				die("Select-Element mit der ID 'strasse' nicht gefunden.");
			}

			// Alle <option>-Elemente extrahieren
			$options = $select->getElementsByTagName('option');

			// Optionen ausgeben
			$streets = array();

			foreach ($options as $option) {
				$value = $option->getAttribute('value');
				$text = trim($option->nodeValue);
				if ($value !== '') {
					$streets[] = array(
						'value' => $value,
						'caption' => $text
					);
				}
			}
			return $streets;
		}

		private function GetCollectionLocation()
		{

			$data = array(
				'gemeinde' => $this->ReadPropertyString('City'),
				'aktuelle_gemeinde' => $this->ReadPropertyString('City'),
				'von' => $this->ReadPropertyString('StreetsFirstLetter'),
				'strasse' => $this->ReadPropertyString('Street'),
				'hausnr' => $this->ReadPropertyString('HouseNumber'),
				'hausnraddon' => $this->ReadPropertyString('HouseNumberAddon'),
				'anzeigen' => 'Suchen'
			);

			$html = $this->HttpRequest(self::URL, 'POST', $data);
		
			if ($html === false) {
				die("Fehler beim Abrufen der Seite:". self::URL);
			}

			// DOM-Dokument erstellen und laden
			libxml_use_internal_errors(true); // Fehler unterdrücken
			$dom = new DOMDocument();
			$dom->loadHTML($html);
			libxml_clear_errors();

			// XPath zur gezielten Suche
			$xpath = new DOMXPath($dom);

			// Input-Feld suchen
			$inputNodeList = $xpath->query('//input[@name="ladeort"]');

			if ($inputNodeList->length > 0) {
				$input = $inputNodeList->item(0);
				$ladeort = trim($input->getAttribute('value'));

				// Elternknoten <p> suchen
				$parent = $input->parentNode;

				// Label innerhalb des Elternknotens suchen
				foreach ($parent->childNodes as $child) {
					if ($child->nodeName === 'label') {
						$labelText = trim($child->textContent);
						break;
					}
				}

				$this->WriteAttributeString('LocationID', $ladeort);
				$this->WriteAttributeString('LocationName', str_replace("Abholplatz:", "", $labelText) );
			} else {
				$this->WriteAttributeString('LocationID', '');
				$this->WriteAttributeString('LocationName', '');
			}

		}


		public function GetICAL()
		{
			$ladeort  = $this->ReadAttributeString('LocationID');

			if ($ladeort == ''){
				$ladeortID = explode('@', $streetID);
				$ladeort = $ladeortID[0].'-'.$houseNumber;
			}

			$data = array(
				'gemeinde' => $this->ReadPropertyString('City'),
				'strasse' => $this->ReadPropertyString('Street'),
				'hausnr' => $this->ReadPropertyString('HouseNumber'),
				'hausnraddon' => $this->ReadPropertyString('HouseNumberAddon'),
				'ladeort' => $ladeort,
				'ical' => 'ICAL Jahresübersicht'
			);

			$ical = $this->HttpRequest(self::URL, 'POST', $data);

			$ical = str_replace(' *', '', $ical);

			return $ical;
		}

		private function ParseICAL($ical)
		{
			$lines = explode("\n", $ical);
			$dates = array();

			foreach($lines as $line){
				$line = trim($line);

				$parts = explode(":", $line, 2);
				if ( count($parts) < 2) continue;

				$key = $parts[0];
				$value = $parts[1];

				//var_dump($parts);
				switch ($key){
					case 'BEGIN':
						if ($value == 'VEVENT'){
							$date = array('date' => '');
						}
						break;

					case 'END':
						if ($value == 'VEVENT'){
							$dates[] = $date;
						}
						break;

					case 'DTSTART;VALUE=DATE':
						$date['date'] = $value;
						break;

					case 'SUMMARY':
						$date['summary'] = trim($value);
						break;
				}
			}

			return $dates;
		}

		public function GetDates()
		{
			$ical = $this->GetICAL();
			$dates = $this->ParseICAL($ical);

			$wasteTypes = array();
			foreach ($dates as $date){
				$wasteTypes[$date['summary']][] = $date['date'];
			}

			return $wasteTypes;
		}

		public function UpdateForm( string $city, string $streetsFirstLetter)
		{
			$options = $this->GetStreets($city, $streetsFirstLetter);

			$this->UpdateFormField('Street', 'options', json_encode($options));
		}

		public function GetConfigurationForm()
		{
			$elements = array();
			$actions = array();
			$status = array();

			$elements[] = array(
				"type" => "Select",
				"name" => "City",
				"caption" => "City",
				"options" => $this->GetCities(),
				"onChange" => 'AHA_UpdateForm($id, $City, $StreetsFirstLetter);'
			);

			$elements[] = array(
				"type" => "Select",
				"name" => "StreetsFirstLetter",
				"caption" => "Streets first letter",
				"options" => $this->GetSelectAlphabet(),
				"onChange" => 'AHA_UpdateForm($id, $City, $StreetsFirstLetter);'
			);

			$elements[] = array(
				"type" => "Select",
				"name" => "Street",
				"caption" => "Street",
				"options" => $this->GetStreets($this->ReadPropertyString('City'), $this->ReadPropertyString('StreetsFirstLetter'))
			);

			$elements[] = array(
				"type" => "ValidationTextBox",
				"name" => "HouseNumber",
				"caption" => "House number"
			);

			$elements[] = array(
				"type" => "ValidationTextBox",
				"name" => "HouseNumberAddon",
				"caption" => "House number addon"
			);

			$elements[] = array(
				"type" => "Label",
				'italic' => true,
				"caption" => $this->GetLocationInfo()
			);

			$elements[] = array(
				"type" => "CheckBox",
				"name" => "VariableTimestamp",
				"caption" => "Create variables for date of next collection"
			);

			$elements[] = array(
				"type" => "CheckBox",
				"name" => "VariableDays",
				"caption" => "Create variables for days until the next collection"
			);

			$elements[] = array(
				"type" => "CheckBox",
				"name" => "SortVariables",
				"caption" => "Sort variables by date of next collection"
			);

			$elements[] = array(
				"type" => "CheckBox",
				"name" => "EnableWebHook",
				"caption" => "Provide dates as iCal-file via webhook (e.g. for WasteManagement Module)"
			);

			if ($this->ReadPropertyBoolean('EnableWebHook')){
				$elements[] = array(
					"type" => "Label",
					"name" => "Link",
					"italic" => true,
					"link" => true,
					"caption" => "\t\tiCal Link: ".$this->GetIcalLink()
				);
			}

			$actions[] = array(
				"type" => "Button",
				"caption" => "Update",
				"onClick" => 'AHA_Update($id);'
			);

			if ($this->ReadPropertyBoolean('EnableWebHook')){
				$actions[] = array(
					"type" => "Button",
					"caption" => "Refresh iCal access token",
					"onClick" => 'AHA_RefreshAccessToken($id);'
				);
			}

			$form['elements'] = $elements;
			$form['actions'] = $actions;
			$form['status'] = $status;

			return json_encode($form);
		}

		private function GetSelectAlphabet()
		{
			$alphabet = range('A', 'Z');
			$options = array();
			foreach ($alphabet as $letter){
				$options[] = array(
					'value' => $letter,
					'caption' => $letter
				);
			}
			return $options;
		}

		private function GetLocationInfo()
		{
			$text = "\n";
			if ($this->ReadAttributeString('LocationID') != ''){
				$text .= "Abfallarten:\t".$this->ReadAttributeString('WasteTypes')."\n";
				$text .= "Abholplatz: \t".$this->ReadAttributeString('LocationName')."\n";	
			} else {
				$text .= "Adresse konnte nicht gefunden werden!\n";
			}
			$text .= "\n";

			return $text;	
		}

		private function CreateVariableProfileInteger(string $name, string $icon, string $prefix, string $suffix, int $min, int $max, int $step, array $associations)
		{
			if (!IPS_VariableProfileExists($name)) {
				IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
			}

			IPS_SetVariableProfileIcon($name, $icon);
			IPS_SetVariableProfileText($name, $prefix, $suffix);
			IPS_SetVariableProfileValues($name, $min, $max, $step);

			// Alte Assoziationen löschen
			$existing = IPS_GetVariableProfile($name)['Associations'];
			foreach ($existing as $assoc) {
				IPS_SetVariableProfileAssociation($name, $assoc['Value'], '', '', -1);
			}

			// Neue Assoziationen setzen
			foreach ($associations as $assoc) {
				if (count($assoc) === 4) {
					list($value, $label, $icon, $color) = $assoc;
					IPS_SetVariableProfileAssociation($name, $value, $label, $icon, $color);
				}
			}
		}

		protected function UpdateTimerInterval($ident, $hour, $minute, $second)
		{
			$now = new DateTime();
			$target = new DateTime();
			$target->modify('+1 day');
			$target->setTime($hour, $minute, $second);
			$diff = $target->getTimestamp() - $now->getTimestamp();
			$interval = $diff * 1000;
			$this->SetTimerInterval($ident, $interval);
		}
	}