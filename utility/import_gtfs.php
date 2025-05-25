<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php

    echo "Hello world <br><br>";


    $agency = "mnr";
    $fileName = "calendar_dates";

    $dsn = "mysql:host=localhost;dbname=transitNYC_gtfs_" . $agency;
    $dbusername = "root";
    $dbpassword = "";

    // Create PDO connection to SQL database
    try {
        $pdo = new PDO($dsn, $dbusername, $dbpassword);
        //$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPION);
    } catch (PDOException $e) {
        echo $e;
    }
    
    // Read the file
    $path = "/Applications/XAMPP/xamppfiles/htdocs/TransitGTA-NY/gtfs/" . $agency . "/" . $fileName . ".txt";
    $file = read_csv($path);

    /* echo "<br>";
    var_dump($file);
    echo "<br>";*/

    // Create a table to store the file
    $query = "CREATE TABLE " . $fileName . "(";
    for ($i = 0; $i < count($file[0]); $i++) {
        // Check type of data (TINYINT, INT, BIGINT, DECIMAL, TEXT)
        $col = $file[0][$i];
        $val = $file[1][$i];
        $type = "TEXT";
        $num = intval($val);
        $num = intval($val);
        if ($val != "0" && $num."" != $val && floatval($val)."" != $val) {
            $type = "TEXT";
        } else if (str_contains($val, ".")) {
            $type = "DECIMAL";
        } else if (0 <= $num && $num < 255) {
            $type = "TINYINT";
        } else if (-2147483648 < $num && $num < 2147483647) {
            $type = "INT";
        } else if (-9223372036854775808 < $num && $num < 9223372036854775807) {
            $type = "BIGINT";
        } else {
            $type = "TEXT";
        }
        $query = $query . $col . " " . $type . ", ";
    }
    $query = substr($query, 0, -2) . ");";
    echo $query . "<br>";

    // Execute CREATE TABLE query
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $stmt = null;
    } catch (PDOException $e) {
        $code = $e->getCode();
        if ($code == "42S01") { // table already exists ==> empty the table
            $stmt = $pdo->prepare("DELETE FROM " . $fileName . ";");
            $stmt->execute();
            $stmt = null;
        } else {
            die("CREATE TABLE Query failed: #" . $e->getCode() . ", " . $e->getMessage());
        }
    }

    // Insert all values into the table
    for ($i = 1; $i < count($file); $i++) {
        // Set up columns
        $query = "INSERT INTO " . $fileName . " (";
        foreach ($file[0] as $col) {
            $query = $query . $col . ", ";
        }
        // Get values
        $query = substr($query, 0, -2) . ") VALUES (";
        for ($j = 0; $j < count($file[0]); $j++) {
            $col = $file[0][$j];
            $val = $file[$i][$j];
            if ($val != "0" && $num."" != $val && floatval($val)."" != $val) {
                $val = '"' . $val . '"';
            }
            $query = $query . $val . ", ";
        }
        $query = substr($query, 0, -2) . ");";

        // Execute INSERT query
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $stmt = null;
        } catch (PDOException $e) {
            die($query . " failed: #" . $e->getCode() . ", " . $e->getMessage());
        }
        //var_dump($stmt);
        //echo "<br>";
        //break;
    }

    $pdo = null;

    echo "Import successful!";


    // Reads a CSV file and stores it as a 2D array
    function read_csv ($file_path) {
        $rows = file($file_path);
        if (!$rows) {
            return [[]];
        }
        $table = [];
        for ($i = 0; $i < count($rows); $i++) {
            $rawRow = explode(",", $rows[$i]);
            $row = [];
            foreach ($rawRow as $item) {
                if (ord(substr($item, -2, 1)) == 13) {
                    $item = substr($item, 0, -2);
                } else if (ord(substr($item, -1, 1)) == 10) {
                    $item = substr($item, 0, -1);
                }
                if (substr($item, 0, 1) == '"' && substr($item, -1, 1) == '"') {
                    $item = substr($item, 1, -1);
                }
                $row[] = $item;
            }
            $table[] = $row;
        }
        return $table;
    }

    // Get i'th entry in column col from table t
    function get_csv (& $t, $i, $col) {
        $index = array_search($col, $t[0]);
        return $t[$i][$index];
    }

    ?>
</body>
</html>