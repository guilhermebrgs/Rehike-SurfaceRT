<?php
require_once "e:/dev/Rehike-SurfaceRT/modules/Rehike/Player/PlayerCore.php";
require_once "e:/dev/Rehike-SurfaceRT/modules/Rehike/Player/PlayerUpdater.php";
require_once "e:/dev/Rehike-SurfaceRT/modules/Rehike/Network.php";
use Rehike\Player\PlayerCore;
use Rehike\Player\PlayerUpdater;

$js = file_get_contents("https://www.youtube.com/s/player/c57c113c/player_ias.vflset/en_US/base.js");
preg_match(PlayerCore::$stsRegex, $js, $matches);
echo "STS: " . ($matches[1] ?? "Not found") . "\n";
