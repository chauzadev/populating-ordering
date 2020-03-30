<?php
require_once 'PopulateCSV.php';

class ProductAction{

    public static $bulkProductLimit = 20;
    public static $segmentSize = 1500;

    public static function getCategoriesForBusiness($con, $business_id)
    {
        $result = $con->query("SELECT * FROM categories WHERE business_id = " . $business_id);

        $businessCategories = [];
        
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $businessCategories[strtolower($row['name'])] = $row['category_id'];
            }
        }

        return $businessCategories;
    }

    /**
     * Fetch product extras from product feed
     */
    public static function fetchExtraProducts($feed, $business_id)
    {
        $extraProducts = [];
        for($i = 1; $i <= 2; $i++){
            if( isset($feed['extra_name_' . $i]) && !empty($feed['extra_name_' . $i]) ){
                $extraProduct = [
                    'business_id'   => $business_id,
                    'name'          => trim(utf8_encode($feed['extra_name_' . $i])),
                    'conditioned'   => 1,
                    'min'           => 0,
                    'max'           => 1,
                    'enabled'       => 1,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'sub_options'       => []
                ];
                foreach( $feed as $key => $value ){
                    if ( strpos($key, 'extra_ ' . $i . '_desc_') === 0 && !empty($value) ) {
                        $price = 1;
                        $descNum = intval(str_replace('extra_ ' . $i . '_desc_', '', $key));
                        if( $descNum == 1 )
                            $price = floatval($feed['price']);
                        else if( isset($feed['extra_ ' . $i . '_price_' . $descNum]) && !empty($feed['extra_ ' . $i . '_price_' . $descNum])){
                            $price = floatval($feed['extra_ ' . $i . '_price_' . $descNum]);
                        }
                        $extraProduct['sub_options'][] = [
                            'name'  => $value,
                            'price' => $price
                        ];
                    }
                }
                $extraProduct['sub_options'] = json_encode($extraProduct['sub_options']);
                $extraProducts[] = $extraProduct;
            }
        }
        return $extraProducts;
    }


    /**
     * Get Extra Products for business from mysql
     */
    public static function getBusinessExtraProducts($con, $business_id)
    {
        //Get all unpopulated products for the business
        $result = $con->query("SELECT * FROM extra_products WHERE business_id = " . $business_id);

        $extraProducts = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                if( isset($extraProducts[$row['product_id']]) )
                    $extraProducts[$row['product_id']][] = $row;
                else
                    $extraProducts[$row['product_id']] = [$row];
            }
        }
        return $extraProducts;
    }

    /**
     * Get Pretty Product entity
     */
    protected static function getPrettyProduct($product)
    {
        $prettyProduct = [
            'name'          => trim(utf8_encode($product['item_name'])),
            'price'         => floatval($product['price']),
            'description'   => trim($product['description']),
            'images'        => trim(strtok($product['Img_src'], '?')),
            'sku'           => uniqid(),
            'inventoried'   => intval($product['inventoried']),
            'featured'      => 0,
            'enabled'       => 1,
            'upselling'     => $product['upselling'] == 'TRUE' ? 1 : 0,
        ];

        if( empty($prettyProduct['images']) )
            unset($prettyProduct['images']);

        return $prettyProduct;
    }

    /**
     * Import product feed to mysql table
     */
    public static function importProductsCategoriesToMysql($csvFileName)
    {
        //mysql connection
        $con = new mysqli('localhost','root','root','pronto');

        $csvData = PopulateCSV::parseCSV($csvFileName);
        
        $businessCategories = [];

        $dataToInsert = [];
        $totalAdded = 0;
        $wrongProducts = [];
        
        foreach( $csvData as $index => $feed )
        {
            //if data is not enough
            if( !isset($feed['store_id']) || empty($feed['store_id']) || !isset($feed['category_name']) || empty($feed['category_name']) || !isset($feed['item_name']) || empty($feed['item_name']) )
            {
                PopulateCSV::output('<span style="color:red;">Failed ' . $index . ': wrong product row</span><br>');
                $wrongProducts[] = $feed;
                continue;
            }

            $feed['store_id'] = intval($feed['store_id']);
            $feed['category_name'] = trim(utf8_encode($feed['category_name']));

            /*
            if( !isset($businessCategories[$feed['store_id']]) ){
                $businessCategories[$feed['store_id']] = self::getCategoriesForBusiness($con, $feed['store_id']);
            }
            $keyCategory = strtolower($feed['category_name']);

            if( !isset($businessCategories[$feed['store_id']][$keyCategory]) ){//if category is not in database then create
                $categoryDetails = [
                    'business_id'   => $feed['store_id'],
                    'name'          => $feed['category_name'],
                    'enabled'       => 1,
                ];
                $createdCategoryId = PopulateCSV::createCategoryToApi($categoryDetails);

                if( $createdCategoryId == false )
                {
                    PopulateCSV::output('<span style="color:red;">Failed ' . $index . ': cannot create category</span><br>');
                    continue;
                }

                //Save category in database
                $categoryDetails['category_id'] = $createdCategoryId;
                $categoryDetails['created_at'] = date('Y-m-d H:i:s');
                PopulateCSV::insertRecordToTable($con, 'categories', $categoryDetails);

                $businessCategories[$feed['store_id']][$keyCategory] = $createdCategoryId;
            }*/

            $productData = [
                'business_id'   => $feed['store_id'],
                'source_id'     => isset($feed['source_id']) ? intval($feed['source_id']) : 0,
                //'category_id'   => $businessCategories[$feed['store_id']][$keyCategory],
                'category_name' => $feed['category_name'],
                'name'          => trim($feed['item_name']),
                'price'         => floatval($feed['price']),
                'description'   => trim($feed['description']),
                'images'        => trim($feed['img_src']),
                'sku'           => uniqid(),
                'inventoried'   => 0,
                'quantity'      => 1,
                'featured'      => 0,
                'enabled'       => $feed['enabled'] == 'TRUE' ? 1 : 0,
                'upselling'     => trim($feed['upselling']) == 'TRUE' ? 1 : 0,
                'created_at'    => date('Y-m-d H:i:s')
            ];

            $totalAdded++;
            /*
            //Import Extra Product
            $extraProducts = self::fetchExtraProducts($feed, $productData['business_id']);
            if( count($extraProducts) > 0 ){
                $insertedProductId = PopulateCSV::insertRecordToTable($con, 'products', $productData);
                $extraProducts = array_map(function ($element) use ($insertedProductId) { 
                    $element['product_id'] = $insertedProductId; return $element; 
                }, $extraProducts);
                if(  PopulateCSV::insertMysqlBatch($con, 'extra_products', $extraProducts) )
                    PopulateCSV::output("Inserted " . count($extraProducts) . " extra products. <br>");
                else
                    PopulateCSV::output("<span style='color:red'>Found " . count($extraProducts) . " extra products. but failed to insert</span><br>");
                continue;
            }*/

            $dataToInsert[] = $productData;

            if( ($index+1) % 5000 == 0 ){
                if( PopulateCSV::insertMysqlBatch($con, 'products', $dataToInsert) )
                    PopulateCSV::output("<br>Imported $totalAdded products from CSV.<br>");
                else
                    PopulateCSV::output("<br>Insert $totalAdded Failed<br>");
                $totalAdded = 0;
                $dataToInsert = [];
            }
        }
        if( PopulateCSV::insertMysqlBatch($con, 'products', $dataToInsert) )
            PopulateCSV::output("<br>Imported $totalAdded products from CSV.<br>");
        else
            PopulateCSV::output("<br>Insert $totalAdded Failed<br>");

        PopulateCSV::outputCSV($csvFileName, $wrongProducts);
    }

    /**
     * Populate Products to api
     */
    public static function populateProductsToApiUsingBulk($business_id, $con = FALSE)
    {
        //mysql connection
        if( $con == FALSE )
            $con = new mysqli('localhost','root','root','pronto');

        //Get all unpopulated products for the business
        $result = $con->query("SELECT * FROM cleanse WHERE store_id = " . $business_id . " AND populated = 0");

        $pendingProducts = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $pendingProducts[] = $row;
            }
        }

        PopulateCSV::output($business_id . ' : Total ' . count($pendingProducts) . ' products starting deploying-------<br><hr>');

        $totalPopulated = 0;
        $totalFailed = 0;
        $chunkProducts = array_chunk($pendingProducts, self::$bulkProductLimit);
        foreach( $chunkProducts as $index => $products ){

            PopulateCSV::output('Populating products from ' . ($index*self::$bulkProductLimit+1) . ' to ' . ($index*self::$bulkProductLimit + count($products)) . ' ------ ');

            $prettyProducts = array_map(function($product){

                $prettyProduct = [
                    'business_id'   => intval($product['store_id']),
                    'category_id'   => intval($product['category_id']),
                    'name'          => trim(utf8_encode($product['item_name'])),
                    'price'         => floatval($product['price']),
                    'description'   => trim($product['description']),
                    'images'        => trim($product['Img_src']),
                    'sku'           => uniqid(),
                    'inventoried'   => intval($product['inventoried']),
                    'featured'      => 0,
                    'enabled'       => 1,
                    'upselling'     => $product['upselling'] == 'TRUE' ? 1 : 0,

                ];
                if( empty($prettyProduct['images']) )
                    unset($prettyProduct['images']);
                return $prettyProduct;
            }, $products);

            $createdProductsIds = PopulateCSV::createBulkProductsToApi($prettyProducts);
            
            if( $createdProductsIds == false ){
                PopulateCSV::output('<span style="color:red;">Failed to create products</span><br>');
                $totalFailed += count($products);
                continue;
            }

            $con->query("UPDATE cleanse SET populated = 1, populated_at='" . date('Y-m-d H:i:s') . "' WHERE id IN(" . implode(',', array_column($products, 'id')) . ")");

            $totalPopulated += count($products);
            PopulateCSV::output('<span style="color:green;">Suceed to create products</span><br>');
        }

        PopulateCSV::output("Total populated " . $totalPopulated . " products. <br>");
        PopulateCSV::output("Total failed " . $totalFailed . " products. <br>");
    }

    /**
     * Populate products to api by bulk api endpoint
     */
    public static function populateProductsToApiUsingBulkNotBusiness($pendingProducts, $con)
    {
        $chunkProducts = array_chunk($pendingProducts, self::$bulkProductLimit);
        foreach( $chunkProducts as $index => $products ){

            PopulateCSV::output('Populating products from ' . $products[0]['id'] . '   ~~~   ' . $products[count($products)-1]['id'] . ' ------ ');

            $prettyProducts = array_map(function($product){

                $prettyProduct = [
                    'business_id'   => intval($product['store_id']),
                    'category_id'   => intval($product['category_id']),
                    'name'          => trim(utf8_encode($product['item_name'])),
                    'price'         => floatval($product['price']),
                    'description'   => trim($product['description']),
                    'images'        => trim($product['Img_src']),
                    'sku'           => uniqid(),
                    'inventoried'   => intval($product['inventoried']),
                    'featured'      => 0,
                    'enabled'       => 1,
                    'upselling'     => $product['upselling'] == 'TRUE' ? 1 : 0,
                ];
                if( empty($prettyProduct['images']) )
                    unset($prettyProduct['images']);
                return $prettyProduct;
            }, $products);

            $createdProductsIds = PopulateCSV::createBulkProductsToApi($prettyProducts);
            
            if( $createdProductsIds !== true ){
                PopulateCSV::output('<span style="color:red;">Failed to create products: ' . $createdProductsIds . '</span><br>');
                continue;
            }

            $con->query("UPDATE cleanse SET populated = 1, populated_at='" . date('Y-m-d H:i:s') . "' WHERE id IN(" . implode(',', array_column($products, 'id')) . ")");
            PopulateCSV::output('<span style="color:green;">Suceed to create products</span><br>');
        }
    }

    /**
     * Populate Products to API one by one
     */
    public static function populateProductsToApiSingle($segment)
    {
        //mysql connection
        $con = new mysqli('localhost','root','root','pronto');

        //Get all unpopulated products for the business
        $result = $con->query("SELECT * FROM cleanse WHERE pre_populated = 0 AND store_id > 0 AND category_id > 0 ORDER BY id LIMIT " . (self::$segmentSize * ($segment-1)) . ", " . (self::$segmentSize));

        $pendingProducts = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                if( $row['populated'] == 0 )
                    $pendingProducts[] = $row;
            }
        }

        //Get Extra Products For Business in database
        //$businessExtraProducts = self::getBusinessExtraProducts($con, $business_id);

        PopulateCSV::output('Populating products from ' . ( self::$segmentSize * ($segment-1) + 1 ) . ' to ' . ( self::$segmentSize * ($segment-1) + count($pendingProducts) ) . ' ----------  ' . count($pendingProducts) . '<br><hr>');

        $totalPopulated = 0;
        $totalFailed = 0;

        foreach( $pendingProducts as $product ){
            if( $product['populated'] ) continue;
            $prettyProduct = self::getPrettyProduct($product);

            $createdProductId = PopulateCSV::createSingleProductToApi($product['store_id'], $product['category_id'], $prettyProduct);

            if( $createdProductId == false ){
                PopulateCSV::output("<span style='color:red;'>Failed to created product</span> : " . $product['id'] . '   ' . $product['item_name'] . '<br>');
                $totalFailed++;
                continue;
            }

            $con->query("UPDATE cleanse SET product_id=" . $createdProductId . ", populated = 1, populated_at='" . date('Y-m-d H:i:s') . "' WHERE id = " . $product['id']);
            $totalPopulated++;
            PopulateCSV::output("<span style='color:green;'>Succeed to created product</span> : " . $product['id'] . " ----- " . $createdProductId . "<br>");

            /*
            //Populate Extra Products
            if( isset($businessExtraProducts[$product['id']]) && is_array($businessExtraProducts[$product['id']]) && count($businessExtraProducts[$product['id']]) > 0 ){
                $createdExtraIds = [];
                foreach( $businessExtraProducts[$product['id']] as $extraProduct ){
                    $createdExtraId = PopulateCSV::createExtraProduct($product['business_id'], $extraProduct);
                    if( $createdExtraId == FALSE ){
                        PopulateCSV::output("<span style='color:red;'>Failed to created extra product</span> : " . $extraProduct['id'] . '<br>');
                        continue;
                    }
                    $con->query("UPDATE extra_products SET populated = 1, extra_id=" . $createdExtraId . " WHERE id = " . $extraProduct['id'] );
                    $createdExtraIds[] = $createdExtraId;

                    //Create Extra Option
                    $createdExtraOptionId = PopulateCSV::createExtraOption($product['business_id'], $createdExtraId, $extraProduct);
                    if( $createdExtraOptionId == FALSE ){
                        PopulateCSV::output("<span style='color:red;'>Failed to created extra option</span> : " . $extraProduct['id'] . '<br>');
                        continue;
                    }
                    $con->query("UPDATE extra_products SET option_id=" . $createdExtraOptionId . " WHERE id = " . $extraProduct['id'] );

                    //Create Extra Sub Options
                    $extraOptions = json_decode($extraProduct['sub_options'], true);
                    if( is_array($extraOptions)  ){
                        foreach( $extraOptions as $extraOption ){
                            if( PopulateCSV::createExtraSubOption($product['business_id'], $createdExtraId, $createdExtraOptionId, $extraOption) == false ){
                                PopulateCSV::output("<span style='color:red;'>Failed to created extra sub option</span> : " . $extraProduct['id'] . '<br>');
                            }
                        }
                    }
                }

                //update product extras
                if( count($createdExtraIds) > 0 )
                    PopulateCSV::updateProductExtras($product['business_id'], $product['category_id'], $createdProductId, $createdExtraIds);
            }
            */
        }

        PopulateCSV::output("<hr>Total populated " . $totalPopulated . " products. <br>");
        PopulateCSV::output("Total failed " . $totalFailed . " products. <br>");
    }

    /**
     * Populate business menu
     */
    public static function populateBusinessMenu($business_id)
    {
        //mysql connection
        $con = new mysqli('localhost','root','root','pronto');

        //Get Business
        $result = $con->query("SELECT * FROM business_import WHERE business_id = " . $business_id);
        if (mysqli_num_rows($result) == 0) {
            PopulateCSV::output('No business found.<br>');
            return;
        }
        $businessDetails = mysqli_fetch_assoc($result);

        /*
        //Get all unpopulated products for the business
        $result = $con->query("SELECT * FROM products WHERE business_id = " . $business_id . " AND populated = 1 AND product_id != 0");
        $businessProductIds = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $businessProductIds[] = $row['product_id'];
            }
        }
        */
        $businessProductIds = PopulateCSV::getAllProductIdsForBusiness($business_id);

        if( count($businessProductIds) == 0 ) {
            PopulateCSV::output('No products found in that business.<br>');
            return;
        }

        $createdMenuId = PopulateCSV::createBusinessMenu($businessDetails, $businessProductIds);
        if( $createdMenuId == FALSE ) {
            PopulateCSV::output('Business menu creation failed');            
        }

        PopulateCSV::output('Business menu created -->  ' . $createdMenuId);
        $con->query("UPDATE business_import SET menu_id = " . $createdMenuId . " WHERE id = " . $businessDetails['id']);
    }

    /**
     * Remove Categories
     */
    public static function removeCategories($segment)
    {

        $segmentSize = 500;
        //mysql connection
        $con = new mysqli('localhost','root','root','pronto');

        //Get all unpopulated products for the business
        $result = $con->query("SELECT store_id, category_id FROM cleanse WHERE category_id > 0 AND store_id > 0 GROUP BY category_id LIMIT " . $segment*$segmentSize . ', ' . $segmentSize);

        $removingCategories = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $removingCategories[] = $row;
            }
        }

        PopulateCSV::output("Started removing categories total " . count($removingCategories) . "<hr>");

        foreach( $removingCategories as $categoryDetails){
            if( PopulateCSV::removeCategory($categoryDetails) ){
                $con->query("DELETE FROM categories WHERE category_id = " . $categoryDetails['category_id']);
                $con->query("UPDATE cleanse SET category_id = 0 WHERE category_id = " . $categoryDetails['category_id']);
                PopulateCSV::output("<span style='color:green'>Removed category successfully</span> : " . $categoryDetails['category_id'] . "<br>");
            }
            else{
                PopulateCSV::output("<span style='color:red'>Failed category deletion</span> : " . $categoryDetails['category_id'] . "<br>");
            }
        }
    }

    /**
     * Copy All Products for business
     */
    public static function copyProductsForBusiness($oldBusinessId, $newBusinessId)
    {
        //mysql connection
        $con = new mysqli('localhost','root','root','pronto');

        //All Categories
        $result = $con->query("SELECT * FROM categories WHERE business_id=" . $oldBusinessId);
        $oldCategories = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $oldCategories[intval($row['category_id'])] = $row;
            }
        }
        
        //Create Categories
        
        $newCategories = [];
        foreach( $oldCategories as $key => $categoryDetails ){
            $post_params = [
                'business_id'   => $newBusinessId,
                'name'          => $categoryDetails['name'],
                'enabled'       => 1,
            ];
            $createdCategoryId = PopulateCSV::createCategoryToApi($post_params);
            if( $createdCategoryId != FALSE ){
                $post_params['category_id'] = $createdCategoryId;
                $post_params['created_at'] = date('Y-m-d H:i:s');
                PopulateCSV::insertRecordToTable($con, 'categories', $post_params);

                $newCategories[$key] = $post_params['category_id']; 
            }
        }

        //Clone Products
        $result = $con->query("SELECT * FROM products WHERE business_id=" . $oldBusinessId);
        $products = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $products[] = $row;
            }
        }

        $newProducts = [];
        foreach( $products as $product ){
            $product['sku'] = uniqid();
            $product['populated'] = 0;
            $product['populated_at'] = null;
            $product['created_at'] = date('Y-m-d H:i:s');
            $product['business_id'] = $newBusinessId;
            $product['category_id'] = $newCategories[$product['category_id']];
            $product['product_id'] = 0;
            
            $oldId = $product['id'];
            unset($product['id']);
            $createdId = PopulateCSV::insertRecordToTable($con, 'products', $product);
            $newProducts[$oldId] = $createdId;
        }
        

        //Clone Extra Products
        $result = $con->query("SELECT * FROM extra_products WHERE business_id=" . $oldBusinessId);
        $extraProducts = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $extraProducts[] = $row;
            }
        }

        $newExtraProducts = [];
        foreach( $extraProducts as $extraProduct ){
            $extraProduct['created_at'] = date('Y-m-d H:i:s');
            $extraProduct['extra_id'] = 0;
            $extraProduct['option_id'] = 0;
            $extraProduct['business_id'] = $newBusinessId;
            $extraProduct['product_id'] = $newProducts[$extraProduct['product_id']];
            $extraProduct['populated'] = 0;
            unset($extraProduct['id']);
            $newExtraProducts[] = $extraProduct;
        }
        PopulateCSV::insertMysqlBatch($con, 'extra_products', $newExtraProducts);
    }

    /**
     * Populate business to api by segment
     */
    public static function populateBusinessMulti(){
        if( !isset($_GET['segment']) || !is_numeric($_GET['segment']) ){
            echo "fail";
            exit;
        }
        $segmentSize = 10;
        $segment = intval($_GET['segment']);
        $con = new mysqli('localhost','root','root','pronto');
        $result = $con->query("SELECT * FROM target_business LIMIT " . $segment*$segmentSize . ', ' . $segmentSize);
        
        $businesses = [];
        if (mysqli_num_rows($result) > 0) {
            while( $row = mysqli_fetch_assoc($result) ){
                $businesses[] = $row;
            }
        }
        
        foreach( $businesses as $business ){
            if( $business['populated'] ) continue;
            self::populateProductsToApiSingle($business['copy_business_id'], 1);
            $con->query("UPDATE target_business SET populated = 1, populated_at='" . date('Y-m-d H:i:s') . "' WHERE id = " . $business['id']);
        }
    }

    /**
     * Copy Business Bulk
     */
    public static function copyBusinessBulk(){
        $segmentSize = 100;
        if( isset($_GET['segment']) && is_numeric($_GET['segment']) ){

            $con = new mysqli('localhost','root','root','pronto');
            $result = $con->query("SELECT * FROM target_business LIMIT " . ($_GET['segment']*$segmentSize) . ", " . $segmentSize);

            $businesses = [];
            if (mysqli_num_rows($result) > 0) {
                while( $row = mysqli_fetch_assoc($result) ) {
                    $businesses[] = $row;
                }
            }

            foreach( $businesses as $businessDetails ){
                if( $businessDetails['copied'] ) continue;
                self::copyProductsForBusiness(19923, $businessDetails['copy_business_id']);

                $con->query("UPDATE target_business SET copied = 1 WHERE id = " . $businessDetails['id']);
                PopulateCSV::output("Sucessfully copied business<br>");
            }
        }
    }

    /**
     * Create Categories For Cleanse Table
     */
    public static function createCategoriesInCleanseTable($segment){
        
        $segmentSize = 500;
        $con = new mysqli('localhost','root','root','pronto');
        $result = $con->query("SELECT * FROM cleanse GROUP BY store_id, category_name LIMIT " . $segment*$segmentSize . ', ' . $segmentSize);
        

        $businessCategories = [];
        if (mysqli_num_rows($result) > 0) {
            while( $row = mysqli_fetch_assoc($result) ){
                $businessCategories[] = $row;
            }
        }

        PopulateCSV::output('Populating categories from ' . ( $segmentSize * ($segment) + 1 ) . ' to ' . ( $segmentSize * ($segment) + count($businessCategories) ) . ' ----------<br><hr>');

        $totalCreated = 0;
        $totalFailed = 0;
        foreach( $businessCategories as $categoryDetails ){
            if( intval($categoryDetails['category_id']) > 0 ) continue;
            if( empty($categoryDetails['category_name']) || empty($categoryDetails['store_id']) ){
                PopulateCSV::output('Wrong category row : ' . $categoryDetails['store_id'] . '  ' . $categoryDetails['category_name'] . '<br>', 'error');
                $totalFailed++;
                continue;
            }

            $post_params = [
                'business_id'   => intval($categoryDetails['store_id']),
                'name'          => trim(utf8_encode($categoryDetails['category_name'])),
                'enabled'       => 1
            ];
            $createdCategoryId = PopulateCSV::createCategoryToApi($post_params);

            if( $createdCategoryId == false )
            {
                PopulateCSV::output('Failed to create category : ' . $categoryDetails['store_id'] . '  ' . $categoryDetails['category_name'] . '<br>', 'error' );
                $totalFailed++;
                continue;
            }
            $prretyCategory = [
                'business_id'   => $categoryDetails['store_id'],
                'category_id'   => $createdCategoryId,
                'name'          => trim(utf8_encode($categoryDetails['category_name'])),
                'enabled'       => 1,
                'created_at'    => date('Y-m-d H:i:s')
            ];
            PopulateCSV::insertRecordToTable($con, 'categories', $prretyCategory);

            $con->query("UPDATE cleanse SET category_id = " . $createdCategoryId . " WHERE store_id = " . $categoryDetails['store_id'] . " AND category_name = '" . $categoryDetails['category_name'] . "'");

            PopulateCSV::output('Created category successfully : ' . $categoryDetails['store_id'] . '  ' . $categoryDetails['category_name'] . '   ' . $createdCategoryId . '<br>', 'success' );
            $totalCreated++;
        }

        PopulateCSV::output("<hr>Created total categories : " . $totalCreated);
        PopulateCSV::output("<br>Failed total categories : " . $totalFailed);
    }

    /**
     * Create Products For all businesses using bulk api endpoint
     */
    public static function createProductsForAllBusinesses($segment){
        //$segmentSize = 20 * self::$bulkProductLimit;
        $segmentSize = 10000;
        $con = new mysqli('localhost','root','root','pronto');

        $result = $con->query("SELECT * FROM cleanse WHERE store_id > 0 AND category_id > 0 AND pre_populated = 0 LIMIT " . $segment*$segmentSize . ', ' . $segmentSize);
        //$result = $con->query("SELECT * FROM cleanse WHERE store_id > 0 AND category_id > 0 AND id >= 2349301 AND id <=  2349650");
        
        $pendingProducts = [];
        if (mysqli_num_rows($result) > 0) {
            while( $row = mysqli_fetch_assoc($result) ){
                if( $row['populated'] == 0 )
                    $pendingProducts[] = $row;
            }
        }

        self::populateProductsToApiUsingBulkNotBusiness($pendingProducts, $con);
    }
}





ob_start();
$start_time = microtime(true);



/**Import Products/categories to mysql */
/*
$csvFiles = scandir('feeds/product/pending');
foreach( $csvFiles as $file ){
    if( strpos($file, 'csv') ){
        ProductAction::importProductsCategoriesToMysql('feeds/product/pending/' . $file);
    }
}*/





/**Populate products to api as bulk */
/*
if( isset($_GET['business_id']) && is_numeric($_GET['business_id']) )
    ProductAction::populateProductsToApiUsingBulk($_GET['business_id']);
*/





/**Populate products to api using single endpoint */

if( isset($_GET['segment']) && is_numeric($_GET['segment']) )
    ProductAction::populateProductsToApiSingle($_GET['segment']);


/**Populate Business Menu */
/*
if( isset($_GET['business_id']) && is_numeric($_GET['business_id']) )
    ProductAction::populateBusinessMenu($_GET['business_id']);
*/


/*Create categories for cleanse table*/
/*
if( isset($_GET['segment']) && is_numeric($_GET['segment']) )
    ProductAction::createCategoriesInCleanseTable($_GET['segment']);
*/

/**Create Products Using Bulk API For all businesses */
/*
if( isset($_GET['segment']) && is_numeric($_GET['segment']) )
    ProductAction::createProductsForAllBusinesses($_GET['segment']);
*/


/**Remove Categories */
/*
if( isset($_GET['segment']) && is_numeric($_GET['segment']) )
    ProductAction::removeCategories($_GET['segment']);
*/
    

echo "<hr>Exeuted within " . intval(microtime(true) - $start_time) . "seconds.";