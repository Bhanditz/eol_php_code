<?php
namespace php_active_record;
/* connector for National Museum of Natural History Image Collection
estimated execution time: 33 secs.
Connector reads the XML provided by partner and 
- sets the image rating.
- If needed ingests TypeInformation text dataObjects
*/
include_once(dirname(__FILE__) . "/../../config/environment.php");
require_library('ResourceDataObjectElementsSetting');

$timestart = time_elapsed();
$resource_id = 342; 
$resource_path = "http://collections.mnh.si.edu/services/eol/nmnh-fishes-response.xml.gz"; //Fishes resource

$result = $GLOBALS['db_connection']->select("SELECT accesspoint_url FROM resources WHERE id=$resource_id");
$row = $result->fetch_row();
$new_resource_path = $row[0];
if($resource_path != $new_resource_path && $new_resource_path != '') $resource_path = $new_resource_path;
echo "\n processing resource:\n $resource_path \n\n";

$nmnh = new ResourceDataObjectElementsSetting($resource_id, $resource_path, 'http://purl.org/dc/dcmitype/StillImage', 2);
$xml = $nmnh->set_data_object_rating_on_xml_document();

require_library('connectors/INBioAPI');
$xml = INBioAPI::assign_eol_subjects($xml);

$nmnh->save_resource_document($xml);
Functions::set_resource_status_to_harvest_requested($resource_id);
$elapsed_time_sec = time_elapsed() - $timestart;
echo "\n";
echo "elapsed time = $elapsed_time_sec seconds             \n";
echo "elapsed time = " . $elapsed_time_sec/60 . " minutes  \n";
echo "elapsed time = " . $elapsed_time_sec/60/60 . " hours \n";
echo "\n\n Done processing.";
?>