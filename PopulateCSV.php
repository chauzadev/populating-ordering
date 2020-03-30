<?php
class PopulateCSV{
    /**
     * Ordering.co api key
     */
    public static $apiKey = 'YX5MAaAfwivM6zaVXYhcOznYzNQwrzaQlx2ykXsnlKZvHAn6Zw5fCgsahm8OPp-3c';

    /**
     * States Abbreviation
     */
    public static $states = array(
        'AL'=>'Alabama',
        'AK'=>'Alaska',
        'AZ'=>'Arizona',
        'AR'=>'Arkansas',
        'CA'=>'California',
        'CO'=>'Colorado',
        'CT'=>'Connecticut',
        'DE'=>'Delaware',
        'DC'=>'District of Columbia',
        'FL'=>'Florida',
        'GA'=>'Georgia',
        'HI'=>'Hawaii',
        'ID'=>'Idaho',
        'IL'=>'Illinois',
        'IN'=>'Indiana',
        'IA'=>'Iowa',
        'KS'=>'Kansas',
        'KY'=>'Kentucky',
        'LA'=>'Louisiana',
        'ME'=>'Maine',
        'MD'=>'Maryland',
        'MA'=>'Massachusetts',
        'MI'=>'Michigan',
        'MN'=>'Minnesota',
        'MS'=>'Mississippi',
        'MO'=>'Missouri',
        'MT'=>'Montana',
        'NE'=>'Nebraska',
        'NV'=>'Nevada',
        'NH'=>'New Hampshire',
        'NJ'=>'New Jersey',
        'NM'=>'New Mexico',
        'NY'=>'New York',
        'NC'=>'North Carolina',
        'ND'=>'North Dakota',
        'OH'=>'Ohio',
        'OK'=>'Oklahoma',
        'OR'=>'Oregon',
        'PA'=>'Pennsylvania',
        'RI'=>'Rhode Island',
        'SC'=>'South Carolina',
        'SD'=>'South Dakota',
        'TN'=>'Tennessee',
        'TX'=>'Texas',
        'UT'=>'Utah',
        'VT'=>'Vermont',
        'VA'=>'Virginia',
        'WA'=>'Washington',
        'WV'=>'West Virginia',
        'WI'=>'Wisconsin',
        'WY'=>'Wyoming',
    );

    /**
     * Get All Cities From API
     */
    public static function getAllCities()
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/countries/1/cities");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		return json_decode($output)->result;
		curl_close($ch);
    }

    /**
     * Parse CSV File
     */
    public static function parseCSV($fileName, $isBusiness = false)
    {
        $row = 1;
        $csvData = [];
        if (($handle = fopen($fileName, "r")) !== FALSE) {
            while (($data = fgetcsv($handle)) !== FALSE) {
                $csvData[] = $data;
            }
            fclose($handle);
        }

        $fields = $csvData[0];

        if( $isBusiness === true )
            $fields[0] = 'name';

        $prettyData = [];
        for( $i = 1; $i < count($csvData); $i++ )
        {
            $row = [];
            for( $j = 0; $j < count($csvData[$i]); $j++ ){
                $row[trim(strtolower($fields[$j]))] = $csvData[$i][$j];
            }
            $prettyData[] = $row;
        }
        return $prettyData;
    }

    /**
     * Output CSV File
     */
    public static function outputCSV($fileName, $data)
    {
        if( count($data) == 0 ) return;
        
        $path_parts = pathinfo($fileName);

        // open the file for writing
        $file = fopen($path_parts['dirname'] . '/' . $path_parts['filename'] . '_failed.csv', 'w');
        
        // save the column headers
        fputcsv($file, array_keys($data[0]));
        
        // save each row of the data
        foreach ($data as $feed) {
            fputcsv($file, $feed);
        }
        
        // Close the file
        fclose($file);
    }

    /**
     * Make City array indexed by name
     */
    public static function getCitieWithNames($cities)
    {
        $prettyCities = [];
        foreach( $cities as $city )
            $prettyCities[$city->name] = $city->id;
        return $prettyCities;
    }

    /**
     * Get City Name with Full State
     */
    public static function getCityNameWithFullState($cityName)
    {
        $prettyCity = trim(str_replace(', ', ',', $cityName));
        $names = explode(',', $prettyCity);

        if( !is_array($names) || count($names) < 2 )
            return $prettyCity;
        
        if( !isset(self::$states[$names[1]]) )
            return $prettyCity;
        return $names[0] . ', ' . self::$states[$names[1]];
    }

    /**
     * Create City In API
     */
    public static function createCity($cityName)
    {
        $cityData = [
            "name"              => $cityName,
            'enabled'           => true,
            "administrator_id"  => 1
        ];
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/countries/1/cities");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cityData));
        
        $output = json_decode(curl_exec($ch));

        if( is_object($output) && $output->error == false )
            return $output->result->id;
        return 0;
		curl_close($ch);
    }

    /**
     * Get Schedule Array
     */
    public static function getScheduleArray($businessData)
    {
        return [
            self::getDaySchedule($businessData['mon_open'], $businessData['mon_close']),
            self::getDaySchedule($businessData['tues_open'], $businessData['tues_close']),
            self::getDaySchedule($businessData['wed_open'], $businessData['wed_close']),
            self::getDaySchedule($businessData['thurs_open'], $businessData['thurs_close']),
            self::getDaySchedule($businessData['fri_open'], $businessData['fri_close']),
            self::getDaySchedule($businessData['sat_open'], $businessData['sat_close']),
            self::getDaySchedule($businessData['sun_open'], $businessData['sun_close']),
        ];
    }

    /**
     * Get Time Object from time
     */
    public static function getTimeObject($timeString, $type)
    {
        $times = explode(":", $timeString);
        if( !is_array($times) || count($times) < 2 )
            return [
                'hour'      => $type == 'open' ? 0 : 23,
                'minute'    => $type == 'open' ? 0 : 59
            ];
        return [
            'hour'      => floatval($times[0]),
            'minute'    => floatval($times[1])
        ];
    }

    /**
     * Get Day Schedule From open and close
     */
    public static function getDaySchedule($open, $close)
    {
        $enabled = true;
        if( trim(strtolower($open)) == 'closed' || trim(strtolower($close)) == 'closed' )
            $enabled = false;

        if( explode(":", $open) )
        return [  
            "enabled" => $enabled,
            "lapses" =>[
              [  
                "open" => self::getTimeObject($open, 'open'),
                "close" => self::getTimeObject($close, 'close')
              ]
            ]
        ];
    }


    /**
     * Clean Slug
     */
    public static function cleanSlug($string) {
        $string = str_replace(' ', '', $string);
        $string = str_replace('-', '', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[0-9]+/', '', $string);
        $string = $string.chr(rand(100,110));
        //$result  = preg_replace('/[^a-zA-Z0-9_ -]/s','',$string);
     
        return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }

    /**
     * Clean Email
     */
    public static function cleanEmail($em){
        $em = str_replace('/-', '', $em);
        $em = str_replace('(', '', $em);
        $em = str_replace(')', '', $em);
        $em = str_replace(' ', '', $em);
        $em = str_replace(',', '', $em);

        return $em;
    }

    /**
     * Create Business To API
     */
    public static function createBusiness($post_params)
    {
        //Validate Post Params
        unset($post_params['created_at']);
        unset($post_params['business_id']);
        unset($post_params['about']);
        unset($post_params['cellphone']);
        if( empty($post_params['logo']) )
            unset($post_params['logo']);
        if( empty($post_params['header']) || substr( $post_params['header'], 0, 4 ) !== "http" )
            unset($post_params['header']);
        unset($post_params['printer_id']);
        unset($post_params['id']);
        unset($post_params['menus_count']);
        
        unset($post_params['full_city_name']);
        $post_params['email'] = self::cleanEmail($post_params['email']);
        if( empty($post_params['city_id']) ) unset($post_params['city_id']);

        $post_params['location'] = json_encode([
            'lat'   => floatval($post_params['lat']),
            'lng'   => floatval($post_params['lng']),
        ]);
        unset($post_params['lat']);
        unset($post_params['lng']);

        if( !in_array($post_params['timezone'], timezone_identifiers_list()) )
        {
            unset($post_params['timezone']);
        }

        /*
        foreach( $post_params as $index => $value ){
            if( empty($value) ){
                echo $index . "<br>";
                unset($post_params[$index]);
            }
        }*/

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(str_replace('Must supply api_key', '', curl_exec($ch)));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return FALSE;
		return $output->result->id;    
    }

    /**
     * Update business on API
     */
    public static function updateBusiness($post_params)
    {
        //Validate Post Params
        unset($post_params['created_at']);
        $businessId = $post_params['business_id'];
        unset($post_params['business_id']);
        unset($post_params['about']);
        unset($post_params['cellphone']);
        if( empty($post_params['logo']) )
            unset($post_params['logo']);
        if( empty($post_params['header']) || substr( $post_params['header'], 0, 4 ) !== "http" )
            unset($post_params['header']);
        unset($post_params['printer_id']);
        unset($post_params['id']);
        unset($post_params['menus_count']);
        unset($post_params['needs_update']);
        
        unset($post_params['full_city_name']);
        $post_params['email'] = self::cleanEmail($post_params['email']);
        if( empty($post_params['city_id']) ) unset($post_params['city_id']);

        $post_params['location'] = json_encode([
            'lat'   => floatval($post_params['lat']),
            'lng'   => floatval($post_params['lng']),
        ]);
        unset($post_params['lat']);
        unset($post_params['lng']);

        if( !in_array($post_params['timezone'], timezone_identifiers_list()) )
        {
            unset($post_params['timezone']);
        }

        /*
        foreach( $post_params as $index => $value ){
            if( empty($value) ){
                echo $index . "<br>";
                unset($post_params[$index]);
            }
        }*/

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $businessId);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(str_replace('Must supply api_key', '', curl_exec($ch)));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return FALSE;
		return $output->result->id;    
    }

    /**
     * Create Business Menu
     */
    public static function createBusinessMenu($businessDetails, $productIds)
    {
        $post_params = [
            'name'      => $businessDetails['name'],
            'schedule'  => $businessDetails['schedule'],
            'pickup'    => 1,
            'delivery'  => 1,
            'enabled'   => 1,
            'products'  => json_encode($productIds)
        ];

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $businessDetails['business_id'] . "/menus");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return FALSE;
		return $output->result->id;
    }

    /**
     * Update Busines Owners
     */
    public static function updateBusinessOwners($businessId, $owners)
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $businessId);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['owners' => json_encode($owners)]));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return FALSE;
		return true;
    }

    /**
     * Update Business Paymethods
     */
    public static function updateBusinessPaymethods($businessId, $post_params)
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $businessId . "/paymethods");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return FALSE;
		return true;
    }

    /**
     * Create category to api
     */
    public static function createCategoryToApi($categoryDetails)
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $categoryDetails['business_id'] . "/categories");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        unset($categoryDetails['business_id']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($categoryDetails));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return false;
		return $output->result->id;   
    }

    /**
     * Remove Category From API
     */
    public static function removeCategory($categoryDetails)
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $categoryDetails['store_id'] . "/categories/" . $categoryDetails['category_id']);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		
        
        $output = json_decode(curl_exec($ch));

        curl_close($ch);
        
        if( is_object($output) && $output->error == false )
            return true;
        return false;        
    }

    /**
     * Create Bulk Products To API
     */
    public static function createBulkProductsToApi($products)
    {
        // echo "<pre>";
        // var_dump($products);
        // exit;
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/bulks/products");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['products' => json_encode($products)]));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(str_replace('Must supply api_key', '', curl_exec($ch)));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return $output->result[0];
		return true;
    }

    /**
     * Create Product to API
     */
    public static function createSingleProductToApi($business_id, $category_id, $product)
    {
        $product['name'] = utf8_encode($product['name']);

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $business_id . "/categories/" . $category_id . "/products");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($product));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(self::getPrettyJsonResponse(curl_exec($ch)));

        //$output = json_decode(str_replace('Must supply api_key', '', curl_exec($ch)));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return false;
		return $output->result->id;   
    }

    /**
     * Create Extra Product
     */
    public static function createExtraProduct($business_id, $extraProduct)
    {

        $post_params = [
            'name'      => $extraProduct['name'],
            'enabled'   => $extraProduct['enabled']
        ];

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $business_id . "/extras");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return false;
		return $output->result->id;
    }

    /**
     * Update Product Extras
     */
    public static function updateProductExtras($business_id, $category_id, $product_id, $extra_ids)
    {
        $post_params = [
            'extras'    => json_encode($extra_ids)
        ];

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $business_id . "/categories/" . $category_id . "/products/" . $product_id);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return false;
		return $output->result->id;
    }

    /**
     * Create Product Extra Option
     */
    public static function createExtraOption($business_id, $extra_id, $extraProduct)
    {

        $post_params = [
            'name'          => $extraProduct['name'],
            'conditioned'   => true,
            'min'           => $extraProduct['min'],
            'max'           => $extraProduct['max'],
            'enabled'       => $extraProduct['enabled']
        ];

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $business_id . "/extras/" . $extra_id . "/options");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return false;
		return $output->result->id;
    }

    /**
     * Create Product Extra Sub Option
     */
    public static function createExtraSubOption($business_id, $extra_id, $option_id, $extraOption)
    {

        $post_params = [
            'name'          => $extraOption['name'],
            'price'         => $extraOption['price'],
            'enabled'       => 1
        ];

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $business_id . "/extras/" . $extra_id . "/options/" . $option_id . "/suboptions");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return false;
		return $output->result->id;
    }
    

    /**
     * Get All Categories for business from API
     */
    public static function getAllCategoriesForBusiness($business_id)
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $business_id . "/categories");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        // var_dump(curl_exec($ch));
        // exit;
        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return FALSE;
		return $output->result;
    }

    /**
     * Get Product Ids for category from api
     */
    public static function getProductsForCategory($business_id, $category_id)
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $business_id . "/categories/" . $category_id . "/products?params=id");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // var_dump(curl_exec($ch));
        // var_dump(curl_error($ch));
        // exit;

        $output = json_decode(curl_exec($ch));
        curl_close($ch);

        if( !is_object($output) || $output->error == true )
            return FALSE;
		return $output->result;
    }

    /**
     * Get All Product Ids from API
     */
    public static function getAllProductIdsForBusiness($business_id)
    {
        $businessCategories = self::getAllCategoriesForBusiness($business_id);

        if( $businessCategories == FALSE) return [];
        
        self::output("Fetched " . count($businessCategories) . " categories ------<hr>");
       
        $businessProductIds = [];
        foreach( $businessCategories as $index => $categoryDetails ){
            $categoryProducts = self::getProductsForCategory($business_id, $categoryDetails->id);
            if( !is_array($categoryProducts) ) continue;

            $categoryProductIds = array_map(function($product){
                return $product->id;
            }, $categoryProducts);
            $businessProductIds = array_merge($businessProductIds, $categoryProductIds);

            self::output("$index :: Fetched " . count($categoryProductIds) . " products ------<hr>");
        }

        return $businessProductIds;
    }

    /**
     * Insert Business to mysql
     */
    public static function insertRecordToTable($con, $table, $data)
    {
        $columnNames = implode(',', array_keys($data));
        $columnValues = '';
        foreach($data as $value){
            $columnValues .= "'" . (str_replace("'", "\'", $value)) . "'" . ',';
        }
        $columnValues = rtrim($columnValues, ',');
        $sql = "INSERT INTO $table($columnNames) VALUES ($columnValues)";
        $result = $con->query($sql); 
        if( !$result ){
            return 0;
        }
        
        return $con->insert_id;
    }

    /**
     * Update Record To Mysql
     */
    public static function updateMysqlQuery($con, $table, $data, $whereStatement)
    {
        $columnEquals = '';
        foreach( $data as $key => $value ){
            $columnEquals .= $key . "=" . "'" . (str_replace("'", "\'", $value)) . "'" . ',';
        }
        $columnEquals = rtrim($columnEquals, ',');

        $sql = "UPDATE $table SET $columnEquals WHERE $whereStatement";
        $result = $con->query($sql); 
        return $result;
    }

    /**
     * Insert Busineeses to mysql batch
     */
    public static function insertMysqlBatch($con, $table, $busineeses)
    {
        $columnNames = implode(',', array_keys($busineeses[0]));

        //$batchSize = 100;
        $sql = "INSERT INTO $table($columnNames) VALUES ";
        
        foreach( $busineeses as $businessData ){
            $columnValues = '';
            foreach($businessData as $value){
                $columnValues .= "'" . (str_replace("'", "\'", $value)) . "'" . ',';
            }
            $columnValues = rtrim($columnValues, ',');

            $sql .= '(' . $columnValues . '),';
        }

        $result = $con->query(rtrim($sql, ','));         
        if( !$result ){
            // echo $sql;
            // exit;
            return false;
        }
        return true;
    }

    /**
     * Get Lat Lng from google zipcode
     */
    public static function getLatLng($zipCode)
    {
        $pretyZipCode = str_pad((string)$zipCode, 5, "0", STR_PAD_LEFT);

        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($pretyZipCode)."&sensor=false&key=AIzaSyBSyKThiinHqLcTR-V-EoW0MYoH-sW0OOI";
        $result_string = file_get_contents($url);
        
        $result = json_decode($result_string, true);
        if(!empty($result['results'])){
            $zipLat = $result['results'][0]['geometry']['location']['lat'];
            $ziplng = $result['results'][0]['geometry']['location']['lng'];
            return [$zipLat, $ziplng];
        }
        return [0, 0];
    }

    //Get Zip Code From Lat Lng
    public static function getZipcodeFromLatLng($latitude, $longitude)
    {   
        $latitude = floatval($latitude);
        $longitude = floatval($longitude);
        // get zipcode
        $geocode = file_get_contents("https://maps.google.com/maps/api/geocode/json?latlng=$latitude,$longitude&key=AIzaSyBSyKThiinHqLcTR-V-EoW0MYoH-sW0OOI");
        $json = json_decode($geocode);
    
        if( !is_object($json) ) return false;
        foreach($json->results[0]->address_components as $adr_node) {
            if($adr_node->types[0] == 'postal_code') {
                return $adr_node->long_name;
            }
        }
        return false;
    }

    /**
     * Get Zip Code From Address
     */
    public static function getZipcodeFromAddress($address)
    {
        $address = trim(str_replace(' ', '+', $address));
        // get geocode
        $geocode = file_get_contents("https://maps.google.com/maps/api/geocode/json?address=$address&key=AIzaSyBSyKThiinHqLcTR-V-EoW0MYoH-sW0OOI");
        $json = json_decode($geocode);

        if( !is_object($json) )  return false;
        $latitude = $json->results[0]->geometry->location->lat;
        $longitude = $json->results[0]->geometry->location->lng;
        
        // get zipcode
        $geocode = file_get_contents("https://maps.google.com/maps/api/geocode/json?latlng=$latitude,$longitude&key=AIzaSyBSyKThiinHqLcTR-V-EoW0MYoH-sW0OOI");
        $json = json_decode($geocode);
        
        foreach($json->results[0]->address_components as $adr_node) {
            if($adr_node->types[0] == 'postal_code') {
                return $adr_node->long_name;
            }
        }
        return false;
    }

    /**
     * Create Delivery Zone For Business
     */
    public static function createDeliveryZone($businessData)
    {
        $post_params = [
            "name"      => $businessData['full_city_name'] . " " . $businessData['zipcode'],
            "type"      => 1,
            "address"   => $businessData['address'],
            "price"     => $businessData['delivery_price'],
            "minimum"   => $businessData['minimum'],
            "schedule"  => $businessData['schedule'],
            "enabled"   => true
        ];

        if( !empty($businessData['zipcode']) ){
            $post_params['data'] = json_encode([
                "center" => [
                    "lat"=>  floatval($businessData['lat']),
                    "lng"=>  floatval($businessData['lng']),
                ],
                "radio" => 9.65606
            ]);
        }

        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $businessData['business_id'] . "/deliveryzones");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_params));
        
        $output = curl_exec($ch);
		return json_decode($output)->result->id;
		curl_close($ch);
    }

    /**
     * Clean text from html entity
     */
    public static function cleanText($content)
    {
        $string = htmlentities($content, null, 'utf-8');
        $content = str_replace("&nbsp;", " ", $string);
        return html_entity_decode($content);
    }

    /**
     * Import Business Data From CSV
     */
    public static function importBusinessFromCSV($csvFileName)
    {

        //Mysql Connection
        $con = new mysqli('localhost','root','root','pronto');

        //Parse CSV
        $csvData = self::parseCSV($csvFileName, true);

        //Get All Cities
        $citiesWithNames = self::getCitieWithNames(self::getAllCities());

        $dataToInsert = [];
        $totalAdded = 0;
        $totalFailed = 0;
        foreach($csvData as $index=>$businessData){
            $city = $businessData['city'];

            //check if needs to create city
            $cityNameWithFullState = self::getCityNameWithFullState($city);

            if( !isset($citiesWithNames[$cityNameWithFullState]) ){
                $citiesWithNames[$cityNameWithFullState] = self::createCity($cityNameWithFullState);
            }

            //Get Lat Lng From ZipCode
            if( isset($businessData['lat']) && is_numeric($businessData['lat']) && isset($businessData['lng']) && is_numeric($businessData['lng']) ){
                $lat = $businessData['lat'];
                $lng = $businessData['lng'];
            }
            else{
                list($lat, $lng) = self::getLatLng($businessData['zipcode']);
            }
            

            //Create Business
            $post_params = [
                'name'                      => $businessData['name'],
                'address'                   => self::cleanText($businessData['address']),
                'address_notes'             => $businessData['address_notes'],
                'zipcode'                   => str_pad((string)$businessData['zipcode'], 5, "0", STR_PAD_LEFT),
                'phone'                     => self::cleanEmail(str_replace('-', '', $businessData['phone'])),
                'description'               => $businessData['description'],
                'about'                     => $businessData['description'],
                'slug'                      => self::cleanSlug($businessData['slug']),
                'email'                     => self::cleanEmail($businessData['email']),
                'header'                    => $businessData['banner_image'],
                'city_id'                   => $citiesWithNames[$cityNameWithFullState],
                'full_city_name'            => $cityNameWithFullState,
                'enabled'                   => strtolower($businessData['enabled']) == 'true' ? true : false,
                'schedule'                  => json_encode(self::getScheduleArray($businessData)),
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
                'business_id'               => 0,
                'created_at'                => date('Y-m-d H:i:s')
            ];

            $totalAdded++;
            self::output("$index, ");
            $dataToInsert[] = $post_params;

            if( ($index+1) % 5000 == 0 ){
                if( self::insertMysqlBatch($con, 'business_import', $dataToInsert) )
                    self::output("<br>Imported $totalAdded businesses from CSV.<br>");
                else
                    self::output("<br>Insert Failed<br>");
                $totalAdded = 0;
                $dataToInsert = [];
            }
        }
        if( self::insertMysqlBatch($con, 'business_import', $dataToInsert) )
            self::output("<br>Imported $totalAdded businesses from CSV.<br>");
        else
            self::output("<br>Insert Failed<br>");
        self::output("Failed $totalFailed businesses from CSV.<br>");
    }

    /**
     * Import Businesses From CSV
     */
    public static function populateBusinessToAPI(){
        //Mysql Connection
        $con = new mysqli('localhost','root','root','pronto');

        if( !isset($_GET['segment']) )
        {
            echo "segment required";
            exit;
        }

        $segmentSize = 1000;
        $segment = intval($_GET['segment']);
        
        $result = $con->query("SELECT * FROM business_import ORDER BY id LIMIT " . ($segmentSize * $segment) . ", " . ($segmentSize));
        
        self::output("Starting From " . ($segmentSize * $segment) . " TO " . ($segmentSize * $segment + $segmentSize) . "--------------<br>");

        //$result = $con->query("SELECT * FROM business_import WHERE business_id = 0");
        $businesses = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $businesses[] = $row;
            }
        }
        
        $totalAdded = 0;
        $totalFailed = 0;

        foreach($businesses as $index=>$businessData){
            if( $businessData['business_id'] != 0 )
                continue;
            $businessData['slug'] .= uniqid();
            $createdBusinessId = self::createBusiness($businessData);

            if( $createdBusinessId == FALSE ){
                self::output( $index . " <span style='color:red;'>Failed</span> to create business " . $businessData['id'] . ' :::: ' . $businessData['address'] . '<br>' );
                $totalFailed++;
                continue;
            }

            //Update Owners
            self::updateBusinessOwners($createdBusinessId, [1,26]);
            self::updateBusinessPaymethods($createdBusinessId, [
                'paymethod_id'  => 33,
                'sandbox'       => false,
                'data'          => json_encode([
                    "loginid"   => "2HmB477n3",
                    "tkey"      => "98mJ86y4QM9bS4wc"
                ]),
                'data_sandbox'  => json_encode([]),
                'enabled'       => true
            ]);

            //Save To Mysql
            $businessData['business_id'] = $createdBusinessId;
            $businessData['populated_at'] = date('Y-m-d H:i:s');

            $con->query("UPDATE business_import SET business_id = '" . $createdBusinessId . "', populated_at = '" . $businessData['populated_at'] . "', slug = '" . $businessData['slug'] . "' WHERE id = " . $businessData['id']);

            //Create Delivery Zone
            self::createDeliveryZone($businessData);

            self::output($businessData['id'] . '  <span style="color:green;">Success::</span>Added Business --> ' . $createdBusinessId . '<br>');

            $totalAdded++;
        }
        self::output("<hr>Total $totalAdded businesses added to API!<br>");
        self::output("$totalFailed businesses failed to Add!<br>");
    }

    /**
     * Delete Business From API
     */
    public static function deleteBusinessFromApi($businessId)
    {
        $ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business/" . $businessId);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: ' . self::$apiKey
		));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		
        
        $output = json_decode(curl_exec($ch));

        curl_close($ch);
        
        if( is_object($output) && $output->error == false )
            return true;
        return false;
    }

    /**
     * Remove Added Businesses
     */
    public static function removeBusinesses(){
        $con = new mysqli('localhost','root','root','pronto');
        /*
        for( $deletingId = 41000; $deletingId < 42000; $deletingId++ ){
            if( self::deleteBusinessFromApi($deletingId) ){
                self::output("<span style='color:green'>Deleted business</span> => " . $deletingId . "<br>");
            }
            else{
                self::output("<span style='color:red'>Failed business</span> => " . $deletingId . "<br>");
            }
        }*/
        /*
        $result = $con->query("SELECT * FROM business WHERE business_id > 31016");
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                if( self::deleteBusinessFromApi($row['business_id']) ){
                    $con->query("DELETE FROM business WHERE id = " . $row['id']);
                    self::output("Deleted business => " . $row['id'] . "   :   " . $row['business_id'] . "<br>");
                }
                else{

                }
            }
        }*/
    }

    /**
     * Out put
     */
    public static function output($str, $status="normal") {
        if( $status == 'error' )
            echo ( '<span style="color:red">' . $str . '</span>');
        else if( $status == 'success' )
            echo ( '<span style="color:green">' . $str . '</span>');
        else echo $str;
        ob_end_flush();
        ob_flush();
        flush();
        ob_start();
    }

    private static function getPrettyJsonResponse($str){
        $str = str_replace('Must supply api_key', '', $str);
        if( empty($str) )
            return $str;
        
        $firstPos = strpos($str, '{');
        $endPos = strrpos($str, '}');
        if( $firstPos == FALSE || $endPos == FALSE || $firstPos >= $endPos)
            return $str;
        return substr($str, $firstPos, $endPos - $firstPos + 1);
    }
    
}