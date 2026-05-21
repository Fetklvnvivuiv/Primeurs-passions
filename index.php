<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);
require_once __DIR__ . '/fonctions.php';
demarrerSession();
$pdo = getPDO();

try {
    $pdo->beginTransaction();
    $pdo->exec('SET TRANSACTION READ ONLY');
    $stmt = $pdo->query("
        SELECT c.libellecategorie, a.libellearticle, v.codevariete, v.nomvariete,
               v.calibre, v.quantitestock,
               MIN(p.prixkg) prix_min, MAX(p.prixkg) prix_max,
               STRING_AGG(DISTINCT p.nomtarif || ':' || p.prixkg::text, '|') AS tarifs
        FROM variete v
        JOIN article a ON a.idarticle = v.idarticle
        JOIN categorie c ON c.idcategorie = a.idcategorie
        LEFT JOIN prix p ON p.codevariete = v.codevariete AND p.datefin IS NULL
        GROUP BY c.libellecategorie, a.libellearticle, v.codevariete, v.nomvariete, v.calibre, v.quantitestock
        ORDER BY c.libellecategorie, a.libellearticle, v.nomvariete
    ");
    $varietes = $stmt->fetchAll();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $varietes = [];
    $erreurCatalogue = $e->getMessage();
}

$catalogue = [];
foreach ($varietes as $v) { $catalogue[$v['libellecategorie']][] = $v; }

$images = [
    'Pomme'  => 'https://images.unsplash.com/photo-1567306226416-28f0efdc88ce?w=800&q=85',
    'Banane' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=800&q=85',
    'Fraise' => 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=800&q=85',
    'Orange' => 'https://images.unsplash.com/photo-1547514701-42782101795e?w=800&q=85',
    'Citron' => 'https://images.unsplash.com/photo-1590502593747-42a996133562?w=800&q=85',
];
$catImages = [
    'Fruits'           => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=1200&q=80',
    'Fruits exotiques' => 'https://images.unsplash.com/photo-1526318896980-cf78c088247c?w=1200&q=80',
    'Fruits rouges'    => 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=1200&q=80',
    'Agrumes'          => 'https://images.unsplash.com/photo-1547514701-42782101795e?w=1200&q=80',
];
$catEmojis = [
    'Fruits'           => '🍎',
    'Fruits exotiques' => '🍌',
    'Fruits rouges'    => '🍓',
    'Agrumes'          => '🍊',
];

/* Passer toutes les données produits en JSON pour le JS */
$produitsJson = [];
foreach ($varietes as $v) {
    $tarifs = [];
    if ($v['tarifs']) {
        foreach (explode('|', $v['tarifs']) as $t) {
            [$nom, $prix] = explode(':', $t, 2);
            $tarifs[] = ['nom' => $nom, 'prix' => (float)$prix];
        }
        usort($tarifs, fn($a,$b) => $a['nom'] <=> $b['nom']);
    }
    $produitsJson[$v['codevariete']] = [
        'code'       => $v['codevariete'],
        'nom'        => $v['nomvariete'],
        'article'    => $v['libellearticle'],
        'categorie'  => $v['libellecategorie'],
        'calibre'    => $v['calibre'],
        'stock'      => (int)$v['quantitestock'],
        'prix_min'   => $v['prix_min'],
        'prix_max'   => $v['prix_max'],
        'tarifs'     => $tarifs,
        'image'      => $images[$v['libellearticle']] ?? 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=800&q=85',
    ];
}

$role = getRole();
include __DIR__ . '/includes/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════
     STYLES GLOBAUX DE LA PAGE D'ACCUEIL
     ═══════════════════════════════════════════════════════════ -->
<style>
/* Réinitialise le main-content pour cette page pleine largeur */
.main-content { padding: 0; max-width: 100%; margin: 0; }

/* ── HERO ────────────────────────────────────────────── */
.home-hero {
    position: relative; min-height: 88vh;
    display: flex; align-items: flex-end;
    overflow: hidden; background: #0a1f05;
}
.home-hero-bg {
    position: absolute; inset: 0;
    background-image: url('https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=1800&q=85');
    background-size: cover; background-position: center 40%;
    filter: brightness(0.55) saturate(1.2);
    transform: scale(1.03); transition: transform 10s ease;
}
.home-hero:hover .home-hero-bg { transform: scale(1.07); }
.home-hero-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to bottom,
        rgba(0,0,0,0.04) 0%,
        rgba(10,31,5,0.38) 45%,
        rgba(10,31,5,0.93) 100%);
}
.home-hero-content {
    position: relative; z-index: 2;
    max-width: 1280px; width: 100%;
    margin: 0 auto; padding: 64px 48px;
}
.hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(200,168,75,0.18);
    border: 1px solid rgba(200,168,75,0.45);
    color: #f0dfa0; padding: 6px 18px; border-radius: 999px;
    font-size: 0.76rem; font-weight: 700; letter-spacing: 1.2px;
    text-transform: uppercase; margin-bottom: 22px;
}
.hero-eyebrow::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: #c8a84b; animation: blink 2s ease infinite;
}
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.25;} }
.home-hero-content h1 {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: clamp(2.4rem, 5vw, 4rem); font-weight: 700;
    color: #fff; line-height: 1.1; margin-bottom: 18px;
    border: none; padding: 0; display: block; max-width: 620px;
}
.home-hero-content h1 em { font-style: normal; color: #f0dfa0; }
.home-hero-content > p {
    font-size: 1.05rem; color: rgba(255,255,255,0.70);
    max-width: 460px; margin-bottom: 34px; line-height: 1.75;
}
.hero-btns { display: flex; gap: 14px; flex-wrap: wrap; }
.btn-hm {
    background: #c8a84b; color: #1a3a0f;
    padding: 14px 30px; border-radius: 8px;
    font-weight: 700; font-size: 0.97rem;
    font-family: 'Segoe UI', Arial, sans-serif; text-decoration: none;
    transition: background 0.2s, transform 0.15s;
    display: inline-flex; align-items: center; gap: 8px;
}
.btn-hm:hover { background: #dfc05e; transform: translateY(-2px); color: #1a3a0f; text-decoration: none; }
.btn-hs {
    background: transparent; color: #fff;
    padding: 14px 30px; border-radius: 8px;
    font-weight: 600; font-size: 0.97rem;
    font-family: 'Segoe UI', Arial, sans-serif; text-decoration: none;
    border: 1.5px solid rgba(255,255,255,0.38);
    transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
}
.btn-hs:hover { background: rgba(255,255,255,0.10); color: #fff; text-decoration: none; }
.hero-stats {
    display: flex; gap: 40px; margin-top: 52px;
    padding-top: 28px; border-top: 1px solid rgba(255,255,255,0.10); flex-wrap: wrap;
}
.hs-val {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 2rem; font-weight: 700; color: #f0dfa0; line-height: 1;
}
.hs-lbl {
    font-size: 0.76rem; color: rgba(255,255,255,0.45);
    font-weight: 500; text-transform: uppercase;
    letter-spacing: 0.6px; margin-top: 4px;
}

/* ── BANDE ARGUMENTS ─────────────────────────────────── */
.why-band { background: #1a3a0f; padding: 62px 48px; position: relative; overflow: hidden; }
.why-band::before {
    content: ''; position: absolute; inset: 0;
    background: url('https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=600&q=10') center/cover;
    opacity: 0.05;
}
.why-inner {
    position: relative; max-width: 1280px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(4,1fr); gap: 28px; text-align: center;
}
.why-icon  { font-size: 2rem; margin-bottom: 12px; display: block; }
.why-title { font-family: Georgia, 'Times New Roman', serif; font-size: 1rem; font-weight: 700; color: #f0dfa0; margin-bottom: 8px; }
.why-text  { font-size: 0.82rem; color: rgba(255,255,255,0.48); line-height: 1.65; }

/* ── CATALOGUE SECTION ──────────────────────────────── */
.cat-wrap { max-width: 1280px; margin: 0 auto; padding: 72px 48px; }
.cat-intro { text-align: center; margin-bottom: 52px; }
.cat-intro .pill {
    display: inline-block; background: #e8f5e1; color: #2a5c18;
    padding: 4px 14px; border-radius: 999px; font-size: 0.74rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 14px;
}
.cat-intro h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 2.3rem; color: #1a3a0f; margin: 0 0 10px; }
.cat-intro p  { font-size: 0.95rem; color: #6b7280; max-width: 420px; margin: 0 auto; }

/* Filtre catégories */
.cat-filter {
    display: flex; gap: 10px; flex-wrap: wrap;
    justify-content: center; margin-bottom: 42px;
}
.cat-filter-btn {
    padding: 8px 20px; border-radius: 999px;
    border: 1.5px solid #d4d8cc; background: #fff;
    font-family: 'Segoe UI', Arial, sans-serif; font-size: 0.88rem; font-weight: 600;
    color: #5f6b4e; cursor: pointer; transition: all 0.18s;
}
.cat-filter-btn:hover { border-color: #4a7c2f; color: #2a5c18; background: #f0f7e8; }
.cat-filter-btn.active { background: #2d5a1b; color: #fff; border-color: #2d5a1b; }

/* En-tête catégorie */
.cat-block { margin-bottom: 60px; }
.cat-hdr {
    position: relative; border-radius: 18px;
    overflow: hidden; height: 180px; margin-bottom: 24px;
    display: flex; align-items: flex-end;
}
.cat-hdr-bg {
    position: absolute; inset: 0;
    background-size: cover; background-position: center;
    filter: brightness(0.58) saturate(1.1);
    transition: transform 0.6s ease;
}
.cat-block:hover .cat-hdr-bg { transform: scale(1.04); }
.cat-hdr-grad {
    position: absolute; inset: 0;
    background: linear-gradient(to right, rgba(0,0,0,0.72) 0%, rgba(0,0,0,0.05) 100%);
}
.cat-hdr-txt { position: relative; z-index: 2; padding: 24px 28px; }
.cat-hdr-txt h3 {
    font-family: Georgia, 'Times New Roman', serif; font-size: 1.65rem;
    font-weight: 700; color: #fff; margin: 0 0 4px;
}
.cat-hdr-txt span { font-size: 0.80rem; color: rgba(255,255,255,0.58); }

/* Grille produits */
.prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 22px; }

/* ── CARTE PRODUIT ────────────────────────────────────── */
.prod-card {
    background: #fff; border-radius: 14px; overflow: hidden;
    border: 1px solid #e5e8e0;
    transition: box-shadow 0.25s, transform 0.25s;
    display: flex; flex-direction: column;
    cursor: pointer; position: relative;
}
.prod-card:hover { box-shadow: 0 14px 44px rgba(26,58,15,0.14); transform: translateY(-5px); }
.prod-card:focus-visible { outline: 3px solid #4a7c2f; outline-offset: 2px; }

/* Hint "Cliquer" */
.prod-card::after {
    content: 'Voir le détail';
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(26,58,15,0.92), rgba(26,58,15,0));
    color: #f0dfa0; text-align: center; padding: 28px 0 12px;
    font-size: 0.80rem; font-weight: 700; letter-spacing: 0.5px;
    text-transform: uppercase; opacity: 0;
    transition: opacity 0.25s; pointer-events: none;
    font-family: 'Segoe UI', Arial, sans-serif;
}
.prod-card:hover::after { opacity: 1; }

.prod-img {
    position: relative; height: 185px;
    overflow: hidden; background: #f0f4ea;
}
.prod-img img {
    width: 100%; height: 100%; object-fit: cover;
    transition: transform 0.5s ease;
}
.prod-card:hover .prod-img img { transform: scale(1.08); }

.stock-badge {
    position: absolute; top: 10px; right: 10px;
    padding: 4px 10px; border-radius: 999px;
    font-size: 0.69rem; font-weight: 700; letter-spacing: 0.3px;
}
.sb-ok   { background: rgba(209,250,229,0.95); color: #065f46; }
.sb-low  { background: rgba(254,243,199,0.95); color: #92400e; }
.sb-none { background: rgba(254,226,226,0.95); color: #7f1d1d; }

.prod-body { padding: 18px; flex: 1; display: flex; flex-direction: column; }
.prod-cat-lbl {
    font-size: 0.69rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.6px;
    color: #4a7c2f; margin-bottom: 5px;
}
.prod-name {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.08rem; font-weight: 700;
    color: #1a3a0f; margin-bottom: 4px; line-height: 1.25;
}
.prod-sub { font-size: 0.81rem; color: #6b7280; margin-bottom: 14px; flex: 1; }
.prod-foot {
    display: flex; align-items: flex-end;
    justify-content: space-between;
    padding-top: 12px; border-top: 1px solid #f0f2ec; margin-top: auto;
}
.prod-price {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.22rem; font-weight: 700;
    color: #c8a84b; line-height: 1;
}
.prod-price small {
    font-size: 0.66rem; font-weight: 400;
    color: #9ca3af; font-family: 'Segoe UI', Arial, sans-serif;
    display: block; margin-top: 2px;
}
.prod-stk { font-size: 0.79rem; font-weight: 700; text-align: right; }

/* ── MODAL OVERLAY ───────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(10,31,5,0.72);
    backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    opacity: 0; pointer-events: none;
    transition: opacity 0.28s cubic-bezier(0.4,0,0.2,1);
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal-box {
    background: #fff; border-radius: 20px;
    max-width: 760px; width: 100%;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 24px 80px rgba(0,0,0,0.30);
    transform: translateY(24px) scale(0.97);
    transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
    position: relative;
}
.modal-overlay.open .modal-box { transform: translateY(0) scale(1); }

.modal-close {
    position: absolute; top: 16px; right: 16px; z-index: 10;
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,0.90); border: 1px solid #e5e8e0;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 1.1rem; color: #3d3d3d;
    transition: background 0.15s, transform 0.15s;
    font-family: sans-serif; font-weight: 300; line-height: 1;
}
.modal-close:hover { background: #fff; transform: scale(1.1); }

/* Image en haut du modal */
.modal-img-wrap {
    height: 280px; position: relative; overflow: hidden;
    border-radius: 20px 20px 0 0;
}
.modal-img-wrap img {
    width: 100%; height: 100%; object-fit: cover;
    transition: transform 0.5s ease;
}
.modal-img-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top,
        rgba(10,31,5,0.80) 0%,
        rgba(0,0,0,0) 55%);
}
.modal-img-info {
    position: absolute; bottom: 0; left: 0; right: 0;
    padding: 28px 32px 22px;
}
.modal-img-info .m-cat {
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: #f0dfa0; margin-bottom: 6px;
}
.modal-img-info h2 {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.85rem; font-weight: 700;
    color: #fff; margin: 0; line-height: 1.15;
}

/* Corps du modal */
.modal-body { padding: 28px 32px 32px; }

/* Grille infos */
.modal-infos {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 14px; margin-bottom: 26px;
}
.modal-info-item {
    background: #f3faf0; border-radius: 10px;
    padding: 14px 16px; border: 1px solid #e0ecda;
}
.modal-info-lbl {
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: #6b7280; margin-bottom: 4px;
}
.modal-info-val {
    font-size: 1rem; font-weight: 700; color: #1a3a0f;
    font-family: Georgia, 'Times New Roman', serif;
}

/* Badge stock modal */
.modal-stock-bar { margin-bottom: 26px; }
.modal-stock-label {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 8px;
}
.modal-stock-label span { font-size: 0.83rem; font-weight: 600; color: #3d3d3d; }
.modal-stock-label strong { font-size: 0.83rem; font-weight: 700; }
.modal-stock-track {
    height: 8px; background: #e8f5e1; border-radius: 999px; overflow: hidden;
}
.modal-stock-fill {
    height: 100%; border-radius: 999px;
    transition: width 0.6s cubic-bezier(0.4,0,0.2,1);
}

/* Tarifs */
.modal-tarifs-title {
    font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.6px;
    color: #6b7280; margin-bottom: 12px;
}
.modal-tarifs-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 28px;
}
.tarif-chip {
    display: flex; justify-content: space-between; align-items: center;
    background: #fdf8ec; border: 1px solid #f0dfa0; border-radius: 8px;
    padding: 10px 14px;
}
.tarif-nom { font-size: 0.83rem; color: #5f4a1a; font-weight: 600; }
.tarif-prix {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1rem; font-weight: 700; color: #c8a84b;
}

/* Bouton commander dans modal */
.modal-cta {
    display: flex; gap: 12px; flex-wrap: wrap;
    padding-top: 18px; border-top: 1px solid #f0f2ec;
}
.btn-modal-order {
    flex: 1; min-width: 180px;
    background: #2d5a1b; color: #fff;
    padding: 13px 24px; border-radius: 9px;
    font-weight: 700; font-size: 0.95rem;
    font-family: 'Segoe UI', Arial, sans-serif; text-decoration: none;
    transition: background 0.18s, transform 0.12s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-modal-order:hover { background: #1a3a0f; color: #fff; text-decoration: none; transform: translateY(-1px); }
.btn-modal-close2 {
    padding: 13px 22px; border-radius: 9px;
    border: 1.5px solid #d4d8cc; background: #fff;
    color: #5f6b4e; font-weight: 600; font-size: 0.95rem;
    font-family: 'Segoe UI', Arial, sans-serif; cursor: pointer;
    transition: all 0.18s;
}
.btn-modal-close2:hover { background: #f0f7e8; border-color: #4a7c2f; }

/* ── CTA BAS DE PAGE ─────────────────────────────────── */
.cta-band {
    padding: 72px 48px; text-align: center;
    background: linear-gradient(135deg, #f3faf0 0%, #e8f5e1 100%);
}
.cta-band h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 1.9rem; color: #1a3a0f; margin-bottom: 10px; }
.cta-band p  { font-size: 0.97rem; color: #6b7280; margin-bottom: 26px; }

/* ── RESPONSIVE ──────────────────────────────────────── */
@media (max-width: 900px) {
    .home-hero-content { padding: 36px 20px; }
    .why-band { padding: 48px 20px; }
    .why-inner { grid-template-columns: 1fr 1fr; gap: 22px; }
    .cat-wrap { padding: 48px 20px; }
    .prod-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
    .modal-infos { grid-template-columns: 1fr 1fr; }
    .cta-band { padding: 48px 20px; }
}
@media (max-width: 600px) {
    .home-hero-content h1 { font-size: 2rem; }
    .hero-stats { gap: 22px; }
    .prod-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    .prod-img { height: 140px; }
    .why-inner { grid-template-columns: 1fr 1fr; }
    .modal-box { border-radius: 14px; }
    .modal-img-wrap { height: 220px; border-radius: 14px 14px 0 0; }
    .modal-body { padding: 20px; }
    .modal-img-info { padding: 20px; }
    .modal-img-info h2 { font-size: 1.5rem; }
    .modal-infos { grid-template-columns: 1fr 1fr; }
    .modal-tarifs-grid { grid-template-columns: 1fr; }
}
@media (max-width: 400px) {
    .prod-grid { grid-template-columns: 1fr; }
    .modal-infos { grid-template-columns: 1fr; }
}
</style>

<!-- ═══════════════════════════════════════════════════════════
     HERO
     ═══════════════════════════════════════════════════════════ -->
<section class="home-hero">
    <div class="home-hero-bg"></div>
    <div class="home-hero-overlay"></div>
    <div class="home-hero-content">
        <div class="hero-eyebrow">Catalogue 2025 — Fruits frais</div>
        <h1>Des fruits d'exception,<br><em>livrés avec soin</em></h1>
        <p>FruitsPro accompagne les professionnels de la restauration, boulangerie et traiteur avec une sélection rigoureuse de fruits frais.</p>
        <div class="hero-btns">
            <?php if (!estConnecte()): ?>
                <a href="<?= h(url('/connexion.php')) ?>" class="btn-hm">Se connecter →</a>
                <a href="#catalogue" class="btn-hs">↓ Voir le catalogue</a>
            <?php elseif ($role === 'client'): ?>
                <a href="<?= h(url('/pages/nouvelle_commande.php')) ?>" class="btn-hm">Commander →</a>
                <a href="<?= h(url('/pages/espace_client.php')) ?>" class="btn-hs">Mon espace</a>
            <?php else: ?>
                <a href="<?= h(url('/pages/dashboard.php')) ?>" class="btn-hm">Dashboard →</a>
                <a href="#catalogue" class="btn-hs">↓ Catalogue</a>
            <?php endif; ?>
        </div>
        <?php
            $nbTotal  = count($varietes);
            $nbCats   = count($catalogue);
            $nbStock  = count(array_filter($varietes, fn($v) => (int)$v['quantitestock'] > 0));
        ?>
        <div class="hero-stats">
            <div><div class="hs-val"><?= $nbTotal ?>+</div><div class="hs-lbl">Variétés</div></div>
            <div><div class="hs-val"><?= $nbCats ?></div><div class="hs-lbl">Catégories</div></div>
            <div><div class="hs-val"><?= $nbStock ?></div><div class="hs-lbl">En stock</div></div>
            <div><div class="hs-val">5</div><div class="hs-lbl">Tarifs pro</div></div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     ARGUMENTS
     ═══════════════════════════════════════════════════════════ -->
<section class="why-band">
    <div class="why-inner">
        <div><span class="why-icon">🌿</span><div class="why-title">Fraîcheur garantie</div><p class="why-text">Fruits sélectionnés à maturité optimale et livrés rapidement.</p></div>
        <div><span class="why-icon">📦</span><div class="why-title">Livraison pro</div><p class="why-text">Standard, express ou retrait dépôt selon vos besoins.</p></div>
        <div><span class="why-icon">💰</span><div class="why-title">Tarifs adaptés</div><p class="why-text">Grilles de prix dédiées par type d'établissement.</p></div>
        <div><span class="why-icon">🔒</span><div class="why-title">Espace sécurisé</div><p class="why-text">Suivi de vos commandes en temps réel depuis votre espace.</p></div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     CATALOGUE
     ═══════════════════════════════════════════════════════════ -->
<section class="cat-wrap" id="catalogue">
    <div class="cat-intro">
        <span class="pill">Notre sélection</span>
        <h2>Catalogue des produits</h2>
        <p>Cliquez sur une carte pour voir le détail, les tarifs et commander.</p>
    </div>

    <!-- Filtres catégories -->
    <div class="cat-filter">
        <button class="cat-filter-btn active" data-cat="all">Tout voir</button>
        <?php foreach (array_keys($catalogue) as $cat): ?>
            <button class="cat-filter-btn" data-cat="<?= h($cat) ?>">
                <?= ($catEmojis[$cat] ?? '') . ' ' . h($cat) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($erreurCatalogue)): ?>
        <div class="msg msg-erreur" style="max-width:600px;margin:0 auto;">❌ <?= h($erreurCatalogue) ?></div>
    <?php elseif (empty($catalogue)): ?>
        <div style="text-align:center;padding:48px;color:#6b7280;"><div style="font-size:3rem;margin-bottom:12px;">🌿</div><p>Aucun article disponible.</p></div>
    <?php else: ?>
        <?php foreach ($catalogue as $cat => $items):
            $bgImg = $catImages[$cat] ?? 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=1200&q=80';
        ?>
        <div class="cat-block" data-cat-block="<?= h($cat) ?>">
            <!-- En-tête catégorie -->
            <div class="cat-hdr">
                <div class="cat-hdr-bg" style="background-image:url('<?= h($bgImg) ?>');"></div>
                <div class="cat-hdr-grad"></div>
                <div class="cat-hdr-txt">
                    <h3><?= ($catEmojis[$cat] ?? '') . ' ' . h($cat) ?></h3>
                    <span><?= count($items) ?> variété<?= count($items) > 1 ? 's' : '' ?></span>
                </div>
            </div>

            <!-- Grille produits -->
            <div class="prod-grid">
                <?php foreach ($items as $art):
                    $stock  = (int)$art['quantitestock'];
                    $imgUrl = $images[$art['libellearticle']] ?? 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=800&q=85';
                    if ($stock <= 0)     { $sbCls='sb-none'; $sbTxt='Rupture';      $sc='#7f1d1d'; $sv='0 u.'; }
                    elseif ($stock < 50) { $sbCls='sb-low';  $sbTxt='Stock limité'; $sc='#92400e'; $sv=$stock.' u.'; }
                    else                 { $sbCls='sb-ok';   $sbTxt='En stock';     $sc='#065f46'; $sv=$stock.' u.'; }
                ?>
                <div class="prod-card"
                     role="button"
                     tabindex="0"
                     aria-label="Voir le détail de <?= h($art['nomvariete']) ?>"
                     data-code="<?= h($art['codevariete']) ?>"
                     onclick="openModal('<?= h($art['codevariete']) ?>')"
                     onkeydown="if(event.key==='Enter'||event.key===' ')openModal('<?= h($art['codevariete']) ?>')">
                    <div class="prod-img">
                        <img src="<?= h($imgUrl) ?>"
                             alt="<?= h($art['libellearticle'].' — '.$art['nomvariete']) ?>"
                             loading="lazy"
                             onerror="this.style.display='none';this.parentElement.style.background='#e8f5e1';">
                        <span class="stock-badge <?= $sbCls ?>"><?= $sbTxt ?></span>
                    </div>
                    <div class="prod-body">
                        <div class="prod-cat-lbl"><?= h($art['libellearticle']) ?></div>
                        <div class="prod-name"><?= h($art['nomvariete']) ?></div>
                        <div class="prod-sub">Code : <?= h($art['codevariete']) ?> &nbsp;·&nbsp; Calibre : <?= h($art['calibre']) ?></div>
                        <div class="prod-foot">
                            <div class="prod-price">
                                <?php if ($art['prix_min'] !== null): ?>
                                    <?= formatEuro($art['prix_min']) ?>
                                    <?php if ($art['prix_min'] != $art['prix_max']): ?>
                                        <span style="font-size:0.80rem;font-weight:400;color:#9ca3af;font-family:'Segoe UI',sans-serif;"> – <?= formatEuro($art['prix_max']) ?></span>
                                    <?php endif; ?>
                                    <small>/kg</small>
                                <?php else: ?>
                                    <span style="font-size:0.80rem;color:#9ca3af;font-family:'Segoe UI',sans-serif;">Sur demande</span>
                                <?php endif; ?>
                            </div>
                            <div class="prod-stk" style="color:<?= $sc ?>;"><?= $sv ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- ═══════════════════════════════════════════════════════════
     CTA BAS
     ═══════════════════════════════════════════════════════════ -->
<?php if (!estConnecte()): ?>
<section class="cta-band">
    <h2>Prêt à commander ?</h2>
    <p>Connectez-vous pour accéder à vos tarifs professionnels personnalisés.</p>
    <a href="<?= h(url('/connexion.php')) ?>" class="btn-hm" style="display:inline-flex;">Accéder à mon espace →</a>
</section>
<?php elseif ($role === 'client'): ?>
<section class="cta-band">
    <h2>Vous avez trouvé ce qu'il vous faut ?</h2>
    <p>Passez votre commande directement depuis votre espace client.</p>
    <a href="<?= h(url('/pages/nouvelle_commande.php')) ?>" class="btn-hm" style="display:inline-flex;">Commander maintenant →</a>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════
     MODAL (structure HTML vide, remplie par JS)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="productModal" role="dialog" aria-modal="true" aria-label="Détail produit">
    <div class="modal-box" id="modalBox">
        <button class="modal-close" onclick="closeModal()" aria-label="Fermer">✕</button>

        <div class="modal-img-wrap">
            <img id="mImg" src="" alt="">
            <div class="modal-img-overlay"></div>
            <div class="modal-img-info">
                <div class="m-cat" id="mCat"></div>
                <h2 id="mNom"></h2>
            </div>
        </div>

        <div class="modal-body">
            <!-- Infos clés -->
            <div class="modal-infos">
                <div class="modal-info-item">
                    <div class="modal-info-lbl">Code variété</div>
                    <div class="modal-info-val" id="mCode"></div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-lbl">Calibre</div>
                    <div class="modal-info-val" id="mCalibre"></div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-lbl">Catégorie</div>
                    <div class="modal-info-val" id="mCategorie"></div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-lbl">Article</div>
                    <div class="modal-info-val" id="mArticle"></div>
                </div>
            </div>

            <!-- Barre de stock -->
            <div class="modal-stock-bar">
                <div class="modal-stock-label">
                    <span>Disponibilité en stock</span>
                    <strong id="mStockLabel"></strong>
                </div>
                <div class="modal-stock-track">
                    <div class="modal-stock-fill" id="mStockFill" style="width:0%;background:#4a7c2f;"></div>
                </div>
            </div>

            <!-- Tarifs -->
            <div id="mTarifsSection">
                <div class="modal-tarifs-title">Tarifs par type de client</div>
                <div class="modal-tarifs-grid" id="mTarifsGrid"></div>
            </div>

            <!-- Boutons -->
            <div class="modal-cta">
                <?php if ($role === 'client'): ?>
                    <a id="mOrderBtn" href="<?= h(url('/pages/nouvelle_commande.php')) ?>" class="btn-modal-order">
                        🛒 Commander ce produit
                    </a>
                <?php elseif ($role === 'televente' || $role === 'admin'): ?>
                    <a id="mOrderBtn" href="<?= h(url('/pages/commandes_televente.php')) ?>" class="btn-modal-order">
                        ☎️ Saisir une commande
                    </a>
                <?php else: ?>
                    <a id="mOrderBtn" href="<?= h(url('/connexion.php')) ?>" class="btn-modal-order">
                        🔑 Se connecter pour commander
                    </a>
                <?php endif; ?>
                <button class="btn-modal-close2" onclick="closeModal()">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     DONNÉES PRODUITS + JAVASCRIPT
     ═══════════════════════════════════════════════════════════ -->
<script>
/* Données injectées depuis PHP */
const PRODUITS = <?= json_encode($produitsJson, JSON_UNESCAPED_UNICODE) ?>;
const URL_COMMANDER = "<?= h(url('/pages/nouvelle_commande.php')) ?>";

/* ── Formatage euro ── */
function euro(v) {
    return new Intl.NumberFormat('fr-FR', {style:'currency', currency:'EUR'}).format(v);
}

/* ── Ouvrir le modal ── */
function openModal(code) {
    const p = PRODUITS[code];
    if (!p) return;

    /* Image */
    const img = document.getElementById('mImg');
    img.src = p.image;
    img.alt = p.nom;

    /* Textes header */
    document.getElementById('mCat').textContent  = p.article + ' — ' + p.categorie;
    document.getElementById('mNom').textContent  = p.nom;

    /* Infos */
    document.getElementById('mCode').textContent     = p.code;
    document.getElementById('mCalibre').textContent  = p.calibre;
    document.getElementById('mCategorie').textContent = p.categorie;
    document.getElementById('mArticle').textContent  = p.article;

    /* Stock */
    const stock  = p.stock;
    const maxRef = 500;
    const pct    = Math.min(100, Math.round(stock / maxRef * 100));
    let stockColor, stockLabel;
    if (stock <= 0)      { stockColor = '#dc2626'; stockLabel = 'Rupture de stock (0 unité)'; }
    else if (stock < 50) { stockColor = '#d97706'; stockLabel = 'Stock limité (' + stock + ' unités)'; }
    else                 { stockColor = '#16a34a'; stockLabel = 'En stock (' + stock + ' unités)'; }

    document.getElementById('mStockLabel').textContent  = stockLabel;
    document.getElementById('mStockLabel').style.color  = stockColor;
    const fill = document.getElementById('mStockFill');
    fill.style.background = stockColor;
    /* reset puis animer */
    fill.style.width = '0%';
    setTimeout(() => { fill.style.width = pct + '%'; }, 60);

    /* Tarifs */
    const grid = document.getElementById('mTarifsGrid');
    grid.innerHTML = '';
    if (p.tarifs && p.tarifs.length > 0) {
        document.getElementById('mTarifsSection').style.display = '';
        p.tarifs.forEach(t => {
            const chip = document.createElement('div');
            chip.className = 'tarif-chip';
            chip.innerHTML = `<span class="tarif-nom">${t.nom}</span><span class="tarif-prix">${euro(t.prix)}/kg</span>`;
            grid.appendChild(chip);
        });
    } else {
        document.getElementById('mTarifsSection').style.display = 'none';
    }

    /* Ouvrir overlay */
    const overlay = document.getElementById('productModal');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    /* Mettre à jour le lien commander avec pré-sélection variété */
    const btnOrder = document.getElementById('mOrderBtn');
    if (btnOrder) {
        const base = btnOrder.href.split('?')[0];
        btnOrder.href = base + '?variete=' + encodeURIComponent(code);
    }

    /* Focus trap */
    setTimeout(() => {
        document.querySelector('.modal-close').focus();
    }, 30);
}

/* ── Fermer le modal ── */
function closeModal() {
    document.getElementById('productModal').classList.remove('open');
    document.body.style.overflow = '';
}

/* Clic sur l'overlay (hors boîte) */
document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/* Touche Escape */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

/* ── Filtres catégories ── */
document.querySelectorAll('.cat-filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const cat = this.dataset.cat;

        /* Activer le bouton */
        document.querySelectorAll('.cat-filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        /* Afficher/masquer les blocs */
        document.querySelectorAll('.cat-block').forEach(block => {
            if (cat === 'all' || block.dataset.catBlock === cat) {
                block.style.display = '';
                block.style.animation = 'fadeIn 0.3s ease';
            } else {
                block.style.display = 'none';
            }
        });

        /* Scroll doux vers le catalogue */
        if (cat !== 'all') {
            document.getElementById('catalogue').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

/* Animation fade */
const style = document.createElement('style');
style.textContent = '@keyframes fadeIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;}}';
document.head.appendChild(style);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
