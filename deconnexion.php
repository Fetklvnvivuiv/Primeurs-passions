<?php
require_once __DIR__ . '/fonctions.php';
demarrerSession();
session_unset();
session_destroy();
header('Location: ' . url('/index.php'));
exit;
