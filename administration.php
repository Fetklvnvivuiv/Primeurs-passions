<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['admin']);
$pdo          = getPDO();
$message      = '';
$erreur       = '';
$rolesValides = ['client','televente','preparateur','gestionnaire','admin'];
$roleIcons    = ['client'=>'👤','televente'=>'📞','preparateur'=>'📦','gestionnaire'=>'📊','admin'=>'⚙️'];
$roleColors   = ['client'=>'#3b82f6','televente'=>'#8b5cf6','preparateur'=>'#f59e0b','gestionnaire'=>'#10b981','admin'=>'#ef4444'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'creer') {
            $login       = trim($_POST['login']       ?? '');
            $mdp         = $_POST['mdp']              ?? '';
            $role        = $_POST['role']             ?? '';
            $codeClient  = trim($_POST['codeclient']  ?? '');
            $idPersonnel = (int)($_POST['idpersonnel'] ?? 0);

            if ($login === '' || $mdp === '' || !in_array($role, $rolesValides, true))
                throw new Exception('Login, mot de passe et rôle valide sont obligatoires.');
            if (strlen($mdp) < 6)
                throw new Exception('Le mot de passe doit contenir au moins 6 caractères.');
            if ($role === 'client' && $codeClient === '')
                throw new Exception('Un utilisateur client doit être lié à un code client.');

            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO utilisateurs_site(login, mot_de_passe, role, actif, codeclient, idpersonnel) VALUES (:l,:m,:r,true,:c,:p)')
                ->execute([':l'=>$login,':m'=>$hash,':r'=>$role,':c'=>$codeClient?:null,':p'=>$idPersonnel?:null]);
            $message = 'Utilisateur "' . h($login) . '" créé avec le rôle ' . h($role) . '.';

        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id_utilisateur'] ?? 0);
            if ($id === (int)$_SESSION['utilisateur_id'])
                throw new Exception('Vous ne pouvez pas désactiver votre propre compte.');
            $pdo->prepare('UPDATE utilisateurs_site SET actif = NOT actif WHERE id_utilisateur = :id')->execute([':id'=>$id]);
            $message = 'Statut mis à jour.';

        } elseif ($action === 'role') {
            $id   = (int)($_POST['id_utilisateur'] ?? 0);
            $role = $_POST['nouveau_role'] ?? '';
            if ($id === (int)$_SESSION['utilisateur_id'])
                throw new Exception('Vous ne pouvez pas modifier votre propre rôle.');
            if (!in_array($role, $rolesValides, true))
                throw new Exception('Rôle invalide.');
            $pdo->prepare('UPDATE utilisateurs_site SET role = :r WHERE id_utilisateur = :id')->execute([':r'=>$role,':id'=>$id]);
            $message = 'Rôle modifié en "' . h($role) . '".';
        }
    } catch (Throwable $e) { $erreur = $e->getMessage(); }
}

$utilisateurs = $pdo->query("
    SELECT u.*, c.raisonsociale, p.nom, p.prenom
    FROM utilisateurs_site u
    LEFT JOIN client c ON c.codeclient = u.codeclient
    LEFT JOIN personnel p ON p.idpersonnel = u.idpersonnel
    ORDER BY u.role, u.login
")->fetchAll();

/* Stats par rôle */
$statsRoles = [];
foreach ($rolesValides as $r) {
    $nb = count(array_filter($utilisateurs, fn($u) => $u['role'] === $r));
    $nbActifs = count(array_filter($utilisateurs, fn($u) => $u['role'] === $r && $u['actif']));
    $statsRoles[$r] = ['total' => $nb, 'actifs' => $nbActifs];
}

$clients    = $pdo->query('SELECT codeclient, raisonsociale FROM client ORDER BY raisonsociale')->fetchAll();
$personnels = $pdo->query("SELECT p.idpersonnel, p.nom, p.prenom, e.fonction FROM personnel p LEFT JOIN employe e ON e.idpersonnel = p.idpersonnel ORDER BY p.nom, p.prenom")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
.admin-layout { display: grid; grid-template-columns: 340px 1fr; gap: 28px; align-items: start; }
.role-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 28px; }
.role-stat-chip {
    border-radius: 10px; padding: 14px 16px;
    border: 1px solid #e5e8e0; background: #fff;
    display: flex; align-items: center; gap: 12px;
}
.role-stat-icon { font-size: 1.4rem; }
.role-stat-info .rs-lbl { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; }
.role-stat-info .rs-val { font-family: Georgia,'Times New Roman',serif; font-size: 1.1rem; font-weight: 700; color: #1a3a0f; }
.role-stat-info .rs-sub { font-size: 0.72rem; color: #9ca3af; }

.user-row { display: grid; grid-template-columns: 32px 1fr auto auto auto; gap: 12px; align-items: center; padding: 14px 18px; border-bottom: 1px solid #f0f2ec; transition: background 0.15s; }
.user-row:last-child { border-bottom: none; }
.user-row:hover { background: #f9fdf4; }
.user-avatar { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.80rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.user-info-name { font-weight: 700; color: #1a3a0f; font-size: 0.90rem; }
.user-info-sub  { font-size: 0.75rem; color: #6b7280; margin-top: 1px; }
.role-pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.actif-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.form-actions { display: flex; flex-direction: column; gap: 12px; }
.inline-actions { display: flex; gap: 6px; align-items: center; }
.inline-actions select { font-size: 0.80rem; padding: 5px 8px; border: 1.5px solid #d4d8cc; border-radius: 6px; font-family: 'Segoe UI',sans-serif; }

@media(max-width:1000px){ .admin-layout { grid-template-columns: 1fr; } }
@media(max-width:600px){ .role-stats { grid-template-columns: 1fr 1fr; } .user-row { grid-template-columns: 28px 1fr auto; } }
</style>

<div class="section-header">
    <div>
        <h1>⚙️ Administration</h1>
        <p><?= count($utilisateurs) ?> utilisateur<?= count($utilisateurs) > 1 ? 's' : '' ?> · <?= count(array_filter($utilisateurs, fn($u) => $u['actif'])) ?> actif<?= count(array_filter($utilisateurs, fn($u) => $u['actif'])) > 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= h(url('/pages/dashboard.php')) ?>" class="btn btn-secondary btn-sm">← Dashboard</a>
</div>

<?php if ($message): echo msgSucces($message); endif; ?>
<?php if ($erreur):  echo msgErreur($erreur);  endif; ?>

<!-- Stats par rôle -->
<div class="role-stats">
    <?php foreach ($rolesValides as $r): $s = $statsRoles[$r]; ?>
    <div class="role-stat-chip">
        <div class="role-stat-icon"><?= $roleIcons[$r] ?></div>
        <div class="role-stat-info">
            <div class="rs-lbl"><?= ucfirst($r) ?></div>
            <div class="rs-val"><?= $s['total'] ?></div>
            <div class="rs-sub"><?= $s['actifs'] ?> actif<?= $s['actifs'] > 1 ? 's' : '' ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="admin-layout">

    <!-- Formulaire création -->
    <div class="card" style="padding:24px;">
        <h2 style="margin-top:0;margin-bottom:20px;">➕ Créer un utilisateur</h2>
        <form method="POST">
            <input type="hidden" name="action" value="creer">
            <div class="form-group">
                <label>Login</label>
                <input type="text" name="login" required placeholder="Ex. : televente2">
            </div>
            <div class="form-group">
                <label>Mot de passe <small class="text-muted">(min. 6 caractères)</small></label>
                <input type="password" name="mdp" required minlength="6" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Rôle</label>
                <select name="role" required>
                    <?php foreach ($rolesValides as $r): ?>
                        <option value="<?= h($r) ?>"><?= ($roleIcons[$r] ?? '') . ' ' . h(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Lier à un client <small class="text-muted">(obligatoire si rôle client)</small></label>
                <select name="codeclient">
                    <option value="">— Aucun —</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= h($c['codeclient']) ?>"><?= h($c['codeclient'] . ' — ' . $c['raisonsociale']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Lier à un personnel</label>
                <select name="idpersonnel">
                    <option value="0">— Aucun —</option>
                    <?php foreach ($personnels as $p): ?>
                        <option value="<?= (int)$p['idpersonnel'] ?>"><?= h($p['nom'] . ' ' . $p['prenom'] . ' (' . ($p['fonction'] ?? '?') . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-full">Créer l'utilisateur</button>
        </form>
    </div>

    <!-- Liste utilisateurs -->
    <div>
        <h2>Utilisateurs (<?= count($utilisateurs) ?>)</h2>
        <div class="table-container">
            <div style="background:#fff;border-radius:12px;overflow:hidden;">
                <?php foreach ($utilisateurs as $u):
                    $estMoi = ((int)$u['id_utilisateur'] === (int)$_SESSION['utilisateur_id']);
                    $color  = $roleColors[$u['role']] ?? '#6b7280';
                    $initiale = strtoupper(substr($u['login'], 0, 1));
                ?>
                <div class="user-row" style="<?= !$u['actif'] ? 'opacity:0.45;' : '' ?>">
                    <!-- Avatar -->
                    <div class="user-avatar" style="background:<?= $color ?>;"><?= $initiale ?></div>

                    <!-- Infos -->
                    <div>
                        <div class="user-info-name">
                            <?= h($u['login']) ?>
                            <?php if ($estMoi): ?><small class="text-muted"> (vous)</small><?php endif; ?>
                        </div>
                        <div class="user-info-sub">
                            <span class="role-pill" style="background:<?= $color ?>18;color:<?= $color ?>;">
                                <?= $roleIcons[$u['role']] ?? '' ?> <?= h($u['role']) ?>
                            </span>
                            <?php if ($u['raisonsociale']): ?>
                                &nbsp;· 👤 <?= h($u['raisonsociale']) ?>
                            <?php elseif ($u['nom']): ?>
                                &nbsp;· 👷 <?= h($u['nom'] . ' ' . $u['prenom']) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actif -->
                    <div title="<?= $u['actif'] ? 'Actif' : 'Inactif' ?>">
                        <div class="actif-dot" style="background:<?= $u['actif'] ? '#16a34a' : '#dc2626' ?>;"></div>
                    </div>

                    <!-- Changer rôle -->
                    <?php if (!$estMoi): ?>
                    <form method="POST" class="inline-actions">
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="id_utilisateur" value="<?= (int)$u['id_utilisateur'] ?>">
                        <select name="nouveau_role">
                            <?php foreach ($rolesValides as $r): ?>
                                <option value="<?= h($r) ?>" <?= $r===$u['role']?'selected':'' ?>><?= h($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-secondary btn-sm">OK</button>
                    </form>
                    <?php else: echo '<div></div>'; endif; ?>

                    <!-- Toggle actif -->
                    <?php if (!$estMoi): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id_utilisateur" value="<?= (int)$u['id_utilisateur'] ?>">
                        <button class="btn btn-sm <?= $u['actif'] ? 'btn-danger' : 'btn-primary' ?>">
                            <?= $u['actif'] ? 'Désact.' : 'Réact.' ?>
                        </button>
                    </form>
                    <?php else: echo '<div></div>'; endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
