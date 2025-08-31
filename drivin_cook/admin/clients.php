<?php
$pageTitle = 'Gestion des Clients';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$error = $success = '';

if ($_POST) {
    switch ($action) {
        case 'add':
            $firstname = $_POST['firstname'] ?? '';
            $lastname = $_POST['lastname'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $language = $_POST['language'] ?? 'fr';
            $password = password_hash('password', PASSWORD_DEFAULT);

            if ($firstname && $lastname && $email) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Cette adresse email existe déjà';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO clients (firstname, lastname, email, phone, password, language) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$firstname, $lastname, $email, $phone, $password, $language]);
                        
                        $_SESSION['success'] = 'Client ajouté avec succès';
                        header('Location: clients.php');
                        exit();
                    }
                } catch (Exception $e) {
                    $error = 'Erreur lors de l\'ajout : ' . $e->getMessage();
                }
            } else {
                $error = 'Prénom, nom et email obligatoires';
            }
            break;

        case 'edit':
            $id = $_POST['id'] ?? 0;
            $firstname = $_POST['firstname'] ?? '';
            $lastname = $_POST['lastname'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $language = $_POST['language'] ?? 'fr';
            $loyalty_points = $_POST['loyalty_points'] ?? 0;

            if ($id && $firstname && $lastname) {
                $stmt = $pdo->prepare("UPDATE clients SET firstname = ?, lastname = ?, phone = ?, language = ?, loyalty_points = ? WHERE id = ?");
                if ($stmt->execute([$firstname, $lastname, $phone, $language, $loyalty_points, $id])) {
                    $_SESSION['success'] = 'Client modifié avec succès';
                    header('Location: clients.php');
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
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = 'Client supprimé avec succès';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erreur lors de la suppression';
    }
    header('Location: clients.php');
    exit();
}

$client_to_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $client_to_edit = $stmt->fetch();
}

// CORRECTION: Requête SQL corrigée pour respecter only_full_group_by
$clients = $pdo->query("
    SELECT c.id, c.firstname, c.lastname, c.email, c.phone, c.language, c.loyalty_points, c.created_at,
           COUNT(co.id) as total_orders,
           COALESCE(SUM(co.total_amount), 0) as total_spent,
           CASE 
               WHEN MAX(ns.subscribed) = 1 THEN 'Oui' 
               ELSE 'Non' 
           END as newsletter_subscribed
    FROM clients c
    LEFT JOIN client_orders co ON c.id = co.client_id AND co.status IN ('confirmed', 'completed')
    LEFT JOIN newsletter_subscribers ns ON c.id = ns.client_id
    GROUP BY c.id, c.firstname, c.lastname, c.email, c.phone, c.language, c.loyalty_points, c.created_at
    ORDER BY c.created_at DESC
")->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-users me-2"></i>Gestion des Clients</h1>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouveau client
            </a>
        <?php else: ?>
            <a href="clients.php" class="btn btn-secondary">
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
                <h5><?php echo $action === 'add' ? 'Nouveau client' : 'Modifier le client'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?php if ($client_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $client_to_edit['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="firstname" class="form-label">Prénom *</label>
                                <input type="text" class="form-control" id="firstname" name="firstname"
                                    value="<?php echo htmlspecialchars($client_to_edit['firstname'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="lastname" class="form-label">Nom *</label>
                                <input type="text" class="form-control" id="lastname" name="lastname"
                                    value="<?php echo htmlspecialchars($client_to_edit['lastname'] ?? ''); ?>" required>
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
                                    value="<?php echo htmlspecialchars($client_to_edit['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="language" class="form-label">Langue préférée</label>
                                <select class="form-select" id="language" name="language">
                                    <option value="fr" <?php echo ($client_to_edit['language'] ?? 'fr') === 'fr' ? 'selected' : ''; ?>>🇫🇷 Français</option>
                                    <option value="en" <?php echo ($client_to_edit['language'] ?? '') === 'en' ? 'selected' : ''; ?>>🇬🇧 English</option>
                                    <option value="es" <?php echo ($client_to_edit['language'] ?? '') === 'es' ? 'selected' : ''; ?>>🇪🇸 Español</option>
                                </select>
                            </div>
                        </div>
                        <?php if ($action === 'edit'): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="loyalty_points" class="form-label">Points fidélité</label>
                                    <input type="number" class="form-control" id="loyalty_points" name="loyalty_points"
                                        value="<?php echo $client_to_edit['loyalty_points'] ?? 0; ?>" min="0">
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
            $total_clients = count($clients);
            $total_spent = array_sum(array_column($clients, 'total_spent'));
            $avg_orders = $total_clients > 0 ? array_sum(array_column($clients, 'total_orders')) / $total_clients : 0;
            $newsletter_subscribers = count(array_filter($clients, fn($c) => $c['newsletter_subscribed'] === 'Oui'));
            ?>
            <div class="col-md-3">
                <div class="stat-card stat-card-info">
                    <div class="text-center">
                        <h4><?php echo $total_clients; ?></h4>
                        <p class="mb-0">Total clients</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-success">
                    <div class="text-center">
                        <h4><?php echo number_format($total_spent, 0); ?> €</h4>
                        <p class="mb-0">CA total clients</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-warning">
                    <div class="text-center">
                        <h4><?php echo number_format($avg_orders, 1); ?></h4>
                        <p class="mb-0">Commandes/client</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-center">
                        <h4><?php echo $newsletter_subscribers; ?></h4>
                        <p class="mb-0">Abonnés newsletter</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Liste des clients (<?php echo count($clients); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($clients)): ?>
                    <p class="text-muted text-center">Aucun client enregistré</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Points</th>
                                    <th>Commandes</th>
                                    <th>Dépenses</th>
                                    <th>Newsletter</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($client['firstname'] . ' ' . $client['lastname']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php 
                                                $flags = ['fr' => '🇫🇷', 'en' => '🇬🇧', 'es' => '🇪🇸'];
                                                echo $flags[$client['language']] ?? '🏳️'; 
                                                ?> <?php echo strtoupper($client['language']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($client['email']); ?></td>
                                        <td><?php echo htmlspecialchars($client['phone'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo $client['loyalty_points']; ?> pts
                                            </span>
                                        </td>
                                        <td><?php echo $client['total_orders']; ?></td>
                                        <td><?php echo number_format($client['total_spent'], 2); ?> €</td>
                                        <td>
                                            <span class="badge <?php echo $client['newsletter_subscribed'] === 'Oui' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $client['newsletter_subscribed']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $client['id']; ?>"
                                                class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?php echo $client['id']; ?>"
                                                class="btn btn-sm btn-danger btn-delete"
                                                data-name="<?php echo htmlspecialchars($client['firstname'] . ' ' . $client['lastname']); ?>">
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