<?php
$pageTitle = 'Gestion des Événements';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$success = $error = '';

if ($_POST) {
    switch ($action) {
        case 'add':
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $event_date = $_POST['event_date'] ?? '';
            $event_time = $_POST['event_time'] ?? '';
            $location = $_POST['location'] ?? '';
            $max_participants = $_POST['max_participants'] ?? 50;
            $price = $_POST['price'] ?? 0;
            
            if ($title && $event_date && $location && $max_participants > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO events (title, description, event_date, event_time, location, max_participants, price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$title, $description, $event_date, $event_time, $location, $max_participants, $price])) {
                    $_SESSION['success'] = 'Événement créé avec succès';
                    header('Location: events.php');
                    exit();
                } else {
                    $error = 'Erreur lors de la création';
                }
            } else {
                $error = 'Tous les champs obligatoires doivent être remplis';
            }
            break;

        case 'edit':
            $id = $_POST['id'] ?? 0;
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $event_date = $_POST['event_date'] ?? '';
            $event_time = $_POST['event_time'] ?? '';
            $location = $_POST['location'] ?? '';
            $max_participants = $_POST['max_participants'] ?? 50;
            $price = $_POST['price'] ?? 0;
            $status = $_POST['status'] ?? 'upcoming';
            
            if ($id && $title && $event_date && $location && $max_participants > 0) {

                $stmt = $pdo->prepare("SELECT current_participants FROM events WHERE id = ?");
                $stmt->execute([$id]);
                $current_participants = $stmt->fetchColumn();
                
                if ($max_participants >= $current_participants) {
                    $stmt = $pdo->prepare("
                        UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, 
                        location = ?, max_participants = ?, price = ?, status = ? WHERE id = ?
                    ");
                    if ($stmt->execute([$title, $description, $event_date, $event_time, $location, 
                                       $max_participants, $price, $status, $id])) {
                        $_SESSION['success'] = 'Événement modifié avec succès';
                        header('Location: events.php');
                        exit();
                    } else {
                        $error = 'Erreur lors de la modification';
                    }
                } else {
                    $error = "Le nombre maximum ne peut pas être inférieur aux participants inscrits ($current_participants)";
                }
            }
            break;
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("SELECT current_participants FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $current_participants = $stmt->fetchColumn();
    
    if ($current_participants > 0) {
        $_SESSION['error'] = 'Impossible de supprimer un événement avec des participants inscrits';
    } else {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['success'] = 'Événement supprimé avec succès';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression';
        }
    }
    header('Location: events.php');
    exit();
}

if (isset($_GET['status_change'])) {
    $id = (int)$_GET['id'];
    $new_status = $_GET['status_change'];
    
    $valid_statuses = ['upcoming', 'active', 'completed', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $id])) {
            $_SESSION['success'] = 'Statut mis à jour';
        }
    }
    header('Location: events.php');
    exit();
}

$event_to_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $event_to_edit = $stmt->fetch();
}

$events = $pdo->query("
    SELECT e.*, 
           (e.max_participants - e.current_participants) as places_remaining,
           CASE 
               WHEN e.event_date < CURDATE() THEN 'past'
               WHEN e.event_date = CURDATE() THEN 'today'
               ELSE 'future'
           END as time_status
    FROM events e
    ORDER BY e.event_date ASC, e.event_time ASC
")->fetchAll();

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_events,
        COUNT(CASE WHEN status = 'upcoming' THEN 1 END) as upcoming_events,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_events,
        SUM(current_participants) as total_participants,
        AVG(current_participants) as avg_participants
    FROM events
")->fetch();

$popular_events = $pdo->query("
    SELECT title, current_participants, max_participants,
           (current_participants / max_participants * 100) as fill_rate
    FROM events
    WHERE max_participants > 0 AND status IN ('upcoming', 'completed')
    ORDER BY fill_rate DESC, current_participants DESC
    LIMIT 5
")->fetchAll();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-calendar-alt me-2"></i>Gestion des Événements</h1>
        <?php if ($action === 'list'): ?>
            <a href="?action=add" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Nouvel événement
            </a>
        <?php else: ?>
            <a href="events.php" class="btn btn-secondary">
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
                <h5><?php echo $action === 'add' ? 'Créer un événement' : 'Modifier l\'événement'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?php if ($event_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $event_to_edit['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Titre de l'événement *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($event_to_edit['title'] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <?php if ($action === 'edit'): ?>
                                <div class="mb-3">
                                    <label for="status" class="form-label">Statut</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="upcoming" <?php echo ($event_to_edit['status'] ?? '') === 'upcoming' ? 'selected' : ''; ?>>📅 À venir</option>
                                        <option value="active" <?php echo ($event_to_edit['status'] ?? '') === 'active' ? 'selected' : ''; ?>>🔴 En cours</option>
                                        <option value="completed" <?php echo ($event_to_edit['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>✅ Terminé</option>
                                        <option value="cancelled" <?php echo ($event_to_edit['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>❌ Annulé</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="4"><?php echo htmlspecialchars($event_to_edit['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" 
                                       value="<?php echo $event_to_edit['event_date'] ?? ''; ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_time" class="form-label">Heure</label>
                                <input type="time" class="form-control" id="event_time" name="event_time" 
                                       value="<?php echo $event_to_edit['event_time'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Prix (€)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       step="0.01" min="0" value="<?php echo $event_to_edit['price'] ?? 0; ?>">
                                <div class="form-text">0 = Gratuit</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="location" class="form-label">Lieu *</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($event_to_edit['location'] ?? ''); ?>" 
                                       placeholder="Ex: Food Truck - Place de la République" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_participants" class="form-label">Participants max *</label>
                                <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                       min="1" value="<?php echo $event_to_edit['max_participants'] ?? 50; ?>" required>
                                <?php if ($event_to_edit && $event_to_edit['current_participants'] > 0): ?>
                                    <div class="form-text text-warning">
                                        ⚠️ Actuellement <?php echo $event_to_edit['current_participants']; ?> inscrits
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i><?php echo $action === 'add' ? 'Créer l\'événement' : 'Modifier'; ?>
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card stat-card-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['total_events']; ?></h3>
                            <p>Total événements</p>
                        </div>
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['upcoming_events']; ?></h3>
                            <p>À venir</p>
                        </div>
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-card-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['total_participants']; ?></h3>
                            <p>Participants total</p>
                        </div>
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo number_format($stats['avg_participants'], 1); ?></h3>
                            <p>Moyenne/événement</p>
                        </div>
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Liste des événements (<?php echo count($events); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events)): ?>
                            <p class="text-muted text-center">Aucun événement programmé</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Événement</th>
                                            <th>Date</th>
                                            <th>Participants</th>
                                            <th>Prix</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                            <tr class="<?php echo $event['time_status'] === 'past' ? 'table-light' : ''; ?>">
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?php echo htmlspecialchars($event['location']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div><?php echo date('d/m/Y', strtotime($event['event_date'])); ?></div>
                                                    <?php if ($event['event_time']): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('H:i', strtotime($event['event_time'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-info me-2">
                                                            <?php echo $event['current_participants']; ?>/<?php echo $event['max_participants']; ?>
                                                        </span>
                                                        <div class="progress" style="width: 60px; height: 8px;">
                                                            <?php 
                                                            $fill_rate = $event['max_participants'] > 0 ? 
                                                                         ($event['current_participants'] / $event['max_participants']) * 100 : 0;
                                                            ?>
                                                            <div class="progress-bar bg-info" style="width: <?php echo min($fill_rate, 100); ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <?php if ($event['places_remaining'] <= 3 && $event['places_remaining'] > 0): ?>
                                                        <small class="text-warning">Plus que <?php echo $event['places_remaining']; ?> place(s)</small>
                                                    <?php elseif ($event['places_remaining'] <= 0): ?>
                                                        <small class="text-danger">Complet</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($event['price'] > 0): ?>
                                                        <strong><?php echo number_format($event['price'], 2); ?> €</strong>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Gratuit</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm dropdown-toggle status-<?php echo $event['status']; ?>" 
                                                                data-bs-toggle="dropdown" style="min-width: 90px;">
                                                            <?php
                                                            $status_icons = [
                                                                'upcoming' => '📅',
                                                                'active' => '🔴',
                                                                'completed' => '✅',
                                                                'cancelled' => '❌'
                                                            ];
                                                            echo $status_icons[$event['status']] . ' ' . ucfirst($event['status']);
                                                            ?>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="?status_change=upcoming&id=<?php echo $event['id']; ?>">📅 À venir</a></li>
                                                            <li><a class="dropdown-item" href="?status_change=active&id=<?php echo $event['id']; ?>">🔴 En cours</a></li>
                                                            <li><a class="dropdown-item" href="?status_change=completed&id=<?php echo $event['id']; ?>">✅ Terminé</a></li>
                                                            <li><a class="dropdown-item" href="?status_change=cancelled&id=<?php echo $event['id']; ?>">❌ Annulé</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="?action=edit&id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            data-bs-toggle="modal" data-bs-target="#participantsModal<?php echo $event['id']; ?>">
                                                        <i class="fas fa-users"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo $event['id']; ?>" 
                                                       class="btn btn-sm btn-danger btn-delete"
                                                       data-name="<?php echo htmlspecialchars($event['title']); ?>">
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
                        <h5><i class="fas fa-trophy me-2"></i>Événements populaires</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($popular_events)): ?>
                            <p class="text-muted">Pas encore de données</p>
                        <?php else: ?>
                            <?php foreach ($popular_events as $index => $event): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <span class="badge bg-<?php echo $index < 3 ? ['warning', 'secondary', 'dark'][$index] : 'light text-dark'; ?> rounded-pill">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <small class="text-muted">
                                            <?php echo $event['current_participants']; ?>/<?php echo $event['max_participants']; ?> 
                                            (<?php echo number_format($event['fill_rate'], 0); ?>%)
                                        </small>
                                        <div class="progress mt-1" style="height: 4px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo min($event['fill_rate'], 100); ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb me-2"></i>Conseils</h5>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <p><i class="fas fa-calendar text-success me-2"></i>Planifiez vos événements à l'avance</p>
                            <p><i class="fas fa-users text-info me-2"></i>Limitez le nombre de participants selon le lieu</p>
                            <p><i class="fas fa-envelope text-warning me-2"></i>Les rappels sont envoyés automatiquement</p>
                            <p class="mb-0"><i class="fas fa-star text-danger me-2"></i>Les clients fidèles sont prioritaires</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($events as $event): ?>
    <?php
    $participants = $pdo->prepare("
        SELECT c.firstname, c.lastname, c.email, ep.registration_date
        FROM event_participants ep
        JOIN clients c ON ep.client_id = c.id
        WHERE ep.event_id = ?
        ORDER BY ep.registration_date ASC
    ");
    $participants->execute([$event['id']]);
    $participants = $participants->fetchAll();
    ?>
    
    <div class="modal fade" id="participantsModal<?php echo $event['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-users me-2"></i>Participants - <?php echo htmlspecialchars($event['title']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($participants)): ?>
                        <p class="text-muted text-center">Aucun participant inscrit</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Inscrit le</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $participant): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($participant['firstname'] . ' ' . $participant['lastname']); ?></td>
                                            <td><?php echo htmlspecialchars($participant['email']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($participant['registration_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>