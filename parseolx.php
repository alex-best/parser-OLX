<?php
require 'vendor/autoload.php';
 
use XPathSelector\Selector;
use GuzzleHttp\Client;

function parsePage($url) {

	$client = new Client(['cookies' => true]);
	$clientPhone = new Client(['cookies' => true]);
	$apartment = [];
	$properties = [];
	preg_match_all ('|ID(.*).html|sU', $url, $id, PREG_SET_ORDER);

	$headers = [
	    'headers' => [
	        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
	        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
	    ]
	];

	$headersPhone = [
	    'headers' => [
	        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
	        'Accept' => 'application/json; charset=utf-8',
	        'X-Requested-With'  => 'X-Requested-With:XMLHttpRequest'
	    ]
	];


	$dom = Selector::loadHTML($client->get($url, $headers )->getBody());
	 
	/*GET MAIN INFORMATION */
	$apartment["title"] = $dom->find('//*[@id="offerdescription"]/div[2]/h1')->extract();
	$apartment["rooms"] = $dom->find('(//td[@class="value"])[3]')->extract();
	$apartment["text"]  = $dom->findOneOrNull('//*[@id="textContent"]/p')->extract();
	$apartment["date"]  = $dom->findOneOrNull('//*[@id="offerdescription"]/div[2]/div[1]/em')->extract();
	$apartment["advid"]  = $dom->findOneOrNull('//*[@id="offerdescription"]/div[2]/div[1]/em/small')->extract();
	$apartment["url"]  = $url;

	if($dom->findOneOrNull('//*[@id="offeractions"]/div[1]/strong') !== null){ 
		$apartment["price"] = $dom->find('//*[@id="offeractions"]/div[1]/strong')->extract();
	} else $apartment["price"] = '0';

	if($dom->findOneOrNull('//*[@id="offeractions"]/div[4]/div[2]/h4') !== null){ 
		$apartment["name"] = $dom->findOneOrNull('//*[@id="offeractions"]/div[4]/div[2]/h4')->extract();
	} elseif($dom->findOneOrNull('//div[@class="offer-user__details"]/h4') !== null){
		$apartment["name"] = $dom->findOneOrNull('//div[@class="offer-user__details"]/h4')->extract();
	}

	/*GET PROPERTIES */
	if($dom->findOneOrNull('(//table[@class="item"])[4]') !== null){ 
		$prop1 = $dom->findOneOrNull('(//table[@class="item"])[4]')->extract();
		$properties["prop1"] = $prop1;
	}
	if($dom->findOneOrNull('(//table[@class="item"])[5]') !== null){ 
		$prop2 = $dom->findOneOrNull('(//table[@class="item"])[5]')->extract();
		$properties["prop2"] = $prop2;
	}
	if($dom->findOneOrNull('(//table[@class="item"])[6]') !== null){ 
		$prop3 = $dom->findOneOrNull('(//table[@class="item"])[6]')->extract();
		$properties["prop3"] = $prop3;
	}	
	if($dom->findOneOrNull('(//table[@class="item"])[7]') !== null){ 
		$prop4 = $dom->findOneOrNull('(//table[@class="item"])[7]')->extract();
		$properties["prop4"] = $prop4;
	}	
	if($dom->findOneOrNull('(//table[@class="item"])[8]') !== null){ 
		$prop5 = $dom->findOneOrNull('(//table[@class="item"])[8]')->extract();
		$properties["prop5"] = $prop5;
	}	
	if($dom->findOneOrNull('(//table[@class="item"])[9]') !== null){ 
		$prop6 = $dom->findOneOrNull('(//table[@class="item"])[9]')->extract();
		$properties["prop6"] = $prop6;
	}
	
	foreach ( $properties as $key => $value ) {
	  if (strpos($value, "Общая площадь") !== false) $apartment["area_all"] = $value;
	  if (strpos($value, "Жилая площадь") !== false) $apartment["area_live"] = $value;
	  if (strpos($value, "Площадь кухни") !== false) $apartment["area_kitchen"] = $value;
	  if ((strpos($value, "Этаж") !== false) && (strpos($value, "дома") === false)) $apartment["floor"] = $value;
	  if (strpos($value, "Этажность дома") !== false) $apartment["floor_all"] = $value;
	}
	//GET INFORMATION ABOUT PHONE EXIST
	if ($dom->findOneOrNull('///*[@id="contact_methods"]') !== null){
		$phoneExist = $dom->findOneOrNull('///*[@id="contact_methods"]')->extract();
		$phoneOnlyNumbers = preg_replace('~\D+~','',$phoneExist);	
	} 
	

	/*GET PHONE*/
	if(iconv_strlen($phoneOnlyNumbers) > 0){
		$sleepTime = mt_rand(2, 8);
		sleep($sleepTime);

		$domPhone = $client->get('https://www.olx.ua/ajax/misc/contact/phone/'.$id[0][1].'/white/', $headersPhone )->getBody();
		$obj = json_decode($domPhone);
		$apartment["phone"] = $obj->value;	
		// $apartment["phone"] = preg_replace('/\+38/','', $apartment["phone"]);

	}

	pushAppartmen($apartment);
}

function getLinks(){
	$client = new Client();

	$headers = [
	    'headers' => [
	        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
	        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
	    ]
	];

	$dom = Selector::loadHTML($client->get('https://www.olx.ua/nedvizhimost/prodazha-kvartir/vtorichnyy-rynok/kirovograd/?search%5Bprivate_business%5D=private', $headers )->getBody());

	$links = $dom->findAll('//td[starts-with(@class, "offer ")]')->map(function ($node) {
	    return [
	        'cover_url' => $node->find('.//a[@class="marginright5 link linkWithHash detailsLink"]/@href')->extract()
	    ];
	});

return $links;

}

function pushAppartmen($apartment){

	$date = convertDate($apartment["date"]);
	$today = date("d.m.Y");
	$yesterdayUnix  = mktime(0, 0, 0, date("m")  , date("d")-1, date("Y"));
	$yesterday = date("d.m.Y", $yesterdayUnix);  	

	$price += convertPrice($apartment["price"]);
	$rooms += preg_replace('~\D+~','',$apartment["rooms"]) ;
	$name = $apartment["name"];
	$url = $apartment["url"];
	$advid += preg_replace('~\D+~','',$apartment["advid"]);

	$comment = $apartment["title"] . '<br>' . $apartment["text"];

	if(isset($apartment["floor"])) {
		$floor += preg_replace('~\D+~','',$apartment["floor"]);
	} else $floor = 0;

	if(isset($apartment["floor_all"])){
		$floor_all += preg_replace('~\D+~','',$apartment["floor_all"]);
	} else $floor_all = 0;

	if(isset($apartment["area_all"])){
		$area_all_explode = explode(" ", $apartment["area_all"]);
		$area_all += preg_replace('~\D+~','',$area_all_explode[1]);
	} else $area_all = 0;	

	if(isset($apartment["area_live"])){
		$area_live_explode = explode(" ", $apartment["area_live"]);
		$area_live += preg_replace('~\D+~','',$area_live_explode[1]);
	} else $area_live = 0;	

	if(isset($apartment["area_kitchen"])){
		$area_kitchen_explode = explode(" ", $apartment["area_kitchen"]);
		$area_kitchen += preg_replace('~\D+~','',$area_kitchen_explode[1]);
	} else $area_kitchen = 0;
	
	
	/*PHONE*/
	if(iconv_strlen($apartment["phone"]) > 0 ){
		$phoneClear = preg_replace('/\+38/','', $apartment["phone"]);
		$phoneOnlyNumbers = preg_replace('~\D+~','',$phoneClear); 

		if(iconv_strlen($phoneOnlyNumbers) == 20){
			$phone = str_split($phoneOnlyNumbers, 10);
		} elseif(iconv_strlen($phoneOnlyNumbers) == 30){
			$phone = str_split($phoneOnlyNumbers, 10);
		} else $phone = array($phoneOnlyNumbers, "0000000000");
	} else $phone =array("0000000000", "0000000000");
	/*END PHONE*/


	/*IF DATE ADV != TODAY OR YESTERDAY -> RETURN FALSE*/
	if(strtotime($date) === strtotime($today) || strtotime($date) === strtotime($yesterday)){

		/*CHECK ADV ID IN BD*/
		$db = new PDO('mysql:host=localhost;dbname=favorit_db', '*****', '********');
		$db->exec("set names utf8");
		$advidForQueri = $db->quote($advid);
		$sqlCheckadvid = "SELECT * FROM wp_realt WHERE advid =".$advidForQueri;
		$stmt = $db->query($sqlCheckadvid);
		$resultCheck = $stmt->FETCH(PDO::FETCH_NUM);

		/*IF advid NOT FIND -> INSERT IN DB, ELSE -> UPDATE ENTRY */
		if($resultCheck === false){
		$insertNew = $db->prepare("INSERT INTO wp_realt(type_appart, rooms, floors, floors_to, area_all, area_live, area_kitchen, price, phone, phone2, source, comment, contacts, add_date, advid, url, street)
			    VALUES(:type_appart, :rooms, :floors, :floors_to, :area_all, :area_live, :area_kitchen, :price, :phone, :phone2, :source, :comment, :contacts, :add_date, :advid,:url, :street)");
			$insertNew->execute(array(
			    "type_appart" => "type_flat",
			    "rooms" => $rooms,
			    "floors" => $floor,
			    "floors_to" => $floor_all,
			    "area_all" => $area_all,
			    "area_live" => $area_live,
			    "area_kitchen" => $area_kitchen,
			    "price" => $price,
			    "phone" => $phone[0],
			    "phone2" => $phone[1],
			    "source" => "OLX",
			    "comment" => $comment,
			    "contacts" => $name,
			    "add_date" => $date,
			    "advid" => $advid,
			    "url" => $url,
			    "street" => "0"
			));
		} else {

			$sql = "UPDATE wp_realt SET 
						rooms = :rooms, 
			            floors = :floors, 
			            floors_to = :floors_to,  
			            area_all = :area_all,  
			            area_live = :area_live,
			            area_kitchen = :area_kitchen,
			            price = :price,
			            phone = :phone,
			            phone2 = :phone2,
			            comment = :comment,
			            add_date = :add_date
			            WHERE advid = :advid";
			$stmt = $db->prepare($sql);                                  
			$stmt->bindParam(':rooms', $rooms, PDO::PARAM_STR);       
			$stmt->bindParam(':floors', $floor, PDO::PARAM_STR);    
			$stmt->bindParam(':floors_to', $floor_all, PDO::PARAM_STR);
			$stmt->bindParam(':area_all', $area_all, PDO::PARAM_STR); 
			$stmt->bindParam(':area_live', $area_live, PDO::PARAM_STR); 
			$stmt->bindParam(':area_kitchen', $area_kitchen, PDO::PARAM_STR); 
			$stmt->bindParam(':price', $price, PDO::PARAM_STR); 
			$stmt->bindParam(':phone', $phone[0], PDO::PARAM_STR); 
			$stmt->bindParam(':phone2', $phone[1], PDO::PARAM_STR); 
			$stmt->bindParam(':comment', $comment, PDO::PARAM_STR); 
			$stmt->bindParam(':add_date', $date, PDO::PARAM_STR); 
			$stmt->bindParam(':advid', $advid, PDO::PARAM_INT);   
			$stmt->execute(); 

		}
	} else {

	}
		

}

function convertPrice($priceRow){

	if(strpos($priceRow, "грн") === false) {
		$priceQuote = preg_quote($priceRow, '/');
		$price = preg_replace('~\D+~','',$priceQuote); 	
	} else {
		$client = new Client();
		$getJsonExchangeRate = $client->get('http://openrates.in.ua/rates')->getBody();
		$convertExchangeRate = json_decode($getJsonExchangeRate);
		$exchangeRate = $convertExchangeRate->USD->nbu->sell;
		$priceOnlyNumbers = preg_replace('~\D+~','',$priceRow);
		$price = (int) ($priceOnlyNumbers / $exchangeRate);
	}

return $price;

}

function convertDate($dateRow){
	$monthNames=[
	 "января" 	=> "01",
	 "февраля"	=> "02",
	 "марта"  	=> "03",
	 "апреля" 	=> "04",
	 "мая"	  	=> "05",
	 "июня"	  	=> "06",
	 "июля"   	=> "07",
	 "августа"	=> "08",
	 "сентября" => "09",
	 "октября"  => "10",
	 "ноября"   => "11",
	 "декабря"  => "12"
	 ];

	$parseDate = explode(",", $dateRow);

	$dateArray = explode(" ", $parseDate[1]);

	if(iconv_strlen($dateArray[1]) == 1) {
		$date = '0'.$dateArray[1];	
	} else $date = $dateArray[1];

	foreach ($monthNames as $key => $value) {
		if($key == $dateArray[2]) $date = $date . "." . $value;
	}
	$date = $date . "." . $dateArray[3];

return $date;
}


$linksFlat = getLinks();

	for($i = 0; $i < count($linksFlat) ; $i++){
		$sleepTime = mt_rand(5, 15);
		sleep($sleepTime);

		parsePage($linksFlat[$i]["cover_url"]);	
	}

?>
