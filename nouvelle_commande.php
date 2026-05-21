<?php
require_once __DIR__ . '/../fonctions.php';
exigerRole(['client']);
$pdo        = getPDO();
$message    = '';
$erreur     = '';
$codeClient = $_SESSION['codeclient'];

/* ── Récupérer le tarif client ── */
$stmtTarif = $pdo->prepare('SELECT nomtarif FROM client WHERE codeclient = :c');
$stmtTarif->execute([':c' => $codeClient]);
$nomTarif = $stmtTarif->fetchColumn();

/* ── Traitement du formulaire (panier) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Récupérer les lignes du panier */
    $varietes_cmd = $_POST['codevariete']  ?? [];
    $quantites    = $_POST['quantite']     ?? [];
    $idPaiement   = (int)($_POST['idpaiement'] ?? 0);
    $idModeLiv    = (int)($_POST['idmodeliv']  ?? 0);

    /* Nettoyer les lignes vides */
    $lignes = [];
    foreach ($varietes_cmd as $i => $cv) {
        $qte = (int)($quantites[$i] ?? 0);
        if ($cv !== '' && $qte > 0) {
            $lignes[] = ['codevariete' => $cv, 'quantite' => $qte];
        }
    }

    if (empty($lignes)) {
        $erreur = 'Ajoutez au moins une variété avec une quantité positive.';
    } elseif ($idPaiement <= 0 || $idModeLiv <= 0) {
        $erreur = 'Veuillez choisir un mode de paiement et un mode de livraison.';
    } else {
        try {
            $pdo->beginTransaction();

            /* Créer la commande */
            $stmt = $pdo->prepare("
                INSERT INTO commande(datecommande, commandeorigine, statut, heuresaisie,
                    codeclient, idpersonnel, idpaiement, idmodeliv, moiscommande)
                VALUES (CURRENT_DATE, NULL, 'Saisie', CURRENT_TIMESTAMP,
                    :client, :pers, :paie, :liv, :mois)
                RETURNING idcommande
            ");
            $stmt->execute([
                ':client' => $codeClient,
                ':pers'   => DEFAULT_ID_PERSONNEL,
                ':paie'   => $idPaiement,
                ':liv'    => $idModeLiv,
                ':mois'   => moisFrancais(),
            ]);
            $idCommande = (int)$stmt->fetchColumn();

            $totalCmd = 0;
            foreach ($lignes as $ligne) {
                $cv  = $ligne['codevariete'];
                $qte = $ligne['quantite'];

                /* Verrouiller le stock */
                $sv = $pdo->prepare('SELECT * FROM variete WHERE codevariete = :cv FOR UPDATE');
                $sv->execute([':cv' => $cv]);
                $variete = $sv->fetch();
                if (!$variete) throw new Exception('Variété inconnue : ' . $cv);
                if ((int)$variete['quantitestock'] < $qte) {
                    throw new Exception('Stock insuffisant pour ' . $variete['nomvariete'] .
                        ' (stock : ' . (int)$variete['quantitestock'] . ').');
                }

                /* Prix selon tarif client */
                $sp = $pdo->prepare('SELECT prixkg FROM prix WHERE codevariete = :cv AND nomtarif = :tarif AND datefin IS NULL ORDER BY datedebut DESC LIMIT 1');
                $sp->execute([':cv' => $cv, ':tarif' => $nomTarif]);
                $prix = $sp->fetchColumn();
                if ($prix === false) {
                    $sp2 = $pdo->prepare('SELECT prixkg FROM prix WHERE codevariete = :cv AND datefin IS NULL ORDER BY datedebut DESC LIMIT 1');
                    $sp2->execute([':cv' => $cv]);
                    $prix = $sp2->fetchColumn();
                }
                if ($prix === false) throw new Exception('Aucun prix pour ' . $variete['nomvariete'] . '.');

                /* Insérer ligne commande */
                $pdo->prepare('INSERT INTO ligne_commande(idcommande, codevariete, quantitedemandee, quantitelivree, prixunitaire) VALUES (:id, :cv, :qte, NULL, :prix)')
                    ->execute([':id' => $idCommande, ':cv' => $cv, ':qte' => $qte, ':prix' => $prix]);

                /* Décrémenter stock */
                $pdo->prepare('UPDATE variete SET quantitestock = quantitestock - :qte WHERE codevariete = :cv')
                    ->execute([':qte' => $qte, ':cv' => $cv]);

                $totalCmd += $qte * $prix;
            }

            $pdo->commit();
            $message = 'Commande #' . $idCommande . ' créée avec succès — ' . count($lignes) . ' ligne(s) — Total estimé : ' . formatEuro($totalCmd) . '.';

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erreur = $e->getMessage();
        }
    }
}

/* ── Données pour le formulaire ── */
$varStmt = $pdo->query("
    SELECT v.codevariete, v.nomvariete, v.calibre, v.quantitestock,
           a.libellearticle, c.libellecategorie,
           p.prixkg
    FROM variete v
    JOIN article a ON a.idarticle = v.idarticle
    JOIN categorie c ON c.idcategorie = a.idcategorie
    LEFT JOIN prix p ON p.codevariete = v.codevariete AND p.nomtarif = " . $pdo->quote($nomTarif) . " AND p.datefin IS NULL
    ORDER BY c.libellecategorie, a.libellearticle, v.nomvariete
");
$varietes   = $varStmt->fetchAll();
$paiements  = $pdo->query('SELECT idpaiement, libellepaiement FROM mode_paiement ORDER BY idpaiement')->fetchAll();
$livraisons = $pdo->query('SELECT idmodeliv, libellemodeliv FROM mode_livraison ORDER BY idmodeliv')->fetchAll();

/* Pré-sélection depuis le modal (paramètre GET) */
$preselectCode = $_GET['variete'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<style>
.panier-wrap { display: grid; grid-template-columns: 1fr 360px; gap: 28px; align-items: start; }
.panier-lignes { background: #fff; border-radius: 16px; border: 1px solid #e5e8e0; overflow: hidden; box-shadow: 0 2px 10px rgba(26,58,15,0.07); }
.panier-ligne-header { background: #1a3a0f; color: #f0dfa0; padding: 16px 22px; font-family: Georgia,'Times New Roman',serif; font-size: 1rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
.panier-ligne { display: grid; grid-template-columns: 1fr 130px 80px 40px; gap: 12px; align-items: center; padding: 16px 22px; border-bottom: 1px solid #f0f2ec; }
.panier-ligne:last-child { border-bottom: none; }
.panier-ligne select, .panier-ligne input { font-family: 'Segoe UI',sans-serif; font-size: 0.88rem; padding: 9px 12px; border: 1.5px solid #d4d8cc; border-radius: 8px; background: #f9fdf4; transition: border-color 0.15s; width: 100%; }
.panier-ligne select:focus, .panier-ligne input:focus { outline: none; border-color: #4a7c2f; background: #fff; box-shadow: 0 0 0 3px rgba(61,122,36,0.10); }
.panier-sous-total { font-weight: 700; color: #c8a84b; font-family: Georgia,'Times New Roman',serif; font-size: 0.95rem; min-width: 70px; text-align: right; }
.btn-remove { background: none; border: none; cursor: pointer; color: #dc2626; font-size: 1.1rem; padding: 4px; border-radius: 6px; transition: background 0.15s; }
.btn-remove:hover { background: #fef2f2; }
.btn-add-ligne { display: flex; align-items: center; gap: 8px; padding: 14px 22px; font-size: 0.88rem; font-weight: 600; color: #4a7c2f; background: #f3faf0; border: none; cursor: pointer; font-family: 'Segoe UI',sans-serif; transition: background 0.15s; width: 100%; }
.btn-add-ligne:hover { background: #e8f5e1; }

.recap-card { background: #fff; border-radius: 16px; border: 1px solid #e5e8e0; padding: 24px; box-shadow: 0 2px 10px rgba(26,58,15,0.07); position: sticky; top: 90px; }
.recap-title { font-family: Georgia,'Times New Roman',serif; font-size: 1.1rem; font-weight: 700; color: #1a3a0f; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #c8a84b; }
.recap-total { display: flex; justify-content: space-between; align-items: baseline; padding: 16px 0; border-top: 1px solid #f0f2ec; margin-top: 8px; }
.recap-total-lbl { font-size: 0.88rem; color: #6b7280; font-weight: 600; }
.recap-total-val { font-family: Georgia,'Times New Roman',serif; font-size: 1.5rem; font-weight: 700; color: #c8a84b; }
.recap-ligne-detail { display: flex; justify-content: space-between; font-size: 0.83rem; padding: 5px 0; color: #5f6b4e; }

.tarif-info { background: #fdf8ec; border: 1px solid #f0dfa0; border-radius: 8px; padding: 10px 14px; margin-bottom: 18px; font-size: 0.83rem; color: #5f4a1a; }
.tarif-info strong { color: #c8a84b; }

@media(max-width:900px){ .panier-wrap { grid-template-columns: 1fr; } .recap-card { position: static; } }
@media(max-width:600px){ .panier-ligne { grid-template-columns: 1fr 100px 60px 36px; gap: 8px; padding: 12px 14px; } }
</style>

<div class="breadcrumb">
    <a href="<?= h(url('/pages/espace_client.php')) ?>">Mon espace</a>
    <span>›</span>
    <span>Nouvelle commande</span>
</div>

<h1>🛒 Nouvelle commande</h1>

<?php if ($message): echo msgSucces($message); endif; ?>
<?php if ($erreur):  echo msgErreur($erreur);  endif; ?>

<div class="tarif-info">
    🏷️ Votre tarif professionnel : <strong><?= h($nomTarif) ?></strong> — les prix affichés sont personnalisés selon votre profil.
</div>

<form method="POST" id="formCommande">
<div class="panier-wrap">

    <!-- ── Lignes du panier ── -->
    <div>
        <div class="panier-lignes">
            <div class="panier-ligne-header">
                <span>🛒 Votre panier</span>
                <span id="nbLignesLabel" style="font-size:0.82rem;font-weight:400;color:rgba(240,223,160,0.7);">0 produit</span>
            </div>
            <div id="panierLignes">
                <!-- Les lignes sont ajoutées dynamiquement par JS -->
            </div>
            <button type="button" class="btn-add-ligne" onclick="ajouterLigne()">
                ➕ Ajouter un produit
            </button>
        </div>
    </div>

    <!-- ── Récapitulatif & options ── -->
    <div>
        <div class="recap-card">
            <div class="recap-title">Récapitulatif</div>

            <div class="form-group">
                <label>Mode de paiement</label>
                <select name="idpaiement" required>
                    <?php foreach ($paiements as $p): ?>
                        <option value="<?= (int)$p['idpaiement'] ?>"><?= h($p['libellepaiement']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Mode de livraison</label>
                <select name="idmodeliv" required>
                    <?php foreach ($livraisons as $l): ?>
                        <option value="<?= (int)$l['idmodeliv'] ?>"><?= h($l['libellemodeliv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="recapDetail" style="margin-bottom:4px;"></div>

            <div class="recap-total">
                <span class="recap-total-lbl">Total estimé</span>
                <span class="recap-total-val" id="recapTotal">0,00 €</span>
            </div>

            <button type="submit" class="btn btn-primary w-full" style="margin-top:16px;justify-content:center;" id="btnValider" disabled>
                ✅ Valider la commande
            </button>
            <p class="text-muted mt-4" style="font-size:0.80rem;text-align:center;">
                Le stock est vérifié au moment de la validation.
            </p>
        </div>
    </div>
</div>
</form>

<!-- ── Données JS ── -->
<script>
const VARIETES = <?= json_encode(array_values($varietes), JSON_UNESCAPED_UNICODE) ?>;
const PRESELECT = <?= json_encode($preselectCode) ?>;

/* Index rapide codevariete → objet */
const VAR_IDX = {};
VARIETES.forEach(v => { VAR_IDX[v.codevariete] = v; });

let ligneCount = 0;

function euro(v) {
    return new Intl.NumberFormat('fr-FR',{style:'currency',currency:'EUR'}).format(v);
}

function buildOptions(selectedCode) {
    let html = '<option value="">— Choisir une variété —</option>';
    let lastCat = '';
    VARIETES.forEach(v => {
        if (v.libellecategorie !== lastCat) {
            if (lastCat) html += '</optgroup>';
            html += `<optgroup label="${v.libellecategorie}">`;
            lastCat = v.libellecategorie;
        }
        const disabled = v.quantitestock <= 0 ? 'disabled' : '';
        const sel      = v.codevariete === selectedCode ? 'selected' : '';
        const label    = `${v.libellearticle} — ${v.nomvariete} (stock: ${v.quantitestock})${v.quantitestock <= 0 ? ' [Rupture]' : ''}`;
        html += `<option value="${v.codevariete}" ${disabled} ${sel} data-prix="${v.prixkg||0}" data-stock="${v.quantitestock}">${label}</option>`;
    });
    if (lastCat) html += '</optgroup>';
    return html;
}

function ajouterLigne(preselectCode) {
    ligneCount++;
    const id  = ligneCount;
    const div = document.createElement('div');
    div.className = 'panier-ligne';
    div.id = 'ligne-' + id;
    div.innerHTML = `
        <select name="codevariete[]" onchange="majLigne(${id})" required>
            ${buildOptions(preselectCode || '')}
        </select>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <input type="number" name="quantite[]" min="1" value="1"
                   placeholder="Qté" onchange="majLigne(${id})" oninput="majLigne(${id})"
                   style="text-align:center;" required>
            <span style="font-size:0.70rem;color:#6b7280;text-align:center;" id="stock-${id}"></span>
        </div>
        <span class="panier-sous-total" id="st-${id}">—</span>
        <button type="button" class="btn-remove" onclick="supprimerLigne(${id})" title="Supprimer">✕</button>
    `;
    document.getElementById('panierLignes').appendChild(div);
    majLigne(id);
    majRecap();

    /* Si présélection, déclencher le calcul */
    if (preselectCode) { setTimeout(() => majLigne(id), 50); }
}

function supprimerLigne(id) {
    const el = document.getElementById('ligne-' + id);
    if (el) el.remove();
    majRecap();
}

function majLigne(id) {
    const ligne = document.getElementById('ligne-' + id);
    if (!ligne) return;
    const sel   = ligne.querySelector('select');
    const input = ligne.querySelector('input[type=number]');
    const stEl  = document.getElementById('stock-' + id);
    const stSub = document.getElementById('st-' + id);
    const cv    = sel.value;
    const qte   = parseInt(input.value) || 0;

    if (!cv) { stEl.textContent = ''; stSub.textContent = '—'; majRecap(); return; }

    const v    = VAR_IDX[cv];
    const prix = parseFloat(sel.selectedOptions[0]?.dataset?.prix || 0);
    const stk  = parseInt(sel.selectedOptions[0]?.dataset?.stock || 0);

    stEl.textContent  = 'Stock : ' + stk;
    stEl.style.color  = stk <= 0 ? '#dc2626' : stk < 50 ? '#d97706' : '#16a34a';

    if (prix > 0 && qte > 0) {
        stSub.textContent = euro(prix * qte);
    } else {
        stSub.textContent = '—';
    }
    majRecap();
}

function majRecap() {
    let total = 0;
    let detail = '';
    let nb = 0;
    document.querySelectorAll('.panier-ligne').forEach(ligne => {
        const sel   = ligne.querySelector('select');
        const input = ligne.querySelector('input[type=number]');
        if (!sel || !sel.value) return;
        const prix = parseFloat(sel.selectedOptions[0]?.dataset?.prix || 0);
        const qte  = parseInt(input?.value) || 0;
        const nom  = sel.selectedOptions[0]?.text?.split('(')[0]?.trim() || '';
        if (prix > 0 && qte > 0) {
            total += prix * qte;
            detail += `<div class="recap-ligne-detail"><span>${nom}</span><span>${euro(prix * qte)}</span></div>`;
            nb++;
        }
    });
    document.getElementById('recapTotal').textContent = euro(total);
    document.getElementById('recapDetail').innerHTML  = detail;
    document.getElementById('nbLignesLabel').textContent = nb + ' produit' + (nb > 1 ? 's' : '');
    document.getElementById('btnValider').disabled = nb === 0;
}

/* Initialisation */
if (PRESELECT) {
    ajouterLigne(PRESELECT);
} else {
    ajouterLigne();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
