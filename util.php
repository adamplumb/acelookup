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
                                        weenie.type = 10
                                        and wps.type = 1
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

function getSpellBook($weenieId) {
    global $dbh;

    $statement = $dbh->prepare("select 
                                    s.id id,
                                    s.name name,
                                    wpsb.probability probability 
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
            'probability'   => round($row['probability'], 2)
        );
    }
    
    return $spellBook;
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


function getEffectiveArmor($bodyArmor, $damageTypes, $floats) {
    foreach ($bodyArmor as $bodyPart => $bodyPartArmor) {
        $effectiveArmor[$bodyPart] = array();

        foreach ($damageTypes as $damageType => $damageProps) {
            $armorModProp = $damageProps['ArmorModProperty'];
            $resistModProp = $damageProps['ResistProperty'];

            $baseArmor = $bodyPartArmor["base_Armor"];
            $armorModVsDamageType = isset($floats[$armorModProp]) ? round($floats[$armorModProp], 2) : 1;
            $resistVsDamageType = isset($floats[$resistModProp]) ? round($floats[$resistModProp], 2) : 1;
            
            $effectiveArmor[$bodyPart][$damageType] = array(
                'baseArmor'     => $baseArmor,
                'armorMod'      => $armorModVsDamageType,
                'resist'        => $resistVsDamageType,
                'calculated'    => round(($baseArmor * $armorModVsDamageType) / $resistVsDamageType)
            );
        }
    }
    
    return $effectiveArmor;
}

function getMagicResistances($damageTypes, $floats) {
    $resistances = array();
    
    foreach ($damageTypes as $damageType => $damageProps) {
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

function percentageBetween($x, $a, $b) {
    if ($b - $a == 0) {
        return 1;
    }

    return round(($x - $a) / ($b - $a), 2);
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

class PropertyAttribute2nd {
    const Undef       = 0;
    const MaxHealth   = 1;
    const Health      = 2;
    const MaxStamina  = 3;
    const Stamina     = 4;
    const MaxMana     = 5;
    const Mana        = 6;
}

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
    'Two Handed Combat'  => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Light Weapons'  => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Heavy Weapons'  => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 3),
    'Finesse Weapons'  => array(PropertyAttribute::Coordination, PropertyAttribute::Quickness, 3),
    'Missile Weapons'  => array(PropertyAttribute::Coordination, null, 2),
    'Sneak Attack'      => array(PropertyAttribute::Coordination, PropertyAttribute::Quickness, 3),
    'Shield'            => array(PropertyAttribute::Strength, PropertyAttribute::Coordination, 2)
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
    1   => 'Slash',
    2   => 'Pierce',
    4   => 'Bludgeon',
    8   => 'Cold',
    16  => 'Fire',
    32  => 'Acid',
    64  => 'Electric'
);
