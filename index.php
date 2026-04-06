<?php
require_once __DIR__ . '/db.php';

// ── Dynamic dropdown values from MongoDB ─────────────────────────────────────
$all_pays        = $collection->distinct('country');
$all_charges     = $collection->distinct('Chargé_client');
$all_raisons     = $collection->distinct('Raison_Social');
sort($all_pays);
sort($all_charges);
sort($all_raisons);

// ── Build filter from POST ────────────────────────────────────────────────────
$filter       = [];
$query_string = null;
$results      = null;
$count        = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['raison_social'])) {
        $filter['Raison_Social'] = ['$regex' => $_POST['raison_social'], '$options' => 'i'];
    }

    if (!empty($_POST['taille'])) {
        $tailles = array_map('intval', $_POST['taille']);
        $filter['Taille_entreprise'] = count($tailles) === 1
            ? $tailles[0]
            : ['$in' => $tailles];
    }

    if (!empty($_POST['pays'])) {
        $filter['country'] = $_POST['pays'];
    }

    if (!empty($_POST['statut'])) {
        $statuts = $_POST['statut'];
        $filter['statut'] = count($statuts) === 1 ? $statuts[0] : ['$in' => $statuts];
    }

    if (!empty($_POST['ticket_min']) || !empty($_POST['ticket_max'])) {
        $tf = [];
        if (!empty($_POST['ticket_min'])) $tf['$gte'] = (int)$_POST['ticket_min'];
        if (!empty($_POST['ticket_max'])) $tf['$lte'] = (int)$_POST['ticket_max'];
        $filter['Nombre_ticket'] = $tf;
    }

    if (!empty($_POST['ca_min']) || !empty($_POST['ca_max'])) {
        $cf = [];
        if (!empty($_POST['ca_min'])) $cf['$gte'] = (float)$_POST['ca_min'];
        if (!empty($_POST['ca_max'])) $cf['$lte'] = (float)$_POST['ca_max'];
        $filter['chiffre_affaires.montant'] = $cf;
    }

    if (!empty($_POST['devise'])) {
        $filter['chiffre_affaires.devise'] = $_POST['devise'];
    }

    if (!empty($_POST['date_from']) || !empty($_POST['date_to'])) {
        $df = [];
        if (!empty($_POST['date_from'])) $df['$gte'] = $_POST['date_from'] . ' 00:00';
        if (!empty($_POST['date_to']))   $df['$lte'] = $_POST['date_to']   . ' 23:59';
        $filter['Date_abonnement'] = $df;
    }

    if (!empty($_POST['product'])) {
        $products = $_POST['product'];
        $filter['Product'] = count($products) === 1 ? $products[0] : ['$in' => $products];
    }

    if (!empty($_POST['charge_client'])) {
        $filter['Chargé_client'] = $_POST['charge_client'];
    }

    // ── Generate query string ─────────────────────────────────────────────────
    $query_string = json_encode(['$match' => $filter], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // ── Run query against MongoDB ─────────────────────────────────────────────
    $cursor  = $collection->find($filter, ['limit' => 200]);
    $results = iterator_to_array($cursor);
    $count   = $collection->countDocuments($filter);
}

function sel($field, $val) {
    return (($_POST[$field] ?? '') === $val) ? 'selected' : '';
}
function chk($field, $val) {
    return (isset($_POST[$field]) && in_array($val, (array)$_POST[$field])) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CRM — MongoDB Query Builder</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0d0f14;
    --surface: #13161e;
    --surface2: #1a1e2a;
    --border: #252a38;
    --border2: #2e3447;
    --accent: #4f8aff;
    --accent2: #7c5cfc;
    --green: #3ecf8e;
    --text: #e8eaf0;
    --muted: #6b7280;
    --label: #9aa3b2;
    --danger: #f87171;
    --amber: #f59e0b;
  }

  body {
    font-family: 'Sora', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding: 2rem 1rem;
  }

  .container { max-width: 1100px; margin: 0 auto; }

  header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2.5rem;
  }

  .logo {
    width: 42px; height: 42px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
  }

  header h1 { font-size: 1.4rem; font-weight: 700; letter-spacing: -0.02em; }
  header p  { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

  .badge {
    margin-left: auto;
    background: rgba(79,138,255,0.12);
    color: var(--accent);
    font-size: 0.68rem; font-weight: 600;
    padding: 4px 10px; border-radius: 20px;
    border: 1px solid rgba(79,138,255,0.25);
    letter-spacing: 0.05em; text-transform: uppercase;
    white-space: nowrap;
  }

  .layout { display: grid; grid-template-columns: 380px 1fr; gap: 1.5rem; align-items: start; }

  /* ── Form panel ── */
  .form-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.4rem;
    display: flex; flex-direction: column; gap: 1rem;
  }

  .field label.flabel {
    display: block;
    font-size: 0.68rem; font-weight: 600;
    color: var(--muted);
    text-transform: uppercase; letter-spacing: 0.08em;
    margin-bottom: 0.5rem;
  }

  .field input[type="text"],
  .field input[type="number"],
  .field input[type="date"],
  .field select {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: 'Sora', sans-serif;
    font-size: 0.82rem;
    padding: 0.48rem 0.75rem;
    outline: none;
    transition: border-color 0.2s;
  }

  .field input:focus, .field select:focus { border-color: var(--accent); }
  .field select option { background: var(--surface2); }

  .range-row { display: grid; grid-template-columns: 1fr auto 1fr; gap: 6px; align-items: center; }
  .range-row span { color: var(--muted); font-size: 0.75rem; text-align: center; }

  .checkgroup { display: flex; flex-wrap: wrap; gap: 6px; }
  .checkgroup label {
    display: flex; align-items: center; gap: 5px;
    cursor: pointer;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 7px;
    padding: 4px 10px;
    font-size: 0.78rem; color: var(--label);
    transition: all 0.15s;
  }
  .checkgroup label:hover { border-color: var(--accent); color: var(--text); }
  .checkgroup input[type="checkbox"] { accent-color: var(--accent); width: 13px; height: 13px; }

  .btn-row { display: flex; gap: 8px; margin-top: 0.25rem; }

  .btn-reset {
    background: transparent; border: 1px solid var(--border2);
    color: var(--muted); font-family: 'Sora', sans-serif;
    font-size: 0.82rem; padding: 0.6rem 1rem;
    border-radius: 8px; cursor: pointer; transition: all 0.2s;
  }
  .btn-reset:hover { color: var(--text); border-color: var(--muted); }

  .btn-submit {
    flex: 1; background: var(--accent); border: none;
    color: #fff; font-family: 'Sora', sans-serif;
    font-size: 0.82rem; font-weight: 600;
    padding: 0.6rem 1rem; border-radius: 8px;
    cursor: pointer; transition: opacity 0.2s, transform 0.1s;
  }
  .btn-submit:hover { opacity: 0.88; }
  .btn-submit:active { transform: scale(0.98); }

  /* ── Right panel ── */
  .right-panel { display: flex; flex-direction: column; gap: 1rem; }

  /* Query box */
  .query-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
  }

  .box-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.75rem 1.1rem;
    border-bottom: 1px solid var(--border);
  }

  .box-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.75rem; font-weight: 600;
    color: var(--green); letter-spacing: 0.05em; text-transform: uppercase;
  }

  .dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--green); animation: pulse 2s infinite;
  }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.35} }

  .copy-btn {
    background: transparent; border: 1px solid var(--border2);
    color: var(--muted); font-family: 'JetBrains Mono', monospace;
    font-size: 0.7rem; padding: 3px 10px; border-radius: 6px;
    cursor: pointer; transition: all 0.2s;
  }
  .copy-btn:hover { color: var(--text); border-color: var(--accent); }

  .result-code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.78rem; line-height: 1.7;
    color: #a8d8a0; background: #0a0c11;
    padding: 1rem 1.2rem; overflow-x: auto; white-space: pre;
    max-height: 260px; overflow-y: auto;
  }

  .empty-hint {
    padding: 1.5rem; text-align: center;
    color: var(--muted); font-size: 0.82rem;
  }

  /* Results table */
  .results-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
  }

  .count-badge {
    background: rgba(62,207,142,0.12);
    color: var(--green); font-size: 0.7rem; font-weight: 600;
    padding: 3px 10px; border-radius: 20px;
    border: 1px solid rgba(62,207,142,0.25);
  }

  .table-wrap { overflow-x: auto; max-height: 420px; overflow-y: auto; }

  table {
    width: 100%; border-collapse: collapse;
    font-size: 0.78rem; min-width: 900px;
  }

  thead th {
    background: #0f111a;
    color: var(--muted); font-weight: 600;
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em;
    padding: 10px 12px; text-align: left;
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; z-index: 1;
  }

  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
  tbody tr:hover { background: var(--surface2); }
  tbody td { padding: 9px 12px; color: var(--label); vertical-align: middle; }
  tbody td:first-child { color: var(--text); font-weight: 500; }

  .pill {
    display: inline-block; padding: 2px 9px; border-radius: 20px;
    font-size: 0.7rem; font-weight: 600;
  }
  .pill-prospect  { background: rgba(245,158,11,0.12); color: var(--amber); border: 1px solid rgba(245,158,11,0.25); }
  .pill-client    { background: rgba(62,207,142,0.12); color: var(--green); border: 1px solid rgba(62,207,142,0.25); }
  .pill-revenant  { background: rgba(79,138,255,0.12); color: var(--accent); border: 1px solid rgba(79,138,255,0.25); }
  .pill-ancien    { background: rgba(248,113,113,0.12); color: var(--danger); border: 1px solid rgba(248,113,113,0.25); }

  .no-results { padding: 2rem; text-align: center; color: var(--muted); font-size: 0.85rem; }

  @media (max-width: 800px) {
    .layout { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="container">

  <header>
    <div class="logo">⚡</div>
    <div>
      <h1>CRM Query Builder</h1>
      <p>TESTING.Clients — MongoDB dynamique</p>
    </div>
    <span class="badge">PHP · MongoDB</span>
  </header>

  <div class="layout">

    <!-- ── FORM ── -->
    <form method="POST" class="form-panel">

      <div class="field">
        <label class="flabel">Raison Sociale</label>
        <input type="text" name="raison_social" placeholder="ex: PUMA" value="<?= htmlspecialchars($_POST['raison_social'] ?? '') ?>">
      </div>

      <div class="field">
        <label class="flabel">Pays</label>
        <select name="pays">
          <option value="">-- Tous --</option>
          <?php foreach ($all_pays as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>" <?= sel('pays', $p) ?>><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label class="flabel">Chargé Client</label>
        <select name="charge_client">
          <option value="">-- Tous --</option>
          <?php foreach ($all_charges as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= sel('charge_client', $c) ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label class="flabel">Devise</label>
        <select name="devise">
          <option value="">-- Toutes --</option>
          <?php foreach (['EUR','USD','GBP','MAD','TND','CHF','DZD'] as $d): ?>
            <option value="<?= $d ?>" <?= sel('devise', $d) ?>><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label class="flabel">Taille de l'entreprise</label>
        <div class="checkgroup">
          <?php foreach (['10','50','100'] as $t): ?>
            <label><input type="checkbox" name="taille[]" value="<?= $t ?>" <?= chk('taille', $t) ?>><span><?= $t ?> emp.</span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field">
        <label class="flabel">Statut</label>
        <div class="checkgroup">
          <?php foreach (['Prospect','Client','Client revenant','Ancien client'] as $s): ?>
            <label><input type="checkbox" name="statut[]" value="<?= $s ?>" <?= chk('statut', $s) ?>><span><?= $s ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field">
        <label class="flabel">Product</label>
        <div class="checkgroup">
          <?php foreach (['Sphere','KIT','CRM','RMC'] as $pr): ?>
            <label><input type="checkbox" name="product[]" value="<?= $pr ?>" <?= chk('product', $pr) ?>><span><?= $pr ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field">
        <label class="flabel">Nombre de Tickets</label>
        <div class="range-row">
          <input type="number" name="ticket_min" placeholder="Min" min="0" value="<?= htmlspecialchars($_POST['ticket_min'] ?? '') ?>">
          <span>→</span>
          <input type="number" name="ticket_max" placeholder="Max" min="0" value="<?= htmlspecialchars($_POST['ticket_max'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label class="flabel">Chiffre d'affaires (Montant)</label>
        <div class="range-row">
          <input type="number" name="ca_min" placeholder="Min" min="0" value="<?= htmlspecialchars($_POST['ca_min'] ?? '') ?>">
          <span>→</span>
          <input type="number" name="ca_max" placeholder="Max" min="0" value="<?= htmlspecialchars($_POST['ca_max'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label class="flabel">Date d'abonnement</label>
        <div class="range-row">
          <input type="date" name="date_from" value="<?= htmlspecialchars($_POST['date_from'] ?? '') ?>">
          <span>→</span>
          <input type="date" name="date_to" value="<?= htmlspecialchars($_POST['date_to'] ?? '') ?>">
        </div>
      </div>

      <div class="btn-row">
        <button type="reset" class="btn-reset">Reset</button>
        <button type="submit" class="btn-submit">Lancer la requête →</button>
      </div>

    </form>

    <!-- ── RIGHT PANEL ── -->
    <div class="right-panel">

      <!-- Query output -->
      <div class="query-box">
        <div class="box-header">
          <div class="box-title"><div class="dot"></div> Requête $match générée</div>
          <?php if ($query_string): ?>
            <button class="copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('qcode').innerText);this.innerText='Copié ✓'">Copier</button>
          <?php endif; ?>
        </div>
        <?php if ($query_string): ?>
          <pre class="result-code" id="qcode"><?= htmlspecialchars($query_string) ?></pre>
        <?php else: ?>
          <div class="empty-hint">Remplissez les filtres et cliquez sur <strong>Lancer la requête</strong></div>
        <?php endif; ?>
      </div>

      <!-- Results table -->
      <?php if ($results !== null): ?>
      <div class="results-box">
        <div class="box-header">
          <div class="box-title"><div class="dot"></div> Résultats</div>
          <span class="count-badge"><?= $count ?> client<?= $count > 1 ? 's' : '' ?> trouvé<?= $count > 1 ? 's' : '' ?></span>
        </div>

        <?php if (count($results) > 0): ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nom</th>
                <th>Raison Sociale</th>
                <th>Pays</th>
                <th>Statut</th>
                <th>Product</th>
                <th>CA</th>
                <th>Tickets</th>
                <th>Chargé</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $row):
                $statut = $row['statut'] ?? '';
                $pillClass = match(strtolower($statut)) {
                  'prospect'        => 'pill-prospect',
                  'client'          => 'pill-client',
                  'client revenant' => 'pill-revenant',
                  'ancien client'   => 'pill-ancien',
                  default           => ''
                };
                $ca = $row['chiffre_affaires'] ?? [];
                $ca_str = isset($ca['montant'])
                  ? number_format($ca['montant'], 0, ',', ' ') . ' ' . ($ca['devise'] ?? '')
                  : '—';
              ?>
              <tr>
                <td><?= htmlspecialchars($row['name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['Raison_Social'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['country'] ?? '—') ?></td>
                <td><span class="pill <?= $pillClass ?>"><?= htmlspecialchars($statut) ?></span></td>
                <td><?= htmlspecialchars($row['Product'] ?? '—') ?></td>
                <td><?= htmlspecialchars($ca_str) ?></td>
                <td><?= htmlspecialchars($row['Nombre_ticket'] ?? '0') ?></td>
                <td><?= htmlspecialchars($row['Chargé_client'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="no-results">Aucun client ne correspond à ces critères.</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>