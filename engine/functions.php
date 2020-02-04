<?php

/*** STATIFIER MAIN FUNCTIONS FILE ***/
/*** david.brett@ehess.fr          ***/
/*** web@ehess.fr                  ***/
/*** V.0.2 - March 2019            ***/
/*** COPYRIGHT EHESS/DNB           ***/

/** 
 * If the debug function is set to true in the configuration file,
 * write log messages into the database (and in the terminal).
 *
 * @param string $log the message to write in the logs.
 *
 * @return nothing.
 */
function writeLog($log) 
{
    global $dbh;
    
    $logVal = filter_var(
        $log, 
        FILTER_SANITIZE_STRING
    );
    $writeLog = $dbh->prepare(
        "INSERT INTO `logs`(`event`) 
        VALUES (:logMessage);"
    );
    $writeLog -> bindParam(':logMessage', $logVal, PDO::PARAM_STR);
    $logMessage = filter_var($log, FILTER_SANITIZE_STRING);
    $writeLog->execute();
    $writeLog->closeCursor();
    print $log . "\n";
    unset($writeLog,$logVal);
}

/** 
 * Truncate existing stats and logs in database.
 * The sites tables is preserved.
 *
 * @param bool $debug true/false for db logging.
 *
 * @return nothing.
 */
function resetDB($debug) 
{
    global $dbh;
    $q = $dbh->prepare(
        "TRUNCATE logs,piwicWeekly;"
    );
    $q->execute();
    $q->closeCursor();
    unset($q);
    if ($debug === true)
    {
        $log = 'Tables logs and piwicWeekly truncated.';
        writeLog($log);
        unset($log);
    }
}

/** 
 * Select week range from first day to last, on a week number/year basis. 
 * Borrowed from https://stackoverflow.com/a/20622278
 *
 * @param int $week the week number.
 * @param int $year the year we are looking for.
 *
 * @return array.
 */
function getStartAndEndDate($week, $year) {
  $dto = new DateTime();
  $ret['week_start'] = $dto->setISODate($year, $week)->format('Y-m-d');
  $ret['week_end'] = $dto->modify('+6 days')->format('Y-m-d');
  return $ret;
}

/** 
 * Build the Piwic URL, call the API, 
 * decode JSON and return the data.
 *
 * @param bool $debug true/false for db logging.
 * @param string $piwicURL the piwic server URL.
 * @param string $piwicToken the piwic token set in the conf file.
 * @param string $method the piwic method we want to use.*
 * @param string $args the piwic method arguments (if any).
 *
 * @return object.
 */
function callPiwik(
    $debug,
    $piwicUrl,
    $piwicToken,
    $method,
    $args
)
{
    // Tell Piwic we want to use the API
    $api = '?module=API';
    
    // Use the appropriate token
    $useToken = '&token_auth=' . $piwicToken;
    
    // Let's avoid SQL injections here
    $methodVal = filter_var(
        $method, 
        FILTER_SANITIZE_STRING
    );
    
    // Let's get the answer in a JSON format
    $answerFormat = '&format=json';
    
    // Build the full URL
    $fullUrl = $piwicUrl . 
        $api . 
        '&method=' . $methodVal . 
        $args .
        $answerFormat . 
        $useToken;

    //  Initiate curl
    $ch = curl_init();
    // Disable SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Will return the response, if false it print the response
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Set the url
    curl_setopt($ch, CURLOPT_URL,$fullUrl);
    // Execute
    $jsonData = curl_exec($ch);
    // Closing
    curl_close($ch);

    // Return the data
    if ($jsonData != false)
    {
        // Convert JSON to PHP object
        $data = json_decode($jsonData);
        return $data;
    } else {
        if ($debug === true)
        {
            $log = 'Function CallPiwic did not return any data.';
            writeLog($log);
            unset($log);
        }
        
        // Return empty data in case of error
        $empty = (object) [
            'nb_visits' => 0,
            'nb_actions' => 0,
            'nb_visits_converted' => 0,
            'bounce_count' => 0,
            'sum_visit_length' => 0,
            'max_actions' => 0,
            'bounce_rate' => 0,
            'nb_actions_per_visit' => 0,
            'avg_time_on_site' => 0
        ];
        
        print_r($empty);
        return $empty;
        // You may not die yet !
        // die();
    }
}

/** 
 * Import PIWIC sites into local database, 
 * Function usually only used once, on database initialization.
 *
 * @param bool $debug true/false for db logging.
 * @param object $data the object containing all the sites data.
 *
 * @return nothing.
 */
function importFromPiwic($debug,$data)
{
    global $dbh;
    $n = 0;
    $dbh->beginTransaction();
    $q = $dbh->prepare(
        "INSERT IGNORE INTO `sites` (
            `name`,
            `url`,
            `piwic`,
            `piwicId`
         ) VALUES (
            ?, 
            ?,
            ?,
            ?
         );"
    );
    $q->bindParam(1, $name);
    $q->bindParam(2, $url);
    $q->bindParam(3, $piwic);
    $q->bindParam(4, $piwicId);
    foreach ($data as $item)
    {
        $name = $item->name;
        $url = $item->main_url;
        $piwic = 1;
        $piwicId = $item->idsite;
        $q->execute();
        $n = $n + 1;
    }
    $dbh->commit();
    if ($debug === true)
    {
        $log = 'Number of sites successfully imported : ' . $n . '.';
        writeLog($log);
        unset($log);
    }
    unset($q,$data,$n);
}

/** 
 * Select all PIWIC sites IDs from local database.
 *
 * @param bool $debug true/false for db logging.
 *
 * @return array.
 */
function selectAllPiwicSites($debug)
{
    global $dbh;
    $q = $dbh->prepare(
        "SELECT DISTINCT `piwicId`  
        FROM `sites` 
        WHERE `piwicId` IS NOT NULL
        AND `piwic` = 1;"
    );
    $q->execute();
    $results = $q->fetchAll(PDO::FETCH_COLUMN);
    $q->closeCursor();
    unset($q);
    if (isset($results))
    {
        if ($debug === true)
        {
            $log = 'Number of selected sites : ' . count($results) . '.';
            writeLog($log);
            unset($log);
        }
        return $results;
    }
}

/** 
 * Select all GOOGLE ANALYTICS sites IDs from local database.
 *
 * @param bool $debug true/false for db logging.
 *
 * @return array.
 */
function selectAllGASites($debug)
{
    global $dbh;
    $q = $dbh->prepare(
        "SELECT `ga_profile_id`,
            `id`
        FROM `sites` 
        WHERE `ganalytics` = 1;"
    );
    $q->execute();
    $results = $q->fetchAll(PDO::FETCH_ASSOC);
    $q->closeCursor();
    unset($q);
    if (isset($results))
    {
        if ($debug === true)
        {
            $log = 'Number of GA selected sites : ' . count($results) . '.';
            writeLog($log);
            unset($log);
        }
        return $results;
    }
}

/** 
 * Import PIWIC per-site general stats on a weekly basis, 
 *
 * @param bool $debug true/false for db logging.
 * @param string $piwicURL the piwic server URL.
 * @param string $piwicToken the piwic token set in the conf file.
 * @param int $siteId the piwic internal ID of a specific site.
 * @param string $method the piwic method we want to use.
 * @param string $args the piwic method arguments (if any).
 * @param string $weekStart the first day of the week we want to retrieve.
 * @param string $weekEnd the last day of the week we want to retrieve.
 *
 * @return true.
 */
function recordStatsFromPiwic(
    $debug,
    $piwicUrl,
    $piwicToken,
    $siteId,
    $method,
    $args,
    $weekStart,
    $weekEnd
)
{
    global $dbh;
    
    // Fetch the data from the API
    $data = callPiwik(
        $debug,
        $piwicUrl,
        $piwicToken,
        $method,
        $args
    );
    
    // Store the data in the database
    // The IGNORE statement makes it impossible to store the same
    // week for the same site twice since a unique database index 
    // spans over siteId and start_date.
    $q = $dbh->prepare(
        "INSERT IGNORE INTO `piwicWeekly` (
            `siteId`,
            `nb_visits`,
            `nb_actions`,
            `nb_visits_converted`,
            `bounce_count`,
            `sum_visit_length`,
            `max_actions`,
            `bounce_rate`,
            `nb_actions_per_visit`,
            `avg_time_on_site`,
            `start_date`,
            `end_date`
        ) VALUES (
            :siteId,
            :nb_visits,
            :nb_actions,
            :nb_visits_converted,
            :bounce_count,
            :sum_visit_length,
            :max_actions,
            :bounce_rate,
            :nb_actions_per_visit,
            :avg_time_on_site,
            :start_date,
            :end_date
        );"
    );
    $q->bindParam(':siteId', $siteId);
    $q->bindParam(':nb_visits', $data->nb_visits);
    $q->bindParam(':nb_actions', $data->nb_actions);
    $q->bindParam(':nb_visits_converted', $data->nb_visits_converted);
    $q->bindParam(':bounce_count', $data->bounce_count);
    $q->bindParam(':sum_visit_length', $data->sum_visit_length);
    $q->bindParam(':max_actions', $data->max_actions);
    $q->bindParam(':bounce_rate', $data->bounce_rate);
    $q->bindParam(':nb_actions_per_visit', $data->nb_actions_per_visit);
    $q->bindParam(':avg_time_on_site', $data->avg_time_on_site);
    $q->bindParam(':start_date', $weekStart);
    $q->bindParam(':end_date', $weekEnd);
    
    $q->execute();
    $q->closeCursor();
    
    unset($data,$q);
    
    return true;
}

/****************************/
/*   CSV EXPORT FUNCTIONS   */
/****************************/

/** 
 * Select all PIWIC sites IDs and names from local database.
 * -> Should be melted with selectAllPiwicSites() func.
 *
 * @param bool $debug true/false for db logging.
 *
 * @return array.
 */
function fetchPWSites($debug)
{
    global $dbh;
    $q = $dbh->prepare(
        "SELECT DISTINCT `piwicId` AS id, name   
        FROM `sites` 
        WHERE `piwicId` IS NOT NULL
        AND `piwic` = 1;"
    );
    $q->execute();
    $results = $q->fetchAll(PDO::FETCH_ASSOC);
    $q->closeCursor();
    unset($q);
    if (isset($results))
    {
        if ($debug === true)
        {
            $log = 'Number of selected Piwic sites : ' . count($results) . '.';
            writeLog($log);
            unset($log);
        }
        return $results;
    }
}

function fetchGASites($debug)
{
    global $dbh;
    $q = $dbh->prepare(
        "SELECT DISTINCT `ga_profile_id` AS id, 
        `url` AS name    
        FROM `sites` 
        WHERE `ganalytics` > 0;"
    );
    $q->execute();
    $results = $q->fetchAll(PDO::FETCH_ASSOC);
    $q->closeCursor();
    unset($q);
    if (isset($results))
    {
        if ($debug === true)
        {
            $log = 'Number of selected GA sites : ' . count($results) . '.';
            writeLog($log);
            unset($log);
        }
        return $results;
    }
}

/** 
 * PIWIC VERSION
 * Fetch wanted metrics on a yearly basis for specified sites. 
 * Format the data and returns a PHP object.
 *
 * Most used metrics -> column name :
 * Visits -> 'nb_visits'
 * Pages -> 'nb_actions'
 * Bounce Rate -> 'bounce_rate'
 * Pages per visit -> 'nb_actions_per_visit'
 * Average Time on site -> 'avg_time_on_site'
 *
 * All metrics needs to specify which mathematics func we need : eg. SUM or AVG...
 *
 * @param bool $debug true/false for db logging.
 * @param array $sites names and piwic IDs of the sites we want.
 * @param array $metricWanted list of the statistics we want.
 * @param array $years the years we want to fetch the stats from.
 *
 * @return array.
 */
function fetchPWData($debug,$sites,$metricsWanted,$years) 
{
    global $dbh;
    
    foreach ($sites as $site)
    {
        foreach ($metricsWanted as $metricWanted)
        {
            foreach ($years as $year)
            {
                if ($metricWanted['type'] == 'SUM')
                {
                    $q = $dbh->prepare(
                        "SELECT SUM(" . $metricWanted['column'] . ") 
                        AS :column 
                        FROM `piwicWeekly` 
                        WHERE `siteId` = :siteId 
                        AND YEAR(`start_date`) = :year;"
                    );
                    $q->bindParam(':column', $metricWanted['column']);
                    $q->bindParam(':siteId', $site['id']);
                    $q->bindParam(':year', $year);
                    $q->execute();
                    $results = $q->fetch(PDO::FETCH_ASSOC);
                    $q->closeCursor();
                    unset($q);
                    if ($debug === true)
                    {
                        $log = $results[$metricWanted['column']];
                        writeLog($log);
                        unset($log);
                    }
                    // If we have no stats for the current year, print 0
                    if (empty($results[$metricWanted['column']]))
                    {
                        $results[$metricWanted['column']] = 0;
                    }
                }
                if ($metricWanted['type'] == 'AVG')
                {
                    $q = $dbh->prepare(
                        "SELECT AVG(" . $metricWanted['column'] . ") 
                        AS :column 
                        FROM `piwicWeekly` 
                        WHERE `siteId` = :siteId 
                        AND YEAR(`start_date`) = :year;"
                    );
                    $q->bindParam(':column', $metricWanted['column']);
                    $q->bindParam(':siteId', $site['id']);
                    $q->bindParam(':year', $year);
                    $q->execute();
                    $results = $q->fetch(PDO::FETCH_ASSOC);
                    $q->closeCursor();
                    unset($q);
                    if ($debug === true)
                    {
                        $log = $results[$metricWanted['column']];
                        writeLog($log);
                        unset($log);
                    }
                    // If we have no stats for the current year, print 0
                    if (empty($results[$metricWanted['column']]))
                    {
                        $results[$metricWanted['column']] = 0;
                    }
                }
                $pushYears[] = array($year => $results[$metricWanted['column']]);
                unset($results);
            }
            $pushYears = array_reduce($pushYears, 'array_merge', array());
            $pushMetric[] = array($metricWanted['column'] => $pushYears);
            unset($pushYears);
        }
        $pushMetric = array_reduce($pushMetric, 'array_merge', array());
        $pushSite[] = array($site['name'] => $pushMetric);
        unset($pushMetric);
    }
    $pushSite = array_reduce($pushSite, 'array_merge', array());
    $object = (object) $pushSite;
    unset($pushSite);
    return $object;
}

/** 
 * GOOGLE ANALYTICS VERSION
 * Fetch wanted metrics on a yearly basis for specified sites. 
 * Format the data and returns a PHP object.
 *
 * Most used metrics -> column name :
 * Visits -> 'nb_visits'
 * Pages -> 'nb_actions'
 * Bounce Rate -> 'bounce_rate'
 * Pages per visit -> 'nb_actions_per_visit'
 * Average Time on site -> 'avg_time_on_site'
 *
 * All metrics needs to specify which mathematics func we need : eg. SUM or AVG...
 *
 * @param bool $debug true/false for db logging.
 * @param array $sites names and piwic IDs of the sites we want.
 * @param array $metricWanted list of the statistics we want.
 * @param array $years the years we want to fetch the stats from.
 *
 * @return array.
 */
function fetchGAData($debug,$sites,$metricsWanted,$years) 
{
    global $dbh;
    
    foreach ($sites as $site)
    {
        foreach ($metricsWanted as $metricWanted)
        {
            foreach ($years as $year)
            {
                if ($metricWanted['type'] == 'SUM')
                {
                    $q = $dbh->prepare(
                        "SELECT SUM(" . $metricWanted['column'] . ") 
                        AS :column 
                        FROM `gaWeekly` 
                        WHERE `profileId` = :siteId 
                        AND YEAR(`start_date`) = :year;"
                    );
                    $q->bindParam(':column', $metricWanted['column']);
                    $q->bindParam(':siteId', $site['id']);
                    $q->bindParam(':year', $year);
                    $q->execute();
                    $results = $q->fetch(PDO::FETCH_ASSOC);
                    $q->closeCursor();
                    unset($q);
                    if ($debug === true)
                    {
                        $log = $results[$metricWanted['column']];
                        writeLog($log);
                        unset($log);
                    }
                    // If we have no stats for the current year, print 0
                    if (empty($results[$metricWanted['column']]))
                    {
                        $results[$metricWanted['column']] = 0;
                    }
                }
                if ($metricWanted['type'] == 'AVG')
                {
                    $q = $dbh->prepare(
                        "SELECT AVG(" . $metricWanted['column'] . ") 
                        AS :column 
                        FROM `gaWeekly` 
                        WHERE `profileId` = :siteId 
                        AND YEAR(`start_date`) = :year;"
                    );
                    $q->bindParam(':column', $metricWanted['column']);
                    $q->bindParam(':siteId', $site['id']);
                    $q->bindParam(':year', $year);
                    $q->execute();
                    $results = $q->fetch(PDO::FETCH_ASSOC);
                    $q->closeCursor();
                    unset($q);
                    if ($debug === true)
                    {
                        $log = $results[$metricWanted['column']];
                        writeLog($log);
                        unset($log);
                    }
                    // If we have no stats for the current year, print 0
                    if (empty($results[$metricWanted['column']]))
                    {
                        $results[$metricWanted['column']] = 0;
                    }
                }
                $pushYears[] = array($year => $results[$metricWanted['column']]);
                unset($results);
            }
            $pushYears = array_reduce($pushYears, 'array_merge', array());
            $pushMetric[] = array($metricWanted['column'] => $pushYears);
            unset($pushYears);
        }
        $pushMetric = array_reduce($pushMetric, 'array_merge', array());
        $pushSite[] = array($site['name'] => $pushMetric);
        unset($pushMetric);
    }
    $pushSite = array_reduce($pushSite, 'array_merge', array());
    $object = (object) $pushSite;
    unset($pushSite);
    return $object;
}

/** 
 * PIWIC VERSION
 * Flattens the data into a CSV string.
 * By default, we use the tab ("\t") delimiter.
 *
 * @param bool $debug true/false for db logging.
 * @param object $data The PHP object containing the data to format.
 *
 * @return string.
 */
function formatPWData($data,$debug)
{
    $delimiter = "\t";
    $newline = "\n";
    $finalString = '';
    $firstLine = 'Centre' . $delimiter 
        . 'Visites' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Pages vues'. $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Taux de rebond (moyenne)' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Pages par visite (moyenne)' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Durée de la visite en secondes (moyenne)' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $newline;
    $secondLine = $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . $newline;
    
    $array = (array) $data;
    foreach ($array as $k => $v)
    {
        $stringPrefix = $k . $delimiter;
        $string = '';
        
        foreach ($v as $k2 => $v2)
        {
            $yearsValues = implode($delimiter,$v2);
            $string = $string . $yearsValues . $delimiter;
            unset($yearsValues);
        }
        $finalString = $finalString 
            . $stringPrefix 
            . substr($string, 0, -1) 
            . $newline;
    }
    $finalString = $firstLine 
        . $secondLine
        . $finalString;
    if ($debug === true)
    {
        $log = substr($finalString,0,128);
        writeLog($log);
        unset($log);
    }
    unset($data,$array);
    return $finalString;
}

/** 
 * GOOGLE ANALYTICS VERSION
 * Flattens the data into a CSV string.
 * By default, we use the tab ("\t") delimiter.
 *
 * @param bool $debug true/false for db logging.
 * @param object $data The PHP object containing the data to format.
 *
 * @return string.
 */
function formatGAData($data,$debug)
{
    /*$delimiter = "\t";*/
    $delimiter = ";";
    $newline = "\n";
    $finalString = '';
    $firstLine = 'Centre' . $delimiter 
        . 'Utilisateurs' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Visites' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Pages vues'. $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Taux de rebond (moyenne)' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . 'Durée de la visite en secondes (moyenne)' . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $delimiter
        . $newline;
    $secondLine = $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . '2015' . $delimiter
        . '2016' . $delimiter
        . '2017' . $delimiter
        . '2018' . $delimiter
        . '2019' . $delimiter
        . $newline;
    
    $array = (array) $data;
    print_r($array);
    foreach ($array as $k => $v)
    {
        $stringPrefix = $k . $delimiter;
        $string = '';
        
        foreach ($v as $k2 => $v2)
        {
            $yearsValues = implode($delimiter,$v2);
            $string = $string . $yearsValues . $delimiter;
            unset($yearsValues);
        }
        $finalString = $finalString 
        . $stringPrefix 
        . substr($string, 0, -1) 
        . $newline; 
    }
    $finalString = $firstLine 
        . $secondLine
        . $finalString;
    if ($debug === true)
    {
        $log = substr($finalString,0,128);
        writeLog($log);
        unset($log);
    }
    unset($data,$array);
    return $finalString;
}

/** 
 * Writes CSV string to file.
 * By default, we use .txt format for the tab char to work on MS Excel.
 *
 * @param bool $debug true/false for db logging.
 * @param string $string the CSV formatted string.
 * @param string $path the path of the file.
 *
 * @return true.
 */
function outputCSV($string,$path,$debug)
{
    file_put_contents($path, $string);
    if ($debug === true)
    {
        $log = 'File ' . $path . ' generated.';
        writeLog($log);
        unset($log);
    }
    unset($string,$path);
    return true;
}

/***************************************************/
/*            GOOGLE ANALYTICS FUNCTIONS           */
/*  Functions borrowed from Google documentation   */
/*  and adapted to suit our specific needs.        */
/*  Note : version 3 AND 4 of the API are used !   */
/***************************************************/

/**
 * Initializes an Analytics Reporting API V3 service object.
 *
 * @return An authorized Analytics Reporting API V3 service object.
 */
function initializeAnalyticsV3($keyFilePath)
{
  // Use the developers console and download your service account
  // credentials in JSON format. Place them in this directory or
  // change the key file location if necessary.
  $KEY_FILE_LOCATION = $keyFilePath;

  // Create and configure a new client object.
  $client = new Google_Client();
  $client->setApplicationName("Hello Analytics Reporting");
  $client->setAuthConfig($KEY_FILE_LOCATION);
  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
  // GA API V.3 version of the class :
  $analytics = new Google_Service_Analytics($client);
    
  return $analytics;
}

/**
 * Initializes an Analytics Reporting API V4 service object.
 *
 * @return An authorized Analytics Reporting API V4 service object.
 */
function initializeAnalyticsv4($keyFilePath)
{
  // Use the developers console and download your service account
  // credentials in JSON format. Place them in this directory or
  // change the key file location if necessary.
  $KEY_FILE_LOCATION = $keyFilePath;

  // Create and configure a new client object.
  $client = new Google_Client();
  $client->setApplicationName("Hello Analytics Reporting");
  $client->setAuthConfig($KEY_FILE_LOCATION);
  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
  // GA API V.4 version of the class :
  $analytics = new Google_Service_AnalyticsReporting($client);
    
  return $analytics;
}

/** 
 * Queries Google Analytics for all accounts and profiles
 * within service account scope.
 *
 * @param array $analytics the API service object.
 * @param bool $debug true/false for db logging.
 *
 * @return array of accounts and associated profiles.
 */
function getAllAccountsInfo($analytics,$debug) 
{
  // Get the list of accounts for the authorized user.
  $accounts = $analytics->management_accounts->listManagementAccounts();  
  if (count($accounts->getItems()) > 0) 
  {
    $items = $accounts->getItems();
    foreach ($items as $item)
    {
        // Get the list of profiles for the accounts.
        $profiles = $analytics->management_profiles->listManagementProfiles($item->id, '~all');
        // build the array
        foreach ($profiles as $profile)
        {
            $profilesArray[] = array(
                'name' => $profile->name,
                'id' => $profile->id,
                'propertyId' => $profile->webPropertyId,
                'url' => $profile->websiteUrl
            );
        }
        $accountsDetails[] = array(
            'name' => $item->name,
            'id' => $item->id,
            'profiles' => $profilesArray
        );
        unset($profilesArray);
    }
    return $accountsDetails;
  }   
}

/** 
 * Save Google Analytics profiles into the database.
 *
 * @param array $accounts the accounts + profiles list.
 * @param bool $debug true/false for db logging.
 *
 * @return true.
 */
function saveGAprofiles($accounts,$debug) 
{
    global $dbh;
    if (!empty($accounts))
    {
        $n = 0;
        $dbh->beginTransaction();
        $q = $dbh->prepare(
            "INSERT IGNORE INTO `sites` (
                `name`,
                `url`,
                `ganalytics`,
                `ga_profile_id`,
                `ga_property_id`
             ) VALUES (
                ?, 
                ?,
                1,
                ?,
                ?
             );"
        );
        $q->bindParam(1, $name);
        $q->bindParam(2, $url);
        $q->bindParam(3, $pid);
        $q->bindParam(4, $proId);
        foreach ($accounts as $account)
        {
            foreach ($account['profiles'] as $profile)
            {
                $name = $profile['name'];
                $url = $profile['url'];
                $pid = $profile['id'];
                $proId = $profile['propertyId'];
                $q->execute();
                $n = $n + 1;
                if ($debug === true)
                {
                    $log = $name . "\n"
                        . $url . "\n"
                        . $pid . "\n"
                        . $proId . "\n";
                    writeLog($log);
                    unset($log);
                } 
            }
            $q->execute();
        }
        $dbh->commit();
        if ($debug === true)
        {
            $log = 'Number of GA sites successfully imported : ' . $n . '.';
            writeLog($log);
            unset($log);
        }
        unset($q,$accounts,$n);
        return true;
    }
}

/**
 * Queries a particuliar view for all metrics, 
 * in a specific range of dates.
 *
 * @param array $analytics the API service object.
 * @param string $viewid the view we are fetching the stat from.
 * @param array $metric the expression/alias of the metric.
 * @param string $daterange start/end dates span.
 * @param bool $debug true/false for db logging.
 *
 * @return array : The Analytics Reporting API V4 response.
 */
function statFromGA(
    $analytics,
    $viewid,
    $daterange,
    $debug
) 
{
    // Replace with your view ID
    $VIEW_ID = $viewid;
    
    // Create the DateRange object.
    $dateRange = new Google_Service_AnalyticsReporting_DateRange();
    $dateRange->setStartDate($daterange['week_start']);
    $dateRange->setEndDate($daterange['week_end']);

    // Create Metrics objects.
    $users = new Google_Service_AnalyticsReporting_Metric();
    $users->setExpression('ga:users');
    $users->setAlias('Users');
    
    $sessions = new Google_Service_AnalyticsReporting_Metric();
    $sessions->setExpression('ga:sessions');
    $sessions->setAlias('Sessions');
    
    $pageviews = new Google_Service_AnalyticsReporting_Metric();
    $pageviews->setExpression('ga:pageviews');
    $pageviews->setAlias('Pageviews');
    
    $bounceRate = new Google_Service_AnalyticsReporting_Metric();
    $bounceRate->setExpression('ga:bounceRate');
    $bounceRate->setAlias('Bounce Rate');
    
    $avgSessionDuration = new Google_Service_AnalyticsReporting_Metric();
    $avgSessionDuration->setExpression('ga:avgSessionDuration');
    $avgSessionDuration->setAlias('Session Duration');

    // Create the ReportRequest object.
    $request = new Google_Service_AnalyticsReporting_ReportRequest();
    $request->setViewId($VIEW_ID);
    $request->setDateRanges($dateRange);
    $request->setMetrics(
        array(
            $users, 
            $sessions, 
            $pageviews,
            $bounceRate,
            $avgSessionDuration
        )
    );

    $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
    $body->setReportRequests( array( $request) );
    return $analytics->reports->batchGet( $body );
}

/**
 * Parses and prints the Analytics Reporting API V4 response.
 *
 * @param array $reports An Analytics Reporting API V4 response.
 *
 * @return string : the stat value.
 */
function printResults(&$reports) {
  $allValues = array();
  for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {
    $report = $reports[ $reportIndex ];
    $header = $report->getColumnHeader();
    //$dimensionHeaders = $header->getDimensions();
    $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
    $rows = $report->getData()->getRows();

    for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
      $row = $rows[ $rowIndex ];
      //$dimensions = $row->getDimensions();
      $metrics = $row->getMetrics();
      //for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
      //    print($dimensionHeaders[$i] . ": " . $dimensions[$i] . "\n");
      //}

      for ($j = 0; $j < count($metrics); $j++) {
        $values = $metrics[$j]->getValues();
        for ($k = 0; $k < count($values); $k++) {
          //$entry = $metricHeaders[$k];
          if (empty($values[$k]))
          {
              $allValues[] = 0;
          } else {
              $allValues[] = $values[$k];
          }
        }
      }
    }
  }
  return $allValues;
}

/**
 * Saves a single stat into the database
 *
 * @param array $data The stat within its date range for the view.
 * @param bool $debug true/false for db logging.
 *
 * @return true.
 */
function recordGAstat(
    $gaViewId,
    $daterange,
    $data,
    $debug
) 
{
    global $dbh;
    if (!empty($data))
    {
        $q = $dbh->prepare(
            "INSERT IGNORE INTO `gaWeekly` (
                `profileId`,
                `nb_users`,
                `nb_sessions`,
                `nb_pageviews`,
                `bounceRate`,
                `avgSessionDuration`,
                `start_date`,
                `end_date`
            ) VALUES (
                :profileid,
                :users,
                :sessions,
                :pageviews,
                :bouncerate,
                :avgSessionDuration,
                :start,
                :end
            );"
        );
        $q->bindParam(':profileid', $gaViewId['ga_profile_id']);
        $q->bindParam(':users', $data[0]);
        $q->bindParam(':sessions', $data[1]);
        $q->bindParam(':pageviews', $data[2]);
        $q->bindParam(':bouncerate', $data[3]);
        $q->bindParam(':avgSessionDuration', $data[4]);
        $q->bindParam(':start', $daterange['week_start']);
        $q->bindParam(':end', $daterange['week_end']);

        $q->execute();
        $q->closeCursor();

        unset($data,$q);
        return true;  
    }
}
