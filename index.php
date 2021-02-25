<?php

include_once 'util.php';

// Properties: https://github.com/ACEmulator/ACE/tree/master/Source/ACE.Entity/Enum/Properties

$name = isset($_GET['name']) ? $_GET['name'] : '';
$resultsByType = array();

if ($name) {

    /**
     * 
     * Bool
     *  19 - Attackable
     * 
     * Int
     *  25 - Level
     */
    $statement = $dbh->prepare("select 
                                    weenie.class_Id id,
                                    wps.value name,
                                    weenie.class_Name code,
                                    wpi.value level,
                                    wpiType.value creatureType,
                                    weenie.type type
                                from weenie 
                                    join weenie_properties_string wps on (wps.object_Id = weenie.class_Id and wps.type = 1) 
                                    left join weenie_properties_bool wpb on (wpb.object_Id = weenie.class_Id and wpb.type = 19)
                                    left join weenie_properties_int wpi on (wpi.object_id = weenie.class_Id and wpi.type = 25)
                                    left join weenie_properties_int wpiType on (wpiType.object_Id = weenie.class_Id and wpiType.type = 2)
                                where 
                                    (wps.value like ? or weenie.class_Name like ?)
                                order by level asc, name asc");

    $statement->execute(array("%${name}%", "%${name}%"));

    while ($row = $statement->fetch()) {
        $type = $row['type'];
        if (!isset($resultsByType[$type])) {
            $resultsByType[$type] = array();
        }

        $resultsByType[$type][] = $row;
    }
    
    $keys = array_keys($resultsByType);
    $numKeys = count($keys);
    
    if ($numKeys == 1 && count($resultsByType[$keys[0]]) == 1) {
        header("Location: mob.php?id={$resultsByType[$keys[0]][0]['id']}\n");
        exit;
    }
}

?>

<html>
<head>
    <title>ACE Mobs</title>
    <link rel="stylesheet" type="text/css" href="style.css?d=<?php echo $config->cacheBuster; ?>" media="screen" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>

<div class="lookup-form">
    <h1>ACE Lookup</h1>
    <p>Research creature weaknesses, attacks, drop items, and more</p>
    <form method="GET">
    <input type="text" name="name" value="<?php echo $name; ?>" placeholder="Search" />
    <input type="submit" value="Lookup" />
    </form>
</div>
<br />

<?php
if ($name) {
    foreach ($resultsByType as $type => $results) {
        if ($type == 10 || $type == 15) {
?>
<h3>Creature</h3>
<table class="horizontal-table lookup-table" align="center">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Type</th>
        <th>Code</th>
        <th>Level</th>
    </tr>
</thead>
<tbody>
<?php
            $index = 0;
            foreach ($results as $row) {
                $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr<?php echo $rowClass; ?>>
        <td><?php echo $row['id']; ?></td>
        <td><a href="mob.php?id=<?php echo $row['id']; ?>"><?php echo $row['name']; ?></a></td>
        <td><?php echo CREATURE_TYPE[$row['creatureType']]; ?></td>
        <td><?php echo $row['code']; ?></td>
        <td><?php echo $row['level']; ?></td>
    </tr>
<?php
                $index++;
            }
?>
</tbody>
</table>
<?php
        } else {
?>
<h3><?php echo WEENIE_TYPE[$type]; ?> </h3>
<table class="horizontal-table lookup-table" align="center">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Code</th>
    </tr>
</thead>
<tbody>
<?php
            $index = 0;
            foreach ($results as $row) {
                $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr<?php echo $rowClass; ?>>
        <td><?php echo $row['id']; ?></td>
        <td><a href="mob.php?id=<?php echo $row['id']; ?>"><?php echo $row['name']; ?></a></td>
        <td><?php echo $row['code']; ?></td>
    </tr>
<?php
                $index++;
            }
?>
</tbody>
</table>
<?php
        }
    }
}

include_once 'footer.php';
?>


</body>
</html>
