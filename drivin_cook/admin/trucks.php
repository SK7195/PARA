<?php
$pageTitle = 'Gestion des Camions';
require_once '../includes/header.php';
requireAdmin();
$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$error = '';

if ($_POST) {
    switch ($action) {
        case 'add':
            $license_plate = $_POST['license_plate'] ?? '';
            $model = $_POST['model'] ?? '';
            $franchisee_id = $_POST['franchisee_id'] ?? null;
            $location = $_POST['location'] ?? '';
            
            if ($license_plate && $model) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM trucks WHERE license_plate = ?");
                    $stmt->execute([$license_plate]);
                    if ($stmt->fetch()) {
                        $error = 'Cette plaque d\'immatriculation existe déjà';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO trucks (license_plate, model, franchisee_id, location, status) VALUES (?, ?, ?, ?, ?)");
                        $status = $franchisee_id ? 'assigned' : 'available';
                        if ($stmt->execute([$license_plate, $model, $franchisee_id ?: null, $location, $status])) {
                            $_SESSION['success'] = 'Camion ajouté avec succès';
                            header('Location: trucks.php');
                            exit();
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Erreur : ' . $e->getMessage();
                }
            } else {
                $error = 'Plaque et modèle obligatoires';
            }
            break;
            
        case 'edit':
            $id = $_POST['id'] ?? 0;
            $license_plate = $_POST['license_plate'] ?? '';
            $model = $_POST['model'] ?? '';
            $franchisee_id = $_POST['franchisee_id'] ?? null;
            $status = $_POST['status'] ?? 'available';
            $location = $_POST['location'] ?? '';
            $last_maintenance = $_POST['last_maintenance'] ?? null;
            
            if ($id && $license_plate && $model) {
                $stmt = $pdo->prepare("UPDATE trucks SET license_plate = ?, model = ?, franchisee_id = ?, status = ?, location = ?, last_maintenance = ? WHERE id = ?");
                if ($stmt->execute([$license_plate, $model, $franchisee_id ?: null, $status, $location, $last_maintenance ?: null, $id])) {
                    $_SESSION['success'] = 'Camion modifié avec succès';
                    header('Location: trucks.php');
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
    $stmt = $pdo->prepare("DELETE FROM trucks WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success'] = 'Camion supprimé avec succès';
    } else {
        $_SESSION['error'] = 'Erreur lors de la suppression';
    }
    header('Location: trucks.php');
    exit();
}

$truck_to_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM trucks WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $truck_to_edit = $stmt->fetch();
}

$franchisees = $pdo->query("SELECT id, name, company_name FROM franchisees WHERE status = 'active' ORDER BY name")->fetchAll();

$trucks = $pdo->query("
    SELECT t.*, f.name as franchisee_name, f.company_name
    FROM trucks t
    LEFT JOIN franchisees f ON t.franchisee_id = f.id
    ORDER BY t.created_at DESC
")->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-truck me-2"></i>Gestion des Camions</h1>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouveau camion
            </a>
        <?php else: ?>
            <a href="trucks.php" class="btn btn-secondary">
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
                <h5><?php echo $action === 'add' ? 'Nouveau camion' : 'Modifier le camion'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?php if ($truck_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $truck_to_edit['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="license_plate" class="form-label">Plaque d'immatriculation *</label>
                                <input type="text" class="form-control" id="license_plate" name="license_plate" 
                                       placeholder="AB-123-CD" value="<?php echo htmlspecialchars($truck_to_edit['license_plate'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="model" class="form-label">Modèle *</label>
                                <input type="text" class="form-control" id="model" name="model" 
                                       placeholder="Food Truck Premium" value="<?php echo htmlspecialchars($truck_to_edit['model'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="franchisee_id" class="form-label">Franchisé assigné</label>
                                <select class="form-select" id="franchisee_id" name="franchisee_id">
                                    <option value="">Non assigné</option>
                                    <?php foreach ($franchisees as $franchisee): ?>
                                        <option value="<?php echo $franchisee['id']; ?>" 
                                                <?php echo ($truck_to_edit['franchisee_id'] ?? '') == $franchisee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($franchisee['name'] . ' - ' . ($franchisee['company_name'] ?? 'Pas d\'entreprise')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php if ($action === 'edit'): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Statut</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="available" <?php echo ($truck_to_edit['status'] ?? '') === 'available' ? 'selected' : ''; ?>>Disponible</option>
                                        <option value="assigned" <?php echo ($truck_to_edit['status'] ?? '') === 'assigned' ? 'selected' : ''; ?>>Assigné</option>
                                        <option value="maintenance" <?php echo ($truck_to_edit['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>En maintenance</option>
                                        <option value="broken" <?php echo ($truck_to_edit['status'] ?? '') === 'broken' ? 'selected' : ''; ?>>En panne</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Emplacement actuel</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="Paris 12ème" value="<?php echo htmlspecialchars($truck_to_edit['location'] ?? ''); ?>">
                            </div>
                        </div>
                        <?php if ($action === 'edit'): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_maintenance" class="form-label">Dernière maintenance</label>
                                    <input type="date" class="form-control" id="last_maintenance" name="last_maintenance" 
                                           value="<?php echo $truck_to_edit['last_maintenance'] ?? ''; ?>">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $action === 'add' ? 'Ajouter' : 'Modifier'; ?>
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>

        <div class="row mb-4">
            <?php
            $stats = [
                'total' => count($trucks),
                'available' => count(array_filter($trucks, fn($t) => $t['status'] === 'available')),
                'assigned' => count(array_filter($trucks, fn($t) => $t['status'] === 'assigned')),
                'maintenance' => count(array_filter($trucks, fn($t) => $t['status'] === 'maintenance')),
                'broken' => count(array_filter($trucks, fn($t) => $t['status'] === 'broken'))
            ];
            ?>
            <div class="col-md-2">
                <div class="stat-card stat-card-info">
                    <div class="text-center">
                        <h4><?php echo $stats['total']; ?></h4>
                        <p class="mb-0">Total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card stat-card-success">
                    <div class="text-center">
                        <h4><?php echo $stats['available']; ?></h4>
                        <p class="mb-0">Disponibles</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="text-center">
                        <h4><?php echo $stats['assigned']; ?></h4>
                        <p class="mb-0">Assignés</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-warning">
                    <div class="text-center">
                        <h4><?php echo $stats['maintenance']; ?></h4>
                        <p class="mb-0">Maintenance</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                    <div class="text-center">
                        <h4><?php echo $stats['broken']; ?></h4>
                        <p class="mb-0">En panne</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Liste des camions (<?php echo count($trucks); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($trucks)): ?>
                    <p class="text-muted text-center">Aucun camion enregistré</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Plaque</th>
                                    <th>Modèle</th>
                                    <th>Franchisé</th>
                                    <th>Statut</th>
                                    <th>Emplacement</th>
                                    <th>Dernière maintenance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trucks as $truck): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($truck['license_plate']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($truck['model']); ?></td>
                                        <td>
                                            <?php if ($truck['franchisee_name']): ?>
                                                <?php echo htmlspecialchars($truck['franchisee_name']); ?>
                                                <?php if ($truck['company_name']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($truck['company_name']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non assigné</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge status-<?php echo $truck['status']; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'available' => 'Disponible',
                                                    'assigned' => 'Assigné',
                                                    'maintenance' => 'Maintenance',
                                                    'broken' => 'En panne'
                                                ];
                                                echo $status_labels[$truck['status']] ?? $truck['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($truck['location'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($truck['last_maintenance']): ?>
                                                <?php echo date('d/m/Y', strtotime($truck['last_maintenance'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Jamais</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $truck['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $truck['id']; ?>" 
                                               class="btn btn-sm btn-danger btn-delete" 
                                               data-name="le camion <?php echo htmlspecialchars($truck['license_plate']); ?>">
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