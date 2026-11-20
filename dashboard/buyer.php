<?php
/**
 * dashboard/buyer.php - legacy entry point.
 * The buyer dashboard is now split into one file per menu item;
 * this stub forwards old links to the Overview page.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
redirect('/dashboard/buyer/overview.php');
