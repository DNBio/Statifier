# Statifier

Author : David N. Brett<br/>
V.0.2 - April 2019<br/>
Copyright : David N. Brett<br/>
davidnbrett@gmail.com<br/>

<h2>What is Statifier ?</h2>

<p>Statifier is a CLI PHP app that connects to Piwic and/or Google Analytics APIs to gather monitored websites metrics data locally.<br/>
It is currently used to export data from specific metrics on a weekly basis in a CSV formated file.<br/>
    In the future, Statifier will be used to display websites metrics from different sources in real-time.<br/></p>

<p><strong>Statifier is still in early developement and currently relies on procedural code.</strong></p>

<h2>Dependencies</h2>

<ul>
    <li>PHP 7+ with CURL;</li>
    <li>MySQL 5.6+;</li>
    <li>An administrative access to Piwic and/or Google analytics account;</li>
    <li>A CLI access to your sever !</li>
</ul>

<h2>How to install</h2>

<ol>
    <li>Create a database and import database schema (sql/database.sql).</li>
    <li>Create a user to access that database.</li>
    <li>Copy/rename statifier.conf.default.php to statifier.conf.php.</li>
    <li>Edit statifier.conf.php to map your database credentials and piwic and/or Google analytics settings.</li>
    <li>Run ga.php and/or piwic.php in your terminal to populate the database.</li>
    <li>Run CSV.php in your terminal to format and export the data in a CSV text file.</li>
</ol>