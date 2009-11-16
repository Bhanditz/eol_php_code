#!/usr/local/bin/php  
<?php
/*
connector for Public Health Image Library (CDC) 
http://phil.cdc.gov/phil/home.asp
*/

/*
Manual hard-coded changes:

REMOVE: Science,Spiders,Poultry

REPLACE:
Pollen - Ambrosia trifida
siphon,siphon tuft,Siphona irritans,siphonal hairs,siphonal tufts - Culex pipiens
Ticks - CLASS Arachnida, ORDER Acarina
saddle - Psorophora
palmate hairs - Anopheles (mosquito)
pecten,dorsal plate - Aedes (mosquito)
lateral plate - Toxorhynchites mosquito 
lateral pouches - Deinocerites mosquito
head spines - Uranotaenia mosquito
HIV - human immunodeficiency virus
Insects - Insecta
Insect Viruses - Insecta
Fleas - Siphonaptera
Dane particles - Hepadnaviridae

*/


//exit;
//define("ENVIRONMENT", "development");
define("ENVIRONMENT", "slave_32");
define("MYSQL_DEBUG", false);
define("DEBUG", true);
include_once(dirname(__FILE__) . "/../../config/start.php");
$mysqli =& $GLOBALS['mysqli_connection'];

//only on local; to be deleted before going into production
 /*
$mysqli->truncate_tables("development");
Functions::load_fixtures("development");
exit;
 */

//$wrap = "\n";
$wrap = "<br>";
 
 
$resource = new Resource(79);
print "resource id = " . $resource->id . "$wrap";
//exit;

$schema_taxa = array();
$used_taxa = array();

$id_list=array();


$total_taxid_count = 0;
$do_count = 0;


$url = 'http://phil.cdc.gov/phil/details.asp';  
$home_url = "http://phil.cdc.gov/phil/home.asp";
$arr_id_list = get_id_list();

$arr_desc_taxa = array();
$arr_categories = array();
$arr_outlinks = array();              

//start - initial run just to activate session
$philid = 11705;
list($id,$image_url,$description,$desc_pic,$desc_taxa,$categories,$taxa,$copyright,$providers,$creation_date,$photo_credit,$outlinks) = process($url,$philid);
//end - initial run just to activate session


for ($i = 0; $i < count($arr_id_list); $i++) 
{
    //main loop
    print $wrap;
    print $i+1 . " of " . count($arr_id_list) . " id=" . $arr_id_list[$i] . " ";
    $philid = $arr_id_list[$i];        
    list($id,$image_url,$description,$desc_pic,$desc_taxa,$categories,$taxa,$copyright,$providers,$creation_date,$photo_credit,$outlinks) = process($url,$philid);

    if(trim($taxa) == "")
    {   
        print " --blank taxa--";
        continue; 
        //exit(" $philid blank taxa exists");
    }
    //print"$id<hr> --- $image_url<hr> --- $description<hr> --- $desc_pic<hr> --- $desc_taxa<hr> --- $categories<hr> --- $taxa<hr> --- $copyright<hr> $providers<hr> --- $creation_date<hr> --- $photo_credit<hr> --- $outlinks<hr> --- ";
    
    $desc_taxa = str_ireplace("animals sre filtered", "animals are filtered", $desc_taxa);
    
    //$categories="xxx";
    $outlinks = utf8_encode($outlinks);
    $desc_pic = utf8_encode($desc_pic);
    $desc_taxa = utf8_encode($desc_taxa);
    
    /* desc_taxa is no longer included    
    if($desc_taxa != "")$desc_pic .= "<br><br>$desc_taxa";   
    */
    
    $desc_pic = $desc_pic . "<br>" . "Created: $creation_date";
    
    $desc_pic = str_ireplace("<i>comb scales</i>", "comb scales", $desc_pic);
    $desc_pic = str_ireplace("<i>lateral plate</i>", "lateral plate", $desc_pic);
    $desc_pic = str_ireplace("<i>spinulose hairs</i>", "spinulose hairs", $desc_pic);
    $desc_pic = str_ireplace("<i>median ventral brush</i>", "median ventral brush", $desc_pic);
    
     
    
    

    if(in_array($taxa . $desc_taxa, $arr_desc_taxa))$desc_taxa="";
    else                                            $arr_desc_taxa[] = $taxa . $desc_taxa;     

    if(in_array($taxa . $categories, $arr_categories))$categories="";
    else                                              $arr_categories[] = $taxa . $categories;     
    
    if(in_array($taxa . $outlinks, $arr_outlinks))$outlinks="";
    else                                          $arr_outlinks[] = $taxa . $outlinks;     

    //new
    $desc_taxa="";
    
    if($categories != "")$desc_taxa .= "<hr>Categories:<br>$categories";   
    if($outlinks != "")  $desc_taxa .= "<hr>Outlinks:<br>$outlinks";
    
    //print"<hr><hr>";    
    //print"<hr>";     

    $taxon = str_replace(" ", "_", $taxa);
    if(@$used_taxa[$taxon])
    {
        $taxon_parameters = $used_taxa[$taxon];
    }
    else
    {
        $taxon_parameters = array();
        $taxon_parameters["identifier"] = "CDC_" . $taxon; //$main->taxid;
        $taxon_parameters["scientificName"]= $taxa;
        $taxon_parameters["source"] = $home_url;
        $used_taxa[$taxon] = $taxon_parameters;            
    }

    if(1==1)
    {
        //if($do_count == 0)//echo "$wrap$wrap phylum = " . $taxa . "$wrap";

        $dc_source = $home_url;       

        $do_count++;        
        $agent_name = $photo_credit;
        $agent_role = "photographer";            
               
        // /* just debug; no images for now
        $data_object_parameters = get_data_object("image",$taxon,$do_count,$dc_source,$agent_name,$agent_role,$desc_pic,$copyright,$image_url,"");               
        $taxon_parameters["dataObjects"][] = new SchemaDataObject($data_object_parameters);                         
        // */
        
        if($desc_taxa != "")
        {
            $temp = trim(strip_tags($desc_taxa));                        
            if(substr($temp,0,9)  != "Outlinks:")
            {
                if(substr($temp,0,11) == "Categories:") $title="Categories";
                //$desc_taxa="<b>Discussion on disease(s) caused by this organism:</b>" . $desc_taxa;                        
                $do_count++;
                $agent_name = $providers;
                $agent_role = "source";            
                $data_object_parameters = get_data_object("text",$taxon,$do_count,$dc_source,$agent_name,$agent_role,$desc_taxa,$copyright,$image_url,$title);                           
                $taxon_parameters["dataObjects"][] = new SchemaDataObject($data_object_parameters);                                 
            }            
        }
        
        $used_taxa[$taxon] = $taxon_parameters;

    }//with photos
    
    //end main loop   
}

foreach($used_taxa as $taxon_parameters)
{
    $schema_taxa[] = new SchemaTaxon($taxon_parameters);
}
////////////////////// ---
$new_resource_xml = SchemaDocument::get_taxon_xml($schema_taxa);
$old_resource_path = CONTENT_RESOURCE_LOCAL_PATH . $resource->id .".xml";
$OUT = fopen($old_resource_path, "w+");
fwrite($OUT, $new_resource_xml);
fclose($OUT);
////////////////////// ---

echo "$wrap$wrap Done processing.";
exit("<hr>-done-");

function get_data_object($type,$taxon,$do_count,$dc_source,$agent_name,$agent_role,$description,$copyright,$image_url,$title)   
{        
    //$description = "<![CDATA[ $description ]]>";
    $dataObjectParameters = array();
        
    if($type == "text")
    {            
        $dataObjectParameters["title"] = $title;            

        //start subject        
        $dataObjectParameters["subjects"] = array();
        $subjectParameters = array();
        
        $subjectParameters["label"] = "http://rs.tdwg.org/ontology/voc/SPMInfoItems#GeneralDescription";        
        //$subjectParameters["label"] = "http://rs.tdwg.org/ontology/voc/SPMInfoItems#RiskStatement";        
        //$subjectParameters["label"] = "http://rs.tdwg.org/ontology/voc/SPMInfoItems#Diseases";
        
        $dataObjectParameters["subjects"][] = new SchemaSubject($subjectParameters);
        //end subject            
            
        $dataObjectParameters["dataType"] = "http://purl.org/dc/dcmitype/Text";
        $dataObjectParameters["mimeType"] = "text/html";
        $dataObjectParameters["source"] = $dc_source;
    }
    elseif($type == "image")
    {
        $dataObjectParameters["dataType"] = "http://purl.org/dc/dcmitype/StillImage";
        $dataObjectParameters["mimeType"] = "image/jpeg";            
        $dataObjectParameters["mediaURL"] = $image_url;
        $dataObjectParameters["rights"] = $copyright;
        $dc_source ="";
    }
        
    $dataObjectParameters["description"] = $description;
    //$dataObjectParameters["created"] = $created;
    //$dataObjectParameters["modified"] = $modified;            
    $dataObjectParameters["identifier"] = $taxon . "_" . $do_count;        
    $dataObjectParameters["rightsHolder"] = "Public Health Image Library";
    $dataObjectParameters["language"] = "en";
    $dataObjectParameters["license"] = "http://creativecommons.org/licenses/publicdomain/";        
        
    //==========================================================================================
    /* working...
    $agent = array(0 => array(     "role" => "photographer" , "homepage" => ""           , $photo_credit),
                   1 => array(     "role" => "project"      , "homepage" => $home_url    , "Public Health Image Library")
                  );    
    */
        
    if($agent_name != "")
    {
        $agent = array(0 => array( "role" => $agent_role , "homepage" => $dc_source , $agent_name) );    
        $agents = array();
        foreach($agent as $agent)
        {  
            $agentParameters = array();
            $agentParameters["role"]     = $agent["role"];
            $agentParameters["homepage"] = $agent["homepage"];
            $agentParameters["logoURL"]  = "";        
            $agentParameters["fullName"] = $agent[0];
            $agents[] = new SchemaAgent($agentParameters);
        }
        $dataObjectParameters["agents"] = $agents;    
    }
    //==========================================================================================
    $audience = array(  0 => array(     "Expert users"),
                        1 => array(     "General public")
                     );        
    $audiences = array();
    foreach($audience as $audience)
    {  
        $audienceParameters = array();
        $audienceParameters["label"]    = $audience[0];
        $audiences[] = new SchemaAudience($audienceParameters);
    }
    $dataObjectParameters["audiences"] = $audiences;    
    //==========================================================================================
    return $dataObjectParameters;
}

function get_id_list()
{
    global $wrap;
    
    $id_list = array();    
    for ($i=1; $i <= 64; $i++)//we only have 21,64 html pages with the ids, the rest of the pages is not server accessible.
    {
        print "$wrap [[$i]] -- ";        
        $url = "http://128.128.175.77/eol_php_code/update_resources/connectors/files/PublicHealthImageLibrary/hiv.htm";
        $url = "http://services.eol.org/eol_php_code/update_resources/connectors/files/PublicHealthImageLibrary/id_list%20(" . $i . ").htm";                                
        $url = "http://128.128.175.77/cdc/test.htm";                
        $url = "http://128.128.175.77/cdc/final/id_list%20(" . $i . ").htm";        
        
        $handle = fopen($url, "r");	
        if ($handle)
        {
            $contents = '';
        	while (!feof($handle)){$contents .= fread($handle, 8192);}
        	fclose($handle);	
        	$str = $contents;
        }    
        $str = utf8_encode($str);
	    $beg='<tr><td><font face="arial" size="2">ID#:'; $end1="</font><hr></td></tr>"; $end2="173xxx"; $end3="173xxx";			
    	$arr = parse_html($str,$beg,$end1,$end2,$end3,$end3,"all",false);	//str = the html block        
        print count($arr) . "\n";    
        $id_list = array_merge($id_list, $arr);    
        //print_r($id); print"<hr>";
    }    
    print "total = " . count($id_list) . "\n"; //exit;
    $count_bef_unset = count($id_list);
    
    //start exclude ids that are images of dogs and their masters, non-organisms
    for ($i = 0; $i < count($id_list); $i++) 
    {
        $not_organism = array(11357,11329,10927,10926,10925,10141,10134,10425,10507,26,107,93,110,111,1500,10507,3,10187,7906,4484,4483
        ,10145,10146,9312,9313,9315,9339,9354,8675,8362,8085,8097,8111,8112,4664,4665,4670,4676,6116,6723,6724,6725,6968,6969,6970
        ,6990,6991,7185,7186,7188,7189,7272,7884,7885,7890,10425,10444,10507,7733,7735,7737,7739,7741
        ,1988,1989,1991,2003,2010,2318,2402,2639,4682,4683,4685,4698,4721,4727,4728,7079,7729,7730,7732,10925,10926,10927);                
        if (in_array($id_list[$i], $not_organism)) unset($id_list[$i]);        
        
        if(@$id_list[$i] >= 10679 and @$id_list[$i] <= 10690) unset($id_list[$i]);    
        if(@$id_list[$i] >= 10694 and @$id_list[$i] <= 10699) unset($id_list[$i]);        
        if(@$id_list[$i] >= 10710 and @$id_list[$i] <= 10715) unset($id_list[$i]);        
        if(@$id_list[$i] >= 10756 and @$id_list[$i] <= 10759) unset($id_list[$i]);                
        if(@$id_list[$i] >= 10365 and @$id_list[$i] <= 10373) unset($id_list[$i]);    
        if(@$id_list[$i] >= 8079 and @$id_list[$i] <= 8082) unset($id_list[$i]);    
        if(@$id_list[$i] >= 8091 and @$id_list[$i] <= 8095) unset($id_list[$i]);    
        if(@$id_list[$i] >= 8099 and @$id_list[$i] <= 8103) unset($id_list[$i]);    
        if(@$id_list[$i] >= 8105 and @$id_list[$i] <= 8108) unset($id_list[$i]);    
        if(@$id_list[$i] >= 4655 and @$id_list[$i] <= 4658) unset($id_list[$i]);    
        if(@$id_list[$i] >= 7986 and @$id_list[$i] <= 7989) unset($id_list[$i]);    
        if(@$id_list[$i] >= 8006 and @$id_list[$i] <= 8014) unset($id_list[$i]);    
        if(@$id_list[$i] >= 4649 and @$id_list[$i] <= 4654) unset($id_list[$i]);    
        if(@$id_list[$i] >= 7744 and @$id_list[$i] <= 7753) unset($id_list[$i]);    
        if(@$id_list[$i] >= 7762 and @$id_list[$i] <= 7773) unset($id_list[$i]);    
        if(@$id_list[$i] >= 7721 and @$id_list[$i] <= 7725) unset($id_list[$i]);                      
        
        
        
    }        
    //end exclude ids    

    print "$wrap count after unset = " . count($id_list);    
    $id_list = array_trim($id_list,$count_bef_unset);
    print "$wrap final count = " . count($id_list) . "$wrap";    
    //exit("<hr>stopx");    
    return $id_list;    
}

function process($url,$philid)
{
    $contents = cURL_it($philid,$url);
    if($contents) print "";
    else print exit("\n bad post [$philid] \n ");
    $arr = parse_contents($contents);
    return $arr;        
}




function parse_contents($str)
{
    //========================================================================================
    
    $str = str_ireplace('�', '', $str);
    $str = str_ireplace('�', '', $str);
    
    
    $image_url="";
    /*
    <img border="0" src="http://phil.cdc.gov/phil_images/20040219/3/PHIL_5485_lores.jpg" alt="PHIL Image 5485" />
    <img border="0" src="http://phil.cdc.gov/PHIL_Images/20031202/e51cc8a13dec4028b5b65478bc22647a/5223_lores.jpg" alt */
   	$beg='http://phil.cdc.gov/PHIL_Images/'; $end1='_lores.jpg'; $end2="173xxx"; $end3="173xxx";			
    $arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
  	$id=$arx;
    $image_url = "http://phil.cdc.gov/PHIL_Images/" . $id . "_lores.jpg";            
    
    ini_set('display_errors', '0'); 
    $handle = fopen($image_url, "r");	
    ini_set('display_errors', '1'); 
    if($handle)fclose($handle);	
    else
    {
    	$beg="ID#:</b></td><td>"; $end1="</td></tr>"; $end2="173xxx"; $end3="173xxx";			
	    $arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
    	$id=$arx;
    	//print "<hr>id = " . $id;	print "<hr>";
        $image_url = "http://phil.cdc.gov/PHIL_Images/" . $id . "/" . $id . "_lores.jpg";
        //print"<img src='http://phil.cdc.gov/PHIL_Images/" . $id . "/" . $id . "_lores.jpg'><hr>";        
    }    
    
    
	//========================================================================================
	$beg="<td><b>Description:</b></td><td>"; $end1="</td></tr>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$description=trim($arx);    
	//print $description;	print "<hr>"; //exit;    

	//========================================================================================
    $description = "xxx" . $description;
	$beg="xxx<b>"; $end1="</b><p>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($description,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$desc_pic=$arx;    
	//print "desc_pic $wrap" . $desc_pic;	print "<hr>"; //exit;    
      

    $description = str_ireplace('xxx', '', $description);        
    $desc_taxa = str_ireplace($desc_pic, '', $description);        
    //print "desc_taxa $wrap" . $desc_taxa;	print "<hr>"; //exit;                  
    
    //========================================================================================
	//$beg='<table border="0" cellpadding="0" cellspacing="0"><tbody><tr><td>CDC Organization</td></tr></tbody></table>'; 
    $beg='<table border="0" cellpadding="0" cellspacing="0"><tr><td>CDC Organization</td></tr></table>';      

	$end1='<tr bgcolor="white" valign="top"><td><b>Copyright Restrictions:</b>'; 
	$end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$categories=$arx;

	$tmp=trim($categories);
	$tmp = str_replace(array("\n", "\r", "\t", "\o", "\xOB"), '', $tmp);	
	$tmp = substr($tmp,0,strlen($tmp)-10);
    
    
    $tmp = str_ireplace('<!--<td>&nbsp;&nbsp;</td>-->', '', $tmp);    
    
    $tmp = strip_tags($tmp,"<td><tr><table><img>");
    
	$categories = $tmp;
	//print $categories;	print "<hr>"; //exit;    
    
    //========================================================================================	
  
    $taxa="";
	$beg="<i>"; $end1="</i>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($desc_pic,$beg,$end1,$end2,$end3,$end3,NULL,true);	//str = the html block
	$taxa=$arx;    
    
    if (in_array($taxa, array("Plants, Edible","Plants, Toxic","Pandemic Flu Preparedness: What Every Community Should Know","Global Program for Avian and Human Influenza: Communications Planning Asia Regional Inter-Agency Knowledge Sharing","Revised Recommendations for HIV Screening of Adults, Adolescents, and Pregnant Women in Health Care Settings","HPV and Cervical Cancer: An Update on Prevention Strategies","Keeping the 'Genome' in the Bottle: Reinforcing Biosafety Level 3 Procedures")))$taxa="";    
    if (in_array($taxa, array("Science","Spiders","Poultry","Food","Men","Motor Vehicles","Child","Child, Preschool","Child, Preschool","Stop Transmission of Polio","Child")))$taxa="";
    
    
    /* will no longer get taxa outside the <i></i> */ 
    $taxa = trim($taxa);
    if($taxa == "")    
    {
    	$str_stripped = str_replace(array("\n", "\r", "\t", "\o", "\xOB"), '', $str);	
    	$beg="document.form2.creationdate.value = '1';"; 
    	$end1='</a></b></td>'; 
    	$end2="</a></td>"; $end3="173";			
    	$arx = parse_html($str_stripped,$beg,$end1,$end2,$end3,$end3);	//str = the html block
    	$arx = trim($arx);
    	$arx = substr($arx,2,strlen($arx));
        $taxa = $arx;
    }         
        

    //manual edits
    $taxa = trim($taxa);
              
    if($taxa == "Pollen")$taxa = "Ambrosia trifida";
    if($taxa == "Ticks")$taxa = "Acarina";
    if($taxa == "saddle")$taxa = "Psorophora";
    if($taxa == "palmate hairs")$taxa = "Anopheles";    
    if($taxa == "lateral plate")$taxa = "Toxorhynchites mosquito";
    if($taxa == "lateral pouches")$taxa = "Deinocerites mosquito";
    if($taxa == "head spines")$taxa = "Uranotaenia mosquito";
    if($taxa == "human immunodeficiency virus")$taxa = "HIV";    
    if($taxa == "Fleas")$taxa = "Siphonaptera";
    if($taxa == "Dane particles")$taxa = "Hepadnaviridae";            
    if($taxa == "Plasmodium spp. life cycle.")$taxa = "Plasmodium spp";
    if($taxa == "Giardia lamblia (intestinalis)")$taxa = "Giardia lamblia";        
    if($taxa == "sclerotium")$taxa = "Penicillium sclerotiorum";        
    if($taxa == "cleistothecia")$taxa = "Talaromyces flavus var. flavus";            
    if($taxa == "Plants")$taxa = "Plantae";            
    
    if (in_array($taxa, array("wasps","Wasps")))$taxa="Hymenoptera";    
    if (in_array($taxa, array("siphon","siphon tuft","Siphona irritans","siphonal hairs","siphonal tufts")))$taxa="Culex pipiens";    
    if (in_array($taxa, array("pecten","dorsal plate")))$taxa="Aedes";
    if (in_array($taxa, array("Insects","Insect Viruses")))$taxa="Insecta";


    if (in_array($taxa, array("Plants, Edible","Plants, Toxic","Pandemic Flu Preparedness: What Every Community Should Know","Global Program for Avian and Human Influenza: Communications Planning Asia Regional Inter-Agency Knowledge Sharing","Revised Recommendations for HIV Screening of Adults, Adolescents, and Pregnant Women in Health Care Settings","HPV and Cervical Cancer: An Update on Prevention Strategies","Keeping the 'Genome' in the Bottle: Reinforcing Biosafety Level 3 Procedures")))$taxa="";    
    if (in_array($taxa, array("Science","Spiders","Poultry","Food","Men","Motor Vehicles","Child","Child, Preschool","Child, Preschool","Stop Transmission of Polio","Child")))$taxa="";
    

    
    
    //end

    $taxa = trim(strip_tags($taxa));                        
    if (in_array($taxa, array("Plasmodium spp. life cycle.")))$taxa="Plasmodium spp.";    
         
    
	print "taxa = [$taxa] ";
    
	//========================================================================================
	$beg="Copyright Restrictions:</b></td><td>"; $end1="</td></tr>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$copyright=$arx;
	//print $copyright;	print "<hr>"; //exit;        
    //========================================================================================	
	$beg="Content Providers(s):</b></td><td>"; $end1="</td></tr>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$providers=$arx;
	//print $providers;	print "<hr>"; //exit;    
    //========================================================================================	
	$beg="Creation Date:</b></td><td>"; $end1="</td></tr>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$creation_date=$arx;
	//print $creation_date;	print "<hr>"; //exit;        
    //========================================================================================	
	$beg="Photo Credit:</b></td><td>"; $end1="</td></tr>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$photo_credit=$arx;
	//print $photo_credit;	print "<hr>"; //exit;    
    //========================================================================================	
	$beg='Links:</b></td><td><table><tr valign="top"><td><li></li></td><td>';           
    $end1="</td></tr></table></td></tr>"; $end2="173xxx"; $end3="173xxx";			
	$arx = parse_html($str,$beg,$end1,$end2,$end3,$end3);	//str = the html block
	$outlinks=$arx;    
    
    $outlinks = str_ireplace('</td></tr></table><table><tr valign="top"><td><li></li></td><td>', '<br>', $outlinks);
    //$outlinks = strip_tags($outlinks,"<a>"); //not needed
    
	//print "<hr>$str";
    //print "<hr>outlinks: " . $outlinks;	print "<hr>"; //exit;
    //========================================================================================	       
    return array ($id,$image_url,$description,$desc_pic,$desc_taxa,$categories,$taxa,$copyright,$providers,$creation_date,$photo_credit,$outlinks);    
}//function parse_contents($contents)

function cURL_it($philid,$url)
{    
    $fields = 'philid=' . $philid;  
    $ch = curl_init();  
    curl_setopt($ch,CURLOPT_URL,$url);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    // not to display the post submission
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    curl_setopt($ch,CURLOPT_POST, $fields);  
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields);  
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION, true);  
    $output = curl_exec($ch);
    $info = curl_getinfo($ch); 
    /*
    src="images/    
    http://phil.cdc.gov/phil/images/nodedownline.gif
    */    
    $output = str_ireplace('src="images/', 'src="http://phil.cdc.gov/phil/images/', $output);    
    //print $output; exit;    
    curl_close($ch);
    $ans = stripos($output,"The page cannot be found");
    $ans = strval($ans);
    if($ans != "")  return false;
    else            return $output;        
}//function cURL_it($philid)

/*
function parse_html($str,$beg,$end1,$end2,$end3,$end4,$all=NULL)	//str = the html block
{
    //PRINT "[$all]"; exit;
	$beg_len = strlen(trim($beg));
	$end1_len = strlen(trim($end1));
	$end2_len = strlen(trim($end2));
	$end3_len = strlen(trim($end3));	
	$end4_len = strlen(trim($end4));		
	//print "[[$str]]";

	$str = trim($str); 
	
	$str = $str . "|||";
	
	$len = strlen($str);
	
	$arr = array(); $k=0;
	
	for ($i = 0; $i < $len; $i++) 
	{
		if(strtolower(substr($str,$i,$beg_len)) == strtolower($beg))
		{	
			$i=$i+$beg_len;
			$pos1 = $i;
			
			//print substr($str,$i,10) . "$wrap";									

			$cont = 'y';
			while($cont == 'y')
			{
				if(	substr($str,$i,$end1_len) == $end1 or 
					substr($str,$i,$end2_len) == $end2 or 
					substr($str,$i,$end3_len) == $end3 or 
					substr($str,$i,$end4_len) == $end4 or 
					substr($str,$i,3) == '|||' )
				{
					$pos2 = $i - 1; 					
					$cont = 'n';					
					$arr[$k] = substr($str,$pos1,$pos2-$pos1+1);															
					
					//print "$arr[$k] <hr>";
					
					$k++;
				}
				$i++;
			}//end while
			$i--;			
		}
		
	}//end outer loop

    if($all == "")	
    {
        $id='';
	    for ($j = 0; $j < count($arr); $j++){$id = $arr[$j];}		
        return $id;
    }
    elseif($all == "all") return $arr;
	
}//end function
*/

// /*
function parse_html($str,$beg,$end1,$end2,$end3,$end4,$all=NULL,$exit_on_first_match=false)	//str = the html block
{
    //PRINT "[$all]"; exit;
	$beg_len = strlen(trim($beg));
	$end1_len = strlen(trim($end1));
	$end2_len = strlen(trim($end2));
	$end3_len = strlen(trim($end3));	
	$end4_len = strlen(trim($end4));		
	//print "[[$str]]";

	$str = trim($str); 	
	$str = $str . "|||";	
	$len = strlen($str);	
	$arr = array(); $k=0;	
	for ($i = 0; $i < $len; $i++) 
	{
        if(strtolower(substr($str,$i,$beg_len)) == strtolower($beg))
		{	
			$i=$i+$beg_len;
			$pos1 = $i;			
			//print substr($str,$i,10) . "<br>";									
			$cont = 'y';
			while($cont == 'y')
			{
				if(	substr($str,$i,$end1_len) == $end1 or 
					substr($str,$i,$end2_len) == $end2 or 
					substr($str,$i,$end3_len) == $end3 or 
					substr($str,$i,$end4_len) == $end4 or 
					substr($str,$i,3) == '|||' )
				{
					$pos2 = $i - 1; 					
					$cont = 'n';					
					$arr[$k] = substr($str,$pos1,$pos2-$pos1+1);																				
					//print "$arr[$k] $wrap";					                    
					$k++;
				}
				$i++;
			}//end while
			$i--;			
            
            //start exit on first occurrence of $beg
            if($exit_on_first_match)break;
            //end exit on first occurrence of $beg
            
		}		
	}//end outer loop
    if($all == "")	
    {
        $id='';
	    for ($j = 0; $j < count($arr); $j++){$id = $arr[$j];}		
        return $id;
    }
    elseif($all == "all") return $arr;	
}//end function
// */

	
function array_trim($a,$len) 
{ 	
	$b=array();
	$j = 0; 
	//print "<hr> -- "; print count($a); print "<hr> -- ";
	for ($i = 0; $i < $len; $i++) 
	{ 
		//if (array_key_exists($i,$a))
        if(isset($a[$i]))
		{
			if (trim($a[$i]) != "") { $b[$j++] = $a[$i]; } 		
            else print "[walang laman]";
		}
	} 	
	return $b; 
}

?>