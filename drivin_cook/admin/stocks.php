<?php
$pageTitle = 'Gestion des Stocks';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$error = '';

if ($_POST) {
    switch ($action) {
        case 'add_product':
            $name = $_POST['name'] ?? '';
            $category = $_POST['category'] ?? '';
            $price = $_POST['price'] ?? 0;
            $warehouse_id = $_POST['warehouse_id'] ?? 0;
            $stock_quantity = $_POST['stock_quantity'] ?? 0;

            if ($name && $category && $price > 0 && $warehouse_id) {
                $stmt = $pdo->prepare("INSERT INTO products (name, category, price, warehouse_id, stock_quantity) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $category, $price, $warehouse_id, $stock_quantity])) {
                    $_SESSION['success'] = 'Produit ajouté avec succès';
                    header('Location: stocks.php');
                    exit();
                } else {
                    $error = 'Erreur lors de l\'ajout';
                }
            } else {
                $error = 'Tous les champs obligatoires doivent être remplis';
            }
            break;

        case 'update_stock':
            $product_id = $_POST['product_id'] ?? 0;
            $new_quantity = $_POST['new_quantity'] ?? 0;

            if ($product_id && $new_quantity >= 0) {
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                if ($stmt->execute([$new_quantity, $product_id])) {
                    $_SESSION['success'] = 'Stock mis à jour';
                    header('Location: stocks.php');
                    exit();
                }
            }
            break;
    }
}

if (isset($_GET['delete_product'])) {
    $id = (int) $_GET['delete_product'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = 'Produit supprimé avec succès';
    } else {
        $_SESSION['error'] = 'Erreur lors de la suppression';
    }
    header('Location: stocks.php');
    exit();
}

$warehouses = $pdo->query("SELECT * FROM warehouses ORDER BY name")->fetchAll();

$products = $pdo->query("
    SELECT p.*, w.name as warehouse_name
    FROM products p
    JOIN warehouses w ON p.warehouse_id = w.id
    ORDER BY w.name, p.name
")->fetchAll();

$warehouse_stats = $pdo->query("
    SELECT w.name, w.manager_name,
           COUNT(p.id) as product_count,
           SUM(p.stock_quantity) as total_items,
           SUM(p.stock_quantity * p.price) as total_value
    FROM warehouses w
    LEFT JOIN products p ON w.id = p.warehouse_id
    GROUP BY w.id
    ORDER BY w.name
")->fetchAll();

$recent_orders = $pdo->query("
    SELECT so.*, f.name as franchisee_name, w.name as warehouse_name
    FROM stock_orders so
    JOIN franchisees f ON so.franchisee_id = f.id
    JOIN warehouses w ON so.warehouse_id = w.id
    ORDER BY so.order_date DESC
    LIMIT 10
")->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-boxes me-2"></i>Gestion des Stocks</h1>
        <a href="?action=add_product" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nouveau produit
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add_product'): ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5>Nouveau produit</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nom du produit *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Catégorie *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Choisir une catégorie</option>
                                    <option value="ingredient">Ingrédient</option>
                                    <option value="prepared_dish">Plat préparé</option>
                                    <option value="beverage">Boisson</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Prix unitaire (€) *</label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0"
                                    required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="warehouse_id" class="form-label">Entrepôt *</label>
                                <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                                    <option value="">Choisir un entrepôt</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?php echo $warehouse['id']; ?>">
                                            <?php echo htmlspecialchars($warehouse['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stock_quantity" class="form-label">Quantité initiale</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0"
                                    value="0">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-save me-2"></i>Ajouter
                    </button>
                    <a href="stocks.php" class="btn btn-secondary">Annuler</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <?php foreach ($warehouse_stats as $stat): ?>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6><?php echo htmlspecialchars($stat['name']); ?></h6>
                            <p class="mb-1"><strong><?php echo $stat['product_count']; ?></strong> produits</p>
                            <p class="mb-1"><?php echo number_format($stat['total_items']); ?> articles</p>
                            <p class="mb-0"><?php echo number_format($stat['total_value'], 2); ?> € de valeur</p>
                        </div>
                        <i class="fas fa-warehouse fa-2x opacity-50"></i>
                    </div>
                    <small class="text-muted">Manager: <?php echo htmlspecialchars($stat['manager_name']); ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Inventaire des produits (<?php echo count($products); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <p class="text-muted text-center">Aucun produit en stock</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Catégorie</th>
                                        <th>Prix</th>
                                        <th>Entrepôt</th>
                                        <th>Stock</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr class="<?php echo $product['stock_quantity'] < 10 ? 'table-warning' : ''; ?>">
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>
                                                <?php
                                                $categories = [
                                                    'ingredient' => 'Ingrédient',
                                                    'prepared_dish' => 'Plat préparé',
                                                    'beverage' => 'Boisson'
                                                ];
                                                echo $categories[$product['category']] ?? $product['category'];
                                                ?>
                                            </td>
                                            <td><?php echo number_format($product['price'], 2); ?> €</td>
                                            <td><?php echo htmlspecialchars($product['warehouse_name']); ?></td>
                                            <td>
                                                <form method="POST" action="?action=update_stock"
                                                    class="d-flex align-items-center" style="min-width: 120px;">
                                                    <input type="hidden" name="product_id"
                                                        value="<?php echo $product['id']; ?>">
                                                    <input type="number" name="new_quantity"
                                                        value="<?php echo $product['stock_quantity']; ?>"
                                                        class="form-control form-control-sm me-1" style="width: 70px;" min="0">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <?php if ($product['stock_quantity'] < 10): ?>
                                                    <small class="text-warning"><i
                                                            class="fas fa-exclamation-triangle me-1"></i>Stock faible</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?delete_product=<?php echo $product['id']; ?>"
                                                    class="btn btn-sm btn-danger btn-delete"
                                                    data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Commandes récentes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-muted text-center">Aucune commande</p>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($order['franchisee_name']); ?></strong>
                                    <span class="badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($order['warehouse_name']); ?><br>
                                    <?php echo date('d/m/Y', strtotime($order['order_date'])); ?> -
                                    <?php echo number_format($order['total_amount'], 2); ?> €
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Contrôle 80/20</h5>
                </div>
                <div class="card-body">
                    <?php

                    $compliance_check = $pdo->query("
                        SELECT f.name,
                               COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_purchases,
                               COALESCE(SUM(CASE WHEN p.warehouse_id IN (1,2,3,4) THEN oi.quantity * oi.unit_price ELSE 0 END), 0) as driv_purchases
                        FROM franchisees f
                        LEFT JOIN stock_orders so ON f.id = so.franchisee_id
                        LEFT JOIN order_items oi ON so.id = oi.order_id
                        LEFT JOIN products p ON oi.product_id = p.id
                        WHERE f.status = 'active'
                        GROUP BY f.id
                        HAVING total_purchases > 0
                        ORDER BY (driv_purchases / total_purchases) ASC
                    ")->fetchAll();
                    ?>

                    <?php if (empty($compliance_check)): ?>
                        <p class="text-muted">Aucune donnée d'achat disponible</p>
                    <?php else: ?>
                        <?php foreach (array_slice($compliance_check, 0, 5) as $check): ?>
                            <?php
                            $percentage = $check['total_purchases'] > 0 ? ($check['driv_purchases'] / $check['total_purchases']) * 100 : 0;
                            $is_compliant = $percentage >= 80;
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small><?php echo htmlspecialchars($check['name']); ?></small>
                                    <span class="badge <?php echo $is_compliant ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo $is_compliant ? 'bg-success' : 'bg-warning'; ?>"
                                        style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>