<?php
require_once __DIR__ . '/config.php';

/**
 * Retourne une instance PDO (PostgreSQL) — singleton.
 */
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $msg = DEBUG_MODE ? htmlspecialchars($e->getMessage()) : 'Erreur de connexion à la base de données.';
            die('<p style="color:red;font-family:sans-serif;padding:20px;">Erreur BD : ' . $msg . '</p>');
        }
    }
    return $pdo;
}

/**
 * Construit une URL absolue à partir du BASE_URL.
 */
function url(string $path): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Démarre la session et gère l'expiration.
 */
function demarrerSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['derniere_activite']) && time() - $_SESSION['derniere_activite'] > SESSION_DURATION) {
        session_unset();
        session_destroy();
        header('Location: ' . url('/connexion.php?timeout=1'));
        exit;
    }
    $_SESSION['derniere_activite'] = time();
}

/** Indique si un utilisateur est connecté. */
function estConnecte(): bool {
    return isset($_SESSION['utilisateur_id']);
}

/** Retourne le rôle de l'utilisateur connecté ('visiteur' si non connecté). */
function getRole(): string {
    return $_SESSION['role'] ?? 'visiteur';
}

/**
 * Exige que l'utilisateur ait l'un des rôles autorisés.
 * Redirige vers connexion sinon.
 */
function exigerRole(array $rolesAutorises): void {
    demarrerSession();
    if (!estConnecte() || !in_array(getRole(), $rolesAutorises, true)) {
        header('Location: ' . url('/connexion.php?acces=interdit'));
        exit;
    }
}

/** Échappe une chaîne pour l'affichage HTML. */
function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** Message de succès HTML. */
function msgSucces(string $message): string {
    return '<div class="msg msg-succes">✅ ' . h($message) . '</div>';
}

/** Message d'erreur HTML. */
function msgErreur(string $message): string {
    return '<div class="msg msg-erreur">❌ ' . h($message) . '</div>';
}

/** Message d'information HTML. */
function msgInfo(string $message): string {
    return '<div class="msg msg-info">ℹ️ ' . h($message) . '</div>';
}

/** Message d'avertissement HTML. */
function msgWarn(string $message): string {
    return '<div class="msg msg-warn">⚠️ ' . h($message) . '</div>';
}

/** Formate un nombre en euros. */
function formatEuro($montant): string {
    return number_format((float)$montant, 2, ',', ' ') . ' €';
}

/** Retourne le libellé français du statut. */
function libelleStatut(string $statut): string {
    return match ($statut) {
        'Saisie'   => 'Saisie',
        'Preparee' => 'Préparée',
        'Livree'   => 'Livrée',
        default    => $statut,
    };
}

/** Retourne la classe CSS du badge statut. */
function classeBadgeStatut(string $statut): string {
    return match ($statut) {
        'Saisie'   => 'badge-saisie',
        'Preparee' => 'badge-preparee',
        'Livree'   => 'badge-livree',
        default    => '',
    };
}

/** Retourne le mois courant en français (sans accent pour PostgreSQL). */
function moisFrancais(): string {
    $m = [
        1 => 'Janvier', 2 => 'Fevrier',  3 => 'Mars',     4 => 'Avril',
        5 => 'Mai',     6 => 'Juin',     7 => 'Juillet',  8 => 'Aout',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
    ];
    return $m[(int)date('n')];
}

/**
 * Vérifie qu'une commande appartient bien à un client donné.
 * Protège contre l'accès non autorisé aux détails de commande.
 */
function verifierClientProprietaire(PDO $pdo, int $idCommande, string $codeClient): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM commande WHERE idcommande = :id AND codeclient = :c');
    $stmt->execute([':id' => $idCommande, ':c' => $codeClient]);
    return (int)$stmt->fetchColumn() > 0;
}
