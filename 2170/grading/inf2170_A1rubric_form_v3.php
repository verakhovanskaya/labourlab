<?php
/*
 INF2170 Content Audit Rubric Helper
 Single-file PHP form to score the assignment, capture TA comments,
 and generate a paste-ready feedback block.

 Deployment:
 - Save as inf2170_rubric_form.php on your PHP-enabled host.
 - Visit the URL to use. No database required.
*/

// ---------- Config ----------
$CRITERIA = [
  ["id"=>"raw",   "title"=>"1) Raw Crawl Data",            "max"=>10],
  ["id"=>"clean", "title"=>"2) Cleaned & Grouped Spreadsheet", "max"=>30],
  ["id"=>"audit", "title"=>"3) Audit (Ratings & Findings)",    "max"=>20],
  ["id"=>"recs",  "title"=>"4) Recommendations Report",        "max"=>40],
];

$GRADE_BANDS = [
  ["label"=>"A+", "min"=>90, "max"=>100],
  ["label"=>"A",  "min"=>85, "max"=>89],
  ["label"=>"A-", "min"=>80, "max"=>84],
  ["label"=>"B+", "min"=>77, "max"=>79],
  ["label"=>"B",  "min"=>73, "max"=>76],
  ["label"=>"B-", "min"=>70, "max"=>72],
  ["label"=>"FZ", "min"=>0,  "max"=>69]
];

$ANCHORS = [
  "raw" => [
  "A"  => "Crawl export is complete, functional, and human‑readable; URLs and metadata intact; opens cleanly. (9–10 pts)",
  "FZ" => "Missing, unreadable, or not produced by Screaming Frog/equivalent. (0–4 pts)"
],
  "clean" => [
    "A"  => "Clear hierarchy & grouping; consistent enumeration; concise labels; redundancies handled; structure evident at a glance. (27–30 pts)",
    "A-/B+"  => "Generally clear; some inconsistent IDs/groups; readable overall. (23–26 pts)",
    "B/B-"  => "Partially cleaned but confusing/uneven; enumeration/grouping incomplete. (18–22 pts)",
    "FZ" => "Mostly unedited crawl or no discernible structure. (0–17 pts)"
  ],
  "audit" => [
    "A"  => "Criteria explicit; evaluations consistent; distinguishes crawler vs content issues; balanced, evidence‑based. (18–20 pts)",
    "A-/B+"  => "Criteria implied; mostly consistent; reasoning adequate though uneven. (15–17 pts)",
    "B/B-"  => "Limited or repetitive; ratings inconsistent or mostly descriptive. (12–14 pts)",
    "FZ" => "No criteria/interpretation; incoherent/absent evaluation. (0–11 pts)"
  ],
  "recs" => [
    "A"  => "Clear, prioritized, evidence‑based recommendations; concise, professional synthesis; strong design judgment. (36–40 pts)",
    "A-/B+"  => "Logical and relevant; linkage to evidence somewhat uneven. (31–35 pts)",
    "B/B-"  => "Generic or weakly linked; minimal synthesis. (27–30 pts)",
    "FZ" => "Off‑topic, fragmented, or missing substantive recommendations. (0–26 pts)"
  ]
];

function sanitize_text($s) {
  return htmlspecialchars(trim($s ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function clamp($val, $min, $max) {
  return max($min, min($max, $val));
}
function compute_grade_label($pct, $bands) {
  foreach ($bands as $b) {
    if ($pct >= $b["min"] && $pct <= $b["max"]) return $b["label"];
  }
  return "FZ";
}

// Handle POST
$results = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $student   = sanitize_text($_POST["student"] ?? "");
  $section   = sanitize_text($_POST["section"] ?? "");
  $ta_name   = sanitize_text($_POST["ta_name"] ?? "");
  $date_str  = sanitize_text($_POST["date_str"] ?? date("Y-m-d"));

  $scores = [];
  $comments = [];
  $levels = [];
  $allA = true;

  $total = 0;
  $total_max = 0;

  foreach ($CRITERIA as $c) {
    $id = $c["id"];
    $max = $c["max"];
    $pts = isset($_POST["pts_$id"]) ? floatval($_POST["pts_$id"]) : 0;
    $pts = clamp($pts, 0, $max);
    $lvl = sanitize_text($_POST["lvl_$id"] ?? (($id === "raw") ? "A" : "A-/B+")); // default
    $com = sanitize_text($_POST["com_$id"] ?? "");

    $scores[$id] = $pts;
    $levels[$id] = $lvl;
    $comments[$id] = $com;

    if ($lvl !== "A") { $allA = false; }

    $total += $pts;
    $total_max += $max;
  }

  $overall_comment = sanitize_text($_POST["overall_comment"] ?? "");
  $consider_a_plus = isset($_POST["consider_a_plus"]);

  $pct = ($total_max > 0) ? floor(($total / $total_max) * 100) : 0;
  $grade = compute_grade_label($pct, $GRADE_BANDS);

  // A+ policy: only if all categories are A and checkbox ticked
  if ($grade === "A" || $grade === "A+" || $pct >= 90) {
    if ($allA && $consider_a_plus) {
      $grade = "A+";
    } else {
      // Keep computed grade; ensure not showing A+ if conditions not met
      if ($grade === "A+" && !$allA) $grade = "A";
    }
  }

  // Build paste‑ready block
  $lines = [];
  $lines[] = "INF2170 – Content Audit Feedback";
  if ($student !== "") $lines[] = "Student: $student";
  if ($section !== "") $lines[] = "Section: $section";
  if ($ta_name !== "") $lines[] = "TA: $ta_name";
  $lines[] = "Date: $date_str";
  $lines[] = "";
  $lines[] = "Scores";
  foreach ($CRITERIA as $c) {
    $id = $c["id"]; $title = $c["title"]; $max = $c["max"];
    $lvl = $levels[$id];
    $pts = $scores[$id];
    $anchor = $ANCHORS[$id][$lvl] ?? "";
    $lines[] = "- $title: $pts/$max (Level: $lvl)";
    if ($anchor !== "") $lines[] = "  · Anchor: $anchor";
    if ($comments[$id] !== "") $lines[] = "  · Comment: ".$comments[$id];
  }
  $lines[] = "";
  $lines[] = "Total: $total/$total_max ($pct%)";
  $lines[] = "Grade: $grade";
  if ($overall_comment !== "") {
    $lines[] = "";
    $lines[] = "Overall Comment";
    $lines[] = $overall_comment;
  }
  if ($grade === "A" && $allA && !$consider_a_plus) {
    $lines[] = "";
    $lines[] = "Note: Eligible for A+ consideration (all categories scored at A).";
  }

  $feedback_block = implode("\n", $lines);

  $results = [
    "total"=>$total,
    "pct"=>$pct,
    "grade"=>$grade,
    "feedback"=>$feedback_block
  ];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>INF2170 Rubric Helper</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --bg:#0b0c10; --card:#15171c; --ink:#eaeaea; --muted:#b7bcc6; --accent:#78d0b6; --warn:#f5a97f; --bad:#f38ba8; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:var(--bg); color:var(--ink); }
    .wrap { max-width: 980px; margin: 24px auto; padding: 0 16px; }
    .card { background: var(--card); border-radius: 16px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); margin-bottom: 16px; }
    h1, h2, h3 { margin: 0 0 10px; }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    label { display:block; font-weight:600; margin-bottom:6px; color: var(--muted); }
    input[type="text"], input[type="number"], textarea, select {
      width:100%; padding:10px 12px; border-radius:10px; border:1px solid #2a2f3a; background:#0f1116; color:var(--ink);
    }
    textarea { min-height: 80px; }
    .crit { border:1px solid #2a2f3a; border-radius: 14px; padding: 12px; margin-top: 10px; }
    .flex { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .grid3 { display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; }
    .pill { padding:8px 12px; border-radius: 999px; background:#0f1116; border:1px solid #2a2f3a; cursor:pointer; }
    .btn { appearance:none; border:none; border-radius:12px; padding:12px 16px; cursor:pointer; font-weight:700; }
    .btn-primary { background: var(--accent); color:#0b0c10; }
    .btn-ghost { background: transparent; color: var(--ink); border:1px solid #2a2f3a; }
    .right { text-align:right; }
    .muted { color: var(--muted); }
    .scoreline { font-weight:700; }
    .kbd { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; background:#0f1116; border:1px solid #2a2f3a; padding:2px 6px; border-radius:6px; }
    .out { white-space: pre-wrap; background:#0f1116; border:1px dashed #2a2f3a; border-radius: 12px; padding: 12px; }
    .badge { padding:4px 8px; border-radius:8px; font-weight:700; }
    .badge-a { background:#10352c; color:#a0eacf; }
    .badge-b { background:#272a34; color:#d6d9e0; }
    .badge-c { background:#332a14; color:#f1d7a2; }
    .badge-f { background:#3b1f24; color:#ffb4c0; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>INF2170 – Content Audit Rubric Helper</h1>
    <p class="muted">Scaffolded scoring with anchors. A+ is only considered if <strong>all categories are A</strong> and the TA ticks ‘A+ consideration’.</p>
  </div>

  <form method="post" class="card">
    <h2>Header</h2>
    <div class="row">
      <div>
        <label>Student</label>
        <input type="text" name="student" placeholder="Name or ID">
      </div>
      <div>
        <label>Section</label>
        <input type="text" name="section" placeholder="e.g., Friday 9am">
      </div>
      <div>
        <label>TA Name</label>
        <input type="text" name="ta_name" placeholder="Your name">
      </div>
      <div>
        <label>Date</label>
        <input type="text" name="date_str" value="<?php echo date('Y-m-d'); ?>">
      </div>
    </div>

    <h2 style="margin-top:16px;">Criteria</h2>
    <?php foreach ($CRITERIA as $c): 
      $id=$c["id"]; $title=$c["title"]; $max=$c["max"];
      $anchors=$ANCHORS[$id];
    ?>
      <div class="crit">
        <h3><?php echo $title; ?> <span class="muted">(max <?php echo $max; ?> pts)</span></h3>
        <div class="row">
          <div>
            <label>Evaluation Level</label>
            <select name="lvl_<?php echo $id; ?>" onchange="syncPreset('<?php echo $id; ?>', <?php echo $max; ?>, this.value)">
              <?php if ($id === 'raw'): ?>
                <option value="A">A (Excellent)</option>
                <option value="FZ">FZ (Fail)</option>
              <?php else: ?>
                <option value="A">A (Excellent)</option>
                <option value="A-/B+">B (Satisfactory)</option>
                <option value="B/B-">C (Minimal/Incomplete)</option>
                <option value="FZ">FZ (Fail)</option>
              <?php endif; ?>
            </select>
            <div class="muted" style="margin-top:8px;">
              <div><span class="badge badge-a">A</span> <?php echo $anchors['A']; ?></div>
              <div><span class="badge badge-b">B</span> <?php echo $anchors['A-/B+']; ?></div>
              <div><span class="badge badge-c">C</span> <?php echo $anchors['B/B-']; ?></div>
              <div><span class="badge badge-f">FZ</span> <?php echo $anchors['FZ']; ?></div>
            </div>
          </div>
          <div>
            <label>Points Awarded (0–<?php echo $max; ?>)</label>
            <input type="number" name="pts_<?php echo $id; ?>" id="pts_<?php echo $id; ?>" min="0" max="<?php echo $max; ?>" step="0.5" value="<?php echo $max; ?>" oninput="recalc()">
            <div class="flex" style="margin-top:8px;">
              <button class="pill" type="button" onclick="preset('<?php echo $id; ?>', <?php echo $max; ?>, 'A')">Fill A‑range</button>
              <?php if ($id !== 'raw'): ?>
                <button class="pill" type="button" onclick="preset('<?php echo $id; ?>', <?php echo $max; ?>, 'A-/B+')">Fill A-/B+‑range</button>
                <button class="pill" type="button" onclick="preset('<?php echo $id; ?>', <?php echo $max; ?>, 'B/B-')">Fill B/B-‑range</button>
              <?php endif; ?>
              <button class="pill" type="button" onclick="preset('<?php echo $id; ?>', <?php echo $max; ?>, 'FZ')">Fill FZ‑range</button>
            </div>
          </div>
        </div>
        <div style="margin-top:8px;">
          <label>Criterion Comment (optional)</label>
          <textarea name="com_<?php echo $id; ?>" placeholder="Targeted feedback tied to the anchor..."></textarea>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="crit">
      <div class="flex">
        <label><input type="checkbox" name="consider_a_plus"> Consider for A+ (all categories must be A)</label>
      </div>
      <label>Overall Comment (optional)</label>
      <textarea name="overall_comment" placeholder="Synthesis and next steps..."></textarea>
    </div>

    <div class="flex right">
      <button type="submit" class="btn btn-primary">Generate Feedback</button>
    </div>
  </form>

  <?php if ($results): ?>
    <div class="card">
      <h2>Result</h2>
      <p class="scoreline">Total: <?php echo $results["total"]; ?>/100 &nbsp; | &nbsp; Percent: <?php echo $results["pct"]; ?>% &nbsp; | &nbsp; Grade: <span class="kbd"><?php echo $results["grade"]; ?></span></p>
      <h3>Paste‑Ready Feedback</h3>
      <div class="out" id="out"><?php echo nl2br($results["feedback"]); ?></div>
      <div class="flex" style="margin-top:10px;">
        <button class="btn btn-ghost" onclick="copyOut()">Copy to Clipboard</button>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>How it works</h2>
    <ul>
      <li>Select a <strong>performance level</strong> to align comments with an anchor (A/[A-/B+]/[B/B-]/FZ).</li>
      <li>Enter <strong>points</strong> directly, or use the quick “Fill range” buttons to auto‑fill typical values.</li>
      <li>Tick <strong>A+ consideration</strong> only if <em>all categories are A</em>. Final label follows the policy.</li>
      <li>Click <strong>Generate Feedback</strong> to produce a clean text block for Canvas comments.</li>
    </ul>
    <p class="muted">No data is stored server‑side. Refresh to clear.</p>
  </div>
</div>

<script>
// Typical ranges based on anchors
const ranges = {
  "raw":   { "A":[9,10], "FZ":[0,4] },
  "clean": { "A":[27,30], "A-/B+":[23,26], "B/B-":[18,22], "FZ":[0,17] },
  "audit": { "A":[18,20], "A-/B+":[15,17], "B/B-":[12,14], "FZ":[0,11] },
  "recs":  { "A":[36,40], "A-/B+":[31,35], "B/B-":[27,30], "FZ":[0,26] }
};

function randInRange(min, max) {
  // pick midpoint, not random, for consistency
  return (min + max) / 2;
}
function preset(id, max, level) {
  const r = ranges[id][level];
  const val = Math.min(max, Math.max(0, randInRange(r[0], r[1])));
  document.getElementById("pts_"+id).value = val;
  const sel = document.querySelector(`select[name="lvl_${id}"]`);
  if (sel) sel.value = level;
  recalc();
}
function syncPreset(id, max, level) {
  // When level changes, gently nudge points into its band if out of band
  const input = document.getElementById("pts_"+id);
  const r = ranges[id][level];
  const cur = parseFloat(input.value || "0");
  if (cur < r[0] || cur > r[1]) {
    input.value = (r[0]+r[1])/2;
  }
  recalc();
}
function recalc() {
  // Client-side live calc could be added; server does authoritative calc
}
function copyOut() {
  const el = document.createElement("textarea");
  el.value = document.getElementById("out").innerText;
  document.body.appendChild(el);
  el.select();
  document.execCommand("copy");
  document.body.removeChild(el);
  alert("Feedback copied!");
}
</script>
</body>
</html>
