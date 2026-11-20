<?php
/**
 * dashboard/admin/compare.php - compare market prices of chosen products.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$user = Auth::requireRole('admin');

$actionUrl = base_url('dashboard/admin/compare.php');
require __DIR__ . '/../../includes/compare_page.php';
