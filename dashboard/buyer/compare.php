<?php
/**
 * dashboard/buyer/compare.php - compare market prices of chosen products.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('buyer');

$actionUrl = base_url('dashboard/buyer/compare.php');
require __DIR__ . '/../../includes/compare_page.php';
