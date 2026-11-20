<?php
/**
 * dashboard/client/compare.php - compare market prices of chosen products.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('farmer');

$actionUrl = base_url('dashboard/client/compare.php');
require __DIR__ . '/../../includes/compare_page.php';
