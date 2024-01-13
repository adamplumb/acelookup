<?php
ini_set('display_errors', 'on');
session_start();

include_once 'util.php';

$groups = json_decode(file_get_contents('titles.json'));
$numAvailableTitles = 0;
foreach ($groups as $group) {
    $numAvailableTitles += count($group->titles);
}
$serverOptions = json_decode(file_get_contents('servers.json'));

$server = isset($_GET['server']) ? $_GET['server'] : null;;
$player = isset($_GET['player']) ? trim($_GET['player']) : null;
$charTitles = array();
$numTitlesFound = 0;

if ($server && $player) {
    // Hack to workaround php 5.6 ssl issue 
    // https://stackoverflow.com/questions/26148701/file-get-contents-ssl-operation-failed-with-code-1-failed-to-enable-crypto
    $arrContextOptions = array(
        "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
        ),
    );

    $treeStatsUrl = 'https://treestats.net/' . $server . '/' . str_replace(' ', '%20', $player);
    $treeStatsContents = @file_get_contents($treeStatsUrl, false, stream_context_create($arrContextOptions));
    $treeStatsLines = explode("\n", $treeStatsContents);
    foreach ($treeStatsLines as $index => $line) {
        if (string_contains($line, "<span class='title_list_item'>") || string_contains($line, "<span class='current_title title_list_item'>")) {
            if (preg_match("/\<a.*\>(.*?)\<\/a\>/", $treeStatsLines[$index +1], $matches)) {
                $charTitles[html_entity_decode($matches[1])] = true;
                $numTitlesFound++;
            }
        }
    }
}

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

<center>
<form method="GET">
<select name="server">
    <option value="">Server</option>
<?php
foreach ($serverOptions as $serverOption) {
?>
    <option<?php echo $serverOption == $server ? ' selected' : ''; ?>><?php echo $serverOption; ?></option>
<?php
}
?>
</select>
<input type="text" name="player" value="<?php echo $player; ?>" size="20" placeholder="Character Name" />
<input type="submit" value="Treestats Lookup" />
<?php
if ($player && $server) {
    if ($numTitlesFound > 0) {
        echo "${numTitlesFound} of ${numAvailableTitles} titles acquired\n";
    } else {
        echo "Player not found\n";
    }
}
?>
</form>
</center>

<?php
foreach ($groups as $name => $obj) {
?>
<h3><?php echo $name; ?></h3>
<table class="horizontal-table lookup-table" align="center">
<thead>
    <tr>
        <?php if ($player && $server) { ?><th>Acquired</th><?php } ?>
        <th>Title</th>
        <th>Quest</th>
        <th>Required Level</th>
        <th>Rec. Level</th>
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
        
        $titleMatch = str_replace(' (Title)', '', $titleObj->title);
        
        $notes = array();
        if ($titleObj->status == 'Unverified') {
            $notes[] = "Unverified";
        }
        if ($titleObj->seasonal) {
            $notes[] = "Seasonal";
        }

?>
<tr<?php echo $rowClass; ?>>
<?php
        if ($player && $server) {
            echo "<td>\n";
            if (isset($charTitles[$titleMatch])) {
                echo 'YES';
            }
            echo "</td>\n";
        }
?>
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

<?php include_once 'footer.php'; ?>

</body>
</html>
