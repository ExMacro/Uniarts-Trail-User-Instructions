<?php
$overrides = [];
foreach (glob(__DIR__ . '/room_overrides/*.php') as $file) {
    $overrides = array_merge($overrides, include($file));
}
return $overrides;