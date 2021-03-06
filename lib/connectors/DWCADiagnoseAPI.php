<?php
namespace php_active_record;
/* This will contain functions to diagnose EOL DWC-A files */
class DWCADiagnoseAPI
{
    function __construct()
    {
        $this->file['taxon']             = "http://rs.tdwg.org/dwc/terms/taxonID";
        $this->file['occurrence']        = "http://rs.tdwg.org/dwc/terms/occurrenceID";
        $this->file['reference']         = "http://purl.org/dc/terms/identifier";
        $this->file['document']          = "http://purl.org/dc/terms/identifier";
        $this->file['agent']             = "http://purl.org/dc/terms/identifier";
        $this->file['vernacularname']    = "http://rs.tdwg.org/dwc/terms/vernacularName";
        $this->file['measurementorfact'] = "http://rs.tdwg.org/dwc/terms/measurementID";
        // $this->file['association']       = "http://eol.org/schema/associationID";
    }

    function check_unique_ids($resource_id, $file_extension = ".tab")
    {
        $harvester = new ContentArchiveReader(NULL, CONTENT_RESOURCE_LOCAL_PATH . $resource_id . "/");
        $tables = $harvester->tables;
        $tables = array_keys($tables);
        $tables = array_diff($tables, array("http://rs.tdwg.org/dwc/terms/measurementorfact")); //exclude measurementorfact
        $tables = array_diff($tables, array("http://rs.gbif.org/terms/1.0/vernacularname")); //exclude vernacular name
        $tables = array_diff($tables, array("http://eol.org/schema/association")); //exclude association name

        print_r($tables);
        foreach($tables as $table) self::process_fields($harvester->process_row_type($table), pathinfo($table, PATHINFO_BASENAME));
    }

    private function process_fields($records, $class)
    {
        $temp_ids = array();
        echo "\n[$class]";
        foreach($records as $rec)
        {
            $keys = array_keys($rec);
            if(!($field_index_key = @$this->file[$class]))
            {
                echo "\nnot yet defined [$class]\n";
                print_r($keys);
                print_r($rec);
                return false;
            }

            if(!isset($temp_ids[$rec[$field_index_key]])) $temp_ids[$rec[$field_index_key]] = '';
            else
            {
                if($val = $rec[$field_index_key])
                {
                    echo "\n -- not unique ID in [$class] - {" . $rec[$field_index_key] . "} - [$field_index_key]";
                    return false;
                }
            }
            
        }
        echo "\n -- OK\n";
        return true;
    }

    function cannot_delete() // a utility
    {
        $final = array();
        foreach(new FileIterator(DOC_ROOT . "/public/tmp/cant_delete.txt") as $line => $r) $final[pathinfo($r, PATHINFO_DIRNAME)] = '';
        $final = array_keys($final);
        asort($final);
        foreach($final as $e) echo "\n $e";
        echo "\n";
    }

    function get_undefined_uris() // a utility
    {
        $ids = array("872", "886", "887", "892", "893", "894", "885", "42");
        foreach($ids as $id)
        {
            echo "\nprocessing id [$id]";
            if($undefined_uris = Functions::get_undefined_uris_from_resource($id)) print_r($undefined_uris);
            echo "\nundefined uris: " . count($undefined_uris) . "\n";
        }
    }
    
    function list_unique_taxa_from_XML_resource($resource_id)
    {
        $file = CONTENT_RESOURCE_LOCAL_PATH . "/$resource_id" . ".xml";
        $xml = simplexml_load_file($file);
        $taxa = array();
        $objects = array();
        foreach($xml->taxon as $t)
        {
            $do_count = sizeof($t->dataObject);
            if($do_count > 0)
            {
                $t_dwc = $t->children("http://rs.tdwg.org/dwc/dwcore/");
                $t_dc = $t->children("http://purl.org/dc/elements/1.1/");
                $sciname    = Functions::import_decode($t_dwc->ScientificName);
                $taxa[$sciname] = '';
            }
            
            foreach($t->dataObject as $o)
            {
                $t_dc2 = $o->children("http://purl.org/dc/elements/1.1/");
                $identifier = Functions::import_decode($t_dc2->identifier);
                $objects[$identifier] = '';
            }
            
        }
        print_r($taxa);
        print_r($objects);
        echo "\nTotal taxa: " . count($taxa) . "\n";
        echo "\nTotal objects: " . count($objects) . "\n";
    }

    //============================================================
    function check_if_all_parents_have_entries($resource_id)
    {
        $var = self::get_fields_from_tab_file($resource_id, array("taxonID", "parentNameUsageID"));
        $taxon_ids = array_keys($var['taxonID']);
        $parent_ids = array_keys($var['parentNameUsageID']);
        // print_r($taxon_ids); print_r($parent_ids);
        $undefined = array();
        foreach($parent_ids as $parent_id)
        {
            if(!in_array($parent_id, $taxon_ids))
            {
                echo "\n not defined parent [$parent_id]";
                $undefined[$parent_id] = '';
            }
            // else echo "\n defined OK parent [$parent_id]";
        }
        // print_r($undefined);
        echo "\n total undefined parent_id: " . count($undefined) . "\n";
    }
    
    function get_fields_from_tab_file($resource_id, $cols)
    {
        $url = CONTENT_RESOURCE_LOCAL_PATH . $resource_id . "/taxon.tab";
        if(!file_exists($url))
        {
            echo "\nFile does not exist: [$url]\n";
            return;
        }
        $i = 0;
        $var = array();
        foreach(new FileIterator($url) as $line_number => $temp)
        {
            $temp = explode("\t", $temp);
            $i++;
            if($i == 1) $fields = $temp;
            else
            {
                $rec = array();
                $k = 0;
                if(!$temp) continue;
                foreach($temp as $t)
                {
                    $rec[$fields[$k]] = $t;
                    $k++;
                }
                foreach($cols as $col) $var[$col][@$rec[$col]] = '';
            }
        }
        return $var;
    }
    //============================================================
}
?>
