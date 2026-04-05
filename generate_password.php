<?php
// File untuk generate password hash
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br><br>";

echo "Copy hash di atas dan jalankan query berikut di phpMyAdmin:<br>";
echo "UPDATE admin SET password = '$hash' WHERE username = 'admin';";
?>