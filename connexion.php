<?php
require_once __DIR__ . '/fonctions.php';
demarrerSession();

if (estConnecte()) {
    header('Location: ' . (getRole() === 'client' ? url('/pages/espace_client.php') : url('/pages/dashboard.php')));
    exit;
}

$erreur = '';
if (isset($_GET['timeout'])) $erreur = 'Votre session a expiré. Veuillez vous reconnecter.';
if (isset($_GET['acces']))   $erreur = 'Accès interdit. Vous n\'avez pas les droits nécessaires.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if ($login === '' || $mdp === '') {
        $erreur = 'Veuillez renseigner votre login et votre mot de passe.';
    } else {
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT * FROM utilisateurs_site WHERE login = :l AND actif = true');
        $stmt->execute([':l' => $login]);
        $u = $stmt->fetch();

        if ($u && password_verify($mdp, $u['mot_de_passe'])) {
            session_regenerate_id(true);
            $_SESSION['utilisateur_id']    = (int)$u['id_utilisateur'];
            $_SESSION['login']             = $u['login'];
            $_SESSION['role']              = $u['role'];
            $_SESSION['codeclient']        = $u['codeclient'] ?? null;
            $_SESSION['idpersonnel']       = $u['idpersonnel'] ?? null;
            $_SESSION['derniere_activite'] = time();
            header('Location: ' . ($u['role'] === 'client' ? url('/pages/espace_client.php') : url('/pages/dashboard.php')));
            exit;
        }
        $erreur = 'Identifiants incorrects ou compte désactivé.';
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* Override main-content pour pleine largeur */
.main-content { padding: 0; max-width: 100%; margin: 0; min-height: calc(100vh - 66px); display: flex; }

.connexion-page {
    flex: 1; display: grid;
    grid-template-columns: 1fr 480px;
    min-height: calc(100vh - 66px);
}

/* ── Partie gauche (image) ── */
.connexion-visuel {
    position: relative; overflow: hidden;
    background: #0a1f05;
}
.connexion-visuel-bg {
    position: absolute; inset: 0;
    background-image: url('https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=1200&q=85');
    background-size: cover; background-position: center;
    filter: brightness(0.50) saturate(1.2);
    transform: scale(1.04);
    transition: transform 10s ease;
}
.connexion-visuel:hover .connexion-visuel-bg { transform: scale(1.07); }
.connexion-visuel-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(135deg,
        rgba(10,31,5,0.70) 0%,
        rgba(26,58,15,0.30) 100%);
}
.connexion-visuel-content {
    position: relative; z-index: 2;
    padding: 56px 48px;
    display: flex; flex-direction: column;
    justify-content: flex-end; height: 100%;
}
.connexion-visuel-content h2 {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 2.2rem; font-weight: 700;
    color: #fff; margin-bottom: 12px; line-height: 1.2;
}
.connexion-visuel-content h2 em { font-style: normal; color: #f0dfa0; }
.connexion-visuel-content p {
    font-size: 0.97rem; color: rgba(255,255,255,0.65);
    max-width: 380px; line-height: 1.7; margin-bottom: 32px;
}
.connexion-features { display: flex; flex-direction: column; gap: 14px; }
.connexion-feature {
    display: flex; align-items: center; gap: 12px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 10px; padding: 12px 16px;
    backdrop-filter: blur(4px);
}
.connexion-feature-icon { font-size: 1.3rem; }
.connexion-feature-txt { font-size: 0.85rem; color: rgba(255,255,255,0.80); font-weight: 500; }

/* ── Partie droite (formulaire) ── */
.connexion-form-side {
    display: flex; flex-direction: column;
    justify-content: center; align-items: center;
    padding: 48px 40px;
    background: #fafaf7;
    border-left: 1px solid #e5e8e0;
}

.login-box {
    width: 100%; max-width: 360px;
}
.login-logo { text-align: center; font-size: 2.8rem; margin-bottom: 6px; }
.login-title {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.7rem; text-align: center;
    color: #1a3a0f; margin-bottom: 4px;
}
.login-subtitle {
    text-align: center; font-size: 0.88rem;
    color: #6b7280; margin-bottom: 28px;
}

.input-group { position: relative; margin-bottom: 16px; }
.input-group label {
    display: block; font-weight: 600;
    font-size: 0.85rem; color: #1a3a0f;
    margin-bottom: 6px; letter-spacing: 0.2px;
}
.input-group input {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid #d4d8cc; border-radius: 9px;
    font-family: 'Segoe UI', Arial, sans-serif; font-size: 0.95rem;
    background: #fff; color: #3d3d3d;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.input-group input:focus {
    outline: none; border-color: #4a7c2f;
    box-shadow: 0 0 0 3px rgba(74,124,47,0.12);
}

.btn-connexion {
    width: 100%; padding: 13px;
    background: #2d5a1b; color: #fff;
    border: none; border-radius: 9px;
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 1rem; font-weight: 700;
    cursor: pointer; margin-top: 8px;
    transition: background 0.18s, transform 0.12s;
    letter-spacing: 0.3px;
}
.btn-connexion:hover { background: #1a3a0f; transform: translateY(-1px); }
.btn-connexion:active { transform: translateY(0); }

.connexion-retour {
    display: block; text-align: center;
    margin-top: 20px; font-size: 0.85rem;
    color: #6b7280;
}
.connexion-retour a { color: #4a7c2f; font-weight: 600; }

/* Séparateur */
.or-sep {
    display: flex; align-items: center; gap: 12px;
    margin: 20px 0; color: #9ca3af; font-size: 0.82rem;
}
.or-sep::before, .or-sep::after {
    content: ''; flex: 1; height: 1px; background: #e5e8e0;
}

@media (max-width: 768px) {
    .connexion-page { grid-template-columns: 1fr; }
    .connexion-visuel { display: none; }
    .connexion-form-side { padding: 40px 24px; min-height: calc(100vh - 66px); }
}
</style>

<div class="connexion-page">

    <!-- Gauche : visuel -->
    <div class="connexion-visuel">
        <div class="connexion-visuel-bg"></div>
        <div class="connexion-visuel-overlay"></div>
        <div class="connexion-visuel-content">
            <h2>Votre espace<br><em>professionnel</em></h2>
            <p>Gérez vos commandes, suivez vos livraisons et accédez à vos tarifs personnalisés.</p>
            <div class="connexion-features">
                <div class="connexion-feature">
                    <span class="connexion-feature-icon">🛒</span>
                    <span class="connexion-feature-txt">Commandez en quelques clics</span>
                </div>
                <div class="connexion-feature">
                    <span class="connexion-feature-icon">📦</span>
                    <span class="connexion-feature-txt">Suivez vos livraisons en temps réel</span>
                </div>
                <div class="connexion-feature">
                    <span class="connexion-feature-icon">💰</span>
                    <span class="connexion-feature-txt">Tarifs adaptés à votre profil</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Droite : formulaire -->
    <div class="connexion-form-side">
        <div class="login-box">
            <div class="login-logo">🍊</div>
            <h2 class="login-title">FruitsPro</h2>
            <p class="login-subtitle">Connectez-vous à votre espace</p>

            <?php if ($erreur): ?>
                <div class="msg msg-erreur" style="margin-bottom:18px;">❌ <?= h($erreur) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <div class="input-group">
                    <label for="login">Login</label>
                    <input type="text" id="login" name="login"
                           value="<?= h($_POST['login'] ?? '') ?>"
                           required autofocus autocomplete="username"
                           placeholder="Votre identifiant">
                </div>
                <div class="input-group">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe"
                           required autocomplete="current-password"
                           placeholder="••••••••">
                </div>
                <button type="submit" class="btn-connexion">Se connecter →</button>
            </form>

            <span class="connexion-retour">
                <a href="<?= h(url('/index.php')) ?>">← Retour au catalogue public</a>
            </span>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
