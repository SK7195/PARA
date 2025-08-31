<?php
$pageTitle = 'Tableau de bord';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();

$stats = [
    'franchisees' => $pdo->query("SELECT COUNT(*) FROM franchisees WHERE status = 'active'")->fetchColumn(),
    'trucks' => $pdo->query("SELECT COUNT(*) FROM trucks")->fetchColumn(),
    'available_trucks' => $pdo->query("SELECT COUNT(*) FROM trucks WHERE status = 'available'")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT COALESCE(SUM(daily_revenue), 0) FROM sales WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)")->fetchColumn(),
    'clients' => $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn(),
    'active_menus' => $pdo->query("SELECT COUNT(*) FROM menus WHERE available = 1")->fetchColumn(),
    'upcoming_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming' AND event_date >= CURDATE()")->fetchColumn(),
    'total_orders' => $pdo->query("SELECT COUNT(*) FROM client_orders WHERE MONTH(order_date) = MONTH(CURRENT_DATE)")->fetchColumn()
];

$recent_client_orders = $pdo->query("
    SELECT co.id, co.total_amount, co.status, co.order_date,
           c.firstname, c.lastname, c.email,
           t.license_plate as truck_plate
    FROM client_orders co
    JOIN clients c ON co.client_id = c.id
    LEFT JOIN trucks t ON co.truck_id = t.id
    ORDER BY co.order_date DESC
    LIMIT 5
")->fetchAll();

$upcoming_events = $pdo->query("
    SELECT e.*, 
           (e.max_participants - e.current_participants) as places_remaining
    FROM events e
    WHERE e.status = 'upcoming' AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
    LIMIT 3
")->fetchAll();

$recent_franchisees = $pdo->query("
    SELECT f.*, u.email 
    FROM franchisees f 
    JOIN users u ON f.user_id = u.id 
    ORDER BY f.created_at DESC 
    LIMIT 5
")->fetchAll();

$newsletter_stats = $pdo->query("
    SELECT 
        COUNT(DISTINCT ns.client_id) as subscribers,
        COUNT(CASE WHEN ns.subscribed = 1 THEN 1 END) as active_subscribers
    FROM newsletter_subscribers ns
    JOIN clients c ON ns.client_id = c.id
")->fetch();

$monthly_commissions = $pdo->query("
    SELECT COALESCE(SUM(commission_due), 0) as total_commission
    FROM sales 
    WHERE MONTH(sale_date) = MONTH(CURRENT_DATE)
")->fetchColumn();

$popular_menus = $pdo->query("
    SELECT m.name_fr, COUNT(coi.id) as order_count
    FROM menus m
    LEFT JOIN client_order_items coi ON m.id = coi.menu_id
    LEFT JOIN client_orders co ON coi.order_id = co.id AND co.status IN ('completed', 'ready')
    GROUP BY m.id
    ORDER BY order_count DESC
    LIMIT 5
")->fetchAll();

$alerts = [];

$broken_trucks = $pdo->query("SELECT COUNT(*) FROM trucks WHERE status = 'broken'")->fetchColumn();
if ($broken_trucks > 0) {
    $alerts[] = [
        'type' => 'danger',
        'icon' => 'exclamation-triangle',
        'message' => "$broken_trucks camion(s) en panne"
    ];
}

$low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < 10")->fetchColumn();
if ($low_stock > 0) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'boxes',
        'message' => "$low_stock produit(s) en stock faible"
    ];
}

$pending_orders = $pdo->query("SELECT COUNT(*) FROM client_orders WHERE status = 'pending'")->fetchColumn();
if ($pending_orders > 5) {
    $alerts[] = [
        'type' => 'info',
        'icon' => 'clock',
        'message' => "$pending_orders commandes en attente"
    ];
}
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="fas fa-tachometer-alt me-2"></i>Tableau de bord</h1>
            <p class="text-muted mb-0">Vue d'ensemble de votre système Driv'n Cook</p>
        </div>
        <div class="text-muted">
            <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card stat-card-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['franchisees']; ?></h3>
                        <p>Franchisés actifs</p>
                    </div>
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="stat-card stat-card-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['available_trucks']; ?>/<?php echo $stats['trucks']; ?></h3>
                        <p>Camions disponibles</p>
                    </div>
                    <i class="fas fa-truck"></i>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="stat-card stat-card-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> €</h3>
                        <p>CA du mois</p>
                    </div>
                    <i class="fas fa-euro-sign"></i>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['clients']; ?></h3>
                        <p>Clients inscrits</p>
                    </div>
                    <i class="fas fa-user-friends"></i>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="stat-card stat-card-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['total_orders']; ?></h3>
                        <p>Commandes/mois</p>
                    </div>
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="stat-card stat-card-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo $stats['upcoming_events']; ?></h3>
                        <p>Événements prévus</p>
                    </div>
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($alerts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Alertes système</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($alerts as $alert): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="alert alert-<?php echo $alert['type']; ?> py-2 mb-0">
                                        <i class="fas fa-<?php echo $alert['icon']; ?> me-2"></i>
                                        <?php echo $alert['message']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-shopping-cart me-2"></i>Commandes clients récentes</h5>
                    <a href="client_orders.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_client_orders)): ?>
                        <p class="text-muted text-center">Aucune commande récente</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Commande</th>
                                        <th>Client</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_client_orders as $order): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                                            <td><?php echo number_format($order['total_amount'], 2); ?> €</td>
                                            <td>
                                                <span class="badge status-<?php echo $order['status']; ?>">
                                                    <?php 
                                                    $status_labels = [
                                                        'pending' => 'En attente',
                                                        'confirmed' => 'Confirmée',
                                                        'ready' => 'Prête',
                                                        'completed' => 'Terminée',
                                                        'cancelled' => 'Annulée'
                                                    ];
                                                    echo $status_labels[$order['status']] ?? $order['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m H:i', strtotime($order['order_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-users me-2"></i>Franchisés récents</h5>
                    <a href="franchises.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_franchisees)): ?>
                        <p class="text-muted text-center">Aucun franchisé enregistré</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Entreprise</th>
                                        <th>Email</th>
                                        <th>Statut</th>
                                        <th>Inscription</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_franchisees as $franchisee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($franchisee['name']); ?></td>
                                            <td><?php echo htmlspecialchars($franchisee['company_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($franchisee['email']); ?></td>
                                            <td>
                                                <span class="badge status-<?php echo $franchisee['status']; ?>">
                                                    <?php echo ucfirst($franchisee['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($franchisee['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">

            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Actions rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="client_orders.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Gérer les commandes
                        </a>
                        <a href="events.php?action=add" class="btn btn-success">
                            <i class="fas fa-calendar-plus me-2"></i>Créer un événement
                        </a>
                        <a href="menus.php?action=add" class="btn btn-info">
                            <i class="fas fa-utensils me-2"></i>Ajouter un plat
                        </a>
                        <a href="newsletter.php" class="btn btn-warning">
                            <i class="fas fa-envelope me-2"></i>Newsletter
                        </a>
                        <a href="clients.php?action=add" class="btn btn-outline-primary">
                            <i class="fas fa-user-plus me-2"></i>Nouveau client
                        </a>
                        <a href="franchises.php?action=add" class="btn btn-outline-success">
                            <i class="fas fa-handshake me-2"></i>Nouveau franchisé
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-calendar-alt me-2"></i>Événements à venir</h5>
                    <a href="events.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_events)): ?>
                        <p class="text-muted text-center small">Aucun événement prévu</p>
                    <?php else: ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($event['event_date'])); ?>
                                            <?php if ($event['event_time']): ?>
                                                à <?php echo date('H:i', strtotime($event['event_time'])); ?>
                                            <?php endif; ?>
                                        </small>
                                        <br><small class="text-muted">
                                            <i class="fas fa-users me-1"></i><?php echo $event['current_participants']; ?>/<?php echo $event['max_participants']; ?> participants
                                        </small>
                                    </div>
                                    <?php if ($event['places_remaining'] <= 5 && $event['places_remaining'] > 0): ?>
                                        <span class="badge bg-warning text-dark">Bientôt complet</span>
                                    <?php elseif ($event['places_remaining'] <= 0): ?>
                                        <span class="badge bg-danger">Complet</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-envelope me-2"></i>Newsletter</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $newsletter_stats['active_subscribers'] ?? 0; ?></h4>
                            <small class="text-muted">Abonnés actifs</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo number_format($monthly_commissions, 0); ?> €</h4>
                            <small class="text-muted">Commissions</small>
                        </div>
                    </div>
                    <hr>
                    <div class="d-grid">
                        <a href="newsletter.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-paper-plane me-2"></i>Envoyer newsletter
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-trophy me-2"></i>Top menus</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($popular_menus)): ?>
                        <p class="text-muted text-center small">Pas encore de données</p>
                    <?php else: ?>
                        <?php foreach ($popular_menus as $index => $menu): ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-<?php echo $index < 3 ? ['warning', 'secondary', 'dark'][$index] : 'light text-dark'; ?> rounded-pill me-2">
                                    <?php echo $index + 1; ?>
                                </span>
                                <div class="flex-grow-1">
                                    <div class="fw-bold small"><?php echo htmlspecialchars($menu['name_fr']); ?></div>
                                    <small class="text-muted"><?php echo $menu['order_count']; ?> commandes</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <hr>
                    <div class="d-grid">
                        <a href="menus.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-utensils me-2"></i>Gérer les menus
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>