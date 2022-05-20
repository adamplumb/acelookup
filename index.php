<?php

include_once 'util.php';

// Properties: https://github.com/ACEmulator/ACE/tree/master/Source/ACE.Entity/Enum/Properties

$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$creatureResults = array();
$craftingResults = array();
$weaponResults = array();

if ($name) {

    /**
     * 
     * Bool
     *  19 - Attackable
     * 
     * Int
     *  25 - Level
     */
    $creatureResults = searchCreatures($name);
    
    $statement = $dbh->prepare("select 
                                        weenie.class_Id id,
                                        wps.value name,
                                        weenie.type type,
                                        wpi.value itemType,
                                        weenie.class_Name code
                                    from weenie 
                                        join weenie_properties_string wps on (wps.object_Id = weenie.class_Id) 
                                        join weenie_properties_int wpi on (wpi.object_Id = weenie.class_Id)
                                    where
                                        wpi.type = 1
                                        and wps.type = 1
                                        and weenie.type in (" . implode(', ', CRAFTING_TYPES) . ")
                                        and (wps.value like ? or weenie.class_Name like ?)
                                    order by name asc");

    $statement->execute(array("%${name}%", "%${name}%"));

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $craftingResults[] = $row;
    }
    
    $statement = $dbh->prepare("select 
                                        weenie.class_Id id,
                                        wps.value name,
                                        weenie.type type,
                                        wpi.value itemType,
                                        weenie.class_Name code
                                    from weenie 
                                        join weenie_properties_string wps on (wps.object_Id = weenie.class_Id) 
                                        join weenie_properties_int wpi on (wpi.object_Id = weenie.class_Id)
                                    where
                                        wpi.type = 1
                                        and wps.type = 1
                                        and weenie.type in (" . implode(', ', WEAPON_WEENIE_TYPES) . ")
                                        and (wps.value like ? or weenie.class_Name like ?)
                                    order by name asc");

    $statement->execute(array("%${name}%", "%${name}%"));

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $weaponResults[] = $row;
    }
    
    if (count($craftingResults) == 1 && !$creatureResults && !$weaponResults) {
        header("Location: crafting.php?id={$craftingResults[0]['id']}\n");
        exit;
    }

    if (count($creatureResults) == 1 && !$craftingResults && !$weaponResults) {
        header("Location: mob.php?id={$creatureResults[0]['id']}\n");
        exit;
    }

    if (count($weaponResults) == 1 && !$craftingResults && !$creatureResults) {
        header("Location: weapons.php?id={$weaponResults[0]['id']}\n");
        exit;
    }
}
?>

<html>
<head>
    <title>ACE Mobs</title>
    <link rel="stylesheet" type="text/css" href="style.css?d=<?php echo $config->cacheBuster; ?>" media="screen" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style type="text/css">
    h3 {
        text-align: center;
    }
    </style>
</head>

<body>

<div class="lookup-form">
    <h1>ACE Lookup</h1>
    <p>Research creature weaknesses, attacks, drop/crafting items, and weapons</p>
    <form method="GET">
    <input type="text" name="name" value="<?php echo $name; ?>" size="30" placeholder="Search for creatures, crafting items, and weapons" />
    <input type="submit" value="Lookup" />
    </form>
</div>

<?php
if ($name) {
    if ($creatureResults) {
?>
<br />
<h3>Creatures</h3>
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
        foreach ($creatureResults as $row) {
            $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr<?php echo $rowClass; ?>>
        <td><?php echo $row['id']; ?></td>
        <td><a href="mob.php?id=<?php echo $row['id']; ?>"><?php echo $row['name']; ?></a></td>
        <td><?php echo CREATURE_TYPE[$row['type']]; ?></td>
        <td><?php echo $row['code']; ?></td>
        <td><?php echo $row['level']; ?></td>
    </tr>
<?php
            $index++;
        }
    }
?>
</tbody>
</table>

<?php
    if ($craftingResults) {
?>
<br />
<h3>Crafting Items</h3>
<table class="horizontal-table lookup-table" align="center">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Item Type</th>
        <th>Code</th>
    </tr>
</thead>
<tbody>
<?php
        $index = 0;
        foreach ($craftingResults as $row) {
            $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr<?php echo $rowClass; ?>>
        <td><?php echo $row['id']; ?></td>
        <td><a href="crafting.php?id=<?php echo $row['id']; ?>"><?php echo $row['name']; ?></a></td>
        <td><?php echo ITEM_TYPES[$row['itemType']]; ?></td>
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

    if ($weaponResults) {
?>
<br />
<h3>Weapons</h3>
<table class="horizontal-table lookup-table" align="center">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Item Type</th>
        <th>Code</th>
    </tr>
</thead>
<tbody>
<?php
        $passThruKeys = array('creatureId', 'attributeValue', 'damageRating', 'criticalDamageRating', 'powerLevel');
        $queryString = '';
        foreach ($passThruKeys as $key) {
            if (isset($_GET[$key])) {
                $queryString .= "&" . $key . "=" . $_GET[$key];
            }
        }

        $index = 0;
        foreach ($weaponResults as $row) {
            $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr<?php echo $rowClass; ?>>
        <td><?php echo $row['id']; ?></td>
        <td><a href="weapons.php?id=<?php echo $row['id']; ?><?php echo $queryString; ?>"><?php echo $row['name']; ?></a></td>
        <td><?php echo ITEM_TYPES[$row['itemType']]; ?></td>
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

include_once 'footer.php';
?>


</body>
</html>
