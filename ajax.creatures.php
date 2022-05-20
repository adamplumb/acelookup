<?php

include_once 'util.php';

$name = $_GET['name'];

$creatures = searchCreatures($name);

header("Content-type", "text/json");

print json_encode($creatures);

?>
