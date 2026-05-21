<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['televente', 'preparateur', 'gestionnaire', 'admin']);
$pdo  = getPDO();
$role = getRole();

$stats = [
    'clients'   => (int)$pdo->query('SELECT COUNT(*) FROM client')->fetchColumn(),
    'commandes' => (int)$pdo->query('SELECT COUNT(*) FROM commande')->fetchColumn(),
    'factures'  => (int)$pdo->query('SELECT COUNT(*) FROM facture')->fetchColumn(),
    'anomalies' => (int)$pdo->query('SELECT COUNT(*) FROM anomalie')->fetchColumn(),
    'saisie'    => (int)$pdo->query("SELECT COUNT(*) FROM commande WHERE statut = 'Saisie'")->fetchColumn(),
    'preparee'  => (int)$pdo->query("SELECT COUNT(*) FROM commande WHERE statut = 'Preparee'")->fetchColumn(),
    'livree'    => (int)$pdo->query("SELECT COUNT(*) FROM commande WHERE statut = 'Livree'")->fetchColumn(),
];

/* ── Commandes par mois (12 derniers mois) ── */
$cmdParMois = $pdo->query("
    SELECT TO_CHAR(datecommande, 'Mon') AS mois_label,
           TO_CHAR(datecommande, 'YYYY-MM') AS mois_tri,
           COUNT(*) AS nb,
           COALESCE(SUM(COALESCE(l.quantitelivree, l.quantitedemandee) * l.prixunitaire), 0) AS ca
    FROM commande c
    LEFT JOIN ligne_commande l ON l.idcommande = c.idcommande
    WHERE datecommande >= CURRENT_DATE - INTERVAL '11 months'
    GROUP BY mois_label, mois_tri
    ORDER BY mois_tri ASC
")->fetchAll();

/* ── Top 5 variétés commandées ── */
$topVarietes = $pdo->query("
    SELECT v.nomvariete, a.libellearticle,
           SUM(l.quantitedemandee) AS total_commande
    FROM ligne_commande l
    JOIN variete v ON v.codevariete = l.codevariete
    JOIN article a ON a.idarticle = v.idarticle
    GROUP BY v.nomvariete, a.libellearticle
    ORDER BY total_commande DESC
    LIMIT 5
")->fetchAll();

/* ── Dernières commandes ── */
$dernieres = $pdo->query("
    SELECT c.idcommande, c.datecommande, c.statut, cl.raisonsociale,
           COALESCE(SUM(l.quantitedemandee * l.prixunitaire), 0) AS total
    FROM commande c
    JOIN client cl ON cl.codeclient = c.codeclient
    LEFT JOIN ligne_commande l ON l.idcommande = c.idcommande
    GROUP BY c.idcommande, c.datecommande, c.statut, cl.raisonsociale
    ORDER BY c.idcommande DESC
    LIMIT 8
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
.dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
.chart-card { background: #fff; border-radius: 16px; border: 1px solid #e5e8e0; padding: 24px; box-shadow: 0 2px 8px rgba(26,58,15,0.06); }
.chart-card h3 { font-family: Georgia,'Times New Roman',serif; font-size: 1rem; color: #1a3a0f; margin-bottom: 18px; }
.chart-wrap { position: relative; height: 220px; }

/* Top variétés */
.top-var-item { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.top-var-rank { font-family: Georgia,'Times New Roman',serif; font-size: 1.3rem; font-weight: 700; color: #e8f5e1; min-width: 28px; text-align: center; }
.top-var-rank.gold   { color: #c8a84b; }
.top-var-rank.silver { color: #9ca3af; }
.top-var-rank.bronze { color: #cd7c2f; }
.top-var-bar-wrap { flex: 1; }
.top-var-name { font-size: 0.85rem; font-weight: 600; color: #1a3a0f; margin-bottom: 4px; }
.top-var-sub  { font-size: 0.75rem; color: #6b7280; }
.top-var-bar  { height: 6px; background: #f0f2ec; border-radius: 999px; margin-top: 6px; overflow: hidden; }
.top-var-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #4a7c2f, #8bc34a); transition: width 0.8s cubic-bezier(0.4,0,0.2,1); }
.top-var-val  { font-family: Georgia,'Times New Roman',serif; font-size: 1rem; font-weight: 700; color: #4a7c2f; min-width: 40px; text-align: right; }

/* Alerte stock bas */
.alert-band {
    background: #fff8ed; border: 1px solid #fde68a; border-radius: 12px;
    padding: 14px 18px; margin-bottom: 24px;
    display: flex; align-items: center; gap: 12px; font-size: 0.88rem; color: #92400e;
}
.alert-band strong { font-weight: 700; }

@media(max-width:900px){ .dash-grid { grid-template-columns: 1fr; } }
</style>

<div class="section-header">
    <div>
        <h1>📊 Dashboard</h1>
        <p>Vue d'ensemble de l'activité — <?= date('d/m/Y') ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if (in_array($role, ['televente','admin'], true)): ?>
            <a class="btn btn-dore btn-sm" href="<?= h(url('/pages/commandes_televente.php')) ?>">+ Saisie</a>
        <?php endif; ?>
        <?php if (in_array($role, ['preparateur','admin'], true)): ?>
            <a class="btn btn-secondary btn-sm" href="<?= h(url('/pages/commandes_preparer.php')) ?>">📦 À préparer <?php if($stats['saisie']>0): ?><span style="background:#c8a84b;color:#1a3a0f;border-radius:999px;padding:1px 7px;font-size:0.75rem;margin-left:4px;"><?= $stats['saisie'] ?></span><?php endif; ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- Alerte si commandes en attente -->
<?php if ($stats['saisie'] > 0): ?>
<div class="alert-band">
    ⚠️ <span><strong><?= $stats['saisie'] ?> commande<?= $stats['saisie'] > 1 ? 's' : '' ?></strong> en attente de préparation.</span>
    <?php if (in_array($role, ['preparateur','admin'], true)): ?>
        <a href="<?= h(url('/pages/commandes_preparer.php')) ?>" style="color:#92400e;font-weight:700;margin-left:auto;">Traiter →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Cartes stats -->
<div class="cards-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
    <div class="stat-card"><span class="stat-icon">👥</span><div class="stat-val"><?= $stats['clients'] ?></div><div class="stat-lbl">Clients</div></div>
    <div class="stat-card"><span class="stat-icon">📋</span><div class="stat-val"><?= $stats['commandes'] ?></div><div class="stat-lbl">Commandes</div></div>
    <div class="stat-card" style="border-top-color:#d97706;"><span class="stat-icon">⏳</span><div class="stat-val" style="color:#d97706;"><?= $stats['saisie'] ?></div><div class="stat-lbl">À préparer</div></div>
    <div class="stat-card" style="border-top-color:#3b82f6;"><span class="stat-icon">📦</span><div class="stat-val" style="color:#3b82f6;"><?= $stats['preparee'] ?></div><div class="stat-lbl">Préparées</div></div>
    <div class="stat-card"><span class="stat-icon">🧾</span><div class="stat-val"><?= $stats['factures'] ?></div><div class="stat-lbl">Factures</div></div>
    <div class="stat-card" style="border-top-color:<?= $stats['anomalies'] > 0 ? '#dc2626' : 'var(--vert-clair)' ?>;"><span class="stat-icon">⚠️</span><div class="stat-val" style="<?= $stats['anomalies'] > 0 ? 'color:#dc2626;' : '' ?>"><?= $stats['anomalies'] ?></div><div class="stat-lbl">Anomalies</div></div>
</div>

<!-- Raccourcis -->
<div class="quick-links">
    <?php if (in_array($role, ['televente','admin'], true)): ?>
        <a class="btn btn-primary" href="<?= h(url('/pages/commandes_televente.php')) ?>">☎️ Saisie commandes</a>
    <?php endif; ?>
    <?php if (in_array($role, ['preparateur','admin'], true)): ?>
        <a class="btn btn-secondary" href="<?= h(url('/pages/commandes_preparer.php')) ?>">📦 Commandes à préparer</a>
    <?php endif; ?>
    <?php if (in_array($role, ['gestionnaire','admin'], true)): ?>
        <a class="btn btn-secondary" href="<?= h(url('/pages/stocks_prix.php')) ?>">📦 Stocks & Prix</a>
        <a class="btn btn-secondary" href="<?= h(url('/pages/facturation.php')) ?>">🧾 Factures & Anomalies</a>
    <?php endif; ?>
    <?php if ($role === 'admin'): ?>
        <a class="btn btn-ghost" href="<?= h(url('/admin/administration.php')) ?>">⚙️ Administration</a>
    <?php endif; ?>
</div>

<!-- Graphiques -->
<div class="dash-grid">

    <!-- Graphique commandes par mois -->
    <div class="chart-card">
        <h3>📈 Commandes par mois</h3>
        <div class="chart-wrap">
            <canvas id="chartMois"></canvas>
        </div>
    </div>

    <!-- Top 5 variétés -->
    <div class="chart-card">
        <h3>🏆 Top variétés commandées</h3>
        <?php
        $maxQte = !empty($topVarietes) ? (int)$topVarietes[0]['total_commande'] : 1;
        $rankColors = ['gold','silver','bronze','',''];
        foreach ($topVarietes as $i => $tv):
            $pct = $maxQte > 0 ? round((int)$tv['total_commande'] / $maxQte * 100) : 0;
        ?>
        <div class="top-var-item">
            <div class="top-var-rank <?= $rankColors[$i] ?? '' ?>"><?= $i+1 ?></div>
            <div class="top-var-bar-wrap">
                <div class="top-var-name"><?= h($tv['nomvariete']) ?></div>
                <div class="top-var-sub"><?= h($tv['libellearticle']) ?></div>
                <div class="top-var-bar"><div class="top-var-fill" style="width:<?= $pct ?>%;"></div></div>
            </div>
            <div class="top-var-val"><?= (int)$tv['total_commande'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topVarietes)): ?>
            <p class="text-muted" style="text-align:center;padding:24px 0;">Aucune donnée.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Dernières commandes -->
<h2>Dernières commandes</h2>
<div class="table-container">
    <table>
        <thead>
            <tr><th>#</th><th>Date</th><th>Client</th><th>Statut</th><th>Total</th></tr>
        </thead>
        <tbody>
            <?php if (empty($dernieres)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">Aucune commande.</td></tr>
            <?php else: ?>
                <?php foreach ($dernieres as $c): ?>
                <tr>
                    <td><strong>#<?= (int)$c['idcommande'] ?></strong></td>
                    <td><?= h($c['datecommande']) ?></td>
                    <td><?= h($c['raisonsociale']) ?></td>
                    <td><span class="badge <?= classeBadgeStatut($c['statut']) ?>"><?= h(libelleStatut($c['statut'])) ?></span></td>
                    <td><?= formatEuro($c['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const moisData = <?= json_encode($cmdParMois, JSON_UNESCAPED_UNICODE) ?>;
const labels   = moisData.map(d => d.mois_label);
const nbData   = moisData.map(d => parseInt(d.nb));
const caData   = moisData.map(d => parseFloat(d.ca).toFixed(2));

const ctx = document.getElementById('chartMois').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Commandes',
                data: nbData,
                backgroundColor: 'rgba(74,124,47,0.75)',
                borderColor: '#2d5a1b',
                borderWidth: 1.5,
                borderRadius: 6,
                yAxisID: 'y',
            },
            {
                label: 'CA (€)',
                data: caData,
                type: 'line',
                borderColor: '#c8a84b',
                backgroundColor: 'rgba(200,168,75,0.10)',
                borderWidth: 2.5,
                pointBackgroundColor: '#c8a84b',
                pointRadius: 4,
                tension: 0.4,
                fill: true,
                yAxisID: 'y2',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                labels: { font: { family: 'Segoe UI', size: 12 }, color: '#3d3d3d' }
            },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        if (ctx.dataset.label === 'CA (€)') {
                            return ' CA : ' + parseFloat(ctx.parsed.y).toLocaleString('fr-FR',{style:'currency',currency:'EUR'});
                        }
                        return ' Commandes : ' + ctx.parsed.y;
                    }
                }
            }
        },
        scales: {
            y:  { beginAtZero:true, ticks:{ font:{family:'Segoe UI',size:11}, color:'#6b7280', precision:0 }, grid:{ color:'rgba(0,0,0,0.05)' } },
            y2: { beginAtZero:true, position:'right', ticks:{ font:{family:'Segoe UI',size:11}, color:'#c8a84b', callback: v => v+'€' }, grid:{ display:false } },
            x:  { ticks:{ font:{family:'Segoe UI',size:11}, color:'#6b7280' }, grid:{ display:false } }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
