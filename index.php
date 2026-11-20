<?php
/**
 * index.php - entry point: sends every visitor to the login portal.
 * (login.php itself routes authenticated users to their role dashboard.)
 */
require_once __DIR__ . '/includes/bootstrap.php';

redirect('/login.php');
