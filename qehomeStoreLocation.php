<?php
require_once(__DIR__ . '/../public/includes/system.inc');

use MiniBC\core\connection\ConnectionManager;
use MiniBC\core\connection\MySQLConnection;


function crawl_page($url) {

    /** @var MySQLConnection $db */
    $db = ConnectionManager::getInstance('mysql');

    $data = file_get_contents('https://www.qehomelinens.com/storelocations.htm');
    $dom = new \domDocument;

    @$dom->loadHTML($data);

    $main = $dom->getElementById('main');
    $tables = $main->getElementsByTagName('table');

    $query = 'SELECT DISTINCT store_name
              FROM ebridge_store_inventory';

    $results = $db->query($query, array());
    $storeNames = array_map('array_shift', $results);

    foreach ($tables as $table) {
        
        $values = $table->getElementsByTagName('p');

        // get store name
        $name = trim($values->item(1)->textContent);

        // phone 
        $phone = trim($values->item(2)->textContent);

         // get store address
        $addressString = trim($values->item(3)->textContent);

        // for website mistakes
        if (strpos($name, '(') !== false) {
            $phoneHolder = $name;
            $name = $phone;
            $phone = $phoneHolder;
        }

         if ( $name == 'Cottonwood Mall') {
            $addressString = trim($values->item(2)->textContent);
            $phone = '(604) 705-1115';
        }

        if ( $name == 'Devonshire Mall') {
            $addressString = '3100 Howard Ave. N8X 3Y8';
            $phone = '(519) 967-9848';
        }

        $name = getCorrectName($name);

        $addressArray = explode(' ', $addressString);

        $zip = implode(' ', array_slice($addressArray, -2, 2));
        
        $address = implode( " ", array_slice($addressArray, 0, count($addressArray)-2) );
        

        // lat and lng
        $latLng = getLnt($zip);
        
        $lat = $latLng['lat'];
        $lng = $latLng['lng'];

        $storeInfo = array(
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'zip'  => $zip,
            'lat' => $lat,
            'lng' => $lng
        );

        //save in database 
        saveStoreInfo($storeInfo , $storeNames);
      
        
    }
}

function getCorrectname($name) {


    if ( $name == 'HighStreet Mall') {
        $name = 'High Street';
       
    }

    if ( $name == 'Carlingwood Shopping Centre') {
        $name == 'Carlingwood Mall';
    }

    if ( strpos($name, 'Prairie') !== false) {
        $name = 'Grande Prairie';
       
    }

    if ( strpos($name, 'Capilano') !== false) {
        $name = 'Capilano Mall';
       
    }

    if ( strpos($name, 'Crossiron') !== false) {
        $name = 'CrossIron Mills';
       
    }

    return $name;
}

function saveStoreInfo($storeData = array(), $storeNames = array()) {

    if (!empty($storeData)  && !empty($storeNames)) {

        $savedStoreNames = $storeNames;
        $storeName = $storeData['name'];

        $foundSavedName = false;
        foreach($savedStoreNames  as $savedStoreName) {
            if (strpos($storeName, $savedStoreName) !== false) {

                if ( $storeData['name'] == 'Fairview Park Mall') {
                    $storeData['name'] = 'Fairview Park';
                } else {
                    $storeData['name'] = $savedStoreName;
                }

                $foundSavedName = true;
                break;
            } 
        }

        if( !$foundSavedName) {
            echo "failed to find saved store name for " . $storeName . "\r\n";
        }
        /** @var MySQLConnection $db */
        $db = ConnectionManager::getInstance('mysql');

        $result = $db->insert('qe_store_info', $storeData);

        if ( $result <= 0 || $result === false) {
            echo "failed to add " . $storeName . " to database \r\n";
        } else {
            echo "saved " . $storeData['name'] . " successful \r\n";
        }
    }
    
}

function getLnt($zip){
    
    $url = "http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($zip)."&sensor=false";
    $result_string = file_get_contents($url);
    $result = json_decode($result_string, true);
    return $result['results'][0]['geometry']['location'];
}

crawl_page("https://www.qehomelinens.com/storelocations.htm", 1);

exit;