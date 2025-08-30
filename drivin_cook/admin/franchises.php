<?php
$pageTitle = 'Gestion des Franchisés';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$success = $error = '';


if ($_POST) {
    switch ($action) {
        case 'add':
            $name = $_POST['name'] ?? '';
            $company_name = $_POST['company_name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $address = $_POST['address'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = password_hash('password', PASSWORD_DEFAULT); // Mot de passe par défaut

            if ($name && $email) {
                try {
                    $pdo->beginTransaction();


                    $stmt = $pdo->prepare("INSERT INTO users (email, password, type) VALUES (?, ?, 'franchisee')");
                    $stmt->execute([$email, $password]);
                    $user_id = $pdo->lastInsertId();


                    $stmt = $pdo->prepare("INSERT INTO franchisees (user_id, name, company_name, phone, address) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $name, $company_name, $phone, $address]);

                    $pdo->commit();
                    $_SESSION['success'] = 'Franchisé ajouté avec succès';
                    header('Location: franchises.php');
                    exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Erreur lors de l\'ajout : ' . $e->getMessage();
                }
            } else {
                $error = 'Nom et email obligatoires';
            }
            break;

        case 'edit':
            $id = $_POST['id'] ?? 0;
            $name = $_POST['name'] ?? '';
            $company_name = $_POST['company_name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $address = $_POST['address'] ?? '';
            $status = $_POST['status'] ?? 'active';

            if ($id && $name) {
                $stmt = $pdo->prepare("UPDATE franchisees SET name = ?, company_name = ?, phone = ?, address = ?, status = ? WHERE id = ?");
                if ($stmt->execute([$name, $company_name, $phone, $address, $status, $id])) {
                    $_SESSION['success'] = 'Franchisé modifié avec succès';
                    header('Location: franchises.php');
                    exit();
                } else {
                    $error = 'Erreur lors de la modification';
                }
            }
            break;
    }
}


if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $pdo->beginTransaction();


        $stmt = $pdo->prepare("SELECT user_id FROM franchisees WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();


        $stmt = $pdo->prepare("DELETE FROM franchisees WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        $_SESSION['success'] = 'Franchisé supprimé avec succès';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Erreur lors de la suppression';
    }
    header('Location: franchises.php');
    exit();
}

$franchisee_to_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM franchisees WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $franchisee_to_edit = $stmt->fetch();
}

$franchisees = $pdo->query("
    SELECT f.*, u.email,
           COUNT(t.id) as truck_count,
           COALESCE(SUM(s.daily_revenue), 0) as total_revenue
    FROM franchisees f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN trucks t ON f.id = t.franchisee_id
    LEFT JOIN sales s ON f.id = s.franchisee_id AND MONTH(s.sale_date) = MONTH(CURRENT_DATE)
    GROUP BY f.id
    ORDER BY f.created_at DESC
")->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-users me-2"></i>Gestion des Franchisés</h1>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouveau franchisé
            </a>
        <?php else: ?>
            <a href="franchises.php" class="btn btn-secondary">
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
                <h5><?php echo $action === 'add' ? 'Nouveau franchisé' : 'Modifier le franchisé'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?php if ($franchisee_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $franchisee_to_edit['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nom complet *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    value="<?php echo htmlspecialchars($franchisee_to_edit['name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Nom de l'entreprise</label>
                                <input type="text" class="form-control" id="company_name" name="company_name"
                                    value="<?php echo htmlspecialchars($franchisee_to_edit['company_name'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <?php if ($action === 'add'): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                    <div class="form-text">Mot de passe par défaut : "password"</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                    value="<?php echo htmlspecialchars($franchisee_to_edit['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <?php if ($action === 'edit'): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Statut</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo ($franchisee_to_edit['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Actif</option>
                                        <option value="inactive" <?php echo ($franchisee_to_edit['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Adresse</label>
                        <textarea class="form-control" id="address" name="address"
                            rows="3"><?php echo htmlspecialchars($franchisee_to_edit['address'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $action === 'add' ? 'Ajouter' : 'Modifier'; ?>
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5>Liste des franchisés (<?php echo count($franchisees); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($franchisees)): ?>
                    <p class="text-muted text-center">Aucun franchisé enregistré</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Entreprise</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Camions</th>
                                    <th>CA du mois</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($franchisees as $franchisee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($franchisee['name']); ?></td>
                                        <td><?php echo htmlspecialchars($franchisee['company_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($franchisee['email']); ?></td>
                                        <td><?php echo htmlspecialchars($franchisee['phone'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $franchisee['truck_count']; ?></span>
                                        </td>
                                        <td><?php echo number_format($franchisee['total_revenue'], 2); ?> €</td>
                                        <td>
                                            <span class="badge status-<?php echo $franchisee['status']; ?>">
                                                <?php echo ucfirst($franchisee['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $franchisee['id']; ?>"
                                                class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $franchisee['id']; ?>"
                                                class="btn btn-sm btn-danger btn-delete"
                                                data-name="<?php echo htmlspecialchars($franchisee['name']); ?>">
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