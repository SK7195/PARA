<?php
$pageTitle = 'Commandes Clients';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();
$error = $success = '';

if ($_POST && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'] ?? 0;
    $new_status = $_POST['status'] ?? '';
    
    $valid_statuses = ['pending', 'confirmed', 'ready', 'completed', 'cancelled'];
    
    if ($order_id && in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE client_orders SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $order_id])) {
            $_SESSION['success'] = 'Statut de la commande mis à jour';
        } else {
            $_SESSION['error'] = 'Erreur lors de la mise à jour';
        }
    }
    header('Location: client_orders.php');
    exit();
}

$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "co.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(co.order_date) = ?";
    $params[] = $date_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$orders = $pdo->prepare("
    SELECT co.*, 
           c.firstname, c.lastname, c.email, c.phone,
           t.license_plate as truck_plate,
           COUNT(coi.id) as items_count,
           GROUP_CONCAT(CONCAT(m.name_fr, ' (', coi.quantity, ')') SEPARATOR ', ') as items_summary
    FROM client_orders co
    JOIN clients c ON co.client_id = c.id
    LEFT JOIN trucks t ON co.truck_id = t.id
    LEFT JOIN client_order_items coi ON co.id = coi.order_id
    LEFT JOIN menus m ON coi.menu_id = m.id
    {$where_clause}
    GROUP BY co.id
    ORDER BY co.order_date DESC
    LIMIT 50
");
$orders->execute($params);
$orders = $orders->fetchAll();

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        COALESCE(SUM(CASE WHEN status IN ('confirmed', 'completed') THEN total_amount END), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_order_value
    FROM client_orders
    WHERE order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
")->fetch();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-shopping-cart me-2"></i>Commandes Clients</h1>
        <div class="d-flex gap-2">

            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmée</option>
                    <option value="ready" <?php echo $status_filter === 'ready' ? 'selected' : ''; ?>>Prête</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                </select>
                <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                <?php if ($status_filter || $date_filter): ?>
                    <a href="client_orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card stat-card-info">
                <div class="text-center">
                    <h4><?php echo $stats['total_orders']; ?></h4>
                    <p class="mb-0">Total (30j)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-warning">
                <div class="text-center">
                    <h4><?php echo $stats['pending_orders']; ?></h4>
                    <p class="mb-0">En attente</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-info">
                <div class="text-center">
                    <h4><?php echo $stats['confirmed_orders']; ?></h4>
                    <p class="mb-0">Confirmées</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-success">
                <div class="text-center">
                    <h4><?php echo $stats['completed_orders']; ?></h4>
                    <p class="mb-0">Terminées</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card stat-card-success">
                <div class="text-center">
                    <h4><?php echo number_format($stats['total_revenue'], 0); ?> €</h4>
                    <p class="mb-0">CA (30j)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="text-center">
                    <h4><?php echo number_format($stats['avg_order_value'], 0); ?> €</h4>
                    <p class="mb-0">Panier moyen</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>
                Liste des commandes 
                <?php if ($status_filter || $date_filter): ?>
                    <span class="badge bg-primary"><?php echo count($orders); ?> résultats</span>
                <?php else: ?>
                    (<?php echo count($orders); ?> dernières)
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <p class="text-muted text-center">Aucune commande trouvée</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Commande</th>
                                <th>Client</th>
                                <th>Articles</th>
                                <th>Montant</th>
                                <th>Retrait</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $order['id']; ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($order['email']); ?>
                                        </small>
                                        <?php if ($order['phone']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($order['phone']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $order['items_count']; ?> article(s)</span>
                                        <?php if ($order['items_summary']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($order['items_summary']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($order['total_amount'], 2); ?> €</strong>
                                        <?php if ($order['loyalty_points_used'] > 0): ?>
                                            <br><small class="text-warning">
                                                <i class="fas fa-star me-1"></i>-<?php echo $order['loyalty_points_used']; ?> pts
                                            </small>
                                        <?php endif; ?>
                                        <br><small class="text-muted">
                                            <?php 
                                            $payment_methods = [
                                                'card' => 'Carte',
                                                'cash' => 'Espèces', 
                                                'loyalty_points' => 'Points'
                                            ];
                                            echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($order['pickup_time']): ?>
                                            <strong><?php echo date('d/m H:i', strtotime($order['pickup_time'])); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                        <?php if ($order['pickup_location']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars(substr($order['pickup_location'], 0, 30)); ?>
                                                <?php if (strlen($order['pickup_location']) > 30) echo '...'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
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
                                    <td>
                                        
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-success" 
                                                        title="Confirmer">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($order['status'] === 'confirmed'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="ready">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-info" 
                                                        title="Marquer prête">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($order['status'] === 'ready'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-primary" 
                                                        title="Marquer terminée">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                     
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
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

<?php foreach ($orders as $order): ?>
    <?php

    $stmt = $pdo->prepare("
        SELECT coi.*, m.name_fr as menu_name, m.price as menu_price
        FROM client_order_items coi
        JOIN menus m ON coi.menu_id = m.id
        WHERE coi.order_id = ?
        ORDER BY m.name_fr
    ");
    $stmt->execute([$order['id']]);
    $order_items = $stmt->fetchAll();
    ?>
    
    <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2"></i>Commande #<?php echo $order['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user me-2"></i>Informations client</h6>
                            <p>
                                <strong><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></strong><br>
                                <?php echo htmlspecialchars($order['email']); ?><br>
                                <?php if ($order['phone']): ?>
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($order['phone']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-info-circle me-2"></i>Détails commande</h6>
                            <p>
                                <strong>Date :</strong> <?php echo date('d/m/Y à H:i', strtotime($order['order_date'])); ?><br>
                                <strong>Statut :</strong> 
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
                                </span><br>
                                <?php if ($order['pickup_time']): ?>
                                    <strong>Retrait :</strong> <?php echo date('d/m/Y à H:i', strtotime($order['pickup_time'])); ?><br>
                                <?php endif; ?>
                                <?php if ($order['pickup_location']): ?>
                                    <strong>Lieu :</strong> <?php echo htmlspecialchars($order['pickup_location']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <h6><i class="fas fa-utensils me-2"></i>Articles commandés</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Article</th>
                                    <th>Prix unitaire</th>
                                    <th>Quantité</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['menu_name']); ?></td>
                                        <td><?php echo number_format($item['unit_price'], 2); ?> €</td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?> €</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <td colspan="3"><strong>Total</strong></td>
                                    <td><strong><?php echo number_format($order['total_amount'], 2); ?> €</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Changer le statut</h6>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <select name="status" class="form-select">
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmée</option>
                                <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Prête</option>
                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Mettre à jour
                            </button>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>