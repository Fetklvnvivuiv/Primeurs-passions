<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['televente', 'admin']);
$pdo     = getPDO();
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeClient  = $_POST['codeclient']  ?? '';
    $codeVariete = $_POST['codevariete'] ?? '';
    $quantite    = (int)($_POST['quantite']    ?? 0);
    $idPaiement  = (int)($_POST['idpaiement']  ?? 0);
    $idModeLiv   = (int)($_POST['idmodeliv']   ?? 0);
    $idPersonnel = $_SESSION['idpersonnel'] ?: DEFAULT_ID_PERSONNEL;

    try {
        if ($codeClient === '' || $codeVariete === '' || $quantite <= 0) {
            throw new Exception('Tous les champs sont obligatoires et la quantité doit être positive.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM variete WHERE codevariete = :cv FOR UPDATE');
        $stmt->execute([':cv' => $codeVariete]);
        $variete = $stmt->fetch();

        if (!$variete || (int)$variete['quantitestock'] < $quantite) {
            throw new Exception('Stock insuffisant ou variété inconnue (stock : ' . ($variete['quantitestock'] ?? 0) . ').');
        }

        $stmt = $pdo->prepare('SELECT nomtarif FROM client WHERE codeclient = :c');
        $stmt->execute([':c' => $codeClient]);
        $nomTarif = $stmt->fetchColumn();
        if (!$nomTarif) throw new Exception('Client inconnu.');

        $stmt = $pdo->prepare('SELECT prixkg FROM prix WHERE codevariete = :cv AND nomtarif = :tarif AND datefin IS NULL ORDER BY datedebut DESC LIMIT 1');
        $stmt->execute([':cv' => $codeVariete, ':tarif' => $nomTarif]);
        $prix = $stmt->fetchColumn();
        if ($prix === false) throw new Exception('Prix introuvable pour le tarif client "' . $nomTarif . '".');

        $stmt = $pdo->prepare("
            INSERT INTO commande(datecommande, commandeorigine, statut, heuresaisie, codeclient, idpersonnel, idpaiement, idmodeliv, moiscommande)
            VALUES (CURRENT_DATE, NULL, 'Saisie', CURRENT_TIMESTAMP, :client, :pers, :paie, :liv, :mois)
            RETURNING idcommande
        ");
        $stmt->execute([':client' => $codeClient, ':pers' => $idPersonnel, ':paie' => $idPaiement, ':liv' => $idModeLiv, ':mois' => moisFrancais()]);
        $id = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('INSERT INTO ligne_commande(idcommande, codevariete, quantitedemandee, prixunitaire) VALUES (:id, :cv, :qte, :prix)');
        $stmt->execute([':id' => $id, ':cv' => $codeVariete, ':qte' => $quantite, ':prix' => $prix]);

        $pdo->prepare('UPDATE variete SET quantitestock = quantitestock - :qte WHERE codevariete = :cv')
            ->execute([':qte' => $quantite, ':cv' => $codeVariete]);

        $pdo->commit();
        $message = 'Commande télévente #' . $id . ' créée — ' . $quantite . ' unités de ' . h($variete['nomvariete']) . ' pour ' . formatEuro($quantite * $prix) . '.';

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erreur = $e->getMessage();
    }
}

$clients  = $pdo->query('SELECT codeclient, raisonsociale FROM client ORDER BY raisonsociale')->fetchAll();
$varietes = $pdo->query('SELECT v.codevariete, v.nomvariete, v.quantitestock, a.libellearticle FROM variete v JOIN article a ON a.idarticle = v.idarticle ORDER BY a.libellearticle, v.nomvariete')->fetchAll();
$paiements = $pdo->query('SELECT idpaiement, libellepaiement FROM mode_paiement ORDER BY idpaiement')->fetchAll();
$livraisons = $pdo->query('SELECT idmodeliv, libellemodeliv FROM mode_livraison ORDER BY idmodeliv')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <h1>☎️ Saisie de commande</h1>
        <p>Créer une commande pour un client</p>
    </div>
    <a href="<?= h(url('/pages/dashboard.php')) ?>" class="btn btn-secondary btn-sm">← Dashboard</a>
</div>

<?php if ($message): echo msgSucces($message); endif; ?>
<?php if ($erreur):  echo msgErreur($erreur);  endif; ?>

<div class="form-card">
    <form method="POST">
        <div class="form-group">
            <label>Client</label>
            <select name="codeclient" required>
                <option value="">— Choisir un client —</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= h($c['codeclient']) ?>" <?= (($_POST['codeclient'] ?? '') === $c['codeclient']) ? 'selected' : '' ?>>
                        <?= h($c['codeclient'] . ' — ' . $c['raisonsociale']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Variété</label>
            <select name="codevariete" required>
                <option value="">— Choisir une variété —</option>
                <?php foreach ($varietes as $v): ?>
                    <option value="<?= h($v['codevariete']) ?>" <?= (($_POST['codevariete'] ?? '') === $v['codevariete']) ? 'selected' : '' ?> <?= ((int)$v['quantitestock'] <= 0) ? 'disabled' : '' ?>>
                        <?= h($v['libellearticle'] . ' — ' . $v['nomvariete']) ?>
                        (stock : <?= (int)$v['quantitestock'] ?>)
                        <?= ((int)$v['quantitestock'] <= 0) ? '[Rupture]' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Quantité demandée</label>
            <input type="number" min="1" name="quantite" value="<?= h($_POST['quantite'] ?? '') ?>" required placeholder="Ex. : 50">
        </div>

        <div class="form-group">
            <label>Mode de paiement</label>
            <select name="idpaiement" required>
                <?php foreach ($paiements as $p): ?>
                    <option value="<?= (int)$p['idpaiement'] ?>"><?= h($p['libellepaiement']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Mode de livraison</label>
            <select name="idmodeliv" required>
                <?php foreach ($livraisons as $l): ?>
                    <option value="<?= (int)$l['idmodeliv'] ?>"><?= h($l['libellemodeliv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary w-full">☎️ Créer la commande</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
