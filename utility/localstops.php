<?php

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo "Error: POST requests only";
}

// Get parameters
$agency = $_POST['a'];
$lat = $_POST['lat'];
$lng = $_POST['lng'];
$latRange = $_POST['latRange'];
$lngRange = $_POST['lngRange'];

// Create PDO connection to SQL database
$dsn = "mysql:host=localhost;dbname=transitNYC_gtfs_" . $agency;
$dbusername = "root";
$dbpassword = "";
try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPION);
} catch (PDOException $e) {
    echo $e;
}

// Fetch all stops
$stmt = $pdo->prepare('SELECT * FROM stops;');
$stmt->execute();
$stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get only stops within the requested lat/lng range
$newstops = [$stops[0]];
foreach ($stops as $stop){
    if(abs($stop['stop_lat']-$lat) <= $latRange && abs($stop['stop_lon']-$lng) <= $lngRange){
        $newstops[] = $stop;
    }
}

echo json_encode($newstops);
