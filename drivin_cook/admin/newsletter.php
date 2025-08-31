<?php
$pageTitle = 'Gestion Newsletter';
require_once '../includes/header.php';
requireAdmin();

$pdo = getDBConnection();
$success = $error = '';

if ($_POST && isset($_POST['send_newsletter'])) {
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if ($subject && $content) {
        try {

            $stmt = $pdo->query("
                SELECT c.id, c.email, c.firstname, c.lastname, c.language
                FROM clients c
                JOIN newsletter_subscribers ns ON c.id = ns.client_id
                WHERE ns.subscribed = 1
            ");
            $subscribers = $stmt->fetchAll();
            
            if (empty($subscribers)) {
                $error = 'Aucun abonné trouvé';
            } else {
                $sent_count = 0;
                
                foreach ($subscribers as $subscriber) {

                    $personalized_content = str_replace(
                        ['{{firstname}}', '{{lastname}}'],
                        [$subscriber['firstname'], $subscriber['lastname']],
                        $content
                    );
                    
                    if (sendNewsletter($subscriber['email'], $subject, $personalized_content, $subscriber['language'])) {
                        $sent_count++;
                    }
                }
                
                $success = "Newsletter envoyée avec succès à {$sent_count} abonné(s)";
            }
        } catch (Exception $e) {
            $error = 'Erreur lors de l\'envoi : ' . $e->getMessage();
        }
    } else {
        $error = 'Sujet et contenu obligatoires';
    }
}

function sendNewsletter($email, $subject, $content, $language) {
    return true; 
}

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_subscribers,
        COUNT(CASE WHEN ns.subscribed = 1 THEN 1 END) as active_subscribers,
        COUNT(CASE WHEN c.language = 'fr' THEN 1 END) as fr_subscribers,
        COUNT(CASE WHEN c.language = 'en' THEN 1 END) as en_subscribers,
        COUNT(CASE WHEN c.language = 'es' THEN 1 END) as es_subscribers
    FROM clients c
    LEFT JOIN newsletter_subscribers ns ON c.id = ns.client_id
")->fetch();

$subscribers = $pdo->query("
    SELECT c.id, c.email, c.firstname, c.lastname, c.language, 
           ns.subscribed, ns.subscribed_at
    FROM clients c
    LEFT JOIN newsletter_subscribers ns ON c.id = ns.client_id
    ORDER BY ns.subscribed DESC, c.created_at DESC
")->fetchAll();

$templates = [
    'monthly' => [
        'subject' => 'Newsletter mensuelle Driv\'n Cook',
        'content' => 'Bonjour {{firstname}},

Découvrez les nouveautés du mois chez Driv\'n Cook !

🍔 NOUVEAU : Burger du Chef
Notre chef a créé spécialement pour vous un nouveau burger avec des ingrédients de saison.

📍 NOUVEAUX EMPLACEMENTS
Retrouvez nos food trucks dans 3 nouveaux quartiers de Paris.

🎉 ÉVÉNEMENTS À VENIR
- Dégustation gratuite le 15 décembre
- Atelier cuisine le 22 décembre

💰 OFFRE SPÉCIALE
Utilisez le code NEWSLETTER10 pour 10% de réduction sur votre prochaine commande.

Merci de votre fidélité !
L\'équipe Driv\'n Cook'
    ],
    'promotion' => [
        'subject' => '🎉 Offre spéciale Driv\'n Cook',
        'content' => 'Bonjour {{firstname}},

Profitez de notre offre exceptionnelle !

🎯 -20% SUR TOUS NOS BURGERS
Jusqu\'au 31 décembre, bénéficiez de 20% de réduction sur tous nos burgers.

Code promo : BURGER20

Rendez-vous dans vos food trucks préférés !
L\'équipe Driv\'n Cook'
    ],
    'event' => [
        'subject' => 'Nouvel événement Driv\'n Cook',
        'content' => 'Bonjour {{firstname}},

Nous avons le plaisir de vous inviter à notre prochain événement :

🎪 SOIRÉE FOOD TRUCK
Samedi 30 décembre à 19h30
Esplanade de Vincennes

Au programme :
- Dégustation de nos spécialités
- Musique live
- Ambiance conviviale
- Entrée gratuite !

Réservez votre place sur notre site web.

À bientôt !
L\'équipe Driv\'n Cook'
    ]
];
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-envelope me-2"></i>Gestion Newsletter</h1>
        <div class="d-flex gap-2">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#sendModal">
                <i class="fas fa-paper-plane me-2"></i>Nouvelle newsletter
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card stat-card-info">
                <div class="text-center">
                    <h4><?php echo $stats['total_subscribers']; ?></h4>
                    <p class="mb-0">Total clients</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card stat-card-success">
                <div class="text-center">
                    <h4><?php echo $stats['active_subscribers']; ?></h4>
                    <p class="mb-0">Abonnés actifs</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="text-center">
                    <h5>🇫🇷 <?php echo $stats['fr_subscribers']; ?></h5>
                    <p class="mb-0">Français</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="text-center">
                    <h5>🇬🇧 <?php echo $stats['en_subscribers']; ?></h5>
                    <p class="mb-0">Anglais</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <div class="text-center">
                    <h5>🇪🇸 <?php echo $stats['es_subscribers']; ?></h5>
                    <p class="mb-0">Espagnol</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-users me-2"></i>Abonnés (<?php echo count($subscribers); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($subscribers)): ?>
                <p class="text-muted text-center">Aucun client enregistré</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Langue</th>
                                <th>Newsletter</th>
                                <th>Inscrit le</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $subscriber): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($subscriber['firstname'] . ' ' . $subscriber['lastname']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                    <td>
                                        <?php
                                        $flags = ['fr' => '🇫🇷', 'en' => '🇬🇧', 'es' => '🇪🇸'];
                                        echo $flags[$subscriber['language']] ?? $subscriber['language'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($subscriber['subscribed']): ?>
                                            <span class="badge bg-success">Abonné</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non abonné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($subscriber['subscribed_at']): ?>
                                            <?php echo date('d/m/Y', strtotime($subscriber['subscribed_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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

<div class="modal fade" id="sendModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Envoyer une newsletter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Template prédéfini (optionnel)</label>
                        <select class="form-select" id="templateSelect" onchange="loadTemplate()">
                            <option value="">Choisir un template...</option>
                            <option value="monthly">Newsletter mensuelle</option>
                            <option value="promotion">Promotion spéciale</option>
                            <option value="event">Nouvel événement</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label">Sujet *</label>
                        <input type="text" class="form-control" id="subject" name="subject" required
                               placeholder="Sujet de la newsletter">
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">Contenu *</label>
                        <textarea class="form-control" id="content" name="content" rows="12" required
                                  placeholder="Votre message..."></textarea>
                        <div class="form-text">
                            Variables disponibles : {{firstname}}, {{lastname}}
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        La newsletter sera envoyée à <strong><?php echo $stats['active_subscribers']; ?> abonné(s)</strong> actifs.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="send_newsletter" class="btn btn-success">
                        <i class="fas fa-paper-plane me-2"></i>Envoyer la newsletter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const templates = <?php echo json_encode($templates); ?>;

function loadTemplate() {
    const select = document.getElementById('templateSelect');
    const template = templates[select.value];
    
    if (template) {
        document.getElementById('subject').value = template.subject;
        document.getElementById('content').value = template.content;
    }
}

document.querySelector('form').addEventListener('submit', function(e) {
    const subscribers = <?php echo $stats['active_subscribers']; ?>;
    if (!confirm(`Confirmer l'envoi de la newsletter à ${subscribers} abonné(s) ?`)) {
        e.preventDefault();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>