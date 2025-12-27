<?php
require 'config.php';   // starts session

session_start();        // tries to start again - will produce a notice if not safe

echo "Session test completed.";
