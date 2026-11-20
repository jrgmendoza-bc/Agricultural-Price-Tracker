<?php
/**
 * logout.php - terminate the session and return to the landing page.
 */
require_once __DIR__ . '/includes/bootstrap.php';

Auth::logout();
flash('You have been signed out.', 'success');
redirect('/login.php');
