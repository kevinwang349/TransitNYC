<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route List</title>
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
    $query = "SELECT agency_name FROM agency;";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $agencyName = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Title (agency name)
    echo "<h1>" . $agencyName[0]["agency_name"] . "</h1>";

    // Get routes file
    $query = "SELECT route_id, route_short_name, route_long_name, route_color FROM routes;";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Display table
    echo '<table id="table"><tbody>';
    // Create first row
    echo '<tr style="background: white; z-index: 2;" id="header">';
    echo '<td>Route name</td>';
    echo '<td style="border-top: 1px solid black; border-left: 1px solid black;">
        Current route schedule + stop list</td>';
    echo "</tr>";
    // Add remaining rows
    $i = 0;
    foreach ($routes as $route) {
        $routeColor = tint($route['route_color']);
        echo '<tr>';
        echo '<td style="border-top: 1px solid black; border-left: 1px solid black;
            background-color: #' . $routeColor . '">' . $route['route_long_name'] . '</td>';
        echo '<td style="border-bottom: 1px solid black; border-right: 1px solid black; background-color: #dedede">
            <a href="./routeschedule.php?a=' . $agency . '&r=' . $route['route_id'] . '">Route Schedule</a></td>';
        echo '<tr>';
    }
    echo "</tbody></table>";


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
</body>
</html>