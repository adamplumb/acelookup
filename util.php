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
                                        weenie.class_Name code,
                                        wpi.value level
                                    from weenie 
                                        join weenie_properties_string wps on (wps.object_Id = weenie.class_Id) 
                                        join weenie_properties_bool wpb on (wpb.object_Id = weenie.class_Id)
                                        join weenie_properties_int wpi on (wpi.object_id = weenie.class_Id)
                                    where 
                                        weenie.type = 10
                                        and wps.type = 1
                                        and wpb.type = 19
                                        and wpb.value = 1
                                        and wpi.type = 25
                                        and weenie.class_Id = ?
                                    order by level asc");

    $statement->execute(array($weenieId));
    $mob = $statement->fetch(PDO::FETCH_ASSOC);
    
    return $mob;
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
                                join weenie on (weenie.class_Id = wpcl.weenie_Class_Id)
                                where 
                                    wpcl.object_id = ?
                                    and (wps.type = 1 or wps.type is null)");

    $statement->execute(array($weenieId));
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
        'Foot'
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

function getSkills($weenieId) {
    return getKeyValues('skill', $weenieId, 'type', 'init_Level');
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

class PropertyAttribute {
    const Undef        = 0;
    const Strength     = 1;
    const Endurance    = 2;
    const Quickness    = 3;
    const Coordination = 4;
    const Focus        = 5;
    const Self         = 6;
}

class PropertyAttribute2nd {
    const Undef       = 0;
    const MaxHealth   = 1;
    const Health      = 2;
    const MaxStamina  = 3;
    const Stamina     = 4;
    const MaxMana     = 5;
    const Mana        = 6;
}

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
