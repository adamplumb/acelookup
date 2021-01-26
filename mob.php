<?php
ini_set('display_errors', 'on');

include_once 'util.php';


$classId = isset($_GET['id']) ? $_GET['id'] : '';
if (!$classId) {
    print("No id provided\n");
    exit;
}

$mob = getCreature($classId);
$bodyArmor = getBodyArmor($classId);
$floats = getFloats($classId);
$attributes = getAttributes($classId);
$attributes2nd = getAttributes2nd($classId);
$skills = getSkills($classId, $attributes);
$createList = getCreateList($classId);

?>

<html>
<head>
    <title>ACE Mob: <?php echo $mob['name']; ?></title>
    <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
</head>
<body>
    
<form method="GET" action="index.php">
<input type="input" name="name" value="<?php echo $mob['name']; ?>" placeholder="Search" />
<input type="submit" value="Lookup" />
</form>

<h1><?php echo $mob['name']; ?></h1>

<table class="vertical-table">
<tr>
    <th>ID</th>
    <td><?php echo $mob['id']; ?></td>
</tr>
<tr>
    <th>Name</th>
    <td><?php echo $mob['name']; ?></td>
</tr>
<tr>
    <th>Code</th>
    <td><?php echo $mob['code']; ?></td>
</tr>
<tr>
    <th>Level</th>
    <td><?php echo $mob['level']; ?></td>
</tr>
<tr>
    <th>Links</th>
    <td>
            <a href="http://acpedia.org/<?php echo str_replace(' ', '_', $mob['name']); ?>" target="acpedia">ACPedia</a>
    </td>
</tr>
</table>

<h3>Physical Armor</h3>

<table class="horizontal-table">
<thead>
    <tr>
        <th>Body Part</th>
        <th>Slash</th>
        <th>Bludgeon</th>
        <th>Pierce</th>
        <th>Cold</th>
        <th>Fire</th>
        <th>Acid</th>
        <th>Electric</th>
        <th>Nether</th>
    </tr>
</thead>
<tbody>
<?php
$index = 0;
foreach ($bodyArmor as $key => $bodyPartArmor) {
    $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr<?php echo $rowClass; ?>>
        <td class="strong"><?php echo $key; ?></td>
        <td><?php echo round($bodyPartArmor['base_Armor'] * $floats[PropertyFloat::ArmorModVsSlash]); ?></td>
        <td><?php echo round($bodyPartArmor['armor_Vs_Bludgeon'] * $floats[PropertyFloat::ArmorModVsBludgeon]); ?></td>
        <td><?php echo round($bodyPartArmor['armor_Vs_Pierce'], $floats[PropertyFloat::ArmorModVsPierce]); ?></td>
        <td><?php echo round($bodyPartArmor['armor_Vs_Cold'] * $floats[PropertyFloat::ArmorModVsCold]); ?></td>
        <td><?php echo round($bodyPartArmor['armor_Vs_Fire'] * $floats[PropertyFloat::ArmorModVsFire]); ?></td>
        <td><?php echo round($bodyPartArmor['armor_Vs_Acid'] * $floats[PropertyFloat::ArmorModVsAcid]); ?></td>
        <td><?php echo round($bodyPartArmor['armor_Vs_Electric'] * $floats[PropertyFloat::ArmorModVsElectric]); ?></td>
        <td><?php echo round($bodyPartArmor['armor_Vs_Nether'] * (isset($floats[PropertyFloat::ArmorModVsNether]) ? $floats[PropertyFloat::ArmorModVsNether] : 0)); ?></td>
    </tr>
<?php
    $index++;
}
?>
</tbody>
</table>

<h3>Attributes</h3>
<table class="vertical-table">
<tbody>
    <tr>
        <th>Strength</th>
        <td><?php echo $attributes[PropertyAttribute::Strength]; ?></td>
    </tr>
    <tr>
        <th>Endurance</th>
        <td><?php echo $attributes[PropertyAttribute::Endurance]; ?></td>
    </tr>
    <tr>
        <th>Coordination</th>
        <td><?php echo $attributes[PropertyAttribute::Coordination]; ?></td>
    </tr>
    <tr>
        <th>Quickness</th>
        <td><?php echo $attributes[PropertyAttribute::Quickness]; ?></td>
    </tr>
    <tr>
        <th>Focus</th>
        <td><?php echo $attributes[PropertyAttribute::Focus]; ?></td>
    </tr>
    <tr>
        <th>Self</th>
        <td><?php echo $attributes[PropertyAttribute::Self]; ?></td>
    </tr>
    <tr class="alt-darker">
        <th>Health</th>
        <td><?php echo $attributes2nd[PropertyAttribute2nd::MaxHealth]; ?></td>
    </tr>
    <tr class="alt-darker">
        <th>Stamina</th>
        <td><?php echo $attributes2nd[PropertyAttribute2nd::MaxStamina]; ?></td>
    </tr>
    <tr class="alt-darker">
        <th>Mana</th>
        <td><?php echo $attributes2nd[PropertyAttribute2nd::MaxMana]; ?></td>
    </tr>
</tbody>
</table>

<h3>Skills</h3>
<table class="vertical-table">
<tbody>
<?php
foreach ($skills as $typeNumber => $value) {
    $skillName = SKILLS_LIST[$typeNumber];
?>
    <tr>
        <th><?php echo $skillName; ?></th>
        <td><?php echo $value; ?></td>
    </tr>
<?php
}
?>
</table>


<h3>Create List</h3>
<table class="horizontal-table">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Code</th>
        <th>Drop Chance</th>
        <th>Links</th>
    </tr>
</thead>
<tbody>
<?php
$index = 0;
foreach ($createList as $row) {
    $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo $row['name']; ?></td>
        <td><?php echo $row['code']; ?></td>
        <td><?php echo round(100 * $row['chance']); ?>%</td>
        <td>
            <a href="http://acpedia.org/<?php echo $row['name']; ?>" target="acpedia">ACPedia</a>
        </td>
    </tr>
<?php
    $index++;
}
?>
</tbody>
</table>

</body>
</html>
