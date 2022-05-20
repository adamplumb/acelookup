<?php
ini_set('display_errors', 'on');

include_once 'util.php';


$classId = isset($_GET['id']) ? $_GET['id'] : '';
if (!$classId) {
    print("No id provided\n");
    exit;
}

$item = getCraftingItem($classId);
if (!$item) {
    print("Not a valid crafting item\n");
    exit;
}

function formatWeenieName($name, $amount) {
    if ($amount > 1) {
        $result = $amount . ' ' . $name;
        $lastLetter = substr($name, -1);

        if ($lastLetter != 's' && $lastLetter != 'y') {
            $result .= 's';
        }
    } else {
        $result = $name;
    }
    
    return $result;
}

$ints = getInts($classId);
$strings = getStrings($classId);
$recipes = array_merge(
    getRecipesByWeenieId('source', $classId),
    getRecipesByWeenieId('target', $classId)
);
$creaturesThatDrop = getCreaturesThatDropItem($classId);

$description = null;
if (isset($strings[PropertyString::ShortDesc])) {
    $description = $strings[PropertyString::ShortDesc];
} else if (isset($strings[PropertyString::LongDesc])) {
    $description = $strings[PropertyString::LongDesc];
}

function showRecipesList($recipes, $showSteps = false) {
?>
<table class="horizontal-table">
<thead>
    <tr>
        <?php echo $showSteps ? "<th>#</th>\n" : '' ?>
        <th width="200">Source</th>
        <th width="200">Target</th>
        <th width="100">Skill</th>
        <th width="70">Diff</th>
        <th width="150">Success</th>
        <th>Fail</th>
    </tr>
</thead>
<tbody>
<?php
    $step = 1;
    $reversedRecipes = array_reverse($recipes);
    foreach ($reversedRecipes as $recipe) {
        $skillAt95 = getCraftingSkillForChance($recipe['difficulty'], 0.95);
        $skillMessage = 'No skill required';
        if ($recipe['difficulty']) {
            $skillMessage = "Skill required for 95% chance: " . $skillAt95;
        }
?>
    <tr>
        <?php echo $showSteps ? "<td>${step}</td>\n" : '' ?>

        <td>
            <a href="crafting.php?id=<?php echo $recipe['sourceWeenieId']; ?>" title="<?php echo $recipe['sourceWeenieName']; ?>">
                <?php echo $recipe['sourceWeenieName']; ?>
            </a>
        </td>
        <td>
            <a href="crafting.php?id=<?php echo $recipe['targetWeenieId']; ?>" title="<?php echo $recipe['targetWeenieName']; ?>">
                <?php echo $recipe['targetWeenieName']; ?>
            </a>
        </td>
        <td><?php echo SKILLS_LIST[$recipe['skill']]; ?></a>
        </td>
        <td title="<?php echo $skillMessage; ?>">
            <?php echo $recipe['difficulty']; ?>
        </td>
        <td>
<?php
        if ($recipe['successWeenieId']) {
?>
            <a href="crafting.php?id=<?php echo $recipe['successWeenieId']; ?>" title="<?php echo $recipe['successMessage']; ?>">
                <?php echo formatWeenieName($recipe['successWeenieName'], $recipe['successAmount']); ?>
            </a>
<?php
        } else {
            echo $recipe['successMessage'];
        }
?>
        </td>
        <td>
<?php
        if ($recipe['failWeenieId']) {
?>
            <a href="crafting.php?id=<?php echo $recipe['failWeenieId']; ?>" title="<?php echo $recipe['failMessage']; ?>">
                <?php echo $recipe['failAmount'] > 1 ? $recipe['failAmount'] : ''; ?>
                <?php echo $recipe['failWeenieName']; ?>
            </a>
<?php
        } else {
            echo $recipe['failMessage'];
        }
        
        $step++;
?>
        </td>
    </tr>
<?php
    }
?>
</tbody>
</table>
<?php
}

?>

<html>
<head>
    <title>ACE Crafting: <?php echo $item['name']; ?> (<?php echo $item['id']; ?>)</title>
    <link rel="stylesheet" type="text/css" href="style.css?d=<?php echo $config->cacheBuster; ?>" media="screen" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    
<form method="GET" action="index.php">
<input type="input" name="name" value="<?php echo $item['name']; ?>" size="30" placeholder="Search for creatures or crafting items" />
<input type="submit" value="Lookup" />
</form>

<h1><?php echo $item['name']; ?></h1>

<table class="vertical-table">
<tr>
    <th>ID</th>
    <td><?php echo $item['id']; ?></td>
</tr>
<tr>
    <th>Name</th>
    <td><?php echo $item['name']; ?></td>
</tr>
<?php
$weenieType = WEENIE_TYPE[$item['type']];
?>
<tr>
    <th>Type</th>
    <td>
        <?php echo $weenieType; ?>
        <?php echo ($weenieType == 'MeleeWeapon' ? '<a href="weapons.php?id=' . $item['id'] . '">(Weapon Stats)</a>' : ''); ?>
    </td>
</tr>
<tr>
    <th>Item Type</th>
    <td><?php echo ITEM_TYPES[$ints[PropertyInt::ItemType]]; ?></td>
</tr>
<tr>
    <th>Code</th>
    <td><?php echo $item['code']; ?></td>
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

if (isset($strings[PropertyString::UseProp])) {
?>
<tr>
    <th>Use</th>
    <td><?php echo $strings[PropertyString::UseProp]; ?></td>
</tr>
<?php
}

if (isset($ints[PropertyInt::BoostValue])) {
    $boosterEnum = $ints[PropertyInt::BoosterEnum];
    $boosterAttribute = ATTRIBUTES_2ND[$boosterEnum];
?>
<tr>
    <th>Boost</th>
    <td>Restores <?php echo $ints[PropertyInt::BoostValue]; ?> <?php echo $boosterAttribute; ?> when consumed</td>
</tr>
<tr>
    <th>Boost/BU</th>
    <td>Restores <?php echo round($ints[PropertyInt::BoostValue] / $ints[PropertyInt::EncumbranceVal], 1); ?> <?php echo $boosterAttribute; ?>/BU when consumed</td>
</tr>
<?php    
}
if (isset($ints[PropertyInt::MaxStackSize])) {
?>
<tr>
    <th>Stack Size</th>
    <td><?php echo $ints[PropertyInt::MaxStackSize]; ?></td>
</tr>
<?php
}
?>
<tr>
    <th>Value</th>
    <td><?php echo isset($ints[PropertyInt::Value]) ? $ints[PropertyInt::Value] : 0; ?></td>
</tr>
<tr>
    <th>Burden</th>
    <td><?php echo $ints[PropertyInt::EncumbranceVal]; ?> Burden Units</td>
</tr>
<tr>
    <th>Links</th>
    <td>
            <a href="http://acpedia.org/<?php echo str_replace(' ', '_', $item['name']); ?>" target="acpedia">ACPedia</a>
            /
            <a href="http://ac.yotesfan.com/weenies/items/<?php echo $item['id']; ?>" target="yotesfan">Yotesfan</a>
    </td>
</tr>
</table>

<br>
<h3>What you can craft from this item</h3>
<?php
if ($recipes) {
    showRecipesList($recipes);
} else {
?>
    <p class="no-item-note"><i>No recipes that use this item</i></p>
<?php    
}
?>

<br>
<h3>Possible ways to craft this item</h3>
<?php

function showWayNumber($way) {
?>
<div class="way-number">#<?php echo $way; ?></div>
<?php
}

$successRecipeLists = getRecipeLists('success', $classId);
$failRecipeLists = getRecipeLists('fail', $classId);

if ($successRecipeLists || $failRecipeLists) {
    $way = 1;
    foreach ($successRecipeLists as $recipeList) {
?>
<div class="way">
<?php
        showWayNumber($way);
        showRecipesList($recipeList, true);
?>
</div>
<?php
        $way++;
    }

    foreach ($failRecipeLists as $recipeList) {
?>
<div class="way">
<?php
        showWayNumber($way);
        showRecipesList($recipeList, true);
?>
</div>
<?php
        $way++;
    }
} else {
?>
    <p class="no-item-note"><i>No known ways to craft this item</i></p>
<?php
}
?>


<br />
<h3>Creatures That Drop/Sell This Item</h3>
<?php
if ($creaturesThatDrop) {
?>
<table class="horizontal-table">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Code</th>
        <th>Type</th>
        <th>Drop Chance</th>
        <th>Links</th>
    </tr>
</thead>
<tbody>
<?php
    $index = 0;
    foreach ($creaturesThatDrop as $row) {
        $rowClass = $index % 2 == 1 ? ' class="alt"' : '';
?>
    <tr<?php echo $rowClass; ?>>
        <td><?php echo $row['id']; ?></td>
        <td>
<?php
        if ($row['weenieType'] == 10 || $row['weenieType'] == 15) {
?>
            <a href="mob.php?id=<?php echo $row['id']; ?>"><?php echo $row['name']; ?></a>
<?php
        } else {
            echo $row['name'];
        }
?>
        </td>
        <td><?php echo $row['code']; ?></td>
        <td><?php echo CREATURE_TYPE[$row['type']]; ?></td>
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
    <p class="no-item-note"><i>No creatures drop this item</i></p>
<?php
}
?>

<?php
include_once 'footer.php';
?>

</body>
</html>
