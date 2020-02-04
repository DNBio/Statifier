<?php

/*** STATIFIER TEST FILE          ***/
/*** david.brett@ehess.fr         ***/
/*** web@ehess.fr                 ***/
/*** V.0.2 - March 2019           ***/
/*** COPYRIGHT EHESS/DNB          ***/

require_once "statifier.conf.php";
require_once "engine/functions.php";
require_once "engine/pdo-connector.php";

// Start with fresh logs and piwicWeekly tables.
// resetDB($debug);

/*** 
 * Use this file to get all sites from PIWIC
 * Register all in db, 
 * fetch the metrics and record it.
*/

// If Piwic is enabled in configuration file
// Process the monitored website stats in Piwic
if ($enablePiwic === true)
{
    /****************************/
    /** IMPORT ALL PIWIC SITES **/
    /****************************/
    // The API method to use
    $method = "SitesManager.getAllSites";
    // We don't need any arguments with this method
    $args = '';
    
    // Fetch all registered sites in PIWIC database
    $allsites = callPiwik(
        $debug,
        $piwicUrl,
        $piwicToken,
        $method,
        $args
    );

    // Import the sites in our local database.
    // The URL column in db has a unique key setting,
    // query will fail silently (INSERT IGNORE) 
    // if a site is already recorded.
    importFromPiwic($debug,$allsites);

    // Clean it up...
    unset($method,$args,$allsites);
    /****************************/

    /****************************/
    /** GET PIWIC SITES STATS  **/
    /** From piwic archives    **/
    /****************************/
    // The API method to use
    $method = "VisitsSummary.get";

    // Get the current week number and current year
    $ddate = date('Y-m-d', time());
    $date = new DateTime($ddate);
    $currentWeek = $date->format("W");
    $currentYear = $date->format("Y");
    unset($ddate,$date);

    // Get all piwic sites IDs
    $allPiwicSites = null;
    $allPiwicSites = selectAllPiwicSites($debug);
    
    if (isset($allPiwicSites))
    {
        foreach($allPiwicSites as $k => $piwicSiteId)
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
                    $log = 'Piwic site id : ' . 
                        $piwicSiteId . 
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
                            $log = 'Piwic site id : ' . 
                                $piwicSiteId . 
                                ' / fetching week n°' . 
                                $w . '(' . $y . ').';
                            writeLog($log);
                            unset($log);
                        }

                        // PIWIC api expects first day/last day of the week
                        $weekRange = getStartAndEndDate($w, $y);

                        // Build the arguments to call the API
                        $args = "&idSite=" . 
                            $piwicSiteId . 
                            '&period=range&date=' . 
                            $weekRange['week_start'] . 
                            ',' . 
                            $weekRange['week_end'];

                        // Get the stats recorded in the database
                        $record = null;
                        $record = recordStatsFromPiwic(
                            $debug,
                            $piwicUrl,
                            $piwicToken,
                            $piwicSiteId,
                            $method,
                            $args,
                            $weekRange['week_start'],
                            $weekRange['week_end']
                        );

                        // Break if function recordStatsFromPiwic fails to return.
                        if (!isset($record))
                        {
                            if ($debug === true)
                            {
                                $log = 'Function recordStatsFromPiwic failed to return.';
                                writeLog($log);
                                unset($log);
                            }
                            break 2;
                        } else {
                            unset($record);
                        }
                    }
                    // reset and go on...
                    unset($weekRange,$args);
                    $w++;
                }
                $y++;
            }
            unset($y);
        }
    } else {
        // When $allPiwicSites remains null...
        if ($debug === true)
        {
            $log = 'Found no Piwic site(s) to process in the database.';
            writeLog($log);
            unset($log);
        }
    }
    
    // Clean it up...
    unset(
        $method,
        $currentWeek,
        $currentYear,
        $allPiwicSites,
        $y,
        $w
    );
    /****************************/
}
