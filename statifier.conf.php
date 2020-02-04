<?php

/*** STATIFIER CONFIGURATION FILE ***/
/*** david.brett@ehess.fr         ***/
/*** web@ehess.fr                 ***/
/*** V.0.2 - March 2019           ***/
/*** COPYRIGHT EHESS/DNB          ***/

// Display all PHP errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Let scripts run for hours if needed
ini_set('max_execution_time', 0);

// We need accurate time display
date_default_timezone_set('Europe/Paris');

// Debug mode : enable db logging
// can be true or false
$debug = true;

// Server install path
$installPath = '';

// Record stats from the begining of this year :
$startYear = 2019;

/*** DATABASE CREDENTIALS ***/
$database = array (
    'driver' => 'mysql',
    'database' => '',
    'username' => '',
    'password' => '',
    'host' => 'localhost',
    'port' => '3306'
);

/***       PIWIC       ***/
// Enable fetching stats from the Piwic platform
// Can be true or false
$enablePiwic = true;
/*** PIWIC CREDENTIALS ***/
// do not forget the trailing slash at the end of the URL
// eg : $piwicUrl = 'http://www.piwic.com/';
$piwicUrl = '';
$piwicToken = '';

/***      PIWIC CSV METRICS       ***/
// The metrics we want from piwic sites for CSV export
// "column" is the name of the column in the database
// "type" is the mathematical function that will be used to gather a specific metric. 
$PiwicMetricsWanted = array(
    'visits' => array(
        'column' => 'nb_visits',
        'type' => 'SUM'
    ),
    'pages' => array(
        'column' => 'nb_actions',
        'type' => 'SUM'
    ),
    'bounceRate' => array(
        'column' => 'bounce_rate',
        'type' => 'AVG'
    ),
    'pagesPerVisit' => array(
        'column' => 'nb_actions_per_visit',
        'type' => 'AVG'
    ),
    'averageTime' => array(
        'column' => 'avg_time_on_site',
        'type' => 'AVG'
    )
);

/***      GOOGLE ANALYTICS        ***/
// Enable fetching stats from the Google Analytics platform
// Can be true or false
$enableGAnalytics = false;
/*** GOOGLE ANALYTICS CREDENTIALS ***/
$keyFilePath = ''; 


// GA exceptions : profiles to skip
// Example : Insufficient rights on 123456789 to process data.
$gaExceptions = array(
    '123456789'
);


/*
$gaMetrics = array(
    array(
        'expression' => 'ga:users',
        'alias' => 'Users'
    ),
    array(
        'expression' => 'ga:sessions',
        'alias' => 'Sessions'
    ),
    array(
        'expression' => 'ga:pageviews',
        'alias' => 'Pageviews'
    ),
    array(
        'expression' => 'ga:bounceRate',
        'alias' => 'Bounce Rate'
    ),
    array(
        'expression' => 'ga:avgSessionDuration',
        'alias' => 'Session Duration'
    )
);
*/

/***      GA CSV METRICS       ***/
// The metrics we want from GA sites for CSV export
// "column" is the name of the column in the database
// "type" is the mathematical function that will be used to gather a specific metric. 
$gaMetricsWanted = array(
    'users' => array(
        'column' => 'nb_users',
        'type' => 'SUM'
    ),
    'visits' => array(
        'column' => 'nb_sessions',
        'type' => 'SUM'
    ),
    'pages' => array(
        'column' => 'nb_pageviews',
        'type' => 'SUM'
    ),
    'bounceRate' => array(
        'column' => 'bounceRate',
        'type' => 'AVG'
    ),
    'averageTime' => array(
        'column' => 'avgSessionDuration',
        'type' => 'AVG'
    )
);
