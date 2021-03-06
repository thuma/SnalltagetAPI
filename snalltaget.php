<?php
if(isset($_GET['debugg']))
	{
	error_reporting(E_ALL);
	ini_set('display_errors', 'on');
	}

if (isset($_GET['from']) == FALSE)
	{
	die('{"error":"data from missing"}');
	}

if (isset($_GET['to']) == FALSE)
	{
	die('{"error":"data to missing"}');
	}

if (isset($_GET['date']) == FALSE)
	{
	die('{"error":"data date missing"}');
	}

if (isset($_GET['departureTime']) == FALSE)
	{
	die('{"error":"data from missing"}');
	}

$ch = curl_init();
$temp = tmpfile();

curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt($ch, CURLOPT_URL, 'https://boka.snalltaget.se/boka-biljett');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Accept-Encoding: gzip,deflate,sdch' 
, 'Host: boka.snalltaget.se' 
, 'Accept-Language: sv-SE,sv;q=0.8,en-US;q=0.6,en;q=0.4'
, 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/30.0.1599.114 Chrome/30.0.1599.114 Safari/537.36' 
, 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8' 
, 'Cache-Control: max-age=0'));
curl_setopt( $ch, CURLOPT_COOKIEJAR,  $temp );
curl_setopt( $ch, CURLOPT_COOKIEFILE, $temp );



if(file_exists('snalltagetcache/snalltaget.json') === TRUE)
	
	{
	$allstops = json_decode(file_get_contents('snalltagetcache/snalltaget.json'));
	}

else	
	{
	curl_exec($ch);
	curl_setopt($ch, CURLOPT_URL, 'https://boka.snalltaget.se/api/locations/all');
	$data = curl_exec($ch);
	$allstops = json_decode($data);
	file_put_contents('snalltagetcache/snalltaget.json',json_encode($allstops));
	}

$stoparray = array();
foreach($allstops->frequent as $stop)
	{
	$stoparray[strval(intval($stop->P)*100000+intval($stop->L))] = $stop;
	}

foreach($allstops->infrequent as $stop)
	{
	$stoparray[strval(intval($stop->P)*100000+intval($stop->L))] = $stop;
	}

if(isset($stoparray[$_GET['to']]) AND isset($stoparray[$_GET['from']]))
	{		
	curl_exec($ch);
	}
else	{
	die('{"error":"stop not in network"}');
	}

curl_setopt($ch, CURLOPT_URL, 'https://boka.snalltaget.se/api/timetables');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Origin: https://boka.snalltaget.se'
,'Accept-Encoding: gzip,deflate,sdch' 
,'Host: boka.snalltaget.se'
,'Accept-Language: sv-SE' 
,'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/30.0.1599.114 Chrome/30.0.1599.114 Safari/537.36' 
,'Content-Type: application/json;charset=UTF-8' 
,'Accept: application/json, text/plain, */*' 
,'Referer: https://boka.snalltaget.se/boka-biljett' 
,'Connection: keep-alive'));

$request = '{"DepartureLocationId":'.intval(substr($_GET['from'],2)).
',"DepartureLocationProducerCode":'.intval(substr($_GET['from'],0,2)).
',"ArrivalLocationId":'.intval(substr($_GET['to'],2)).
',"ArrivalLocationProducerCode":'.intval(substr($_GET['to'],0,2)).
',"DepartureDateTime":"'.$_GET['date'].' '.$_GET['departureTime'].'"'.
',"TravelType":"E","Passengers":[{"PassengerCategory":"VU"}]}';

curl_setopt($ch, CURLOPT_POSTFIELDS,$request);
curl_setopt($ch, CURLOPT_POST, TRUE);
$triplist = json_decode(curl_exec($ch));

$list = "";
$comma = "";
foreach($triplist->JourneyAdvices as $refid){
$list = $list.$comma.$refid->JourneyConnectionReference;
$comma = ",";
}

curl_setopt($ch, CURLOPT_URL, 'https://boka.snalltaget.se/api/journeyadvices/lowestprices');
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"TimetableId":"'.$triplist->Id.'","JourneyConnectionReferences":['.$list.']}');
$price = json_decode(curl_exec($ch));

$findprice = array();

foreach($price as $row){
$findprice[$row->JourneyConnectionReference]=$row;
}

foreach($triplist->JourneyAdvices as $key => $refid){
$triplist->JourneyAdvices[$key]->PriceData = $findprice[$refid->JourneyConnectionReference];
}

fclose($temp);

print json_encode($triplist);

// 'https://boka.snalltaget.se/boka-biljett#!/step1?from=1&to=120&date=2013-12-15';

?>
