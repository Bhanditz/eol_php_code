#!/usr/local/bin/php
<?php
//#!/usr/local/bin/php
//connector for WORMS
     
//exit;

/* 
22966. 22964 of 68984
*/

set_time_limit(0);
ini_set('memory_limit','3500M');
//define("ENVIRONMENT", "development");
define("ENVIRONMENT", "slave_32");
define("MYSQL_DEBUG", true);
define("DEBUG", true);
include_once(dirname(__FILE__) . "/../../config/start.php");
$mysqli =& $GLOBALS['mysqli_connection'];

//only on local; to be deleted before going into production
/*
$mysqli->truncate_tables("development");
Functions::load_fixtures("development");
*/

$resource = new Resource(26);//WORMS

//exit("[$resource->id]");

$old_resource_path = CONTENT_RESOURCE_LOCAL_PATH . $resource->id .".xml";
//$old_resource_path = CONTENT_RESOURCE_LOCAL_PATH . "eli" .".xml";

$OUT = fopen($old_resource_path, "w+");
$str = "<?xml version='1.0' encoding='utf-8' ?>\n";
$str .= "<response\n";
$str .= "  xmlns='http://www.eol.org/transfer/content/0.3'\n";           
$str .= "  xmlns:xsd='http://www.w3.org/2001/XMLSchema'\n";
$str .= "  xmlns:dc='http://purl.org/dc/elements/1.1/'\n";           
$str .= "  xmlns:dcterms='http://purl.org/dc/terms/'\n";           
$str .= "  xmlns:geo='http://www.w3.org/2003/01/geo/wgs84_pos#'\n";           
$str .= "  xmlns:dwc='http://rs.tdwg.org/dwc/dwcore/'\n";           
$str .= "  xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'\n";                      
$str .= "  xsi:schemaLocation='http://www.eol.org/transfer/content/0.3 http://services.eol.org/schema/content_0_3.xsd'>\n";
fwrite($OUT, $str);

$main_count=0;
//====================================================================================
$main_id_list = array();
$id_processed = array();
$main_id_list = get_main_id_list();
echo "\n total taxid count = " . count($main_id_list) . "\n\n";;
//exit;
//====================================================================================
$i=1;

//while( count($id_processed) != count($main_id_list) )
//{
    echo "-x- \n";    
    for ($i = 0; $i < count($main_id_list); $i++)     
    {
        $taxid = $main_id_list[$i];
        if(!in_array("$taxid", $id_processed))        
        {                        
            if(process($taxid)) $id_processed[] = $taxid;
            echo $i+1 . ". ";            
            echo count($id_processed) . " of " . count($main_id_list) . "\n";
        }                
    }    
//}
//print_r($main_id_list);print_r($id_processed);
//====================================================================================
$str = "</response>";
fwrite($OUT, $str);
fclose($OUT);
//====================================================================================
        
//start functions #################################################################################################
function process($id)
{
    global $OUT;
    
    $file = "http://www.marinespecies.org/aphia.php?p=eol&action=taxdetails&id=$id";
    //       http://www.marinespecies.org/aphia.php?p=eol&action=taxdetails&id=395770
    
    //if(!($xml = simplexml_load_file($file)))return false;
    if(!($xml = Functions::get_hashed_response($file)))return false;    
          
    $i=0;
    foreach($xml->taxon as $t)
    {
        $i++;    
        //if($i >= 100 and $i <= 105)
        if(true)    
        {                   
            $do_count = sizeof($t->dataObject);
            //if($do_count > 0)
            if(true)
            {
                $t_dwc         = $t->children("http://rs.tdwg.org/dwc/dwcore/");                         
                $t_dc          = $t->children("http://purl.org/dc/elements/1.1/");        
                $t_dcterms     = $t->children("http://purl.org/dc/terms/");        
        
                $identifier = Functions::import_decode($t_dc->identifier);    
                $source     = Functions::import_decode($t_dc->source);    
                $kingdom    = Functions::import_decode($t_dwc->Kingdom);    
                $phylum     = Functions::import_decode($t_dwc->Phylum);    
                $class      = Functions::import_decode($t_dwc->Class);    
                $order      = Functions::import_decode($t_dwc->Order);    
                $family     = Functions::import_decode($t_dwc->Family);    
                $sciname    = Functions::import_decode($t_dwc->ScientificName);               
        
                $genus        = Functions::import_decode($t_dwc->Genus);                       
                $created      = Functions::import_decode($t_dcterms->created);    
                $modified     = Functions::import_decode($t_dcterms->modified);            
           
                $taxonParameters = array();
                $taxonParameters["identifier"]      = $identifier;
                $taxonParameters["source"]          = $source;
                $taxonParameters["kingdom"]         = $kingdom;
                $taxonParameters["phylum"]          = $phylum;
                $taxonParameters["class"]           = $class;
                $taxonParameters["order"]           = $order;
                $taxonParameters["family"]          = $family;
    
                $taxonParameters["scientificName"]  = $sciname;
                $taxonParameters["genus"]           = $genus;            
                $taxonParameters["created"]      = $created;    
                $taxonParameters["modified"]     = $modified;         
             
                $references = array();
                foreach($t->reference as $reference)
                {  
                    $referenceParameters = array();
                    $arr=array();
                    if($reference["url"])
                    {
                        $arr = array("label" => "url" , "value" => $reference["url"]);                
                        $referenceParameters["referenceIdentifiers"] = array();
                        $referenceParameters["referenceIdentifiers"][] = new SchemaReferenceIdentifier($arr);
                    }
                    $referenceParameters["fullReference"] = $reference;
                    $references[] = new SchemaReference($referenceParameters);    
                }
                $taxonParameters["references"] = $references;
     
                //start process dataObjects =====================================================================
                $taxonParameters["dataObjects"] = array();    
                $dataObjects = array();
                $arr = $t->dataObject;
                foreach($arr as $do)
                {
                   $t_dc2      = $do->children("http://purl.org/dc/elements/1.1/");            
                   $t_dcterms  = $do->children("http://purl.org/dc/terms/");                         
                   $dataObjects[] = get_data_object($do,$t_dc2,$t_dcterms);            
                }    
                foreach($dataObjects as $k => $v)
                {
                    $taxonParameters["dataObjects"][] = new SchemaDataObject($v);
                    unset($v);
                }
                //end process dataObjects =====================================================================
        
     
                ///////////////////////////////////////////////////////////////////////////////////    
                $taxa = array();
                $taxa[] = new SchemaTaxon($taxonParameters);    
             
                //$new_resource_xml = SchemaDocument::get_taxon_xml($taxa);
                $str='';
                foreach($taxa as $tax)
                {
                    $str .= $tax->__toXML();
                }        
                fwrite($OUT, $str);
                 
                echo $sciname . " ";      
                ///////////////////////////////////////////////////////////////////////////////////            
            }//if($do > 0)
        }    
    }   

    /* nice debug
    if(substr($sciname,0,8) == "Acropora"){}
    else return true;
    */
    return true;    
    
}//end process() 




function get_data_object($do,$t_dc2,$t_dcterms)
{
    /*
    print $do->agent . " ";  
    print $do->agent["role"] . " \n";  
    print $do->agent["homepage"] . " ";  
    */

    $dataObjectParameters = array();

    $dataObjectParameters["identifier"]      = $t_dc2->identifier;
    $dataObjectParameters["dataType"]        = $do->dataType;
    $dataObjectParameters["mimeType"]        = $do->mimeType;    
    
     
    $dataObjectParameters["description"]         = $t_dc2->description;     
    //$dataObjectParameters["description"]["xml:lang"]      = $t_dc2->description["xml:lang"];
     
    $dataObjectParameters["title"]         = $t_dc2->title;
    //$dataObjectParameters["title"]["xml:lang"]      = $t_dc2->title["xml:lang"];
     
    $dataObjectParameters["language"]     = $t_dc2->language;     
    
    if($do->subject != "")
    {
        $dataObjectParameters["subjects"] = array();
        $subjectParameters = array();
        $subjectParameters["label"] = $do->subject;
        $dataObjectParameters["subjects"][] = new SchemaSubject($subjectParameters);
    }
    
    $agents = array();
    foreach($do->agent as $agent)
    {  
        $agentParameters = array();
        $agentParameters["role"]     = $agent["role"];
        $agentParameters["homepage"] = $agent["homepage"];
        $agentParameters["logoURL"]  = $agent["logoURL"];        
        $agentParameters["fullName"] = $agent;
        $agents[] = new SchemaAgent($agentParameters);
    }
    $dataObjectParameters["agents"] = $agents;
        
    $dataObjectParameters["created"]       = $t_dcterms->created;
    $dataObjectParameters["modified"]      = $t_dcterms->modified;
    $dataObjectParameters["license"]       = $do->license;
    $dataObjectParameters["rightsHolder"]  = $t_dcterms->rightsHolder;    
    $dataObjectParameters["source"]        = $t_dc2->source;
    $dataObjectParameters["mediaURL"]      = $do->mediaURL;
    $dataObjectParameters["thumbnailURL"]  = $do->thumbnailURL;
    $dataObjectParameters["location"]      = $do->location;          
    

    ///////////////////////////////////
    /* working
    $dataObjectParameters["audiences"] = array();    
    $audienceParameters = array();
  
    $audienceParameters["label"] = "Expert users";
    $dataObjectParameters["audiences"][] = new SchemaAudience($audienceParameters);
  
    $audienceParameters["label"] = "General public";
    $dataObjectParameters["audiences"][] = new SchemaAudience($audienceParameters);
    */
    ///////////////////////////////////

    $audiences = array();
    foreach($do->audience as $audience)
    {  
        $audienceParameters = array();
        $audienceParameters["label"]    = $audience;
        $audiences[] = new SchemaAudience($audienceParameters);
    }
    $dataObjectParameters["audiences"] = $audiences;
     
     ///////////////////////////////////     
     
    $references = array();
    foreach($do->reference as $reference)
    {  
        $referenceParameters = array();
        $arr=array();
        if($reference["url"])
        {
             $arr = array("label" => "url" , "value" => $reference["url"]);                
             $referenceParameters["referenceIdentifiers"] = array();
             $referenceParameters["referenceIdentifiers"][] = new SchemaReferenceIdentifier($arr);
        }
        $referenceParameters["fullReference"] = $reference;
        $references[] = new SchemaReference($referenceParameters);    
    }
    $dataObjectParameters["references"] = $references;     
     
    return $dataObjectParameters;
}

function get_main_id_list()
{


    //$url[]="http://127.0.0.1/mtce/WORMS/20090819/id/2007.xml";
    //$url[]="http://127.0.0.1/mtce/WORMS/20090819/id/2008.xml";
    //$url[]="http://127.0.0.1/mtce/WORMS/20090819/id/2009.xml";

    $url[]="http://127.0.0.1/mtce/WORMS/20091016/id/2007.xml";
    $url[]="http://127.0.0.1/mtce/WORMS/20091016/id/2008.xml";
    $url[]="http://127.0.0.1/mtce/WORMS/20091016/id/2009.xml";


    //$url[]="http://www.marinespecies.org/aphia.php?p=eol&action=taxlist&startdate=19960101&enddate=20071231";
    //$url[]="http://www.marinespecies.org/aphia.php?p=eol&action=taxlist&startdate=20080101&enddate=20081231";
    //$url[]="http://www.marinespecies.org/aphia.php?p=eol&action=taxlist&startdate=20090101&enddate=20091231";    
 
    echo "\n URLs = " . sizeof($url) . "\n";
    $no_of_urls = sizeof($url);    
    
    $arr = array(); 
    $jj=0;
    for ($i = 0; $i < count($url); $i++) 
    {
        //echo $url[$i] . "\n";
        $j=0;
        
        //if($xml = @simplexml_load_file($url[$i]))        
        if($xml = Functions::get_hashed_response($url[$i]))        
        {   
            $no_of_taxdetail = count($xml->taxdetail);
            foreach($xml->taxdetail as $taxdetail)
            {
                $temp = @$taxdetail["id"];
                $arr["$temp"]=true;
                $j++; $jj++;
            }    
        }
        echo "\n " . $i+1 . " of " . $no_of_urls . " URLs | taxid count = " . $j . "\n";     
    }
    //exit("total = $jj");
    
    $arr = array_keys($arr);
    return $arr;

}//get_main_id_list()


/*
function get_id_list()
{
    global $OUT;
    global $main_count;

    $id=array();
    $url=array();     
    
    $url[]="http://www.marinespecies.org/aphia.php?p=eol&action=taxlist&startdate=19990101&enddate=19991231";
    $url[]="http://www.marinespecies.org/aphia.php?p=eol&action=taxlist&startdate=19960101&enddate=19961231";
    
    echo "\n URLs = " . sizeof($url) . "\n";
    $no_of_urls = sizeof($url);
     
    for ($i = 0; $i < count($url); $i++) 
    {
        echo $url[$i] . "\n";
        $j=0;
        if($xml = @simplexml_load_file($url[$i]))
        {   
            $no_of_taxdetail = count($xml->taxdetail);
            foreach($xml->taxdetail as $taxdetail)
            {
                $temp = @$taxdetail["id"];
                //$id["$temp"]=true;                        
                if(!in_array("$temp", $id))
                {
                    $i_str = $i + 1;
                    $j_str = $j + 1;
                    
                    $main_count++;                    
                    $run_str =      $main_count . ". " . $i_str . "/" . $no_of_urls . " | " . $j_str . "/" . $no_of_taxdetail . " - ";
                    
                    set_time_limit(0);                    
                    echo $run_str;
                    process($temp,$OUT);
                }
                else echo $j . " - ";        
                $id[] = $temp;        
                $j++;
            }    
        }
        echo "\n total = " . $j . "\n";     
    }
    return $id;
}//function get_id_list()
*/
/*
WORMS puts cdata in the ff:
     <dwc:ScientificName>
     <reference>
     <agent>
     <dc:description xml:lang="en">
*/

?>