<?php
require_once 'config/database.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

$message = '';
$error = '';

// Handle stock movements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'stock_in' || $action === 'stock_out' || $action === 'adjustment') {
        $item_id = $_POST['item_id'] ?? 0;
        $quantity = abs((int)($_POST['quantity'] ?? 0));
        $reference = trim($_POST['reference'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $batch_number = trim($_POST['batch_number'] ?? '');
        $supplier_id = $_POST['supplier_id'] ?? null;
        
        if ($item_id <= 0 || $quantity <= 0) {
            $error = 'Please select an item and enter a valid quantity.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get current stock
                $stmt = $pdo->prepare("SELECT quantity_in_stock FROM inventory WHERE item_id = ?");
                $stmt->execute([$item_id]);
                $current_stock = $stmt->fetchColumn() ?: 0;
                
                // Calculate new stock
                $new_stock = $current_stock;
                $movement_type = '';
                
                if ($action === 'stock_in') {
                    $new_stock += $quantity;
                    $movement_type = 'in';
                } elseif ($action === 'stock_out') {
                    if ($quantity > $current_stock) {
                        throw new Exception('Cannot remove more stock than available.');
                    }
                    $new_stock -= $quantity;
                    $movement_type = 'out';
                } elseif ($action === 'adjustment') {
                    $new_stock = $quantity;
                    $movement_type = 'adjustment';
                }
                
                // Update inventory
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET quantity_in_stock = ?, supplier_id = ?, location = ?, batch_number = ? 
                    WHERE item_id = ?
                ");
                $stmt->execute([$new_stock, $supplier_id ?: null, $location, $batch_number, $item_id]);
                
                // Record stock movement
                $stmt = $pdo->prepare("
                    INSERT INTO stock_movements (item_id, movement_type, quantity, reference_number, notes, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$item_id, $movement_type, $quantity, $reference, $notes, $user['id']]);
                
                $pdo->commit();
                $message = 'Stock updated successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error updating stock: ' . $e->getMessage();
            }
        }
    }
}

// Get suppliers for dropdown
$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name");
$suppliers = $stmt->fetchAll();

// Get items for dropdown
$stmt = $pdo->query("SELECT * FROM items ORDER BY name");
$items = $stmt->fetchAll();

// Get inventory with item details
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';

$sql = "
    SELECT i.*, it.name, it.sku, it.unit_price, it.minimum_stock, 
           c.name as category_name, s.name as supplier_name,
           (i.quantity_in_stock * it.unit_price) as stock_value
    FROM inventory i
    JOIN items it ON i.item_id = it.id
    LEFT JOIN categories c ON it.category_id = c.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $sql .= " AND (it.name LIKE ? OR it.sku LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

if ($category_filter) {
    $sql .= " AND it.category_id = ?";
    $params[] = $category_filter;
}

if ($stock_filter === 'low') {
    $sql .= " AND i.quantity_in_stock <= it.minimum_stock";
} elseif ($stock_filter === 'out') {
    $sql .= " AND i.quantity_in_stock = 0";
}

$sql .= " ORDER BY it.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Get categories for filter
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get recent stock movements
$stmt = $pdo->query("
    SELECT sm.*, it.name as item_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN items it ON sm.item_id = it.id
    LEFT JOIN users u ON sm.user_id = u.id
    ORDER BY sm.created_at DESC
    LIMIT 10
");
$recent_movements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Inventory Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                    <span class="text-gray-600">Inventory</span>
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
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Inventory Management</h2>
                <p class="text-gray-600">Track and manage your stock levels</p>
            </div>
            <div class="flex gap-2">
                <button onclick="openModal('stockInModal')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Stock In
                </button>
                <button onclick="openModal('stockOutModal')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                    <i class="fas fa-minus mr-2"></i>Stock Out
                </button>
                <button onclick="openModal('adjustmentModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-edit mr-2"></i>Adjust Stock
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-64">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search by item name or SKU"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                <div class="min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stock Level</label>
                    <select name="stock_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Items</option>
                        <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="inventory.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Inventory Table -->
            <div class="xl:col-span-2">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($inventory)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                            <i class="fas fa-warehouse text-4xl mb-4 text-gray-300"></i>
                                            <p>No inventory items found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($inventory as $item): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($item['category_name'] ?: 'No Category') ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['sku']) ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="flex items-center">
                                                    <span class="<?= $item['quantity_in_stock'] <= $item['minimum_stock'] ? 'text-red-600 font-medium' : 'text-gray-900' ?>">
                                                        <?= number_format($item['quantity_in_stock']) ?>
                                                    </span>
                                                    <?php if ($item['quantity_in_stock'] <= $item['minimum_stock']): ?>
                                                        <i class="fas fa-exclamation-triangle text-red-500 ml-2" title="Low stock"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-500">Min: <?= number_format($item['minimum_stock']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">$<?= number_format($item['stock_value'], 2) ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['location'] ?: '-') ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['supplier_name'] ?: '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Movements -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-bold text-gray-900">Recent Stock Movements</h3>
                    </div>
                    <div class="p-6 max-h-96 overflow-y-auto">
                        <?php if (empty($recent_movements)): ?>
                            <p class="text-gray-500 text-center py-4">No recent movements</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_movements as $movement): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center">
                                            <div class="p-2 rounded-full <?= $movement['movement_type'] === 'in' ? 'bg-green-100' : ($movement['movement_type'] === 'out' ? 'bg-red-100' : 'bg-blue-100') ?> mr-3">
                                                <i class="fas <?= $movement['movement_type'] === 'in' ? 'fa-arrow-up text-green-600' : ($movement['movement_type'] === 'out' ? 'fa-arrow-down text-red-600' : 'fa-edit text-blue-600') ?>"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($movement['item_name']) ?></p>
                                                <p class="text-xs text-gray-600"><?= htmlspecialchars($movement['user_name'] ?: 'System') ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium text-sm <?= $movement['movement_type'] === 'in' ? 'text-green-600' : ($movement['movement_type'] === 'out' ? 'text-red-600' : 'text-blue-600') ?>">
                                                <?= $movement['movement_type'] === 'in' ? '+' : ($movement['movement_type'] === 'out' ? '-' : '') ?><?= $movement['quantity'] ?>
                                            </p>
                                            <p class="text-xs text-gray-500"><?= date('M j', strtotime($movement['created_at'])) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock In Modal -->
    <div id="stockInModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Stock In</h3>
                <button onclick="closeModal('stockInModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="stock_in">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item *</label>
                    <select name="item_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Item</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['sku']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                    <select name="supplier_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                    <input type="text" name="reference" placeholder="PO-2024-001" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" name="location" placeholder="Warehouse A-1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
                    <input type="text" name="batch_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('stockInModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">Add Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Out Modal -->
    <div id="stockOutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Stock Out</h3>
                <button onclick="closeModal('stockOutModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="stock_out">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item *</label>
                    <select name="item_id" required onchange="updateCurrentStock(this)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Item</option>
                        <?php foreach ($inventory as $item): ?>
                            <option value="<?= $item['item_id'] ?>" data-stock="<?= $item['quantity_in_stock'] ?>">
                                <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['sku']) ?>) - Current: <?= $item['quantity_in_stock'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="currentStock" class="text-sm text-gray-600 mt-1 hidden">Current stock: <span id="stockAmount">0</span></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                    <input type="text" name="reference" placeholder="SO-2024-001" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" placeholder="Reason for stock removal" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('stockOutModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">Remove Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div id="adjustmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Stock Adjustment</h3>
                <button onclick="closeModal('adjustmentModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="adjustment">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item *</label>
                    <select name="item_id" required onchange="updateCurrentStockAdjust(this)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Item</option>
                        <?php foreach ($inventory as $item): ?>
                            <option value="<?= $item['item_id'] ?>" data-stock="<?= $item['quantity_in_stock'] ?>">
                                <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['sku']) ?>) - Current: <?= $item['quantity_in_stock'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="currentStockAdjust" class="text-sm text-gray-600 mt-1 hidden">Current stock: <span id="stockAmountAdjust">0</span></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Quantity *</label>
                    <input type="number" name="quantity" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                    <input type="text" name="reference" placeholder="ADJ-2024-001" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" placeholder="Reason for adjustment" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('adjustmentModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">Adjust Stock</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }

        function updateCurrentStock(select) {
            const option = select.options[select.selectedIndex];
            const stock = option.dataset.stock || 0;
            const stockDiv = document.getElementById('currentStock');
            const stockAmount = document.getElementById('stockAmount');
            
            if (select.value) {
                stockAmount.textContent = stock;
                stockDiv.classList.remove('hidden');
            } else {
                stockDiv.classList.add('hidden');
            }
        }

        function updateCurrentStockAdjust(select) {
            const option = select.options[select.selectedIndex];
            const stock = option.dataset.stock || 0;
            const stockDiv = document.getElementById('currentStockAdjust');
            const stockAmount = document.getElementById('stockAmountAdjust');
            
            if (select.value) {
                stockAmount.textContent = stock;
                stockDiv.classList.remove('hidden');
            } else {
                stockDiv.classList.add('hidden');
            }
        }

        // Close modal when clicking outside
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>