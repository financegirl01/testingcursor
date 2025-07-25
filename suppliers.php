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
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        if (empty($name)) {
            $error = 'Supplier name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers (name, contact_person, email, phone, address, city, state, zip_code, country) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $contact_person, $email, $phone, $address, $city, $state, $zip_code, $country]);
                $message = 'Supplier added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding supplier: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        if (empty($name)) {
            $error = 'Supplier name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, country = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $contact_person, $email, $phone, $address, $city, $state, $zip_code, $country, $id]);
                $message = 'Supplier updated successfully!';
            } catch (PDOException $e) {
                $error = 'Error updating supplier: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Supplier deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting supplier: ' . $e->getMessage();
        }
    }
}

// Get suppliers with item count
$search = $_GET['search'] ?? '';

$sql = "
    SELECT s.*, COUNT(i.id) as item_count
    FROM suppliers s
    LEFT JOIN inventory inv ON s.id = inv.supplier_id
    LEFT JOIN items i ON inv.item_id = i.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $sql .= " AND (s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.city LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$sql .= " GROUP BY s.id ORDER BY s.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - Inventory Management System</title>
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
                    <span class="text-gray-600">Suppliers</span>
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
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Supplier Management</h2>
                <p class="text-gray-600">Manage your suppliers and vendor relationships</p>
            </div>
            <button onclick="openModal('addModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-plus mr-2"></i>Add Supplier
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

        <!-- Search -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <form method="GET" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Suppliers</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search by name, contact person, email, or city"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <a href="suppliers.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Suppliers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($suppliers)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-truck text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No suppliers found</h3>
                    <p class="text-gray-600">Get started by adding your first supplier.</p>
                </div>
            <?php else: ?>
                <?php foreach ($suppliers as $supplier): ?>
                    <div class="bg-white rounded-lg shadow hover:shadow-lg transition duration-200">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-purple-100 mr-3">
                                        <i class="fas fa-truck text-purple-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($supplier['name']) ?></h3>
                                        <?php if ($supplier['item_count'] > 0): ?>
                                            <span class="text-sm text-gray-600"><?= $supplier['item_count'] ?> items supplied</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button 
                                        onclick="editSupplier(<?= htmlspecialchars(json_encode($supplier)) ?>)"
                                        class="text-blue-600 hover:text-blue-900 transition duration-200"
                                        title="Edit"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button 
                                        onclick="deleteSupplier(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name']) ?>')"
                                        class="text-red-600 hover:text-red-900 transition duration-200"
                                        title="Delete"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <?php if ($supplier['contact_person']): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-user w-4 mr-2"></i>
                                        <?= htmlspecialchars($supplier['contact_person']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($supplier['email']): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-envelope w-4 mr-2"></i>
                                        <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" class="hover:text-blue-600">
                                            <?= htmlspecialchars($supplier['email']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($supplier['phone']): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-phone w-4 mr-2"></i>
                                        <a href="tel:<?= htmlspecialchars($supplier['phone']) ?>" class="hover:text-blue-600">
                                            <?= htmlspecialchars($supplier['phone']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($supplier['address']): ?>
                                    <div class="flex items-start text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt w-4 mr-2 mt-0.5"></i>
                                        <div>
                                            <?= htmlspecialchars($supplier['address']) ?>
                                            <?php if ($supplier['city'] || $supplier['state']): ?>
                                                <br><?= htmlspecialchars($supplier['city']) ?><?= $supplier['city'] && $supplier['state'] ? ', ' : '' ?><?= htmlspecialchars($supplier['state']) ?>
                                                <?php if ($supplier['zip_code']): ?> <?= htmlspecialchars($supplier['zip_code']) ?><?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($supplier['country']): ?>
                                                <br><?= htmlspecialchars($supplier['country']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4 pt-4 border-t text-xs text-gray-500">
                                Added <?= date('M j, Y', strtotime($supplier['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Add New Supplier</h3>
                <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                    <input type="text" name="contact_person" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                        <input type="text" name="zip_code" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                    <input type="text" name="country" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Supplier Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Edit Supplier</h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-4" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
                    <input type="text" name="name" id="editName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                    <input type="text" name="contact_person" id="editContactPerson" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="editEmail" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" id="editPhone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" id="editAddress" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" id="editCity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" id="editState" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                        <input type="text" name="zip_code" id="editZipCode" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                    <input type="text" name="country" id="editCountry" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition duration-200">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">Update Supplier</button>
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
                <h3 class="text-lg font-bold text-gray-900">Delete Supplier</h3>
            </div>
            
            <p class="text-gray-600 mb-6">Are you sure you want to delete "<span id="deleteSupplierName"></span>"? This action cannot be undone.</p>
            
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

        function editSupplier(supplier) {
            document.getElementById('editId').value = supplier.id;
            document.getElementById('editName').value = supplier.name;
            document.getElementById('editContactPerson').value = supplier.contact_person || '';
            document.getElementById('editEmail').value = supplier.email || '';
            document.getElementById('editPhone').value = supplier.phone || '';
            document.getElementById('editAddress').value = supplier.address || '';
            document.getElementById('editCity').value = supplier.city || '';
            document.getElementById('editState').value = supplier.state || '';
            document.getElementById('editZipCode').value = supplier.zip_code || '';
            document.getElementById('editCountry').value = supplier.country || '';
            openModal('editModal');
        }

        function deleteSupplier(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteSupplierName').textContent = name;
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