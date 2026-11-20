<?php
/**
 * dashboard/client.php - legacy entry point.
 * The farmer dashboard is now split into one file per menu item;
 * this stub forwards old links to the Overview page.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
redirect('/dashboard/client/overview.php');
