<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['preparateur', 'admin']);
$pdo     = getPDO();
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCommande = (int)($_POST['idcommande'] ?? 0);
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM commande WHERE idcommande = :id FOR UPDATE');
        $stmt->execute([':id' => $idCommande]);
        $cmd = $stmt->fetch();

        if (!$cmd) {
            throw new Exception('Commande inconnue.');
        }
        if ($cmd['statut'] !== 'Saisie') {
            // BUG CORRIGÉ : apostrophe échappée
            throw new Exception('Cette commande n\'est plus au statut Saisie.');
        }

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(numerocolis), 0) + 1 FROM colis WHERE idcommande = :id');
        $stmt->execute([':id' => $idCommande]);
        $numero = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('INSERT INTO colis(idcommande, tournee, numerocolis) VALUES (:id, 1, :num) RETURNING idcolis');
        $stmt->execute([':id' => $idCommande, ':num' => $numero]);
        $idColis = (int)$stmt->fetchColumn();

        $lignes = $pdo->prepare('SELECT * FROM ligne_commande WHERE idcommande = :id');
        $lignes->execute([':id' => $idCommande]);

        foreach ($lignes->fetchAll() as $l) {
            $poids = $l['quantitelivree'] ?? $l['quantitedemandee'];

            $stmt = $pdo->prepare('
                INSERT INTO contenu_colis(idcolis, codevariete, poidsreel)
                VALUES (:colis, :cv, :poids)
                ON CONFLICT (idcolis, codevariete) DO NOTHING
            ');
            $stmt->execute([':colis' => $idColis, ':cv' => $l['codevariete'], ':poids' => $poids]);

            $stmt = $pdo->prepare('
                UPDATE ligne_commande
                SET quantitelivree = quantitedemandee
                WHERE idcommande = :id AND codevariete = :cv
            ');
            $stmt->execute([':id' => $idCommande, ':cv' => $l['codevariete']]);
        }

        $stmt = $pdo->prepare("UPDATE commande SET statut = 'Preparee' WHERE idcommande = :id");
        $stmt->execute([':id' => $idCommande]);
        $pdo->commit();
        $message = 'Commande #' . $idCommande . ' préparée avec succès — colis #' . $idColis . ' créé.';

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erreur = $e->getMessage();
    }
}

$commandes = $pdo->query("
    SELECT c.idcommande, c.datecommande, c.statut, c.heuresaisie,
           cl.raisonsociale, cl.codeclient,
           COUNT(l.codevariete) AS nb_lignes
    FROM commande c
    JOIN client cl ON cl.codeclient = c.codeclient
    LEFT JOIN ligne_commande l ON l.idcommande = c.idcommande
    WHERE c.statut = 'Saisie'
    GROUP BY c.idcommande, c.datecommande, c.statut, c.heuresaisie, cl.raisonsociale, cl.codeclient
    ORDER BY c.datecommande, c.idcommande
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <h1>📦 Commandes à préparer</h1>
        <p><?= count($commandes) ?> commande<?= count($commandes) > 1 ? 's' : '' ?> en attente de préparation</p>
    </div>
    <a href="<?= h(url('/pages/dashboard.php')) ?>" class="btn btn-secondary btn-sm">← Dashboard</a>
</div>

<?php if ($message): echo msgSucces($message); endif; ?>
<?php if ($erreur):  echo msgErreur($erreur);  endif; ?>

<?php if (empty($commandes)): ?>
    <div class="empty-state">
        <div class="empty-icon">✅</div>
        <p>Aucune commande en attente de préparation.</p>
    </div>
<?php else: ?>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Heure saisie</th>
                <th>Client</th>
                <th>Lignes</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($commandes as $c): ?>
            <tr>
                <td><strong>#<?= (int)$c['idcommande'] ?></strong></td>
                <td><?= h($c['datecommande']) ?></td>
                <td style="color:var(--gris-doux);font-size:0.85rem;"><?= h(substr($c['heuresaisie'], 11, 5)) ?></td>
                <td>
                    <strong><?= h($c['raisonsociale']) ?></strong><br>
                    <small class="text-muted"><?= h($c['codeclient']) ?></small>
                </td>
                <td><span style="font-weight:700;color:var(--vert-fonce);"><?= (int)$c['nb_lignes'] ?></span> ligne<?= (int)$c['nb_lignes'] > 1 ? 's' : '' ?></td>
                <td><span class="badge badge-saisie">Saisie</span></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Préparer la commande #<?= (int)$c['idcommande'] ?> ?')">
                        <input type="hidden" name="idcommande" value="<?= (int)$c['idcommande'] ?>">
                        <button class="btn btn-primary btn-sm">✅ Préparer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
