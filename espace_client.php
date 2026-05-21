<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['client']);
$pdo        = getPDO();
$codeClient = $_SESSION['codeclient'];

$s = $pdo->prepare('SELECT * FROM client WHERE codeclient = :c');
$s->execute([':c' => $codeClient]);
$client = $s->fetch();

$s = $pdo->prepare('SELECT COUNT(*) FROM commande WHERE codeclient = :c');
$s->execute([':c' => $codeClient]);
$nbCommandes = (int)$s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM commande WHERE codeclient = :c AND statut = 'Saisie'");
$s->execute([':c' => $codeClient]);
$nbEnCours = (int)$s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM commande WHERE codeclient = :c AND statut = 'Livree'");
$s->execute([':c' => $codeClient]);
$nbLivrees = (int)$s->fetchColumn();

$s = $pdo->prepare("
    SELECT c.idcommande, c.datecommande, c.statut, c.heuresaisie,
           ml.libellemodeliv, mp.libellepaiement,
           COALESCE(SUM(l.quantitedemandee * l.prixunitaire), 0) total
    FROM commande c
    JOIN mode_livraison ml ON ml.idmodeliv  = c.idmodeliv
    JOIN mode_paiement  mp ON mp.idpaiement = c.idpaiement
    LEFT JOIN ligne_commande l ON l.idcommande = c.idcommande
    WHERE c.codeclient = :c
    GROUP BY c.idcommande, c.datecommande, c.statut, c.heuresaisie, ml.libellemodeliv, mp.libellepaiement
    ORDER BY c.datecommande DESC, c.idcommande DESC
    LIMIT 5
");
$s->execute([':c' => $codeClient]);
$commandes = $s->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
.espace-hero {
    background: linear-gradient(135deg, #1a3a0f 0%, #2d5a1b 100%);
    border-radius: 18px; padding: 32px 36px;
    margin-bottom: 32px; position: relative; overflow: hidden;
    display: flex; align-items: center; justify-content: space-between; gap: 24px;
}
.espace-hero::before {
    content: ''; position: absolute; top: -30px; right: -30px;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(200,168,75,0.10);
}
.espace-hero-txt h2 {
    font-family: Georgia,'Times New Roman',serif; font-size: 1.6rem;
    color: #fff; margin: 0 0 6px;
}
.espace-hero-txt p { font-size: 0.90rem; color: rgba(255,255,255,0.65); margin: 0; }
.tarif-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(200,168,75,0.20); border: 1px solid rgba(200,168,75,0.40);
    color: #f0dfa0; padding: 6px 14px; border-radius: 999px;
    font-size: 0.78rem; font-weight: 700; letter-spacing: 0.5px;
    text-transform: uppercase; margin-top: 10px;
}
.espace-hero-actions { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
.btn-hero-cmd {
    background: #c8a84b; color: #1a3a0f;
    padding: 11px 22px; border-radius: 8px;
    font-weight: 700; font-size: 0.92rem;
    font-family: 'Segoe UI',sans-serif; text-decoration: none;
    transition: background 0.18s, transform 0.12s;
    white-space: nowrap;
}
.btn-hero-cmd:hover { background: #dfc05e; color: #1a3a0f; text-decoration: none; transform: translateY(-1px); }
.btn-hero-sec2 {
    background: rgba(255,255,255,0.10); color: #fff;
    padding: 11px 22px; border-radius: 8px;
    font-weight: 600; font-size: 0.92rem;
    font-family: 'Segoe UI',sans-serif; text-decoration: none;
    border: 1px solid rgba(255,255,255,0.25);
    transition: background 0.18s; white-space: nowrap;
}
.btn-hero-sec2:hover { background: rgba(255,255,255,0.18); color: #fff; text-decoration: none; }

.client-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 32px; }
.info-card { background: #fff; border-radius: 14px; border: 1px solid #e5e8e0; padding: 22px; box-shadow: 0 2px 8px rgba(26,58,15,0.06); }
.info-card h3 { font-family: Georgia,'Times New Roman',serif; font-size: 1rem; color: #1a3a0f; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f0f2ec; }
.info-row { display: flex; justify-content: space-between; align-items: baseline; padding: 7px 0; border-bottom: 1px solid #f9faf5; font-size: 0.87rem; }
.info-row:last-child { border-bottom: none; }
.info-lbl { color: #6b7280; }
.info-val { font-weight: 600; color: #1a3a0f; text-align: right; }

/* Timeline mini dans tableau */
.tl-mini { display: flex; align-items: center; gap: 4px; }
.tl-mini-dot { width: 8px; height: 8px; border-radius: 50%; background: #e5e8e0; }
.tl-mini-dot.done  { background: #4a7c2f; }
.tl-mini-dot.actif { background: #c8a84b; box-shadow: 0 0 0 2px rgba(200,168,75,0.30); }
.tl-mini-line { flex: 1; height: 2px; background: #e5e8e0; max-width: 16px; }
.tl-mini-line.done { background: #4a7c2f; }

@media(max-width:768px){ .client-grid { grid-template-columns: 1fr; } .espace-hero { flex-direction: column; } }
</style>

<?php if (!$client): ?>
    <?= msgErreur('Votre compte n\'est pas lié à un client existant. Contactez l\'administrateur.') ?>
<?php else: ?>

<!-- Bannière de bienvenue -->
<div class="espace-hero">
    <div class="espace-hero-txt">
        <h2>Bonjour, <?= h(explode(' ', $client['raisonsociale'])[0]) ?> 👋</h2>
        <p><?= h($client['raisonsociale']) ?> · <?= h($client['adresse']) ?></p>
        <div class="tarif-badge">🏷️ Tarif <?= h($client['nomtarif']) ?></div>
    </div>
    <div class="espace-hero-actions">
        <a href="<?= h(url('/pages/nouvelle_commande.php')) ?>" class="btn-hero-cmd">➕ Commander</a>
        <a href="<?= h(url('/pages/mes_commandes.php')) ?>" class="btn-hero-sec2">📋 Mes commandes</a>
        <a href="<?= h(url('/index.php')) ?>" class="btn-hero-sec2">🍊 Catalogue</a>
    </div>
</div>

<!-- Grille infos + stats -->
<div class="client-grid">
    <div class="info-card">
        <h3>📋 Mes informations</h3>
        <div class="info-row"><span class="info-lbl">Code client</span><span class="info-val"><?= h($client['codeclient']) ?></span></div>
        <div class="info-row"><span class="info-lbl">Téléphone</span><span class="info-val"><?= h($client['telephone']) ?></span></div>
        <div class="info-row"><span class="info-lbl">Adresse</span><span class="info-val" style="max-width:180px;"><?= h($client['adresse']) ?></span></div>
        <div class="info-row"><span class="info-lbl">Tarif</span><span class="info-val" style="color:#c8a84b;"><?= h($client['nomtarif']) ?></span></div>
    </div>

    <div>
        <div class="cards-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:0;">
            <div class="stat-card">
                <span class="stat-icon">📋</span>
                <div class="stat-val"><?= $nbCommandes ?></div>
                <div class="stat-lbl">Total commandes</div>
            </div>
            <div class="stat-card" style="border-top-color:#d97706;">
                <span class="stat-icon">⏳</span>
                <div class="stat-val" style="<?= $nbEnCours > 0 ? 'color:#d97706;' : '' ?>"><?= $nbEnCours ?></div>
                <div class="stat-lbl">En cours</div>
            </div>
            <div class="stat-card" style="border-top-color:#16a34a;">
                <span class="stat-icon">✅</span>
                <div class="stat-val" style="color:#16a34a;"><?= $nbLivrees ?></div>
                <div class="stat-lbl">Livrées</div>
            </div>
        </div>
    </div>
</div>

<!-- Dernières commandes -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
    <h2 style="margin:0;">Dernières commandes</h2>
    <a href="<?= h(url('/pages/mes_commandes.php')) ?>" class="btn btn-secondary btn-sm">Voir tout →</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr><th>#</th><th>Date</th><th>Statut</th><th>Avancement</th><th>Paiement</th><th>Livraison</th><th>Total</th><th></th></tr>
        </thead>
        <tbody>
            <?php if (empty($commandes)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">Aucune commande pour le moment.</td></tr>
            <?php else: ?>
                <?php foreach ($commandes as $c):
                    $etapes = ['Saisie' => 1, 'Preparee' => 2, 'Livree' => 3];
                    $niv = $etapes[$c['statut']] ?? 1;
                ?>
                <tr>
                    <td><strong>#<?= (int)$c['idcommande'] ?></strong></td>
                    <td><?= h($c['datecommande']) ?></td>
                    <td><span class="badge <?= classeBadgeStatut($c['statut']) ?>"><?= h(libelleStatut($c['statut'])) ?></span></td>
                    <td>
                        <div class="tl-mini">
                            <div class="tl-mini-dot <?= $niv >= 1 ? 'done' : '' ?>"></div>
                            <div class="tl-mini-line <?= $niv >= 2 ? 'done' : '' ?>"></div>
                            <div class="tl-mini-dot <?= $niv >= 2 ? ($niv > 2 ? 'done' : 'actif') : '' ?>"></div>
                            <div class="tl-mini-line <?= $niv >= 3 ? 'done' : '' ?>"></div>
                            <div class="tl-mini-dot <?= $niv >= 3 ? 'done' : '' ?>"></div>
                        </div>
                    </td>
                    <td><?= h($c['libellepaiement']) ?></td>
                    <td><?= h($c['libellemodeliv']) ?></td>
                    <td><strong><?= formatEuro($c['total']) ?></strong></td>
                    <td><a class="btn btn-secondary btn-sm" href="<?= h(url('/pages/mes_commandes.php?id=' . (int)$c['idcommande'])) ?>">Voir →</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
