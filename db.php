<?php
$host = "localhost";
$dbname = "exam_portal1";
$username = "root";
$password = "Kakash@549";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    // No echo statement here
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}