<?php

$host = 'ai-study-assistant-db-brew-haven1.h.aivencloud.com';
$port = 21806;
$timeout = 10;

$start = microtime(true);

$socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

$elapsed = round(microtime(true) - $start, 2);

if ($socket) {
    fclose($socket);

    echo "TCP CONNECTION: SUCCESS<br>";
    echo "Host: " . htmlspecialchars($host) . "<br>";
    echo "Port: " . $port . "<br>";
    echo "Time: " . $elapsed . " seconds";
} else {
    echo "TCP CONNECTION: FAILED<br>";
    echo "Host: " . htmlspecialchars($host) . "<br>";
    echo "Port: " . $port . "<br>";
    echo "Error: " . htmlspecialchars($errstr) . "<br>";
    echo "Error code: " . $errno . "<br>";
    echo "Time: " . $elapsed . " seconds";
}