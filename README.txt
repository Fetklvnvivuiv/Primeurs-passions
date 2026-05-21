================================================================
  FRUITSPRO — Site web de gestion commerciale de fruits
  Projet SAE 2-04 — Rendu 3
  Étudiants : FETTOUL Mohamed / CHEIKH-BENCHEIKH Nasser-Edine
  IUT Gustave Eiffel — BUT Informatique — 2025-2026
================================================================

LIEN D'ACCÈS AU SITE
---------------------
https://etudiant.univ-eiffel.fr/~cheikhbencheik/projet_fruits_final_2/

COMPTES DE TEST (mot de passe commun : motdepasse123)
------------------------------------------------------
admin          → rôle admin        (accès complet)
gestionnaire1  → rôle gestionnaire (stocks, prix, factures, anomalies)
preparateur1   → rôle préparateur  (commandes à préparer)
televente1     → rôle télévente    (saisie commandes)
client1        → rôle client       (lié au client CLT0001 — Maison Dupont)

BASE DE DONNÉES
---------------
Serveur : sqledu.univ-eiffel.fr
Base    : cheikhbencheik_db
Schéma  : public
Tables  : 16 tables métier + utilisateurs_site

ARBORESCENCE
------------
projet_fruits_final_2/
├── config.php               → configuration BDD et BASE_URL
├── fonctions.php            → fonctions communes PHP
├── index.php                → page d'accueil catalogue (avec images)
├── connexion.php            → authentification
├── deconnexion.php          → destruction de session
├── css/style.css            → feuille de style globale
├── includes/
│   ├── header.php           → en-tête commun (navigation par rôle)
│   └── footer.php           → pied de page commun
├── pages/
│   ├── espace_client.php         → espace personnel du client
│   ├── nouvelle_commande.php     → panier multi-produits
│   ├── mes_commandes.php         → historique avec timeline
│   ├── _ajax_detail_commande.php → endpoint AJAX détail commande
│   ├── dashboard.php             → tableau de bord avec graphique
│   ├── commandes_televente.php   → saisie commande télévente
│   ├── commandes_preparer.php    → préparation des commandes
│   ├── stocks_prix.php           → gestion stocks et tarifs
│   └── facturation.php           → facturation et anomalies
├── admin/
│   └── administration.php        → gestion des utilisateurs
└── sql/
    ├── dump-sujet-fettoul-cheik-postgresql-corrige.sql → dump BDD
    └── utilisateurs_site.sql                           → comptes de test

INSTALLATION
------------
1. Créer la base avec le dump SQL :
   psql -U cheikhbencheik -d cheikhbencheik_db -f sql/dump-sujet-fettoul-cheik-postgresql-corrige.sql
2. Créer les comptes utilisateurs :
   psql -U cheikhbencheik -d cheikhbencheik_db -f sql/utilisateurs_site.sql
3. Vérifier config.php : DB_USER, DB_PASS, BASE_URL
4. Déposer les fichiers sur le serveur
5. Accéder à l'URL ci-dessus

TRANSACTIONS POSTGRESQL IMPLÉMENTÉES
--------------------------------------
1. Nouvelle commande client (nouvelle_commande.php)
   → BEGIN, SELECT FOR UPDATE sur chaque variété, vérification stock,
     INSERT commande + lignes, UPDATE stock, COMMIT / ROLLBACK

2. Saisie télévente (commandes_televente.php)
   → Même logique, avec sélection du client par le télévente

3. Préparation de commande (commandes_preparer.php)
   → BEGIN, SELECT FOR UPDATE sur la commande, création colis,
     contenu_colis, mise à jour statut → Preparee, COMMIT / ROLLBACK

4. Facturation et anomalies (facturation.php)
   → BEGIN, SELECT FOR UPDATE sur la commande, vérification statut,
     INSERT facture + anomalie optionnelle, statut → Livree, COMMIT / ROLLBACK

FONCTIONNALITÉS PRINCIPALES
-----------------------------
- Catalogue public avec images, filtres par catégorie et modal détaillé
- Panier multi-produits (plusieurs variétés par commande)
- Pré-sélection de variété depuis le modal catalogue → commande
- Timeline visuelle Saisie → Préparée → Livrée dans les commandes
- Dashboard avec graphique des commandes par mois (Chart.js)
- Top 5 variétés commandées avec barres de progression
- Page connexion avec image de fond en split-screen
- Administration : stats par rôle, avatars colorés, gestion complète
- Micro-animations sur les messages et cartes statistiques
- Responsive mobile sur toutes les pages

SÉCURITÉ
--------
- Sessions PHP avec expiration automatique (1 heure)
- Mots de passe hachés avec password_hash() / password_verify()
- Requêtes préparées PDO (protection injection SQL)
- Échappement HTML via h() (protection XSS)
- Contrôle d'accès par rôle sur chaque page (exigerRole)
- Un client ne peut voir que ses propres commandes (verifierClientProprietaire)

NOTES TECHNIQUES
----------------
- SGBD : PostgreSQL (migration MySQL → PostgreSQL effectuée au rendu 1)
- Langages : PHP 8, SQL, HTML5, CSS3, JavaScript vanilla
- Librairie externe : Chart.js 4.4 (CDN jsdelivr) pour les graphiques
- Images : Unsplash (URLs directes, chargement lazy)
- Aucun framework PHP utilisé (architecture MVC simplifiée maison)
================================================================
