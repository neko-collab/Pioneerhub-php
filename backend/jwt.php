<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require '../vendor/autoload.php';

$secret_key = "ghiuy79TIUGUT%76y789ytiugY(^*7rtyiuyo&*(67t8riyfgoyip089678r6ufyguoy897tIYGUYO*(T&iguHYI89t7ryfGUOY*967tIYFHGOY*(T&IYFJHVKGOY*(T&*RUFYJHVKgot7ifyvhkjbhIOY*(T&fiyvhkbjHOY*(T&ifyjvhkJGOT7ify";  //  a secure key

function generateJWT($user_id, $email, $role) {
    global $secret_key;
    $payload = [
        "iat" => time(),
        "exp" => time() + (60 * 60 * 24 * 365 ), // Token expires in 1 year
        "user_id" => $user_id,
        "email" => $email, 
        "role" => $role

    ];
    return JWT::encode($payload, $secret_key, 'HS256');
}

function validateJWT($token) {
    global $secret_key;
    try {
        return JWT::decode($token, new Key($secret_key, 'HS256'));
    } catch (Exception $e) {
        return null;
    }
}
?>
