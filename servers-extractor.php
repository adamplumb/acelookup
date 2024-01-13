<?php

$servers = array();

$contents = file_get_contents('https://treestats.net/servers');

$lines = explode("\n", $contents);

$hasSeenPrivate = false;
foreach ($lines as $index => $line) {
    if (str_starts_with($line, "<a href='#private'>Private Servers</a>")) {
        $hasSeenPrivate = true;
    }
    
    if ($hasSeenPrivate) {
        if ($line == '</table>') {
            break;
        }
        
        if (str_starts_with($line, "<a href='/")) {
            if (preg_match("/\<a.*\>(.*?)\<\/a\>/", $line, $matches)) {
                $server = $matches[1];
                $servers[] = $server;
            }
        }
    }
}

print_r($servers);
file_put_contents('servers.json', json_encode($servers, JSON_PRETTY_PRINT));
