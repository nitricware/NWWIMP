<?php
  include './Tonic.php';
  use Tonic\Tonic as Tonic;

  $url = 'http://www.wienerlinien.at/ogd_routing/XML_TRIP_REQUEST2?locationServerActive=1&type_origin=coord&name_origin=16.347026:48.205452:WGS84&type_destination=coord&name_destination=16.353268603044167:48.19936173021695:WGS84';

  $wlSender = ''; // API-Key for Wiener Linien API

  $routingRawUrl = 'http://www.wienerlinien.at/ogd_routing/XML_TRIP_REQUEST2?locationServerActive=1&type_origin=coord&name_origin=%f:%f:WGS84&type_destination=coord&name_destination=%f:%f:WGS84&sender='.$wlSender;

  $reverseGeocodingRawUrl = 'http://data.wien.gv.at/daten/OGDAddressService.svc/ReverseGeocode?location=%f,%f&crs=EPSG:4326&type=A3:8012';

  $punschUrl = 'http://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:ADVENTMARKTOGD&srsName=EPSG:4326&outputFormat=json';

  //$location = ['latitude' => $_GET['latitude'], 'longitude' => $_GET['longitude']];

  const location = ['latitude' => 48.205452, 'longitude' => 16.347026];

  function request($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }

  function isClose($coordinates){
    $minLat = location['latitude'] - 0.01;
    $maxLat = location['latitude'] + 0.01;
    $minLong = location['longitude'] - 0.01;
    $maxLong = location['longitude'] + 0.01;
    if ($coordinates[1] > $minLat && $coordinates[1] < $maxLat && $coordinates[0] > $minLong && $coordinates[0] < $maxLong){
      return true;
    }
    return false;
  }

  function minifyPunsch($punsch){
    $minifyPunsch = ["properties" => (array) $punsch->properties,
                     "coordinates" => (array) $punsch->geometry->coordinates];

    return $minifyPunsch;
  }

  function minifyRoute($route){
    foreach ($route->itdTripRequest->itdItinerary->itdRouteList->itdRoute[0]->itdPartialRouteList[0] as $partialRoute){
      if ($partialRoute->itdMeansOfTransport['name'] == ''){
        $meansOfTransport = (string) $partialRoute->itdMeansOfTransport['productName'];
      } else {
        $meansOfTransport =  (string) $partialRoute->itdMeansOfTransport['name'].' (Ri. '. (string) $partialRoute->itdMeansOfTransport['destination'].')';
      }

      $punschRoute[] = ['origin' => (string) $partialRoute->itdPoint[0]['name'],
                        'destination' => (string)  $partialRoute->itdPoint[1]['name'],
                        'meansOfTransport' => $meansOfTransport];


    }
    return $punschRoute;
  }

  $allPunsch = json_decode(request($punschUrl));
  foreach ($allPunsch->features as $punsch){
    if (isClose($punsch->geometry->coordinates)){
      $closePunsch[]['punsch'] = minifyPunsch($punsch);
    }
  }

  for ($i=0; $i < count($closePunsch); $i++) {
    $routingModifiedUrl = sprintf($routingRawUrl, location['longitude'], location['latitude'],$closePunsch[$i]['punsch']['coordinates'][0],$closePunsch[$i]['punsch']['coordinates'][1]);

    $routingResponse = request($routingModifiedUrl);
    $routingXML = simplexml_load_string($routingResponse);

    $closePunsch[$i]['route'] = minifyRoute($routingXML);
  }

  $tpl = new Tonic("display.html");
  $tpl->location = location;
  $tpl->closePunsch = $closePunsch;
  echo $tpl->render();
