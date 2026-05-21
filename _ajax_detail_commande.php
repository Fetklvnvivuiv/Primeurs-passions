<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['client']);
$pdo        = getPDO();
$codeClient = $_SESSION['codeclient'];
$id         = (int)($_GET['id'] ?? 0);

if (!$id || !verifierClientProprietaire($pdo, $id, $codeClient)) {
    echo '<p style="color:#dc2626;padding:12px;">Accès refusé.</p>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.idcommande, c.datecommande, c.statut, c.heuresaisie,
           ml.libellemodeliv, mp.libellepaiement,
           COALESCE(SUM(l.quantitedemandee * l.prixunitaire), 0) total
    FROM commande c
    JOIN mode_livraison ml ON ml.idmodeliv  = c.idmodeliv
    JOIN mode_paiement  mp ON mp.idpaiement = c.idpaiement
    LEFT JOIN ligne_commande l ON l.idcommande = c.idcommande
    WHERE c.idcommande = :id AND c.codeclient = :c
    GROUP BY c.idcommande, c.datecommande, c.statut, c.heuresaisie, ml.libellemodeliv, mp.libellepaiement
");
$stmt->execute([':id' => $id, ':c' => $codeClient]);
$cmd = $stmt->fetch();

$stmt2 = $pdo->prepare("
    SELECT l.*, v.nomvariete, v.calibre, a.libellearticle
    FROM ligne_commande l
    JOIN variete v ON v.codevariete = l.codevariete
    JOIN article a ON a.idarticle   = v.idarticle
    WHERE l.idcommande = :id
    ORDER BY a.libellearticle, v.nomvariete
");
$stmt2->execute([':id' => $id]);
$lignes = $stmt2->fetchAll();

if (!$cmd) { echo '<p style="padding:12px;color:#dc2626;">Commande introuvable.</p>'; exit; }
?>
<div class="detail-grid">
    <div class="detail-info">
        <div class="detail-info-lbl">Mode de paiement</div>
        <div class="detail-info-val"><?= h($cmd['libellepaiement']) ?></div>
    </div>
    <div class="detail-info">
        <div class="detail-info-lbl">Mode de livraison</div>
        <div class="detail-info-val"><?= h($cmd['libellemodeliv']) ?></div>
    </div>
    <div class="detail-info">
        <div class="detail-info-lbl">Heure de saisie</div>
        <div class="detail-info-val"><?= h(substr($cmd['heuresaisie'], 11, 5)) ?></div>
    </div>
    <div class="detail-info">
        <div class="detail-info-lbl">Total estimé</div>
        <div class="detail-info-val" style="color:#c8a84b;"><?= formatEuro($cmd['total']) ?></div>
    </div>
</div>

<table class="detail-lignes">
    <thead>
        <tr>
            <th>Article</th><th>Variété</th><th>Calibre</th>
            <th>Qté dem.</th><th>Qté livrée</th>
            <th>Prix/kg</th><th>Sous-total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lignes as $l): ?>
        <tr>
            <td><?= h($l['libellearticle']) ?></td>
            <td><?= h($l['nomvariete']) ?></td>
            <td><?= h($l['calibre']) ?></td>
            <td><?= (int)$l['quantitedemandee'] ?></td>
            <td><?= $l['quantitelivree'] !== null ? h($l['quantitelivree']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= formatEuro($l['prixunitaire']) ?></td>
            <td><strong><?= formatEuro((float)$l['quantitedemandee'] * (float)$l['prixunitaire']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" style="text-align:right;">Total estimé</td>
            <td><?= formatEuro($cmd['total']) ?></td>
        </tr>
    </tfoot>
</table>
<a href="<?= h(url('/pages/nouvelle_commande.php')) ?>" class="btn-reorder">🔄 Passer une nouvelle commande</a>
