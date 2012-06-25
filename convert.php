<?php

// Check for ogr2ogr existence
if( function_exists('exec') )
{
    // send test command to system
    exec('command -v ogr2ogr >&1 > /dev/null && echo "Found" || echo "Not Found"', $output);

    if( $output[0] == "Not Found" ) {
		die("\nogr2ogr not found. Make sure you've installed the GDAL/OGR binaries and followed the instructions to add their programs to your ~/.bash-profile.\n\n");
    }
}

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

function reduceUsingDp($elements, $tolerance) {
	foreach($elements as $coordinatesElement){
		
	    $coords = $coordinatesElement->nodeValue . "\n";
	    $threepoints = preg_split("/[\s]+/", $coords);
	    $points = array();
	
	    foreach($threepoints as $threepoint){
	        $pnt = explode(",", $threepoint);
		    if(isset($pnt[0]) && isset($pnt[1])){
		        $points[] = new GeoPoint($pnt[0], $pnt[1]);
			}
	    }

	    $reducer = new PolylineReducer($points);
	    $simple_line = $reducer->SimplerLine(0);

	    print "    Starting with " . count($simple_line) . " points.\n";

	    $reducer = new PolylineReducer($simple_line);
	    $simple_line = $reducer->SimplerLine($tolerance);
        
	    print "    Reduced to " . count($simple_line) . " points.\n";

	    $coordinatesElement->nodeValue = "";
	    foreach($simple_line as $point){
	        if($point->latitude != 0){
	            $coordinatesElement->nodeValue .= $point->latitude . ',' . $point->longitude . ' ';            
	        }
	    }
	}
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

$options = array();

$options["tempZip"] = sys_get_temp_dir() . '/CensusShapeConverter.zip';
$options["tempDir"] = sys_get_temp_dir() . '/CensusShapeConverter';

if(is_dir($options['tempDir'])){
    deleteDir($options['tempDir']);
}

// What are we getting?
$options["type"] = \cli\menu(array('Congressional Districts' => 'Congressional Districts', 'State' => 'States', 'County' => 'Counties', 'Local' => 'Localities'), null, 'Choose a Type');
$options["outputDirectory"] = \cli\prompt('Output directory', $default = getcwd() . "/CensusShapeOutput", $marker = ': ');
$options["tolerance"] = \cli\prompt('Douglas-Peuker Tolerance (Optional, 0.001 is a good place to start)', $default = '0', $marker = ': ');

if (!is_dir($options['outputDirectory'])) {
    mkdir($options['outputDirectory']);
}

if($options["type"] == 'Congressional Districts'){

  while(!isset($chosenStateCodes)){
		
  	// Prompt user for input
  	$stateCodeInput = strtoupper(\cli\prompt('Enter a two-letter state code, multiple codes separated with commas, or', $default = "all", $marker = ': '));
  	
  	// Setup Array
  	$chosenStateCodes = array();
  
  	// Single state
  	if(strlen($stateCodeInput) == 2){
  		if(isset($stateCodes[$stateCodeInput])){
  			$chosenStateCodes[] = $stateCodes[$stateCodeInput];
  		}
  	
  	// Comma separated	
  	}elseif(strpos($stateCodeInput,',')){
  		
  		$exploded = explode(',', $stateCodeInput);
  		
  		foreach($exploded as $single){
  			
  			$single = trim($single);
  			
  			if(isset($stateCodes[$single]) && !isset($chosenStateCodes[$stateCodes[$single]])){
  				$chosenStateCodes[] = $stateCodes[$single];
  			}
  			
  		}
  		
  	// All	
  	}elseif($stateCodeInput == "ALL"){
  		foreach($stateCodes as $key => $val){
  			$chosenStateCodes[] = $val;
  		}
  	}
  }
  
  	foreach($chosenStateCodes as $chosenStateCode){
	
		$options["remoteDirectory"] = 'geo/tiger/TIGER2010/CD/111/';
		$options["remoteFileName"] = 'tl_2010_' . $chosenStateCode . '_cd111';
	
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
		$dom = new DOMDocument;
		$dom->loadXML( $data );
  
		foreach( $dom->getElementsByTagName( 'Placemark' ) as $placemark ) {
  
		        $name = str_replace('/',' ',ltrim($placemark->getElementsByTagName('SimpleData')->item(3)->nodeValue, '0'));
		        $name = trim(str_replace('Congressional District', '', $name));
													
				if($options["tolerance"]){
					reduceUsingDp($placemark->getElementsByTagName('coordinates'), $options["tolerance"]);					
				}
				
				if (!is_dir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput) . "/")) {
        		    mkdir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput) . "/");
        		}
				
				if (!is_dir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput) . "/Lower/")) {
        		    mkdir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput) . "/Lower/");
        		}
  
		        $outputFileName = $options['outputDirectory'] . "/" . strtoupper($stateCodeInput) 
		                          . "/Lower/" . $name . ".kml";
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
 
  
  
  



}elseif($options["type"] == 'State'){
	
	while(!isset($chosenStateCodes)){
		
		// Prompt user for input
		$stateCodeInput = strtoupper(\cli\prompt('Enter a two-letter state code, multiple codes separated with commas, or', $default = "all", $marker = ': '));
		
		// Setup Array
		$chosenStateCodes = array();
	
		// Single state
		if(strlen($stateCodeInput) == 2){
			if(isset($stateCodes[$stateCodeInput])){
				$chosenStateCodes[] = $stateCodes[$stateCodeInput];
			}
		
		// Comma separated	
		}elseif(strpos($stateCodeInput,',')){
			
			$exploded = explode(',', $stateCodeInput);
			
			foreach($exploded as $single){
				
				$single = trim($single);
				
				if(isset($stateCodes[$single]) && !isset($chosenStateCodes[$stateCodes[$single]])){
					$chosenStateCodes[] = $stateCodes[$single];
				}
				
			}
			
		// All	
		}elseif($stateCodeInput == "ALL"){
			foreach($stateCodes as $key => $val){
				$chosenStateCodes[] = $val;
			}
		}
	}

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
		$dom = new DOMDocument;
		$dom->loadXML( $data );
    
		foreach( $dom->getElementsByTagName( 'Placemark' ) as $placemark ) {
    
		        $name = str_replace('/',' ',ltrim($placemark->getElementsByTagName('SimpleData')->item(5)->nodeValue, '0'));
													
				if($options["tolerance"]){
					reduceUsingDp($placemark->getElementsByTagName('coordinates'), $options["tolerance"]);					
				}
    
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

}elseif($options["type"] == "County" ||
		$options["type"] == "Local"){
	
	while(!isset($chosenStateCode)){
		
		// Prompt user for input
		$stateCodeInput = strtoupper(\cli\prompt('Enter a two-letter state code', $default = false, $marker = ': '));
			
		// Single state
		if(strlen($stateCodeInput) == 2){
			if(isset($stateCodes[$stateCodeInput])){
				$chosenStateCode = $stateCodes[$stateCodeInput];
			}
		}
		
	}
	
	if($options["type"] == "County"){
		$singular = "County";
		$plural = "Counties";
		$options["remoteDirectory"] = 'geo/tiger/TIGER2010/COUNTY/2010/';
		$options["remoteFileName"] = 'tl_2010_' . $chosenStateCode . '_county10';
		$nameIndex = 5;
	}else{
		$singular = "Locality";
		$plural = "Localities";
		$options["remoteDirectory"] = 'geo/tiger/TIGER2010/PLACE/2010/';
		$options["remoteFileName"] = 'tl_2010_' . $chosenStateCode . '_place10';
		$nameIndex = 4;
	}
	


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
	
	// List all counties/localities
	
	$countyMenu = array();
	
	$length = $dom->getElementsByTagName( 'Placemark' )->length;
	
	for( $i = 0; $i < $length; $i++) {
		
			$placemark = $dom->getElementsByTagName( 'Placemark' )->item($i);

	        $countyMenu[] = str_replace('/',' ',ltrim($placemark->getElementsByTagName('SimpleData')->item($nameIndex)->nodeValue, '0'));

	}
	
	asort($countyMenu);
	
	$countyMenu["all"] = "=== ALL ===";
	
	$countyIndex = \cli\menu($countyMenu, null, 'Choose a ' . $singular);
	
	if($countyIndex == "all"){
		
		foreach($dom->getElementsByTagName( 'Placemark' ) as $placemark){
			$placemarks[] = $placemark;
		}
		
	}else{
		$placemarks[] = $dom->getElementsByTagName( 'Placemark' )->item($countyIndex);		
	}
	
	foreach($placemarks as $placemark){
		
		$name = str_replace('/',' ',ltrim($placemark->getElementsByTagName('SimpleData')->item($nameIndex)->nodeValue, '0'));
		
		if (!is_dir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput))) {
		    mkdir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput));
		}
		
		if (!is_dir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput) . "/" . $plural . "/")) {
		    mkdir($options['outputDirectory'] . "/" . strtoupper($stateCodeInput) . "/" . $plural . "/");
		}
		
		if($options["tolerance"]){
			reduceUsingDp($placemark->getElementsByTagName('coordinates'), $options["tolerance"]);					
		}
		
	    $outputFileName = $options['outputDirectory'] . "/" . strtoupper($stateCodeInput) . "/" . $plural . "/" . $name . ".kml";
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
 
