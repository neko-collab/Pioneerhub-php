<?php
header("Content-Type: application/json");

$host = "localhost";
$user = "root";
$password = "";
$database = "pioneer_hub";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}
?>
