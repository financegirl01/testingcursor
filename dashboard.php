<?php
require_once 'config/database.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

// Get dashboard statistics
$stats = [];

// Total items
$stmt = $pdo->query("SELECT COUNT(*) as count FROM items");
$stats['total_items'] = $stmt->fetchColumn();

// Total inventory value
$stmt = $pdo->query("
    SELECT SUM(i.quantity_in_stock * it.unit_price) as total_value 
    FROM inventory i 
    JOIN items it ON i.item_id = it.id
");
$stats['total_value'] = $stmt->fetchColumn() ?: 0;

// Low stock items
$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM inventory i 
    JOIN items it ON i.item_id = it.id 
    WHERE i.quantity_in_stock <= it.minimum_stock
");
$stats['low_stock'] = $stmt->fetchColumn();

// Total suppliers
$stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers");
$stats['total_suppliers'] = $stmt->fetchColumn();

// Recent stock movements
$stmt = $pdo->query("
    SELECT sm.*, it.name as item_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN items it ON sm.item_id = it.id
    LEFT JOIN users u ON sm.user_id = u.id
    ORDER BY sm.created_at DESC
    LIMIT 5
");
$recent_movements = $stmt->fetchAll();

// Low stock alerts
$stmt = $pdo->query("
    SELECT it.name, it.sku, i.quantity_in_stock, it.minimum_stock, it.unit_price
    FROM inventory i 
    JOIN items it ON i.item_id = it.id 
    WHERE i.quantity_in_stock <= it.minimum_stock
    ORDER BY (i.quantity_in_stock - it.minimum_stock) ASC
    LIMIT 5
");
$low_stock_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventory Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-boxes text-blue-600 text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-gray-900">Inventory Management</h1>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($user['full_name']) ?></span>
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"><?= ucfirst($user['role']) ?></span>
                    <a href="logout.php" class="text-gray-600 hover:text-red-600 transition duration-200">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Page Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Dashboard</h2>
            <p class="text-gray-600">Overview of your inventory management system</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 mr-4">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Items</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_items']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 mr-4">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Value</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['total_value'], 2) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 mr-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Low Stock</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['low_stock']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 mr-4">
                        <i class="fas fa-truck text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Suppliers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_suppliers']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow mb-8 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="items.php" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition duration-200">
                    <i class="fas fa-boxes text-blue-600 text-2xl mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">Item Management</h4>
                        <p class="text-sm text-gray-600">Manage your items</p>
                    </div>
                </a>

                <a href="inventory.php" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition duration-200">
                    <i class="fas fa-warehouse text-green-600 text-2xl mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">Inventory</h4>
                        <p class="text-sm text-gray-600">Track stock levels</p>
                    </div>
                </a>

                <a href="suppliers.php" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition duration-200">
                    <i class="fas fa-truck text-purple-600 text-2xl mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">Suppliers</h4>
                        <p class="text-sm text-gray-600">Manage suppliers</p>
                    </div>
                </a>

                <a href="reports.php" class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition duration-200">
                    <i class="fas fa-chart-line text-orange-600 text-2xl mr-3"></i>
                    <div>
                        <h4 class="font-medium text-gray-900">Reports</h4>
                        <p class="text-sm text-gray-600">View analytics</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Stock Movements -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold text-gray-900">Recent Stock Movements</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_movements)): ?>
                        <p class="text-gray-500 text-center py-4">No recent movements</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_movements as $movement): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-full <?= $movement['movement_type'] === 'in' ? 'bg-green-100' : 'bg-red-100' ?> mr-3">
                                            <i class="fas <?= $movement['movement_type'] === 'in' ? 'fa-arrow-up text-green-600' : 'fa-arrow-down text-red-600' ?>"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($movement['item_name']) ?></p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($movement['user_name'] ?: 'System') ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium <?= $movement['movement_type'] === 'in' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $movement['movement_type'] === 'in' ? '+' : '-' ?><?= $movement['quantity'] ?>
                                        </p>
                                        <p class="text-xs text-gray-500"><?= date('M j, Y', strtotime($movement['created_at'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Alerts -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        Low Stock Alerts
                    </h3>
                </div>
                <div class="p-6">
                    <?php if (empty($low_stock_items)): ?>
                        <p class="text-gray-500 text-center py-4">No low stock items</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($low_stock_items as $item): ?>
                                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></p>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($item['sku']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-red-600 font-medium">
                                            <?= $item['quantity_in_stock'] ?> / <?= $item['minimum_stock'] ?>
                                        </p>
                                        <p class="text-xs text-gray-500">Current / Min</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>