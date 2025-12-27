<?php
// check_session_auto.php
echo "ini session.auto_start = " . ini_get('session.auto_start') . "<br>";
echo "Before require: session_status = " . session_status() . " (1=NONE, 2=ACTIVE)<br>";

require 'config.php';

echo "After require: session_status = " . session_status() . "<br>";

// Show if a session id already exists
echo "session_id = '" . session_id() . "'<br>";
