<?php

include_once 'util.php';

// Properties: https://github.com/ACEmulator/ACE/tree/master/Source/ACE.Entity/Enum/Properties

$name = isset($_GET['name']) ? $_GET['name'] : '';
$results = array();

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
                                    wpi.value level
                                from weenie 
                                    join weenie_properties_string wps on (wps.object_Id = weenie.class_Id) 
                                    left join weenie_properties_bool wpb on (wpb.object_Id = weenie.class_Id and wpb.type = 19)
                                    join weenie_properties_int wpi on (wpi.object_id = weenie.class_Id)
                                where 
                                    weenie.type = 10
                                    and wps.type = 1
                                    and (wpb.value = 1 or wpb.value is null)
                                    and wpi.type = 25
                                    and (wps.value like ? or weenie.class_Name like ?)
                                order by level asc, id asc");

    $statement->execute(array("%${name}%", "%${name}%"));

    while ($row = $statement->fetch()) {
        $results[] = $row;
    }
    
    if (count($results) == 1) {
        header("Location: mob.php?id={$results[0]['id']}\n");
        exit;
    }
}
?>

<html>
<head>
    <title>ACE Mobs</title>
    <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
</head>

<body>

<h1>ACE Lookup</h1>
<form method="GET">
<input type="input" name="name" value="<?php echo $name; ?>" placeholder="Search" />
<input type="submit" value="Lookup" />
</form>

<?php
if ($name) {
?>
<table class="horizontal-table">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
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
}

include_once 'footer.php';
?>


</body>
</html>
