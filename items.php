<?php
require_once 'config/database.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = $_POST['category_id'] ?? null;
        $unit_price = $_POST['unit_price'] ?? 0;
        $sku = trim($_POST['sku'] ?? '');
        $minimum_stock = $_POST['minimum_stock'] ?? 10;
        
        if (empty($name) || empty($sku) || $unit_price <= 0) {
            $error = 'Please fill in all required fields with valid values.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO items (name, description, category_id, unit_price, sku, minimum_stock) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $category_id ?: null, $unit_price, $sku, $minimum_stock]);
                
                // Also create inventory record
                $item_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO inventory (item_id, quantity_in_stock) VALUES (?, 0)");
                $stmt->execute([$item_id]);
                
                $message = 'Item added successfully!';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'SKU already exists. Please use a unique SKU.';
                } else {
                    $error = 'Error adding item: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = $_POST['category_id'] ?? null;
        $unit_price = $_POST['unit_price'] ?? 0;
        $sku = trim($_POST['sku'] ?? '');
        $minimum_stock = $_POST['minimum_stock'] ?? 10;
        
        if (empty($name) || empty($sku) || $unit_price <= 0) {
            $error = 'Please fill in all required fields with valid values.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE items SET name = ?, description = ?, category_id = ?, unit_price = ?, sku = ?, minimum_stock = ? WHERE id = ?");
                $stmt->execute([$name, $description, $category_id ?: null, $unit_price, $sku, $minimum_stock, $id]);
                $message = 'Item updated successfully!';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'SKU already exists. Please use a unique SKU.';
                } else {
                    $error = 'Error updating item: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Item deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting item: ' . $e->getMessage();
        }
    }
}

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get items with category info
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$sql = "
    SELECT i.*, c.name as category_name, inv.quantity_in_stock
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN inventory inv ON i.id = inv.item_id
    WHERE 1=1
";
$params = [];

if ($search) {
    $sql .= " AND (i.name LIKE ? OR i.sku LIKE ? OR i.description LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($category_filter) {
    $sql .= " AND i.category_id = ?";
    $params[] = $category_filter;
}

$sql .= " ORDER BY i.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Get item for editing
$edit_item = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_item = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items - Inventory Management System</title>
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
                    <span class="text-gray-600">Items</span>
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
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Item Management</h2>
                <p class="text-gray-600">Manage your inventory items</p>
            </div>
            <button onclick="openModal('addModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Item
            </button>
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
                        placeholder="Search by name, SKU, or description"
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
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="items.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Items Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                                    <p>No items found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                            <?php if ($item['description']): ?>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?><?= strlen($item['description']) > 50 ? '...' : '' ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['sku']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['category_name'] ?: 'No Category') ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">$<?= number_format($item['unit_price'], 2) ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="<?= $item['quantity_in_stock'] <= $item['minimum_stock'] ? 'text-red-600 font-medium' : 'text-gray-900' ?>">
                                            <?= number_format($item['quantity_in_stock']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= number_format($item['minimum_stock']) ?></td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button 
                                                onclick="editItem(<?= htmlspecialchars(json_encode($item)) ?>)"
                                                class="text-blue-600 hover:text-blue-900 transition duration-200"
                                                title="Edit"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button 
                                                onclick="deleteItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>')"
                                                class="text-red-600 hover:text-red-900 transition duration-200"
                                                title="Delete"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Add New Item</h3>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SKU *</label>
                    <input type="text" name="sku" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price *</label>
                        <input type="number" name="unit_price" step="0.01" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Stock</label>
                        <input type="number" name="minimum_stock" min="0" value="10" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Edit Item</h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" id="editName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="editDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" id="editCategory" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">No Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SKU *</label>
                    <input type="text" name="sku" id="editSku" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price *</label>
                        <input type="number" name="unit_price" id="editPrice" step="0.01" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Stock</label>
                        <input type="number" name="minimum_stock" id="editMinStock" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">Update Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex items-center mb-4">
                <div class="p-3 rounded-full bg-red-100 mr-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Delete Item</h3>
            </div>
            
            <p class="text-gray-600 mb-6">Are you sure you want to delete "<span id="deleteItemName"></span>"? This action cannot be undone.</p>
            
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">Delete</button>
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

        function editItem(item) {
            document.getElementById('editId').value = item.id;
            document.getElementById('editName').value = item.name;
            document.getElementById('editDescription').value = item.description || '';
            document.getElementById('editCategory').value = item.category_id || '';
            document.getElementById('editSku').value = item.sku;
            document.getElementById('editPrice').value = item.unit_price;
            document.getElementById('editMinStock').value = item.minimum_stock;
            openModal('editModal');
        }

        function deleteItem(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteItemName').textContent = name;
            openModal('deleteModal');
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