<?php
ini_set('display_errors', 'on');
session_start();

include_once 'util.php';

$groups = json_decode(file_get_contents('titles.json'));
/*

*/

?>

<html>
<head>
    <title>ACE Titles</title>
    <link rel="stylesheet" type="text/css" href="style.css?d=<?php echo $config->cacheBuster; ?>" media="screen" />
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style type="text/css">
    h1, h3 {
        text-align: center;
    }
    </style>
</head>
<body>
    
<h1>Titles</h1>
<?php
foreach ($groups as $name => $obj) {
?>
<h3><?php echo $name; ?></h3>
<table class="horizontal-table lookup-table" align="center">
<thead>
    <tr>
        <th>Title</th>
        <th>Quest</th>
        <th>Required Level</th>
        <th>Recommended Level</th>
        <th>XP</th>
        <th>Type</th>
        <th>Notes</th>
    </tr>
</thead>
<tbody>
<?php

    foreach ($obj->titles as $index => $titleObj) {
        $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
        $htmlTitle = str_replace(' ', '_', $titleObj->title);
        $htmlQuest = str_replace(' ', '_', $titleObj->quest);
        
        $notes = array();
        if ($titleObj->status == 'Unverified') {
            $notes[] = "Unverified";
        }
        if ($titleObj->seasonal) {
            $notes[] = "Seasonal";
        }

?>
<tr<?php echo $rowClass; ?>>
    <td><a href="http://acportalstorm.com/wiki/<?php echo $htmlTitle; ?>" target="_new"><?php echo $titleObj->title; ?></a></td>
    <td><a href="http://acportalstorm.com/wiki/<?php echo $htmlQuest; ?>" target="_new"><?php echo $titleObj->quest; ?></a></td>
    <td><?php echo $titleObj->reqLevel; ?></td>
    <td><?php echo $titleObj->recLvlMin; ?></td>
    <td><?php echo $titleObj->xp; ?></td>
    <td><?php echo $titleObj->type; ?></td>
    <td><?php echo implode("<br />", $notes); ?></td>
</tr>
<?php
    }

?>
</tbody>
</table>
<?php
}
?>

</body>
</html>
