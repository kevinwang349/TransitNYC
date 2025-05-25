<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stop Schedule</title>
</head>
<body>
    <?php
    // Get URL components
    $url = parse_url($_SERVER['REQUEST_URI']);
    parse_str($url['query'], $params);
    $agency = $params['a'];
    $stopid = $params['s'];
    if (!isset($agency) || !isset($stopid)) {
        echo "Invalid agency / stopid!";
        return;
    }
    $date = $params['t'];
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $date = date_format(date_create($_POST['date']), 'Ymd');
    }
    if (!isset($date)) {
        $date = date_format(date_create("now",
            timezone_open("America/Toronto")), "Ymd");
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

    // Get stops file
    $query = "SELECT stop_code, stop_name FROM stops
        WHERE stop_id = " . $stopid . ";";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stop = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
    //var_dump($stops); echo "<br><br>";
    $dateObj = date_create_from_format("Ymd", $date);
    echo "<h2>Stop Schedule for #" . $stop['stop_code'] . " " .
        $stop["stop_name"] . " on " . date_format($dateObj, "l Y/m/d") . "</h2>";

    // Get calendar_dates file
    $query = "SELECT service_id, exception_type FROM calendar_dates
        WHERE date = " . $date . ";";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $cal2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($cal2); echo "<br><br>";

    if (empty($cal2)) {
        echo "No data available for this date.";
        die();
    }

    // Get service ids for the given date
    $serviceids = [];
    for ($i = 0; $i < count($cal2); $i++) {
        $index = array_search($cal2[$i]['service_id'], $serviceids);
        if($cal2[$i]['exception_type'] == 1){
            if(!$index){
                $serviceids[] = $cal2[$i]['service_id'];
            }
        }else{
            array_splice($serviceids, $index, 1);
        }
    }
    //var_dump($serviceids); echo "<br><br>";

    // Get filters, if present
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST["direction"])) {
            $dirid = $_POST["direction"];
        }
        $routeids = [];
        foreach (array_keys($_POST) as $item) {
            if ($item != 'direction' && $item != 'date') {
                $routeids[] = $item;
            }
        }
    }

    // Get all trips for the given service id
    $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_trips;');
    $stmt->execute();
    $stmt = $pdo->prepare('CREATE TABLE temp_trips(
        trip_id VARCHAR(255) PRIMARY KEY, route_id TEXT, trip_headsign TEXT, direction_id TINYINT);');
    $stmt->execute();
    $query = 'INSERT INTO temp_trips
        SELECT trip_id, route_id, trip_headsign, direction_id FROM trips
        WHERE service_id IN ("' . implode('", "', $serviceids) . '")';
    // Apply direction . route filters
    if (isset($dirid) && $dirid != -1) {
        $query = $query . ' AND direction_id = ' . $dirid;
    }
    if (isset($routeids) && !empty($routeids)) {
        $query = $query . ' AND route_id IN ("' . implode('", "', $routeids) . '")';
    }
    $query = $query . ';';
    //echo $query;
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stmt = $pdo->prepare('SELECT * FROM temp_trips;');
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tripids = array_column($trips, 'trip_id');
    //var_dump($tripids); echo "<br><br>";

    // Fetch stop times
    $query = 'SELECT trip_id, departure_time FROM stop_times
        WHERE trip_id IN ("' . implode('", "', $tripids) . '")
        AND stop_id = ' . $stopid . '
        ORDER BY departure_time ASC;';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stop_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($stop_times); echo "<br><br>";

    // If there are no stop times, don't display the table
    if (empty($stop_times)) {
        echo '<h3>There are no arrivals at this stop with the requested conditions.<br>
            Please try a different date, or a different set of filters.</h3>';
        die();
    }

    // Display table
    echo '<table id="columns"><tr><td style="width: 60%; padding: 2%;">';
    echo '<div style="position: relaive; width: 100%; height: 570px; overflow: scroll; margin: auto;
        border: 2px black solid" id="outerBox" onscroll="updateDisplay()">';
    echo '<table id="table"><tbody>';
    // Create first row
    echo '<tr style="position: relative; background: white; z-index: 2; top: 0px;" id="stopsRow">';
    echo '<td>Route + link to route schedule</td>';
    echo '<td>Trip headsign + link to trip schedule</td>';
    echo '<td>Departure time</td>';
    echo "</tr>";
    // Add remaining rows
    foreach ($stop_times as $time) {
        echo "<tr>";
        // Fetch trip
        $stmt = $pdo->prepare('SELECT route_id, trip_headsign, direction_id FROM temp_trips
            WHERE trip_id = "' . $time['trip_id'] . '";');
        $stmt->execute();
        $trip = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
        // Fetch route for trip
        $stmt = $pdo->prepare('SELECT route_id, route_short_name, route_long_name, route_color FROM routes
            WHERE route_id = "' . $trip['route_id'] . '";');
        $stmt->execute();
        $route = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
        $route_color = tint($route['route_color']);
        echo '<td style="border-bottom: 1px black solid; border-right: 1px black solid;
                font-size: 18px; background-color: #' . $route_color . '">
            <a href="./routeschedule.php?a=' . $agency . '&r=' . $route['route_id'] . '&d=' .
                $trip['direction_id'] . '&t=' . $date . '" style="color: black; text-decoration: none;">' .
                $route['route_short_name'] . ' ' . $route['route_long_name'] . "</a></td>";
        echo '<td style="border-bottom: 1px black solid; border-right: 1px black solid;
                font-size: 18px; background-color: #' . $route_color . '">
            <a href="./trip.php?a=' . $agency . '&t=' . $time['trip_id'] . '"
                style="color: black; text-decoration: none;">' . $trip['trip_headsign'] . "</a></td>";
        echo '<td style="border-top: 1px black solid; border-left: 1px black solid;
            font-size: 16px;">' .
            $time['departure_time'] . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
    echo '</td><td style="width: 50%; padding: 5%;">';

    // Fetch all routes running through this stop
    $stmt = $pdo->prepare('SELECT route_id FROM routestops
        WHERE stop_id = ' . $stopid . ';');
    $stmt->execute();
    $routeids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'route_id');
    //var_dump($routeids);
    $stmt = $pdo->prepare('SELECT route_id, route_short_name, route_long_name, route_color FROM routes
        WHERE route_id IN ("' . implode('", "', $routeids) . '")');
    $stmt->execute();
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($routes);

    // Create filtering options
    echo '<h3>Filter arrival times</h3>';
    echo '<form method="post" action="' . $_SERVER['REQUEST_URI'] . '">';
    echo '<p>Filter by direction:</p>';
    echo '<input type="radio" id="dir-" name="direction" value="-1"';
    echo (isset($dirid) && $dirid == -1) ? ' checked>' : '>';
    echo '<label for="dir-">All directions</label>';
    echo '<br>';
    echo '<input type="radio" id="dir0" name="direction" value="0"';
    echo (isset($dirid) && $dirid == 0) ? ' checked>' : '>';
    echo '<label for="dir0">0 (eastbound / northbound)</label>';
    echo '<br>';
    echo '<input type="radio" id="dir1" name="direction" value="1"';
    echo (isset($dirid) && $dirid == 1) ? ' checked>' : '>';
    echo '<label for="dir1">1 (westbound / southbound)</label>';
    echo '<p>Filter by route:</p>';
    foreach ($routes as $route) {
        echo '<input type="checkbox" id="' . $route['route_id'] . '" name="' . $route['route_id'] . '"';
        echo (isset($_POST[$route['route_id']])) ? ' checked>' : '>';
        echo '<label style="background-color: #' . tint($route['route_color']) . '" for="' . $route['route_id'] . '">' . $route['route_short_name'] . ' ' .
            $route['route_long_name'] . '</label>';
        echo '<br>';
    }
    echo '<p>See schedule for a different date:<br>
        <input id="date" type="date" name="date" value="' . date_format(date_create($date), "Y-m-d") . '"></p>';
    echo '<button onclick="submit()">Apply filters</button>';
    echo '</form>';
    echo '</td></tr></table>';


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

    <script>
        // Update positions of top row (stops) and left column (trips) to force them to stick to the top / left of the schedule table box
        function updateDisplay(){
            const ypos=document.getElementById("outerBox").scrollTop;
            document.getElementById("stopsRow").style.top=ypos+'px';
        }
    </script>
    <?php
        // Delete temporary tables
        $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_trips;');
        $stmt->execute();
    ?>
</body>
</html>