<?php

$user_agents = ['AudioAddict-di/1.6.0.320 Android/4.1.2', 'AudioAddict-di/1.6.0.320 Android/4.2.2', 'AudioAddict-di/1.6.0.320 Android/4.3.1', 'AudioAddict-di/2.0.6.1540 Android/4.4.4', 'AudioAddict-di/2.0.6.1540 Android/5.0.2', 'AudioAddict-di/2.0.6.1540 Android/5.1.1'];

$uagent = $user_agents[array_rand($user_agents)];

function echo_line($text)
{
    echo PHP_EOL, $text, PHP_EOL;
}

function fetch_data($url, $post = false, $header = false)
{
    global $uagent;

    $curl_handle = curl_init();
    //$f = fopen('request.txt', 'w');

    curl_setopt_array($curl_handle, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERPWD => 'ephemeron:dayeiph0ne@pp',
        //CURLOPT_VERBOSE => true,
        //CURLOPT_STDERR => $f,
        CURLOPT_USERAGENT => $uagent
    ]);

    if ($post) {
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $post);
    }

    if ($header) {
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $header);
    }

    $result = curl_exec($curl_handle);

    //fclose($f);
    curl_close($curl_handle);

    return $result;
}
