<?php
namespace php_active_record;
/* connector: [741] IRMNG data and classification
Connector processes the DWC-A file from partner (CSV files).
Connector downloads the zip file, extracts, reads, assembles the data and generates the EOL DWC-A resource.
*/
class IrmngAPI
{
    function __construct($folder = null)
    {
        if($folder)
        {
            $this->path_to_archive_directory = CONTENT_RESOURCE_LOCAL_PATH . '/' . $folder . '_working/';
            $this->archive_builder = new \eol_schema\ContentArchiveBuilder(array('directory_path' => $this->path_to_archive_directory));
            $this->occurrence_ids = array();
        }

        // $this->zip_path = "http://localhost/~eolit/cp/IRMNG/IRMNG_DWC.zip";
        // $this->zip_path = "https://dl.dropboxusercontent.com/u/7597512/IRMNG/IRMNG_DWC.zip";
        $this->zip_path = "http://www.cmar.csiro.au/datacentre/downloads/IRMNG_DWC.zip";

        // these 2 text files were generated by a utility function
        // $this->taxa_with_blank_status_dump_file                   = "http://localhost/~eolit/cp/IRMNG/taxa_with_blank_status.txt";
        // $this->taxa_with_blank_status_but_with_eol_page_dump_file = "http://localhost/~eolit/cp/IRMNG/taxa_with_blank_status_but_with_eol_page.txt";
        $this->taxa_with_blank_status_dump_file                   = "https://dl.dropboxusercontent.com/u/7597512/IRMNG/taxa_with_blank_status.txt";
        $this->taxa_with_blank_status_but_with_eol_page_dump_file = "https://dl.dropboxusercontent.com/u/7597512/IRMNG/taxa_with_blank_status_but_with_eol_page.txt";
        
        $this->text_path = array();
        $this->names = array();
        $this->source_links["kingdom"] = "http://www.marine.csiro.au/mirrorsearch/ir_search.list_phylum?kingdom=";
        $this->source_links["phylum"] = "http://www.marine.csiro.au/mirrorsearch/ir_search.list_class?phylum=";
        $this->source_links["class"] = "http://www.marine.csiro.au/mirrorsearch/ir_search.list_order?class=";
        $this->source_links["order"] = "http://www.marine.csiro.au/mirrorsearch/ir_search.list_family?order=";
        $this->source_links["family"] = "http://www.marine.csiro.au/mirrorsearch/ir_search.list_genera?fam_id=";
        $this->source_links["genus"] = "http://www.marine.csiro.au/mirrorsearch/ir_search.list_species?gen_id=";
        $this->source_links["species"] = "http://www.marine.csiro.au/mirrorsearch/ir_search.go?groupchoice=any&cSub=Check+species+name%28s%29&match_type=normal&response_format=html&searchtxt=";
        $this->taxa_ids_with_blank_taxonomicStatus = array();

        /*
        // utility 1 of 2 - saving to dump file
        // $this->TEMP_DIR = create_temp_dir() . "/";
        // $this->taxa_with_blank_status_dump_file = $this->TEMP_DIR . "taxa_with_blank_status.txt";
        
        // utility 2 of 2 - generating "taxa_with_blank_status_but_with_eol_page"
        $this->TEMP_DIR = create_temp_dir() . "/";
        $this->taxa_with_blank_status_but_with_eol_page_dump_file = $this->TEMP_DIR . "taxa_with_blank_status_but_with_eol_page.txt";
        */
    }

    function get_irmng_families() // utility for WEB-5220 Comparison of FALO classification to others that we have
    {
        if(!self::load_zip_contents()) return;
        print_r($this->text_path);
        $records = self::csv_to_array($this->text_path["IRMNG_DWC"], "families");
        self::remove_temp_dir();
        return $records;
    }
    
    function get_all_taxa()
    {
        if(!self::load_zip_contents()) return;
        print_r($this->text_path);
        self::csv_to_array($this->text_path["IRMNG_DWC"], "classification");
        self::csv_to_array($this->text_path["IRMNG_DWC_SP_PROFILE"], "extant_habitat_data");
        $this->archive_builder->finalize(TRUE);
        self::remove_temp_dir();
    }
    
    private function remove_temp_dir()
    {
        // remove temp dir
        $path = $this->text_path["IRMNG_DWC"];
        $parts = pathinfo($path);
        $parts["dirname"] = str_ireplace("/IRMNG_DWC", "", $parts["dirname"]);
        recursive_rmdir($parts["dirname"]);
        debug("\n temporary directory removed: " . $parts["dirname"]);
    }
    
    private function csv_to_array($csv_file, $type)
    {
        if($type != "families")
        {
            if($val = $this->taxa_ids_with_blank_taxonomicStatus) $taxa_ids_with_blank_taxonomicStatus = $val;
            else $taxa_ids_with_blank_taxonomicStatus = self::get_taxa_ids_with_blank_taxonomicStatus();
        }
        else $taxa_ids_with_blank_taxonomicStatus = array();
        
        $i = 0;
        $file = fopen($csv_file, "r");
        while(!feof($file))
        {
            $i++;
            echo "\n [$type] $i - ";
            if($i == 1) $fields = fgetcsv($file);
            else
            {
                $rec = array();
                $temp = fgetcsv($file);
                $k = 0;
                if(!$temp) continue;
                foreach($temp as $t)
                {
                    $rec[$fields[$k]] = $t;
                    $k++;
                }
                
                if(in_array($type, array("get_taxa_ids_with_data", "extant_habitat_data"))) $taxon_id = $rec["TAXON_ID"];
                else                                                                        $taxon_id = $rec["TAXONID"];
                
                if(isset($taxa_ids_with_blank_taxonomicStatus[$taxon_id])) continue;
                
                if    ($type == "classification")           $this->create_instances_from_taxon_object($rec);
                elseif($type == "extant_habitat_data")      self::process_profile($rec);
                elseif($type == "families")
                {
                    if($rec["TAXONRANK"] == "family") $records[] = Functions::canonical_form($rec["SCIENTIFICNAME"]);
                }
            }
        }
        fclose($file);
        if($type == "get_taxa_ids_with_data") return $taxon_ids;
        if($type == "families") return array_unique($records);
    }

    private function create_instances_from_taxon_object($rec)
    {
        $taxon = new \eol_schema\Taxon();
        $taxon->taxonID                  = $rec["TAXONID"];
        if($val = trim($rec["SCIENTIFICNAMEAUTHORSHIP"])) $taxon->scientificName = str_replace($val, "", $rec["SCIENTIFICNAME"]);
        else                                              $taxon->scientificName = $rec["SCIENTIFICNAME"];
        echo " - " . $taxon->scientificName . " [$taxon->taxonID]";
        $taxon->family                   = $rec["FAMILY"];
        $taxon->genus                    = $rec["GENUS"];
        $taxon->taxonRank                = $rec["TAXONRANK"];
        $taxon->taxonomicStatus          = $rec["TAXONOMICSTATUS"];
        $taxon->taxonRemarks             = $rec["TAXONREMARKS"];
        $taxon->namePublishedIn          = $rec["NAMEPUBLISHEDIN"];
        $taxon->scientificNameAuthorship = $rec["SCIENTIFICNAMEAUTHORSHIP"];
        $taxon->parentNameUsageID        = $rec["PARENTNAMEUSAGEID"];

        // used so that MeasurementOrFact will have source link
        if(!in_array($taxon->taxonRank, array("family", "genus"))) $this->names[$taxon->taxonID]["n"] = $taxon->scientificName; // save only K,P,C,O & S; excludes family & genus
        $this->names[$taxon->taxonID]["r"] = $taxon->taxonRank;

        $this->archive_builder->write_object_to_file($taxon);
        return true;
        
        /* allowed in EOL, but IRMNG doesn't have these 2 fields:
            http://rs.tdwg.org/ac/terms/furtherInformationURL
            http://eol.org/schema/media/referenceID
        
        not allowed in EOL, but IRMNG have these 8 fields:
            $taxon->specificEpithet          = $rec["SPECIFICEPITHET"];
            $taxon->nomenclaturalStatus      = $rec["NOMENCLATURALSTATUS"];
            $taxon->nameAccordingTo          = $rec["NAMEACCORDINGTO"];
            $taxon->parentNameUsage          = $rec["PARENTNAMEUSAGE"];
            $taxon->originalNameUsageID      = $rec["ORIGINALNAMEUSAGEID"];
            $taxon->acceptedNameUsageID      = $rec["ACCEPTEDNAMEUSAGEID"];
            $taxon->modified                 = $rec["MODIFIED"];
            $taxon->nomenclaturalCode        = $rec["NOMENCLATURALCODE"];
        */
        /* for debug...
        $this->debug_status["rank"][$taxon->taxonRank] = 1;
        $this->debug_status[$rec["TAXONOMICSTATUS"]] = 1;
        if(isset($this->debug_status_count[$rec["TAXONOMICSTATUS"]]["count"])) $this->debug_status_count[$rec["TAXONOMICSTATUS"]]["count"]++;
        else                                                                   $this->debug_status_count[$rec["TAXONOMICSTATUS"]]["count"] = 1;
        */
        /*
        if(trim($taxon->taxonomicStatus) == "") // utility: this just saves all names with blank taxonomicStatus
        {
            self::save_to_dump(array("id" => $taxon->taxonID, "name" => $taxon->scientificName), $this->taxa_with_blank_status_dump_file);
            return false; // right now just ignore blank taxonomicStatus. once we determine which names have EOL pages then we will include those
        }
        */
    }

    private function process_profile($record)
    {
        echo " - " . $record["TAXON_ID"];
        $rec = array();
        $rec["taxon_id"] = $record["TAXON_ID"];
        if(isset($this->names[$record["TAXON_ID"]]))
        {
            $rec["rank"] = $this->names[$record["TAXON_ID"]]["r"];
            if(!in_array($rec["rank"], array("family", "genus"))) $rec["SCIENTIFICNAME"] = $this->names[$record["TAXON_ID"]]["n"];
        }
        $conservation_status = false;
        if($record["ISEXTINCT"] == "TRUE")      $conservation_status = "http://eol.org/schema/terms/extinct";
        elseif($record["ISEXTINCT"] == "FALSE") $conservation_status = "http://eol.org/schema/terms/extant";
        $habitat = false;
        if($record["ISMARINE"] == "TRUE")       $habitat = "http://purl.obolibrary.org/obo/ENVO_00000569";
        elseif($record["ISMARINE"] == "FALSE")  $habitat = "http://purl.obolibrary.org/obo/ENVO_00002037";
        $rec["catnum"] = "cs"; //conservation status
        if($val = $conservation_status) self::add_string_types($rec, "Conservation status", $val, "http://eol.org/schema/terms/ExtinctionStatus");
        $rec["catnum"] = "h"; //habitat
        if($val = $habitat)             self::add_string_types($rec, "Habitat", $val, "http://rs.tdwg.org/dwc/terms/habitat");
    }

    private function add_string_types($rec, $label, $value, $mtype)
    {
        $taxon_id = $rec["taxon_id"];
        $catnum = $rec["catnum"];
        $m = new \eol_schema\MeasurementOrFact();
        $occurrence = $this->add_occurrence($taxon_id, $catnum);
        $m->occurrenceID = $occurrence->occurrenceID;
        $m->measurementType = $mtype;
        $m->measurementValue = $value;
        $m->measurementOfTaxon = 'true';
        // $m->measurementRemarks = ''; $m->contributor = ''; $m->measurementMethod = '';
        if(isset($rec["rank"]))
        {
            $param = "";
            if    (in_array($rec["rank"], array("kingdom", "phylum", "class", "order"))) $param = $rec["SCIENTIFICNAME"];
            elseif(in_array($rec["rank"], array("family", "genus")))                     $param = $taxon_id;
            elseif($rec["rank"] == "species")                                            $param = urlencode(trim($rec["SCIENTIFICNAME"]));
            if($param) $m->source = $this->source_links[$rec["rank"]] . $param;
        }
        $this->archive_builder->write_object_to_file($m);
    }

    private function add_occurrence($taxon_id, $catnum)
    {
        $occurrence_id = $taxon_id . '_' . $catnum;
        $o = new \eol_schema\Occurrence();
        $o->occurrenceID = $occurrence_id;
        $o->taxonID = $taxon_id;
        $this->archive_builder->write_object_to_file($o);
        return $o;
    }

    private function load_zip_contents()
    {
        $this->TEMP_FILE_PATH = create_temp_dir() . "/";
        if($file_contents = Functions::get_remote_file($this->zip_path, array('timeout' => 999999, 'download_attempts' => 2, 'delay_in_minutes' => 2)))
        {
            $parts = pathinfo($this->zip_path);
            $temp_file_path = $this->TEMP_FILE_PATH . "/" . $parts["basename"];
            $TMP = fopen($temp_file_path, "w");
            fwrite($TMP, $file_contents);
            fclose($TMP);
            $output = shell_exec("tar -xzf $temp_file_path -C $this->TEMP_FILE_PATH");
            if(!file_exists($this->TEMP_FILE_PATH . "/IRMNG_DWC_20140131.csv")) 
            {
                $this->TEMP_FILE_PATH = str_ireplace(".zip", "", $temp_file_path);
                if(!file_exists($this->TEMP_FILE_PATH . "/IRMNG_DWC_20140131.csv")) return false;
            }
            $this->text_path["IRMNG_DWC"] = $this->TEMP_FILE_PATH . "/IRMNG_DWC_20140131.csv";
            $this->text_path["IRMNG_DWC_SP_PROFILE"] = $this->TEMP_FILE_PATH . "/IRMNG_DWC_SP_PROFILE_20140131.csv";
            return true;
        }
        else
        {
            debug("\n\n Connector terminated. Remote files are not ready.\n\n");
            return false;
        }
    }

    private function save_to_dump($data, $filename) // utility
    {
        $WRITE = fopen($filename, "a");
        if($data && is_array($data)) fwrite($WRITE, json_encode($data) . "\n");
        else                         fwrite($WRITE, $data . "\n");
        fclose($WRITE);
    }

    private function get_taxa_ids_with_blank_taxonomicStatus()
    {
        $names_with_blank_status_but_with_eol_page = self::get_names_with_blank_status_but_with_eol_page();
        $taxa_ids = array();
        // [taxa_with_blank_status_dump_file] was generated by a utility function
        if($filename = Functions::save_remote_file_to_local($this->taxa_with_blank_status_dump_file, array('timeout' => 172800, 'download_attempts' => 2, 'delay_in_minutes' => 2)))
        {
            foreach(new FileIterator($filename) as $line_number => $line)
            {
                if($line)
                {
                    $arr = json_decode($line, true);
                    // exclude IDs even if blank status but we already have EOL pages for them
                    if(!isset($names_with_blank_status_but_with_eol_page[$arr["name"]])) $taxa_ids[$arr["id"]] = "";
                }
            }
            echo "\n\n taxa_with_blank_status: " . count($taxa_ids);
            unlink($filename);
        }
        $this->taxa_ids_with_blank_taxonomicStatus = $taxa_ids;
        return $taxa_ids;
    }

    private function get_names_with_blank_status_but_with_eol_page()
    {
        $names = array();
        if($filename = Functions::save_remote_file_to_local($this->taxa_with_blank_status_but_with_eol_page_dump_file, array('timeout' => 172800, 'download_attempts' => 2, 'delay_in_minutes' => 2)))
        {
            foreach(new FileIterator($filename) as $line_number => $line)
            {
                if($val = trim($line)) $names[$val] = "";
            }
            unlink($filename);
        }
        return $names;
    }

    /*
    public function get_taxa_without_status_but_with_eol_page() // utility
    {
        $filename = Functions::save_remote_file_to_local($this->taxa_with_blank_status_dump_file, array('timeout' => 172800, 'download_attempts' => 2, 'delay_in_minutes' => 2)); // this was generated by a utility function
        $eol_api = "http://eol.org/api/search/1.0.json?exact=true&q=";
        $download_options = array('download_wait_time' => 2000000, 'timeout' => 10800, 'download_attempts' => 1); //, 'expire_seconds' => 0);
        $i = 0;
        foreach(new FileIterator($filename) as $line_number => $line)
        {
            $i++;
            $cont = false;
            // if($i >= 1 && $i <= 100000) $cont = true;
            // if($i >= 100001 && $i <= 200000) $cont = true;
            // if($i >= 200001 && $i <= 300000) $cont = true;
            // if($i >= 300001 && $i <= 350000) $cont = true;
            // if($i >= 350001 && $i <= 360000) $cont = true;
            if($i >= 360001 && $i <= 400000) $cont = true;
            
            if(!$cont) continue;
            if($line)
            {
                echo "\n $i. ";
                $arr = json_decode($line, true);
                $arr["name"] = trim($arr["name"]);
                if(strpos($arr["name"], "×") === false) {} // not found - this is the "×" char infront of a name string e.g. "×Diaker..."; "\u00d7"
                else continue; // found - if found just ignore the name
                if($json = Functions::lookup_with_cache($eol_api . $arr["name"], $download_options))
                {
                    $taxon = json_decode($json, true);
                    echo " totalResults: " . $taxon["totalResults"];
                    if(intval($taxon["totalResults"]) > 0)
                    {
                        echo " with eol page";
                        self::save_to_dump($arr["name"], $this->taxa_with_blank_status_but_with_eol_page_dump_file);
                    }
                    else echo " without eol page";
                }
            }
        }
        echo "\n\n taxa_with_blank_status: [$i]";
        unlink($filename);
    }
    */

}
?>