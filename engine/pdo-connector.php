<?php

/*** STATIFIER DATABASE CONNECTOR ***/
/*** david.brett@ehess.fr         ***/
/*** web@ehess.fr                 ***/
/*** V.0.1 - October 2018         ***/
/*** COPYRIGHT EHESS/DNB          ***/

/*** ENGINE DATABASE CONNECTION   ***/
try {
    $dbh = new PDO(
        $database['driver'].':host='.
        $database['host'].'; dbname='.
        $database['database'], 
        $database['username'], 
        $database['password']
    );
    $dbh->setAttribute(
        PDO::ATTR_ERRMODE, 
        PDO::ERRMODE_EXCEPTION
    );
}
catch (Exception $e){
    die($e->getMessage());
}