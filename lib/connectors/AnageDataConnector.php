<?php
namespace php_active_record;

class AnageDataConnector
{
    const DUMP_URL = "/Users/pleary/Downloads/datasets/anage_data.txt";

    public function __construct($resource_id)
    {
        $this->resource_id = $resource_id;
        $this->taxon_ids = array();
    }

    public function build_archive()
    {
        $this->path_to_archive_directory = CONTENT_RESOURCE_LOCAL_PATH . "/$this->resource_id/";
        $this->archive_builder = new \eol_schema\ContentArchiveBuilder(array('directory_path' => $this->path_to_archive_directory));

        $this->column_labels = array();
        $this->column_indices = array();
        foreach(new FileIterator(self::DUMP_URL) as $line_number => $line)
        {
            if($line_number % 1000 == 0) echo "$line_number :: ". time_elapsed() ." :: ". memory_get_usage() ."\n";
            $line_data = explode("\t", $line);
            if($line_number == 0)
            {
                $this->column_labels = $line_data;
                foreach($this->column_labels as $k => $v) $this->column_indices[$v] = $k;
                continue;
            }
            $this->process_line_data($line_data);
        }
        $this->archive_builder->finalize(true);
    }

    public function process_line_data($line_data)
    {
        $taxon = $this->add_taxon($line_data);
        $occurrence = $this->add_occurrence($line_data, $taxon);
        $this->add_numeric_data($line_data, $taxon, $occurrence);
    }

    private function add_taxon($line_data)
    {
        $t = new \eol_schema\Taxon();
        $t->scientificName = trim($line_data[$this->column_indices['Genus']] ." ". $line_data[$this->column_indices['Species']]);
        $t->family = $line_data[$this->column_indices['Family']];
        $t->order = $line_data[$this->column_indices['Order']];
        $t->class = $line_data[$this->column_indices['Class']];
        $t->phylum = $line_data[$this->column_indices['Phylum']];
        $t->kingdom = $line_data[$this->column_indices['Kingdom']];
        $t->taxonID = md5($t->scientificName . $t->family . $t->order . $t->class . $t->phylum . $t->kingdom);
        $t->source = "http://genomics.senescence.info/species/entry.php?species=". str_replace(" ", "_", $t->scientificName);
        $this->archive_builder->write_object_to_file($t);

        if($v = $line_data[$this->column_indices['Common name']])
        {
            $vernacular = new \eol_schema\VernacularName();
            $vernacular->taxonID = $t->taxonID;
            $vernacular->vernacularName = $v;
            $vernacular->language = 'en';
            $this->archive_builder->write_object_to_file($vernacular);
        }
        return $t;
    }

    private function add_occurrence($line_data, $taxon)
    {
        $o = new \eol_schema\Occurrence();
        $o->occurrenceID = md5($taxon->taxonID . "occurrence");
        $o->taxonID = $taxon->taxonID;
        if($establishment_means = $line_data[$this->column_indices['Specimen origin']]) $o->establishmentMeans = "http://genomics.senescence.info/terms/". $establishment_means;
        $this->archive_builder->write_object_to_file($o);

        if($sample_size = $line_data[$this->column_indices['Sample size']])
        {
            $m = new \eol_schema\MeasurementOrFact();
            $m->occurrenceID = $o->occurrenceID;
            $m->measurementType = "http://genomics.senescence.info/terms/sample_size";
            $m->measurementValue = "http://genomics.senescence.info/terms/". SparqlClient::to_underscore($sample_size);
            $this->archive_builder->write_object_to_file($m);
        }
        if($data_quality = $line_data[$this->column_indices['Data quality']])
        {
            $m = new \eol_schema\MeasurementOrFact();
            $m->occurrenceID = $o->occurrenceID;
            $m->measurementType = "http://genomics.senescence.info/terms/data_quality";
            $m->measurementValue = "http://genomics.senescence.info/terms/". SparqlClient::to_underscore($data_quality);
            $this->archive_builder->write_object_to_file($m);
        }
        return $o;
    }

    private function add_numeric_data($line_data, $taxon, $occurrence)
    {
        $numeric_types = array('Female maturity (days)', 'Male maturity (days)', 'Gestation/Incubation (days)',
            'Weaning (days)', 'Litter/Clutch size', 'Litters/Clutches per year', 'Inter-litter/Interbirth interval',
            'Birth weight (g)', 'Weaning weight (g)', 'Adult weight (g)', 'Growth rate (1/days)', 'Maximum longevity (yrs)',
            'IMR (per yr)', 'MRDT (yrs)', 'Metabolic rate (W)', 'Body mass (g)', 'Temperature (K)');
        foreach($numeric_types as $label)
        {
            if($v = trim($line_data[$this->column_indices[$label]]))
            {
                $this_label = $label;
                $unit_of_measure = null;
                if(preg_match("/^(.*) \((.+)\)$/", $label, $arr))
                {
                    $this_label = $arr[1];
                    $unit_of_measure = "http://genomics.senescence.info/terms/". SparqlClient::to_underscore(str_replace("/", "_", $arr[2]));
                }
                $m = new \eol_schema\MeasurementOrFact();
                $m->occurrenceID = $occurrence->occurrenceID;
                $m->measurementOfTaxon = 'true';
                $m->measurementType = "http://genomics.senescence.info/terms/". SparqlClient::to_underscore($this_label);
                $m->measurementValue = $v;
                $m->measurementUnit = $unit_of_measure;
                $m->source = "http://genomics.senescence.info/species/entry.php?species=". str_replace(" ", "_", $taxon->scientificName);
                $this->archive_builder->write_object_to_file($m);
            }
        }
    }
}

?>