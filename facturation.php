<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['gestionnaire', 'admin']);
$pdo     = getPDO();
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCommande        = (int)($_POST['idcommande']        ?? 0);
    $commentaire       = trim($_POST['commentaire']        ?? 'OK');
    $typeAnomalie      = trim($_POST['typeanomalie']       ?? '');
    $commentaireAnom   = trim($_POST['commentaireanomalie'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM commande WHERE idcommande = :id FOR UPDATE');
        $stmt->execute([':id' => $idCommande]);
        $cmd = $stmt->fetch();

        if (!$cmd) throw new Exception('Commande inconnue.');
        if (!in_array($cmd['statut'], ['Preparee', 'Livree'], true)) {
            throw new Exception('La commande doit être préparée ou livrée pour être facturée.');
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM facture WHERE idcommande = :id');
        $stmt->execute([':id' => $idCommande]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new Exception('Cette commande possède déjà une facture.');
        }

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(COALESCE(quantitelivree, quantitedemandee) * prixunitaire), 0) FROM ligne_commande WHERE idcommande = :id');
        $stmt->execute([':id' => $idCommande]);
        $montant = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO facture(datefacture, montanttotal, type, commentaire, idcommande)
            VALUES (CURRENT_DATE, :m, 'Facture', :com, :id)
            RETURNING idfacture
        ");
        $stmt->execute([':m' => $montant, ':com' => $commentaire ?: 'OK', ':id' => $idCommande]);
        $idFacture = (int)$stmt->fetchColumn();

        if ($typeAnomalie !== '') {
            $stmt = $pdo->prepare('INSERT INTO anomalie(typeanomalie, commentaire, idfacture) VALUES (:t, :c, :f)');
            $stmt->execute([':t' => $typeAnomalie, ':c' => $commentaireAnom ?: null, ':f' => $idFacture]);
        }

        $pdo->prepare("UPDATE commande SET statut = 'Livree' WHERE idcommande = :id")->execute([':id' => $idCommande]);
        $pdo->commit();
        $message = 'Facture #' . $idFacture . ' créée pour ' . formatEuro($montant) . '.';

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erreur = $e->getMessage();
    }
}

$commandes = $pdo->query("
    SELECT c.idcommande, c.datecommande, c.statut, cl.raisonsociale,
           COALESCE(SUM(COALESCE(l.quantitelivree, l.quantitedemandee) * l.prixunitaire), 0) AS total
    FROM commande c
    JOIN client cl ON cl.codeclient = c.codeclient
    LEFT JOIN ligne_commande l ON l.idcommande = c.idcommande
    LEFT JOIN facture f ON f.idcommande = c.idcommande
    WHERE c.statut IN ('Preparee','Livree') AND f.idfacture IS NULL
    GROUP BY c.idcommande, c.datecommande, c.statut, cl.raisonsociale
    ORDER BY c.datecommande
")->fetchAll();

$factures = $pdo->query("
    SELECT f.*, cl.raisonsociale
    FROM facture f
    JOIN commande c ON c.idcommande = f.idcommande
    JOIN client cl ON cl.codeclient = c.codeclient
    ORDER BY f.idfacture DESC
")->fetchAll();

$anomalies = $pdo->query("
    SELECT a.*, f.idcommande
    FROM anomalie a
    JOIN facture f ON f.idfacture = a.idfacture
    ORDER BY a.idanomalie DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <h1>🧾 Facturation & Anomalies</h1>
        <p><?= count($commandes) ?> commande<?= count($commandes) > 1 ? 's' : '' ?> à facturer · <?= count($anomalies) ?> anomalie<?= count($anomalies) > 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= h(url('/pages/dashboard.php')) ?>" class="btn btn-secondary btn-sm">← Dashboard</a>
</div>

<?php if ($message): echo msgSucces($message); endif; ?>
<?php if ($erreur):  echo msgErreur($erreur);  endif; ?>

<h2>Commandes à facturer</h2>

<?php if (empty($commandes)): ?>
    <div class="empty-state">
        <div class="empty-icon">✅</div>
        <p>Toutes les commandes ont été facturées.</p>
    </div>
<?php else: ?>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Client</th>
                <th>Statut</th>
                <th>Total estimé</th>
                <th>Commentaire</th>
                <th>Anomalie (optionnel)</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($commandes as $c): ?>
            <tr>
                <td><strong>#<?= (int)$c['idcommande'] ?></strong></td>
                <td><?= h($c['datecommande']) ?></td>
                <td><?= h($c['raisonsociale']) ?></td>
                <td><span class="badge <?= classeBadgeStatut($c['statut']) ?>"><?= h(libelleStatut($c['statut'])) ?></span></td>
                <td><strong><?= formatEuro($c['total']) ?></strong></td>
                <td colspan="3">
                    <form method="POST" class="form-inline" style="flex-wrap:wrap;gap:6px;">
                        <input type="hidden" name="idcommande" value="<?= (int)$c['idcommande'] ?>">
                        <input type="text" name="commentaire" placeholder="Commentaire" style="width:130px;">
                        <input type="text" name="typeanomalie" placeholder="Type anomalie" style="width:140px;">
                        <input type="text" name="commentaireanomalie" placeholder="Détail anomalie" style="width:150px;">
                        <button class="btn btn-primary btn-sm">🧾 Facturer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h2>Factures existantes</h2>
<div class="table-container">
    <table>
        <thead>
            <tr><th>#</th><th>Date</th><th>Client</th><th>Montant</th><th>Commentaire</th><th>Commande</th></tr>
        </thead>
        <tbody>
            <?php if (empty($factures)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:20px;">Aucune facture.</td></tr>
            <?php else: ?>
                <?php foreach ($factures as $f): ?>
                <tr>
                    <td><strong>#<?= (int)$f['idfacture'] ?></strong></td>
                    <td><?= h($f['datefacture']) ?></td>
                    <td><?= h($f['raisonsociale']) ?></td>
                    <td><?= formatEuro($f['montanttotal']) ?></td>
                    <td><?= h($f['commentaire']) ?></td>
                    <td>#<?= (int)$f['idcommande'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h2>Anomalies enregistrées</h2>
<div class="table-container">
    <table>
        <thead>
            <tr><th>#</th><th>Facture</th><th>Commande</th><th>Type</th><th>Commentaire</th></tr>
        </thead>
        <tbody>
            <?php if (empty($anomalies)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:20px;">Aucune anomalie.</td></tr>
            <?php else: ?>
                <?php foreach ($anomalies as $a): ?>
                <tr>
                    <td>#<?= (int)$a['idanomalie'] ?></td>
                    <td>#<?= (int)$a['idfacture'] ?></td>
                    <td>#<?= (int)$a['idcommande'] ?></td>
                    <td><span class="badge" style="background:#fef3c7;color:#92400e;"><?= h($a['typeanomalie']) ?></span></td>
                    <td><?= h($a['commentaire'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
