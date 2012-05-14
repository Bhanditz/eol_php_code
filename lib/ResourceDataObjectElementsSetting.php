<?php
namespace php_active_record;
class ResourceDataObjectElementsSetting
{
    public function __construct($resource_id, $xml_path, $data_object_type, $rating)
    {
        $this->resource_id = $resource_id;
        $this->xml_path = $xml_path;
        $this->data_object_type = $data_object_type;
        $this->rating = $rating;
    }

    public function set_data_object_rating_on_xml_document()
    {
        $xml_string = self::load_xml_string();
        $xml = simplexml_load_string($xml_string);
        foreach($xml->taxon as $taxon)
        {
            foreach($taxon->dataObject as $dataObject)
            {
                $dataObject_dc = $dataObject->children("http://purl.org/dc/elements/1.1/");
                if(@$dataObject->dataType == $this->data_object_type)
                {
                    print "\n" . $dataObject_dc->identifier;
                    if ($dataObject->additionalInformation->rating) $dataObject->additionalInformation->rating = $this->rating;
                    else
                    {
                        $dataObject->addChild("additionalInformation", "");
                        $dataObject->additionalInformation->addChild("rating", $this->rating);
                    }
                }

            }
        }
        return $xml->asXML();
    }

    function load_xml_string()
    {
        print "\nPlease wait, downloading resource document...\n";
        if(preg_match("/^(.*)\.(gz|gzip)$/", $this->xml_path, $arr))
        {
            $path_parts = pathinfo($this->xml_path);
            $filename = $path_parts['basename'];
            $this->TEMP_FILE_PATH = create_temp_dir() . "/";
            print "\n " . $this->TEMP_FILE_PATH;
            if($file_contents = Functions::get_remote_file($this->xml_path, DOWNLOAD_WAIT_TIME, 999999))
            {
                $temp_file_path = $this->TEMP_FILE_PATH . "/" . $filename;
                $TMP = fopen($temp_file_path, "w");
                fwrite($TMP, $file_contents);
                fclose($TMP);
                shell_exec("gunzip -f $temp_file_path");
                $this->xml_path = $this->TEMP_FILE_PATH . str_ireplace(".gz", "", $filename);
                print "\n -- " . $this->xml_path;
            }
            else exit("\n\n Connector terminated. Remote files are not ready.\n\n");
            // remove tmp dir
            // if($this->TEMP_FILE_PATH) shell_exec("rm -fr $this->TEMP_FILE_PATH");
        }
        return Functions::get_remote_file($this->xml_path);
    }

    public function replace_data_object_element_value($field, $old_value, $new_value, $xml_string, $compare = true)
    {
        /* e.g. 
            replace_data_object_element_value("mimeType", "audio/wav", "audio/x-wav", $xml);
            replace_data_object_element_value("dcterms:modified", "", "07/13/1972", $xml, false);
        */
        $xml = simplexml_load_string($xml_string);
        $i = 0;
        foreach($xml->taxon as $taxon)
        {
            $i++; print "$i ";
            foreach($taxon->dataObject as $dataObject)
            {
                if(substr($field,0,3) == "dc:")             $do = $dataObject->children("http://purl.org/dc/elements/1.1/");
                elseif(substr($field,0,8) == "dcterms:")    $do = $dataObject->children("http://purl.org/dc/terms/");
                else                                        $do = $dataObject;

                if(substr($field,0,3) == "dc:" || substr($field,0,8) == "dcterms:")
                {
                    $field = str_ireplace(array("dc:", "dcterms:"), "", $field);
                }
                
                if($compare) 
                {
                    if(@$do->$field == $old_value) $do->$field = $new_value;
                }
                else $do->$field = $new_value;
            }
        }
        return $xml->asXML();
    }

    public function save_resource_document($xml)
    {
        $resource_path = CONTENT_RESOURCE_LOCAL_PATH . $this->resource_id . ".xml";
        $OUT = fopen($resource_path, "w");
        fwrite($OUT, $xml);
        fclose($OUT);
    }

}
?>