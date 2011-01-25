<?php

define("VIMEO_USER_SERVICE", "http://vimeo.com/api/v2/");
define("VIMEO_PLAYER_URL", "http://vimeo.com/moogaloop.swf?clip_id=");

class VimeoAPI
{
    public static function get_all_taxa()
    {
        $all_taxa = array();
        $used_collection_ids = array();        
        $users = self::compile_user_list();        
        $total_users = sizeof($users); $j=0;
        foreach($users as $user)
        {
            $j++;            
            for ($page = 1; $page <= 3; $page++) 
            {
                $xml = simplexml_load_file(VIMEO_USER_SERVICE . $user . "/videos.xml?page=$page");                        
                $num_rows = sizeof($xml->video); 
                if(!$num_rows)break;
                $i=0;
                foreach($xml->video as $rec)
                {                               
                    $i++; print"\n [user $j of $total_users] [video $i of $num_rows] [page $page] ";                
                    $arr = self::get_vimeo_taxa($rec,$used_collection_ids);                                
                    $page_taxa              = $arr[0];
                    $used_collection_ids    = $arr[1];                            
                    if($page_taxa) $all_taxa = array_merge($all_taxa,$page_taxa);                                                                    
                }                                        
            }            
        }
        return $all_taxa;
    }
    
    public static function get_vimeo_taxa($rec,$used_collection_ids)
    {
        $response = self::parse_xml($rec);//this will output the raw (but structured) array
        $page_taxa = array();
        foreach($response as $rec)
        {
            if(@$used_collection_ids[$rec["sciname"]]) continue;            
            $taxon = self::get_taxa_for_photo($rec);
            if($taxon) $page_taxa[] = $taxon;            
            @$used_collection_ids[$rec["sciname"]] = true;
        }        
        return array($page_taxa,$used_collection_ids);        
    }            
    
    function parse_xml($rec)
    {
        $arr_data=array();                           
        $description = Functions::import_decode($rec->description);        
        $description = str_ireplace("<br />","",$description);                

        $license="";                
        $arr_sciname = array();
        if(preg_match_all("/\[(.*?)\]/ims", $description, $matches))//gets everything between brackets []
        {
            $smallest_rank = self::get_smallest_rank($matches[1]);
            //print "\n smallest rank: $smallest_rank"; //exit;
            
            foreach($matches[1] as $tag)
            {
                $tag=trim($tag);                
                if(is_numeric(stripos($tag,$smallest_rank)))
                {
                    if(preg_match("/^taxonomy:" . $smallest_rank . "=(.*)$/i", $tag, $arr))$sciname = ucfirst(trim($arr[1]));                    
                    //print"\n [$tag][$smallest_rank] sciname = $sciname";
                    $arr_sciname[$sciname]['trinomial']   = "";
                    $arr_sciname[$sciname]['subspecies']  = "";
                    $arr_sciname[$sciname]['species']     = "";
                    $arr_sciname[$sciname]['genus']       = "";
                    $arr_sciname[$sciname]['family']      = "";
                    $arr_sciname[$sciname]['order']       = "";
                    $arr_sciname[$sciname]['class']       = "";
                    $arr_sciname[$sciname]['phylum']      = "";
                    $arr_sciname[$sciname]['kingdom']     = "";
                    $arr_sciname[$sciname]['commonNames'] = array();                                                   
                }                                                    

                if(preg_match("/^taxonomy:binomial=(.*)$/i", $tag, $arr)){}       
                elseif(preg_match("/^taxonomy:trinomial=(.*)$/i", $tag, $arr)){}  
                elseif(preg_match("/^taxonomy:genus=(.*)$/i", $tag, $arr))      $arr_sciname[$sciname]['genus']     = ucfirst(trim($arr[1]));
                elseif(preg_match("/^taxonomy:family=(.*)$/i", $tag, $arr))     $arr_sciname[$sciname]['family']    = ucfirst(trim($arr[1]));
                elseif(preg_match("/^taxonomy:order=(.*)$/i", $tag, $arr))      $arr_sciname[$sciname]['order']     = ucfirst(trim($arr[1]));
                elseif(preg_match("/^taxonomy:class=(.*)$/i", $tag, $arr))      $arr_sciname[$sciname]['class']     = ucfirst(trim($arr[1]));
                elseif(preg_match("/^taxonomy:phylum=(.*)$/i", $tag, $arr))     $arr_sciname[$sciname]['phylum']    = ucfirst(trim($arr[1]));                
                elseif(preg_match("/^taxonomy:kingdom=(.*)$/i", $tag, $arr))    $arr_sciname[$sciname]['kingdom']   = ucfirst(trim($arr[1]));                
                
                elseif(preg_match("/^taxonomy:common=(.*)$/i", $tag, $arr))     $arr_sciname[$sciname]['commonNames'][]  = trim($arr[1]);                                
                elseif(preg_match("/^dc:license=(.*)$/i", $tag, $arr))          $license = strtolower(trim($arr[1]));                                
            }
            foreach($matches[0] as $str)
            {
                $description = str_ireplace($str,"",trim($description));
            }
        }                                
        
        $with_eol_tag = false;        
        $tags = explode(",", $rec->tags);                
        foreach($tags as $tag)
        {
            $tag=trim($tag);
            if(trim($tag)=="eol") $with_eol_tag = true;
            elseif(preg_match("/^dc:license=(.*)$/i", $tag, $arr)) $license = strtolower(trim($arr[1]));
        }        
        
        //has to have an 'eol' tag
        if(!$with_eol_tag)return array();                

        //license from Vimeo license settings - scraped from the video page
        $license = self::get_license_from_page($rec->url);            
                        
        if(!$license)           
        {
            //license from Vimeo tag or description section
            $license = self::get_cc_license($license);                             
        }
        
        //has to have a valid license
        if(!$license) 
        {
            print "\n invalid license ";   
            return array();                
        }
        
        foreach($arr_sciname as $sciname => $temp)
        {
            if(!$sciname && $arr_sciname[$sciname]['trinomial']) $sciname = $arr_sciname[$sciname]['trinomial'];
            if(!$sciname && $arr_sciname[$sciname]['genus'] && $arr_sciname[$sciname]['species'] && !preg_match("/ /", $arr_sciname[$sciname]['genus']) && !preg_match("/ /", $arr_sciname[$sciname]['species'])) $sciname = $arr_sciname[$sciname]['genus']." ".$arr_sciname[$sciname]['species'];                        
            if(!$sciname && !$arr_sciname[$sciname]['genus'] && !$arr_sciname[$sciname]['family'] && !$arr_sciname[$sciname]['order'] && !$arr_sciname[$sciname]['class'] && !$arr_sciname[$sciname]['phylum'] && !$arr_sciname[$sciname]['kingdom']) return array();
                        
            //start data objects //----------------------------------------------------------------------------------------
            $arr_objects=array();        
            $identifier  = $rec->id;
            $dataType    = "http://purl.org/dc/dcmitype/MovingImage"; 
            $mimeType    = "video/x-flv";
            if(trim($rec->title)) $title = $rec->title;        
            else                  $title = "Vimeo video";                
            $source      = $rec->url;        
            $mediaURL    = VIMEO_PLAYER_URL . $rec->id;                  
            $agent=array();
            if($rec->user_name) $agent = array(0 => array("role" => "creator" , "homepage" => $rec->user_url , $rec->user_name));                    
            $arr_objects = self::add_objects($identifier,$dataType,$mimeType,$title,$source,$description,$mediaURL,$agent,$license,$arr_objects);
            //end data objects //----------------------------------------------------------------------------------------                    
            $taxon_id   = str_ireplace(" ","_",$sciname) . "_vimeo";
            $arr_data[]=array(  "identifier"   =>"",
                                "source"       =>"",
                                "kingdom"      =>$arr_sciname[$sciname]['kingdom'],
                                "phylum"       =>$arr_sciname[$sciname]['phylum'],
                                "class"        =>$arr_sciname[$sciname]['class'],
                                "order"        =>$arr_sciname[$sciname]['order'],
                                "family"       =>$arr_sciname[$sciname]['family'],
                                "genus"        =>$arr_sciname[$sciname]['genus'],
                                "sciname"      =>$sciname,
                                "commonNames"  =>$arr_sciname[$sciname]['commonNames'], 
                                "arr_objects"  =>$arr_objects
                             );                   
        }                                       
        return $arr_data;        
    }
    
    function initialize($sciname)
    {
        $arr_sciname[$sciname]['trinomial']   = "";
        $arr_sciname[$sciname]['subspecies']  = "";
        $arr_sciname[$sciname]['species']     = "";
        $arr_sciname[$sciname]['genus']       = "";
        $arr_sciname[$sciname]['family']      = "";
        $arr_sciname[$sciname]['order']       = "";
        $arr_sciname[$sciname]['class']       = "";
        $arr_sciname[$sciname]['phylum']      = "";
        $arr_sciname[$sciname]['kingdom']     = "";
        $arr_sciname[$sciname]['commonNames'] = array();                               
        return $arr_sciname;
    }
    
    function get_smallest_rank($arr)
    {        
        $rank_id = array("trinomial" => 1, "binomial" => 2, "genus" => 3, "family" => 4, "order" => 5, "class" => 6, "phylum" => 7, "kingdom" => 8);                
        $smallest_rank_id=9; $smallest_rank="";
        foreach($arr as $tag)
        {
            if(preg_match("/^taxonomy:(.*)\=/i", $tag, $arr)) 
            {
                $rank = trim($arr[1]);
                if(in_array($rank, array_keys($rank_id))) 
                {
                    //print "\n $rank [in array]";
                    if($rank_id[$rank] < $smallest_rank_id)
                    {
                        $smallest_rank_id = $rank_id[$rank]; $smallest_rank = $rank;
                    }
                }                
            }
        }            
        return $smallest_rank;
    }
    
    function add_objects($identifier,$dataType,$mimeType,$title,$source,$description,$mediaURL,$agent,$license,$arr_objects)
    {
        $arr_objects[]=array( "identifier"=>$identifier,
                              "dataType"=>$dataType,
                              "mimeType"=>$mimeType,
                              "title"=>$title,
                              "source"=>$source,
                              "description"=>$description,
                              "mediaURL"=>$mediaURL,
                              "agent"=>$agent,
                              "license"=>$license
                            );                                    
        return $arr_objects;
    }    
    
    function get_taxa_for_photo($rec)
    {
        $taxon = array();                        
        $taxon["source"] = $rec["source"];
        $taxon["identifier"] = trim($rec["identifier"]);
        $taxon["scientificName"] = ucfirst(trim($rec["sciname"]));
        $taxon["genus"] = ucfirst(trim(@$rec["genus"]));
        $taxon["family"] = ucfirst(trim(@$rec["family"]));
        $taxon["order"] = ucfirst(trim(@$rec["order"]));
        $taxon["class"] = ucfirst(trim(@$rec["class"]));        
        $taxon["phylum"] = ucfirst(trim(@$rec["phylum"]));
        $taxon["kingdom"] = ucfirst(trim(@$rec["kingdom"]));                
        
        //start common names
        foreach($rec["commonNames"] as $comname)
        {            
            $taxon["commonNames"][] = new SchemaCommonName(array("name" => $comname, "language" => ""));
        }
        //end common names                        
                
        if($rec["arr_objects"])
        {
            foreach($rec["arr_objects"] as $object)
            {
                $data_object = self::get_data_object($object);
                if(!$data_object) return false;
                $taxon["dataObjects"][] = new SchemaDataObject($data_object);                     
            }
        }        
        $taxon_object = new SchemaTaxon($taxon);
        return $taxon_object;
    }
    
    function get_data_object($rec)
    {
        $data_object_parameters = array();        
        $data_object_parameters["identifier"]   = trim(@$rec["identifier"]);        
        $data_object_parameters["source"]       = $rec["source"];        
        $data_object_parameters["dataType"]     = trim($rec["dataType"]);
        $data_object_parameters["mimeType"]     = trim($rec["mimeType"]);
        $data_object_parameters["mediaURL"]     = trim(@$rec["mediaURL"]);        
        $data_object_parameters["created"]      = trim(@$rec["created"]);                
        $data_object_parameters["description"]  = Functions::import_decode(@$rec["description"]);                            
        $data_object_parameters["source"]       = @$rec["source"];        
        $data_object_parameters["license"]      = @$rec["license"];        
        $data_object_parameters["rightsHolder"] = @trim($rec["rightsHolder"]);
        $data_object_parameters["title"]        = @trim($rec["title"]);
        $data_object_parameters["language"]     = "en";
        //==========================================================================================
        $agents = array();
        foreach(@$rec["agent"] as $agent)
        {  
            $agentParameters = array();
            $agentParameters["role"]     = $agent["role"];
            $agentParameters["homepage"] = $agent["homepage"];
            $agentParameters["logoURL"]  = "";        
            $agentParameters["fullName"] = $agent[0];
            $agents[] = new SchemaAgent($agentParameters);
        }
        $data_object_parameters["agents"] = $agents;    
        //==========================================================================================        
        return $data_object_parameters;
    }    

    function compile_user_list()
    {
        $users=array();
        $users[]="user1632860"; //Peter Kuttner        
        $users[]="user5352360"; //Eli Agbayani //debug
        $users[]="user5814509"; //Katja Schulz        
        return $users;
    }    
    
    function get_cc_license($license)
    {
        switch($license)
        {
            case 'cc-by':
                return 'http://creativecommons.org/licenses/by/3.0/'; break;
            case 'cc-by-sa':
                return 'http://creativecommons.org/licenses/by-sa/3.0/'; break;
            case 'cc-by-nc':
                return 'http://creativecommons.org/licenses/by-nc/3.0/'; break;
            case 'cc-by-nc-sa':
                return 'http://creativecommons.org/licenses/by-nc-sa/3.0/'; break;
            case 'public domain':
                return 'http://creativecommons.org/licenses/publicdomain/'; break;
            case 'by-sa':
                return 'http://creativecommons.org/licenses/by-sa/3.0/'; break;
            case 'by-nc':
                return 'http://creativecommons.org/licenses/by-nc/3.0/'; break;
            case 'by-nc-sa':
                return 'http://creativecommons.org/licenses/by-nc-sa/3.0/'; break;                                                
            default:
                return false;
        }        
    }
    
    function get_license_from_page($video_page_url)
    {
        $html = Functions::get_remote_file($video_page_url);                            
        if(preg_match("/<a href=\"http:\/\/creativecommons.org\/licenses\/(.*?)\//ims", $html, $matches))
        {
            return self::get_cc_license("cc-" . trim($matches[1]));            
        }        
        return false;
    }
                
}
?>