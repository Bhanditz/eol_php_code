<?php
namespace php_active_record;
/* https://jira.eol.org/browse/DATA-1549 iDigBio Portal 
                5k                  4Feb
measurement     6748    1385056     3191194
occurrence      2250    461686      461686
taxon           2157    224065      224065
reference							189866
*/
return;
include_once(dirname(__FILE__) . "/../../config/environment.php");
require_library('connectors/GBIFCountryTypeRecordAPI');
$timestart = time_elapsed();

/*
//local
$params["dwca_file"]    = "http://localhost/cp/iDigBio/iDigBioTypes.zip";
$params["uri_file"]     = "http://localhost/cp/iDigBio/idigbio mappings.xlsx";
*/

/*
//remote
$params["dwca_file"]    = "";
$params["uri_file"]     = "https://dl.dropboxusercontent.com/u/7597512/iDigBio/idigbio mappings.xlsx";
*/

$params["dataset"]      = "iDigBio";
$params["type"]         = "structured data";

$fields["institutionCode"]  = "institutionCode_uri";
$fields["sex"]              = "sex_uri";
$fields["typeStatus"]       = "typeStatus_uri";
$fields["lifeStage"]        = "lifeStage_uri";
$params["fields"] = $fields;

$params["resource_id"]  = 885;

$resource_id = $params["resource_id"];
$func = new GBIFCountryTypeRecordAPI($resource_id);
$func->export_gbif_to_eol($params);
Functions::finalize_dwca_resource($resource_id);
$elapsed_time_sec = time_elapsed() - $timestart;
echo "\n\n";
echo "elapsed time = " . $elapsed_time_sec/60 . " minutes \n";
echo "elapsed time = " . $elapsed_time_sec/60/60 . " hours \n";
echo "\nDone processing.\n";
?>