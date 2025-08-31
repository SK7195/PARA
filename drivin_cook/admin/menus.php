<?php
$pageTitle = 'Gestion des Menus';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$success = $error = '';

if ($_POST) {
    switch ($action) {
        case 'add':
            $name_fr = $_POST['name_fr'] ?? '';
            $name_en = $_POST['name_en'] ?? '';
            $name_es = $_POST['name_es'] ?? '';
            $description_fr = $_POST['description_fr'] ?? '';
            $description_en = $_POST['description_en'] ?? '';
            $description_es = $_POST['description_es'] ?? '';
            $price = $_POST['price'] ?? 0;
            $category = $_POST['category'] ?? '';
            $image_url = $_POST['image_url'] ?? '';
            
            if ($name_fr && $name_en && $name_es && $price > 0 && $category) {
                $stmt = $pdo->prepare("
                    INSERT INTO menus (name_fr, name_en, name_es, description_fr, description_en, description_es, price, category, image_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$name_fr, $name_en, $name_es, $description_fr, $description_en, $description_es, $price, $category, $image_url])) {
                    $_SESSION['success'] = 'Plat ajouté avec succès';
                    header('Location: menus.php');
                    exit();
                } else {
                    $error = 'Erreur lors de l\'ajout';
                }
            } else {
                $error = 'Tous les champs obligatoires doivent être remplis';
            }
            break;

        case 'edit':
            $id = $_POST['id'] ?? 0;
            $name_fr = $_POST['name_fr'] ?? '';
            $name_en = $_POST['name_en'] ?? '';
            $name_es = $_POST['name_es'] ?? '';
            $description_fr = $_POST['description_fr'] ?? '';
            $description_en = $_POST['description_en'] ?? '';
            $description_es = $_POST['description_es'] ?? '';
            $price = $_POST['price'] ?? 0;
            $category = $_POST['category'] ?? '';
            $image_url = $_POST['image_url'] ?? '';
            $available = isset($_POST['available']) ? 1 : 0;
            
            if ($id && $name_fr && $name_en && $name_es && $price > 0 && $category) {
                $stmt = $pdo->prepare("
                    UPDATE menus SET name_fr = ?, name_en = ?, name_es = ?, description_fr = ?, description_en = ?, 
                    description_es = ?, price = ?, category = ?, image_url = ?, available = ? WHERE id = ?
                ");
                if ($stmt->execute([$name_fr, $name_en, $name_es, $description_fr, $description_en, $description_es, 
                                   $price, $category, $image_url, $available, $id])) {
                    $_SESSION['success'] = 'Plat modifié avec succès';
                    header('Location: menus.php');
                    exit();
                } else {
                    $error = 'Erreur lors de la modification';
                }
            }
            break;
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = 'Plat supprimé avec succès';
    } else {
        $_SESSION['error'] = 'Erreur lors de la suppression';
    }
    header('Location: menus.php');
    exit();
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE menus SET available = !available WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = 'Disponibilité mise à jour';
    }
    header('Location: menus.php');
    exit();
}

$menu_to_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $menu_to_edit = $stmt->fetch();
}

$menus = $pdo->query("
    SELECT m.*, 
           COUNT(coi.id) as order_count,
           COALESCE(SUM(coi.quantity), 0) as total_sold
    FROM menus m
    LEFT JOIN client_order_items coi ON m.id = coi.menu_id
    LEFT JOIN client_orders co ON coi.order_id = co.id AND co.status IN ('completed', 'ready')
    GROUP BY m.id
    ORDER BY m.category, m.name_fr
")->fetchAll();

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_menus,
        COUNT(CASE WHEN available = 1 THEN 1 END) as available_menus,
        AVG(price) as avg_price,
        MIN(price) as min_price,
        MAX(price) as max_price
    FROM menus
")->fetch();

$category_stats = $pdo->query("
    SELECT 
        category,
        COUNT(*) as menu_count,
        AVG(price) as avg_price,
        COUNT(CASE WHEN available = 1 THEN 1 END) as available_count
    FROM menus
    GROUP BY category
    ORDER BY category
")->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-utensils me-2"></i>Gestion des Menus</h1>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Nouveau plat
            </a>
        <?php else: ?>
            <a href="menus.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Retour à la liste
            </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="card">
            <div class="card-header">
                <h5><?php echo $action === 'add' ? 'Nouveau plat' : 'Modifier le plat'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?php if ($menu_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $menu_to_edit['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="name_fr" class="form-label">🇫🇷 Nom (Français) *</label>
                                <input type="text" class="form-control" id="name_fr" name="name_fr" 
                                       value="<?php echo htmlspecialchars($menu_to_edit['name_fr'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="name_en" class="form-label">🇬🇧 Nom (Anglais) *</label>
                                <input type="text" class="form-control" id="name_en" name="name_en" 
                                       value="<?php echo htmlspecialchars($menu_to_edit['name_en'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="name_es" class="form-label">🇪🇸 Nom (Espagnol) *</label>
                                <input type="text" class="form-control" id="name_es" name="name_es" 
                                       value="<?php echo htmlspecialchars($menu_to_edit['name_es'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="description_fr" class="form-label">🇫🇷 Description (Français)</label>
                                <textarea class="form-control" id="description_fr" name="description_fr" rows="3"><?php echo htmlspecialchars($menu_to_edit['description_fr'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="description_en" class="form-label">🇬🇧 Description (Anglais)</label>
                                <textarea class="form-control" id="description_en" name="description_en" rows="3"><?php echo htmlspecialchars($menu_to_edit['description_en'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="description_es" class="form-label">🇪🇸 Description (Espagnol)</label>
                                <textarea class="form-control" id="description_es" name="description_es" rows="3"><?php echo htmlspecialchars($menu_to_edit['description_es'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="price" class="form-label">Prix (€) *</label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0"
                                       value="<?php echo $menu_to_edit['price'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="category" class="form-label">Catégorie *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Choisir une catégorie</option>
                                    <option value="burger" <?php echo ($menu_to_edit['category'] ?? '') === 'burger' ? 'selected' : ''; ?>>🍔 Burgers</option>
                                    <option value="salad" <?php echo ($menu_to_edit['category'] ?? '') === 'salad' ? 'selected' : ''; ?>>🥗 Salades</option>
                                    <option value="drink" <?php echo ($menu_to_edit['category'] ?? '') === 'drink' ? 'selected' : ''; ?>>🥤 Boissons</option>
                                    <option value="dessert" <?php echo ($menu_to_edit['category'] ?? '') === 'dessert' ? 'selected' : ''; ?>>🍰 Desserts</option>
                                    <option value="starter" <?php echo ($menu_to_edit['category'] ?? '') === 'starter' ? 'selected' : ''; ?>>🍤 Entrées</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="image_url" class="form-label">URL de l'image</label>
                                <input type="url" class="form-control" id="image_url" name="image_url" 
                                       value="<?php echo htmlspecialchars($menu_to_edit['image_url'] ?? ''); ?>"
                                       placeholder="https://exemple.com/image.jpg">
                            </div>
                        </div>
                    </div>

                    <?php if ($action === 'edit'): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="available" name="available" 
                                       <?php echo ($menu_to_edit['available'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="available">
                                    Plat disponible à la commande
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i><?php echo $action === 'add' ? 'Ajouter' : 'Modifier'; ?>
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card stat-card-info">
                    <div class="text-center">
                        <h4><?php echo $stats['total_menus']; ?></h4>
                        <p class="mb-0">Total plats</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card stat-card-success">
                    <div class="text-center">
                        <h4><?php echo $stats['available_menus']; ?></h4>
                        <p class="mb-0">Disponibles</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card stat-card-warning">
                    <div class="text-center">
                        <h4><?php echo number_format($stats['avg_price'], 2); ?> €</h4>
                        <p class="mb-0">Prix moyen</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-center">
                        <h5><?php echo number_format($stats['min_price'], 2); ?> € - <?php echo number_format($stats['max_price'], 2); ?> €</h5>
                        <p class="mb-0">Fourchette de prix</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Par catégorie</h6>
                    </div>
                    <div class="card-body py-2">
                        <?php foreach ($category_stats as $cat): ?>
                            <div class="d-flex justify-content-between">
                                <small><?php 
                                    $icons = ['burger' => '🍔', 'salad' => '🥗', 'drink' => '🥤', 'dessert' => '🍰', 'starter' => '🍤'];
                                    echo ($icons[$cat['category']] ?? '') . ' ' . ucfirst($cat['category']); 
                                ?></small>
                                <small><strong><?php echo $cat['available_count']; ?>/<?php echo $cat['menu_count']; ?></strong></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>


        <div class="card">
            <div class="card-header">
                <h5>Liste des plats (<?php echo count($menus); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($menus)): ?>
                    <p class="text-muted text-center">Aucun plat enregistré</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Plat</th>
                                    <th>Catégorie</th>
                                    <th>Prix</th>
                                    <th>Statut</th>
                                    <th>Ventes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $current_category = '';
                                foreach ($menus as $menu): 
                                    if ($current_category !== $menu['category']):
                                        $current_category = $menu['category'];
                                        $icons = ['burger' => '🍔', 'salad' => '🥗', 'drink' => '🥤', 'dessert' => '🍰', 'starter' => '🍤'];
                                ?>
                                        <tr class="table-secondary">
                                            <td colspan="7">
                                                <strong><?php echo ($icons[$current_category] ?? '') . ' ' . strtoupper($current_category); ?></strong>
                                            </td>
                                        </tr>
                                <?php endif; ?>
                                <tr>
                                    <td>
                                        <?php if ($menu['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($menu['image_url']); ?>" 
                                                 class="rounded" width="50" height="50" style="object-fit: cover;"
                                                 alt="<?php echo htmlspecialchars($menu['name_fr']); ?>">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($menu['name_fr']); ?></strong>
                                        <br><small class="text-muted">🇬🇧 <?php echo htmlspecialchars($menu['name_en']); ?></small>
                                        <br><small class="text-muted">🇪🇸 <?php echo htmlspecialchars($menu['name_es']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($menu['category']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo number_format($menu['price'], 2); ?> €</strong></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   <?php echo $menu['available'] ? 'checked' : ''; ?>
                                                   onchange="window.location.href='?toggle=<?php echo $menu['id']; ?>'">
                                            <label class="form-check-label">
                                                <?php echo $menu['available'] ? 'Disponible' : 'Indisponible'; ?>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($menu['total_sold'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $menu['total_sold']; ?> vendus</span>
                                        <?php else: ?>
                                            <span class="text-muted">Aucune vente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $menu['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $menu['id']; ?>" 
                                           class="btn btn-sm btn-danger btn-delete"
                                           data-name="<?php echo htmlspecialchars($menu['name_fr']); ?>">
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
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>