<?php
include 'db.php';

if ($conn) {
    echo "<h1>Success!</h1>";
    echo "<p>Your PHP script is successfully connected to the <b>tickettron</b> database.</p>";
} else {
    echo "<h1>Connection Failed</h1>";
}
?>