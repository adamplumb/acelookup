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
$damageTypes = array(
    'Slash' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsSlash,
        'ResistProperty' => PropertyFloat::ResistSlash
    ), 
    'Bludgeon' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsBludgeon,
        'ResistProperty' => PropertyFloat::ResistBludgeon 
    ), 
    'Pierce' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsPierce,
        'ResistProperty' => PropertyFloat::ResistPierce 
    ), 
    'Cold' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsCold,
        'ResistProperty' => PropertyFloat::ResistCold 
    ), 
    'Fire' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsFire,
        'ResistProperty' => PropertyFloat::ResistFire 
    ), 
    'Acid' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsAcid,
        'ResistProperty' => PropertyFloat::ResistAcid 
    ), 
    'Electric' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsElectric,
        'ResistProperty' => PropertyFloat::ResistElectric 
    ), 
    'Nether' => array(
        'ArmorModProperty' => PropertyFloat::ArmorModVsNether,
        'ResistProperty' => PropertyFloat::ResistNether
    )
);


$effectiveArmor = getEffectiveArmor($bodyArmor, $damageTypes, $floats);
$magicResistances = getMagicResistances($damageTypes, $floats);

$maxRGB = 200;
$minRGB = 20;

?>

<html>
<head>
    <title>ACE Mob: <?php echo $mob['name']; ?> (<?php echo $mob['id']; ?>)</title>
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

<br />
<h3>Effective Physical Armor</h3>
<p class="note">The lower the value, the weaker to that damage type.  The formula used here is <i>BaseArmor * ArmorModVsType / ResistModByType</i></p>

<table class="horizontal-table">
<thead>
    <tr>
        <th>Body Part</th>
<?php
foreach ($damageTypes as $damageType => $damageProps) {
?>
        <th><?php echo $damageType; ?></th>
<?php
}
?>
    </tr>
</thead>
<tbody>
<?php

$armorRange = getArmorRange($effectiveArmor, 'calculated');
$index = 0;
foreach ($effectiveArmor as $bodyPart => $armorByDamageType) {
?>
    <tr class="alt">
        <td class="strong"><?php echo $bodyPart; ?></td>
<?php
    foreach ($damageTypes as $damageType => $damageProps) {
        $val = $armorByDamageType[$damageType]['calculated'];
        $percentBetween = percentageBetween($val, $armorRange['min'], $armorRange['max']);
        
        $rgb = 255 - round((($maxRGB - $minRGB) * $percentBetween) + $minRGB);
        $rgbLabel = "rgb(255, ${rgb}, ${rgb})";
        
        $title = "Base: {$armorByDamageType[$damageType]['baseArmor']}, ArmorModByType: {$armorByDamageType[$damageType]['armorMod']}, ResistMod: {$armorByDamageType[$damageType]['resist']}";
?>
        <td style="background-color: <?php echo $rgbLabel; ?>" title="<?php echo $title; ?>"><?php echo $val; ?></td>
<?php
    }
?>
    </tr>
<?php
    $index++;
}
?>
</tbody>
</table>

<br />
<h3>Effective Magical Resistance</h3>
<p class="note">This is how much effective magical damage you will do against this monster after innate resistances.  A missing value means the resistance value is missing from the ACE World database, and seems to default to 100%</p>

<table class="horizontal-table">
<thead>
    <tr>
<?php
foreach ($damageTypes as $damageType => $damageProps) {
?>
        <th><?php echo $damageType; ?></th>
<?php


}
?>
    </tr>
</thead>
<tbody>
    <tr class="alt">
<?php
    $minResist = min(array_values($magicResistances));
    $maxResist = max(array_values($magicResistances));
    foreach ($damageTypes as $damageType => $a) {
        $resistance = isset($magicResistances[$damageType]) ? $magicResistances[$damageType] : null;
        
        if ($resistance) {
            $percentBetween = percentageBetween($resistance, $minResist, $maxResist);
            
            $rgb = round((($maxRGB - $minRGB) * $percentBetween) + $minRGB);
            $rgbLabel = "rgb(255, ${rgb}, ${rgb})";
?>
        <td style="background-color: <?php echo $rgbLabel; ?>"><?php echo ($resistance ? ($resistance * 100) . '%' : ''); ?></td>
<?php
        } else {
?>
        <td>Missing</td>
<?php
        }
    }
?>
    </tr>
</tbody>
</table>

<br />
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

<br />
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

<br />
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

<?php
include_once 'footer.php';
?>

</body>
</html>
