<?php

$user     = 'root';
$password = '';
$host     = 'localhost';
$dbname   = 'cycom_eproposal';

$condb = new mysqli($host, $user, $password, $dbname);

// Semak jika sambungan berjaya
if ($condb->connect_error) {
  die("Connection failed: " . $condb->connect_error);
}

?>