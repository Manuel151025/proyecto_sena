<?php
// Redirecciona a crear.php que maneja ambos casos (crear y editar)
header('Location: ' . dirname(__FILE__) . '/crear.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
exit;
