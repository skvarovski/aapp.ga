<?php

$time_start = microtime(true);

function fetch_data($url)
{
    $user_agents = ['Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36', 'Mozilla/5.0 (Windows NT 5.1; rv:38.0) Gecko/20100101 Firefox/38.0', 'Mozilla/5.0 (Windows NT 6.1; rv:44.0) Gecko/20100101 Firefox/44.0', 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36', 'Mozilla/5.0 (Windows NT 5.1; rv:43.0) Gecko/20100101 Firefox/43.0'];
    $uagent = $user_agents[array_rand($user_agents)];
    $curl_handle = curl_init();
    curl_setopt_array($curl_handle, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => $uagent
    ]);
    $result = curl_exec($curl_handle);
    curl_close($curl_handle);
    return $result;
}

$mysqli = new mysqli('localhost', 'root', 'root', 'aapp');

$mysqli->set_charset('utf8');

if ($mysqli->connect_errno) {
    echo '<h2>Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error . '</h2>';
    exit();
}

$urls = [
    'http://listen.di.fm/premium.json',
    'http://listen.radiotunes.com/premium.json',
    'http://listen.rockradio.com/premium.json',
    'http://listen.jazzradio.com/premium.json',
    'http://listen.classicalradio.com/premium.json'
];

$json = [];

foreach ($urls as $name => $url) {
    $result = fetch_data($url);
    $json = array_merge($json, json_decode($result));
}

$channels = [];

foreach ($json as $id => $channel) {
    $channels[$channel->id] = [
        'full' => $channel->name,
        'url' => $channel->playlist
    ];
}

ksort($channels);

$mysqli->query("TRUNCATE TABLE `channels`");

foreach ($channels as $id => $channel) {
    preg_match('/listen\.(\w+)\./', $channel['url'], $result);
    $station = $result[1];
    usleep(250000);
    $result = fetch_data(str_replace('.pls', '', $channel['url']));
    preg_match('/:\/\/[^\/]+\/([^_"]+)/', $result, $short);
    $short = mysqli_real_escape_string($mysqli, $short[1]);
    $name = mysqli_real_escape_string($mysqli, $channel['full']);
    $mysqli->query("INSERT INTO `channels` (`channel_id`, `station`, `short`, `name`) VALUES($id, '$station', '$short', '$name')");
}

$mysqli->close();

date_default_timezone_set('Europe/Minsk');

echo '[' . date("Y-m-d H:i:s") . '] Channels updated. Exec time: ' . round((microtime(true) - $time_start), 2) . 's' . PHP_EOL;
