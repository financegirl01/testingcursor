<?php
require_once 'config/database.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

// Get report data
$report_data = [];

// Inventory summary
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT i.id) as total_items,
        SUM(inv.quantity_in_stock) as total_stock,
        SUM(inv.quantity_in_stock * i.unit_price) as total_value,
        COUNT(DISTINCT s.id) as total_suppliers,
        COUNT(CASE WHEN inv.quantity_in_stock <= i.minimum_stock THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN inv.quantity_in_stock = 0 THEN 1 END) as out_of_stock_items
    FROM items i
    LEFT JOIN inventory inv ON i.id = inv.item_id
    LEFT JOIN suppliers s ON inv.supplier_id = s.id
");
$report_data['summary'] = $stmt->fetch();

// Category breakdown
$stmt = $pdo->query("
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category_name,
        COUNT(i.id) as item_count,
        SUM(inv.quantity_in_stock) as total_stock,
        SUM(inv.quantity_in_stock * i.unit_price) as category_value
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN inventory inv ON i.id = inv.item_id
    GROUP BY c.id, c.name
    ORDER BY category_value DESC
");
$report_data['categories'] = $stmt->fetchAll();

// Top items by value
$stmt = $pdo->query("
    SELECT 
        i.name,
        i.sku,
        inv.quantity_in_stock,
        i.unit_price,
        (inv.quantity_in_stock * i.unit_price) as stock_value
    FROM items i
    LEFT JOIN inventory inv ON i.id = inv.item_id
    ORDER BY stock_value DESC
    LIMIT 10
");
$report_data['top_items_by_value'] = $stmt->fetchAll();

// Low stock items
$stmt = $pdo->query("
    SELECT 
        i.name,
        i.sku,
        inv.quantity_in_stock,
        i.minimum_stock,
        i.unit_price,
        c.name as category_name
    FROM items i
    LEFT JOIN inventory inv ON i.id = inv.item_id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE inv.quantity_in_stock <= i.minimum_stock
    ORDER BY (inv.quantity_in_stock - i.minimum_stock) ASC
");
$report_data['low_stock'] = $stmt->fetchAll();

// Stock movements by month (last 6 months)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        movement_type,
        SUM(quantity) as total_quantity
    FROM stock_movements 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), movement_type
    ORDER BY month DESC
");
$report_data['movements'] = $stmt->fetchAll();

// Top suppliers by items
$stmt = $pdo->query("
    SELECT 
        s.name,
        COUNT(DISTINCT inv.item_id) as item_count,
        SUM(inv.quantity_in_stock * i.unit_price) as total_value
    FROM suppliers s
    LEFT JOIN inventory inv ON s.id = inv.supplier_id
    LEFT JOIN items i ON inv.item_id = i.id
    GROUP BY s.id, s.name
    HAVING item_count > 0
    ORDER BY total_value DESC
    LIMIT 10
");
$report_data['top_suppliers'] = $stmt->fetchAll();

// Recent stock movements
$stmt = $pdo->query("
    SELECT 
        sm.*,
        i.name as item_name,
        i.sku,
        u.full_name as user_name
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    LEFT JOIN users u ON sm.user_id = u.id
    ORDER BY sm.created_at DESC
    LIMIT 20
");
$report_data['recent_movements'] = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Inventory Management System</title>
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
                    <a href="dashboard.php" class="flex items-center">
                        <i class="fas fa-boxes text-blue-600 text-2xl mr-3"></i>
                        <h1 class="text-xl font-bold text-gray-900">Inventory Management</h1>
                    </a>
                    <span class="text-gray-300">></span>
                    <span class="text-gray-600">Reports</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($user['full_name']) ?></span>
                    <a href="logout.php" class="text-gray-600 hover:text-red-600 transition duration-200">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Inventory Reports</h2>
                <p class="text-gray-600">Analytics and insights for your inventory management</p>
            </div>
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-print mr-2"></i>Print Report
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 mr-4">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Items</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($report_data['summary']['total_items']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 mr-4">
                        <i class="fas fa-cubes text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Stock</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($report_data['summary']['total_stock']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 mr-4">
                        <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Value</p>
                        <p class="text-2xl font-bold text-gray-900">$<?= number_format($report_data['summary']['total_value'], 2) ?></p>
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
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($report_data['summary']['total_suppliers']) ?></p>
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
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($report_data['summary']['low_stock_items']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-gray-100 mr-4">
                        <i class="fas fa-times-circle text-gray-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Out of Stock</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($report_data['summary']['out_of_stock_items']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Category Breakdown Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Inventory Value by Category</h3>
                <div class="relative h-64">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <!-- Stock Movements Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Stock Movements (Last 6 Months)</h3>
                <div class="relative h-64">
                    <canvas id="movementsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables Section -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <!-- Top Items by Value -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold text-gray-900">Top Items by Value</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($report_data['top_items_by_value'] as $item): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($item['sku']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= number_format($item['quantity_in_stock']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">$<?= number_format($item['stock_value'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Suppliers -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold text-gray-900">Top Suppliers by Value</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Value</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($report_data['top_suppliers'] as $supplier): ?>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($supplier['name']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= number_format($supplier['item_count']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">$<?= number_format($supplier['total_value'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($report_data['low_stock'])): ?>
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        Low Stock Alert
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Minimum Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($report_data['low_stock'] as $item): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($item['sku']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['category_name'] ?: 'Uncategorized') ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= number_format($item['quantity_in_stock']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= number_format($item['minimum_stock']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($item['quantity_in_stock'] == 0): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Out of Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Low Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Stock Movements -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-lg font-bold text-gray-900">Recent Stock Movements</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data['recent_movements'] as $movement): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y H:i', strtotime($movement['created_at'])) ?></td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($movement['item_name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($movement['sku']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $movement['movement_type'] === 'in' ? 'bg-green-100 text-green-800' : 
                                            ($movement['movement_type'] === 'out' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') ?>">
                                        <?= ucfirst($movement['movement_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= number_format($movement['quantity']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($movement['reference_number'] ?: '-') ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($movement['user_name'] ?: 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Category Chart
        const categoryData = {
            labels: [<?= implode(',', array_map(function($cat) { return '"' . addslashes($cat['category_name']) . '"'; }, $report_data['categories'])) ?>],
            datasets: [{
                data: [<?= implode(',', array_map(function($cat) { return $cat['category_value']; }, $report_data['categories'])) ?>],
                backgroundColor: [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                    '#F97316', '#06B6D4', '#84CC16', '#EC4899', '#6B7280'
                ]
            }]
        };

        const categoryChart = new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: categoryData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Stock Movements Chart
        const movementsData = <?= json_encode($report_data['movements']) ?>;
        const months = [...new Set(movementsData.map(m => m.month))].sort().reverse();
        
        const movementsChart = new Chart(document.getElementById('movementsChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Stock In',
                        data: months.map(month => {
                            const inData = movementsData.find(m => m.month === month && m.movement_type === 'in');
                            return inData ? inData.total_quantity : 0;
                        }),
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Stock Out',
                        data: months.map(month => {
                            const outData = movementsData.find(m => m.month === month && m.movement_type === 'out');
                            return outData ? outData.total_quantity : 0;
                        }),
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>