<?php
namespace php_active_record;
/* connector: [gbif_gereference.php]
This script searches GBIF API occurrence data via taxon (taxon_key)
*/
class GBIFoccurrenceAPI
{
    // const DL_MAP_SPECIES_LIST   = "http://www.discoverlife.org/export/species_map.txt";
    const DL_MAP_SPECIES_LIST   = "http://localhost/cp/DiscoverLife/species_map.txt";
    
    function __construct($folder = null, $query = null)
    {
        $this->download_options = array('resource_id' => "gbif", 'expire_seconds' => 5184000, 'download_wait_time' => 2000000, 'timeout' => 600, 'download_attempts' => 1, 'delay_in_minutes' => 1); //2 months to expire
        $this->download_options['expire_seconds'] = false; //debug
        // $this->download_options['expire_seconds'] = true; //debug
        

        //GBIF services
        $this->gbif_taxon_info      = "http://api.gbif.org/v1/species/match?name="; //http://api.gbif.org/v1/species/match?name=felidae&kingdom=Animalia
        $this->gbif_record_count    = "http://api.gbif.org/v1/occurrence/count?taxonKey=";
        $this->gbif_occurrence_data = "http://api.gbif.org/v1/occurrence/search?taxonKey=";
        
        $this->html['publisher'] = "http://www.gbif.org/publisher/";
        $this->html['dataset'] = "http://www.gbif.org/dataset/";
        
        $this->save_path['cluster'] = DOC_ROOT . "public/tmp/google_maps/cluster/";
        $this->save_path['fusion']  = DOC_ROOT . "public/tmp/google_maps/fusion/";
        $this->save_path['fusion2'] = DOC_ROOT . "public/tmp/google_maps/fusion2/";
        
    }

    function start()
    {
        // self::main_loop("Chanos chanos (Forsskål, 1775)", 224731); return;   //process 1 taxon
        // self::main_loop("Gadus morhua Linnaeus, 1758", 206692); return;      //process 1 taxon
        
        // self::get_center_latlon_using_taxonID(206692); return;   //computes the center lat long
        
        // self::process_all_eol_taxa(); return;                    //make use of tab-delimited text file from JRice
        
        // self::process_hotlist_spreadsheet(); return;             //make use of hot list spreadsheet from SPG
        
        // self::process_DL_taxon_list(); return;                   //make use of taxon list from DiscoverLife
        
        /*
        $scinames = array();                                        //make use of manual taxon list V1
        // $scinames[] = "Gadus morhua";
        // $scinames[] = "Anopheles";
        // $scinames[] = "Ursus maritimus";
        // $scinames[] = "Carcharodon carcharias";
        // $scinames[] = "Panthera leo";
        // $scinames[] = "Rattus rattus";
        // $scinames[] = "Chanos chanos";
        foreach($scinames as $sciname) self::main_loop($sciname);
        */

        $scinames = array();                                        //make use of manual taxon list V2
        $scinames["Phalacrocorax penicillatus"] = 1048643;
        // $scinames["Chanos chanos"] = 224731;
        foreach($scinames as $sciname => $taxon_concept_id) self::main_loop($sciname, $taxon_concept_id);
        
        
        /*
        [offset]        => 0
        [limit]         => 20
        [endOfRecords]  => 
        [count]         => 78842
        [results]       => Array
        */
    }

    private function process_all_eol_taxa()
    {
        $path = DOC_ROOT . "/public/tmp/google_maps/taxon_concept_names.tab";
        $i = 0;
        foreach(new FileIterator($path) as $line_number => $line) // 'true' will auto delete temp_filepath
        {
            $line = explode("\t", $line);
            $taxon_concept_id = $line[0];
            $sciname          = Functions::canonical_form($line[1]);
            $i++;
            if(stripos($sciname, " ") !== false)
            {
                echo "\n$i. [$sciname][$taxon_concept_id]"; //exit;
                //==================
                $m = 100000;
                $cont = false;
                if($i >=  1    && $i < $m)    $cont = true;
                // if($i >=  $m   && $i < $m*2)  $cont = true;
                // if($i >=  $m*2 && $i < $m*3)  $cont = true;
                // if($i >=  $m*3 && $i < $m*4)  $cont = true;
                // if($i >=  $m*4 && $i < $m*5)  $cont = true;
                // if($i >=  $m*5 && $i < $m*6)  $cont = true;

                if(!$cont) continue;
                self::main_loop($sciname, $taxon_concept_id);
                // exit("\nelix\n");
                //==================
            }
        }//end loop
    }
    
    private function process_hotlist_spreadsheet()
    {
        require_library('XLSParser');
        $parser = new XLSParser();
        $families = array();
        $doc = "http://localhost/eol_php_code/public/tmp/spreadsheets/SPG Hotlist Official Version.xlsx";
        echo "\n processing [$doc]...\n";
        if($path = Functions::save_remote_file_to_local($doc, array("timeout" => 3600, "file_extension" => "xlsx", 'download_attempts' => 2, 'delay_in_minutes' => 2)))
        {
            $arr = $parser->convert_sheet_to_array($path);
            $i = -1;
            foreach($arr['Animals'] as $sciname)
            {
                $i++;
                $sciname = trim($sciname);
                if(stripos($sciname, " ") !== false)
                {
                    $taxon_concept_id = $arr['1'][$i];
                    echo "\n$i. [$sciname][$taxon_concept_id]";
                    //==================
                    $m = 10000;
                    $cont = false;
                    // if($i >=  1    && $i < $m)    $cont = true;
                    // if($i >=  $m   && $i < $m*2)  $cont = true;
                    // if($i >=  $m*2 && $i < $m*3)  $cont = true;
                    // if($i >=  $m*3 && $i < $m*4)  $cont = true;
                    // if($i >=  $m*4 && $i < $m*5)  $cont = true;
                    if($i >=  $m*5 && $i < $m*6)  $cont = true;

                    if(!$cont) continue;
                    self::main_loop($sciname, $taxon_concept_id);
                    //==================
                }
            }
            unlink($path);
        }
        else echo "\n [$doc] unavailable! \n";
    }

    private function process_DL_taxon_list()
    {
        $temp_filepath = Functions::save_remote_file_to_local(self::DL_MAP_SPECIES_LIST, array('timeout' => 4800, 'download_attempts' => 5));
        if(!$temp_filepath)
        {
            echo "\n\nExternal file not available. Program will terminate.\n";
            return;
        }
        $i = 0;
        foreach(new FileIterator($temp_filepath, true) as $line_number => $line) // 'true' will auto delete temp_filepath
        {
            $i++;
            if($line)
            {
                $m = 10000;
                $cont = false;
                if($i >=  1    && $i < $m)    $cont = true;
                // if($i >=  $m   && $i < $m*2)  $cont = true;
                // if($i >=  $m*2 && $i < $m*3)  $cont = true;
                // if($i >=  $m*3 && $i < $m*4)  $cont = true;
                // if($i >=  $m*4 && $i < $m*5)  $cont = true;
                
                if(!$cont) continue;
                
                $arr = explode("\t", $line);
                $sciname = trim($arr[0]);
                echo "\n[$sciname]\n";
                self::main_loop($sciname);
            }
            // if($i >= 5) break; //debug
        }
    }

    private function main_loop($sciname, $taxon_concept_id = false)
    {
        $sciname = Functions::canonical_form($sciname); echo "\n[$sciname]\n";
        $basename = $sciname;
        if($val = $taxon_concept_id) $basename = $val;
        
        $final_count = false;
        if(!($this->file = Functions::file_open($this->save_path['cluster'].$basename.".json", "w"))) return;
        if(!($this->file2 = Functions::file_open($this->save_path['fusion'].$basename.".txt", "w"))) return;
        if(!($this->file3 = Functions::file_open($this->save_path['fusion2'].$basename.".json", "w"))) return;
        
        $headers = "catalogNumber, sciname, publisher, publisher_id, dataset, dataset_id, gbifID, latitude, longitude, recordedBy, identifiedBy, pic_url";
        $headers = "catalogNumber, sciname, publisher, publisher_id, dataset, dataset_id, gbifID, recordedBy, identifiedBy, pic_url, location";
        
        fwrite($this->file2, str_replace(", ", "\t", $headers) . "\n");
        if($rec = self::get_initial_data($sciname))
        {
            if($rec['count'] < 100000) $final_count = self::get_georeference_data($rec['usageKey']);    //only process taxa with < 100K georeference records
            // if($rec['count'] < 20000) $final_count = self::get_georeference_data($rec['usageKey']);    //only process taxa with < 20K georeference records
        }
        fclose($this->file);
        fclose($this->file2);
        
        if(!$final_count)
        {
            unlink($this->save_path['cluster'].$basename.".json");
            unlink($this->save_path['fusion'].$basename.".txt");
            unlink($this->save_path['fusion2'].$basename.".json");
        }
        else //delete respective file
        {
            // return; //debug uncomment in real operation...
            if($final_count < 20000) {
                    unlink($this->save_path['fusion'].$basename.".txt");   //delete Fusion data
                    unlink($this->save_path['fusion2'].$basename.".json"); //delete Fusion data (centerLatLon, tableID, publishers)
            }
            else    unlink($this->save_path['cluster'].$basename.".json"); //delete cluster map data
        }
    }

    private function prepare_data($taxon_concept_id)
    {
        $txtFile = DOC_ROOT . "/public/tmp/google_maps/fusion/" . $taxon_concept_id . ".txt";
        $file_array = file($txtFile);
        unset($file_array[0]); //remove first line, the headers
        return $file_array;
    }

    
    private function get_georeference_data($taxonKey)
    {
        $offset = 0;
        $limit = 300;
        $continue = true;
        
        $final = array();
        $final['records'] = array();
        
        while($continue)
        {
            if($offset > 100000) break;
            $url = $this->gbif_occurrence_data . $taxonKey . "&limit=$limit";
            if($offset) $url .= "&offset=$offset";
            if($json = Functions::lookup_with_cache($url, $this->download_options))
            {
                $j = json_decode($json);
                $recs = self::write_to_file($j);
                $final['records'] = array_merge($final['records'], $recs);
                
                echo "\n" . count($j->results) . "\n";
                if($j->endOfRecords)                    $continue = false;
                if(count($final['records']) > 100000)   $continue = false; //limit no. of markers in Google maps is 100K
            }
            else break; //just try again next time...
            $offset += $limit;
        }
        
        $final['count'] = count($final['records']);
        $json = json_encode($final);
        fwrite($this->file, "var data = ".$json);
        
        self::write_to_supplementary_fusion_text($final);
        
        return $final['count'];
    }

    private function get_center_latlon_using_taxonID($taxon_concept_id)
    {
        $rows = self::prepare_data($taxon_concept_id);
        // echo "\n" . count($rows) . "\n";
        $minlat = false; $minlng = false; $maxlat = false; $maxlng = false;
        foreach($rows as $row) //$row is String not array
        {
            $cols = explode("\t", $row);
            // print_r($cols); exit;
            if(count($cols) != 11) continue; //exclude row if total no. of cols is not 11, just to be sure that the col 10 is the "lat,long" column.
            $temp = explode(",", $cols[10]); //col 10 is the latlon column.
            $lat = $temp[0];
            $lon = $temp[1];
            if ($lat && $lon) {
                if ($minlat === false) { $minlat = $lat; } else { $minlat = ($lat < $minlat) ? $lat : $minlat; }
                if ($maxlat === false) { $maxlat = $lat; } else { $maxlat = ($lat > $maxlat) ? $lat : $maxlat; }
                if ($minlng === false) { $minlng = $lon; } else { $minlng = ($lon < $minlng) ? $lon : $minlng; }
                if ($maxlng === false) { $maxlng = $lon; } else { $maxlng = ($lon > $maxlng) ? $lon : $maxlng; }
            }
            $lat_center = $maxlat - (($maxlat - $minlat) / 2);
            $lon_center = $maxlng - (($maxlng - $minlng) / 2);
            // echo "\n[$lat_center][$lon_center]\n";
            echo "\n$lat_center".","."$lon_center\n";
            return $lat_center.','.$lon_center;
        }
        /* computation based on: http://stackoverflow.com/questions/6671183/calculate-the-center-point-of-multiple-latitude-longitude-coordinate-pairs */
    }

    private function get_center_latlon_using_coordinates($records)
    {
        $minlat = false; $minlng = false; $maxlat = false; $maxlng = false;
        foreach($records as $r)
        {
            $lat = $r['lat'];
            $lon = $r['lon'];
            if ($lat && $lon) {
                if ($minlat === false) { $minlat = $lat; } else { $minlat = ($lat < $minlat) ? $lat : $minlat; }
                if ($maxlat === false) { $maxlat = $lat; } else { $maxlat = ($lat > $maxlat) ? $lat : $maxlat; }
                if ($minlng === false) { $minlng = $lon; } else { $minlng = ($lon < $minlng) ? $lon : $minlng; }
                if ($maxlng === false) { $maxlng = $lon; } else { $maxlng = ($lon > $maxlng) ? $lon : $maxlng; }
            }
            $lat_center = $maxlat - (($maxlat - $minlat) / 2);
            $lon_center = $maxlng - (($maxlng - $minlng) / 2);
            return array('center_lat' => $lat_center, 'center_lon' => $lon_center);
        }
        /* computation based on: http://stackoverflow.com/questions/6671183/calculate-the-center-point-of-multiple-latitude-longitude-coordinate-pairs */
    }

    private function write_to_supplementary_fusion_text($final)
    {
        //get publishers:
        $publishers = array();
        foreach($final['records'] as $r)
        {
            if($r['lat'] && $r['lon']) $publishers[$r['publisher']] = '';
        }
        $publishers = array_keys($publishers);
        sort($publishers);
        // print_r($publishers); exit;
        
        //get center lat lon:
        $temp = self::get_center_latlon_using_coordinates($final['records']);
        $center_lat = $temp['center_lat'];
        $center_lon = $temp['center_lon'];
        
        if($center_lat && $center_lon && $publishers)
        {
            $arr = array("center_lat" => $center_lat, "center_lon" => $center_lon, "publishers" => $publishers);
            echo "\n" . json_encode($arr) . "\n"; exit;
            
        }
        
        
        
        
        /*
        
        var data = {"center_lat": 33.83253, "center_lon": -118.4745, "tableID": "1TspfLoWk5Vee6PHP78g09vwYtmNoeMIBgvt6Keiq", 
        "publishers" : ["Cornell Lab of Ornithology (CLO)", "Museum of Comparative Zoology, Harvard University (MCZ)"] };
        
        
        [count] => 619
        [records] => Array
                (
                    [0] => Array
                        (
                            [catalogNumber] => 1272385
                            [sciname] => Chanos chanos (Forsskål, 1775)
                            [publisher] => iNaturalist.org (iNaturalist)
                            [publisher_id] => 28eb1a3f-1c15-4a95-931a-4af90ecb574d
                            [dataset] => iNaturalist research-grade observations
                            [dataset_id] => 50c9509d-22c7-4a22-a47d-8c48425ef4a7
                            [gbifID] => 1088910889
                            [lat] => 1.87214
                            [lon] => -157.42781
                            [recordedBy] => David R
                            [identifiedBy] => 
                            [pic_url] => http://static.inaturalist.org/photos/1596294/original.jpg?1444769372
                        )

                    [1] => Array
                        (
                            [catalogNumber] => 2014-0501
                            [sciname] => Chanos chanos (Forsskål, 1775)
                            [publisher] => MNHN - Museum national d'Histoire naturelle (MNHN)
                            [publisher_id] => 2cd829bb-b713-433d-99cf-64bef11e5b3e
                            [dataset] => Fishes collection (IC) of the Muséum national d'Histoire naturelle (MNHN - Paris)
                            [dataset_id] => f58922e2-93ed-4703-ba22-12a0674d1b54
                            [gbifID] => 1019730375
                            [lat] => -12.8983
                            [lon] => 45.19877
                            [recordedBy] => 
                            [identifiedBy] => 
                            [pic_url] => 
                        )
        */
    }

    private function write_to_file($j)
    {
        $recs = array();
        $i = 0;
        foreach($j->results as $r)
        {
            // if($i > 2) break; //debug
            $i++;
            if(@$r->decimalLongitude && @$r->decimalLatitude)
            {
                $rec = array();
                $rec['catalogNumber']   = (string) @$r->catalogNumber;
                $rec['sciname']         = self::get_sciname($r);
                $rec['publisher']       = self::get_org_name('publisher', @$r->publishingOrgKey);
                $rec['publisher_id']    = @$r->publishingOrgKey;
                if($val = @$r->institutionCode) $rec['publisher'] .= " ($val)";
                $rec['dataset']         = self::get_org_name('dataset', @$r->datasetKey);
                $rec['dataset_id']      = @$r->datasetKey;
                $rec['gbifID']          = $r->gbifID;
                $rec['lat']             = $r->decimalLatitude;
                $rec['lon']             = $r->decimalLongitude;

                // if($val = @$r->recordedBy)           $rec['recordedBy'] = $val;
                // if($val = @$r->identifiedBy)         $rec['identifiedBy'] = $val;
                // if($val = @$r->media[0]->identifier) $rec['pic_url'] = $val;
                
                $rec['recordedBy']   = @$r->recordedBy;
                $rec['identifiedBy'] = @$r->identifiedBy;
                $rec['pic_url']      = @$r->media[0]->identifier;

                self::write_to_fusion_table($rec);
                $recs[] = $rec;
                
                /*
                Catalogue number: 3043
                Uncinocythere stubbsi
                Institution: Unidad de Ecología (Ostrácodos), Dpto. Microbiología y Ecología, Universidad de Valencia
                Collection: Entocytheridae (Ostracoda) World Database
                */
            }
        }
        return $recs;
    }
    
    private function write_to_fusion_table($rec)
    {   /*
        [catalogNumber] => 1272385
        [sciname] => Chanos chanos (Forsskål, 1775)
        [publisher] => iNaturalist.org (iNaturalist)
        [publisher_id] => 28eb1a3f-1c15-4a95-931a-4af90ecb574d
        [dataset] => iNaturalist research-grade observations
        [dataset_id] => 50c9509d-22c7-4a22-a47d-8c48425ef4a7
        [gbifID] => 1088910889
        [lat] => 1.87214
        [lon] => -157.42781
        [recordedBy] => David R
        [pic_url] => http://static.inaturalist.org/photos/1596294/original.jpg?1444769372
        */
        // fwrite($this->file2, implode("\t", $rec) . "\n"); //works OK but it has 2 fields for lat and lon
        
        $rek = $rec;
        $rek['location'] = $rec['lat'] . "," . $rec['lon'];
        unset($rek['lat']);
        unset($rek['lon']);
        fwrite($this->file2, implode("\t", $rek) . "\n");
    }
    
    private function get_sciname($r)
    {
        // if($r->taxonRank == "SPECIES") return $r->species;
        return $r->scientificName;
    }
    
    private function get_org_name($org, $id)
    {
        if(!$id) return "";
        $options = $this->download_options;
        $options['delay_in_minutes'] = 0;
        $options['expire_seconds'] = false; //debug
        
        if($html = Functions::lookup_with_cache($this->html[$org] . $id, $options))
        {
            if(preg_match("/Full title<\/h3>(.*?)<\/p>/ims", $html, $arr)) return strip_tags(trim($arr[1]));
        }
    }
    
    private function get_initial_data($sciname)
    {
        if($usageKey = self::get_usage_key($sciname))
        {
            $count = Functions::lookup_with_cache($this->gbif_record_count . $usageKey, $this->download_options);
            if($count > 0)
            {
                echo "\nTotal:[$count]";
                $rec['usageKey'] = $usageKey;
                $rec["count"] = $count;
                return $rec;
            }
        }
    }

    private function get_usage_key($sciname)
    {
        if($json = Functions::lookup_with_cache($this->gbif_taxon_info . $sciname, $this->download_options))
        {
            $json = json_decode($json);
            $usageKey = false;
            if(!isset($json->usageKey))
            {
                if(isset($json->note)) $usageKey = self::get_usage_key_again($sciname);
                else {} // e.g. Fervidicoccaceae
            }
            else $usageKey = trim((string) $json->usageKey);
            if($val = $usageKey) return $val;
        }
        return false;
    }

    private function get_usage_key_again($sciname)
    {
        if($json = Functions::lookup_with_cache($this->gbif_taxon_info . $sciname . "&verbose=true", $this->download_options))
        {
            $usagekeys = array();
            $options = array();
            $json = json_decode($json);
            if(!isset($json->alternatives)) return false;
            foreach($json->alternatives as $rec)
            {
                if($rec->canonicalName == $sciname)
                {
                    $options[$rec->rank][] = $rec->usageKey;
                    $usagekeys[] = $rec->usageKey;
                }
            }
            if($options)
            {
                /* from NCBIGGIqueryAPI.php connector
                if(isset($options["FAMILY"])) return min($options["FAMILY"]);
                else return min($usagekeys);
                */
                return min($usagekeys);
            }
        }
        return false;
    }

}
?>
