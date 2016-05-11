<?php

require_once dirname(__FILE__) . '/functions.php';
require_once dirname(__FILE__) . '/autoload.php';
require_once dirname(__FILE__) . '/PronounceableWord/DependencyInjectionContainer.php';

/*
 * Functions
 */

function create_files($prefix, $channels, $servers, $codecs, $formats, $listen_key)
{
    global $station_names;
    $site_url = $station_names[$prefix]['url'];
    $site_name = $station_names[$prefix]['name'];
    $size = count($channels);
    $keys = array_keys($channels);

    foreach($servers as $server) {
        foreach($codecs as $codec) {
            $add = $station_names[$prefix]['add'][$codec];
            foreach($formats as $format) {
                $filename = $prefix . '_' . $codec . '_s' . $server . '.' . $format;
                $handle = fopen('playlists/' . $filename, 'w');
                if ($format == 'pls') {
                    $text = '[playlist]' . PHP_EOL . 'NumberOfEntries=' . $size . PHP_EOL;
                    fwrite($handle, $text);
                    foreach($channels as $name=>$code) {
                        $num = array_search($name, $keys) + 1;
                        $url = 'http://prem' . $server . '.' . $site_url . ':80/' . $code . $add;
                        $text = 'File' . $num . '=' . $url .  '?' . $listen_key . PHP_EOL .
                            'Title' . $num . '=' . $name . ' - ' . $site_name . ' Premium' . PHP_EOL .
                            'Length' . $num . '=-1' . PHP_EOL;
                        fwrite($handle, $text);
                    }
                    $text = 'Version=2';
                    fwrite($handle, $text);
                } elseif ($format == 'm3u') {
                    $text = '#EXTM3U';
                    fwrite($handle, $text);
                    foreach($channels as $name=>$code) {
                        $url = 'http://prem' . $server . '.' . $site_url . ':80/' . $code . $add;
                        $text = PHP_EOL . '#EXTINF:-1,' . $name . PHP_EOL . $url . '?' . $listen_key;
                        fwrite($handle, $text);
                    }
                }
                fclose($handle);
            }
        }
    }
}

function generate_playlists($prefix, $servers, $codecs, $formats, $listen_key)
{
    $mysqli = new mysqli('127.0.0.1', 'XXX', 'XXX', 'XXX');

    $mysqli->set_charset('utf8');

    $channels = [];

    foreach ($mysqli->query("SELECT `short`, `name` FROM `channels`
                             WHERE `station` = '$prefix'") as $row) {
        $channels[$row['name']] = $row['short'];
    }

    $mysqli->close();

    ksort($channels);

    create_files($prefix, $channels, $servers, $codecs, $formats, $listen_key);
}

function make_zip($prefixes, $servers, $codecs, $formats, $listen_key, $zip_name)
{
    foreach ($prefixes as $prefix) {
        generate_playlists($prefix, $servers, $codecs, $formats, $listen_key);
    }

    $rootPath = realpath('playlists');

    $zip = new ZipArchive();
    $zip->open('playlists/' . $zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $filesToDelete = array();

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file)
    {
        if (!$file->isDir())
        {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);

            if ((strpos($file->getFilename(), '.pls') !== false) ||
                (strpos($file->getFilename(), '.m3u') !== false))
            {
                $zip->addFile($filePath, $relativePath);
                $filesToDelete[] = $filePath;
            }
        }
    }

    $zip->close();

    foreach ($filesToDelete as $file)
    {
        unlink($file);
    }
}

function get_info($email, $password)
{
    $url = 'https://api.audioaddict.com/v1/di/members/authenticate';

    $post = ['username' => $email, 'password' => $password];

    $result = fetch_data($url, $post);

    $json = json_decode($result);

    $data['api_key'] = $json->api_key;
    $data['listen_key'] = $json->listen_key;
    $data['expire'] = $json->subscriptions[0]->expires_on;

    return $data;
}

function transfer_favorites($old_email, $old_password, $api_key)
{
    global $station_names;
    $prefixes = array_keys($station_names);

    $info = get_info($old_email, $old_password);
    $old_api_key = $info['api_key'];

    foreach($prefixes as $prefix) {
        $url = 'https://api.audioaddict.com/v1/' . $prefix . '/members/1/favorites/channels?api_key=' . $old_api_key;
        $result = fetch_data($url);

        $post = '{"favorites":' . $result . '}';
        $url = 'https://api.audioaddict.com/v1/' . $prefix . '/members/1/favorites/channels?api_key=' . $api_key;
        $header = ['Content-Type: application/json'];
        $result = fetch_data($url, $post, $header);
    }
}

/*
 * Check sent data
 */

$errors = [];
$data = [];

$secret = "XXX";
$recaptcha = new \ReCaptcha\ReCaptcha($secret);

if (isset($_POST["g-recaptcha-response"])) {
    $resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
    if ($resp->isSuccess()) {

        if (empty($_POST['station']))
            $errors['station'] = 'Station is required.';

        if (empty($_POST['quality']))
            $errors['quality'] = 'Quality is required.';

        if (empty($_POST['format']))
            $errors['format'] = 'Format is required.';

        if (empty($_POST['server']))
            $errors['server'] = 'Server is required.';

        if (isset($_POST['email'])) {
            $old_email = htmlspecialchars(trim($_POST['email']));
        }

        if (isset($_POST['password'])) {
            $old_password = htmlspecialchars(trim($_POST['password']));
        }

        if (!empty($errors)) {
            $data['success'] = false;
            $data['errors'] = $errors;
        } else {
            $codecs = $_POST['quality'];
            $formats = $_POST['format'];
            $servers = $_POST['server'];
            $prefixes = $_POST['station'];

            $station_names = [
                'di' => [
                    'name' => 'Digitally Imported',
                    'url' => 'di.fm',
                    'add' => [
                        'aac_40' => '_aacp',
                        'aac_64' => '_aac',
                        'aac_128' => '',
                        'mp3' => '_hi'
                    ]
                ],
                'radiotunes' => [
                    'name' => 'RADIOTUNES.COM',
                    'url' => 'radiotunes.com',
                    'add' => [
                        'aac_40' => '_aacp',
                        'aac_64' => '_aac',
                        'aac_128' => '',
                        'mp3' => '_hi'
                    ]
                ],
                'rockradio' => [
                    'name' => 'ROCKRADIO.COM',
                    'url' => 'rockradio.com',
                    'add' => [
                        'aac_40' => '_aacp',
                        'aac_64' => '_low',
                        'aac_128' => '_aac',
                        'mp3' => ''
                    ]
                ],
                'jazzradio' => [
                    'name' => 'JAZZRADIO.com',
                    'url' => 'jazzradio.com',
                    'add' => [
                        'aac_40' => '_aacp',
                        'aac_64' => '_low',
                        'aac_128' => '_aac',
                        'mp3' => ''
                    ]
                ],
                'classicalradio' => [
                    'name' => 'ClassicalRadio.com',
                    'url' => 'classicalradio.com',
                    'add' => [
                        'aac_40' => '_aacp',
                        'aac_64' => '_low',
                        'aac_128' => '_aac',
                        'mp3' => ''
                    ]
                ]
            ];

            $mail_domains = ['aol.com', 'gmx.com', 'mac.com', 'me.com', 'mail.com', 'msn.com', 'live.com', 'wow.com', 'zoho.com', 'juno.com', 'sky.com', 'bt.com', 'nate.com'];

            if (!empty($_POST['options']) &&
                ($_POST['options'][0] == 'not_create') &&
                !empty($old_email) && !empty($old_password)
            ) {
                $info = get_info($old_email, $old_password);
                $zip_name = 'aapp.ga_' . $info['expire'] . '_' . $old_email . '.zip';
                make_zip($prefixes, $servers, $codecs, $formats, $info['listen_key'], $zip_name);
                $data['zip_name'] = $zip_name;
                $data['success'] = true;
            } else {

                /*
                 * Registering
                 */

                $container = new PronounceableWord_DependencyInjectionContainer();
                $generator = $container->getGenerator();

                // try to register with an e-mail up to 10 times
                for ($i = 1; $i <= 10; $i++) {
                    $word1 = $generator->generateWordOfGivenLength(rand(3, 6));
                    $word2 = $generator->generateWordOfGivenLength(rand(5, 7));
                    $email = $word1 . '@' . $mail_domains[array_rand($mail_domains)];
                    $password = str_pad($word1, 6, 'a');

                    $url = 'https://api.audioaddict.com/v1/di/members';
                    $post = [
                        'member[email]' => $email,
                        'member[first_name]' => ucfirst($word1),
                        'member[last_name]' => ucfirst($word2),
                        'member[password]' => $password,
                        'member[password_confirmation]' => $password
                    ];

                    $result = fetch_data($url, $post);

                    $json = json_decode($result);

                    if (isset($json->api_key)) {
                        break;
                    }
                }

                if (isset($json->errors)) {
                    $data['success'] = false;
                    $errors['account'] = 'Can\'t register an account. Please, contact website admin';
                    $data['errors'] = $errors;
                } else {
                    $api_key = $json->api_key;
                    $conf_token = $json->confirmation_token;
                    $listen_key = $json->listen_key;

                    /*
                     * Confirming
                     */

                    $url = 'http://www.di.fm/member/confirm/' . $conf_token;

                    $result = fetch_data($url);

                    /*
                     * Activating trial
                     */

                    // try to activate a free trial up to 10 times
                    for ($i = 1; $i <= 10; $i++) {
                        $url = 'https://api.audioaddict.com/v1/di/members/1/subscriptions/trial/premium-pass';
                        $post = ['api_key' => $api_key];

                        $result = fetch_data($url, $post);
                        if (empty($result)) {
                            break;
                        }
                    }

                    if (!empty($result)) {
                        $data['success'] = false;
                        $errors['account'] = 'Can\'t activate a free trial. Please, contact website admin';
                        $data['errors'] = $errors;
                    } else {

                        /*
                         * Logging in
                         */

                        $url = 'https://api.audioaddict.com/v1/di/members/authenticate';
                        $post = ['api_key' => $api_key];

                        $result = fetch_data($url, $post);

                        $json = json_decode($result);

                        $name = $json->first_name . ' ' . $json->last_name;
                        $status = $json->subscriptions[0]->status;
                        $expire = $json->subscriptions[0]->expires_on;

                        /*
                         * Transferring favorites
                         */

                        if (!empty($_POST['options']) &&
                            ($_POST['options'][0] == 'favorites') &&
                            !empty($old_email) && !empty($old_password)
                        ) {
                            transfer_favorites($old_email, $old_password, $api_key);
                        }

                        /*
                         * Generating playlists
                         */

                        $zip_name = 'aapp.ga_' . $expire . '_' . $email . '.zip';

                        make_zip($prefixes, $servers, $codecs, $formats, $listen_key, $zip_name);

                        $data['name'] = $name;
                        $data['email'] = $email;
                        $data['password'] = $password;
                        $data['expire'] = $expire;
                        $data['status'] = $status;
                        $data['zip_name'] = $zip_name;
                        $data['success'] = true;
                    }
                }
            }
        }
    } else {
        $captcha_errors = $resp->getErrorCodes();
        $data['success'] = false;
        $errors['captcha'] = 'Captcha error: ' . $captcha_errors[0];
        $data['errors'] = $errors;
    }
}

echo json_encode($data);
