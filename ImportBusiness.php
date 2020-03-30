<?php
class ImportBusiness{
	private static $openingHoursFields = [
		'openhour1',
		'openminute1',
		'closehour1',
		'closeminute1',
		'openhour2',
		'openminute2',
		'closehour2',
		'closeminute2',
		'openhour3',
		'openminute3',
		'closehour3',
		'closeminute3',
		'openhour4',
		'openminute4',
		'closehour4',
		'closeminute4',
	];
	private static $fetchingAttributes = [
		'name',
		'email',
		'slug',
		'description',
		'about',
		'phone',
		'cellphone',
		'owner_id',
		'city_id',
		'address',
		'address_notes',
		'zipcode',
		'featured',
		'timezone',
		'currency',
		'printer_id',
		'minimum',
		'delivery_price',
		'always_deliver',
		'tax_type',
		'tax',
		'delivery_time',
		'pickup_time',
		'service_fee',
		'fixed_usage_fee',
		'percentage_usage_fee',
		'enabled',
		'food',
		'alcohol',
		'groceries',
		'laundry',
		'menus_count',
		'logo',
		'header',
		'schedule'
	];

	private static function getAllBusinesses(){
		$ch = curl_init();
	
		curl_setopt($ch, CURLOPT_URL, "https://apiv4.ordering.co/v400/en/kwik/business?mode=dashboard&type=1&params=" . implode(',', self::$fetchingAttributes));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/json',
			'x-api-key: YX5MAaAfwivM6zaVXYhcOznYzNQwrzaQlx2ykXsnlKZvHAn6Zw5fCgsahm8OPp-3c'
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		
		return json_decode($output)->result;
		curl_close($ch);
	}


	public static function importBizToMysql(){

		$con = new mysqli('localhost','root','root','pronto');

		$allBiz = self::getAllBusinesses();

		$columnNames = 'business_id,' . implode(',', self::$fetchingAttributes) . ',' . 'created_at';

		$batchSize = 500;
		$sql = "INSERT INTO business($columnNames) VALUES ";
		foreach( $allBiz as $index => $bizData ){

			$columnValues = "'" . $bizData->id . "'";
			foreach( self::$fetchingAttributes as $field ){
				$fieldVal = $bizData->$field;
				if( $field == 'schedule' )
					$fieldVal = json_encode($fieldVal);
				$columnValues = $columnValues . ",'" . str_replace("'", "\'", $fieldVal) . "'";
			}
			$columnValues .= ",'" . date('Y-m-d H:i:s') . "'";
			$sql .= "($columnValues),";

			if( ($index+1) % $batchSize == 0 ){
				$execute = $con->query(rtrim($sql, ","));
				if( $execute )
					echo "executed<br>";
				else
				{
					echo "Failed<br>";
				}
				$sql = "INSERT INTO business($columnNames) VALUES ";
			}
		}
		$execute = $con->query(rtrim($sql, ","));
	}
}

ImportBusiness::importBizToMysql();