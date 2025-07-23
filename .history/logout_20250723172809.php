<?php
session_start();
session_destroy();
header("header("Location: admin/login.php");
");
exit();
