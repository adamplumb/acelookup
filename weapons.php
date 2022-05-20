<?php
ini_set('display_errors', 'on');

include_once 'util.php';

$weaponId = $_GET['id'];
if (!$weaponId) {
    print("No id provided\n");
    exit;
}

$weapon = getWeapon($weaponId);
if (!$weapon) {
    print("Not a valid weapon\n");
    exit;
}


function getDamageObj($damage, $variance, $mod) {
    $moddedDamage = round($damage * $mod, 2);
    $minModdedDamage = getMinDamage($moddedDamage, $variance);

    return array(
        'max' => $moddedDamage,
        'min' => $minModdedDamage,
        'avg' => round(($minModdedDamage + $moddedDamage) / 2)
    );
}

function getWeaponModifierPercentage($val) {
    return round(100 * ($val - 1)) . '%';
}

function getPercentage($val) {
    return (100 * $val) . '%';
}

function getRoundedPercentage($val) {
    return round(100 * $val) . '%';
}

$attributeValue = isset($_GET['attributeValue']) ? $_GET['attributeValue'] : 200;

$floats = getFloats($weaponId);
$ints = getInts($weaponId);
$bools = getBools($weaponId);
$strings = getStrings($weaponId);
$spells = getSpellBook($weaponId);
/*
print_r($weapon);
print "floats\n";
print_r($floats);
print "ints\n";
print_r($ints);
print "bools\n";
print_r($bools);
print "strings\n";
print_r($strings);
print "spells\n";
print_r($spells);
*/
$spellEffects = array();
$spellEffects[PropertyFloat::WeaponAuraOffense] = 0;
$spellEffects[PropertyFloat::WeaponAuraDefense] = 0;
$spellEffects[PropertyInt::WeaponAuraDamage] = 0;
$spellEffects[PropertyInt::WeaponAuraSpeed] = 0;
foreach ($spells as $spell) {
    $keys = array_keys($spellEffects);
    foreach ($keys as $key) {
        if ($spell['statModKey'] == $key) {
            $spellEffects[$key] += $spell['statModVal'];
        }
    }
}

/*
print "Spell effects\n";
print_r($spellEffects);
*/

$description = null;
if (isset($strings[PropertyString::ShortDesc])) {
    $description = $strings[PropertyString::ShortDesc];
} else if (isset($strings[PropertyString::LongDesc])) {
    $description = $strings[PropertyString::LongDesc];
}

$damage = $ints[PropertyInt::Damage];
$variance = $floats[PropertyFloat::DamageVariance];
$minDamage = getMinDamage($damage, $variance);
$avgDamage = round(($damage + $minDamage) / 2);
$baseDamageType = DAMAGE_TYPE_INT[$ints[PropertyInt::DamageType]];
$damageType = $baseDamageType;
if ($damageType == 'Slash/Pierce') {
    $damageType = 'Slash';
}
$speed = $ints[PropertyInt::WeaponTime];
$weaponOffense = isset($floats[PropertyFloat::WeaponOffense]) ? $floats[PropertyFloat::WeaponOffense] : 1;
$weaponDefense = isset($floats[PropertyFloat::WeaponDefense]) ? $floats[PropertyFloat::WeaponDefense] : 1;
$unenchantable = isset($ints[PropertyInt::ResistMagic]) && $ints[PropertyInt::ResistMagic] == 9999;
$hasArmorCleaving = isset($floats[PropertyFloat::IgnoreArmor]) ? true : false;
$weaponType = isset($ints[PropertyInt::WeaponType]) ? WEAPON_TYPES[$ints[PropertyInt::WeaponType]] : null;

$imbuedEffectValue = isset($ints[PropertyInt::ImbuedEffect]) ? abs($ints[PropertyInt::ImbuedEffect]) : '';
$imbuedEffect = isset(IMBUED_EFFECTS[$imbuedEffectValue]) ? IMBUED_EFFECTS[$imbuedEffectValue] : '';

$finalWeaponOffense = $weaponOffense;
if (isset($spellEffects[PropertyFloat::WeaponAuraOffense])) {
    $finalWeaponOffense += $spellEffects[PropertyFloat::WeaponAuraOffense];
}

$finalWeaponDefense = $weaponDefense;
if (isset($spellEffects[PropertyFloat::WeaponAuraDefense])) {
    $finalWeaponDefense += $spellEffects[PropertyFloat::WeaponAuraDefense];
}

$finalWeaponSpeed = $speed;
if (isset($spellEffects[PropertyInt::WeaponAuraSpeed])) {
    $finalWeaponSpeed += $spellEffects[PropertyInt::WeaponAuraSpeed];
    if ($finalWeaponSpeed < 0) {
        $finalWeaponSpeed = 0;
    }
}

/**
 * CALCULTE BUFFED DAMAGE
 */

$buffedDamage = $damage;
if (isset($spellEffects[PropertyInt::WeaponAuraDamage])) {
    $buffedDamage += $spellEffects[PropertyInt::WeaponAuraDamage];
}
$buffedMinDamage = getMinDamage($buffedDamage, $variance);
$avgBuffedDamage = round(($buffedDamage + $buffedMinDamage) / 2);
$buffedDamageObj = array('min' => $buffedMinDamage, 'max' => $buffedDamage, 'avg' => $avgBuffedDamage);
$lastDamageObj = $buffedDamageObj;

/**
 * CALCULATE POWER-LEVEL DAMAGE
 */

$powerLevel = isset($_GET['powerLevel']) ? $_GET['powerLevel'] : 1;
$powerMod = getPowerMod($powerLevel, false);
$beforePowerDamageObj = $lastDamageObj;
$powerDamageObj = getDamageObj($lastDamageObj['max'], $variance, $powerMod);
$lastDamageObj = $powerDamageObj;

/**
 * CALCULATE DAMAGE-RATING
 */
 
$damageRating = isset($_GET['damageRating']) ? $_GET['damageRating'] : 5;
$damageRatingMod = (100 + $damageRating) / 100;
$beforeDRDamageObj = $lastDamageObj;
$damageRatingObj = getDamageObj($lastDamageObj['max'], $variance, $damageRatingMod);
$lastDamageObj = $damageRatingObj;

/**
 * CALCULATE ATTRIBUTE-BASED DAMAGE
 */

$weaponDamageAttributeNumber = getWeaponDamageAttribute(
    SKILLS_LIST[$ints[PropertyInt::WeaponSkill]],
    $weaponType
);
$weaponDamageAttribute = ATTRIBUTES[$weaponDamageAttributeNumber];
$attributeMod = getAttributeDamageMod($attributeValue, $weaponType);

$attributeDamageObj = getDamageObj($lastDamageObj['max'], $variance, $attributeMod);
$beforeAttributeDamageObj = $lastDamageObj;
$lastDamageObj = $attributeDamageObj;

/**
 * CALCULATE CREATURE-BASED DAMAGE + ARMOR MITIGATIONS
 */

$creatureId = isset($_GET['creatureId']) ? $_GET['creatureId'] : 1610;
$creature = getCreature($creatureId);
$creatureFloats = getFloats($creatureId);
$creatureInts = getInts($creatureId);
$creatureAttributes2nd = getAttributes2nd($creatureId);
$creatureType = CREATURE_TYPE[$creatureInts[PropertyInt::CreatureType]];
$creatureBodyArmor = getBodyArmor($creatureId);
$creatureHealth = $creatureAttributes2nd[PropertyAttribute2nd::MaxHealth];
$creatureRegenRates = getRegenRates($creatureFloats);

/**
 * CALCULATE CREATURE-BASED SLAYER DAMAGE
 */

$slayerMod = isset($floats[PropertyFloat::SlayerDamageBonus]) ? round($floats[PropertyFloat::SlayerDamageBonus], 2) : 1;
$effectiveSlayerMod = 1;
$slayerCreatureType = isset($ints[PropertyInt::SlayerCreatureType]) ? CREATURE_TYPE[$ints[PropertyInt::SlayerCreatureType]] : null;
$slayerDamageObj = null;
$beforeSlayerDamageObj = null;
if ($slayerCreatureType && $slayerCreatureType == $creatureType) {
    $slayerDamageObj = getDamageObj($lastDamageObj['max'], $variance, $slayerMod);
    $beforeSlayerDamageObj = $lastDamageObj;
    $lastDamageObj = $slayerDamageObj;
    $effectiveSlayerMod = $slayerMod;
}


/**
 * CALCULATE BODY ARMOR DAMAGE MITIGATION
 */

$bodyPart = isset($_GET['bodyPart']) ? $_GET['bodyPart'] : null;

$phantasmal = $imbuedEffect == 'IgnoreAllArmor';

$creatureBodyParts = array_keys($creatureBodyArmor);
$numBodyParts = count($creatureBodyParts);
$defaultBodyPart = $creatureBodyParts[floor($numBodyParts / 2)];
$creatureBodyPart = $defaultBodyPart;
if ($bodyPart && isset($creatureBodyArmor[$bodyPart])) {
    $creatureBodyPart = $bodyPart;
}

$creatureBodyPartArmorLevel = $creatureBodyArmor[$creatureBodyPart]['base_Armor'];
$creatureBaseArmorMod = round(calcArmorMod($creatureBodyPartArmorLevel), 4);
if ($phantasmal) {
    $creatureBaseArmorMod = 1;
}

$beforeCreatureBaseArmorDamageObj = $lastDamageObj;
$creatureBaseArmorDamageObj = getDamageObj($lastDamageObj['max'], $variance, $creatureBaseArmorMod);

$effectiveArmorObj = getEffectiveArmorObj(
    $creatureFloats,
    $creatureBodyPartArmorLevel,
    $damageType,
    0,
    1,
    isset($bools[PropertyBool::IgnoreMagicArmor]),
    isset($bools[PropertyBool::IgnoreMagicResist]),
    $phantasmal
);
$creatureArmorMod = $effectiveArmorObj['calculatedArmorMod'];

$creatureArmorDamageObj = getDamageObj($lastDamageObj['max'], $variance, $creatureArmorMod);
$beforeCreatureArmorDamageObj = $lastDamageObj;
$lastDamageObj = $beforeCreatureArmorDamageObj;

/**
 * CALCULATE ARMOR CLEAVING DAMAGE
 */

$maxSpellLevel = getMaxSpellLevel($spells);
$armorCleavingMod = getArmorCleavingMod($hasArmorCleaving, $maxSpellLevel);
$effectiveArmorCleavingObj = getEffectiveArmorObj(
    $creatureFloats,
    $creatureBodyPartArmorLevel,
    $damageType,
    0,
    $armorCleavingMod,
    isset($bools[PropertyBool::IgnoreMagicArmor]),
    isset($bools[PropertyBool::IgnoreMagicResist]),
    $phantasmal
);
$armorCleavingArmorMod = $effectiveArmorCleavingObj['calculatedArmorMod'];
$armorCleavingDamageObj = getDamageObj($beforeCreatureArmorDamageObj['max'], $variance, $armorCleavingArmorMod);
$beforeArmorCleavingDamageObj = $beforeCreatureArmorDamageObj;
$lastDamageObj = $armorCleavingDamageObj;

/**
 * CALCULATE CREATURE-BASED RESISTANCE MITIGATIONS
 */
$creatureResistanceModVsType = getResistanceModVsType($creatureFloats, $damageType);
$creatureResistanceDamageObj = getDamageObj($lastDamageObj['max'], $variance, $creatureResistanceModVsType);
$beforeCreatureResistanceDamageObj = $lastDamageObj;
$lastDamageObj = $creatureResistanceDamageObj;


/**
 * CALCULATE RESISTANCE CLEAVING/RENDING DAMAGE
 */

$weaponResistanceCleavingType = isset($ints[PropertyInt::ResistanceModifierType]) ? DAMAGE_TYPE_INT[$ints[PropertyInt::ResistanceModifierType]] : null;
$weaponResistanceCleavingMod = getResistanceCleavingMod(
    $weaponResistanceCleavingType,
    isset($floats[PropertyFloat::ResistanceModifier]) ? $floats[PropertyFloat::ResistanceModifier] : 0
);
$weaponResistanceCleavingDamageObj = getDamageObj($lastDamageObj['max'], $variance, $weaponResistanceCleavingMod);
$beforeWeaponResistanceCleavingDamageObj = $lastDamageObj;
$lastDamageObj = $weaponResistanceCleavingDamageObj;



/**
 * This should be the final damage information
 */
 
/**
 * SIMULATE FINAL DAMAGE WITH CRITS
 */

$criticalDamageRating = isset($_GET['criticalDamageRating']) ? $_GET['criticalDamageRating'] : 0;
$criticalDamageRatingMod = (100 + $criticalDamageRating + $damageRating) / 100;

$criticalMultipler = isset($floats[PropertyFloat::CriticalMultiplier]) ? $floats[PropertyFloat::CriticalMultiplier] : 1;
$weaponCriticalDamageMod = 1 + $criticalMultipler;
$criticalFrequency = isset($floats[PropertyFloat::CriticalFrequency]) ? $floats[PropertyFloat::CriticalFrequency] : 0.1;
$hasCriticalFrequencyMod = $criticalFrequency != 0.1;

$numCritHits = round(100 * $criticalFrequency);
$numNotCritHits = 100 - $numCritHits;

$simulatedNonCritMinDamage = $lastDamageObj['min'] * $numNotCritHits;
$simulatedNonCritMaxDamage = $lastDamageObj['max'] * $numNotCritHits;

$combinedCritDamageRating = $damageRating + $criticalDamageRating;
$combinedCritDamageRatingMod = (100 + $combinedCritDamageRating) / 100;

$critDamageBeforeMitigation = $buffedDamageObj['max'] * $attributeMod * $powerMod * $effectiveSlayerMod * $combinedCritDamageRatingMod * $weaponCriticalDamageMod;
/*
print "buffedDamage: " . $buffedDamageObj['max'] . "<br />";
print "attributeMod: " . $attributeMod . "<br />";
print "powerMod: " . $powerMod . "<br />";
print "effectiveSlayerMod: " . $effectiveSlayerMod . "<br />";
print "combinedCritDamageRatingMod: " . $combinedCritDamageRatingMod . "<br />";
print "weaponCriticalDamageMod: " . $weaponCriticalDamageMod. "<br />";
print "critDamageBeforeMitigation: " . $critDamageBeforeMitigation . "<br />";
*/
$finalCritDamagePerHit = round($critDamageBeforeMitigation * $creatureArmorMod * $creatureResistanceModVsType * $weaponResistanceCleavingMod);
/*
print "creatureArmorMod: " . $creatureArmorMod . "<br />";
print "creatureResistanceModVsType: " . $creatureResistanceModVsType . "<br />";
print "weaponResistanceMod: " . $weaponResistanceCleavingMod . "<br />";
print "finalCritDamagePerHit: " . $finalCritDamagePerHit . "<br />";
*/
$simulatedCritTotalDamage = $finalCritDamagePerHit * $numCritHits;

$simulatedMinDamage = $simulatedNonCritMinDamage + $simulatedCritTotalDamage;
$simulatedMaxDamage = $simulatedNonCritMaxDamage + $simulatedCritTotalDamage;
$simulatedAvgDamage = round(($simulatedMinDamage + $simulatedMaxDamage) / 200);

/**
 * Special Properties
 */
 

$specialProperties = array();
if (isset($ints[PropertyInt::Attuned])) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Attuned" target="acpedia" title="The item cannot be dropped on the ground, handed to another player, or placed in a trade window.">Attuned</a>';
}
if (isset($ints[PropertyInt::Bonded])) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Bonded" target="acpedia" title="A bonded item will not drop on death.">Bonded</a>';
}
if (isset($bools[PropertyBool::Ivoryable])) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Ivoryable" target="acpedia" title="You can apply a bag of Salvaged Ivory to allow the item to be muled, but not wielded by other characters">Ivoryable</a>';
}
if (isset($weaponResistanceCleavingType)) {
    $prop = "Resistance Cleaving: ${weaponResistanceCleavingType}";
    $specialProperties[] = '<a href="http://acpedia.org/wiki/' . str_replace(' ', '_', $prop) . '" target="acpedia" title="Causes extra damage against the creature of this damage type">' . $prop . '</a>';
}
if ($hasArmorCleaving) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Armor_Cleaving" target="acpedia" title="Ignores some amount of creature armor based on max spell level">Armor Cleaving</a>';
}
if ($criticalMultipler > 1) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Crushing_Blow" target="acpedia" title="Critical hits do extra damage">Crushing Blow</a>';    
}
if ($hasCriticalFrequencyMod) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Biting_Strike" target="acpedia" title="Higher chance of critical hits">Biting Strike</a>';    
}
if ($unenchantable) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Unenchantable" target="acpedia" title="Player spells cannot be cast on the item, though it might hav built-in enchantments">Unenchantable</a>';    
}
if ($slayerCreatureType) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/' . $slayerCreatureType . '_Slayer" target="acpedia" title="Weapon does greater damage against this creature type">' . $slayerCreatureType . ' Slayer</a>';
}
if ($phantasmal) {
    $specialProperties[] = '<a href="http://acpedia.org/wiki/Phantasmal" target="acpedia" title="Weapon does greater damage against this creature type">Phantasmal</a>';    
}

$powerLevelOptions = array(0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1);

?>

<html>
<head>
    <title>ACE Weapon: <?php echo $weapon['name']; ?> (<?php echo $weapon['id']; ?>)</title>
    <link rel="stylesheet" type="text/css" href="style.css?d=<?php echo $config->cacheBuster; ?>" media="screen" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    
<form method="GET" action="index.php">
<input type="text" name="name" value="<?php echo $weapon['name']; ?>" size="30" placeholder="Search for creatures, crafting items, or weapons" />
<input type="hidden" name="creatureId" value="<?php echo $creatureId; ?>" />
<input type="hidden" name="attributeValue" value="<?php echo $attributeValue; ?>" />
<input type="hidden" name="damageRating" value="<?php echo $damageRating; ?>" />
<input type="hidden" name="criticalDamageRating" value="<?php echo $criticalDamageRating; ?>" />
<input type="hidden" name="powerLevel" value="<?php echo $powerLevel; ?>" />

<input type="submit" value="Lookup" />
</form>

<h1><?php echo $weapon['name']; ?> vs. <a href="mob.php?id=<?php echo $creature['id']; ?>"><?php echo $creature['name']; ?> (<?php echo $creature['id']; ?>)</a></h1>

<form method="GET" id="weapon-form">
<input type="hidden" name="id" value="<?php echo $weapon['id']; ?>" />

<table class="vertical-table">
<tr>
    <th>ID</th>
    <td><?php echo $weapon['id']; ?></td>
</tr>
<tr>
    <th>Name</th>
    <td><?php echo $weapon['name']; ?></td>
</tr>
<tr>
    <th>Type</th>
    <td>
        <?php echo WEENIE_TYPE[$weapon['type']]; ?>
        <a href="crafting.php?id=<?php echo $weapon['id']; ?>">(Crafting Info)</a>
    </td>
</tr>
<tr>
    <th>Code</th>
    <td><?php echo $weapon['code']; ?></td>
</tr>
<tr>
    <th>Value</th>
    <td><?php echo number_format($ints[PropertyInt::Value]); ?></td>
</tr>
<tr>
    <th>Burden</th>
    <td><?php echo $ints[PropertyInt::EncumbranceVal]; ?> Burden Units</td>
</tr>
<tr>
    <th>Usable</th>
    <td><?php echo $ints[PropertyInt::ItemUseable] ? 'Yes' : 'No'; ?></td>
</tr>
<?php
if ($description) {
?>
<tr>
    <th>Description</th>
    <td><?php echo $description; ?></td>
</tr>
<?php
}
?>
<tr>
    <th>Links</th>
    <td>
            <a href="http://acpedia.org/<?php echo str_replace(' ', '_', $weapon['name']); ?>" target="acpedia">ACPedia</a>
            /
            <a href="http://ac.yotesfan.com/weenies/items/<?php echo $weapon['id']; ?>" target="yotesfan">Yotesfan</a>
    </td>
</tr>
</table>

<h3>Stats</h3>

<table class="vertical-table">
<tr>
    <th>Special Properties</th>
    <td>
        <?php echo implode(', ', $specialProperties); ?>
    </td>
</tr>
<tr>
    <th>Skill</th>
    <td>
        <?php echo SKILLS_LIST[$ints[PropertyInt::WeaponSkill]]; ?>
        <?php if ($weaponType) { echo '(' . $weaponType . ')'; } ?>
    </td>
</tr>
<tr>
    <th>Damage</th>
    <td>
        <?php echo $minDamage . ' - ' . $damage; ?>,
        <?php echo $buffedDamageObj['max'] != $damage ? "<span class=\"spell-effect\">(" . $buffedDamageObj['min'] . ' - ' . $buffedDamageObj['max'] . ")</span>" : ''; ?>
        <?php echo $baseDamageType; ?>
    </td>
</tr>
<tr>
    <th>Speed</th>
    <td>
        <?php echo $speed; ?>
        <?php echo $finalWeaponSpeed != $speed ? "<span class=\"spell-effect\">(" . $finalWeaponSpeed . ")</span>" : ''; ?>
    </td>
</tr>
<tr>
    <th>Bonus to Attack Skill</th>
    <td>
        <?php echo getWeaponModifierPercentage($weaponOffense); ?>
        <?php echo $finalWeaponOffense != $weaponOffense ? "<span class=\"spell-effect\">(" . getWeaponModifierPercentage($finalWeaponOffense) . ")</span>" : ''; ?>
    </td>
</tr>
<tr>
    <th>Bonus to Defense Skill</th>
    <td>
        <?php echo getWeaponModifierPercentage($weaponDefense); ?>
        <?php echo $finalWeaponDefense != $weaponDefense ? "<span class=\"spell-effect\">(" . getWeaponModifierPercentage($finalWeaponDefense) . ")</span>" : ''; ?>
    </td>
</tr>
<?php
if (isset($ints[PropertyInt::WieldRequirements])) {
?>
<tr>
    <th>Wield Requirements</th>
    <td><?php echo getWieldRequirementDisplay($ints[PropertyInt::WieldRequirements], $ints[PropertyInt::WieldSkillType], $ints[PropertyInt::WieldDifficulty]); ?></td>
</tr>
<?php
}
if (isset($ints[PropertyInt::ItemDifficulty])) {
?>
<tr>
    <th>Activation Requirements</th>
    <td>Arcane Lore: <?php echo $ints[PropertyInt::ItemDifficulty]; ?></td>
</tr>
<?php
}
if ($spells) {
?>
<tr><td colspan=2>&nbsp;</td></tr>
<tr>
    <th>Casts the following spells</th>
    <td>
<?php
    $spellsList = array();
    foreach ($spells as $spell) {
        $spellsList[] = '<a href="http://acpedia.org/wiki/' . str_replace(' ', '_', $spell['name']) . '">' . $spell['name'] . '</a>';
    }
    
    echo implode('<br />', $spellsList);
?>
    </td>
</tr>
<?php
}
if (isset($ints[PropertyInt::ItemSpellcraft])) {
?>
<tr><td colspan=2>&nbsp;</td></tr>
<tr>
    <th>Spellcraft</th>
    <td><?php echo $ints[PropertyInt::ItemSpellcraft]; ?></td>
</tr>
<tr>
    <th>Mana</th>
    <td><?php echo $ints[PropertyInt::ItemMaxMana]; ?></td>
</tr>
<?php
}
?>
</table>


<h3>Applying the effects of the power meter</h3>
<table class="vertical-table">
<tr>
    <th>Power Level</th>
    <td>
        
        <select name="powerLevel">
<?php
foreach ($powerLevelOptions as $loopPowerLevel) {
?>
            <option value="<?php echo $loopPowerLevel; ?>"<?php echo $loopPowerLevel == $powerLevel ? ' selected' : ''; ?>><?php echo getPercentage($loopPowerLevel); ?></option>
<?php
}
?>
        </select>
        
        <input type="submit" value="Update" />
    </td>
</tr>
<tr><td colspan="2"></td></tr>
<tr>
    <th>Damage before power modifier <i>(Avg)</i></th>
    <td>
        <?php echo $beforePowerDamageObj['min'] . ' - ' . $beforePowerDamageObj['max']; ?>
        <i>(<?php echo $beforePowerDamageObj['avg']; ?></i>)
    </td>
</tr>
<tr>
    <th>Power Level Modifier</th>
    <td><?php echo getPercentage($powerMod); ?></td>
</tr>
<tr>
    <th>Damage after power modifier modifier <i>(Avg)</i></th>
    <td>
        <?php echo $powerDamageObj['min'] . ' - ' . $powerDamageObj['max']; ?>
        <i>(<?php echo $powerDamageObj['avg']; ?></i>)
    </td>
</tr>
</table>



<h3>Applying the effects of your damage rating</h3>
<table class="vertical-table">
<tr>
    <th>Damage Rating</th>
    <td>
        <input type="text" name="damageRating" value="<?php echo $damageRating; ?>" size="5" />
    </td>
</tr>
<tr>
    <th>Critical Damage Rating</th>
    <td>
        <input type="text" name="criticalDamageRating" value="<?php echo $criticalDamageRating; ?>" size="5" />
    </td>
</tr>
<tr>
    <th>&nbsp;</th>
    <td><input type="submit" value="Update" /></td>
</tr>
<tr><td colspan="2"></td></tr>
<tr>
    <th>Damage before damage rating modifier <i>(Avg)</i></th>
    <td>
        <?php echo $beforeDRDamageObj['min'] . ' - ' . $beforeDRDamageObj['max']; ?>
        <i>(<?php echo $beforeDRDamageObj['avg']; ?></i>)
    </td>
</tr>
<tr>
    <th>Damage Rating Modifier</th>
    <td><?php echo getPercentage($damageRatingMod); ?></td>
</tr>
<tr>
    <th>Damage after damage rating modifier <i>(Avg)</i></th>
    <td>
        <?php echo $damageRatingObj['min'] . ' - ' . $damageRatingObj['max']; ?>
        <i>(<?php echo $damageRatingObj['avg']; ?></i>)
    </td>
</tr>
</table>


<h3>Applying the effects of your primary weapon attribute</h3>
<table class="vertical-table">
<tr>
    <th>Buffed <?php echo $weaponDamageAttribute; ?></th>
    <td>
        <input type="text" name="attributeValue" value="<?php echo $attributeValue; ?>" size="5" />
        <input type="submit" value="Update" />
    </td>
</tr>
<tr><td colspan="2"></td></tr>
<tr>
    <th>Damage before <?php echo $weaponDamageAttribute; ?> modifier <i>(Avg)</i></th>
    <td>
        <?php echo $beforeAttributeDamageObj['min'] . ' - ' . $beforeAttributeDamageObj['max']; ?>
        <i>(<?php echo $beforeAttributeDamageObj['avg']; ?></i>)
    </td>
</tr>
<tr>
    <th><?php echo $weaponDamageAttribute; ?> Modifier</th>
    <td><?php echo getPercentage($attributeMod); ?></td>
</tr>
<tr>
    <th>Damage after <?php echo $weaponDamageAttribute; ?> modifier <i>(Avg)</i></th>
    <td>
        <?php echo $attributeDamageObj['min'] . ' - ' . $attributeDamageObj['max']; ?>
        <i>(<?php echo $attributeDamageObj['avg']; ?></i>)
    </td>
</tr>
</table>



<h3>Creature</h3>

<input type="text" id="creatureSearch" onKeyUp="onCreatureInputKeyUp()" value="" placeholder="Starting typing a creature name to choose one" size="40" />
<input type="hidden" name="creatureId" value="<?php echo $creatureId; ?>" id="creature-id" />
<div id="creature-results" class="typeahead"></div>
<br /><br />

<table class="vertical-table">
<tr>
    <th>ID</th>
    <td><?php echo $creature['id']; ?></td>
</tr>
<tr>
    <th>Name</th>
    <td><a href="mob.php?id=<?php echo $creature['id']; ?>"><?php echo $creature['name']; ?></a></td>
</tr>
<tr>
    <th>Creature Type</th>
    <td><?php echo CREATURE_TYPE[$creatureInts[PropertyInt::CreatureType]]; ?></td>
</tr>
<tr>
    <th>Code</th>
    <td><?php echo $creature['code']; ?></td>
</tr>
<tr>
    <th>Level</th>
    <td><?php echo $creature['level']; ?></td>
</tr>
<tr>
    <th>Health</th>
    <td><?php echo number_format($creatureHealth); ?></td>
</tr>
<tr>
    <th>Regen/sec</th>
    <td><?php echo $creatureRegenRates['health']; ?></td>
</tr>
</table>



<h3>Applying slayer effects</h3>
<?php
if ($slayerCreatureType && $slayerCreatureType == $creatureType) {
?>
<table class="vertical-table">
<tr>
    <th><?php echo $slayerCreatureType; ?> Slayer Damage Multiplier</th>
    <td>
        <?php echo getPercentage($slayerMod); ?>
    </td>
</tr>
<tr>
    <th>Damage after slayer effects <i>(Avg)</i></th>
    <td>
        <?php echo $slayerDamageObj['min']; ?> - <?php echo $slayerDamageObj['max']; ?>
        <i>(<?php echo $slayerDamageObj['max']; ?>)</i>
    </td>
</tr>
</table>
<?php
} else if ($slayerCreatureType) {
?>
    <p>This weapon has a slayer property of <?php echo getPercentage($slayerMod); ?> for <i><?php echo $slayerCreatureType; ?></i> creatures but that is not applied to <?php echo $creatureType; ?></p>
<?php
} else {
?>
    <p>This weapon has no creature slaying properties.</p>
<?php
}
?>


<h3>Applying the effects of Creature Body Armor</h3>
<table class="vertical-table">
<tr>
    <th>Body Part</th>
    <td>
        <select name="bodyPart">
<?php
foreach ($creatureBodyArmor as $loopBodyPart => $loopBodyPartInfo) {
?>
            <option value="<?php echo $loopBodyPart; ?>"<?php echo ($loopBodyPart == $creatureBodyPart ? ' selected' : ''); ?>><?php echo $loopBodyPart; ?> (<?php echo $loopBodyPartInfo['base_Armor']; ?> AL)</option>
<?php
}
?>
        </select>
        
        <input type="submit" value="Update" />
    </td>
</tr>
<?php
if ($phantasmal) {
?>
<tr>
    <th>Weapon Has Phantasmal Property?</th>
    <td>Yes, all physical armor is ignored</td>
</tr>
<?php
}
?>
<tr><td colspan="2"></td></tr>
<tr>
    <th><?php echo $damageType; ?>-specific armor multiplier</th>
    <td>
        <?php echo getPercentage($effectiveArmorObj['armorModVsType']); ?>
    </td>
</tr>
<tr>
    <th>Effective AL for <?php echo $damageType; ?></th>
    <td>
        <?php echo $effectiveArmorObj['effectiveArmorLevel']; ?>
    </td>
</tr>
<tr><td colspan="2"></td></tr>
<tr>
    <th>Damage before Creature Body Armor <i>(Avg)</i></th>
    <td>
        <?php echo $beforeCreatureArmorDamageObj['min']; ?> - <?php echo $beforeCreatureArmorDamageObj['max']; ?>
        <i>(<?php echo $beforeCreatureArmorDamageObj['avg']; ?>)</i>
    </td>
</tr>
<tr>
    <th>Creature Body Armor Damage Modifier</th>
    <td>
        <?php echo getPercentage(round($creatureArmorMod, 4)); ?>
    </td>
</tr>
<tr>
    <th>Damage after Creature Body Armor <i>(Avg)</i></th>
    <td>
        <?php echo $creatureArmorDamageObj['min']; ?> - <?php echo $creatureArmorDamageObj['max']; ?>
        <i>(<?php echo $creatureArmorDamageObj['avg']; ?>)</i>
    </td>
</tr>
</table>


<h3>Applying the effects of Armor Cleaving/Rending</h3>
<?php
if ($hasArmorCleaving) {
?>
<table class="vertical-table">
<tr>
    <th>Effective AL Before Cleaving</th>
    <td>
        <?php echo $effectiveArmorObj['effectiveArmorLevelAfterRending']; ?>
    </td>
</tr>
<tr>
    <th>Effective AL After Cleaving</th>
    <td>
        <?php echo $effectiveArmorCleavingObj['effectiveArmorLevelAfterRending']; ?>
    </td>
</tr>
<tr><td colspan="2"></td></tr>
<tr>
    <th>Damage before Armor Cleaving <i>(Avg)</i></th>
    <td>
        <?php echo $beforeArmorCleavingDamageObj['min']; ?> - <?php echo $beforeArmorCleavingDamageObj['max']; ?>
        <i>(<?php echo $beforeArmorCleavingDamageObj['avg']; ?>)</i>
    </td>
</tr>
<tr>
    <th>Armor Cleaving Modifier</th>
    <td>
        <span title="<?php echo 'Based on a max spell level of ' . $maxSpellLevel; ?>"><?php echo getPercentage($armorCleavingMod); ?></span>
    </td>
</tr>
<tr>
    <th>Damage after Armor Cleaving <i>(Avg)</i></th>
    <td>
        <?php echo $armorCleavingDamageObj['min']; ?> - <?php echo $armorCleavingDamageObj['max']; ?>
        <i>(<?php echo $armorCleavingDamageObj['avg']; ?>)</i>
    </td>
</tr>
<?php
} else {
?>
No effects from Armor Cleaving
<?php
}
?>
</table>



<h3>Applying the effects of innate creature resistance to <?php echo $damageType; ?></h3>
<table class="vertical-table">
<tr>
    <th>Damage before Creature Resistance <i>(Avg)</i></th>
    <td>
        <?php echo $beforeCreatureResistanceDamageObj['min']; ?> - <?php echo $beforeCreatureResistanceDamageObj['max']; ?>
        <i>(<?php echo $beforeCreatureResistanceDamageObj['avg']; ?>)</i>
    </td>
</tr>
<tr>
    <th><?php echo $damageType; ?> Damage Modifier</th>
    <td>
        <?php echo getPercentage($creatureResistanceModVsType); ?>
    </td>
</tr>
<tr>
    <th>Damage after Creature Resistance <i>(Avg)</i></th>
    <td>
        <?php echo $creatureResistanceDamageObj['min']; ?> - <?php echo $creatureResistanceDamageObj['max']; ?>
        <i>(<?php echo $creatureResistanceDamageObj['avg']; ?>)</i>
    </td>
</tr>
</table>




<h3>Applying the effects of resistance cleaving/rending</h3>
<?php
if ($weaponResistanceCleavingMod > 1) {
?>
<table class="vertical-table">
<tr>
    <th>Damage before Resistance Cleaving <i>(Avg)</i></th>
    <td>
        <?php echo $beforeWeaponResistanceCleavingDamageObj['min'] . ' - ' . $beforeWeaponResistanceCleavingDamageObj['max']; ?>
        <i>(<?php echo $beforeWeaponResistanceCleavingDamageObj['avg']; ?></i>)
    </td>
</tr>
<tr>
    <th>Resistance Cleaving Damage Modifier</th>
    <td>
        <?php echo getPercentage($weaponResistanceCleavingMod); ?>
    </td>
</tr>
<tr>
    <th>Damage after Resistance Cleaving <i>(Avg)</i></th>
    <td>
        <?php echo $weaponResistanceCleavingDamageObj['min'] . ' - ' . $weaponResistanceCleavingDamageObj['max']; ?>,
        <i>(<?php echo $weaponResistanceCleavingDamageObj['avg']; ?>)</i>
    </td>
</tr>
</table>
<?php
} else {
?>
    <p>This weapon has no resistance cleaving properties</p>
<?php
}
?>



<h3>Simulation of 100 Post-Armor Strikes</h3>
<p>Combines non-crit and crit damage for a simulation of damage over time</p>

<table class="vertical-table">
<tr>
    <th>Non-Crit Damage Per Strike <i>(Avg)</i></th>
    <td>
        <?php echo $lastDamageObj['min']; ?> - <?php echo $lastDamageObj['max']; ?>
        <i>(<?php echo $lastDamageObj['avg']; ?>)</i>
    </td>
</tr>
<tr><td colspan="2"></td></tr>
<tr>
    <th>Weapon Critical Frequency</th>
    <td>
        <?php echo getRoundedPercentage($criticalFrequency); ?>
    </td>
</tr>
<tr>
    <th>Number of Crit Strikes</th>
    <td>
        <?php echo $numCritHits; ?>
    </td>
</tr>
<tr>
    <th>Number of Non-Crit Strikes</th>
    <td>
        <?php echo $numNotCritHits; ?>
    </td>
</tr>

<tr><td colspan="2"></td></tr>
<tr>
    <th>Weapon Critical Damage Modifier</th>
    <td>
        <?php echo getPercentage($weaponCriticalDamageMod); ?>
    </td>
</tr>
<tr>
    <th>Crit Damage Per Strike</th>
    <td>
        <?php echo $finalCritDamagePerHit; ?>
    </td>
</tr>

<tr><td colspan="2"></td></tr>
<tr>
    <th>Total Combined Damage</th>
    <td>
        <?php echo number_format($simulatedMinDamage); ?> - <?php echo number_format($simulatedMaxDamage); ?>
    </td>
</tr>
<tr>
    <th>Avg Damage Per Strike</th>
    <td>
        <?php echo $simulatedAvgDamage; ?>
    </td>
</tr>
</table>

</form>

<script type="text/javascript">
function onCreatureInputKeyUp(event) {
    const creatureField = document.getElementById('creatureSearch');
    const name = creatureField.value;
    
    if (name) {
        refreshCreatureList(name);
    } else {
        refreshTypeahead('');
    }
}

function refreshCreatureList(name) {
    const xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            const result = JSON.parse(this.responseText);
            
            
            const listing = [];
            result.forEach(c => {
                const lineDisplay = `${c.name} (${c.id} / ${c.code}) - Level ${c.level}`;
                listing.push('<div class="entry" onClick="selectCreature(' + c.id + ')">' + lineDisplay + '</div>\n');
            });
            
            refreshTypeahead(listing.join("\n"));
        }
    };
    
    xhttp.open("GET", "ajax.creatures.php?name=" + encodeURIComponent(name), true);
    xhttp.send();
}

function refreshTypeahead(content) {
    const creatureResultsField = document.getElementById('creature-results');

    creatureResultsField.innerHTML = content;
    if (content.length > 0) {
        creatureResultsField.style.display = 'block';
    } else {
        creatureResultsField.style.display = 'none';
    }
}

function selectCreature(id) {
    document.getElementById('creature-id').value = id;
    document.getElementById('weapon-form').action = 'weapons.php';
    document.getElementById('weapon-form').submit();
    document.getElementById('creature-results').style.display = 'none';
}

</script>


<?php
include_once 'footer.php';
?>
</body>
</html>
