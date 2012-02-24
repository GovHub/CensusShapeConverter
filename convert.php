<?php

$stateCodes = array("AL"=>"01","AK"=>"02","AZ"=>"04","AR"=>"05","CA"=>"06","CO"=>"08","CT"=>"09","DE"=>"10","DC"=>"11","FL"=>"12","GA"=>"13","HI"=>"15","ID"=>"16","IL"=>"17","IN"=>"18","IA"=>"19","KS"=>"20","KY"=>"21","LA"=>"22","ME"=>"23","MD"=>"24","MA"=>"25","MI"=>"26","MN"=>"27","MS"=>"28","MO"=>"29","MT"=>"30","NE"=>"31","NV"=>"32","NH"=>"33","NJ"=>"34","NM"=>"35","NY"=>"36","NC"=>"37","ND"=>"38","OH"=>"39","OK"=>"40","OR"=>"41","PA"=>"42","RI"=>"44","SC"=>"45","SD"=>"46","TN"=>"47","TX"=>"48","UT"=>"49","VT"=>"50","VA"=>"51","WA"=>"53","WV"=>"54","WI"=>"55","WY"=>"56","AS"=>"60","GU"=>"66","MP"=>"69","PR"=>"72","VI"=>"78");

class GeoPoint {
    public $latitude;
    public $longitude;

    public function __construct($lat,$lng)
    {
        $this->latitude = (float)$lat;
        $this->longitude = (float)$lng;
    }
};

function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
        throw new InvalidArgumentException('$dirPath must be a directory');
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            self::deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

if (php_sapi_name() != 'cli') {
	die('Must run from command line');
}

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('log_errors', 0);
ini_set('html_errors', 0);

require 'lib/cli/cli.php';
require 'DouglasPeuker.php';
\cli\register_autoload();

$menu = array(
	'State' => 'States',
	'County' => 'Counties',
	'Local' => 'Localities'
);

$options = array();

$options["tempZip"] = sys_get_temp_dir() . '/CensusShapeConverter.zip';
$options["tempDir"] = sys_get_temp_dir() . '/CensusShapeConverter';

if(is_dir($options['tempDir'])){
    deleteDir($options['tempDir']);
}

$options["type"] = \cli\menu($menu, null, 'Choose a Type');

if($options["type"] == 'State'){
	
	while(!isset($chosenStateCodes)){
		$stateCodeInput = \cli\prompt('Enter a two-letter state code or', $default = "all", $marker = ': ');
		if(isset($stateCodes[$stateCodeInput])){
			$chosenStateCodes = array($stateCodes[$stateCodeInput]);
		}elseif($stateCodeInput == "all"){
			$chosenStateCodes = array();
			foreach($stateCodes as $key => $val){
				$chosenStateCodes[] = $val;
			}
		}
	}

}

$options["maxPoints"] = \cli\prompt('Enter max number of coordinate pairs', $default = '2500', $marker = ': ');
$options["outputDirectory"] = \cli\prompt('Output directory', $default = getcwd() . "/CensusShapeOutput", $marker = ': ');

if (!is_dir($options['outputDirectory'])) {
    mkdir($options['outputDirectory']);
}

if($options["type"] == 'State'){
	
   	foreach($chosenStateCodes as $chosenStateCode){
	
		$options["remoteDirectory"] = 'geo/tiger/TIGER2010/STATE/2010/';
		$options["remoteFileName"] = 'tl_2010_' . $chosenStateCode . '_state10';
	
		///////// MAKE CONNECTION, DOWNLOAD FILE ////////////
		$conn_id = ftp_connect('ftp2.census.gov');
		$login_result = ftp_login($conn_id, 'anonymous', '');
		if (!ftp_get($conn_id, $options["tempZip"], $options["remoteDirectory"] . $options["remoteFileName"] . ".zip", FTP_BINARY)) {
			\cli\err('Error contacting US Census FTP server.');
		}
		ftp_close($conn_id);
    
		//Unzip the file
		exec('unzip ' . $options['tempZip'] . ' -d ' . $options['tempDir']);
    
		//Convert to KML	
		exec('ogr2ogr -f "KML" ' . $options['tempDir'] . '/output' . $chosenStateCode . '.kml ' . $options['tempDir'] . "/" . $options["remoteFileName"] . ".shp");
    
		$fh = fopen($options['tempDir'] . "/output" . $chosenStateCode . ".kml", 'r'); 
		$data = fread($fh, filesize($options['tempDir'] . "/output" . $chosenStateCode . ".kml")); 
		fclose($fh);
    
		$arrXml = array();
		$dom    = new DOMDocument;
		$dom->loadXML( $data );
    
		foreach( $dom->getElementsByTagName( 'Placemark' ) as $placemark ) {
    
		        $name = str_replace('/',' ',ltrim($placemark->getElementsByTagName('SimpleData')->item(5)->nodeValue, '0'));
    
		        $outputFileName = $options['outputDirectory'] . "/" . $name . ".kml";
		        $fh = fopen($outputFileName, 'w+') or die("can't open file");
    
		        fwrite($fh, 
					'<?xml version="1.0" encoding="utf-8" ?>' .
					'<kml xmlns="http://www.opengis.net/kml/2.2">' .
					'<Document><Folder><name>' . $name . '</name>' . 
			     	$dom->saveXML( $placemark ) 
					. '</Folder></Document></kml>'   
				);
    
		        fclose($fh);
    
		        \cli\line("%C%5 Wrote $name %5%n");
    
		}
	}    

}
        