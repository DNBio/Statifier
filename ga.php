<?php

/*** STATIFIER TEST FILE          ***/
/*** david.brett@ehess.fr         ***/
/*** web@ehess.fr                 ***/
/*** V.0.2 - March 2019           ***/
/*** COPYRIGHT EHESS/DNB          ***/

require_once "statifier.conf.php";
require_once "engine/functions.php";
require_once "engine/pdo-connector.php";

// GA exceptions : profiles to skip
$gaExceptions = array(
    '000001',
    '000002'
);

/*** 
 * Use this file to get all profiles from GA
 * Register all sites/views, 
 * fetch the metrics and record it
 *
 * NOTE : We use both v3 and v4 Google APIs...
*/

// If Piwic is enabled in configuration file
// Process the monitored website stats in Piwic
if ($enableGAnalytics === true)
{
    // Load the Google API PHP Client Library.
    require_once __DIR__ . '/vendor/autoload.php';
    
    /******************************************/
    /** IMPORT GOOGLE ANALYTICS PROFILES     **/
    /******************************************/
    // We have to use GA API v.3 here.
    // The URL column in db has a unique key setting,
    // query will fail silently (INSERT IGNORE) 
    // if a site is already recorded.
    $analytics = initializeAnalyticsv3($keyFilePath);
    $accounts = getAllAccountsInfo($analytics,$debug);
    saveGAprofiles($accounts,$debug);
    unset($analytics,$accounts);
    
    /******************************************/

    /******************************************/
    /** GET GOOGLE ANALYTICS PROFILES STATS  **/
    /******************************************/

    // Get the current week number and current year
    $ddate = date('Y-m-d', time());
    $date = new DateTime($ddate);
    $currentWeek = $date->format("W");
    $currentYear = $date->format("Y");
    unset($ddate,$date);

    // Get all GA sites IDs
    $allGaSites = null;
    $allGaSites = selectAllGASites($debug);
    
    if (isset($allGaSites))
    {
        foreach($allGaSites as $k => $gaViewId)
        {
            // Evaluate GA profiles exceptions (if any),
            // to skip specific profiles/views.
            if (in_array($gaViewId['ga_profile_id'], $gaExceptions)) 
            { 
                if ($debug === true)
                {
                    $log = 'GA profile id : ' . 
                        $gaViewId['ga_profile_id'] . 
                        ' is flagged as an exception, skipping stats.';
                    writeLog($log);
                    unset($log);
                }
            } 
            else
            { 
                // Reset the year to start with :
                $y = $startYear;

                // Process all years one by one
                // (Don't try to fetch future stats...)
                $nextYear = (intval($currentYear) + 1);
                while ($startYear < $nextYear)
                {
                    if ($debug === true)
                    {
                        $log = 'GA profile id : ' . 
                            $gaViewId['ga_profile_id'] . 
                            ' / Fetching year ' . 
                            $y . '.';
                        writeLog($log);
                        unset($log);
                    }
                    // Don't take the current week into account.
                    // (Don't try to fetch tomorrow's stats...)
                    // get all weeks one by one, from the first :
                    $w = 1;
                    while ($w < 53)
                    {
                        if ($y === intval($currentYear) && 
                            $w >= intval($currentWeek)
                        ) 
                        {
                            if ($debug === true)
                            {
                                $log = 'We reached current week (' . 
                                    $currentWeek . '), stopping.';
                                writeLog($log);
                                unset($log);
                            }
                            break 2;
                        } else {
                            if ($debug === true)
                            {
                                $log = 'GA profile id : ' . 
                                    $gaViewId['ga_profile_id'] . 
                                    ' / fetching week n°' . 
                                    $w . '(' . $y . ').';
                                writeLog($log);
                                unset($log);
                            }

                            // GA API expects first day/last day of the week
                            $weekRange = getStartAndEndDate($w, $y);

                            // HERE : loop over GA METRICS

                            // Get the stats recorded in the database
                            $record = null;

                            // Build new initializer
                            $analytics = initializeAnalyticsv4($keyFilePath);

                            // HERE : fetch stat
                            $record = statFromGA(
                                $analytics,
                                $gaViewId['ga_profile_id'],
                                $weekRange,
                                $debug
                            ); 

                            // Break if function statFromGA fails to return.
                            if (!isset($record))
                            {
                                if ($debug === true)
                                {
                                    $log = 'Function statFromGA failed to return.';
                                    writeLog($log);
                                    unset($log);
                                }
                                break 2;
                            } else {
                                $data = printResults($record);
                                // Break if function printResults fails to return.
                                if (!isset($data))
                                {
                                    if ($debug === true)
                                    {
                                        $log = 'Function printResults failed to return.';
                                        writeLog($log);
                                        unset($log);
                                    }
                                    break 2;
                                } else {
                                    // Save stat
                                    recordGAstat(
                                        $gaViewId,
                                        $weekRange,
                                        $data,
                                        $debug
                                    );
                                    // reset variables
                                    unset($data);
                                }
                                unset($record);
                            }
                            unset($analytics);
                        }
                        // Avoid Google Analytics Quota restrictions
                        sleep(2);

                        // reset and go on...
                        unset($weekRange);
                        $w++;
                    }
                    $y++;
                }
                unset($y);
            }    
        }
    } else {
        // When $allPiwicSites remains null...
        if ($debug === true)
        {
            $log = 'Found no GA profile(s) to process in the database.';
            writeLog($log);
            unset($log);
        }
    }
    
    // Clean it up...
    unset(
        $currentWeek,
        $currentYear,
        $y,
        $w
    );
    /****************************/
}
