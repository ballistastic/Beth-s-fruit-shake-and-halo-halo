<?php
session_start();

// ─── Initialize session data ───────────────────────────────────────────────────
if (!isset($_SESSION['sales_log'])) {
    $_SESSION['sales_log']   = [];
    $_SESSION['total_sales'] = 0.00;
}

// ─── Handle reset ───────────────────────────────────────────────────────────────
if (isset($_POST['reset'])) {
    $_SESSION['sales_log']   = [];
    $_SESSION['total_sales'] = 0.00;
}

// ─── Process order ──────────────────────────────────────────────────────────────
$message = '';
$previewMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reset'])) {
    // 1) Define prices
    $itemPrices = [
        'buko_shake'   => 35.00,
        'mango_shake'  => 35.00,
        'banana_shake' => 35.00,
        'halo_halo'    => 35.00,
        'add_on'       => 10.00,
    ];

    // 2) Pull posted arrays, fallback to empty arrays
    $items       = (isset($_POST['item'])          && is_array($_POST['item']))        ? $_POST['item']          : [];
    $qtys        = (isset($_POST['quantity'])      && is_array($_POST['quantity']))    ? $_POST['quantity']      : [];
    $addons      = (isset($_POST['add_on_qty'])    && is_array($_POST['add_on_qty']))  ? $_POST['add_on_qty']    : [];
    $amountGiven = isset($_POST['amount_given'])                                       ? floatval($_POST['amount_given']) : 0.00;

    $grandTotal = 0.00;
    $lines      = [];

    // 3) Build each line
    for ($i = 0; $i < count($items); $i++) {
        $itemKey = $items[$i];
        $q       = max(0, intval($qtys[$i]));
        $a_q     = max(0, intval($addons[$i]));

        // skip empty lines
        if ($q === 0 && $a_q === 0) {
            continue;
        }

        $unitPrice    = isset($itemPrices[$itemKey]) ? $itemPrices[$itemKey] : 0.00;
        $itemTotal    = $unitPrice * $q;
        $addonTotal   = $itemPrices['add_on'] * $a_q;
        $lineTotal    = $itemTotal + $addonTotal;

        $grandTotal  += $lineTotal;
        $lines[]      = [
            'time'       => date('Y-m-d H:i:s'),
            'item'       => $itemKey,
            'quantity'   => $q,
            'add_on_qty' => $a_q,
            'total'      => $lineTotal,
        ];
    }

    // Handle preview request
    if (isset($_POST['preview'])) {
        if (empty($lines)) {
            $previewMessage = "No items selected for preview.";
        } else {
            $parts = [];
            foreach ($lines as $ln) {
                $name = ucfirst(str_replace('_',' ',$ln['item']));
                $part = "{$name} x{$ln['quantity']}";
                if ($ln['add_on_qty'] > 0) {
                    $part .= " + Add‑On x{$ln['add_on_qty']}";
                }
                $part .= " → P" . number_format($ln['total'],2);
                $parts[] = $part;
            }
            $previewMessage = implode("<br>", $parts)
                             . "<br><strong>Preview Total: P" . number_format($grandTotal,2) . "</strong>";
        }
    }
    // Handle actual order processing
    elseif (!isset($_POST['preview'])) {
        // 4) Check payment
        $change = $amountGiven - $grandTotal;
        if (empty($lines)) {
            $message = "No items selected.";
        } elseif ($change < 0) {
            $message = "Insufficient amount. You need P" . number_format(abs($change),2) . " more.";
        } else {
            // 5) Commit to session
            foreach ($lines as $ln) {
                $_SESSION['sales_log'][] = $ln;
            }
            $_SESSION['total_sales'] += $grandTotal;

            // 6) Build confirmation text
            $parts = [];
            foreach ($lines as $ln) {
                $name = ucfirst(str_replace('_',' ',$ln['item']));
                $part = "{$name} x{$ln['quantity']}";
                if ($ln['add_on_qty'] > 0) {
                    $part .= " + Add‑On x{$ln['add_on_qty']}";
                }
                $part .= " → P" . number_format($ln['total'],2);
                $parts[] = $part;
            }
            $message = implode("<br>", $parts)
                     . "<br><strong>Order Total: P" . number_format($grandTotal,2) . "</strong>"
                     . "<br>Given: P" . number_format($amountGiven,2)
                     . " | Change: P" . number_format($change,2);
        }
    }
}

// ─── Compute trending item ──────────────────────────────────────────────────────
$trendingText = '';
if (!empty($_SESSION['sales_log'])) {
    $counts = [];
    foreach ($_SESSION['sales_log'] as $sale) {
        $key = $sale['item'] . ($sale['add_on_qty']>0 ? '_addon' : '_only');
        if (!isset($counts[$key])) {
            $counts[$key] = 0;
        }
        $counts[$key] += $sale['quantity'];
    }
    // find max
    arsort($counts);
    $topKey = key($counts);
    list($itemKey, $suffix) = explode('_', $topKey, 2);
    $itemName = ucfirst(str_replace('_',' ',$itemKey));
    if ($suffix === 'addon') {
        $trendingText = "Most Trending Item: {$itemName} with Add‑On";
    } else {
        $trendingText = "Most Trending Item: {$itemName} only";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Beth's Fruit Shake 'N Halo-Halo</title>
  <style>
    body { font-family: Arial,sans-serif; margin:20px; }
    form { margin-bottom:30px; }
    .row { display:flex; gap:10px; margin-bottom:5px; }
    .row select, .row .input-group { display:flex; flex-direction:column; }
    .row input { padding:5px; }
    .input-group input { width:60px; }
    .message { background:#eef; padding:10px; margin-bottom:20px; }
    .preview { background:#ffe; padding:10px; margin-bottom:20px; border:1px dashed #ccc; }
    ul { list-style:none; padding:0; }
    li { margin:4px 0; }
    .total, .trending { font-weight:bold; margin-top:20px; }
    button.add { margin-bottom:10px; }
    .button-group { margin-top:10px; }
    .button-group input { margin-right:10px; }
  </style>
</head>
<body>
  <h1>Beth's Fruit Shake 'N Halo-Halo Register</h1>

  <form method="POST" action="">
    <div id="items-container">
      <div class="row">
        <select name="item[]">
          <option value="buko_shake">Buko Shake – P35</option>
          <option value="mango_shake">Mango Shake – P35</option>
          <option value="banana_shake">Banana Shake – P35</option>
          <option value="halo_halo">Halo‑Halo – P35</option>
        </select>
        <div class="input-group">
          <label>Quantity</label>
          <input type="number" name="quantity[]" value="1" min="0" title="Qty">
        </div>
        <div class="input-group">
          <label>Add‑On Qty</label>
          <input type="number" name="add_on_qty[]" value="0" min="0" title="Add‑On Qty">
        </div>
      </div>
    </div>

    <button type="button" class="add" onclick="
      var c = document.getElementById('items-container');
      var r = c.firstElementChild.cloneNode(true);
      r.querySelectorAll('input').forEach(i=>i.value='0');
      r.querySelector('select').selectedIndex = 0;
      c.appendChild(r);
    ">+ Add Item</button>
    <br><br>

    <label>Amount Given (P):
      <input type="number" name="amount_given" step="0.01" min="0" required>
    </label>
    <br><br>

    <div class="button-group">
      <input type="submit" name="preview" value="Preview Order">
      <input type="submit" value="Process Order">
      <input type="submit" name="reset" value="Reset Sales">
    </div>
  </form>

  <?php if ($previewMessage !== ''): ?>
    <div class="preview"><?php echo $previewMessage; ?></div>
  <?php endif; ?>

  <?php if ($message !== ''): ?>
    <div class="message"><?php echo $message; ?></div>
  <?php endif; ?>

  <h2>Sales Summary</h2>
  <?php if (empty($_SESSION['sales_log'])): ?>
    <p>No sales yet.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($_SESSION['sales_log'] as $sale): ?>
        <li>
          <?php echo date('H:i:s', strtotime($sale['time'])); ?> –
          <?php echo ucfirst(str_replace('_',' ',$sale['item'])); ?>
          x<?php echo $sale['quantity']; ?>
          <?php if ($sale['add_on_qty']>0): ?>
            + Add‑On x<?php echo $sale['add_on_qty']; ?>
          <?php endif; ?>
          : P<?php echo number_format($sale['total'],2); ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="total">Daily Total: P<?php echo number_format($_SESSION['total_sales'],2); ?></div>
    <?php if ($trendingText): ?>
      <div class="trending"><?php echo $trendingText; ?></div>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>