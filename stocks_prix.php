<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['gestionnaire', 'admin']);
$pdo     = getPDO();
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'stock') {
            $code  = $_POST['codevariete'] ?? '';
            $stock = (int)($_POST['quantitestock'] ?? -1);
            if ($code === '' || $stock < 0) throw new Exception('Stock invalide (doit être ≥ 0).');
            $stmt = $pdo->prepare('UPDATE variete SET quantitestock = :s WHERE codevariete = :c');
            $stmt->execute([':s' => $stock, ':c' => $code]);
            $message = 'Stock de ' . h($code) . ' mis à jour : ' . $stock . ' unité(s).';

        } elseif ($action === 'prix') {
            $idPrix = (int)($_POST['idprix'] ?? 0);
            $prix   = (float)($_POST['prixkg'] ?? 0);
            if ($idPrix <= 0 || $prix <= 0) throw new Exception('Prix invalide (doit être > 0).');
            $stmt = $pdo->prepare('UPDATE prix SET prixkg = :p WHERE idprix = :id');
            $stmt->execute([':p' => $prix, ':id' => $idPrix]);
            $message = 'Prix #' . $idPrix . ' mis à jour : ' . formatEuro($prix) . '/kg.';
        }
    } catch (Throwable $e) {
        $erreur = $e->getMessage();
    }
}

$stocks = $pdo->query("
    SELECT v.codevariete, v.nomvariete, v.calibre, v.quantitestock,
           a.libellearticle, c.libellecategorie
    FROM variete v
    JOIN article a ON a.idarticle = v.idarticle
    JOIN categorie c ON c.idcategorie = a.idcategorie
    ORDER BY c.libellecategorie, a.libellearticle, v.nomvariete
")->fetchAll();

$prix = $pdo->query("
    SELECT p.idprix, p.codevariete, v.nomvariete, p.nomtarif, p.prixkg, p.datedebut, p.datefin
    FROM prix p
    JOIN variete v ON v.codevariete = p.codevariete
    ORDER BY p.nomtarif, v.nomvariete
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <h1>📦 Stocks & Prix</h1>
        <p><?= count($stocks) ?> variété<?= count($stocks) > 1 ? 's' : '' ?> · <?= count($prix) ?> tarif<?= count($prix) > 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= h(url('/pages/dashboard.php')) ?>" class="btn btn-secondary btn-sm">← Dashboard</a>
</div>

<?php if ($message): echo msgSucces($message); endif; ?>
<?php if ($erreur):  echo msgErreur($erreur);  endif; ?>

<h2>Stocks des variétés</h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Catégorie</th>
                <th>Article</th>
                <th>Code</th>
                <th>Variété</th>
                <th>Calibre</th>
                <th>Stock actuel</th>
                <th>Modifier</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stocks as $s):
                $stock = (int)$s['quantitestock'];
                $stockClass = $stock <= 0 ? 'stock-zero' : ($stock < 50 ? 'stock-bas' : 'stock-ok');
            ?>
            <tr>
                <td><small class="text-muted"><?= h($s['libellecategorie']) ?></small></td>
                <td><?= h($s['libellearticle']) ?></td>
                <td><code style="font-size:0.82rem;background:var(--vert-pale);padding:2px 6px;border-radius:4px;"><?= h($s['codevariete']) ?></code></td>
                <td><?= h($s['nomvariete']) ?></td>
                <td><?= h($s['calibre']) ?></td>
                <td><span class="<?= $stockClass ?>" style="font-weight:700;"><?= $stock ?></span></td>
                <td>
                    <form method="POST" class="form-inline">
                        <input type="hidden" name="action" value="stock">
                        <input type="hidden" name="codevariete" value="<?= h($s['codevariete']) ?>">
                        <input type="number" min="0" name="quantitestock" value="<?= $stock ?>" style="width:80px;">
                        <button class="btn btn-secondary btn-sm">OK</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h2>Prix par tarif client</h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Code variété</th>
                <th>Variété</th>
                <th>Tarif</th>
                <th>Prix/kg</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Modifier</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prix as $p): ?>
            <tr>
                <td><small class="text-muted"><?= (int)$p['idprix'] ?></small></td>
                <td><code style="font-size:0.82rem;background:var(--vert-pale);padding:2px 6px;border-radius:4px;"><?= h($p['codevariete']) ?></code></td>
                <td><?= h($p['nomvariete']) ?></td>
                <td><span class="badge badge-preparee"><?= h($p['nomtarif']) ?></span></td>
                <td><strong><?= formatEuro($p['prixkg']) ?></strong></td>
                <td><?= h($p['datedebut']) ?></td>
                <td><?= h($p['datefin'] ?? '—') ?></td>
                <td>
                    <form method="POST" class="form-inline">
                        <input type="hidden" name="action" value="prix">
                        <input type="hidden" name="idprix" value="<?= (int)$p['idprix'] ?>">
                        <input type="number" step="0.01" min="0.01" name="prixkg" value="<?= h($p['prixkg']) ?>" style="width:85px;">
                        <button class="btn btn-secondary btn-sm">OK</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
