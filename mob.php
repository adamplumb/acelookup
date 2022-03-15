<?php
ini_set('display_errors', 'on');

include_once 'util.php';


$classId = isset($_GET['id']) ? $_GET['id'] : '';
if (!$classId) {
    print("No id provided\n");
    exit;
}

$mob = getCreature($classId);
if (!$mob) {
    print("Not a valid creature\n");
    exit;
}

$bodyArmor = getBodyArmor($classId);
$floats = getFloats($classId);
$ints = getInts($classId);
$bools = getBools($classId);
$dataIds = getDataIds($classId);
$attributes = getAttributes($classId);
$attributes2nd = getAttributes2nd($classId);
$skills = getSkills($classId, $attributes);
$createList = getCreateList($classId);
$spellBook = getSpellBook($classId);
$wieldedItems = getWieldedItems($classId);
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

$specialProperties = getSpecialProperties($floats, $ints, $bools);
$effectiveArmor = getEffectiveArmor($bodyArmor, $damageTypes, $floats);
$magicResistances = getMagicResistances($damageTypes, $floats);
$regenRates = getRegenRates($floats);

$maxRGB = 200;
$minRGB = 20;

$treasureDeath = array('tier' => 'Not Set');
if (isset($dataIds[PropertyDataId::DeathTreasureType])) {
    $treasureDeath = getTreasureDeath($dataIds[PropertyDataId::DeathTreasureType]);
}
?>

<html>
<head>
    <title>ACE Mob: <?php echo $mob['name']; ?> (<?php echo $mob['id']; ?>)</title>
    <link rel="stylesheet" type="text/css" href="style.css?d=<?php echo $config->cacheBuster; ?>" media="screen" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    
<form method="GET" action="index.php">
<input type="text" name="name" value="<?php echo $name; ?>" size="30" placeholder="Search for creatures or crafting items" />
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
    <th>Creature Type</th>
    <td><?php echo CREATURE_TYPE[$ints[PropertyInt::CreatureType]]; ?></td>
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
    <th>Tier</th>
    <td><?php echo $treasureDeath['tier']; ?></td>
</tr>
<tr>
    <th>XP</th>
    <td><?php echo $ints[PropertyInt::XpOverride]; ?></td>
</tr>
<tr>
    <th>Links</th>
    <td>
            <a href="http://acpedia.org/<?php echo str_replace(' ', '_', $mob['name']); ?>" target="acpedia">ACPedia</a>
            /
            <a href="http://ac.yotesfan.com/weenies/items/<?php echo $mob['id']; ?>" target="yotesfan">Yotesfan</a>
    </td>
</tr>
</table>

<br />
<h3>Physical Armor Damage Reduction</h3>
<p class="note">The creature's physical armor reduces your damage by this amount depending on the body part and damage type.  The formula used here is <i>ArmorMod * ResistModByType</i> where <i>ArmorMod = 66.67 / 66.67 + (BaseArmor * ArmorModVsType)</i>.  For example, if you do 100 damage before mitigation, and damage reduction is 17%, then you will end up doing 17 damage.  This doesn't include your attack weapon or damage and damage is reduced further if the creature is wielding a shield.  A value over 100% means damage of that type is increased.</p>

<div class="armor-table-container">
<table class="horizontal-table armor-table">
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

$armorRange = getArmorRange($effectiveArmor['bodyParts'], 'calculated');
$averageArmorRange = getArmorRange($effectiveArmor['average'], 'calculated');
$index = 0;
foreach ($effectiveArmor['bodyParts'] as $bodyPart => $armorByDamageType) {
?>
    <tr class="alt">
        <td width="100" class="strong"><?php echo $bodyPart; ?></td>
<?php
    foreach ($damageTypes as $damageType => $damageProps) {
        $val = $armorByDamageType[$damageType]['calculated'];
        $percentBetween = percentageBetween($val, $armorRange['max'], $armorRange['min']);        
        $rgb = 255 - round((($maxRGB - $minRGB) * $percentBetween) + $minRGB);
        $rgbLabel = "rgb(255, ${rgb}, ${rgb})";
        
        $title = "Base: {$armorByDamageType[$damageType]['baseArmor']}, ArmorModVs{$damageType}: {$armorByDamageType[$damageType]['armorMod']}, Resist{$damageType}: {$armorByDamageType[$damageType]['resist']}";
?>
        <td style="background-color: <?php echo $rgbLabel; ?>" title="<?php echo $title; ?>"><?php echo $val; ?>%</td>
<?php
    }
?>
    </tr>
<?php
    $index++;
}
?>
</tbody>
<thead>
    <tr>
        <th width="100">&nbsp;</th>
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
        <td class="strong">Average</td>
<?php
    foreach ($damageTypes as $damageType => $damageProps) {
        $armor = $effectiveArmor['average']['Average'][$damageType];
        $val = $armor['calculated'];
        $percentBetween = percentageBetween($val, $averageArmorRange['max'], $averageArmorRange['min']);        
        $rgb = 255 - round((($maxRGB - $minRGB) * $percentBetween) + $minRGB);
        $rgbLabel = "rgb(255, ${rgb}, ${rgb})";
        $title = "Base: {$armor['baseArmor']}, ArmorModVs{$damageType}: {$armor['armorMod']}, Resist{$damageType}: {$armor['resist']}";
?>
        <td style="background-color: <?php echo $rgbLabel; ?>" title="<?php echo $title; ?>"><?php echo $val; ?>%</td>
<?php
    }
?>
    </tr>
</tbody>
</table>
</div>

<br />
<h3>Effective Magical Damage</h3>
<p class="note">This is how much effective magical damage you will do against this monster after innate resistances.  A higher value means you will do more relative damage.  A missing value means the resistance value is missing from the ACE World database, and seems to default to 100%</p>

<div class="magic-damage-container">
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
</div>


<br />
<h3>Drain Resistance</h3>
<p class="note">This shows how effective drains will be against the creature. A lower value means the drain has less effect on the creature, and thus on how much of that vital you receive.</p>

<div class="magic-damage-container">
<table class="horizontal-table">
<thead>
    <tr>
        <th>Drain Health</th>
        <th>Drain Stamina</th>
        <th>Drain Mana</th>
    </tr>
</thead>
<tbody>
    <tr class="alt">
        <td><?php echo round($floats[PropertyFloat::ResistHealthDrain] * 100, 2); ?>%</td>
        <td><?php echo round($floats[PropertyFloat::ResistStaminaDrain] * 100, 2); ?>%</td>
        <td><?php echo round($floats[PropertyFloat::ResistManaDrain] * 100, 2); ?>%</td>
    </tr>
</tbody>
</table>
</div>

<br />
<h3>Attributes</h3>
<table class="vertical-table">
<tbody>
    <tr>
        <th>Strength</th>
        <td><?php echo $attributes[PropertyAttribute::Strength]; ?></td>
        <td class="transparent">&nbsp;</td>
        <td class="transparent">&nbsp;</td>
    </tr>
    <tr>
        <th>Endurance</th>
        <td><?php echo $attributes[PropertyAttribute::Endurance]; ?></td>
        <td class="transparent">&nbsp;</td>
        <td class="transparent">&nbsp;</td>
    </tr>
    <tr>
        <th>Coordination</th>
        <td><?php echo $attributes[PropertyAttribute::Coordination]; ?></td>
        <td class="transparent">&nbsp;</td>
        <td class="transparent">&nbsp;</td>
    </tr>
    <tr>
        <th>Quickness</th>
        <td><?php echo $attributes[PropertyAttribute::Quickness]; ?></td>
        <td class="transparent">&nbsp;</td>
        <td class="transparent">&nbsp;</td>
    </tr>
    <tr>
        <th>Focus</th>
        <td><?php echo $attributes[PropertyAttribute::Focus]; ?></td>
        <td class="transparent">&nbsp;</td>
        <td class="transparent">&nbsp;</td>
    </tr>
    <tr>
        <th>Self</th>
        <td><?php echo $attributes[PropertyAttribute::Self]; ?></td>
        <td class="transparent">&nbsp;</td>
        <td class="transparent">&nbsp;</td>
    </tr>
    <tr class="alt-darker">
        <th>Health</th>
        <td><?php echo $attributes2nd[PropertyAttribute2nd::MaxHealth]; ?></td>
        <th>Regen/sec</th>
        <td><?php echo $regenRates['health']; ?></td>
    </tr>
    <tr class="alt-darker">
        <th>Stamina</th>
        <td><?php echo $attributes2nd[PropertyAttribute2nd::MaxStamina]; ?></td>
        <th>Regen/sec</th>
        <td><?php echo $regenRates['stamina']; ?></td>
    </tr>
    <tr class="alt-darker">
        <th>Mana</th>
        <td><?php echo $attributes2nd[PropertyAttribute2nd::MaxMana]; ?></td>
        <th>Regen/sec</th>
        <td><?php echo $regenRates['mana']; ?></td>
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
<h3>Spell Book</h3>
<p class="note">This is the set of spells the creature has a chance to cast during each cast attempt.</p>

<?php
if ($spellBook) {
?>
<table class="horizontal-table">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Probability</th>
        <th>Links</th>
    </tr>
</thead>
<tbody>
<?php
    foreach ($spellBook as $spell) {
?>
    <tr>
        <td><?php echo $spell['id']; ?></td>
        <td><?php echo $spell['name']; ?></td>
        <td><?php echo $spell['probability']; ?>%</td>
        <td>
            <a href="http://acpedia.org/<?php echo str_replace(' ', '_', $spell['name']); ?>" target="acpedia">ACPedia</a>
            /
            <a href="http://ac.yotesfan.com/spells/spell/<?php echo $spell['id']; ?>" target="yotesfan">Yotesfan</a>
        </td>
    </tr>
<?php
    }
?>
</tbody>
</table>
<?php
} else {
?>
    <p class="no-item-note"><i>No spells found</i></p>
<?php    
}
?>

<br />
<h3>Attacks from Body Parts</h3>
<p class="note">If the creature is using body parts to attack, this is what they may use.  If there is "wielded treasure" below then they may use those weapons to attack instead.</p>

<table class="horizontal-table">
<thead>
    <tr>
        <th>Body Part</th>
        <th>Damage Type</th>
        <th>Amount</th>
    </tr>
</thead>
<tbody>
<?php
    foreach ($bodyArmor as $bodyPart => $row) {
        $damageTypeId = $row['d_Type'];
        $damageTypeLabel = getDamageTypeLabel($damageTypeId);
        $damageValue = $row['d_Val'];
        $minDamage = getMinDamage($damageValue, $row['d_Var']);
        
        if (!$damageValue) {
            continue;
        }
?>
    <tr>
        <td class="strong"><?php echo $bodyPart; ?></td>
        <td><?php echo $damageValue > 0 ? $damageTypeLabel : ''; ?></td>
        <td><?php echo $damageValue > 0 ? "${minDamage} - {$damageValue}" : ''; ?></td>
    </tr>
<?php
    }
?>
</tbody>
</table>

<br />
<h3>Special Properties</h3>
<?php
if ($specialProperties) {
?>
<p class="note">These properties being present means there is something noteworthy about this creature you should pay attention to.</p>

<div class="magic-damage-container">
<table class="horizontal-table">
<thead>
    <tr>
        <th>Property</th>
        <th>Description</th>
    </tr>
</thead>
<tbody>
<?php
    foreach ($specialProperties as $prop) {
?>
    <tr class="alt">
        <td><b><?php echo $prop['name']; ?></b></td>
        <td><?php echo $prop['description']; ?></td>
    </tr>
<?php
    }
?>
</tbody>
</table>
</div>
<?php
} else {
?>
    <p class="no-item-note"><i>No special properties found</i></p>
<?php    
}
?>


<br />
<h3>Wielded Treasure / Weapons / Armor</h3>
<p class="note">This is the probability that a creature will have these wielded items.</p>

<?php
if ($wieldedItems) {
?>
<table class="horizontal-table">
<thead>
    <tr>
        <th>ID</th>
        <th>Code</th>
        <th>Name</th>
        <th>Probability</th>
        <th>Damage Type</th>
        <th>Damage</th>
        <th>Links</th>
    </tr>
</thead>
<tbody>
<?php
    foreach ($wieldedItems as $item) {
?>
    <tr>
        <td><?php echo $item['id']; ?></td>
        <td><?php echo $item['code']; ?></td>
        <td><?php echo $item['name']; ?></td>
        <td><?php echo round(100 * $item['probability'], 2); ?>%</td>
        <td><?php echo $item['damageType']; ?></td>
        <td><?php echo $item['minDamage']; ?> - <?php echo $item['damage']; ?></td>
        <td>
            <a href="http://acpedia.org/<?php echo str_replace(' ', '_', $item['name']); ?>" target="acpedia">ACPedia</a>
            /
            <a href="http://ac.yotesfan.com/weenies/items/<?php echo $item['id']; ?>" target="yotesfan">Yotesfan</a>
        </td>
    </tr>
<?php
    }
?>
</tbody>
</table>
<?php
} else {
?>
    <p class="no-item-note"><i>No wielded items found</i></p>
<?php    
}
?>


<br />
<h3>Drop Items</h3>
<p class="note">These are the special items this creature may possibly drop on death, in addition to regular loot.</p>

<?php
if (count($createList) > 0) {
?>
<table class="horizontal-table drop-items-table">
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
        <td>
            <a href="crafting.php?id=<?php echo $row['id']; ?>">
                <?php echo $row['name']; ?>
            </a>
        </td>
        <td><?php echo $row['code']; ?></td>
        <td><?php echo round(100 * $row['chance'], 1); ?>%</td>
        <td>
            <a href="http://acpedia.org/<?php echo $row['name']; ?>" target="acpedia">ACPedia</a>
            /
            <a href="http://ac.yotesfan.com/weenies/items/<?php echo $row['id']; ?>" target="yotesfan">Yotesfan</a>
        </td>
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
    <p> class="no-item-note"<i>No drop items found</i></p>
<?php    
}
?>

<?php
include_once 'footer.php';
?>

</body>
</html>
