<?php
include_once(dirname(__FILE__) . "/../config/environment.php");

class SplitEntryHandler
{

  function split_entry($args)
  {

    if(!$args['hierarchy_entry_id'] || !is_numeric($args['hierarchy_entry_id']) || !$args['bad_match_hierarchy_entry_id'] || !is_numeric($args['bad_match_hierarchy_entry_id']))
    {
        echo "\n\n\tsplit_entry.php [hierarchy_entry_id] [bad_match_hierarchy_entry_id] [confirmed]\n\n";
        exit;
    }

    $he = HierarchyEntry::find($args['hierarchy_entry_id']);
    $bad_he = HierarchyEntry::find($args['bad_match_hierarchy_entry_id']);

    if(!$he->id || !$bad_he->id)
    {
        echo "\n\nInvalid ID\n";
        exit;
    }

    if($he->taxon_concept_id != $bad_he->taxon_concept_id)
    {
        echo "\n\nThe bad match ID isn't from the same concept\n";
        exit;
    }

    if($args['confirmed'] == 'confirmed')
    {
        $user_id = 13;  # 13 is Patrick's user ID
        $update_caches = true;
        echo HierarchyEntry::split_from_concept_static($args['hierarchy_entry_id'], $update_caches)."\n";
        $GLOBALS['db_connection']->query("INSERT IGNORE INTO curated_hierarchy_entry_relationships VALUES (" .$args['hierarchy_entry_id'] . ", " . $args['bad_match_hierarchy_entry_id'] . ", $user_id, 0)");
    }else
    {
        echo "\n\n";
        echo "Removing:\n";
        print_r($he);
        echo "Name: ".$he->name->string."\n\n";
        echo "From:\n";
        print_r($he->taxon_concept);
        
        $descendant_objects = TaxonConcept::count_descendants_objects($he->taxon_concept_id);
        echo "\n\nDescendant Objects:  $descendant_objects\n\n";
    }

//OLD:

  function split_concept($args)
  {

    if(!$args['taxon_concept_id_from'] || !is_numeric($args['taxon_concept_id_from']) ||
       !$args['hierarchy_entry_id'] || !is_numeric($args['hierarchy_entry_id']) ||
       !$args['taxon_concept_id_to'] || !is_numeric($args['taxon_concept_id_to']) ||
       !$args['bad_match_hierarchy_entry_id'] || !is_numeric($args['bad_match_hierarchy_entry_id']))
    {
        echo "\n\n\tsplit_concept.php [taxon_concept_id_from] [hierarchy_entry_id] [taxon_concept_id_to] [bad_match_hierarchy_entry_id] [confirmed] [reindex?]\n\n";
        return;
    }

    $tc_from = TaxonConcept::find($args['taxon_concept_id_from']);
    $tc_to = TaxonConcept::find($args['taxon_concept_id_to']);
    $he = HierarchyEntry::find($args['hierarchy_entry_id']);
    $bad_he = HierarchyEntry::find($args['bad_match_hierarchy_entry_id']);
    if($args['reindex'] == 'true' || $args['reindex'] == 'reindex' || $args['reindex'] == 'update' || $args['reindex'] == 1) $args['reindex'] = true;
    else $args['reindex'] = false;

    if(!$he->id || !$tc_from->id || !$tc_to->id || !$bad_he->id)
    {
        echo "\n\nInvalid ID\n";
        return;
    }

    if($he->taxon_concept_id != $tc_from->id)
    {
        echo "\n\nThis entry is not in the source concept\n";
        return;
    }
    if($he->taxon_concept_id != $bad_he->taxon_concept_id)
    {
        echo "\n\nThe bad match ID isn't from the same concept\n";
        return;
    }

    if($args['confirmed'] == 'confirmed' || $args['confirmed'] == 'force')
    {
        if($args['confirmed'] == 'force') $force_move_if_disallowed = true;
        else $force_move_if_disallowed = false;
        $update_caches = false;
        $user_id = 13;  # 13 is Patrick's user ID
        
        /* HierarchyEntry::move_to_concept_static(he_id, tc_id, force); */
        HierarchyEntry::move_to_concept_static($args['hierarchy_entry_id'], $args['taxon_concept_id_to'], $force_move_if_disallowed, $args['reindex']);
        $GLOBALS['db_connection']->query("INSERT IGNORE INTO curated_hierarchy_entry_relationships VALUES (" . $args['hierarchy_entry_id'] . ", " . $args['bad_match_hierarchy_entry_id'] . ", $user_id, 0)");
        echo "\nMoved " . $args['hierarchy_entry_id'] . " to " . $args['taxon_concept_id_to'] . "\n\n";
    }else
    {
        echo "\n\n";
        echo "Removing:\n";
        print_r($he);
        echo "Name: ".$he->name->string."\n\n";
        echo "From:\n";
        print_r($tc_from);
        echo "To:\n";
        print_r($tc_to);
        
        $descendant_objects = TaxonConcept::count_descendants_objects($tc_from->id);
        $descendants = TaxonConcept::count_descendants($tc_from->id);
        echo "\n\nTaxonConcept1: $tc_from->id\n";
        echo "Descendant Objects:  $descendant_objects\n";
        echo "Descendant Concepts: $descendants\n";
        
        $descendant_objects = TaxonConcept::count_descendants_objects($tc_to->id);
        $descendants = TaxonConcept::count_descendants($tc_to->id);
        echo "\n\nTaxonConcept1: $tc_to->id\n";
        echo "Descendant Objects:  $descendant_objects\n";
        echo "Descendant Concepts: $descendants\n";

        echo "\n\nDon't forget to solr_update_concept.php\n\n";
    }

  }

}

?>