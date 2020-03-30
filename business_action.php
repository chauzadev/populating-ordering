<?php
require_once 'PopulateCSV.php';

class Business_Action
{
    public static function importBusinessForUpdateFromCSV($csvFileName)
    {

        //Mysql Connection
        $con = new mysqli('localhost','root','root','pronto');

        //Parse CSV
        $csvData = PopulateCSV::parseCSV($csvFileName, true);

        //Get All Cities
        $citiesWithNames = PopulateCSV::getCitieWithNames(PopulateCSV::getAllCities());

        $totalUpdated = 0;
        $totalFailed = 0;
        foreach($csvData as $index=>$businessData){
            $city = $businessData['city'];

            //check if needs to create city
            $cityNameWithFullState = PopulateCSV::getCityNameWithFullState($city);

            if( !isset($citiesWithNames[$cityNameWithFullState]) ){
                $citiesWithNames[$cityNameWithFullState] = PopulateCSV::createCity($cityNameWithFullState);

                if( !$citiesWithNames[$cityNameWithFullState] ){
                    PopulateCSV::output("<br>Failed to create city : " . $businessData['store_id'] . '  ' . $cityNameWithFullState);
                    $totalFailed++;
                    continue;
                }
            }

            //Get Lat Lng From ZipCode
            if( isset($businessData['lat']) && is_numeric($businessData['lat']) && isset($businessData['lng']) && is_numeric($businessData['lng']) ){
                $lat = $businessData['lat'];
                $lng = $businessData['lng'];
            }
            else{
                list($lat, $lng) = PopulateCSV::getLatLng($businessData['zipcode']);
            }
            

            //Create Business
            $post_params = [
                'name'                      => $businessData['name'],
                'address'                   => PopulateCSV::cleanText($businessData['address']),
                'address_notes'             => $businessData['address_notes'],
                'zipcode'                   => str_pad((string)$businessData['zipcode'], 5, "0", STR_PAD_LEFT),
                'phone'                     => PopulateCSV::cleanEmail(str_replace('-', '', $businessData['phone'])),
                'description'               => $businessData['description'],
                'about'                     => $businessData['description'],
                'slug'                      => PopulateCSV::cleanSlug($businessData['slug']),
                'email'                     => PopulateCSV::cleanEmail($businessData['email']),
                'header'                    => $businessData['banner_image'],
                'city_id'                   => $citiesWithNames[$cityNameWithFullState],
                'full_city_name'            => $cityNameWithFullState,
                'enabled'                   => strtolower($businessData['enabled']) == 'true' ? true : false,
                'schedule'                  => json_encode(PopulateCSV::getScheduleArray($businessData)),
                'minimum'                   => floatval($businessData['minimum']),
                'delivery_price'            => floatval($businessData['delivery_price']),
                'tax_type'                  => $businessData['tax_type'],
                'tax'                       => floatval($businessData['tax']),
                'service_fee'               => floatval($businessData['service_fee']),
                'owner_id'                  => empty($businessData['owner_id']) ? 1 : intval($businessData['owner_id']),
                
                'delivery_time'             => $businessData['delivery_time'],
                'pickup_time'               => $businessData['pickup_time'],
                'fixed_usage_fee'           => floatval($businessData['fixed_usage_fee']),
                'percentage_usage_fee'      => floatval($businessData['percentage_usage_fee']),
                'food'                      => $businessData['biz_type_food'] == 'TRUE' ? true : false,
                'alcohol'                   => $businessData['biz_type_alcohol'] == 'TRUE' ? true : false,
                'groceries'                 => $businessData['biz_type_groceries'] == 'TRUE' ? true : false,
                'laundry'                   => $businessData['biz_type_shopping'] == 'TRUE' ? true : false,
                'featured'                  => $businessData['featured'] == 'TRUE' ? true : false,
                'always_deliver'            => $businessData['always_deliver'] == 'TRUE' ? true : false,
                'timezone'                  => $businessData['timezone'],
                'currency'                  => $businessData['currency'],
                'lat'                       => floatval($lat),
                'lng'                       => floatval($lng),
                'business_id'               => intval($businessData['store_id']),
                'created_at'                => date('Y-m-d H:i:s'),
                'needs_update'              => 1
            ];

            if( PopulateCSV::updateMysqlQuery($con, 'business_import', $post_params, 'business_id = ' . intval($businessData['store_id'])) ){
                $totalUpdated++;
                PopulateCSV::output($index . ', ');
            }
            else{
                PopulateCSV::output('<br>Failed to update ' . $businessData['store_id'] . '<br>');
                $totalFailed++;
            }
        }
        PopulateCSV::output("<hr>Total Updated :" . $totalUpdated);
        PopulateCSV::output("<br>Total Failed : " . $totalFailed);
    }

    public static function updateBusinesses()
    {
        //Mysql Connection
        $con = new mysqli('localhost','root','root','pronto');

        $busineeses = [];
        $result = $con->query("SELECT * FROM business_import WHERE needs_update = 1");
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $busineeses[] = $row;
            }
        }

        PopulateCSV::output("Updating " . count($busineeses) . " Businesses Starting<hr>");

        $totalUpdated = 0;
        $totalFailed = 0;
        foreach( $busineeses as $businessData ){
            $businessData['slug'] .= uniqid();
            if( PopulateCSV::updateBusiness($businessData) != FALSE ){
                
                $con->query("UPDATE business_import SET needs_update = 0, slug = '" . $businessData['slug'] . "' WHERE id = " . $businessData['id']);
                $totalUpdated++;

                PopulateCSV::output("<span style='color:green'>Successfully updated business  " . $businessData['business_id'] . "</span><br>");
            }
            else{
                PopulateCSV::output("<span style='color:red'>Failed to updated business  " . $businessData['business_id'] . "</span><br>");
                $totalFailed++;
            }
        }

        PopulateCSV::output("Total " . $totalUpdated . " Businesses Updated<br>");
        PopulateCSV::output("Total " . $totalFailed . " Businesses Failed<br>");
    }
}



ob_start();
$start_time = microtime(true);

/**Import Business From CSV */
/*
$csvFiles = scandir('feeds/business/pending');
foreach( $csvFiles as $file ){
    if( strpos($file, 'csv') ){
        //PopulateCSV::importBusinessFromCSV('feeds/business/pending/' . $file);      
        Business_Action::importBusinessForUpdateFromCSV('feeds/business/pending/' . $file);
    }
}*/

/**Update Business */
Business_Action::updateBusinesses();

/**Populate Business To API */
//PopulateCSV::populateBusinessToAPI();

/**Remove Businesses */
//PopulateCSV::removeBusinesses();
echo "<hr>Exeuted within " . intval(microtime(true) - $start_time) . "seconds.";