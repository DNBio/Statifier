<?php

/*** STATIFIER CUSTOM CSV EXPORTER         ***/
/*** david.brett@ehess.fr                  ***/
/*** web@ehess.fr                          ***/
/*** V.0.2 - March 2019                    ***/
/*** COPYRIGHT EHESS/DNB                   ***/

/* TEMPORARY FILE */
// Use it to manually export stats in a file.

require_once "statifier.conf.php";
require_once "engine/functions.php";
require_once "engine/pdo-connector.php";

$years = array(2015,2016,2017,2018,2019);
$pwpath = 'stats.txt';
$gapath = 'pwstats.txt';

// Here we call Google analytics stats
// We could call piwic functions if we needed piwic stats
// Attention : would crush the previous file !
$sites = fetchPWSites($debug);
$data = fetchPWData($debug,$sites,$PiwicMetricsWanted,$years);

$csv = formatPWData($data,$debug);
outputCSV($csv,$pwpath,$debug);

unset($sites,$data,$csv);

$sites = fetchGASites($debug);
$data = fetchGAData($debug,$sites,$gaMetricsWanted,$years);

$csv = formatGAData($data,$debug);
outputCSV($csv,$gapath,$debug);


?>