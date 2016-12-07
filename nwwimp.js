var wlSender = '' // API-Key for Wiener Linien API

var routingRawUrl = 'http://www.wienerlinien.at/ogd_routing/XML_TRIP_REQUEST2?locationServerActive=1&type_origin=coord&name_origin={0}:{1}:WGS84&type_destination=coord&name_destination={2}:{3}:WGS84&sender='+wlSender

var reverseGeocodingRawUrl = 'http://data.wien.gv.at/daten/OGDAddressService.svc/ReverseGeocode?location={0},{1}&crs=EPSG:4326&type=A3:8012'
var punschUrl = 'http://data.wien.gv.at/daten/geo?service=WFS&request=GetFeature&version=1.1.0&typeName=ogdwien:ADVENTMARKTOGD&srsName=EPSG:4326&outputFormat=json'
var myLocation
var closePunsch = []
var myPunsch = []
/*navigator.geolocation.getCurrentPosition(function(location){
  myLocation = location.coords
  var minLat = myLocation.latitude - 0.05
  var maxLat = myLocation.latitude + 0.05
  var minLong = myLocation.longitude - 0.05
  var maxLong = myLocation.longitude + 0.05
});*/

myLocation = {'latitude': 48.205452, 'longitude': 16.347026}
var minLat = myLocation.latitude - 0.01
var maxLat = myLocation.latitude + 0.01
var minLong = myLocation.longitude - 0.01
var maxLong = myLocation.longitude + 0.01
var myAddress = "Neustiftgasse 83"

// First, checks if it isn't implemented yet.
if (!String.prototype.format) {
  String.prototype.format = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) {
      return typeof args[number] != 'undefined'
        ? args[number]
        : match
      ;
    });
  };
}

function isClose(coordinates){
  if (coordinates[1] > minLat && coordinates[1] < maxLat && coordinates[0] > minLong && coordinates[0] < maxLong){
    return true
  }
  return false
}

function createNewPunsch(data,status,xhr,punsch){
  var address = data.features[0].properties.Adresse
  myPunsch.push([punsch,address])
  return true
}

function drawRoute(data,status,xhr){
  console.log("first data: "+data)
}

function parsePunschRequest(data, status, xhr){
  data.features.forEach(function(punsch){
    if (isClose(punsch.geometry.coordinates)){
      closePunsch.push(punsch)
    }
  })
  var requests = []
  closePunsch.forEach(function(punsch){
    var modifiedGeoLocationUrl = reverseGeocodingRawUrl.format(punsch.geometry.coordinates[0], punsch.geometry.coordinates[1])
    requests.push($.getJSON(modifiedGeoLocationUrl,function(data,status,xhr){
      createNewPunsch(data,status,xhr,punsch)
    }))
  })

  defer = $.when.apply($, requests)
  defer.done(function(){
    myPunsch.forEach(function(punsch){
      var modifiedRoutingUrl = routingRawUrl.format(myLocation.longitude,myLocation.latitude,punsch[0].geometry.coordinates[0],punsch[0].geometry.coordinates[1])
      console.log(modifiedRoutingUrl)
      $.get(modifiedRoutingUrl,drawRoute,'xml')
    })
  })

  return true
}

function findPunschAddress(data){
  var modifiedGeoLocationUrl = reverseGeocodingRawUrl.format(data[0], data[1]);
  var punschAddress
  return $.getJSON(modifiedGeoLocationUrl)
}

$(document).ready(function(){
  $.getJSON(punschUrl, parsePunschRequest)
})
