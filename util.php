<?php
ini_set('display_errors', 'on');

if (!file_exists('config.json')) {
    echo('No config.json file exists');
    exit;
}

$config = json_decode(file_get_contents('config.json'));
$dbh = new PDO("mysql:host={$config->db->host};dbname=ace_world", $config->db->user, $config->db->password);

function getCreature($weenieId) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                        weenie.class_Id id,
                                        wps.value name,
                                        weenie.type type,
                                        weenie.class_Name code,
                                        wpi.value level
                                    from weenie 
                                        join weenie_properties_string wps on (wps.object_Id = weenie.class_Id) 
                                        join weenie_properties_int wpi on (wpi.object_id = weenie.class_Id)
                                    where 
                                        weenie.type in (10, 15)
                                        and wps.type = 1
                                        and wpi.type = 25
                                        and weenie.class_Id = ?
                                    order by level asc");

    $statement->execute(array($weenieId));
    $mob = $statement->fetch(PDO::FETCH_ASSOC);
    
    return $mob;
}

function searchCreatures($name) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                    weenie.class_Id id,
                                    wps.value name,
                                    weenie.class_Name code,
                                    wpi.value level,
                                    wpiType.value type
                                from weenie 
                                    join weenie_properties_string wps on (wps.object_Id = weenie.class_Id and wps.type = 1) 
                                    left join weenie_properties_bool wpb on (wpb.object_Id = weenie.class_Id and wpb.type = 19)
                                    join weenie_properties_int wpi on (wpi.object_id = weenie.class_Id and wpi.type = 25)
                                    join weenie_properties_int wpiType on (wpiType.object_Id = weenie.class_Id and wpiType.type = 2)
                                where 
                                    weenie.type in (10, 15)
                                    and (wpb.value = 1 or wpb.value is null)
                                    and (wps.value like ? or weenie.class_Name like ?)
                                order by level asc, id asc");

    $statement->execute(array("%${name}%", "%${name}%"));

    $creatureResults = array();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $creatureResults[] = $row;
    }
    
    return $creatureResults;
}

function getWeapon($weenieId) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                        weenie.class_Id id,
                                        wps.value name,
                                        weenie.type type,
                                        weenie.class_Name code
                                    from weenie 
                                        join weenie_properties_string wps on (wps.object_Id = weenie.class_Id) 
                                    where
                                        weenie.type in (" . implode(', ', WEAPON_WEENIE_TYPES) . ")
                                        and (wps.type = 1 and weenie.class_Id = ?)");

    $statement->execute(array($weenieId));
    $item = $statement->fetch(PDO::FETCH_ASSOC);
    
    return $item;
}

function getCraftingItem($weenieId) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                        weenie.class_Id id,
                                        wps.value name,
                                        weenie.type type,
                                        weenie.class_Name code
                                    from weenie 
                                        join weenie_properties_string wps on (wps.object_Id = weenie.class_Id) 
                                    where
                                        weenie.type in (" . implode(', ', CRAFTING_TYPES) . ")
                                        and (wps.type = 1 and weenie.class_Id = ?)");

    $statement->execute(array($weenieId));
    $item = $statement->fetch(PDO::FETCH_ASSOC);
    
    return $item;
}

function getCreateList($weenieId) {
    global $dbh;

    $statement = $dbh->prepare("select
                                    wpcl.weenie_Class_Id id,
                                    wpcl.shade chance,
                                    weenie.class_Name code,
                                    wps.value name
                                from weenie_properties_create_list wpcl
                                left join weenie_properties_string wps on (wps.object_Id = wpcl.weenie_Class_Id)
                                left join weenie on (weenie.class_Id = wpcl.weenie_Class_Id)
                                where 
                                    wpcl.object_id = ?
                                    and wpcl.weenie_Class_Id != 0
                                    and (wps.type = 1 or wps.type is null)
                                order by shade desc, name asc");

    $statement->execute(array($weenieId));
    $rows = array();
    
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = $row;
    }
    
    return $rows;
}

function getCreaturesThatDropItem($treasureWeenieId) {
    global $dbh;

    $statement = $dbh->prepare("select
                                    wpcl.object_Id id,
                                    wpcl.shade chance,
                                    weenie.class_Name code,
                                    wps.value name,
                                    wpi.value type,
                                    weenie.type weenieType
                                from weenie_properties_create_list wpcl
                                left join weenie_properties_string wps on (wps.object_Id = wpcl.object_Id)
                                left join weenie on (weenie.class_Id = wpcl.object_Id)
                                left join weenie_properties_int wpi on (wpi.object_id = wpcl.object_Id)
                                where 
                                    wpcl.weenie_Class_Id = ?
                                    and (wps.type = 1 or wps.type is null)
                                    and (wpi.type = 2 or wpi.type is null)
                                order by shade desc, name asc");

    $statement->execute(array($treasureWeenieId));
    $rows = array();
    
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = $row;
    }
    
    return $rows;
}

function getBodyArmor($weenieId) {
    global $dbh;

    $armorStatement = $dbh->prepare("select * from weenie_properties_body_part
                                        where object_Id = ?");
    $armorStatement->execute(array($weenieId));

    $bodyArmor = array();
    $bodyArmorKeys = array(
        'Head',
        'Chest',
        'Abdomen',
        'Upper Arm',
        'Lower Arm',
        'Hand',
        'Upper Leg',
        'Lower Leg',
        'Foot',
        'Horn',
        'Front Leg',
        '',
        'Front Foot',
        'Rear Leg',
        '',
        'Rear Foot',
        'Torso',
        'Tail',
        'Arm',
        'Leg',
        'Claw',
        'Wings',
        'Breath',
        'Tentacle',
        'Upper Tentacle',
        'Lower Tentacle',
        'Cloak',
        'NumParts'
    );

    while ($row = $armorStatement->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['key'];
        $bodyArmorName = $bodyArmorKeys[$key];
        
        $bodyArmor[$bodyArmorName] = $row;
    }
    
    return $bodyArmor;
}

function getFloats($weenieId) {
    return getKeyValues('float', $weenieId, 'type', 'value');
}

function getAttributes($weenieId) {
    return getKeyValues('attribute', $weenieId, 'type', 'init_Level');
}

function getAttributes2nd($weenieId) {
    return getKeyValues('attribute_2nd', $weenieId, 'type', 'current_Level');
}

function getInts($weenieId) {
    return getKeyValues('int', $weenieId, 'type', 'value');
}

function getStrings($weenieId) {
    return getKeyValues('string', $weenieId, 'type', 'value');
}

function getBools($weenieId) {
    return getKeyValues('bool', $weenieId, 'type', 'value');
}

function getDataIds($weenieId) {
    return getKeyValues('d_i_d', $weenieId, 'type', 'value');
}

function getSpellBook($weenieId) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                    s.id id,
                                    s.name name,
                                    wpsb.probability probability,
                                    s.stat_Mod_Type,
                                    s.stat_Mod_Key,
                                    s.stat_Mod_Val
                                        from weenie_properties_spell_book wpsb
                                        join spell s on s.id = wpsb.spell
                                        where wpsb.object_Id = ?
                                        order by wpsb.probability desc, s.name asc");
    $statement->execute(array($weenieId));

    $spellBook = array();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        // Found this formula here: https://github.com/ACEmulator/ACE/blob/7c059bb30f64a82be1f0fcfb7ef6a9433e187b1d/Source/ACE.Server/WorldObjects/Monster_Magic.cs#L79
        $probability = $row['probability'] > 2 ? $row['probability'] - 2 : $row['probability'] / 100;
        
        $spellBook[] = array(
            'id'            => $row['id'],
            'name'          => $row['name'],
            'probability'   => round($row['probability'], 2),
            'statModType'   => $row['stat_Mod_Type'],
            'statModKey'    => $row['stat_Mod_Key'],
            'statModVal'    => $row['stat_Mod_Val']
        );
    }
    
    return $spellBook;
}

function getSpell($spellId) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                    s.id id,
                                    s.name name,
                                    s.stat_Mod_Type,
                                    s.stat_Mod_Key,
                                    s.stat_Mod_Val
                                from spell s
                                where s.id = ?");
    $statement->execute(array($spellId));
    
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function getMaxSpellLevel($spellBook) {
    $maxSpellLevel = 0;
    
    foreach ($spellBook as $spell) {
        $spellLevel = 0;
        if (strstr($spell['name'], ' I ') !== false) {
            $spellLevel = 1;
        } else if (strstr($spell['name'], ' II ') !== false) {
            $spellLevel = 2;
        } else if (strstr($spell['name'], ' III ') !== false) {
            $spellLevel = 3;
        } else if (strstr($spell['name'], ' IV ') !== false) {
            $spellLevel = 4;
        } else if (strstr($spell['name'], ' V ') !== false) {
            $spellLevel = 5;
        } else if (strstr($spell['name'], ' VI ') !== false) {
            $spellLevel = 6;
        } else if (strstr($spell['name'], 'Incantation') !== false) {
            $spellLevel = 8;
        } else if ($spell['statModVal'] == 40) {
            $spellLevel = 7;
        }
        
        if ($spellLevel > $maxSpellLevel) {
            $maxSpellLevel = $spellLevel;
        }
    }
    
    return $maxSpellLevel;
}

// See https://github.com/ACEmulator/ACE/blob/7d61b543e3c24652c4db0ddfee0eb829b7cf09cf/Source/ACE.Server/WorldObjects/WorldObject_Weapon.cs#L786
function getArmorCleavingMod($hasArmorCleaving, $maxSpellLevel) {
    if (!$hasArmorCleaving) {
        return 1;
    }
    
    return 1 - (0.1 + ($maxSpellLevel * 0.05));
}

function getResistanceCleavingMod($resistanceModifierType, $resistanceMod) {
    if ($resistanceModifierType && $resistanceMod) {
        return 1 + $resistanceMod;
    }
    
    return 1;
}

const ARMOR_MOD = 200 / 3;
function calcArmorMod($armorLevel) {
    if ($armorLevel > 0) {
        return ARMOR_MOD / ($armorLevel + ARMOR_MOD);
    } else if ($armorLevel < 0) {
        return 1 - ($armorLevel / ARMOR_MOD);
    } else {
        return 1;
    }
}

function getWeaponDamageAttribute($skill, $weaponType) {
    if ($skill == 'Finesse Weapons') {
        return PropertyAttribute::Coordination;
    } else if ($skill == 'Missile Weapons') {
        if ($weaponType == 'Thrown Weapons') {
            return PropertyAttribute::Strength;
        } else {
            return PropertyAttribute::Coordination;
        }
    } else {
        return PropertyAttribute::Strength;
    }
}

const ATTRIBUTE_DAMAGE_MOD_BOW = 0.008;
const ATTRIBUTE_DAMAGE_MOD_DEFAULT = 0.011;

function shouldUseBowMod($weaponType) {
    return ($weaponType == 'Bow' || $weaponType == 'Crossbow');
}

// See https://github.com/ACEmulator/ACE/blob/7d61b543e3c24652c4db0ddfee0eb829b7cf09cf/Source/ACE.Server/WorldObjects/SkillFormula.cs#L18
function getAttributeDamageMod($value, $weaponType) {
    $factor = shouldUseBowMod($weaponType) ? ATTRIBUTE_DAMAGE_MOD_BOW : ATTRIBUTE_DAMAGE_MOD_DEFAULT;
    
    $mod = 1 + (($value - 55) * $factor);
    if ($mod < 1) {
        return 1;
    } else {
        return $mod;
    }
}

function getPowerMod($powerLevel, $isRanged) {
    if ($isRanged) {
        return 1;
    } else {
        return $powerLevel + 0.5;
    }
}

function getSkills($weenieId, $attributes) {    
    $baseSkills = getKeyValues('skill', $weenieId, 'type', 'init_Level');
    
    $finalSkills = array();
    foreach ($baseSkills as $typeNumber => $value) {
        $skillName = SKILLS_LIST[$typeNumber];
        $formula = SKILL_FORMULAS[$skillName];

        $add = 0;
        if ($formula) {
            if ($formula[0] && $formula[1]) {
                $first = $attributes[$formula[0]];
                $second = $attributes[$formula[1]];
                $divisor = $formula[2];
                
                $add = ($first + $second) / $divisor;
            } else if ($formula[0]) {
                $first = $attributes[$formula[0]];
                $divisor = $formula[2];
                
                $add = ($first / $divisor);                
            }
        }
        
        $finalSkills[$typeNumber] = $value + round($add);
    }
    
    return $finalSkills;
}

function getKeyValues($propertyType, $weenieId, $keyColumn, $valueColumn) {
    global $dbh;

    $statement = $dbh->prepare("select * from `weenie_properties_${propertyType}`
                                        where object_Id = ?");
    $statement->execute(array($weenieId));

    $properties = array();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $properties[$row[$keyColumn]] = $row[$valueColumn];
    }
    
    return $properties;    
}

const DAMAGE_TYPES_MAP = array(
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

function isHittableBodyPart($bodyPartArmor) {
    $totalQuadrant = $bodyPartArmor['h_l_f'] + $bodyPartArmor['m_l_f'] + $bodyPartArmor['l_l_f']
                   + $bodyPartArmor['h_r_f'] + $bodyPartArmor['m_r_f'] + $bodyPartArmor['l_r_f']
                   + $bodyPartArmor['h_l_b'] + $bodyPartArmor['m_l_b'] + $bodyPartArmor['l_l_b']
                   + $bodyPartArmor['h_r_b'] + $bodyPartArmor['m_r_b'] + $bodyPartArmor['l_r_b'];

    // This means it is a special offense-only body part that cannot be hit
    return ($totalQuadrant != 0);
}

function getEffectiveArmor($bodyArmor, $floats) {
    $average = array();

    $count = 0;
    foreach ($bodyArmor as $bodyPart => $bodyPartArmor) {        
        if (!isHittableBodyPart($bodyPartArmor)) {
            continue;
        }

        $effectiveArmor[$bodyPart] = array();

        foreach (DAMAGE_TYPES_MAP as $damageType => $damageProps) {
            $armorModProp = $damageProps['ArmorModProperty'];
            $resistModProp = $damageProps['ResistProperty'];

            $baseArmor = $bodyPartArmor["base_Armor"];
            $armorModVsDamageType = isset($floats[$armorModProp]) ? round($floats[$armorModProp], 2) : 1;
            $resistVsDamageType = isset($floats[$resistModProp]) ? round($floats[$resistModProp], 2) : 1;
            
            if ($resistVsDamageType == 0) {
                $calculated = 'INF';
            } else {
                $armorMod = calcArmorMod($baseArmor * $armorModVsDamageType);
                $calculated = round(100 * $armorMod * $resistVsDamageType);
            }

            $effectiveArmor[$bodyPart][$damageType] = array(
                'baseArmor'     => $baseArmor,
                'armorMod'      => $armorModVsDamageType,
                'resist'        => $resistVsDamageType,
                'calculated'    => $calculated
            );
            
            if (!isset($average[$damageType])) {
                $average[$damageType] = array(
                    'baseArmor' => 0,
                    'armorMod'  => 0,
                    'resist'    => 0,
                    'calculated'=> 0
                );
            }

            $average[$damageType]['baseArmor'] += $baseArmor;
            $average[$damageType]['armorMod'] += $armorModVsDamageType;
            $average[$damageType]['resist'] += $resistVsDamageType;
            
            if ($calculated == 'INF') {
                $average[$damageType]['calculated'] = 'INF';
            } else {
                $average[$damageType]['calculated'] += $calculated;
            }
        }
        
        $count++;
    }
    
    foreach ($average as $damageType => $stats) {
        $average[$damageType]['baseArmor'] = round($stats['baseArmor'] / $count);
        $average[$damageType]['armorMod'] = round($stats['armorMod'] / $count, 2);
        $average[$damageType]['resist'] = round($stats['resist'] / $count, 2);
        
        if ($stats['calculated'] != 'INF') {
            $average[$damageType]['calculated'] = round($stats['calculated'] / $count);
        }
    }
    
    return array(
        'bodyParts' => $effectiveArmor,
        'average'   => array('Average' => $average)
    );
}

function getAverageBaseArmorLevel($bodyArmor) {
    $totalBaseArmor = 0;
    $count = 0;
    
    foreach ($bodyArmor as $bodyPartArmor) {
        if (isHittableBodyPart($bodyPartArmor)) {
            $totalBaseArmor += $bodyPartArmor['base_Armor'];
            $count++;
        }
    }
    
    return $totalBaseArmor / $count;
}

// See https://github.com/ACEmulator/ACE/blob/7d61b543e3c24652c4db0ddfee0eb829b7cf09cf/Source/ACE.Server/WorldObjects/Creature_BodyPart.cs#L36
function getEffectiveArmorObj(
    $floats,
    $armorLevel,
    $damageType,
    $armorAddedFromEnchantment = 0, // From either Imperil (negative) or Armor (positive)
    $armorRendingMod = 1,
    $ignoreMagicArmor = false,
    $ignoreMagicResist = false,
    $phantasmal = false
) {
    $armorModVsType = getArmorModVsType($floats, $damageType);
    $armorVsType = $armorLevel * $armorModVsType;
    
    // Called enchantmentMod in ACE
    // Enchantments are ignored if the weapon has Float::IgnoreMagicResist
    $effectiveArmorAddedFromEnchantment = $ignoreMagicResist ? 0 : $armorAddedFromEnchantment;
    
    $effectiveArmorLevel = $armorVsType + $effectiveArmorAddedFromEnchantment;
    
    // At this point we're ignoring armor layers, which I think might be clothing/armor banes
    // Can we safely ignore it for monsters?
    
    $effectiveArmorLevelAfterRending = $effectiveArmorLevel;
    if ($effectiveArmorLevel > 0) {
        $effectiveArmorLevelAfterRending *= $armorRendingMod;
    }
    
    if ($phantasmal) {
        $effectiveArmorLevel = 0;
        $effectiveArmorLevelAfterRending = 0;
    }
    
    $armorMod = calcArmorMod($effectiveArmorLevelAfterRending);
    
    return array(
        'armorLevel'     => $armorLevel,
        'armorModVsType' => $armorModVsType,
        'armorVsType'    => $armorVsType,
        'ignoreMagicArmor'  => $ignoreMagicArmor,
        'ignoreMagicResist' => $ignoreMagicResist,
        'armorAddedFromEnchantment' => $armorAddedFromEnchantment,
        'effectiveArmorAddedFromEnchantment' => $effectiveArmorAddedFromEnchantment,
        'effectiveArmorLevel' => $effectiveArmorLevel,
        'effectiveArmorLevelAfterRending' => $effectiveArmorLevelAfterRending,
        'armorMod'      => $armorMod
    );
}

// https://github.com/ACEmulator/ACE/blob/8f9e96fbdaf0d86cb1fffa4c64115ec973e846b0/Source/ACE.Server/WorldObjects/Creature_Combat.cs#L643
function getShieldModObj(
    $floats,
    $armorLevel,
    $damageType,
    $armorAddedFromEnchantment = 0, // From either Imperil (negative) or Armor (positive)
    $ignoreShieldValue = 0,
    $ignoreMagicArmor = false,
    $ignoreMagicResist = false,
    $phantasmal = false
) {
    $armorModVsType = getArmorModVsType($floats, $damageType);
    $armorVsType = $armorLevel * $armorModVsType;
    
    // Called enchantmentMod in ACE
    // Enchantments are ignored if the weapon has Float::IgnoreMagicResist
    $effectiveArmorAddedFromEnchantment = $ignoreMagicResist ? 0 : $armorAddedFromEnchantment;
    
    $effectiveArmorLevel = $armorVsType + $effectiveArmorAddedFromEnchantment;
    
    // At this point we're ignoring armor layers, which I think might be clothing/armor banes
    // Can we safely ignore it for monsters?
    
    $ignoreShieldMod = getIgnoreShieldMod($ignoreShieldValue);
    
    $effectiveArmorLevelAfterIgnore = $effectiveArmorLevel;
    if ($effectiveArmorLevel > 0) {
        $effectiveArmorLevelAfterIgnore *= $ignoreShieldMod;
    }
    
    if ($phantasmal) {
        $effectiveArmorLevel = 0;
        $effectiveArmorLevelAfterIgnore = 0;
    }
    
    $shieldMod = calcArmorMod($effectiveArmorLevelAfterIgnore);
    
    return array(
        'armorLevel'     => $armorLevel,
        'armorModVsType' => $armorModVsType,
        'armorVsType'    => $armorVsType,
        'ignoreMagicArmor'  => $ignoreMagicArmor,
        'ignoreMagicResist' => $ignoreMagicResist,
        'ignoreShieldValue' => $ignoreShieldValue,
        'ignoreShieldMod'   => $ignoreShieldMod,
        'armorAddedFromEnchantment' => $armorAddedFromEnchantment,
        'effectiveArmorAddedFromEnchantment' => $effectiveArmorAddedFromEnchantment,
        'effectiveArmorLevel' => $effectiveArmorLevel,
        'effectiveArmorLevelAfterIgnore' => $effectiveArmorLevelAfterIgnore,
        'shieldMod'      => $shieldMod
    );
}

// See https://github.com/ACEmulator/ACE/blob/7d61b543e3c24652c4db0ddfee0eb829b7cf09cf/Source/ACE.Server/WorldObjects/Creature_Properties.cs#L160
function getArmorModVsType($floats, $damageType) {
    $armorModProp = DAMAGE_TYPES_MAP[$damageType]['ArmorModProperty'];
    return isset($floats[$armorModProp]) ? round($floats[$armorModProp], 2) : 1;
}

// See https://github.com/ACEmulator/ACE/blob/8f9e96fbdaf0d86cb1fffa4c64115ec973e846b0/Source/ACE.Server/WorldObjects/WorldObject_Weapon.cs#L804
function getIgnoreShieldMod($ignoreShieldValue) {
    if (!$ignoreShieldValue) {
        $ignoreShieldValue = 0;
    }

    return 1 - $ignoreShieldValue;
}

// See https://github.com/ACEmulator/ACE/blob/7d61b543e3c24652c4db0ddfee0eb829b7cf09cf/Source/ACE.Server/WorldObjects/Creature_Properties.cs#L185
function getResistanceModVsType($floats, $damageType) {
    $resistModProp = DAMAGE_TYPES_MAP[$damageType]['ResistProperty'];
    return isset($floats[$resistModProp]) ? round($floats[$resistModProp], 2) : 1;
}

function getMagicResistances($floats) {
    $resistances = array();
    
    foreach (DAMAGE_TYPES_MAP as $damageType => $damageProps) {
        $resistance = isset($floats[$damageProps['ResistProperty']]) ? $floats[$damageProps['ResistProperty']] : 0;
        
        if ($resistance) {
            $resistances[$damageType] = round($resistance, 2);
        }
    }
    
    return $resistances;
}

function getArmorRange($effectiveArmor, $key) {
    $min = null;
    $max = null;
    
    foreach ($effectiveArmor as $bodyPart => $bodyPartArmor) {
        foreach ($bodyPartArmor as $damageType => $values) {
            $value = $values[$key];
            
            if ($value == 'INF') {
                continue;
            }
            
            if ($min == null || $value < $min) {
                $min = $value;
            }
            
            if ($max == null || $value > $max) {
                $max = $value;
            }
        }
    }
    
    return array(
        'min' => $min,
        'max' => $max
    );
}

function getWieldedItems($weenieId) {
    global $dbh;

    /**
     * wps.type 1 is the weenie name
     * wpi.type 45 is the damage type
     * wdid.type 32 is the wielded treasure type
     */
    $statement = $dbh->prepare("select 
                                    weenie.class_Id id,
                                    weenie.class_Name code,
                                    tw.probability probability, 
                                    wps.value name,
                                    wpi.value damageType,
                                    wpiD.value damage,
                                    wpfDv.value damageVariance,
                                    wpAL.value armorLevel
                                from
                                    weenie_properties_d_i_d wdid 
                                    join treasure_wielded tw on (wdid.value = tw.treasure_Type and wdid.type = 32)
                                    join weenie on weenie.class_Id = tw.weenie_Class_Id
                                    join weenie_properties_string wps on (wps.object_Id = weenie.class_Id and wps.type = 1) 
                                    left join weenie_properties_int wpi on (wpi.object_Id = weenie.class_Id and wpi.type = 45)
                                    left join weenie_properties_int wpiD on (wpiD.object_Id = weenie.class_id and wpiD.type = 44)
                                    left join weenie_properties_float wpfDv on (wpfDv.object_Id = weenie.class_id and wpfDv.type = 22)
                                    left join weenie_properties_int wpAL on (wpAL.object_Id = weenie.class_Id and wpAL.type = 28)
                                where
                                    wdid.object_Id = ?
                                order by probability desc");
    $statement->execute(array($weenieId));

    $items = array();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $items[] = array(
            'id'    => $row['id'],
            'name'  => $row['name'],
            'probability' => $row['probability'],
            'code'      => $row['code'],
            'damageType' => $row['damage'] > 0 ? getDamageTypeLabel($row['damageType']) : '',
            'damage'    => $row['damage'],
            'minDamage' => getMinDamage($row['damage'], $row['damageVariance']),
            'armorLevel'    => $row['armorLevel']
        );
    }
    
    return $items;    
}


function getWieldedShields($weenieId) {
    global $dbh;

    /**
     * wdid.type 32 is the wielded treasure type
     * wps.type 1 is the weenie name
     */
    $statement = $dbh->prepare("select 
                                    weenie.class_Id id,
                                    weenie.class_Name code,
                                    tw.probability probability, 
                                    wps.value name,
                                    wpAL.value armorLevel
                                from
                                    weenie_properties_d_i_d wdid 
                                    join treasure_wielded tw on (wdid.value = tw.treasure_Type and wdid.type = 32)
                                    join weenie on weenie.class_Id = tw.weenie_Class_Id
                                    join weenie_properties_string wps on (wps.object_Id = weenie.class_Id and wps.type = 1) 
                                    left join weenie_properties_int wpAL on (wpAL.object_Id = weenie.class_Id and wpAL.type = 28)
                                where
                                    wdid.object_Id = ?
                                    and wpAL.value > 0
                                order by probability desc");
    $statement->execute(array($weenieId));

    $items = array();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $items[] = array(
            'id'            => $row['id'],
            'name'          => $row['name'],
            'probability'   => $row['probability'],
            'code'          => $row['code'],
            'armorLevel'    => $row['armorLevel']
        );
    }
    
    return $items;
}

function getWeenie($id) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                        weenie.class_Id id,
                                        wps.value name,
                                        weenie.type type,
                                        weenie.class_Name code
                                    from weenie 
                                        join weenie_properties_string wps on (wps.object_Id = weenie.class_Id and wps.type = 1) 
                                    where 
                                        weenie.class_Id = ?");

    $statement->execute(array($id));
    $weenie = $statement->fetch(PDO::FETCH_ASSOC);
    
    return $weenie;
}

function getMinDamage($damage, $variance) {
    return round($damage * (1 - $variance), 2);
}

function getDamageTypeLabel($damageTypeBitMask) {
    return DAMAGE_TYPE_INT[$damageTypeBitMask];
}

function getDamageTypeLabelFromBitmask($damageTypeBitMask) {
    $vals = array();
    
    foreach (DAMAGE_TYPE as $key => $val) {
        if ($val & $damageTypeBitMask) {
            $vals[] = $key;
        }
    }
    
    return implode('/', $vals);
}

function percentageBetween($x, $a, $b) {
    if ($x == 'INF') {
        return 1;
    }

    if ($b - $a == 0) {
        return 1;
    }

    return round(($x - $a) / ($b - $a), 2);
}

function getSpecialProperties($floats, $ints, $bools) {
    $properties = array();

    if (isset($bools[PropertyBool::IgnoreMagicResist])) {
        $properties[] = array(
            'name'          => 'Ignores Life Protections', 
            'description'   => 'Creature damage fully ignores your life protections'
        );
    }

    if (isset($bools[PropertyBool::IgnoreMagicArmor])) {
        $properties[] = array(
            'name'          => 'Ignores Magic Banes', 
            'description'   => 'Creature damage fully ignores your armor banes'
        );
    }

    if (isset($bools[PropertyBool::NonProjectileMagicImmune])) {
        $properties[] = array(
            'name'          => 'Immune to Debuffs', 
            'description'   => 'All creature and life debuffs will fail'
        );
    }

    if (isset($floats[PropertyFloat::IgnoreArmor])) {
        $properties[] = array(
            'name'          => 'Ignores Physical Armor', 
            'description'   => "Your effective physical armor level is reduced roughly 10% - 50% depending on the creature."
        );
    }

    if (isset($floats[PropertyFloat::IgnoreShield])) {
        $effectiveAL = 100 - (100 * $floats[PropertyFloat::IgnoreShield]);
        $properties[] = array(
            'name'          => 'Ignores Shield', 
            'description'   => "Your shield armor level is effectively <b>{$effectiveAL}%</b> of its normal value."
        );
    }
    
    return $properties;
}

function getRegenRates($floats) {
    $rates = array();
    $rates['health'] = round($floats[PropertyFloat::HealthRate] / 5, 1);
    $rates['stamina'] = round($floats[PropertyFloat::StaminaRate] / 5, 1);
    $rates['mana'] = round($floats[PropertyFloat::ManaRate] / 5, 1);
    
    return $rates;
}

function getTreasureDeath($treasureType) {
    global $dbh;

    $statement = $dbh->prepare("select * from treasure_death
                                        where treasure_Type = ?");
    $statement->execute(array($treasureType));
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    
    return $row;
}

// usage is source, target, success, fail
function getRecipesByWeenieId($usage, $id) {
    global $dbh;
    
    if ($usage == 'source' || $usage == 'target') {
        $column = "`cook_book`.`${usage}_W_C_I_D`";
        
    } else if ($usage == 'success' || $usage == 'fail') {
        $column = "`recipe`.`${usage}_W_C_I_D`";
    } else {
        throw new Error("Invalid usage: ${usage}");
    }

    $statement = $dbh->prepare("select 
                                    cook_book.id cookBookId,
                                    cook_book.source_W_C_I_D sourceWeenieId,
                                    wpsSource.value sourceWeenieName,
                                    cook_book.target_W_C_I_D targetWeenieId,
                                    wpsTarget.value targetWeenieName,
                                    recipe.id recipeId,
                                    recipe.skill skill,
                                    recipe.difficulty difficulty,
                                    recipe.success_W_C_I_D successWeenieId,
                                    wpsSuccess.value successWeenieName,
                                    recipe.success_Amount successAmount,
                                    recipe.success_Message successMessage,
                                    recipe.success_Destroy_Source_Chance successDestroySourceChance,
                                    recipe.success_Destroy_Source_Amount successDestroySourceAmount,
                                    recipe.success_Destroy_Source_Message successDestroySourceMessage,
                                    recipe.fail_W_C_I_D failWeenieId,
                                    wpsFail.value failWeenieName,
                                    recipe.fail_Amount failAmount,
                                    recipe.fail_Message failMessage,
                                    recipe.fail_Destroy_Source_Chance failDestroySourceChance,
                                    recipe.fail_Destroy_Source_Amount failDestroySourceAmount,
                                    recipe.fail_Destroy_Source_Message failDestroySourceMessage                                    
                                from
                                    cook_book
                                    join recipe on cook_book.recipe_Id = recipe.id
                                    left join weenie_properties_string wpsSource on (wpsSource.type = 1 and wpsSource.object_Id = cook_book.source_W_C_I_D)
                                    left join weenie_properties_string wpsTarget on (wpsTarget.type = 1 and wpsTarget.object_Id = cook_book.target_W_C_I_D)
                                    left join weenie_properties_string wpsSuccess on (wpsSuccess.type = 1 and wpsSuccess.object_Id = recipe.success_W_C_I_D)
                                    left join weenie_properties_string wpsFail on (wpsFail.type = 1 and wpsFail.object_Id = recipe.fail_W_C_I_D)
                                where
                                    ${column} = ?
                                order by difficulty asc, ${column} asc");

    $statement->execute(array($id));
    
    $recipes = array();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $recipes[] = $row;
    }
    
    return $recipes;
}

function array_clone($array) {
    return array_map(function($element) {
        return ((is_array($element))
            ? array_clone($element)
            : ((is_object($element))
                ? clone $element
                : $element
            )
        );
    }, $array);
}

// usage can be success or fail
function getRecipes($usage, $id) {
    global $dbh;
    
    $column = $usage == 'fail' ? 'recipe.fail_W_C_I_D' : 'recipe.success_W_C_I_D';
    $usageKey = $usage == 'fail' ? 'failWeenieId' : 'successWeenieId';

    $q = "select 
                                    cook_book.id cookBookId,
                                    cook_book.source_W_C_I_D sourceWeenieId,
                                    wpsSource.value sourceWeenieName,
                                    cook_book.target_W_C_I_D targetWeenieId,
                                    wpsTarget.value targetWeenieName,
                                    recipe.id recipeId,
                                    recipe.skill skill,
                                    recipe.difficulty difficulty,
                                    recipe.success_W_C_I_D successWeenieId,
                                    wpsSuccess.value successWeenieName,
                                    recipe.success_Amount successAmount,
                                    recipe.success_Message successMessage,
                                    recipe.success_Destroy_Source_Chance successDestroySourceChance,
                                    recipe.success_Destroy_Source_Amount successDestroySourceAmount,
                                    recipe.success_Destroy_Source_Message successDestroySourceMessage,
                                    recipe.fail_W_C_I_D failWeenieId,
                                    wpsFail.value failWeenieName,
                                    recipe.fail_Amount failAmount,
                                    recipe.fail_Message failMessage,
                                    recipe.fail_Destroy_Source_Chance failDestroySourceChance,
                                    recipe.fail_Destroy_Source_Amount failDestroySourceAmount,
                                    recipe.fail_Destroy_Source_Message failDestroySourceMessage                                    
                                from
                                    cook_book
                                    join recipe on cook_book.recipe_Id = recipe.id
                                    left join weenie_properties_string wpsSource on (wpsSource.type = 1 and wpsSource.object_Id = cook_book.source_W_C_I_D)
                                    left join weenie_properties_string wpsTarget on (wpsTarget.type = 1 and wpsTarget.object_Id = cook_book.target_W_C_I_D)
                                    left join weenie_properties_string wpsSuccess on (wpsSuccess.type = 1 and wpsSuccess.object_Id = recipe.success_W_C_I_D)
                                    left join weenie_properties_string wpsFail on (wpsFail.type = 1 and wpsFail.object_Id = recipe.fail_W_C_I_D)
                                where
                                    $column = ?
                                limit 30";

    $statement = $dbh->prepare($q);
    
    $recipes = array();
    $statement->execute(array($id));
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $recipes[] = $row;
    }
    
    return $recipes;
}

function getRecipeLists($usage, $id, $recipeList = array()) {
    $recipes = getRecipes($usage, $id);
    
    $lists = array();
    foreach ($recipes as $recipe) {
        $lists[] = array($recipe);
    }
    
    $usageTypes = array('source', 'target');

    $count = 1;
    while (true) {
        $hasNew = false;

        $listsCount = count($lists);
        for ($l = 0; $l < $listsCount; $l++) {
            
            // Make sure to find both the source and target crafting parents
            foreach ($usageTypes as $usageType) {
                $lastRecipe = end($lists[$l]);

                $usageKey = $usageType . 'WeenieId';

                $parentRecipes = getRecipes('success', $lastRecipe[$usageKey]);
                $parentRecipesCount = count($parentRecipes);
                if ($parentRecipesCount >= 1) {
                    $hasNew = true;

                    // Additional recipes need to be created as a new list
                    // Do this before adding the top recipe to the current list                                
                    if ($parentRecipesCount > 1) {
                        for ($s = 1; $s < $parentRecipesCount; $s++) {                        
                            $lists[] = array_merge(
                                array_clone($lists[$l]),
                                array($parentRecipes[$s])
                            );

        
                            $count++;
                            
                            if ($count >= 30) {
                                return $lists;
                            }
                        }                    
                    }

                    // Add to the current list
                    $lists[$l][] = $parentRecipes[0];
                }
            }
        }
                
        if (!$hasNew) {
            break;
        }
    }

    return $lists;
}

// See https://github.com/ACEmulator/ACE/blob/d374a8fc261dd09abc2e16607c6c202e20599937/Source/ACE.Server/WorldObjects/SkillCheck.cs#L7
$craftingSkills = array();
function getCraftingSkillForChance($difficulty, $chance) {
    $key = $difficulty . '-' . $chance;
    if (isset($craftingSkills[$key])) {
        return $craftingSkills[$key];
    }

    $result = (log((1 / (1 - $chance)) - 1) / 0.03) + $difficulty;
    $returner = round($result);
    
    $craftingSkills[$key] = $returner;
    return $returner;
}

const CRAFTING_TYPES = [
    1,
    2,
    3,
    4,
    5,
    6,
    8,
    18,
    22,
    26,
    28,
    32,
    34,
    35,
    38,
    44,
    51,
    63,
    64
];

const WEAPON_WEENIE_TYPES = [
    6 // MeleeWeapon
];

class PropertyFloat {
    // properties marked as ServerOnly are properties we never saw in PCAPs, from here:
    // http://ac.yotesfan.com/ace_object/not_used_enums.php
    // source: @OptimShi
    // description attributes are used by the weenie editor for a cleaner display name

    const Undef                          = 0;
    const HeartbeatInterval              = 1;
    const HeartbeatTimestamp             = 2;
    const HealthRate                     = 3;
    const StaminaRate                    = 4;
    const ManaRate                       = 5;
    const HealthUponResurrection         = 6;
    const StaminaUponResurrection        = 7;
    const ManaUponResurrection           = 8;
    const StartTime                      = 9;
    const StopTime                       = 10;
    const ResetInterval                  = 11;
    const Shade                          = 12;
    const ArmorModVsSlash                = 13;
    const ArmorModVsPierce               = 14;
    const ArmorModVsBludgeon             = 15;
    const ArmorModVsCold                 = 16;
    const ArmorModVsFire                 = 17;
    const ArmorModVsAcid                 = 18;
    const ArmorModVsElectric             = 19;
    const CombatSpeed                    = 20;
    const WeaponLength                   = 21;
    const DamageVariance                 = 22;
    const CurrentPowerMod                = 23;
    const AccuracyMod                    = 24;
    const StrengthMod                    = 25;
    const MaximumVelocity                = 26;
    const RotationSpeed                  = 27;
    const MotionTimestamp                = 28;
    const WeaponDefense                  = 29;
    const WimpyLevel                     = 30;
    const VisualAwarenessRange           = 31;
    const AuralAwarenessRange            = 32;
    const PerceptionLevel                = 33;
    const PowerupTime                    = 34;
    const MaxChargeDistance              = 35;
    const ChargeSpeed                    = 36;
    const BuyPrice                       = 37;
    const SellPrice                      = 38;
    const DefaultScale                   = 39;
    const LockpickMod                    = 40;
    const RegenerationInterval           = 41;
    const RegenerationTimestamp          = 42;
    const GeneratorRadius                = 43;
    const TimeToRot                      = 44;
    const DeathTimestamp                 = 45;
    const PkTimestamp                    = 46;
    const VictimTimestamp                = 47;
    const LoginTimestamp                 = 48;
    const CreationTimestamp              = 49;
    const MinimumTimeSincePk             = 50;
    const DeprecatedHousekeepingPriority = 51;
    const AbuseLoggingTimestamp          = 52;
    const LastPortalTeleportTimestamp    = 53;
    const UseRadius                      = 54;
    const HomeRadius                     = 55;
    const ReleasedTimestamp              = 56;
    const MinHomeRadius                  = 57;
    const Facing                         = 58;
    const ResetTimestamp                 = 59;
    const LogoffTimestamp                = 60;
    const EconRecoveryInterval           = 61;
    const WeaponOffense                  = 62;
    const DamageMod                      = 63;
    const ResistSlash                    = 64;
    const ResistPierce                   = 65;
    const ResistBludgeon                 = 66;
    const ResistFire                     = 67;
    const ResistCold                     = 68;
    const ResistAcid                     = 69;
    const ResistElectric                 = 70;
    const ResistHealthBoost              = 71;
    const ResistStaminaDrain             = 72;
    const ResistStaminaBoost             = 73;
    const ResistManaDrain                = 74;
    const ResistManaBoost                = 75;
    const Translucency                   = 76;
    const PhysicsScriptIntensity         = 77;
    const Friction                       = 78;
    const Elasticity                     = 79;
    const AiUseMagicDelay                = 80;
    const ItemMinSpellcraftMod           = 81;
    const ItemMaxSpellcraftMod           = 82;
    const ItemRankProbability            = 83;
    const Shade2                         = 84;
    const Shade3                         = 85;
    const Shade4                         = 86;
    const ItemEfficiency                 = 87;
    const ItemManaUpdateTimestamp        = 88;
    const SpellGestureSpeedMod           = 89;
    const SpellStanceSpeedMod            = 90;
    const AllegianceAppraisalTimestamp   = 91;
    const PowerLevel                     = 92;
    const AccuracyLevel                  = 93;
    const AttackAngle                    = 94;
    const AttackTimestamp                = 95;
    const CheckpointTimestamp            = 96;
    const SoldTimestamp                  = 97;
    const UseTimestamp                   = 98;
    const UseLockTimestamp               = 99;
    const HealkitMod                     = 100;
    const FrozenTimestamp                = 101;
    const HealthRateMod                  = 102;
    const AllegianceSwearTimestamp       = 103;
    const ObviousRadarRange              = 104;
    const HotspotCycleTime               = 105;
    const HotspotCycleTimeVariance       = 106;
    const SpamTimestamp                  = 107;
    const SpamRate                       = 108;
    const BondWieldedTreasure            = 109;
    const BulkMod                        = 110;
    const SizeMod                        = 111;
    const GagTimestamp                   = 112;
    const GeneratorUpdateTimestamp       = 113;
    const DeathSpamTimestamp             = 114;
    const DeathSpamRate                  = 115;
    const WildAttackProbability          = 116;
    const FocusedProbability             = 117;
    const CrashAndTurnProbability        = 118;
    const CrashAndTurnRadius             = 119;
    const CrashAndTurnBias               = 120;
    const GeneratorInitialDelay          = 121;
    const AiAcquireHealth                = 122;
    const AiAcquireStamina               = 123;
    const AiAcquireMana                  = 124;
    const ResistHealthDrain              = 125;
    const LifestoneProtectionTimestamp   = 126;
    const AiCounteractEnchantment        = 127;
    const AiDispelEnchantment            = 128;
    const TradeTimestamp                 = 129;
    const AiTargetedDetectionRadius      = 130;
    const EmotePriority                  = 131;
    const LastTeleportStartTimestamp     = 132;
    const EventSpamTimestamp             = 133;
    const EventSpamRate                  = 134;
    const InventoryOffset                = 135;
    const CriticalMultiplier             = 136;
    const ManaStoneDestroyChance         = 137;
    const SlayerDamageBonus              = 138;
    const AllegianceInfoSpamTimestamp    = 139;
    const AllegianceInfoSpamRate         = 140;
    const NextSpellcastTimestamp         = 141;
    const AppraisalRequestedTimestamp    = 142;
    const AppraisalHeartbeatDueTimestamp = 143;
    const ManaConversionMod              = 144;
    const LastPkAttackTimestamp          = 145;
    const FellowshipUpdateTimestamp      = 146;
    const CriticalFrequency              = 147;
    const LimboStartTimestamp            = 148;
    const WeaponMissileDefense           = 149;
    const WeaponMagicDefense             = 150;
    const IgnoreShield                   = 151;
    const ElementalDamageMod             = 152;
    const StartMissileAttackTimestamp    = 153;
    const LastRareUsedTimestamp          = 154;
    const IgnoreArmor                    = 155;
    const ProcSpellRate                  = 156;
    const ResistanceModifier             = 157;
    const AllegianceGagTimestamp         = 158;
    const AbsorbMagicDamage              = 159;
    const CachedMaxAbsorbMagicDamage     = 160;
    const GagDuration                    = 161;
    const AllegianceGagDuration          = 162;
    const GlobalXpMod                    = 163;
    const HealingModifier                = 164;
    const ArmorModVsNether               = 165;
    const ResistNether                   = 166;
    const CooldownDuration               = 167;
    const WeaponAuraOffense              = 168;
    const WeaponAuraDefense              = 169;
    const WeaponAuraElemental            = 170;
    const WeaponAuraManaConv             = 171;
}

class PropertyInt {
    const Undef                                    = 0;
    const ItemType                                 = 1;
    const CreatureType                             = 2;
    const PaletteTemplate                          = 3;
    const ClothingPriority                         = 4;
    const EncumbranceVal                           = 5; // ENCUMB_VAL_INT;
    const ItemsCapacity                            = 6;
    const ContainersCapacity                       = 7;
    const Mass                                     = 8;
    const ValidLocations                           = 9; // LOCATIONS_INT
    const CurrentWieldedLocation                   = 10;
    const MaxStackSize                             = 11;
    const StackSize                                = 12;
    const StackUnitEncumbrance                     = 13;
    const StackUnitMass                            = 14;
    const StackUnitValue                           = 15;
    const ItemUseable                              = 16;
    const RareId                                   = 17;
    const UiEffects                                = 18;
    const Value                                    = 19;
    const CoinValue                                = 20;
    const TotalExperience                          = 21;
    const AvailableCharacter                       = 22;
    const TotalSkillCredits                        = 23;
    const AvailableSkillCredits                    = 24;
    const Level                                    = 25;
    const AccountRequirements                      = 26;
    const ArmorType                                = 27;
    const ArmorLevel                               = 28;
    const AllegianceCpPool                         = 29;
    const AllegianceRank                           = 30;
    const ChannelsAllowed                          = 31;
    const ChannelsActive                           = 32;
    const Bonded                                   = 33;
    const MonarchsRank                             = 34;
    const AllegianceFollowers                      = 35;
    const ResistMagic                              = 36;
    const ResistItemAppraisal                      = 37;
    const ResistLockpick                           = 38;
    const DeprecatedResistRepair                   = 39;
    const CombatMode                               = 40;
    const CurrentAttackHeight                      = 41;
    const CombatCollisions                         = 42;
    const NumDeaths                                = 43;
    const Damage                                   = 44;
    const DamageType                               = 45;
    const DefaultCombatStyle                       = 46;
    const AttackType                               = 47;
    const WeaponSkill                              = 48;
    const WeaponTime                               = 49;
    const AmmoType                                 = 50;
    const CombatUse                                = 51;
    const ParentLocation                           = 52;
    const PlacementPosition                        = 53;
    const WeaponEncumbrance                        = 54;
    const WeaponMass                               = 55;
    const ShieldValue                              = 56;
    const ShieldEncumbrance                        = 57;
    const MissileInventoryLocation                 = 58;
    const FullDamageType                           = 59;
    const WeaponRange                              = 60;
    const AttackersSkill                           = 61;
    const DefendersSkill                           = 62;
    const AttackersSkillValue                      = 63;
    const AttackersClass                           = 64;
    const Placement                                = 65;
    const CheckpointStatus                         = 66;
    const Tolerance                                = 67;
    const TargetingTactic                          = 68;
    const CombatTactic                             = 69;
    const HomesickTargetingTactic                  = 70;
    const NumFollowFailures                        = 71;
    const FriendType                               = 72;
    const FoeType                                  = 73;
    const MerchandiseItemTypes                     = 74;
    const MerchandiseMinValue                      = 75;
    const MerchandiseMaxValue                      = 76;
    const NumItemsSold                             = 77;
    const NumItemsBought                           = 78;
    const MoneyIncome                              = 79;
    const MoneyOutflow                             = 80;
    const MaxGeneratedObjects                      = 81;
    const InitGeneratedObjects                     = 82;
    const ActivationResponse                       = 83;
    const OriginalValue                            = 84;
    const NumMoveFailures                          = 85;
    const MinLevel                                 = 86;
    const MaxLevel                                 = 87;
    const LockpickMod                              = 88;
    const BoosterEnum                              = 89;
    const BoostValue                               = 90;
    const MaxStructure                             = 91;
    const Structure                                = 92;
    const PhysicsState                             = 93;
    const TargetType                               = 94;
    const RadarBlipColor                           = 95;
    const EncumbranceCapacity                      = 96;
    const LoginTimestamp                           = 97;
    const CreationTimestamp                        = 98;
    const PkLevelModifier                          = 99;
    const GeneratorType                            = 100;
    const AiAllowedCombatStyle                     = 101;
    const LogoffTimestamp                          = 102;
    const GeneratorDestructionType                 = 103;
    const ActivationCreateClass                    = 104;
    const ItemWorkmanship                          = 105;
    const ItemSpellcraft                           = 106;
    const ItemCurMana                              = 107;
    const ItemMaxMana                              = 108;
    const ItemDifficulty                           = 109;
    const ItemAllegianceRankLimit                  = 110;
    const PortalBitmask                            = 111;
    const AdvocateLevel                            = 112;
    const Gender                                   = 113;
    const Attuned                                  = 114;
    const ItemSkillLevelLimit                      = 115;
    const GateLogic                                = 116;
    const ItemManaCost                             = 117;
    const Logoff                                   = 118;
    const Active                                   = 119;
    const AttackHeight                             = 120;
    const NumAttackFailures                        = 121;
    const AiCpThreshold                            = 122;
    const AiAdvancementStrategy                    = 123;
    const Version                                  = 124;
    const Age                                      = 125;
    const VendorHappyMean                          = 126;
    const VendorHappyVariance                      = 127;
    const CloakStatus                              = 128;
    const VitaeCpPool                              = 129;
    const NumServicesSold                          = 130;
    const MaterialType                             = 131;
    const NumAllegianceBreaks                      = 132;
    const ShowableOnRadar                          = 133;
    const PlayerKillerStatus                       = 134;
    const VendorHappyMaxItems                      = 135;
    const ScorePageNum                             = 136;
    const ScoreConfigNum                           = 137;
    const ScoreNumScores                           = 138;
    const DeathLevel                               = 139;
    const AiOptions                                = 140;
    const OpenToEveryone                           = 141;
    const GeneratorTimeType                        = 142;
    const GeneratorStartTime                       = 143;
    const GeneratorEndTime                         = 144;
    const GeneratorEndDestructionType              = 145;
    const XpOverride                               = 146;
    const NumCrashAndTurns                         = 147;
    const ComponentWarningThreshold                = 148;
    const HouseStatus                              = 149;
    const HookPlacement                            = 150;
    const HookType                                 = 151;
    const HookItemType                             = 152;
    const AiPpThreshold                            = 153;
    const GeneratorVersion                         = 154;
    const HouseType                                = 155;
    const PickupEmoteOffset                        = 156;
    const WeenieIteration                          = 157;
    const WieldRequirements                        = 158;
    const WieldSkillType                           = 159;
    const WieldDifficulty                          = 160;
    const HouseMaxHooksUsable                      = 161;
    const HouseCurrentHooksUsable                  = 162;
    const AllegianceMinLevel                       = 163;
    const AllegianceMaxLevel                       = 164;
    const HouseRelinkHookCount                     = 165;
    const SlayerCreatureType                       = 166;
    const ConfirmationInProgress                   = 167;
    const ConfirmationTypeInProgress               = 168;
    const TsysMutationData                         = 169;
    const NumItemsInMaterial                       = 170;
    const NumTimesTinkered                         = 171;
    const AppraisalLongDescDecoration              = 172;
    const AppraisalLockpickSuccessPercent          = 173;
    const AppraisalPages                           = 174;
    const AppraisalMaxPages                        = 175;
    const AppraisalItemSkill                       = 176;
    const GemCount                                 = 177;
    const GemType                                  = 178;
    const ImbuedEffect                             = 179;
    const AttackersRawSkillValue                   = 180;
    const ChessRank                                = 181;
    const ChessTotalGames                          = 182;
    const ChessGamesWon                            = 183;
    const ChessGamesLost                           = 184;
    const TypeOfAlteration                         = 185;
    const SkillToBeAltered                         = 186;
    const SkillAlterationCount                     = 187;
    const HeritageGroup                            = 188;
    const TransferFromAttribute                    = 189;
    const TransferToAttribute                      = 190;
    const AttributeTransferCount                   = 191;
    const FakeFishingSkill                         = 192;
    const NumKeys                                  = 193;
    const DeathTimestamp                           = 194;
    const PkTimestamp                              = 195;
    const VictimTimestamp                          = 196;
    const HookGroup                                = 197;
    const AllegianceSwearTimestamp                 = 198;
    const HousePurchaseTimestamp                   = 199;
    const RedirectableEquippedArmorCount           = 200;
    const MeleeDefenseImbuedEffectTypeCache        = 201;
    const MissileDefenseImbuedEffectTypeCache      = 202;
    const MagicDefenseImbuedEffectTypeCache        = 203;
    const ElementalDamageBonus                     = 204;
    const ImbueAttempts                            = 205;
    const ImbueSuccesses                           = 206;
    const CreatureKills                            = 207;
    const PlayerKillsPk                            = 208;
    const PlayerKillsPkl                           = 209;
    const RaresTierOne                             = 210;
    const RaresTierTwo                             = 211;
    const RaresTierThree                           = 212;
    const RaresTierFour                            = 213;
    const RaresTierFive                            = 214;
    const AugmentationStat                         = 215;
    const AugmentationFamilyStat                   = 216;
    const AugmentationInnateFamily                 = 217;
    const AugmentationInnateStrength               = 218;
    const AugmentationInnateEndurance              = 219;
    const AugmentationInnateCoordination           = 220;
    const AugmentationInnateQuickness              = 221;
    const AugmentationInnateFocus                  = 222;
    const AugmentationInnateSelf                   = 223;
    const AugmentationSpecializeSalvaging          = 224;
    const AugmentationSpecializeItemTinkering      = 225;
    const AugmentationSpecializeArmorTinkering     = 226;
    const AugmentationSpecializeMagicItemTinkering = 227;
    const AugmentationSpecializeWeaponTinkering    = 228;
    const AugmentationExtraPackSlot                = 229;
    const AugmentationIncreasedCarryingCapacity    = 230;
    const AugmentationLessDeathItemLoss            = 231;
    const AugmentationSpellsRemainPastDeath        = 232;
    const AugmentationCriticalDefense              = 233;
    const AugmentationBonusXp                      = 234;
    const AugmentationBonusSalvage                 = 235;
    const AugmentationBonusImbueChance             = 236;
    const AugmentationFasterRegen                  = 237;
    const AugmentationIncreasedSpellDuration       = 238;
    const AugmentationResistanceFamily             = 239;
    const AugmentationResistanceSlash              = 240;
    const AugmentationResistancePierce             = 241;
    const AugmentationResistanceBlunt              = 242;
    const AugmentationResistanceAcid               = 243;
    const AugmentationResistanceFire               = 244;
    const AugmentationResistanceFrost              = 245;
    const AugmentationResistanceLightning          = 246;
    const RaresTierOneLogin                        = 247;
    const RaresTierTwoLogin                        = 248;
    const RaresTierThreeLogin                      = 249;
    const RaresTierFourLogin                       = 250;
    const RaresTierFiveLogin                       = 251;
    const RaresLoginTimestamp                      = 252;
    const RaresTierSix                             = 253;
    const RaresTierSeven                           = 254;
    const RaresTierSixLogin                        = 255;
    const RaresTierSevenLogin                      = 256;
    const ItemAttributeLimit                       = 257;
    const ItemAttributeLevelLimit                  = 258;
    const ItemAttribute2ndLimit                    = 259;
    const ItemAttribute2ndLevelLimit               = 260;
    const CharacterTitleId                         = 261;
    const NumCharacterTitles                       = 262;
    const ResistanceModifierType                   = 263;
    const FreeTinkersBitfield                      = 264;
    const EquipmentSetId                           = 265;
    const PetClass                                 = 266;
    const Lifespan                                 = 267;
    const RemainingLifespan                        = 268;
    const UseCreateQuantity                        = 269;
    const WieldRequirements2                       = 270;
    const WieldSkillType2                          = 271;
    const WieldDifficulty2                         = 272;
    const WieldRequirements3                       = 273;
    const WieldSkillType3                          = 274;
    const WieldDifficulty3                         = 275;
    const WieldRequirements4                       = 276;
    const WieldSkillType4                          = 277;
    const WieldDifficulty4                         = 278;
    const Unique                                   = 279;
    const SharedCooldown                           = 280;
    const Faction1Bits                             = 281;
    const Faction2Bits                             = 282;
    const Faction3Bits                             = 283;
    const Hatred1Bits                              = 284;
    const Hatred2Bits                              = 285;
    const Hatred3Bits                              = 286;
    const SocietyRankCelhan                        = 287;
    const SocietyRankEldweb                        = 288;
    const SocietyRankRadblo                        = 289;
    const HearLocalSignals                         = 290;
    const HearLocalSignalsRadius                   = 291;
    const Cleaving                                 = 292;
    const AugmentationSpecializeGearcraft          = 293;
    const AugmentationInfusedCreatureMagic         = 294;
    const AugmentationInfusedItemMagic             = 295;
    const AugmentationInfusedLifeMagic             = 296;
    const AugmentationInfusedWarMagic              = 297;
    const AugmentationCriticalExpertise            = 298;
    const AugmentationCriticalPower                = 299;
    const AugmentationSkilledMelee                 = 300;
    const AugmentationSkilledMissile               = 301;
    const AugmentationSkilledMagic                 = 302;
    const ImbuedEffect2                            = 303;
    const ImbuedEffect3                            = 304;
    const ImbuedEffect4                            = 305;
    const ImbuedEffect5                            = 306;
    const DamageRating                             = 307;
    const DamageResistRating                       = 308;
    const AugmentationDamageBonus                  = 309;
    const AugmentationDamageReduction              = 310;
    const ImbueStackingBits                        = 311;
    const HealOverTime                             = 312;
    const CritRating                               = 313;
    const CritDamageRating                         = 314;
    const CritResistRating                         = 315;
    const CritDamageResistRating                   = 316;
    const HealingResistRating                      = 317;
    const DamageOverTime                           = 318;
    const ItemMaxLevel                             = 319;
    const ItemXpStyle                              = 320;
    const EquipmentSetExtra                        = 321;
    const AetheriaBitfield                         = 322;
    const HealingBoostRating                       = 323;
    const HeritageSpecificArmor                    = 324;
    const AlternateRacialSkills                    = 325;
    const AugmentationJackOfAllTrades              = 326;
    const AugmentationResistanceNether             = 327;
    const AugmentationInfusedVoidMagic             = 328;
    const WeaknessRating                           = 329;
    const NetherOverTime                           = 330;
    const NetherResistRating                       = 331;
    const LuminanceAward                           = 332;
    const LumAugDamageRating                       = 333;
    const LumAugDamageReductionRating              = 334;
    const LumAugCritDamageRating                   = 335;
    const LumAugCritReductionRating                = 336;
    const LumAugSurgeEffectRating                  = 337;
    const LumAugSurgeChanceRating                  = 338;
    const LumAugItemManaUsage                      = 339;
    const LumAugItemManaGain                       = 340;
    const LumAugVitality                           = 341;
    const LumAugHealingRating                      = 342;
    const LumAugSkilledCraft                       = 343;
    const LumAugSkilledSpec                        = 344;
    const LumAugNoDestroyCraft                     = 345;
    const RestrictInteraction                      = 346;
    const OlthoiLootTimestamp                      = 347;
    const OlthoiLootStep                           = 348;
    const UseCreatesContractId                     = 349;
    const DotResistRating                          = 350;
    const LifeResistRating                         = 351;
    const CloakWeaveProc                           = 352;
    const WeaponType                               = 353;
    const MeleeMastery                             = 354;
    const RangedMastery                            = 355;
    const SneakAttackRating                        = 356;
    const RecklessnessRating                       = 357;
    const DeceptionRating                          = 358;
    const CombatPetRange                           = 359;
    const WeaponAuraDamage                         = 360;
    const WeaponAuraSpeed                          = 361;
    const SummoningMastery                         = 362;
    const HeartbeatLifespan                        = 363;
    const UseLevelRequirement                      = 364;
    const LumAugAllSkills                          = 365;
    const UseRequiresSkill                         = 366;
    const UseRequiresSkillLevel                    = 367;
    const UseRequiresSkillSpec                     = 368;
    const UseRequiresLevel                         = 369;
    const GearDamage                               = 370;
    const GearDamageResist                         = 371;
    const GearCrit                                 = 372;
    const GearCritResist                           = 373;
    const GearCritDamage                           = 374;
    const GearCritDamageResist                     = 375;
    const GearHealingBoost                         = 376;
    const GearNetherResist                         = 377;
    const GearLifeResist                           = 378;
    const GearMaxHealth                            = 379;
    const Unknown380                               = 380;
    const PKDamageRating                           = 381;
    const PKDamageResistRating                     = 382;
    const GearPKDamageRating                       = 383;
    const GearPKDamageResistRating                 = 384;
    const Unknown385                               = 385;
    const Overpower                                = 386;
    const OverpowerResist                          = 387;
    const GearOverpower                            = 388;
    const GearOverpowerResist                      = 389;
    const Enlightenment                            = 390;
    const PCAPRecordedAutonomousMovement           = 8007;
    const PCAPRecordedMaxVelocityEstimated         = 8030;
    const PCAPRecordedPlacement                    = 8041;
    const PCAPRecordedAppraisalPages               = 8042;
    const PCAPRecordedAppraisalMaxPages            = 8043;
    const CurrentLoyaltyAtLastLogoff              = 9008;
    const CurrentLeadershipAtLastLogoff           = 9009;
    const AllegianceOfficerRank                   = 9010;
    const HouseRentTimestamp                      = 9011;
    const Hairstyle                               = 9012;
    const VisualClothingPriority                  = 9013;
    const SquelchGlobal                           = 9014;
    const InventoryOrder                          = 9015;
}

class PropertyAttribute {
    const Undef        = 0;
    const Strength     = 1;
    const Endurance    = 2;
    const Quickness    = 3;
    const Coordination = 4;
    const Focus        = 5;
    const Self         = 6;
}

const ATTRIBUTES = [
    'Undef',
    'Strength',
    'Endurance',
    'Quickness',
    'Coordination',
    'Focus',
    'Self'
];

class PropertyAttribute2nd {
    const Undef       = 0;
    const MaxHealth   = 1;
    const Health      = 2;
    const MaxStamina  = 3;
    const Stamina     = 4;
    const MaxMana     = 5;
    const Mana        = 6;
}

const ATTRIBUTES_2ND = [
    "",
    "Max Health",
    "Health",
    "Max Stamina",
    "Stamina",
    "Max Mana",
    "Mana"
];

class WeenieType {
    const Undef = 0;
    const Generic = 1;
    const Clothing = 2;
    const MissileLauncher = 3;
    const Missile = 4;
    const Ammunition = 5;
    const MeleeWeapon = 6;
    const Portal = 7;
    const Book = 8;
    const Coin = 9;
    const Creature = 10;
    const Admin = 11;
    const Vendor = 12;
    const HotSpot = 13;
    const Corpse = 14;
    const Cow = 15;
    const AI = 16;
    const Machine = 17;
    const Food = 18;
    const Door = 19;
    const Chest = 20;
    const Container = 21;
    const Key = 22;
    const Lockpick = 23;
    const PressurePlate = 24;
    const LifeStone = 25;
    const PKModifier = 27;
    const Healer = 28;
    const LightSource = 29;
    const Allegiance = 30;
    const UNKNOWN__GUESSEDNAME32 = 31; // NOTE: Missing 1
    const SpellComponent = 32;
    const ProjectileSpell = 33;
    const Scroll = 33;
    const Caster = 34;
    const Channel = 35;
    const ManaStone = 36;
    const Gem = 37;
    const AdvocateFane = 38;
    const AdvocateItem = 39;
    const Sentinel = 40;
    const GSpellEconomy = 41;
    const LSpellEconomy = 42;
    const CraftTool = 43;
    const LScoreKeeper = 44;
    const GScoreKeeper = 45;
    const GScoreGatherer = 46;
    const ScoreBook = 47;
    const EventCoordinator = 48;
    const Entity = 49;
    const Stackable = 50;
    const HUD = 51;
    const House = 52;
    const Deed = 53;
    const SlumLord = 54;
    const Hook = 55;
    const Storage = 56;
    const BootSpot = 57;
    const HousePortal = 58;
    const Game = 59;
    const GamePiece = 60;
    const SkillAlterationDevice = 61;
    const AttributeTransferDevice = 62;
    const Hooker = 63;
    const AllegianceBindstone = 64;
    const InGameStatKeeper = 65;
    const AugmentationDevice = 66;
    const SocialManager = 67;
    const Pet = 68;
    const PetDevice = 69;
    const CombatPet = 70;
}

class PropertyBool {
    const Undef                            = 0;
    const Stuck                            = 1;
    const Open                             = 2;
    const Locked                           = 3;
    const RotProof                         = 4;
    const AllegianceUpdateRequest          = 5;
    const AiUsesMana                       = 6;
    const AiUseHumanMagicAnimations        = 7;
    const AllowGive                        = 8;
    const CurrentlyAttacking               = 9;
    const AttackerAi                       = 10;
    const IgnoreCollisions                 = 11;
    const ReportCollisions                 = 12;
    const Ethereal                         = 13;
    const GravityStatus                    = 14;
    const LightsStatus                     = 15;
    const ScriptedCollision                = 16;
    const Inelastic                        = 17;
    const Visibility                       = 18;
    const Attackable                       = 19;
    const SafeSpellComponents              = 20;
    const AdvocateState                    = 21;
    const Inscribable                      = 22;
    const DestroyOnSell                    = 23;
    const UiHidden                         = 24;
    const IgnoreHouseBarriers              = 25;
    const HiddenAdmin                      = 26;
    const PkWounder                        = 27;
    const PkKiller                         = 28;
    const NoCorpse                         = 29;
    const UnderLifestoneProtection         = 30;
    const ItemManaUpdatePending            = 31;
    const GeneratorStatus                  = 32;
    const ResetMessagePending              = 33;
    const DefaultOpen                      = 34;
    const DefaultLocked                    = 35;
    const DefaultOn                        = 36;
    const OpenForBusiness                  = 37;
    const IsFrozen                         = 38;
    const DealMagicalItems                 = 39;
    const LogoffImDead                     = 40;
    const ReportCollisionsAsEnvironment    = 41;
    const AllowEdgeSlide                   = 42;
    const AdvocateQuest                    = 43;
    const IsAdmin                          = 44;
    const IsArch                           = 45;
    const IsSentinel                       = 46;
    const IsAdvocate                       = 47;
    const CurrentlyPoweringUp              = 48;
    const GeneratorEnteredWorld            = 49;
    const NeverFailCasting                 = 50;
    const VendorService                    = 51;
    const AiImmobile                       = 52;
    const DamagedByCollisions              = 53;
    const IsDynamic                        = 54;
    const IsHot                            = 55;
    const IsAffecting                      = 56;
    const AffectsAis                       = 57;
    const SpellQueueActive                 = 58;
    const GeneratorDisabled                = 59;
    const IsAcceptingTells                 = 60;
    const LoggingChannel                   = 61;
    const OpensAnyLock                     = 62;
    const UnlimitedUse                     = 63;
    const GeneratedTreasureItem            = 64;
    const IgnoreMagicResist                = 65;
    const IgnoreMagicArmor                 = 66;
    const AiAllowTrade                     = 67;
    const SpellComponentsRequired          = 68;
    const IsSellable                       = 69;
    const IgnoreShieldsBySkill             = 70;
    const NoDraw                           = 71;
    const ActivationUntargeted             = 72;
    const HouseHasGottenPriorityBootPos    = 73;
    const GeneratorAutomaticDestruction    = 74;
    const HouseHooksVisible                = 75;
    const HouseRequiresMonarch             = 76;
    const HouseHooksEnabled                = 77;
    const HouseNotifiedHudOfHookCount      = 78;
    const AiAcceptEverything               = 79;
    const IgnorePortalRestrictions         = 80;
    const RequiresBackpackSlot             = 81;
    const DontTurnOrMoveWhenGiving         = 82;
    const NpcLooksLikeObject               = 83;
    const IgnoreCloIcons                   = 84;
    const AppraisalHasAllowedWielder       = 85;
    const ChestRegenOnClose                = 86;
    const LogoffInMinigame                 = 87;
    const PortalShowDestination            = 88;
    const PortalIgnoresPkAttackTimer       = 89;
    const NpcInteractsSilently             = 90;
    const Retained                         = 91;
    const IgnoreAuthor                     = 92;
    const Limbo                            = 93;
    const AppraisalHasAllowedActivator     = 94;
    const ExistedBeforeAllegianceXpChanges = 95;
    const IsDeaf                           = 96;
    const IsPsr                            = 97;
    const Invincible                       = 98;
    const Ivoryable                        = 99;
    const Dyable                           = 100;
    const CanGenerateRare                  = 101;
    const CorpseGeneratedRare              = 102;
    const NonProjectileMagicImmune         = 103;
    const ActdReceivedItems                = 104;
    const Unknown105                       = 105;
    const FirstEnterWorldDone              = 106;
    const RecallsDisabled                  = 107;
    const RareUsesTimer                    = 108;
    const ActdPreorderReceivedItems        = 109;
    const Afk                              = 110;
    const IsGagged                         = 111;
    const ProcSpellSelfTargeted            = 112;
    const IsAllegianceGagged               = 113;
    const EquipmentSetTriggerPiece         = 114;
    const Uninscribe                       = 115;
    const WieldOnUse                       = 116;
    const ChestClearedWhenClosed           = 117;
    const NeverAttack                      = 118;
    const SuppressGenerateEffect           = 119;
    const TreasureCorpse                   = 120;
    const EquipmentSetAddLevel             = 121;
    const BarberActive                     = 122;
    const TopLayerPriority                 = 123;
    const NoHeldItemShown                  = 124;
    const LoginAtLifestone                 = 125;
    const OlthoiPk                         = 126;
    const Account15Days                    = 127;
    const HadNoVitae                       = 128;
    const NoOlthoiTalk                     = 129;
    const AutowieldLeft                    = 130;
    const LinkedPortalOneSummon            = 9001;
    const LinkedPortalTwoSummon            = 9002;
    const HouseEvicted                     = 9003;
    const UntrainedSkills                  = 9004;
    const IsEnvoy                          = 9005;
    const UnspecializedSkills              = 9006;
    const FreeSkillResetRenewed            = 9007;
    const FreeAttributeResetRenewed        = 9008;
    const SkillTemplesTimerReset           = 9009;
};

class PropertyDataId {
    const Undef                      = 0;
    const Setup                      = 1;
    const MotionTable                = 2;
    const SoundTable                 = 3;
    const CombatTable                = 4;
    const QualityFilter              = 5;
    const PaletteBase                = 6;
    const ClothingBase               = 7;
    const Icon                       = 8;
    const EyesTexture                = 9;
    const NoseTexture                = 10;
    const MouthTexture               = 11;
    const DefaultEyesTexture         = 12;
    const DefaultNoseTexture         = 13;
    const DefaultMouthTexture        = 14;
    const HairPalette                = 15;
    const EyesPalette                = 16;
    const SkinPalette                = 17;
    const HeadObject                 = 18;
    const ActivationAnimation        = 19;
    const InitMotion                 = 20;
    const ActivationSound            = 21;
    const PhysicsEffectTable         = 22;
    const UseSound                   = 23;
    const UseTargetAnimation         = 24;
    const UseTargetSuccessAnimation  = 25;
    const UseTargetFailureAnimation  = 26;
    const UseUserAnimation           = 27;
    const Spell                      = 28;
    const SpellComponent             = 29;
    const PhysicsScript              = 30;
    const LinkedPortalOne            = 31;
    const WieldedTreasureType        = 32;
    const UnknownGuessedname         = 33;
    const UnknownGuessedname2        = 34;
    const DeathTreasureType          = 35;
    const MutateFilter               = 36;
    const ItemSkillLimit             = 37;
    const UseCreateItem              = 38;
    const DeathSpell                 = 39;
    const VendorsClassId             = 40;
    const ItemSpecializedOnly        = 41;
    const HouseId                    = 42;
    const AccountHouseId             = 43;
    const RestrictionEffect          = 44;
    const CreationMutationFilter     = 45;
    const TsysMutationFilter         = 46;
    const LastPortal                 = 47;
    const LinkedPortalTwo            = 48;
    const OriginalPortal             = 49;
    const IconOverlay                = 50;
    const IconOverlaySecondary       = 51;
    const IconUnderlay               = 52;
    const AugmentationMutationFilter = 53;
    const AugmentationEffect         = 54;
    const ProcSpell                  = 55;
    const AugmentationCreateItem     = 56;
    const AlternateCurrency          = 57;
    const BlueSurgeSpell             = 58;
    const YellowSurgeSpell           = 59;
    const RedSurgeSpell              = 60;
    const OlthoiDeathTreasureType    = 61;
    const PCAPRecordedWeenieHeader         = 8001;
    const PCAPRecordedWeenieHeader2        = 8002;
    const PCAPRecordedObjectDesc           = 8003;
    const PCAPRecordedPhysicsDesc          = 8005;
    const PCAPRecordedParentLocation       = 8009;
    const PCAPRecordedDefaultScript        = 8019;
    const PCAPRecordedTimestamp0           = 8020;
    const PCAPRecordedTimestamp1           = 8021;
    const PCAPRecordedTimestamp2           = 8022;
    const PCAPRecordedTimestamp3           = 8023;
    const PCAPRecordedTimestamp4           = 8024;
    const PCAPRecordedTimestamp5           = 8025;
    const PCAPRecordedTimestamp6           = 8026;
    const PCAPRecordedTimestamp7           = 8027;
    const PCAPRecordedTimestamp8           = 8028;
    const PCAPRecordedTimestamp9           = 8029;
    const PCAPRecordedMaxVelocityEstimated = 8030;
    const PCAPPhysicsDIDDataTemplatedFrom  = 8044;
}

class StatModType {
    const Armor = 41088;
}

class PropertyString {
    const Name = 1;
    const UseProp = 14;
    const ShortDesc = 15;
    const LongDesc = 16;
    const PluralName = 20;
}

const WEENIE_TYPE = [
    'Undef',
    'Generic',
    'Clothing',
    'MissileLauncher',
    'Missile',
    'Ammunition',
    'MeleeWeapon',
    'Portal',
    'Book',
    'Coin',
    'Creature',
    'Admin',
    'Vendor',
    'HotSpot',
    'Corpse',
    'Cow',
    'AI',
    'Machine',
    'Food',
    'Door',
    'Chest',
    'Container',
    'Key',
    'Lockpick',
    'PressurePlate',
    'LifeStone',
    'Switch',
    'PKModifier',
    'Healer',
    'LightSource',
    'Allegiance',
    'UNKNOWN__GUESSEDNAME32', // NOTE: Missing 1
    'SpellComponent',
    'ProjectileSpell',
    'Scroll',
    'Caster',
    'Channel',
    'ManaStone',
    'Gem',
    'AdvocateFane',
    'AdvocateItem',
    'Sentinel',
    'GSpellEconomy',
    'LSpellEconomy',
    'CraftTool',
    'LScoreKeeper', // type 44
    'GScoreKeeper',
    'GScoreGatherer',
    'ScoreBook',
    'EventCoordinator',
    'Entity',
    'Stackable',
    'HUD',
    'House',
    'Deed',
    'SlumLord',
    'Hook',
    'Storage',
    'BootSpot',
    'HousePortal',
    'Game',
    'GamePiece',
    'SkillAlterationDevice',
    'AttributeTransferDevice',
    'Hooker',
    'AllegianceBindstone',
    'InGameStatKeeper',
    'AugmentationDevice',
    'SocialManager',
    'Pet',
    'PetDevice',
    'CombatPet'
];

const SKILLS_LIST = [
    'None',
    'Axe',                 /* Retired */
    'Bow',                 /* Retired */
    'Crossbow',            /* Retired */
    'Dagger',              /* Retired */
    'Mace',                /* Retired */
    'Melee Defense',
    'Missile Defense',
    'Sling',               /* Retired */
    'Spear',               /* Retired */
    'Staff',               /* Retired */
    'Sword',               /* Retired */
    'Thrown Weapon',        /* Retired */
    'Unarmed Combat',       /* Retired */
    'Arcane Lore',
    'Magic Defense',
    'Mana Conversion',
    'Spellcraft',          /* Unimplemented */
    'Item Tinkering',
    'Assess Person',
    'Deception',
    'Healing',
    'Jump',
    'Lockpick',
    'Run',
    'Awareness',           /* Unimplemented */
    'ArmsAndArmorRepair',  /* Unimplemented */
    'Assess Creature',
    'Weapon Tinkering',
    'Armor Tinkering',
    'Magic Item Tinkering',
    'Creature Enchantment',
    'Item Enchantment',
    'Life Magic',
    'War Magic',
    'Leadership',
    'Loyalty',
    'Fletching',
    'Alchemy',
    'Cooking',
    'Salvaging',
    'Two Handed Combat',
    'Gearcraft',           /* Retired */
    'VoidMagic',
    'Heavy Weapons',
    'Light Weapons',
    'Finesse Weapons',
    'Missile Weapons',
    'Shield',
    'Dual Wield',
    'Recklessness',
    'Sneak Attack',
    'Dirty Fighting',
    'Challenge',          /* Unimplemented */
    'Summoning'
];

const SKILL_FORMULAS = array(
    'Melee Defense'     => array(PropertyAttribute::Coordination, PropertyAttribute::Quickness, 3),
    'Missile Defense'   => array(PropertyAttribute::Coordination, PropertyAttribute::Quickness, 5),
    'Arcane Lore'       => array(PropertyAttribute::Focus, null, 3),
    'Magic Defense'     => array(PropertyAttribute::Focus, PropertyAttribute::Self, 7),
    'Mana Conversion'   => array(PropertyAttribute::Focus, PropertyAttribute::Self, 6),
    'Item Tinkering'    => array(PropertyAttribute::Focus, PropertyAttribute::Coordination, 2),
    'Assess Person'     => null,
    'Deception'         => null,
    'Healing'           => array(PropertyAttribute::Coordination, PropertyAttribute::Focus, 3),
    'Jump'              => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 2),
    'Lockpick'          => array(PropertyAttribute::Coordination, PropertyAttribute::Focus, 3),
    'Alchemy'          => array(PropertyAttribute::Coordination, PropertyAttribute::Focus, 3),
    'Cooking'          => array(PropertyAttribute::Coordination, PropertyAttribute::Focus, 3),
    'Fletching'          => array(PropertyAttribute::Coordination, PropertyAttribute::Focus, 3),
    'Run'               => array(PropertyAttribute::Quickness, null, 1),
    'Assess Creature'   => null,
    'Weapon Tinkering'  => array(PropertyAttribute::Strength, PropertyAttribute::Focus, 2),
    'Armor Tinkering'   => array(PropertyAttribute::Focus, PropertyAttribute::Endurance, 2),
    'Magic Item Tinkering'  => array(PropertyAttribute::Focus, null, 1),
    'Creature Enchantment'  => array(PropertyAttribute::Focus, PropertyAttribute::Self, 4),
    'Item Enchantment'  => array(PropertyAttribute::Focus, PropertyAttribute::Self, 4),
    'Life Magic'  => array(PropertyAttribute::Focus, PropertyAttribute::Self, 4),
    'War Magic'  => array(PropertyAttribute::Focus, PropertyAttribute::Self, 4),
    'Void Magic'  => array(PropertyAttribute::Focus, PropertyAttribute::Self, 4),
    'VoidMagic'  => array(PropertyAttribute::Focus, PropertyAttribute::Self, 4),
    'Two Handed Combat'  => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Light Weapons'  => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Heavy Weapons'  => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Finesse Weapons'  => array(PropertyAttribute::Coordination, PropertyAttribute::Quickness, 3),
    'Missile Weapons'  => array(PropertyAttribute::Coordination, null, 2),
    'Thrown Weapon'  => array(PropertyAttribute::Coordination, null, 2),
    'Bow'  => array(PropertyAttribute::Coordination, null, 2),
    'Crossbow'  => array(PropertyAttribute::Coordination, null, 2),
    'Sneak Attack'      => array(PropertyAttribute::Coordination, PropertyAttribute::Quickness, 3),
    'Shield'            => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 2),
    'Axe'           => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Dagger'           => array(PropertyAttribute::Quickness, PropertyAttribute::Coordination, 3),
    'Mace'           => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Spear'           => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Staff'           => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Sword'           => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Unarmed Combat'           => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Dirty Fighting'           => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3)
);

const CREATURE_TYPE = [
    'Invalid',
    'Olthoi',
    'Banderling',
    'Drudge',
    'Mosswart',
    'Lugian',
    'Tumerok',
    'Mite',
    'Tusker',
    'PhyntosWasp',
    'Rat',
    'Auroch',
    'Cow',
    'Golem',
    'Undead',
    'Gromnie',
    'Reedshark',
    'Armoredillo',
    'Fae',
    'Virindi',
    'Wisp',
    'Knathtead',
    'Shadow',
    'Mattekar',
    'Mumiyah',
    'Rabbit',
    'Sclavus',
    'ShallowsShark',
    'Monouga',
    'Zefir',
    'Skeleton',
    'Human',
    'Shreth',
    'Chittick',
    'Moarsman',
    'OlthoiLarvae',
    'Slithis',
    'Deru',
    'FireElemental',
    'Snowman',
    'Unknown',
    'Bunny',
    'LightningElemental',
    'Rockslide',
    'Grievver',
    'Niffis',
    'Ursuin',
    'Crystal',
    'HollowMinion',
    'Scarecrow',
    'Idol',
    'Empyrean',
    'Hopeslayer',
    'Doll',
    'Marionette',
    'Carenzi',
    'Siraluun',
    'AunTumerok',
    'HeaTumerok',
    'Simulacrum',
    'AcidElemental',
    'FrostElemental',
    'Elemental',
    'Statue',
    'Wall',
    'AlteredHuman',
    'Device',
    'Harbinger',
    'DarkSarcophagus',
    'Chicken',
    'GotrokLugian',
    'Margul',
    'BleachedRabbit',
    'NastyRabbit',
    'GrimacingRabbit',
    'Burun',
    'Target',
    'Ghost',
    'Fiun',
    'Eater',
    'Penguin',
    'Ruschk',
    'Thrungus',
    'ViamontianKnight',
    'Remoran',
    'Swarm',
    'Moar',
    'EnchantedArms',
    'Sleech',
    'Mukkir',
    'Merwart',
    'Food',
    'ParadoxOlthoi',
    'Harvest',
    'Energy',
    'Apparition',
    'Aerbax',
    'Touched',
    'BlightedMoarsman',
    'GearKnight',
    'Gurog',
    'Anekshay'
];

const DAMAGE_TYPE = array(
    'Slash'       => 0x1,
    'Pierce'      => 0x2,
    'Bludgeon'    => 0x4,
    'Cold'        => 0x8,
    'Fire'        => 0x10,
    'Acid'        => 0x20,
    'Electric'    => 0x40,
    'Health'      => 0x80,
    'Stamina'     => 0x100,
    'Mana'        => 0x200,
    'Nether'      => 0x400,
    'Base'        => 0x10000000
);

const DAMAGE_TYPE_INT = array(
    0       => '',
    1       => 'Slash',
    2       => 'Pierce',
    3       => 'Slash/Pierce',
    4       => 'Bludgeon',
    5       => 'Slash/Bludgeon',
    8       => 'Cold',
    16      => 'Fire',
    17      => 'Slash/Fire',
    20      => 'Bludgeon/Fire',
    32      => 'Acid',
    50      => 'Pierce/Fire/Acid',
    64      => 'Electric',
    256     => 'Stamina',
    512     => 'Mana',
    1024    => 'Nether',
    268435456 => 'Base',
);

const ITEM_TYPES = array(
    0               => "None",
    1               => "MeleeWeapon",
    2               => "Armor",
    4               => "Clothing",
    8               => "Jewelry",
    16              => "Creature",
    32              => "Food",
    64              => "Money",
    128             => "Misc",
    256             => "MissileWeapon",
    512             => "Container",
    1024            => "Useless",
    2048            => "Gem",
    4096            => "SpellComponents",
    8192            => "Writable",
    16384           => "Key",
    32768           => "Caster",
    65536           => "Portal",
    131072          => "Lockable",
    262144          => "PromissoryNote",
    524288          => "ManaStone",
    1048576         => "Service",
    2097152         => "MagicWieldable",
    4194304         => "CraftCookingBase",
    8388608         => "CraftAlchemyBase",
    33554432        => "CraftFletchingBase",
    67108864        => "CraftAlchemyIntermediate",
    134217728       => "CraftFletchingIntermediate",
    268435456       => "LifeStone",
    536870912       => "TinkeringTool",
    1073741824      => "TinkeringMaterial",
    2147483648      => "Gameboard",
);

const MATERIALS = [
    "Unknown",
    "Ceramic", // 1
    "Porcelain", // 2
    "Cloth", // 3
    "Linen", // 4
    "Satin",
    "Silk",
    "Velvet",
    "Wool",
    "Gem",
    "Agate", // 10
    "Amber",
    "Amethyst",
    "Aquamarine",
    "Azurite",
    "BlackGarnet", // 15
    "BlackOpal",
    "Bloodstone",
    "Carnelian",
    "Citrine",
    "Diamond", // 20
    "Emerald",
    "FireOpal",
    "GreenGarnet",
    "GreenJade",
    "Hematite", // 25
    "ImperialTopaz",
    "Jet",
    "LapisLazuli",
    "LavenderJade",
    "Malachite", // 30
    "Moonstone",
    "Onyx",
    "Opal",
    "Peridot",
    "RedGarnet", // 35
    "RedJade",
    "RoseQuartz",
    "Ruby",
    "Sapphire",
    "SmokeyQuartz", // 40
    "Sunstone",
    "TigerEye",
    "Tourmaline",
    "Turquoise",
    "WhiteJade", // 45
    "WhiteQuartz",
    "WhiteSapphire",
    "YellowGarnet",
    "YellowTopaz",
    "Zircon", // 50
    "Ivory",
    "Leather",
    "ArmoredilloHide",
    "GromnieHide",
    "ReedSharkHide", // 55
    "Metal",
    "Brass",
    "Bronze",
    "Copper",
    "Gold", // 60
    "Iron",
    "Pyreal",
    "Silver",
    "Steel",
    "Stone", // 65
    "Alabaster",
    "Granite",
    "Marble",
    "Obsidian",
    "Sandstone", // 70
    "Serpentine",
    "Wood",
    "Ebony",
    "Mahogany",
    "Oak", // 75
    "Pine",
    "Teak"
];

class TreasureItemType {
    const Undef = 0;
    const Gem = 1;
    const Armor = 2;
    const Clothing = 3;
    const Cloak = 4;
    const Weapon = 5;
    const Jewelry = 6;
    const Dinnerware = 7;
}

// Found here: https://github.com/ACEmulator/ACE/blob/a653c84fb4c7d569dffb2bbe22455f29c52c2354/Source/ACE.Server/Factories/Tables/TreasureItemTypeChances.cs#L6
// Accurate in ACE as of April 18 2021
const DefaultMagicalChanceTable = [
    array('itemType' => TreasureItemType::Gem, 'probability' => 0.14),
    array('itemType' => TreasureItemType::Armor, 'probability' => 0.24),
    array('itemType' => TreasureItemType::Weapon, 'probability' => 0.30),
    array('itemType' => TreasureItemType::Clothing, 'probability' => 0.13),
    array('itemType' => TreasureItemType::Cloak, 'probability' => 0.01),
    array('itemType' => TreasureItemType::Jewelry, 'probability' => 0.18)
];

const DefaultNonMagicalChanceTable = [
    array('itemType' => TreasureItemType::Gem, 'probability' => 0.14),
    array('itemType' => TreasureItemType::Armor, 'probability' => 0.24),
    array('itemType' => TreasureItemType::Weapon, 'probability' => 0.30),
    array('itemType' => TreasureItemType::Clothing, 'probability' => 0.13),
    array('itemType' => TreasureItemType::Cloak, 'probability' => 0.01),
    array('itemType' => TreasureItemType::Jewelry, 'probability' => 0.10),
    array('itemType' => TreasureItemType::Dinnerware, 'probability' => 0.08)
];

class WieldRequirement {
    const Skill = 1;
    const RawSkill = 2;
    const Attrib = 3;
    const RawAttrib = 4;
    const SecondaryAttrib = 5;
    const RawSecondaryAttrib = 6;
    const Level = 7;
}

const WIELD_REQUIREMENTS = [
    'Invalid',
    'Skill',
    'RawSkill',
    'Attrib',
    'RawAttrib',
    'SecondaryAttrib',
    'RawSecondaryAttrib',
    'Level',
    'Training',
    'IntStat',
    'BoolStat',
    'CreatureType',
    'HeritageType'
];

const WIELD_REQUIREMENT_WORD = array(
    'Skill'         => 'buffed',
    'RawSkill'      => 'base',
    'Attrib'        => 'buffed',
    'RawAttrib'     => 'base',
    'SecondaryAttrib' => 'buffed',
    'RawSecondaryAttrib' => 'base',
    'Level'         => ''
);

/**
 * const WieldRequirements                        = 158;
 * const WieldSkillType                           = 159;
 * const WieldDifficulty                          = 160;
 */
function getWieldRequirementDisplay($requirement, $skillType, $difficulty) {
    $requirementLabel = WIELD_REQUIREMENTS[$requirement];
    
    $skillWord = '';
    if (array_key_exists($requirementLabel, WIELD_REQUIREMENT_WORD)) {
        $skillWord = WIELD_REQUIREMENT_WORD[$requirementLabel] . ' ';
    }
    
    $skill = '';
    switch ($requirementLabel) {
        case 'Skill':
        case 'RawSkill':
            $skill = SKILLS_LIST[$skillType];
            break;
            
        case 'Level':
            $skill = 'level';
            break;
            
        case 'CreatureType':
            $creatureType = CREATURE_TYPE[$skillType];
            return 'You must be a ' . $creatureType . ' to wield this weapon';
    }
    
    return "Your ${skillWord}${skill} must be at least ${difficulty} to wield this item.";
}

const WEAPON_TYPES = [
    'Undef',
    'Unarmed',
    'Sword',
    'Axe',
    'Mace',
    'Spear',
    'Dagger',
    'Staff',
    'Bow',
    'Crossbow',
    'Thrown',
    'TwoHanded',
    'Magic'
];

function clamp($val, $min, $max) {
    return max($min, min($max, val));
}

const IMBUED_EFFECTS = array(
    0 => "Undef",
    1 => "CriticalStrike",
    2 => "CripplingBlow",
    4 => "ArmorRending",
    8 => "SlashRending",
    16 => "PierceRending",
    32 => "BludgeonRending",
    64 => "AcidRending",
    128 => "ColdRending",
    256 => "ElectricRending",
    512 => "FireRending",
    1024 => "MeleeDefense",
    2048 => "MissileDefense",
    4096 => "MagicDefense",
    8192 => "Spellbook",
    16384 => "NetherRending",
    536870912 => "IgnoreSomeMagicProjectileDamage",
    1073741824 => "AlwaysCritical",
    2147483648 => "IgnoreAllArmor",
);

const COMBAT_STYLES = array(
    0 => "Undef",
    1 => "Unarmed",
    2 => "OneHanded",
    4 => "OneHandedAndShield",
    8 => "TwoHanded",
    16 => "Bow",
    32 => "Crossbow",
    64 => "Sling",
    128 => "ThrownWeapon",
    256 => "DualWield",
    512 => "Magic",
    1024 => "Atlatl",
    2048 => "ThrownShield",
    4096 => "Reserved1",
    8192 => "Reserved2",
    16384 => "Reserved3",
    32768 => "Reserved4",
    65536 => "StubbornMagic",
    131072 => "StubbornProjectile",
    262144 => "StubbornMelee",
    524288 => "StubbornMissile",
);

const MOTION_STANCES = array(
    2147483648 => "Invalid",
    2147483708 => "HandCombat",
    2147483709 => "NonCombat",
    2147483710 => "SwordCombat",
    2147483711 => "BowCombat",
    2147483712 => "SwordShieldCombat",
    2147483713 => "CrossbowCombat",
    2147483714 => "UnusedCombat",
    2147483715 => "SlingCombat",
    2147483716 => "TwoHandedSwordCombat",
    2147483717 => "TwoHandedStaffCombat",
    2147483718 => "DualWieldCombat",
    2147483719 => "ThrownWeaponCombat",
    2147483720 => "Graze",
    2147483721 => "Magic",
    2147483880 => "BowNoAmmo",
    2147483881 => "CrossBowNoAmmo",
    2147483963 => "AtlatlCombat",
    2147483964 => "ThrownShieldCombat"
);

function getPageVariable($key, $defaultValue) {
    $returner = $defaultValue;

    if (isset($_SESSION[$key])) {
        $returner = $_SESSION[$key];
    }

    if (isset($_GET[$key])) {
        $returner = $_GET[$key];
    }

    $_SESSION[$key] = $returner;
    
    return $returner;
}
