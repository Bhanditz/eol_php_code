<?php
namespace php_active_record;
/* connector: [218] Missouri Botanical Garden:
Tropicos resource - this connector will generate the EOL archive with structured data (for Distribution text)
Partner provides a number of services to share their data to EOL. There is no scraping for this resource.
Partner provides a list of IDs: e.g. http://services.tropicos.org/Name/List?startid=0&PageSize=1000&apikey=2810ce68-f4cf-417c-b336-234bc8928390&format=json
The connector does some looping to get all the IDs.
And partner provides 7 different services for each type of information:
http://services.tropicos.org/Name/25510055?format=json&apikey=2810ce68-f4cf-417c-b336-234bc8928390
http://services.tropicos.org/Name/25510055/ChromosomeCounts?format=xml&apikey=2810ce68-f4cf-417c-b336-234bc8928390
http://services.tropicos.org/Name/25510055/Images?format=xml&apikey=2810ce68-f4cf-417c-b336-234bc8928390
http://services.tropicos.org/Name/25510055/Distributions?format=xml&apikey=2810ce68-f4cf-417c-b336-234bc8928390
http://services.tropicos.org/Name/25510055/Synonyms?format=xml&apikey=2810ce68-f4cf-417c-b336-234bc8928390
http://services.tropicos.org/Name/25510055/References?format=xml&apikey=2810ce68-f4cf-417c-b336-234bc8928390
http://services.tropicos.org/Name/25510055/HigherTaxa?format=xml&apikey=2810ce68-f4cf-417c-b336-234bc8928390

* Tropicos web service goes down daily between 7-8am Eastern. So the connector process sleeps for an hour during this downtime.
* Connector runs for a long time because the sheer number of server requests to get all data for all taxa.
Last collection numbers: taxa=260,738; articles=345,414; images=80,125

Take note of these sample ID's which generated resource without <dwc:ScientificName> last time connector was run: 13000069 13000165 50335886
*/

define("TROPICOS_DOMAIN", "http://www.tropicos.org");
define("TROPICOS_TAXON_DETAIL_PAGE", "http://www.tropicos.org/Name/");
define("TROPICOS_IMAGE_LOCATION_LOW_BANDWIDTH", "http://www.tropicos.org/ImageScaled.aspx?imageid=");
define("TROPICOS_API_KEY", "2810ce68-f4cf-417c-b336-234bc8928390");
define("TROPICOS_API_SERVICE", "http://services.tropicos.org/Name/");

class TropicosArchiveAPI
{

    // const TEMP_FILE_PATH = "/Volumes/Seagate Backup Plus Drive/TropicosArchive_cache/";
    // const TEMP_FILE_PATH  = DOC_ROOT . "/update_resources/connectors/files/TropicosArchive_cache/";
    const TEMP_FILE_PATH = "/Users/eolit/EOL/TropicosArchive_cache/";
    
    function __construct($folder)
    {
        $this->taxa = array();
        $this->path_to_archive_directory = CONTENT_RESOURCE_LOCAL_PATH . '/' . $folder . '_working/';
        $this->archive_builder = new \eol_schema\ContentArchiveBuilder(array('directory_path' => $this->path_to_archive_directory));
        $this->resource_reference_ids = array();
        $this->resource_agent_ids = array();
        $this->vernacular_name_ids = array();
        $this->SPM = 'http://rs.tdwg.org/ontology/voc/SPMInfoItems';
        $this->occurrence_ids = array();
        $this->taxon_ids = array();
        $this->tropicos_ids_list_file = self::TEMP_FILE_PATH . "tropicos_ids.txt";
        $this->download_options = array('download_wait_time' => 1000000, 'timeout' => 10800, 'download_attempts' => 1, 'delay_in_minutes' => 2);
    }

    function get_all_taxa()
    {
        /*
        $id = "100001";
        $arr = array("distribution", "chromosome", "taxon_name", "taxonomy", "synonyms", "taxon_ref", "images");
        foreach($arr as $type)
        {
            if($type == "taxon_name") $url = TROPICOS_API_SERVICE . $id . "?format=json&apikey=" . TROPICOS_API_KEY;
            elseif($type == "taxonomy") $url = TROPICOS_API_SERVICE . $id . "/HigherTaxa?format=xml&apikey=" . TROPICOS_API_KEY;
            elseif($type == "synonyms") $url = TROPICOS_API_SERVICE . $id . "/Synonyms?format=xml&apikey=" . TROPICOS_API_KEY;
            elseif($type == "taxon_ref") $url = TROPICOS_API_SERVICE . $id . "/References?format=xml&apikey=" . TROPICOS_API_KEY;
            elseif($type == "distribution") $url = TROPICOS_API_SERVICE . $id . "/Distributions?format=xml&apikey=" . TROPICOS_API_KEY;
            elseif($type == "images") $url = TROPICOS_API_SERVICE . $id . "/Images?format=xml&apikey=" . TROPICOS_API_KEY;
            elseif($type == "chromosome") $url = TROPICOS_API_SERVICE . $id . "/ChromosomeCounts?format=xml&apikey=" . TROPICOS_API_KEY;
            echo "\n $type: [$url]";
        }
        exit;
        */

        if(!file_exists(self::TEMP_FILE_PATH)) mkdir(self::TEMP_FILE_PATH); // make sure cache directory exists
        self::assemble_id_list();
        self::process_taxa();
        $this->archive_builder->finalize(TRUE);
    }

    private function process_taxa()
    {
        $i = 0;
        foreach(new FileIterator($this->tropicos_ids_list_file) as $line_number => $taxon_id)
        {
            // self::check_server_downtime(); -- temporarily disabled as we get latest schedule for downtime
            if($taxon_id)
            {
                $i++; echo "\n$i. [$taxon_id]";
                // if($i <= 650000) continue; // debug
                self::process_taxon($taxon_id);
            }
        }

        /* during preview mode -- debug
        $ids = array("100184", "100423", "21300106", "21300477", "21300646", "21300647", "21301287", "21301354", "21301544");
        foreach($ids as $taxon_id)
        {
            self::process_taxon($taxon_id);
        }
        */
    }

    function process_taxon($taxon_id)
    {
        self::get_images($taxon_id);
        if(!$name = self::check_cache("taxon_name", $taxon_id)) $name = self::create_cache("taxon_name", $taxon_id);
        $name = json_decode($name, true);
        $sciname = @$name['ScientificName'];
        
        // "NomenclatureStatusName":"No opinion",
        // "Citation":"Tropicos.org. Missouri Botanical Garden. 11 Dec 2013 &lt;http:\/\/www.tropicos.org\/Name\/1&gt;",
        // "Copyright":"© 2013 Missouri Botanical Garden - 4344 Shaw Boulevard - Saint Louis, Missouri 63110",
        
        echo "[$taxon_id] " . $sciname;
        /* working but temporarily commented by Chris Freeland
        self::get_chromosome_count($taxon_id);
        */

        $taxonomy = self::get_taxonomy($taxon_id);
        $reference_ids = array();
        $reference_ids = self::get_taxon_ref($taxon_id);
        
        $taxon = new \eol_schema\Taxon();
        $taxon->taxonID                     = $taxon_id;
        $taxon->scientificName              = $sciname;
        $taxon->scientificNameAuthorship    = @$name["Author"];
        $taxon->taxonRank                   = @$name["Rank"];
        $taxon->furtherInformationURL       = str_replace('\\', "", @$name["Source"]);
        $taxon->namePublishedIn             = @$name["NamePublishedCitation"];
        $taxon->kingdom         = @$taxonomy['kingdom'];
        $taxon->phylum          = @$taxonomy['phylum'];
        $taxon->class           = @$taxonomy['class'];
        $taxon->order           = @$taxonomy['order'];
        $taxon->family          = @$taxonomy['family'];
        $taxon->genus           = @$taxonomy['genus'];
        if($reference_ids) $taxon->referenceID = implode("; ", $reference_ids);
        if(!isset($this->taxon_ids[$taxon->taxonID]))
        {
            $this->taxon_ids[$taxon->taxonID] = 1;
            $this->archive_builder->write_object_to_file($taxon);
        }
        self::get_distributions($taxon_id, $sciname);
        self::get_synonyms($taxon_id);
        echo "\n sciname: [$sciname][$taxon_id]\n";
    }

    private function get_images($taxon_id)
    {
        if(!$xml = self::check_cache("images", $taxon_id)) $xml = self::create_cache("images", $taxon_id);
        else return true;
        
        $xml = simplexml_load_string($xml);
        $with_image = 0;
        foreach($xml->Image as $rec)
        {
            if($rec->Error)
            {
                echo "\n no images - " . $rec->DetailUrl;
                continue;
            }
            $with_image++;
            if($with_image > 15) break;//max no. of images per taxon //debug orig 15
            $description = $rec->NameText . ". " . $rec->LongDescription;
            if($rec->SpecimenId)    $description .= "<br>" . "SpecimenId: " . $rec->SpecimenId;
            if($rec->SpecimenText)  $description .= "<br>" . "SpecimenText: " . $rec->SpecimenText;
            if($rec->Caption)       $description .= "<br>" . "Caption: " . $rec->Caption;
            if($rec->PhotoLocation) $description .= "<br>" . "Location: " . $rec->PhotoLocation;
            if($rec->PhotoDate)     $description .= "<br>" . "Photo taken: " . $rec->PhotoDate;
            if($rec->ImageKindText) $description .= "<br>" . "Image kind: " . $rec->ImageKindText;
            $valid_licenses = array("http://creativecommons.org/licenses/by/3.0/", "http://creativecommons.org/licenses/by-sa/3.0/", "http://creativecommons.org/licenses/by-nc/3.0/", "http://creativecommons.org/licenses/by-nc-sa/3.0/", "http://creativecommons.org/licenses/publicdomain/");
            if(!in_array(trim($rec->LicenseUrl), $valid_licenses))
            {
                echo "\n invalid image license - " . $rec->DetailUrl . "\n";
                continue;
            }
            else echo "\n valid image license - " . $rec->DetailUrl . "\n";
            $license = $rec->LicenseUrl;
            $agent_ids = array();
            if(trim($rec->Photographer) != "")
            {
                $agents = array();
                $agents[] = array("role" => "photographer", "homepage" => "", "fullName" => $rec->Photographer);
                $agent_ids = self::create_agents($agents);
            }
            $rightsHolder = $rec->Copyright;
            $identifier = $rec->ImageId;
            $dataType   = "http://purl.org/dc/dcmitype/StillImage";
            $mimeType   = "image/jpeg";
            $title      = "";
            $subject    = "";
            $source = $rec->DetailUrl;
            if($rec->DetailJpgUrl == 'http://www.tropicos.org/images/imageprotected.jpg') 
            {
                $mediaURL = $rec->ThumbnailUrl;
                $rating = 1;
            }
            else
            {
                $mediaURL = $rec->DetailJpgUrl;
                $rating = 2;
            }
            $description .= "<br>Full sized images can be obtained by going to the <a href='$source'>original source page</a>.";

            // start create media
            $mr = new \eol_schema\MediaResource();
            if($agent_ids) $mr->agentID = implode("; ", $agent_ids);
            $mr->taxonID        = $taxon_id;
            $mr->identifier     = $identifier;
            $mr->type           = $dataType;
            $mr->language       = 'en';
            $mr->format         = $mimeType;
            $mr->furtherInformationURL = $source;
            $mr->Owner          = $rightsHolder;
            $mr->rights         = "";
            $mr->title          = $title;
            $mr->UsageTerms     = $license;
            $mr->description    = $description;
            $mr->accessURI      = $mediaURL;
            $mr->Rating         = $rating;
            $this->archive_builder->write_object_to_file($mr);
        }
    }

    private function get_distributions($taxon_id, $sciname)
    {
        if(!$xml = self::check_cache("distribution", $taxon_id)) $xml = self::create_cache("distribution", $taxon_id);
        $xml = simplexml_load_string($xml);
        $lines = array();
        foreach($xml->Distribution as $rec)
        {
            if(!isset($rec->Location->CountryName)) continue;

            $line = trim($rec->Location->CountryName) . trim($rec->Location->RegionName) . trim($rec->Location->UpperName);
            if(in_array($line, $lines)) continue;
            $lines[] = $line;

            $citation = trim($rec->Reference->FullCitation);
            $ref_url = TROPICOS_DOMAIN . "/Reference/" . trim($rec->Reference->ReferenceId);
            
            // $agents = array();
            // $agents[] = array("role" => "source", "homepage" => "http://www.tropicos.org", "fullName" => "Tropicos");
            // $agent_ids = self::create_agents($agents);

            $text_id = $taxon_id . "_" . $rec->Location->LocationID;
            
            $region = "";
            if($RegionName = trim($rec->Location->RegionName)) $region .= $RegionName;
            if($CountryName = trim($rec->Location->CountryName)) $region .= " - " . $CountryName;
            if($UpperName = trim($rec->Location->UpperName)) $region .= " - " . $UpperName;
            
            self::add_string_types($taxon_id, $text_id, "Distribution", $region, "http://rs.tdwg.org/ontology/voc/SPMInfoItems#Distribution", true);
            self::add_string_types($taxon_id, $text_id, "Taxon", $sciname, "http://rs.tdwg.org/dwc/terms/scientificName");
            self::add_string_types($taxon_id, $text_id, "Country", trim($rec->Location->CountryName), "http://rs.tdwg.org/dwc/terms/country");
            self::add_string_types($taxon_id, $text_id, "Continent", trim($rec->Location->RegionName), "http://rs.tdwg.org/dwc/terms/continent");
            if($upper_name = @$rec->Location->UpperName)
            {
                self::add_string_types($taxon_id, $text_id, "Upper name", $upper_name, "http://tropicos.org/". SparqlClient::to_underscore("Upper name"));
            }
        }

        //     $title      = "Localities documented in Tropicos sources";
        //     $subject    = "http://rs.tdwg.org/ontology/voc/SPMInfoItems#Distribution";
        //     $license    = "http://creativecommons.org/licenses/by-nc-sa/3.0/";
    }

    private function add_string_types($taxon_id, $catnum, $label, $value, $mtype, $mtaxon = false)
    {
        $m = new \eol_schema\MeasurementOrFact();
        $occurrence = $this->add_occurrence($taxon_id, $catnum);
        $m->occurrenceID = $occurrence->occurrenceID;
        if($mtaxon)
        {
            $m->measurementOfTaxon = 'true';
            $m->measurementRemarks = "Note: This information is based on publications available through <a href='http://tropicos.org/'>Tropicos</a> and may not represent the entire distribution. Tropicos does not categorize distributions as native or non-native.";
        }
        $m->measurementType = $mtype;
        $m->source = TROPICOS_DOMAIN . "/Name/" . $taxon_id . "?tab=distribution";
        $m->measurementValue = (string) $value;
        // $m->measurementMethod = '';
        // $m->contributor = '';
        $this->archive_builder->write_object_to_file($m);
    }

    private function add_occurrence($taxon_id, $catnum)
    {
        $occurrence_id = md5($taxon_id . 'o' . $catnum);
        $occurrence_id = $taxon_id . 'O' . $catnum;
        if(isset($this->occurrence_ids[$occurrence_id])) return $this->occurrence_ids[$occurrence_id];
        $o = new \eol_schema\Occurrence();
        $o->occurrenceID = $occurrence_id;
        $o->taxonID = $taxon_id;
        $this->archive_builder->write_object_to_file($o);
        $this->occurrence_ids[$occurrence_id] = $o;
        return $o;
    }

    private function create_agents($agents)
    {
        $agent_ids = array();
        foreach($agents as $rec)
        {
            $agent = (string) trim($rec["fullName"]);
            if(!$agent) continue;
            $r = new \eol_schema\Agent();
            $r->term_name = $agent;
            $r->identifier = md5("$agent|" . $rec["role"]);
            $r->agentRole = $rec["role"];
            $r->term_homepage = $rec["homepage"];
            $agent_ids[] = $r->identifier;
            if(!in_array($r->identifier, $this->resource_agent_ids))
            {
               $this->resource_agent_ids[] = $r->identifier;
               $this->archive_builder->write_object_to_file($r);
            }
        }
        return $agent_ids;
    }

    private function get_taxon_ref($taxon_id)
    {
        if(!$xml = self::check_cache("taxon_ref", $taxon_id)) $xml = self::create_cache("taxon_ref", $taxon_id);
        $xml = simplexml_load_string($xml);
        $reference_ids = array();
        foreach($xml->NameReference as $rec)
        {
            if($ref_id = trim($rec->Reference->ReferenceId))
            {
                $reference_ids[] = $ref_id;
                $ref_url = TROPICOS_DOMAIN . "/Reference/" . $ref_id;
                $citation = trim($rec->Reference->FullCitation);
                self::add_reference($citation, $ref_id, $ref_url);
            }
        }
        return $reference_ids;
    }

    private function get_synonyms($taxon_id)
    {
        $records = array();
        if(!$xml = self::check_cache("synonyms", $taxon_id)) $xml = self::create_cache("synonyms", $taxon_id);
        $xml = simplexml_load_string($xml);
        foreach($xml->Synonym as $syn)
        {
            $synonym = trim($syn->SynonymName->ScientificNameWithAuthors);
            $NameId = trim($syn->SynonymName->NameId);
            $Family = trim($syn->SynonymName->Family);
            $reference_ids = array();
            if($ref_id = (string) $syn->Reference->ReferenceId)
            {
                $citation = $syn->Reference->AuthorString . ". " . $syn->Reference->ArticleTitle . ". " . $syn->Reference->AbbreviatedTitle . ". " . $syn->Reference->Collation . ".";
                $citation = str_replace("..", ".", $citation);
                $reference_ids[] = $ref_id;
                self::add_reference($citation, $ref_id);
            }
            if($NameId) $records[] = array("id" => $NameId, "synonym" => $synonym, "family" => $Family, "ref_ids" => $reference_ids);
        }
        if($records) self::add_synonyms($records, $taxon_id);
    }

    private function add_reference($citation, $ref_id, $ref_url = false)
    {
        if($citation)
        {
            $r = new \eol_schema\Reference();
            $r->full_reference = (string) $citation;
            $r->identifier = $ref_id;
            if($ref_url) $r->uri = $ref_url;
            if(!isset($this->resource_reference_ids[$r->identifier]))
            {
               $this->resource_reference_ids[$r->identifier] = 1;
               $this->archive_builder->write_object_to_file($r);
            }
        }
    }

    private function add_synonyms($records, $acceptedNameUsageID)
    {
        foreach($records as $rec)
        {
            $synonym = new \eol_schema\Taxon();
            $synonym->taxonID               = $rec["id"];
            $synonym->scientificName        = $rec["synonym"];
            $synonym->acceptedNameUsageID   = $acceptedNameUsageID;
            $synonym->taxonomicStatus       = "synonym";
            $synonym->family                = @$rec["family"];
            $synonym->furtherInformationURL = TROPICOS_TAXON_DETAIL_PAGE . $rec["id"];
            if($rec["ref_ids"]) $synonym->referenceID = implode("; ", $rec["ref_ids"]);
            if(!isset($this->taxon_ids[$synonym->taxonID]))
            {
                $this->taxon_ids[$synonym->taxonID] = 1;
                $this->archive_builder->write_object_to_file($synonym);
            }
            // else
            // {
            //     echo "\n investigate: synonym already entered \n";
            //     print_r($rec);
            // }
        }
    }
    
    function get_taxonomy($taxon_id)
    {
        $taxonomy = array();
        if(!$xml = self::check_cache("taxonomy", $taxon_id)) $xml = self::create_cache("taxonomy", $taxon_id);
        $xml = simplexml_load_string($xml);
        foreach($xml->Name as $rec)
        {
            if($rec->Rank == "kingdom") $taxonomy['kingdom'] = $rec->ScientificNameWithAuthors;
            if($rec->Rank == "phylum")  $taxonomy['phylum'] = $rec->ScientificNameWithAuthors;
            if($rec->Rank == "class")   $taxonomy['class'] = $rec->ScientificNameWithAuthors;
            if($rec->Rank == "order")   $taxonomy['order'] = $rec->ScientificNameWithAuthors;
            if($rec->Rank == "family")  $taxonomy['family'] = $rec->ScientificNameWithAuthors;
            if($rec->Rank == "genus")   $taxonomy['genus'] = $rec->ScientificNameWithAuthors;
        }
        return $taxonomy;
    }

    function get_chromosome_count($taxon_id)
    {
        if(!$xml = self::check_cache("chromosome", $taxon_id)) $xml = self::create_cache("chromosome", $taxon_id);
        $xml = simplexml_load_string($xml);

        $refs = array();
        $temp_reference = array();
        $with_content = false;
        $GametophyticCount = array();
        $SporophyticCount = array();
        $IPCNReferenceID = array();
        foreach($xml->ChromosomeCount as $rec)
        {
            if(!isset($rec->GametophyticCount) && !isset($rec->SporophyticCount)) continue;
            $with_content = true;
            $citation = trim($rec->Reference->FullCitation);
            $ref_url = TROPICOS_DOMAIN . "/Reference/" . trim($rec->Reference->ReferenceId);
            if($rec->GametophyticCount) $GametophyticCount["$rec->GametophyticCount"] = 1;
            if($rec->SporophyticCount) $SporophyticCount["$rec->SporophyticCount"] = 1;
            if(trim($rec->IPCNReferenceID))
            {                
                $IPCNref_url = TROPICOS_DOMAIN . "/Reference/" . trim($rec->IPCNReferenceID);
                $index = "<a target='tropicos' href='" . $IPCNref_url . "'>" . $rec->IPCNAbbreviation . "</a>";
                $IPCNReferenceID[$index] = 1;
            }
            //this is to prevent getting duplicate references
            if(!in_array($citation, $temp_reference)) $refs[] = array("url" => $ref_url, "fullReference" => $citation);
            $temp_reference[] = $citation;
        }
        $description = "";
        $GametophyticCount = array_keys($GametophyticCount);
        $SporophyticCount = array_keys($SporophyticCount);
        $IPCNReferenceID = array_keys($IPCNReferenceID);
        if($GametophyticCount) $description .= "Gametophyte chromosome count = " . implode("; ", $GametophyticCount) . "<br><br>";
        if($SporophyticCount) $description .= "Sporophyte chromosome count = " . implode("; ", $SporophyticCount) . "<br><br>";
        if($IPCNReferenceID) $description .= "IPCN Ref. = " . implode("; ", $IPCNReferenceID) . "<br><br>";
        if($with_content)
        {
            $source = TROPICOS_DOMAIN . "/Name/" . $taxon_id . "?tab=chromosomecounts";
            $identifier = $taxon_id . "_chromosome";
            $mimeType   = "text/html";
            $dataType   = "http://purl.org/dc/dcmitype/Text";
            $title      = "Chromosome Counts";
            $subject    = "http://rs.tdwg.org/ontology/voc/SPMInfoItems#Genetics";
            $agent      = array();
            $agent[]    = array("role" => "source", "homepage" => "http://www.tropicos.org", "fullName" => "Tropicos");
            $mediaURL   = "";
            $location   = "";
            $license    = "http://creativecommons.org/licenses/by-nc-sa/3.0/";
            $rightsHolder   = "";
        }
    }

    private function assemble_id_list()
    {
        $OUT = fopen($this->tropicos_ids_list_file, "w");
        $startid = 0; // debug orig value 0; 1600267 with mediaURL and <location>; 1201245 with thumbnail size images; 100391155 near the end
        $count = 0;
        while(true)
        {
            $count++;
            if(!$contents = self::check_cache("id_list", $startid)) $contents = self::create_cache("id_list", $startid);
            if($contents)
            {
                $ids = json_decode($contents, true);
                echo "\n count: " . count($ids);
                $str = "";
                foreach($ids as $id)
                {
                    if(isset($id["Error"])) return; // no more ids --- [{"Error":"No names were found"}]
                    if($id["NameId"])
                    {
                        $str .= $id["NameId"] . "\n";
                        $startid = $id["NameId"];
                    }
                    else echo "\n nameid undefined";
                }
                $startid++; // to avoid duplicate ids, set next id to get
                if($str != "") fwrite($OUT, $str);
            }
            else
            {
                echo "\n --server not accessible-- \n";
                break;
            }
            // if($count == 1300) break; // normal operation
        }
        fclose($OUT);
    }

    private function check_cache($dir_name, $id, $last_update = null)
    {
        $file_path = self::TEMP_FILE_PATH . $dir_name . "/" . $id . ".txt";
        echo "\n type: [$dir_name][$file_path]";
        if(file_exists($file_path))
        {
            $file_contents = file_get_contents($file_path);
            /*
            // if we're checking the cache for GetInfo and there is a later copy, then
            // delete BOTH the GetInfo and GetSizes calls for that media
            if($dir_name == 'photosGetInfo' && (!$last_update || @$json_object->photo->dates->lastupdate != $last_update))
            {
                unlink($file_path);
                $sizes_path = DOC_ROOT . "/update_resources/connectors/files/flickr_cache/photosGetSizes/$filter_dir/$photo_id.json";
                @unlink($sizes_path);
            }else return $json_object;
            */
            return $file_contents;
        }
        return false;
    }

    private function create_cache($type, $id)
    {
        if($type == "id_list") // $id here is the startid
        {
            $pagesize = 5;
            $pagesize = 1000; // debug orig value max size is 1000; pagesize is the no. of records returned from Tropicos master list service
            $url = TROPICOS_API_SERVICE . "List?startid=$id&PageSize=$pagesize&apikey=" . TROPICOS_API_KEY . "&format=json";
        }
        
        // $id here is the taxon_id
        elseif($type == "taxon_name")   $url = TROPICOS_API_SERVICE . $id . "?format=json&apikey=" . TROPICOS_API_KEY;
        elseif($type == "taxonomy")     $url = TROPICOS_API_SERVICE . $id . "/HigherTaxa?format=xml&apikey=" . TROPICOS_API_KEY;
        elseif($type == "synonyms")     $url = TROPICOS_API_SERVICE . $id . "/Synonyms?format=xml&apikey=" . TROPICOS_API_KEY;
        elseif($type == "taxon_ref")    $url = TROPICOS_API_SERVICE . $id . "/References?format=xml&apikey=" . TROPICOS_API_KEY;
        elseif($type == "distribution") $url = TROPICOS_API_SERVICE . $id . "/Distributions?format=xml&apikey=" . TROPICOS_API_KEY;
        elseif($type == "images")       $url = TROPICOS_API_SERVICE . $id . "/Images?format=xml&apikey=" . TROPICOS_API_KEY;
        elseif($type == "chromosome")   $url = TROPICOS_API_SERVICE . $id . "/ChromosomeCounts?format=xml&apikey=" . TROPICOS_API_KEY;
        
        if($contents = Functions::get_remote_file($url, $this->download_options))
        {
            self::add_to_cache($type, $id, $contents);
            return $contents;
        }
        else return false;
    }

    private function add_to_cache($dir_name, $id, $contents)
    {
        $dir_path = self::TEMP_FILE_PATH . $dir_name;
        $file_path = $dir_path . "/" . $id . ".txt";
        
        // make sure cache directory exists
        if(!file_exists($dir_path)) mkdir($dir_path);
        
        // write to cache file
        $FILE = fopen($file_path, "w+");
        fwrite($FILE, $contents);
        fclose($FILE);
    }

    private function get_texts($description, $taxon_id, $title, $subject, $code, $reference_ids = null, $agent_ids = null)
    {
            $description = str_ireplace("&", "", $description);
            $mr = new \eol_schema\MediaResource();
            if($reference_ids) $mr->referenceID = implode("; ", $reference_ids);
            if($agent_ids) $mr->agentID = implode("; ", $agent_ids);
            $mr->taxonID = $taxon_id;
            $mr->identifier = $mr->taxonID . "_" . $code;
            $mr->type = 'http://purl.org/dc/dcmitype/Text';
            $mr->language = 'en';
            $mr->format = 'text/html';
            $mr->furtherInformationURL = '';
            $mr->description = utf8_encode($description);
            $mr->CVterm = $this->SPM . $subject;
            $mr->title = $title;
            $mr->creator = '';
            $mr->CreateDate = '';
            $mr->modified = '';
            $mr->UsageTerms = 'http://creativecommons.org/licenses/by-nc/3.0/';
            $mr->Owner = '';
            $mr->publisher = '';
            $mr->audience = 'Everyone';
            $mr->bibliographicCitation = '';
            $this->archive_builder->write_object_to_file($mr);
    }

    private function check_server_downtime()
    {
        $time = date('H:i:s', time());
        if($time >= "06:40:00" && $time <= "07:00:00")
        {
            echo "\n\n Process stopped at [$time], will resume in 1.5 hours...";
            sleep((60*60)+(60*30)); //sleep 1.5 hours
        }
    }

}
?>