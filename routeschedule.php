<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Schedule</title>
</head>
<body>
    <?php
    // Get URL components
    $url = parse_url($_SERVER['REQUEST_URI']);
    parse_str($url['query'], $params);
    if (!array_key_exists('a', $params) || !array_key_exists('r', $params)) {
        echo "Invalid agency / routeid!";
        return;
    }
    $agency = $params['a'];
    $routeid = $params['r'];
    $dirid = array_key_exists('d', $params) ? $params['d'] : 0;
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $date = date_format(date_create($_POST['date']), 'Ymd');
    } elseif (array_key_exists('t', $params)) {
        $date = $params['t'];
    } else {
        $date = date_format(date_create("now",
            timezone_open("America/Toronto")), "Ymd");
    }

    //echo $agency . " " . $routeid . " " . $dirid . " " . $date . "<br>";

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

    // Get routes file
    $query = "SELECT route_short_name, route_long_name, route_color FROM routes
        WHERE route_id = " . $routeid . ";";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $route = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
    //var_dump($route); echo "<br><br>";
    $dateObj = date_create_from_format("Ymd", $date);
    echo "<h2>Route Schedule for " . $route['route_short_name'] . " " .
        $route["route_long_name"] . " on " . date_format($dateObj, "l Y/m/d") . "</h2>";

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
    foreach ($cal2 as $row) {
        $index = array_search($row['service_id'], $serviceids);
        if($row['exception_type'] == 1){
            if(!$index){
                $serviceids[] = $row['service_id'];
            }
        }else{
            array_splice($serviceids, $index, 1);
        }
    }
    //var_dump($serviceids); echo "<br><br>";

    // Get all trips for the given service id
    $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_trips;');
    $stmt->execute();
    $stmt = $pdo->prepare('CREATE TABLE temp_trips(
        trip_id VARCHAR(255) PRIMARY KEY, trip_headsign TEXT);');
    $stmt->execute();
    $query = 'INSERT INTO temp_trips
        SELECT trip_id, trip_headsign FROM trips
        WHERE service_id IN ("' . implode('", "', $serviceids) . '")
        AND route_id = ' . $routeid . '
        AND direction_id = ' . $dirid . ';';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stmt = $pdo->prepare('SELECT * FROM temp_trips;');
    $stmt->execute();
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tripids = array_column($trips, 'trip_id');
    $num_trips = count($tripids);
    //var_dump($tripids); echo "<br><br>";

    if (empty($tripids)) {
        echo "<h3>There is no service on this line on the requested date.</h3>";
        die();
    }

    // Create temporary table for stop_times
    $query = 'DROP TABLE IF EXISTS schedule_times;';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $query = 'CREATE TABLE schedule_times(
        trip_id TEXT,
        departure_time TEXT,
        stop_id INT,
        stop_sequence TINYINT
    )';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    // Fetch stop times into temporary table
    $query = 'INSERT INTO schedule_times
        SELECT trip_id, departure_time, stop_id, stop_sequence
        FROM stop_times
        WHERE trip_id IN ("' . implode('", "', $tripids) . '");';
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stop_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($stop_times); echo "<br><br>";

    // Fetch all (unique) stop ids from stop_times
    $stmt = $pdo->prepare('SELECT DISTINCT stop_id FROM schedule_times;');
    $stmt->execute();
    $stopids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'stop_id');
    $num_stops = count($stopids);
    //var_dump($stopids); echo "<br><br>";

    // Get all stops for the given schedule
    $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_stops;');
    $stmt->execute();
    $stmt = $pdo->prepare('CREATE TABLE temp_stops(
        stop_id VARCHAR(255) PRIMARY KEY, stop_name TEXT);');
    $stmt->execute();
    $query = "INSERT INTO temp_stops
        SELECT stop_id, stop_name FROM stops
        WHERE stop_id IN (" . implode(", ", $stopids) . ");";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    //$stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //var_dump($stops); echo "<br><br>";
    
    console_log(microtime(true) . " data loaded");
    
    // Sort the trips
    foreach ($stopids as $stopid){
        // Sort the stop using SQL
        $query = 'SELECT trip_id, departure_time FROM schedule_times
            WHERE stop_id = "' . $stopid . '"
            ORDER BY departure_time ASC;';
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $trip_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Reorder the stops using the sorted stop
        $k = 0;
        foreach ($trip_times as $trip) {
            if ($k < $num_trips && $tripids[$k] == $trip['trip_id']) {
                $k++;
                continue;
            }
            $m = array_search($trip['trip_id'], $tripids);
            if ($m > $k) {
                $k = $m + 1;
                continue;
            }
            // Swap the row
            array_splice($tripids, $k, 0, $tripids[$m]);
            array_splice($tripids, $m, 1);
        }
    }
    //var_dump($stopids); echo "<br><br>";
    //var_dump($tripids);
    
    console_log(microtime(true) . " trips sorted");

    // Create temporary table to format the schedule
    $stmt = $pdo->prepare('DROP TABLE IF EXISTS schedule_table;');
    $stmt->execute();
    $query_C_ST = 'CREATE TABLE schedule_table(trip_id TEXT, ';
    foreach ($stopids as $stopid) {
        $query_C_ST = $query_C_ST . '`' . $stopid . '` TEXT, ';
    }
    $query_C_ST = substr($query_C_ST, 0, -2) . ');';
    //echo $query_C_ST . "<br><br>";
    $stmt = $pdo->prepare($query_C_ST);
    $stmt->execute();
    // Load values into schedule table
    foreach ($tripids as $tripid) {
        $query_S_ST = 'SELECT * FROM schedule_times
            WHERE trip_id = "' . $tripid . '"';
        $stmt = $pdo->prepare($query_S_ST);
        $stmt->execute();
        $trip_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $query_I = 'INSERT INTO schedule_table (trip_id, ';
        $query_V = 'VALUES ("' . $tripid . '", ';
        foreach ($trip_times as $val) {
            $query_I = $query_I . '`' . $val['stop_id'] . '`, ';
            $query_V = $query_V . '"' . $val['departure_time'] . '", ';
        }
        $query_I = substr($query_I, 0, -2) . ') ';
        $query_V = substr($query_V, 0, -2) . ');';
        //echo $query_I . $query_V . "<br><br>";
        $stmt = $pdo->prepare($query_I . $query_V);
        $stmt->execute();
    }

    // Replace all NULL elements with spaces
    $stmt = $pdo->prepare('SELECT * FROM schedule_table');
    $stmt->execute();
    $table = $stmt->fetchAll(PDO::FETCH_NUM);
    for($i=0; $i<$num_trips; $i++){
        for($j=1; $j<=$num_stops; $j++){
            if(!$table[$i][$j]) {
                $table[$i][$j] = ' ';
            }
        }
    }
    //var_dump($table);

    console_log(microtime(true) . " table created");

    // Sort the stops
    foreach ($table as $row){
        // Sort the trip using SQL
        $query = 'SELECT stop_id, departure_time FROM schedule_times
            WHERE trip_id = "' . $row[0] . '"
            ORDER BY stop_sequence ASC;';
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $trip_times = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Reorder the stops using the sorted trip
        $k = 0;
        foreach ($trip_times as $stop) {
            if ($k < $num_stops && $stopids[$k] == $stop['stop_id']) {
                $k++;
                continue;
            }
            $m = array_search($stop['stop_id'], $stopids);
            if ($m > $k) {
                $k = $m + 1;
                continue;
            }
            // Swap the column
            for($l=0; $l<$num_trips; $l++){
                array_splice($table[$l], $k + 1, 0, $table[$l][$m + 1]);
                array_splice($table[$l], $m + 1, 1);
            }
            array_splice($stopids, $k, 0, $stopids[$m]);
            array_splice($stopids, $m, 1);
        }
    }

    console_log(microtime(true) . " stops sorted");
    //


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

    // Returns true if time1str is after time2str,
    //  and false if time1str is before or the same as time2str
    function compare($time1str, $time2str){
        $time1=[];
        $time2=[];
        try{
            $time1strArr=explode(':', $time1str);
            foreach ($time1strArr as $time){
                $time1[] = intval($time);
            }
            $time2strArr=explode(':', $time2str);
            foreach ($time2strArr as $time){
                $time2[] = intval($time);
            }
            if ($time1[0] > $time2[0]) {
                return true;
            } else if ($time1[0] == $time2[0]) {
                if ($time1[1] > $time2[1]) {
                    return true;
                } else if ($time1[1] == $time2[1]) {
                    if ($time1[2] > $time2[2]) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }catch(Exception $e){
            echo $time1str . " " . $time2str . "<br>";
            return false;
        }
    }
    
    // Writing to console
    function console_log($output) {
        echo '<script> console.log(' . json_encode($output, JSON_HEX_TAG) . '); </script>';
    }

    ?>
    <script>
        // Update positions of top row (stops) and left column (trips) to force them to stick to the top / left of the schedule table box
        function updateDisplay(){
            const ypos=document.getElementById("outerBox").scrollTop;
            document.getElementById("stopsRow").style.top=ypos+'px';
            const xpos=document.getElementById("outerBox").scrollLeft;
            const cells=document.getElementsByClassName('trip');
            for(const cell of cells){
                cell.style.left=xpos+'px';
            }
        }
    </script>


    <div style="width: 90%; height: 570px; margin: auto; overflow: scroll;
        border: 2px black solid" id="outerBox" onscroll="updateDisplay()">
    <table id="table">
        <tr style="position: relative; background: white; z-index: 2; top: 0px;" id="stopsRow">
            <td></td>
            <?php
                // Fetch and add stops
                foreach ($stopids as $stopid) {
                    $stmt = $pdo->prepare('SELECT stop_name FROM temp_stops
                        WHERE stop_id = ' . $stopid . ';');
                    $stmt->execute();
                    $stop = $stmt->fetchAll(PDO::FETCH_NUM);
                    echo '<td style="border-bottom: 1px black solid; border-right: 1px black solid;
                        font-size: 17px" class="link">
                        <a href="./stopschedule.php?a=' . $agency . '&s=' . $stopid . '">' .
                        $stop[0][0] . "</a></td>";
                }
            ?>
        </tr>
        <?php
            // Add remaining rows
            $i = 0;
            $route_color = tint($route['route_color']);
            foreach ($table as $row) {
                echo "<tr>";
                // Fetch trip
                $stmt = $pdo->prepare('SELECT trip_headsign FROM temp_trips
                    WHERE trip_id = "' . $row[0] . '";');
                $stmt->execute();
                $trip = $stmt->fetchAll(PDO::FETCH_NUM);
                echo '<td style="border-bottom: 1px black solid; border-right: 1px black solid;
                    font-size: 18px; position: relative; z-index: 1; background-color: #' . $route_color .
                    '" class="trip">
                    <a href="./trip.php?a=' . $agency . '&t=' . $row[0] . '">' . $trip[0][0] . "</a></td>";
                // Add times
                for ($j = 0; $j < $num_stops; $j++) {
                    if($i%2==0 && $j%2==0) $color = 'cfcfcf';
                    elseif($i%2==1 && $j%2==0) $color = 'dfdfdf';
                    elseif($i%2==0 && $j%2==1) $color = 'efefef';
                    else $color = 'ffffff';
                    echo '<td style="border-top: 1px black solid; border-left: 1px black solid;
                        font-size: 16px; position: relative; z-index: 0; background-color: #' . $color . '">' .
                        $row[$j + 1] . "</td>";
                }
                $i += 1;
                echo "</tr>";
            }
        ?>
    </table></div>
    <br>
    <button onclick="flipDirection()">See schedule in the opposite direction</button>
    <?php
        // Reconstruct URL for opposite direction
        $reversedir = !($dirid);
        $reverseURL = $_SERVER['PHP_SELF'] . '?' .
            http_build_query(['a' => $agency, 'r' => $routeid, 'd' => $reversedir, 't' => $date], '', '&');
    ?>
    <script>
        function flipDirection() {
            window.location.href = '<?php echo $reverseURL; ?>';
        }
    </script>

    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
        <p style="margin-bottom: 0;">Find schedules for a different date:</p>
        <label for="date">Choose a date: </label>
            <input id="date" type="date" name="date" value="<?php echo date_format(date_create($date), "Y-m-d"); ?>"><br>
        <button onclick="submit()">See new schedule</button>
    </form>

    <?php
        // Delete temporary tables
        $stmt = $pdo->prepare('DROP TABLE IF EXISTS schedule_table;');
        $stmt->execute();
        $stmt = $pdo->prepare('DROP TABLE IF EXISTS schedule_times;');
        $stmt->execute();
        $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_stops;');
        $stmt->execute();
        $stmt = $pdo->prepare('DROP TABLE IF EXISTS temp_trips;');
        $stmt->execute();
    ?>
</body>
</html>