<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['client']);
$pdo        = getPDO();
$codeClient = $_SESSION['codeclient'];

$stmt = $pdo->prepare("
    SELECT c.idcommande, c.datecommande, c.statut, c.heuresaisie,
           ml.libellemodeliv, mp.libellepaiement,
           COALESCE(SUM(l.quantitedemandee * l.prixunitaire), 0) total,
           COUNT(l.codevariete) nb_lignes
    FROM commande c
    JOIN mode_livraison ml ON ml.idmodeliv  = c.idmodeliv
    JOIN mode_paiement  mp ON mp.idpaiement = c.idpaiement
    LEFT JOIN ligne_commande l ON l.idcommande = c.idcommande
    WHERE c.codeclient = :c
    GROUP BY c.idcommande, c.datecommande, c.statut, c.heuresaisie, ml.libellemodeliv, mp.libellepaiement
    ORDER BY c.datecommande DESC, c.idcommande DESC
");
$stmt->execute([':c' => $codeClient]);
$commandes = $stmt->fetchAll();

$detail   = [];
$idDetail = null;
if (isset($_GET['id'])) {
    $idDetail = (int)$_GET['id'];
    if (verifierClientProprietaire($pdo, $idDetail, $codeClient)) {
        $stmt = $pdo->prepare("
            SELECT l.*, v.nomvariete, v.calibre, a.libellearticle
            FROM ligne_commande l
            JOIN variete v ON v.codevariete = l.codevariete
            JOIN article a ON a.idarticle   = v.idarticle
            WHERE l.idcommande = :id
            ORDER BY a.libellearticle, v.nomvariete
        ");
        $stmt->execute([':id' => $idDetail]);
        $detail = $stmt->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Timeline statut ── */
.timeline {
    display: flex; align-items: center; gap: 0;
    margin: 8px 0 0; width: 100%;
}
.tl-step {
    display: flex; flex-direction: column; align-items: center;
    flex: 1; position: relative;
}
.tl-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 12px; left: 50%; right: -50%;
    height: 2px;
    background: #d4d8cc;
    z-index: 0;
}
.tl-step.done:not(:last-child)::after { background: #4a7c2f; }
.tl-dot {
    width: 24px; height: 24px; border-radius: 50%;
    background: #f0f2ec; border: 2px solid #d4d8cc;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.65rem; font-weight: 700; color: #9ca3af;
    position: relative; z-index: 1; transition: all 0.3s;
}
.tl-step.done .tl-dot   { background: #4a7c2f; border-color: #4a7c2f; color: #fff; }
.tl-step.actif .tl-dot  { background: #c8a84b; border-color: #c8a84b; color: #fff; box-shadow: 0 0 0 4px rgba(200,168,75,0.20); }
.tl-lbl {
    font-size: 0.68rem; font-weight: 600; color: #9ca3af;
    margin-top: 5px; text-align: center; white-space: nowrap;
}
.tl-step.done .tl-lbl   { color: #4a7c2f; }
.tl-step.actif .tl-lbl  { color: #c8a84b; }

/* ── Cartes commandes ── */
.cmd-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 32px; }
.cmd-card {
    background: #fff; border-radius: 14px;
    border: 1px solid #e5e8e0;
    box-shadow: 0 2px 8px rgba(26,58,15,0.06);
    overflow: hidden; transition: box-shadow 0.2s;
}
.cmd-card:hover { box-shadow: 0 6px 20px rgba(26,58,15,0.10); }
.cmd-card.selected { border-color: #4a7c2f; box-shadow: 0 0 0 3px rgba(74,124,47,0.12); }
.cmd-card-header {
    display: grid; grid-template-columns: auto 1fr auto auto;
    gap: 16px; align-items: center;
    padding: 18px 22px; cursor: pointer;
}
.cmd-id {
    font-family: Georgia,'Times New Roman',serif;
    font-size: 1.1rem; font-weight: 700; color: #1a3a0f;
    min-width: 52px;
}
.cmd-meta { font-size: 0.83rem; color: #6b7280; }
.cmd-meta strong { color: #3d3d3d; font-weight: 600; display: block; margin-bottom: 2px; }
.cmd-prix {
    font-family: Georgia,'Times New Roman',serif;
    font-size: 1.1rem; font-weight: 700; color: #c8a84b;
    text-align: right; white-space: nowrap;
}
.cmd-chevron {
    color: #9ca3af; font-size: 1rem; transition: transform 0.25s;
    line-height: 1;
}
.cmd-card.open .cmd-chevron { transform: rotate(180deg); }

.cmd-timeline-wrap { padding: 0 22px 14px; }

.cmd-detail {
    display: none; border-top: 1px solid #f0f2ec;
    padding: 20px 22px;
    animation: fadeSlide 0.25s ease;
}
.cmd-card.open .cmd-detail { display: block; }
@keyframes fadeSlide { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:none;} }

.detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 12px; margin-bottom: 18px; }
.detail-info { background: #f3faf0; border-radius: 8px; padding: 12px 14px; border: 1px solid #e0ecda; }
.detail-info-lbl { font-size: 0.70rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; margin-bottom: 3px; }
.detail-info-val { font-size: 0.92rem; font-weight: 700; color: #1a3a0f; }

.detail-lignes { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.detail-lignes thead { background: #f3faf0; }
.detail-lignes thead th { padding: 9px 12px; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #4a7c2f; }
.detail-lignes tbody tr { border-bottom: 1px solid #f0f2ec; }
.detail-lignes tbody tr:last-child { border-bottom: none; }
.detail-lignes tbody td { padding: 10px 12px; color: #3d3d3d; }
.detail-lignes tfoot td { padding: 10px 12px; font-weight: 700; background: #f3faf0; border-top: 2px solid #e0ecda; }

.btn-reorder {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 14px; padding: 9px 18px; border-radius: 8px;
    background: #2d5a1b; color: #fff; font-family: 'Segoe UI',sans-serif;
    font-size: 0.88rem; font-weight: 700; text-decoration: none;
    transition: background 0.18s, transform 0.12s;
}
.btn-reorder:hover { background: #1a3a0f; color: #fff; text-decoration: none; transform: translateY(-1px); }

@media(max-width:600px){
    .cmd-card-header { grid-template-columns: auto 1fr auto; gap: 10px; padding: 14px; }
    .cmd-prix { display: none; }
}
</style>

<div class="breadcrumb">
    <a href="<?= h(url('/pages/espace_client.php')) ?>">Mon espace</a>
    <span>›</span>
    <span>Mes commandes</span>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <h1 style="margin-bottom:0;border:none;padding:0;">📋 Mes commandes</h1>
    <a class="btn btn-primary btn-sm" href="<?= h(url('/pages/nouvelle_commande.php')) ?>">➕ Nouvelle commande</a>
</div>

<?php if (empty($commandes)): ?>
    <div class="empty-state">
        <div class="empty-icon">📋</div>
        <p>Vous n'avez pas encore de commande.</p>
        <a class="btn btn-primary mt-4" href="<?= h(url('/pages/nouvelle_commande.php')) ?>">Passer ma première commande</a>
    </div>
<?php else: ?>

<div class="cmd-list" id="cmdList">
    <?php foreach ($commandes as $idx => $c):
        $statut  = $c['statut'];
        $etapes  = ['Saisie' => 1, 'Preparee' => 2, 'Livree' => 3];
        $niveauActuel = $etapes[$statut] ?? 1;
        $isOpen  = ($idDetail === (int)$c['idcommande']);
    ?>
    <div class="cmd-card <?= $isOpen ? 'open selected' : '' ?>" id="cmd-<?= (int)$c['idcommande'] ?>">

        <!-- En-tête cliquable -->
        <div class="cmd-card-header" onclick="toggleCmd(<?= (int)$c['idcommande'] ?>)">
            <div class="cmd-id">#<?= (int)$c['idcommande'] ?></div>
            <div class="cmd-meta">
                <strong><?= h($c['datecommande']) ?></strong>
                <?= (int)$c['nb_lignes'] ?> produit<?= (int)$c['nb_lignes'] > 1 ? 's' : '' ?>
                &nbsp;·&nbsp; <?= h($c['libellemodeliv']) ?>
                &nbsp;·&nbsp; <?= h($c['libellepaiement']) ?>
            </div>
            <div class="cmd-prix"><?= formatEuro($c['total']) ?></div>
            <div class="cmd-chevron">▾</div>
        </div>

        <!-- Timeline -->
        <div class="cmd-timeline-wrap">
            <div class="timeline">
                <?php
                $tlSteps = [
                    1 => ['label' => 'Saisie',    'icon' => '📝'],
                    2 => ['label' => 'Préparée',  'icon' => '📦'],
                    3 => ['label' => 'Livrée',    'icon' => '✅'],
                ];
                foreach ($tlSteps as $n => $step):
                    $cls = $n < $niveauActuel ? 'done' : ($n === $niveauActuel ? 'actif' : '');
                ?>
                <div class="tl-step <?= $cls ?>">
                    <div class="tl-dot"><?= $step['icon'] ?></div>
                    <div class="tl-lbl"><?= $step['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Détail (chargé via AJAX ou pré-chargé) -->
        <div class="cmd-detail" id="detail-<?= (int)$c['idcommande'] ?>">
            <?php if ($isOpen && !empty($detail)): ?>
                <?php include_once __DIR__ . '/../includes/_detail_commande.php'; // snippet interne ?>
                <div class="detail-grid">
                    <div class="detail-info">
                        <div class="detail-info-lbl">Mode de paiement</div>
                        <div class="detail-info-val"><?= h($c['libellepaiement']) ?></div>
                    </div>
                    <div class="detail-info">
                        <div class="detail-info-lbl">Mode de livraison</div>
                        <div class="detail-info-val"><?= h($c['libellemodeliv']) ?></div>
                    </div>
                    <div class="detail-info">
                        <div class="detail-info-lbl">Heure de saisie</div>
                        <div class="detail-info-val"><?= h(substr($c['heuresaisie'], 11, 5)) ?></div>
                    </div>
                    <div class="detail-info">
                        <div class="detail-info-lbl">Total</div>
                        <div class="detail-info-val" style="color:#c8a84b;"><?= formatEuro($c['total']) ?></div>
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
                        <?php foreach ($detail as $l): ?>
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
                            <td><?= formatEuro($c['total']) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php if ($statut === 'Saisie'): ?>
                <a href="<?= h(url('/pages/nouvelle_commande.php')) ?>" class="btn-reorder">🔄 Passer une nouvelle commande</a>
                <?php endif; ?>
            <?php else: ?>
                <div style="text-align:center;padding:12px;color:#9ca3af;font-size:0.85rem;">
                    Chargement du détail…
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const BASE_URL = "<?= h(url('')) ?>";
const CLIENT_CODE = "<?= h($codeClient) ?>";

function toggleCmd(id) {
    const card = document.getElementById('cmd-' + id);
    const isOpen = card.classList.contains('open');

    /* Fermer tous */
    document.querySelectorAll('.cmd-card').forEach(c => {
        c.classList.remove('open', 'selected');
    });

    if (!isOpen) {
        card.classList.add('open', 'selected');
        chargerDetail(id);
        /* Mettre à jour l'URL sans recharger */
        history.replaceState(null, '', '?id=' + id);
    } else {
        history.replaceState(null, '', '?');
    }
}

function chargerDetail(id) {
    const zone = document.getElementById('detail-' + id);
    /* Si déjà chargé (contient une table) on ne recharge pas */
    if (zone.querySelector('table')) return;

    fetch(BASE_URL + '/pages/_ajax_detail_commande.php?id=' + id)
        .then(r => r.text())
        .then(html => { zone.innerHTML = html; })
        .catch(() => { zone.innerHTML = '<p style="color:#dc2626;padding:12px;">Erreur de chargement.</p>'; });
}

/* Ouvrir automatiquement si id dans l'URL */
const urlId = new URLSearchParams(location.search).get('id');
if (urlId) {
    const card = document.getElementById('cmd-' + urlId);
    if (card && !card.classList.contains('open')) chargerDetail(parseInt(urlId));
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
