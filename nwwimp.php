<?php
  include './Tonic.php';
  use Tonic\Tonic as Tonic;
  /**
   * The API Key for the Wiener Linien Routing API
   * @var string
   */
  $wlSender = '';

  /**
   * URL for the Wiener Linien Routing API
   * @var string
   */

  $routingRawUrl = 'http://www.wienerlinien.at/ogd_routing/XML_TRIP_REQUEST2?locationServerActive=1&type_origin=coord&name_origin=%f:%f:WGS84&type_destination=coord&name_destination=%f:%f:WGS84&sender='.$wlSender;

  /**
   * Reverse geocoding url by the City Of Vienna.
   * With placeholder for coordinates.
   * Not used in this version of the program.
   * @var string
   */

  $reverseGeocodingRawUrl = 'http://data.wien.gv.at/daten/OGDAddressService.svc/ReverseGeocode?location=%f,%f&crs=EPSG:4326&type=A3:8012';

  /**
   * Url for the christmas market data from the City Of Vienna.
   * @var string
   */

  $punschUrl = 'http://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:ADVENTMARKTOGD&srsName=EPSG:4326&outputFormat=json';

  define('latitude', (float) $_GET['latitude']);
  define('longitude', (float) $_GET['longitude']);

  /**
   * Function for various curl requests made throughout the program.
   * @param  string $url The url to be called.
   * @return string      Returns the content of the response.
   */
  function request($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }

  /**
   * Checks if the coordinates are in a close range of each your gps location.
   * GPS location is given as GET Parameter by a JS-File (index.html) and
   * defined as constant some lines above.
   * @param  array   $coordinates The coordinates in an array.
   * @return boolean              True if coordinates are close
   */
  function isClose($coordinates){
    $minLat = latitude - 0.01;
    $maxLat = latitude + 0.01;
    $minLong = longitude - 0.01;
    $maxLong = longitude + 0.01;
    if ($coordinates[1] > $minLat && $coordinates[1] < $maxLat && $coordinates[0] > $minLong && $coordinates[0] < $maxLong){
      return true;
    }
    return false;
  }

  /**
   * Returns a minified version of the object that contains data from the
   * christmas market.
   * @param  object $punsch The SimpleXMLObject containing data
   * @return array          Contains the minified version
   */

  function minifyPunsch($punsch){
    $minifyPunsch = ["properties" => (array) $punsch->properties,
                     "coordinates" => (array) $punsch->geometry->coordinates];

    return $minifyPunsch;
  }

  /**
   * Wiener Linien Routing API return departure time somewhat unconvenient.
   * 08:09 is 8:9 - This function corrects that.
   * @param  int $int number witout leading zero
   * @return int      number with leading zero
   */
  function correctTime($int){
    if ($int < 10){
      return '0'.$int;
    }

    return $int;
  }

  /**
   * Minifes the route XML to an Array
   * @param  object $route SimpleXMLObject containing all the information.
   * @return array         Contains only important information
   */
  
  function minifyRoute($route){
    foreach ($route->itdTripRequest->itdItinerary->itdRouteList->itdRoute[0]->itdPartialRouteList[0] as $partialRoute){
      $walkway = 2;

      if ($partialRoute->itdMeansOfTransport['productName'] == "Fussweg"){
        $walkway = 1;
        $cssSelector = "transportWalk";
      } else if($partialRoute->itdMeansOfTransport['productName'] == "U-Bahn"){
        $cssSelector = "transport".$partialRoute->itdMeansOfTransport['shortname'];
      } else if ($partialRoute->itdMeansOfTransport['productName'] == "Straßenbahn") {
        $cssSelector = "transportTram";
      } else {
        $cssSelector = "transport".$partialRoute->itdMeansOfTransport['productName'];
      }

      $punschRoute[] = ['origin' =>
                          ["name" => str_replace("Wien, ", '', str_replace("Wien ", '', (string) $partialRoute->itdPoint[0]['name'])),
                           "time" => (string) correctTime($partialRoute->itdPoint[0]->itdDateTime->itdTime['hour']).':'.correctTime($partialRoute->itdPoint[0]->itdDateTime->itdTime['minute'])],
                        'destination' =>
                          ["name" => str_replace("Wien, ", '', str_replace("Wien ", '', (string) $partialRoute->itdPoint[1]['name'])),
                           "time" =>  (string) correctTime($partialRoute->itdPoint[1]->itdDateTime->itdTime['hour']).':'.correctTime($partialRoute->itdPoint[1]->itdDateTime->itdTime['minute'])],
                        'meansOfTransport' =>
                          ['type' => (string) $partialRoute->itdMeansOfTransport['productName'],
                           'linenumber' => (string) $partialRoute->itdMeansOfTransport['shortname'],
                           'direction' => str_replace("Wien, ", '', str_replace("Wien ", '',(string) $partialRoute->itdMeansOfTransport['destination']))
                         ],
                         'isWalkWay' => $walkway,
                         'cssSelector' => $cssSelector];
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
    $routingModifiedUrl = sprintf($routingRawUrl, longitude, latitude,$closePunsch[$i]['punsch']['coordinates'][0],$closePunsch[$i]['punsch']['coordinates'][1]);

    $routingResponse = request($routingModifiedUrl);
    $routingXML = simplexml_load_string($routingResponse);

    $closePunsch[$i]['route'] = minifyRoute($routingXML);
  }

  $tpl = new Tonic("display.html");
  $tpl->location = ['latitude' => latitude, 'longitude' => longitude];
  $tpl->closePunsch = $closePunsch;
  $tpl->fussweg = "Fussweg";
  echo $tpl->render();
