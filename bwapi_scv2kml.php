<?php header('Content-Type: application/vnd.google-earth.kml+xml; charset=utf-8'); 
header('Content-disposition: attachment;filename="bwapi.kml"');
include("shared_inc/wiki_functions.inc.php");
?>
<?php 
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<kml xmlns="http://www.opengis.net/kml/2.2">';

$lat_url = $_REQUEST['lat'] + 0.0;
$lon_url = $_REQUEST['lon'] + 0.0;
$distance  = $_REQUEST['dist'] + 0.0;
$page = "https://tools.wmflabs.org/request/bwAPI/export/Bilderwuensche.csv?lat=$lat_url&lon=$lon_url&distance=$distance";
// var_dump($page);
$csvContent = curl_request ($page);
$rows = explode("\n", $csvContent);

$bFirst = true;
foreach($rows as $row)
{
    if($bFirst)
    {
        $bFirst = false;
        continue;
    }
    $cols = explode(';', str_replace('_', ' ', $row));
    // var_dump($cols);
    $name = $cols[3];
    $descr = $cols[4];
    $lat = $cols[1];
    $lon = $cols[2];
    
    echo "<Placemark>\n<name>$name</name>\n<description>$descr</description>\n<Point><coordinates>$lon,$lat</coordinates></Point>\n</Placemark>\n";
}


?>
</kml>

