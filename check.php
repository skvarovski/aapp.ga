<?php

require_once dirname(__FILE__) . '/functions.php';
require_once dirname(__FILE__) . '/autoload.php';

$errors = [];
$data = [];

$secret = "XXX";
$recaptcha = new \ReCaptcha\ReCaptcha($secret);

if (isset($_POST["g-recaptcha-response"])) {
    $resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
    if ($resp->isSuccess()) {

        if (isset($_POST['email'])) {
            $email = htmlspecialchars(trim($_POST['email']));
            if (empty($email))
                $errors['email'] = 'Email is required.';
        }

        if (isset($_POST['password'])) {
            $password = htmlspecialchars(trim($_POST['password']));
            if (empty($password))
                $errors['password'] = 'Password is required.';
        }

        if (!empty($errors)) {
            $data['success'] = false;
            $data['errors'] = $errors;
        } else {

            $url = 'https://api.audioaddict.com/v1/di/members/authenticate';
            $post = ['username' => $email, 'password' => $password];

            $result = fetch_data($url, $post);

            if (($result[0] == '{') || ($result[0] == '[')) {

                $json = json_decode($result);

                $name = $json->first_name . ' ' . $json->last_name;
                $status = $json->subscriptions[0]->status;
                $expire = $json->subscriptions[0]->expires_on;

                $data['name'] = $name;
                $data['status'] = $status;
                $data['expire'] = $expire;
                $data['success'] = true;
            } else {
                $data['success'] = false;
                $errors['invalid'] = 'Invalid Email or Password';
                $data['errors'] = $errors;
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
