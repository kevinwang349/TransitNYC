<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <title>Trip Schedule</title>
</head>
<body>
    <?php
    // Get URL components
    $url = parse_url($_SERVER['REQUEST_URI']);
    parse_str($url['query'], $params);
    $agency = $params['a'];
    $tripid = $params['t'];
    if (!isset($agency) || !isset($tripid)) {
        echo "Invalid agency / tripid!";
        return;
    }

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

    // Get trips file
    $query = 'SELECT trip_headsign, route_id, direction_id, shape_id FROM trips
        WHERE trip_id = "' . $tripid . '";';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $trip = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];

    // Get routes file
    $query = 'SELECT route_short_name, route_long_name, route_color FROM routes
        WHERE route_id = "' . $trip['route_id'] . '";';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $route = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
    //var_dump($route); echo "<br><br>";

    // Create title and map wrapper div
    echo "<h2>Trip Schedule for " . $route['route_short_name'] . " " .
        $route["route_long_name"] . " towards " . $trip['trip_headsign'] . "</h2>";
    echo '<div id="outerbox"></div>';
    echo '<br>';

    // Create temporary table for stop_times
    $query = 'DROP TABLE IF EXISTS schedule_times;';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $query = 'CREATE TABLE schedule_times(
        departure_time TEXT,
        stop_id INT,
        stop_sequence TINYINT
    )';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    // Fetch stop times into temporary table
    $query = 'INSERT INTO schedule_times
        SELECT departure_time, stop_id, stop_sequence
        FROM stop_times
        WHERE trip_id = "' . $tripid . '"
        ORDER BY stop_sequence ASC;';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stmt = $pdo->prepare('SELECT * FROM schedule_times;');
    $stmt->execute();
    $stop_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($stop_times); echo "<br><br>";

    // Fetch all (unique) stop ids from stop_times
    $stmt = $pdo->prepare('SELECT DISTINCT stop_id FROM schedule_times;');
    $stmt->execute();
    $stopids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'stop_id');
    //var_dump($stopids); echo "<br><br>";

    // Get all stops for the given schedule
    $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_stops;');
    $stmt->execute();
    $stmt = $pdo->prepare('CREATE TABLE temp_stops(
        stop_id VARCHAR(255) PRIMARY KEY, stop_name TEXT, stop_code TEXT, stop_lat DECIMAL(10,8), stop_lon DECIMAL(10,8));');
    $stmt->execute();
    $query = 'INSERT INTO temp_stops
        SELECT stop_id, stop_name, stop_code, stop_lat, stop_lon FROM stops
        WHERE stop_id IN ("' . implode('", "', $stopids) . '");';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stmt = $pdo->prepare('SELECT * FROM temp_stops;');
    $stmt->execute();
    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($stops); echo "<br><br>";

    // Display table
    echo '<table id="table"><tbody>';
    // Create first row
    echo '<tr style="position: relative; background: white; z-index: 2; top: 0px;" id="stopsRow">';
    echo '<td>Stop name + link to stop schedule</td>';
    echo '<td>Departure time</td>';
    echo "</tr>";
    // Add remaining rows
    $route_color = tint($route['route_color']);
    foreach ($stop_times as $row) {
        echo "<tr>";
        // Fetch stop
        $stmt = $pdo->prepare('SELECT stop_name FROM temp_stops
            WHERE stop_id = "' . $row['stop_id'] . '";');
        $stmt->execute();
        $stop = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
        echo '<td style="border-bottom: 1px black solid; border-right: 1px black solid;
                font-size: 18px; background-color: #' . $route_color . '">
            <a href="./stopschedule.php?a=' . $agency . '&s=' . $row['stop_id'] . '"
                style="color: black; text-decoration: none;">' .
            $stop['stop_name'] . "</a></td>";
        echo '<td style="border-top: 1px black solid; border-left: 1px black solid;
            font-size: 16px;">' . $row['departure_time'] . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";

    // Link to route schedule
    echo "<br>";
    echo '<a href="./routeschedule.php?a=' . $agency . '&r=' . $trip['route_id'] . '&d=' . $trip['direction_id'] . '">
        Link to route schedule for ' . $route['route_short_name'] . " " .
        $route["route_long_name"] . " towards " . $trip['trip_headsign'] . '</a>';

    // Get shape data for trip
    $query = 'SELECT shape_pt_lat, shape_pt_lon FROM shapes
        WHERE shape_id = "' . $trip['shape_id'] . '";';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $shape = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Send data to Javascript
    echo '
    <script>
        const agency = ' . json_encode($agency) . '
        const tripstops = ' . json_encode($stops) . '
        const tripshape = ' . json_encode($shape) . '
        const color = ' . json_encode($route['route_color']) . '
    </script>';

    // Delete temporary tables
    $stmt = $pdo->prepare('DROP TABLE IF EXISTS schedule_times;');
    $stmt->execute();
    $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_stops;');
    $stmt->execute();


    // Make a color lighter
    function tint($color) {
        if ($color == NULL) $color = '7f7f7f';
        $rgb = array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2)));
        $newcolor = "";
        for ($i = 0; $i < 3; $i++) {
            $tint = 255 - $rgb[$i];
            $tint *= 2 / 3;
            $rgb[$i] += $tint;
            $hex = dechex($rgb[$i]);
            if (strlen($hex) == 1) {
                $newcolor = $newcolor . '0' . $hex;
            } else {
                $newcolor = $newcolor . $hex;
            }
        }
        return $newcolor;
    }

    ?>
    <script src="./scripts/trip.js"></script>
</body>
</html>