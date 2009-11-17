#!/usr/local/bin/php
<?php
//#!/usr/local/bin/php
//
//exit;

define("ENVIRONMENT", "slave_32");
define("MYSQL_DEBUG", true);
define("DEBUG", true);
include_once(dirname(__FILE__) . "/../../config/start.php");
$mysqli =& $GLOBALS['mysqli_connection'];

set_time_limit(0);

$month = get_val_var("month");
$year = get_val_var("year");

//$month = "04"; $year = "2009";

$month = GetNumMonthAsString($month, $year);

//$year_month = "2009_04";

$year_month = $year . "_" . $month;

$google_analytics_page_statistics = "google_analytics_page_statistics_" . $year_month;

//=================================================================
$mysqli2 = load_mysql_environment('eol_statistics');
/* use to initialize 3 tables - run once */ initialize_tables(); //exit;

//=================================================================
//query 1
$query = "SELECT tcn.taxon_concept_id, n.string FROM taxon_concept_names tcn JOIN names n ON (tcn.name_id=n.id) JOIN taxon_concepts tc ON (tcn.taxon_concept_id=tc.id) WHERE tcn.vern=0 AND tcn.preferred=1 AND tc.supercedure_id=0 AND tc.published=1 GROUP BY tcn.taxon_concept_id 
ORDER BY tcn.source_hierarchy_entry_id DESC "; 
//$query .= " limit 1 "; //debug ??? maybe can't be limited, even on when debugging
$result = $mysqli->query($query);    
$fields=array();
$fields[0]="taxon_concept_id";
$fields[1]="string";
$temp = save_to_txt($result,"hierarchies_names",$fields,$year_month,chr(9),0,"txt");
//=================================================================
//query 2

/*
$query="Select agents.id From agents Inner Join content_partners ON agents.id = content_partners.agent_id 
Where content_partners.eol_notified_of_acceptance Is Not Null
Order By agents.full_name Asc "; */ 
$query="Select distinct agents.id From agents
Inner Join agents_resources ON agents.id = agents_resources.agent_id
Inner Join harvest_events ON agents_resources.resource_id = harvest_events.resource_id
Where harvest_events.published_at is not null order by agents.full_name "; 
//this query now only gets partners with a published data on the time the report was run.

//$query .= " limit 1 "; //debug
$result = $mysqli->query($query);    

while($result && $row=$result->fetch_assoc())	
{
    /* legacy version
    $query = "SELECT DISTINCT a.full_name, tcn.taxon_concept_id 
    FROM agents a
    JOIN agents_resources ar ON (a.id=ar.agent_id)
    JOIN harvest_events he ON (ar.resource_id=he.resource_id)
    JOIN harvest_events_taxa het ON (he.id=het.harvest_event_id)
    JOIN taxa t ON (het.taxon_id=t.id)
    JOIN taxon_concept_names tcn ON (t.name_id=tcn.name_id)
    WHERE a.id = $row[id] ";
    */
    /* new Sep21, as suggested by PL */
    $query = "SELECT DISTINCT a.full_name, he.taxon_concept_id 
    FROM agents a
    JOIN agents_resources ar ON (a.id=ar.agent_id)
    JOIN harvest_events hev ON (ar.resource_id=hev.resource_id)
    JOIN harvest_events_taxa het ON (hev.id=het.harvest_event_id)
    JOIN taxa t ON (het.taxon_id=t.id)
    join hierarchy_entries he on t.hierarchy_entry_id = he.id
    join taxon_concepts tc on he.taxon_concept_id = tc.id
    WHERE a.id = $row[id] and tc.published = 1 and tc.supercedure_id = 0 ";    
    
    //$query .= " limit 1 "; //debug 

    $result2 = $mysqli->query($query);    
    $fields=array();
    $fields[0]="full_name";
    $fields[1]="taxon_concept_id";
    $temp = save_to_txt($result2,"agents_hierarchies",$fields,$year_month,chr(9),0,"txt");
}

    
/*
WHERE a.full_name IN (
	'AmphibiaWeb', 'BioLib.cz', 'Biolib.de', 'Biopix', 'Catalogue of Life', 'FishBase',
	'Global Biodiversity Information Facility (GBIF)', 'IUCN', 'Micro*scope',
	'Solanaceae Source', 'Tree of Life web project', 'uBio','AntWeb','ARKive','Animal Diversity Web' ";
if($year >= 2009 && intval($month) > 4) $query .= " , 'The Nearctic Spider Database' ";    
*/    




//=================================================================
//query 3
/*legacy
$query = "SELECT DISTINCT 'BHL' full_name, tcn.taxon_concept_id FROM page_names pn JOIN taxon_concept_names tcn ON (pn.name_id=tcn.name_id)";
*/
//either of these 2 queries will work
//$query = "SELECT DISTINCT 'BHL' full_name, tcn.taxon_concept_id From page_names AS pn Inner Join taxon_concept_names AS tcn ON (pn.name_id = tcn.name_id) Inner Join taxon_concepts ON tcn.taxon_concept_id = taxon_concepts.id WHERE taxon_concepts.published = 1 and taxon_concepts.supercedure_id = 0 and taxon_concepts.vetted_id <> 4";
$query = "select distinct 'BHL' full_name, tc.id taxon_concept_id from taxon_concepts tc 
STRAIGHT_JOIN taxon_concept_names tcn on (tc.id=tcn.taxon_concept_id) 
STRAIGHT_JOIN page_names pn on (tcn.name_id=pn.name_id) where tc.supercedure_id=0 and tc.published=1 and (tc.vetted_id=5 OR tc.vetted_id=0) ";
//$query .= " LIMIT 1 "; //debug
$result = $mysqli->query($query);    
$fields=array();
$fields[0]="full_name";
$fields[1]="taxon_concept_id";
$temp = save_to_txt($result, "agents_hierarchies_bhl",$fields,$year_month,chr(9),0,"txt");

//==============================================================================================
//start COL 2009

/* working but don't go through taxon_concept_names
$query = "select distinct 'COL 2009' full_name, tc.id taxon_concept_id from 
taxon_concepts tc STRAIGHT_JOIN taxon_concept_names tcn on (tc.id=tcn.taxon_concept_id) 
where tc.supercedure_id=0 and tc.published=1 and (tc.vetted_id=5 OR tc.vetted_id=0) 
and tcn.name_id in (Select distinct hierarchy_entries.name_id From hierarchy_entries where hierarchy_entries.hierarchy_id = ".Hierarchy::col_2009().")"; */

$query = "select distinct 'COL 2009' full_name, tc.id taxon_concept_id from 
taxon_concepts tc STRAIGHT_JOIN hierarchy_entries tcn on (tc.id=tcn.taxon_concept_id) 
where tc.supercedure_id=0 and tc.published=1 and (tc.vetted_id=5 OR tc.vetted_id=0) 
and tcn.name_id in (Select distinct hierarchy_entries.name_id From hierarchy_entries where hierarchy_entries.hierarchy_id = ".Hierarchy::col_2009().")";

//$query .= " LIMIT 1 "; //debug
$result = $mysqli->query($query);    
$fields=array();
$fields[0]="full_name";
$fields[1]="taxon_concept_id";
$temp = save_to_txt($result, "agents_hierarchies_col",$fields,$year_month,chr(9),0,"txt");

//end COL 2009
//==============================================================================================


//=================================================================
//query 4,5
$update = $mysqli2->query("TRUNCATE TABLE eol_statistics.hierarchies_names_" . $year_month . "");        
$update = $mysqli2->query("TRUNCATE TABLE eol_statistics.agents_hierarchies_" . $year_month . "");        
//query 6,7,8
$update = $mysqli2->query("LOAD DATA LOCAL INFILE 'data/" . $year_month . "/temp/hierarchies_names.txt' INTO TABLE eol_statistics.hierarchies_names_" . $year_month . "");        
$update = $mysqli2->query("LOAD DATA LOCAL INFILE 'data/" . $year_month . "/temp/agents_hierarchies.txt'     INTO TABLE eol_statistics.agents_hierarchies_" . $year_month . "");        
$update = $mysqli2->query("LOAD DATA LOCAL INFILE 'data/" . $year_month . "/temp/agents_hierarchies_bhl.txt' INTO TABLE eol_statistics.agents_hierarchies_" . $year_month . "");        
$update = $mysqli2->query("LOAD DATA LOCAL INFILE 'data/" . $year_month . "/temp/agents_hierarchies_col.txt' INTO TABLE eol_statistics.agents_hierarchies_" . $year_month . "");        
//=================================================================
//start query9,10,11,12 => start3.php
//start query11 - site_statistics



//print"<hr>xxx [$temp]<hr>"; exit($temp);

function save_to_txt($result,$filename,$fields,$year_month,$field_separator,$with_col_header,$file_extension)
{
	$str="";    
    if($with_col_header)
    {
		for ($i = 0; $i < count($fields); $i++) 		
		{
			$field = $fields[$i];
			$str .= $field . $field_separator;    //chr(9) is tab
		}
		$str .= "\n";    
    }
    
	while($result && $row=$result->fetch_assoc())	
	{
		for ($i = 0; $i < count($fields); $i++) 		
		{
			$field = $fields[$i];
			$str .= $row["$field"] . $field_separator;    //chr(9) is tab
		}
		$str .= "\n";
	}
    if($file_extension == "txt")$temp = "temp/";
    else                        $temp = "";
    
	$filename = "data/" . $year_month . "/" . $temp . "$filename" . "." . $file_extension;
	if($fp = fopen($filename,"a")){fwrite($fp,$str);fclose($fp);}		
    
    //print "<br>[$i]<br>";
    
    return "";
    
}//function save_to_txt($result,$filename,$fields,$year_month,$field_separator,$with_col_header,$file_extension)



function initialize_tables()
{
	global $mysqli2;
    global $year_month;

	$query="DROP TABLE IF EXISTS `eol_statistics`.`agents_hierarchies_" . $year_month . "`;";   $update = $mysqli2->query($query);    		
	$query="DROP TABLE IF EXISTS `eol_statistics`.`hierarchies_names_" . $year_month . "`;";    $update = $mysqli2->query($query);

	$query="CREATE TABLE  `eol_statistics`.`agents_hierarchies_" . $year_month . "` ( `agentName` varchar(64) NOT NULL, `hierarchiesID` int(10) unsigned NOT NULL, PRIMARY KEY  USING BTREE (`agentName`,`hierarchiesID`), KEY `hierarchiesID` (`hierarchiesID`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8"; $update = $mysqli2->query($query);    
	$query="CREATE TABLE  `eol_statistics`.`hierarchies_names_" . $year_month . "` ( `hierarchiesID` int(10) unsigned NOT NULL, `scientificName` varchar(255) default NULL, `commonNameEN` varchar(255) default NULL, PRIMARY KEY  (`hierarchiesID`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8"; $update = $mysqli2->query($query);
    
}//function initialize_tables()

function get_val_var($v)
{
    if     (isset($_GET["$v"])){$var=$_GET["$v"];}
    elseif (isset($_POST["$v"])){$var=$_POST["$v"];}
    else   return NULL;                            
    return $var;    
}
function GetNumMonthAsString($m,$y)
{
    $timestamp = mktime(0, 0, 0, $m, 1, $y);    
    return date("m", $timestamp);
}


/*
//$query1 .= " INTO OUTFILE 'C:/webroot/eol_php_code/applications/google_stats/data/2009_07/eli.txt' FIELDS TERMINATED BY '\t' ";
*/

?>