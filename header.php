<?php
require_once __DIR__ . '/../fonctions.php';
demarrerSession();
$role = getRole();
$rolesLabels = [
    'client'       => ['label' => 'Client',       'icon' => '👤'],
    'televente'    => ['label' => 'Télévente',    'icon' => '📞'],
    'preparateur'  => ['label' => 'Préparateur',  'icon' => '📦'],
    'gestionnaire' => ['label' => 'Gestionnaire', 'icon' => '📊'],
    'admin'        => ['label' => 'Admin',         'icon' => '⚙️'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(url('/css/style.css')) ?>">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a class="logo" href="<?= h(url('/index.php')) ?>">
            <span class="logo-icon">🍊</span>
            Fruits<span>Pro</span>
        </a>

        <nav class="main-nav">
            <?php if ($role === 'visiteur'): ?>
                <a href="<?= h(url('/index.php')) ?>">Accueil</a>
                <a href="<?= h(url('/connexion.php')) ?>" class="btn-nav">Se connecter</a>

            <?php elseif ($role === 'client'): ?>
                <a href="<?= h(url('/index.php')) ?>">🍋 Catalogue</a>
                <a href="<?= h(url('/pages/espace_client.php')) ?>">Mon espace</a>
                <a href="<?= h(url('/pages/nouvelle_commande.php')) ?>">Commander</a>
                <a href="<?= h(url('/pages/mes_commandes.php')) ?>">Mes commandes</a>
                <span class="nav-role-badge">Client</span>
                <a href="<?= h(url('/deconnexion.php')) ?>" class="btn-nav btn-deconnexion">Déconnexion</a>

            <?php elseif ($role === 'televente'): ?>
                <a href="<?= h(url('/pages/dashboard.php')) ?>">Dashboard</a>
                <a href="<?= h(url('/pages/commandes_televente.php')) ?>">Saisie</a>
                <a href="<?= h(url('/index.php')) ?>">Catalogue</a>
                <span class="nav-role-badge">📞 Télévente</span>
                <a href="<?= h(url('/deconnexion.php')) ?>" class="btn-nav btn-deconnexion">Déconnexion</a>

            <?php elseif ($role === 'preparateur'): ?>
                <a href="<?= h(url('/pages/dashboard.php')) ?>">Dashboard</a>
                <a href="<?= h(url('/pages/commandes_preparer.php')) ?>">À préparer</a>
                <span class="nav-role-badge">📦 Préparateur</span>
                <a href="<?= h(url('/deconnexion.php')) ?>" class="btn-nav btn-deconnexion">Déconnexion</a>

            <?php elseif ($role === 'gestionnaire'): ?>
                <a href="<?= h(url('/pages/dashboard.php')) ?>">Dashboard</a>
                <a href="<?= h(url('/pages/stocks_prix.php')) ?>">Stocks & Prix</a>
                <a href="<?= h(url('/pages/facturation.php')) ?>">Factures</a>
                <span class="nav-role-badge">📊 Gestionnaire</span>
                <a href="<?= h(url('/deconnexion.php')) ?>" class="btn-nav btn-deconnexion">Déconnexion</a>

            <?php elseif ($role === 'admin'): ?>
                <a href="<?= h(url('/pages/dashboard.php')) ?>">Dashboard</a>
                <a href="<?= h(url('/pages/commandes_televente.php')) ?>">Saisie</a>
                <a href="<?= h(url('/pages/commandes_preparer.php')) ?>">Préparation</a>
                <a href="<?= h(url('/pages/stocks_prix.php')) ?>">Stocks</a>
                <a href="<?= h(url('/pages/facturation.php')) ?>">Factures</a>
                <a href="<?= h(url('/admin/administration.php')) ?>">Admin</a>
                <span class="nav-role-badge">⚙️ Admin</span>
                <a href="<?= h(url('/deconnexion.php')) ?>" class="btn-nav btn-deconnexion">Déconnexion</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="main-content">
