<?php

const WIKI_BASE_URL = 'https://acportalstorm.com/wiki/';

const ADVANCED_BESTOWER_TITLES = array(
    'Deadeye',
    'Sureshot',
    'Projectilist',
    'Skullsplitter',
    'Slicer',
    'Skullcrusher',
    'Impaler',
    'Master of Staves',
    'Duelist',
    'Pugilist',
    'Alchemist',
    'Iron Chef',
    'Master Fletcher',
    'Warlock',
    'Theurgist',
    'Artifex',
    'Evoker'
);


function convertNameToUrl($key) {
    return WIKI_BASE_URL . $key;
}

function getPageKey($name) {
    return str_replace(' ', '_', $name);
}

function getTitlePagePathFromName($name) {
    $key = getPageKey($name);
    return getTitlePagePath($key);
}

function getTitlePagePath($key) {
    $first_letter = substr($key, 0, 1);
    return "title-pages/" . $first_letter . "/" . $key . ".html";
}

function getTitlePageContents($name) {
    $path = getTitlePagePath($name);
    
    if (file_exists($path)) {
        return file_get_contents($path);
    } else {
        throw new Exception("Unable to find file at path ${path}");
    }
}

function downloadTitlePageFromName($name) {
    $key = getPageKey($name);
    $url = convertNameToUrl($key);
    return downloadTitlePage($key, $url);
}

function downloadListingPage($letter) {
    $url = convertNameToUrl('Category:Titles?from=' . $letter);
    return downloadTitlePage($letter, $url);
}

function downloadTitlePage($key, $url) {
    $path = getTitlePagePath($key);
    
    if (file_exists($path)) {
        echo "Already downloaded ${url} to ${path}\n";
        return $path;
    }

    sleep(2);
    echo "Downloading Quest ${url} to ${path}...\n";

    $contents = file_get_contents($url);

    $folder = dirname($path);
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
    
    file_put_contents($path, $contents);
    
    return $path;
}

function extractInformation($name, $titleOverride = null) {
    echo "Extracting Quest Information for ${name}\n";
    if ($titleOverride) {
        echo "Title override = ${titleOverride}\n";
    }

    $questPath = downloadTitlePageFromName($name);
    $contents = file_get_contents($questPath);
    
    return extractInformationFromContents($name, $contents, $titleOverride);
}

function extractSummaryTableInformation($title, $searchString, $lines, $type) {
    $returner = array(
        "title" => "",
        "quest" => "",
        "xp" => "",
        "reqLevel" => "",
        "recLevel" => "",
        "type" => "",
        "seasonal" => false,
        "status" => "Verified"
    );
    
    // Title-specific hacks
    if ($searchString == 'Golden God') {
        $searchString = 'Goldengod';
    }

    $found = false;
    $canLook = false;
    foreach ($lines as $index => $line) {
        if (str_contains($line, "Summary table")) {
            $canLook = true;
        }

        if (!$canLook) {
            continue;
        }

        if ($type == 'title' && str_contains($line, "<td>${searchString}")) {
            $returner['title'] = str_replace("<td>", "", $line);  
            $returner['reqLevel'] = str_replace("<td align=\"center\">", "", $lines[$index - 4]);
            $returner['xp'] = str_replace("<td align=\"center\">", "", $lines[$index - 2]);
            
            if (preg_match("/\<td\>\<a.*title\=\"(.*?)\"/", $lines[$index - 6], $matches)) {
                $returner['quest'] = $matches[1] . " Quest";
            }
            
            $found = true;
        } else if ($type == 'substring' && str_contains($line, $searchString)) {
            $returner['title'] = $title;
            
            if (preg_match("/^\<td\>\<a.*title\=\"(.*?)\"/", $line, $matches)) {
                $returner['quest'] = $matches[1] . " Quest";
            }
            
            $returner['reqLevel'] = str_replace("<td align=\"center\">", "", $lines[$index + 2]);
            $returner['xp'] = str_replace("<td align=\"center\">", "", $lines[$index + 4]);
            
            $found = true;
        }
        
        if ($returner['title']) {
            break;
        }
    }
    
    
    if ($searchString == 'Goldengod') {
        $returner['title'] = 'Golden God';
    }
    
    if ($found) {
        // Hack to fix br tags in Olthoi Pincers page
        $returner['xp'] = str_replace('<br />', '-', $returner['xp']);
        
        // Get the info from the actual quest page, but search for this title
        $questInfo = extractInformation($returner['quest'], $returner['title']);

        if ($questInfo['quest']) {
            return $questInfo;
        } else {
            return $returner;
        }
    }
}

function extractInformationFromContents($title, $contents, $titleOverride) {
    $lines = explode("\n", $contents);
    
    $searchTitle = $titleOverride ? $titleOverride : $title;
    
    // Hack for some listing titles like "Guardian of Linvak Tukal (Title)"
    $searchTitle = str_replace(" (Title)", "", $searchTitle);
    
    $returner = array(
        "title" => "",
        "quest" => "",
        "xp" => "",
        "reqLevel" => "",
        "recLevel" => "",
        "type" => "",
        "seasonal" => false,
        "status" => "Unverified"
    );

    $lastExperience = null;
    $numSeenTitles = 0;
    $hearthSeeker = null;
    foreach ($lines as $index => $line) {
        if (str_contains($line, "Experience:")) {
            if (preg_match("/^\<li\>Experience\: ([0-9\,]+)/", $line, $matches)) {
                $lastExperience = $matches[1];
            } else {
                $lastExperience = str_replace("<td>", "", $lines[$index + 2]);
            }
        }

        if (!$returner['title']) {
            if (str_contains($line, "<td>Titles:")) {
                $numSeenTitles++;
            }

            if ($numSeenTitles > 0) {
                if (str_contains($line, "<td>${searchTitle}")) {
                    if (preg_match("/\<td\>([a-zA-Z\s\']+)/", $line, $matches)) {
                        $returner['title'] = trim($matches[1]);
                        $returner['xp'] = $lastExperience;
                    }
                } else if (str_contains($line, "<ul><li>Titles: ${searchTitle}")) {
                    // Hack for Coral Golem Kill Task
                    if (preg_match("/\<ul\>\<li\>Titles\: ([a-zA-Z\s\']+)/", $line, $matches)) {
                        $returner['title'] = trim($matches[1]);
                        $returner['xp'] = $lastExperience;
                    }
                }
            }
        }
        
        if (!$returner['quest'] && preg_match("/\<title\>(.*?) - Levistras\<\/title\>/", $line, $matches)) {
            $returner['quest'] = $matches[1];
        }
        
        if (str_contains($line, "<b>Recommended Level:</b>")) {
            $lineWithValue = $lines[$index + 2];
            $returner['recLevel'] = str_replace("<td>", "", $lineWithValue);
        }
        
        if (str_contains($line, "<b>Required Level:</b>")) {
            $lineWithValue = $lines[$index + 2];
            $returner['reqLevel'] = str_replace("<td>", "", $lineWithValue);
        }
        
        // Fixes titles like "Bathed in Blood" that have a "Requirements" section that is treated differently
        if (!$returner['reqLevel'] && str_contains($line, "<td>Level: ")) {
            if (preg_match("/^\<td\>Level\: ([0-9]+)/", $line, $matches)) {
                $returner['reqLevel'] = $matches[1];
            }
        }
        
        if (str_contains($line, "<b>Type:</b>")) {
            $lineWithValue = $lines[$index + 2];
            preg_match("/\<td\>\<a.*\>(.*?)\<\/a\>/", $lineWithValue, $matches);
            $returner['type'] = $matches[1];
        }
        
        // Use acportalstorm hearthseeker quest info to help us out
        if (str_contains($line, 'Marketplace HearthSeeker')) {
            if (preg_match("/Marketplace HearthSeeker ([0-9]+)\-[0-9]+ Quests/", $line, $matches)) {
                $hearthSeeker = $matches[1];
            }
        }
        
        if (!$returner['seasonal'] && str_contains($line, '/wiki/Seasonal_Quests')) {
            $returner['seasonal'] = true;
        }
        
        if (str_contains($line, '<b>Verified</b>')) {
            $returner['status'] = 'Verified';
        }
    }

    if (!$returner['title']) {
        $searchType = 'title';
        $searchString = $searchTitle;
        if (str_contains($returner['quest'], 'Jaws')) {
            $searchString = strtok($searchTitle, ' ');
            $searchType = 'substring';
        }
        
        $found = extractSummaryTableInformation($title, $searchString, $lines, $searchType);
        if ($found) {
            $returner = $found;
        }
    }

    // Quest-specific overrides
    switch ($returner['quest']) {
        case "Bestowers' Guild of Dereth":
            if (in_array($searchTitle, ADVANCED_BESTOWER_TITLES)) {
                $returner['recLevel'] = 40;
            } else {
                $returner['recLevel'] = "30";
            }
            break;

        case "Larry's Ruined Garden":
            $returner['recLevel'] = "25+";
            break;
            
        case "The Deep (Vissidal)":
            $returner['recLevel'] = "120+";
            $returner['type'] = 'Solo';
            $returner['xp'] = "11,400,000 (3.5% up to level 150?) ";
            break;
            
        case "Tactical Defense Game":
            $returner['reqLevel'] = '180+';
            break;
            
        case "Gold Hill Ruins Quest":
            $returner['recLevel'] = "50+";
            break;
            
        case "Harvest Reaper Kill Task":
            $returner['recLevel'] = "100+";
            break;
            
        case "Resting Place":
            $returner['recLevel'] = "70+";
            break;
            
        case "Snowman Village":
            $returner['recLevel'] = "40+";
            break;

        case "Gaerlan's Citadel (Extreme)":
            $returner['recLevel'] = 130;
            break;
        
        case "Ruschk Aspect of Grael Quest":
            $returner['recLevel'] = 90;
            break;

        case "Shadow Aspect of Grael Quest":
            $returner['recLevel'] = 140;
            break;

        case "Mukkir Aspect of Grael Quest":
            $returner['recLevel'] = 180;
            break;
            
        case "Falatacot Medallion (Low)":
            $returner['recLevel'] = 30;
            break;
            
        case "Colosseum Arena":
            $returner['recLevel'] = 40;
            break;
            
        case "Broker Contracts":
            $returner['recLevel'] = 80;
            break;
            
        case "Town Founder":
            $returner['recLevel'] = 100;
            break;
            
        case "Gauntlet Bosses":
            $returner['recLevel'] = 180;
            break;
            
    }
    
    // Hack for pages like "Ritual of the Blight" that contain multiple titles and required levels
    if ($returner['reqLevel'] && str_contains($returner['reqLevel'], ', ')) {
        $levels = explode(', ', $returner['reqLevel']);
        $returner['reqLevel'] = $levels[$numSeenTitles - 1];
        
        // If not a range, put a +
        if (!str_contains($returner['reqLevel'], '-')) {
            $returner['reqLevel'] .= '+';
        }
    }
    
    if (!$returner['title']) {
        $returner['title'] = $title;
    }
    
    // Something like Gumshoe doesn't have a req level but it is in the hearthseeker quests
    if ((!$returner['reqLevel'] || $returner['reqLevel'] == 'Any') && $hearthSeeker) {
        $returner['recLevel'] = $hearthSeeker . '+';
    }
    
    return $returner;
}

function extractTitles($letter) {
    echo "Extracting Title Listing for ${letter}\n";

    $questPath = downloadListingPage($letter);
    $contents = file_get_contents($questPath);
    
    $titles = array();
    $lines = explode("\n", $contents);
    
    foreach ($lines as $index => $line) {
        if (str_contains($line, '<li><span class="redirect-in-category"><a href')) {
            if (preg_match("/\<a.*\>(.*?)\<\/a\>/", $line, $matches)) {
                if ($matches[1] == 'next page') {
                    continue;
                }
                
                // Only concern ourselves with titles that start with this letter to prevent duplicates
                if (substr($matches[1], 0, 1) !== $letter) {
                    continue;
                }
                
                $titles[] = html_entity_decode($matches[1]);
            }
        }
    }
    
    return $titles;
}


/**
 * Specify title-specific overrides
 * 
 * - Some titles can be done by multiple quest levels.  So we can duplicate the titles
 *      in our listing to show which quest levels are recommended at which level
 * - Also some titles are redirected to the wrong quest page.  We can do an override here.
 **/
$titleQuestOverrides = array(
    'Ruuk Ally'         => array(
        'Falatacot Medallion (Low)',
        'Falatacot Medallion (Mid)',
        'Falatacot Medallion (High)',
        'Falatacot Medallion (Extreme)'
    ),
    'Gaerlan Slayer' => array(
        "Gaerlan's Citadel (Low)",
        "Gaerlan's Citadel (Mid)",
        "Gaerlan's Citadel (High)",
        "Gaerlan's Citadel (Extreme)",
        "Gaerlan's Citadel (Uber)"
    ),
    'Contract Killer' => array("Broker Contracts"),
    "Pumpkin Throne Usurper" => array("Colosseum Bosses")
);

$letters = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
$all_info = array();
foreach ($letters as $letter) {
    $titles = extractTitles($letter);
    var_dump($titles);
    
    foreach ($titles as $title) {
        $titleArr = array(array($title, $title));
        if (isset($titleQuestOverrides[$title])) {
            $titleArr = array();
            foreach ($titleQuestOverrides[$title] as $questName) {
                $titleArr[] = array($questName, $title);
            }
        }
        
        foreach ($titleArr as $titleObj) {
            if ($titleObj[0] == $titleObj[1]) {
                $info = extractInformation($title, null);
            } else {
                $info = extractInformation($titleObj[0], $titleObj[1]);
            }

            if ($info['reqLevel'] == 'Any' || $info['reqLevel'] == '??') {
                $info['reqLevel'] = '';
            }
            
            if ($info['xp']) {
                if (preg_match("/(^\<span.*\>\<\/span\>)?([0-9\,]+)/", $info['xp'], $matches)) {
                    $info['xpNum'] = intval(str_replace(',', '', $matches[2]));
                }
            }
            
            if (!isset($info['recLvlMin']) || $info['recLevel'] == 'Varies') {
                if (preg_match("/up to level ([0-9]+)/", html_entity_decode($info['xp']), $matches)) {
                    $info['recLevel'] = ceil(floor(0.8 * intval($matches[1])) / 5) * 5;
                }
            }
            
            if (isset($info['xpNum']) && !$info['recLevel'] && !$info['reqLevel']) {
                if ($info['xpNum'] < 500000) {
                    $info['recLevel'] = "15-40";
                } else if ($info['xpNum'] < 2000000) {
                    $info['recLevel'] = "30-60";
                } else if ($info['xpNum'] < 10000000) {
                    $info['recLevel'] = "50-100";
                } else {
                    $info['recLevel'] = '150+';
                }
            }
            
            if ($info['reqLevel']) {
                if (preg_match("/^([0-9]+)[\-\+]?([0-9]*)$/", $info['reqLevel'], $matches)) {
                    $info['reqLvlMin'] = $matches[1] ? intval($matches[1]) : 1;
                    $info['reqLvlMax'] = $matches[2] ? intval($matches[2]) : 275;
                }
            }
            
            if (!isset($info['reqLvlMin'])) {
                $info['reqLvlMin'] = 1;
            }

            if (!isset($info['reqLvlMax'])) {
                $info['reqLvlMax'] = 275;
            }
            
            if ($info['recLevel']) {
                if (preg_match("/^([0-9]+)[\+]?/", $info['recLevel'], $matches)) {
                    $info['recLvlMin'] = $matches[1] ? intval($matches[1]) : 1;
                }
            }
            
            // Make sure the recommended level is not lower than the required one
            if (!isset($info['recLvlMin']) || $info['recLvlMin'] < $info['reqLvlMin']) {

                if ($info['reqLvlMax'] != 275) {
                    $avgReq = floor(($info['reqLvlMin'] + $info['reqLvlMax']) / 2);
                    $info['recLvlMin'] = $avgReq;
                } else {
                    $info['recLvlMin'] = $info['reqLvlMin'];
                }
            }
            
            // Make sure the recommended level is not higher than the required max
            if ($info['recLvlMin'] > $info['reqLvlMax']) {
                $info['recLvlMin'] = $info['reqLvlMax'];
            }
            
            if ((!$info['recLevel'] || $info['recLevel'] == 'Varies') && $info['recLvlMin'] > 1) {
                $info['recLevel'] = $info['recLvlMin'];
            }
            
            $all_info[] = $info;
        }
    }
}

function cmpQuest($a, $b) {
    if ($a['quest'] == $b['quest']) {
        return 0;
    }
    
    return ($a['quest'] < $b['quest']) ? -1 : 1;
}

function cmpReqLvlMin($a, $b) {
    if ($a['reqLvlMin'] == $b['reqLvlMin']) {
        return cmpQuest($a, $b);
    }
    
    return ($a['reqLvlMin'] < $b['reqLvlMin']) ? -1 : 1;
}

function cmpRecLvlMin($a, $b) {
    $aVal = $a['recLvlMin'];
    $bVal = $b['recLvlMin']; 
       
    if ($aVal == $bVal) {
        return cmpQuest($a, $b);
    }
    
    return ($aVal < $bVal) ? -1 : 1;
}

usort($all_info, 'cmpRecLvlMin');

$groups = array(
    'Any' => array('titles' => array()),
    '10 to 19' => array('min' => 10, 'max' => 19, 'titles' => array()),
    '20 to 29' => array('min' => 20, 'max' => 29, 'titles' => array()),
    '30 to 39' => array('min' => 30, 'max' => 39, 'titles' => array()),
    '40 to 49' => array('min' => 40, 'max' => 49, 'titles' => array()),
    '50 to 59' => array('min' => 50, 'max' => 59, 'titles' => array()),
    '60 to 69' => array('min' => 60, 'max' => 69, 'titles' => array()),
    '70 to 79' => array('min' => 70, 'max' => 79, 'titles' => array()),
    '80 to 89' => array('min' => 80, 'max' => 89, 'titles' => array()),
    '90 to 125' => array('min' => 90, 'max' => 125, 'titles' => array()),
    '126 to 150' => array('min' => 126, 'max' => 150, 'titles' => array()),
    '151 to 179' => array('min' => 151, 'max' => 179, 'titles' => array()),
    '180+' => array('min' => 180, 'max' => 275, 'titles' => array())
);

$keys = array_reverse(array_keys($groups));

foreach ($all_info as $obj) {
    $added = false;
    if (!$obj['reqLevel'] && !$obj['recLevel']) {
        $groups['Any']['titles'][] = $obj;
    } else {
        foreach ($keys as $key) {
            if ($added) {
                continue;
            }

            $group = $groups[$key];
            
            if ($obj['reqLvlMin'] >= $group['min']) {
                $groups[$key]['titles'][] = $obj;
                $added = true;
                break;
            }
            
            $avgReq = ($obj['reqLvlMin'] + $obj['reqLvlMax']) / 2;
            if ($obj['reqLvlMax'] != 275 && $avgReq >= $group['min']) {
                $groups[$key]['titles'][] = $obj;
                $added = true;
                break;                
            }

            if ($obj['recLvlMin'] >= $group['min']) {
                $groups[$key]['titles'][] = $obj;
                $added = true;
                break;                
            }

        }
    }
}

/*
$sortKey = 'reqLvlMin';
if (!isset($titles[0]->$sortKey)) {
    $sortKey == 'title';
}

function cmpQuest($a, $b) {
    if ($a['quest'] == $b['quest']) {
        return 0;
    }
    
    return ($a['quest'] < $b['quest']) ? -1 : 1;
}

function cmpReqLvlMin($a, $b) {
    if ($a['reqLvlMin'] == $b['reqLvlMin']) {
        return cmpQuest($a, $b);
    }
    
    return ($a['reqLvlMin'] < $b['reqLvlMin']) ? -1 : 1;
}

function cmpRecLvlMin($a, $b) {
    $aVal = $a['recLvlMin'] ? $a['recLvlMin'] : $a['reqLvlMin'];
    $bVal = $b['recLvlMin'] ? $b['recLvlMin'] : $b['reqLvlMin']; 
       
    if ($aVal == $bVal) {
        return cmpQuest($a, $b);
    }
    
    return ($aVal < $bVal) ? -1 : 1;
}

usort($all_info, 'cmpRecLvlMin');
*/

file_put_contents("titles.json", json_encode($groups,  JSON_PRETTY_PRINT));
