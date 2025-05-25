<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <title>Agency</title>
</head>
<body>
    <?php
        // Get URL components
        $url = parse_url($_SERVER['REQUEST_URI']);
        parse_str($url['query'], $params);
        $agency = $params['a'];

        // Set up connection to SQL database
        $dsn = "mysql:host=localhost;dbname=transitNYC_gtfs_" . $agency;
        $dbusername = "root";
        $dbpassword = "";
        try {
            $pdo = new PDO($dsn, $dbusername, $dbpassword);
            //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPION);
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
        }

        // Get agency file
        $stmt = $pdo->prepare('SELECT agency_name FROM agency;');
        $stmt->execute();
        $agencyName = $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['agency_name'];

        // Get routes
        $stmt = $pdo->prepare('SELECT route_id, route_type, route_color FROM routes;');
        $stmt->execute();
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get route stops
        $stmt = $pdo->prepare('SELECT route_id, stop_id, stop_name, stop_lat, stop_lon FROM routestops;');
        $stmt->execute();
        $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get route shapes
        $stmt = $pdo->prepare('SELECT route_id, shape_pt_lat, shape_pt_lon FROM routeshapes;');
        $stmt->execute();
        $shapes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send data to JavaScript
        echo '
        <script>
            const agency = ' . json_encode($agency) . '
            const shapes = ' . json_encode($shapes) . '
            const routes = ' . json_encode($routes) . '
        </script>';
    ?>
    <h1><?php echo $agencyName; ?> System Map</h1>
    <button onclick="zoomToCurrent()">Zoom in to current location</button><br><br>
    <a href="./routelist.php?a=<?php echo $agency; ?>">See a full list of routes with schedules</a><br><br>
    <div id="outerbox"></div>
    <script src="./scripts/map.js"></script>
</body>
</html>