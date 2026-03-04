<?php require "config/config.php"; ?>
<?php require "includes/header.php"; ?>

<?php 
if (isset($_POST['submit'])) {
    // base query
    $sql = "SELECT j.*, u.fullname AS company_name, u.img 
        FROM jobs j
        JOIN users u ON j.company_id = u.id
        WHERE j.status = 1 AND j.application_deadline >= CURDATE()";

    // user inputs
    $job_title = trim($_POST['job-title'] ?? '');
    $job_region = trim($_POST['job-region'] ?? '');
    $job_type = trim($_POST['job-type'] ?? '');

    /* ---- Pagination ---- */
    $perPage = 12;
    $page = isset($_POST['page']) && ctype_digit((string)$_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $perPage;


    // store searched keyword
    if (!empty($job_title)) {
        $insertSearch = $conn->prepare("INSERT INTO searches (keyword) VALUES (:keyword)");
        $insertSearch->bindParam(':keyword', $job_title);
        $insertSearch->execute();
    }

    if (!empty($job_title)) $sql .= " AND j.job_title LIKE :job_title";
    if (!empty($job_region)) $sql .= " AND j.job_region LIKE :job_region";
    if (!empty($job_type))  $sql .= " AND j.job_type LIKE :job_type";

    // Total count for pagination
    $countSql = "SELECT COUNT(*) 
                FROM jobs j
                JOIN users u ON j.company_id = u.id
                WHERE j.status = 1 AND j.application_deadline >= CURDATE()";
    if (!empty($job_title))  $countSql .= " AND j.job_title LIKE :job_title";
    if (!empty($job_region)) $countSql .= " AND j.job_region LIKE :job_region";
    if (!empty($job_type))   $countSql .= " AND j.job_type LIKE :job_type";

    $countStmt = $conn->prepare($countSql);
    if (!empty($job_title))  $countStmt->bindValue(':job_title', "%$job_title%");
    if (!empty($job_region)) $countStmt->bindValue(':job_region', "%$job_region%");
    if (!empty($job_type))   $countStmt->bindValue(':job_type', "%$job_type%");
    $countStmt->execute();
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    // Paged query
    $sql .= " LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    if (!empty($job_title))  $stmt->bindValue(':job_title', "%$job_title%");
    if (!empty($job_region)) $stmt->bindValue(':job_region', "%$job_region%");
    if (!empty($job_type))   $stmt->bindValue(':job_type', "%$job_type%");
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $searchRes = $stmt->fetchAll(PDO::FETCH_OBJ);

} else {
    header("location: " . APPURL);
    exit;
}

// for the type checklist
$jobTypes = ["Full Time","Part Time","Contract","Casual","Fixed Term"];

/** helper: parse salary text to (min,max,period) for data-attrs */
function parse_salary_triplet($text){
  $t = (string)$text;
  $lt = mb_strtolower($t,'UTF-8');
  $period = 'unknown';
  if (strpos($lt,'per hour') !== false)   $period = 'hourly';
  elseif (strpos($lt,'per month') !== false) $period = 'monthly';
  elseif (strpos($lt,'annually') !== false || strpos($lt,'per year') !== false || strpos($lt,'per annum') !== false || strpos($lt,'yearly') !== false) $period = 'yearly';

  preg_match_all('/\d[\d,\.]*/', $t, $m);
  $nums = array_map(function($s){ return (float)str_replace([','],[''],$s); }, $m[0] ?? []);
  $min = $nums[0] ?? null;
  $max = $nums[1] ?? null;
  if ($max !== null && $max < $min) { $tmp=$min; $min=$max; $max=$tmp; }
  return [$min, $max, $period];
}
?>

<style>
.site-section{ padding-top:2rem; }

/* ===== Hero (consistent with Companies / Find Jobs) ===== */
.companies-hero{
  position:relative; background-size:cover; background-position:center;
  padding: 60px 0; overflow:hidden;
}
.companies-hero .overlay-dark{ position:absolute; inset:0;
  background:
    radial-gradient(1200px 400px at 10% -10%, rgba(99,102,241,.22), transparent 60%),
    radial-gradient(1200px 400px at 90% 0%, rgba(6,182,212,.18), transparent 60%),
    linear-gradient(180deg, rgba(2,6,23,.65), rgba(2,6,23,.80));
}
.companies-hero .hero-inner{ position:relative; z-index:2; }
.ch-eyebrow{ color:#c7d2fe; text-transform:uppercase; letter-spacing:.16em; font-weight:700; font-size:.75rem; }
.ch-title{ color:#fff; font-weight:800; }
.ch-sub{ color:#e2e8f0; }

/* layout */
.results-wrap{ display:flex; gap:24px; }
@media (max-width: 991.98px){ .results-wrap{ flex-direction:column; } }

.filter-col{ flex:0 0 280px; }
@media (max-width: 991.98px){ .filter-col{ flex:1 1 auto; } }

.list-col{ flex:1 1 auto; }

/* filter card */
.filters-card{
  background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.06);
  padding:16px;
}
.filters-card h6{ font-weight:700; margin:0 0 8px; }
.filters-card .small{ color:#6c757d; }
.filters-card .form-group{ margin-bottom:14px; }
.filters-divider{ height:1px; background:#e9ecef; margin:12px 0; }
.sticky-md{ position:sticky; top:16px; }

/* job list styles */
.job-listings{ margin:0; }
.job-card{
  position:relative; border-radius:14px; background:#fff;
  box-shadow:0 2px 10px rgba(0,0,0,.04); transition:transform .18s ease, box-shadow .18s ease;
  overflow:hidden; margin-bottom:16px;
}
.job-card:hover{ transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.08); }
.job-link{
  display:grid; grid-template-columns:72px 1fr auto; gap:16px; text-decoration:none; color:inherit;
  padding:16px 18px 16px 0;
}
.job-card .accent{ content:""; position:absolute; left:0; top:0; bottom:0; width:0; background:linear-gradient(90deg,#06b6d4,#6366f1); transition:width .28s ease; }
.job-card:hover .accent{ width:6px; }

/* logo */
.job-media{ display:flex; align-items:center; justify-content:center; width:72px; }
.job-logo{
  width:66px; height:66px; object-fit:cover; border-radius:12px; border:1px solid rgba(0,0,0,.06); background:#f7f8fa;
}
.job-logo-initial{
  width:66px; height:66px; border-radius:12px; display:flex; align-items:center; justify-content:center;
  color:#fff; font-weight:800; font-size:24px; line-height:1; user-select:none;
  border:1px solid rgba(0,0,0,.06); box-shadow:0 2px 8px rgba(0,0,0,.06);
}

/* text */
.job-body{ display:flex; flex-direction:column; justify-content:center; min-width:0; }
.job-title-row{ display:flex; align-items:center; gap:10px; }
.job-title{ font-size:1.125rem; line-height:1.25; font-weight:700; margin:0;
  display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
.job-type{ white-space:nowrap; }
.job-meta{ color:#6c757d; font-size:.95rem; display:flex; align-items:center; gap:8px; }
.job-company{ font-weight:600; color:#6c757d; }
.sep{ opacity:.5; }
.job-sub{ color:#98a2b3; font-size:.9rem; }

/* aside */
.job-aside{ display:flex; flex-direction:column; align-items:flex-end; justify-content:center; gap:8px; min-width:180px; }
.due-chip{ font-size:.875rem; font-weight:700; padding:6px 10px; border-radius:999px; background:#eef2ff; color:#3730a3; }
.due-ok{ background:#ecfdf5; color:#065f46; }
.due-warn{ background:#fff7ed; color:#9a3412; }
.due-danger{ background:#fef2f2; color:#991b1b; }
.deadline{ color:#6c757d; }
.deadline-track{ position:relative; width:160px; height:6px; background:#eef2f7; border-radius:999px; overflow:hidden; }
.deadline-fill{ height:100%; background:linear-gradient(90deg,#22c55e,#f59e0b,#ef4444); }

.job-card a, .job-card a:hover, .job-card a:focus, .job-card a:active{ text-decoration:none !important; }

/* responsive */
@media (max-width: 768px){
  .job-link{ grid-template-columns:56px 1fr; padding-right:14px; }
  .job-aside{ grid-column:1 / -1; align-items:flex-start; margin-top:8px; }
  .deadline-track{ width:100%; }
}

/* range slider labels */
.range-display{ font-size:.875rem; color:#6c757d; }

<style>
  /* Search bar alignment + consistent heights */
  .searchbar-row .form-control,
  .searchbar-row select,
  .searchbar-row .btn {
    height: 48px;            /* same height for all controls */
  }
  .searchbar-row .btn {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
  }
  /* remove extra gaps on desktop */
  @media (min-width: 992px){
    .searchbar-row > [class*="col-"] { margin-bottom: 0 !important; }
  }
</style>

</style>

<!-- HERO -->
<section class="companies-hero overlay inner-page bg-image" style="background-image:url('images/tst.jpg');" id="home-section">
  <div class="overlay-dark"></div>
  <div class="container hero-inner text-center">
    <div class="ch-eyebrow mb-1">Jobs</div>
    <h2 class="ch-title mb-2">Search Results</h2>
    <p class="ch-sub mb-0">Refine the filters to zero in on your next role.</p>
  </div>
</section>

<section class="site-section">
  <div class="container">

    <!-- SEARCH BAR -->
<div class="row align-items-center justify-content-center">
  <div class="col-md-12">
    <form method="post" action="search.php" class="search-jobs-form" role="search" autocomplete="off" id="searchForm">
      <input type="hidden" name="page" id="pageField" value="<?php echo (int)($page ?? 1); ?>">
      <div class="row searchbar-row mb-4">
        <!-- Title -->
        <div class="col-12 col-lg-4 mb-3 mb-lg-0">
          <input
            name="job-title"
            type="text"
            class="form-control"
            placeholder="Job title/Keyword"
            value="<?php echo htmlspecialchars($job_title ?? ''); ?>">
        </div>

        <!-- Region -->
        <?php
          $regionsStmt = $conn->prepare("SELECT * FROM job_regions WHERE status = 1 ORDER BY name ASC");
          $regionsStmt->execute();
          $jobRegions = $regionsStmt->fetchAll(PDO::FETCH_OBJ);
        ?>
        <div class="col-12 col-lg-3 mb-3 mb-lg-0">
          <select name="job-region" class="form-control" title="Region">
            <option value="">Anywhere</option>
            <?php foreach ($jobRegions as $region): ?>
              <option value="<?php echo htmlspecialchars($region->code); ?>"
                      <?php if(($job_region ?? '')===$region->code) echo 'selected'; ?>>
                <?php echo htmlspecialchars($region->name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Type -->
        <div class="col-12 col-lg-3 mb-3 mb-lg-0">
          <select name="job-type" class="form-control" title="Type">
            <option<?php if(($job_type ?? '')==='Part Time') echo ' selected'; ?>>Part Time</option>
            <option<?php if(($job_type ?? '')==='Full Time') echo ' selected'; ?>>Full Time</option>
            <option<?php if(($job_type ?? '')==='Casual') echo ' selected'; ?>>Casual</option>
            <option<?php if(($job_type ?? '')==='Contract') echo ' selected'; ?>>Contract</option>
            <option<?php if(($job_type ?? '')==='Fixed Term') echo ' selected'; ?>>Fixed Term</option>
          </select>
        </div>

        <!-- Button -->
        <div class="col-12 col-lg-2">
          <button type="submit" name="submit" class="btn btn-primary text-white btn-block">
            <span class="icon-search icon mr-2"></span>Search
          </button>
        </div>
      </div>
    </form>
  </div>
</div>


    <!-- RESULTS + FILTERS -->
    <div class="results-wrap">
      <!-- LEFT: Filters -->
      <aside class="filter-col">
        <div class="filters-card sticky-md">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="mb-0">Filters</h6>
            <button class="btn btn-link btn-sm p-0" style="text-decoration: none;" id="clearFilters">Clear all</button>
          </div>

          <!-- Job Type -->
          <div class="form-group">
            <label class="d-block font-weight-bold mb-2">Job Type</label>
            <?php foreach ($jobTypes as $i => $t): ?>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input js-type" id="type<?php echo $i; ?>" value="<?php echo htmlspecialchars($t); ?>" checked>
                <label class="custom-control-label" for="type<?php echo $i; ?>"><?php echo htmlspecialchars($t); ?></label>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="filters-divider"></div>

          <!-- Work Arrangement -->
          <div class="form-group">
            <label class="d-block font-weight-bold mb-2">Work Arrangement</label>
            <?php
              $workOptions = ["Fully Remote","Hybrid (Remote + On-site)","On-site Only"];
              foreach ($workOptions as $i => $w):
            ?>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input js-work" id="work<?php echo $i; ?>" value="<?php echo htmlspecialchars($w); ?>" checked>
                <label class="custom-control-label" for="work<?php echo $i; ?>"><?php echo htmlspecialchars($w); ?></label>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="filters-divider"></div>

          <!-- Salary -->
          <div class="form-group">
            <label class="d-block font-weight-bold mb-2">Salary</label>
            <div class="form-row">
              <div class="col-6">
                <select class="form-control" id="salaryPeriod">
                  <option value="any">Any period</option>
                  <option value="hourly">Hourly</option>
                  <option value="monthly">Monthly</option>
                  <option value="yearly">Yearly</option>
                </select>
              </div>
              <div class="col-6 text-right">
                <span class="range-display"><span id="salMinOut">—</span> – <span id="salMaxOut">—</span></span>
              </div>
            </div>

            <input type="range" class="custom-range mt-2" id="salaryMin" min="0" max="100" step="1" value="0">
            <input type="range" class="custom-range" id="salaryMax" min="0" max="100" step="1" value="100">
            <small class="text-muted d-block">Only applies when a period is selected</small>
          </div>

          <div class="filters-divider"></div>

          <!-- Date Posted -->
          <div class="form-group">
            <label class="d-block font-weight-bold mb-2">Date Posted</label>
            <select class="form-control" id="datePosted">
              <option value="any">Anytime</option>
              <option value="1">Last 24 hours</option>
              <option value="3">Last 3 days</option>
              <option value="7">Last 7 days</option>
              <option value="14">Last 14 days</option>
            </select>
          </div>
        </div>
      </aside>

      <!-- RIGHT: Results -->
      <div class="list-col">
        <h4 class="mb-3">
        Total Jobs Found: <span id="total-jobs-count"><?php echo (int)$totalRows; ?></span>
        <small class="text-muted small"> — Showing <span id="shown-count"><?php echo count($searchRes); ?></span> on this page</small>
      </h4>


        <ul class="job-listings mb-5" id="filtered-results">
        <?php if (count($searchRes) > 0): ?>
          <?php foreach ($searchRes as $oneJob): ?>
            <?php
              $postedTs   = strtotime($oneJob->created_at);
              $deadlineTs = strtotime($oneJob->application_deadline);
              $nowTs      = time();
              $daysLeft   = max(0, ceil(($deadlineTs - $nowTs) / 86400));

              $typeClass = 'primary';
              if ($oneJob->job_type == 'Full Time')     $typeClass = 'success';
              elseif ($oneJob->job_type == 'Part Time') $typeClass = 'danger';
              elseif ($oneJob->job_type == 'Contract')  $typeClass = 'info';
              elseif ($oneJob->job_type == 'Casual')    $typeClass = 'secondary';

              $dueClass = $daysLeft <= 3 ? 'due-danger' : ($daysLeft <= 10 ? 'due-warn' : 'due-ok');

              list($salMin, $salMax, $salPer) = parse_salary_triplet($oneJob->salary);

              $name    = trim($oneJob->company_name ?? '');
              $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
              $imgFile = $oneJob->img ?? '';
              $fsPath  = __DIR__ . '/users/user-images/' . $imgFile;
              $hasLogo = !empty($imgFile) && @is_file($fsPath);
              $palette = ['#0d6efd','#6f42c1','#20c997','#dc3545','#fd7e14','#198754','#0dcaf0','#6c757d'];
              $bg      = $palette[hexdec(substr(md5(mb_strtolower($name,'UTF-8')),0,2)) % count($palette)];
            ?>
            <li class="job-card"
                data-type="<?php echo htmlspecialchars($oneJob->job_type); ?>"
                data-work="<?php echo htmlspecialchars($oneJob->work_arrangement ?? ''); ?>"
                data-posted="<?php echo (int)$postedTs; ?>"
                data-salmin="<?php echo $salMin !== null ? (float)$salMin : ''; ?>"
                data-salmax="<?php echo $salMax !== null ? (float)$salMax : ''; ?>"
                data-salperiod="<?php echo htmlspecialchars($salPer); ?>">
              <a class="job-link" href="<?php echo $base_url; ?>/jobs/job-single.php?id=<?php echo (int)$oneJob->id; ?>">
                <div class="accent"></div>

                <div class="job-media">
                  <?php if ($hasLogo): ?>
                    <img class="job-logo"
                         src="users/user-images/<?php echo htmlspecialchars($imgFile); ?>"
                         alt="<?php echo htmlspecialchars($name ?: 'Company'); ?> logo" loading="lazy">
                  <?php else: ?>
                    <div class="job-logo-initial" style="background: <?php echo $bg; ?>;" aria-label="<?php echo htmlspecialchars($name ?: 'Company'); ?> logo">
                      <?php echo htmlspecialchars($initial ?: '•'); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="job-body">
                  <div class="job-title-row">
                    <h2 class="job-title mb-1"><?php echo htmlspecialchars($oneJob->job_title); ?></h2>
                    <span class="badge badge-<?php echo $typeClass; ?> job-type">
                      <?php echo htmlspecialchars($oneJob->job_type); ?>
                    </span>
                  </div>

                  <div class="job-meta">
                    <span class="job-company"><?php echo htmlspecialchars($oneJob->company_name); ?></span>
                    <span class="sep">•</span>
                    <span class="job-loc"><i class="icon-room"></i> <?php echo htmlspecialchars($oneJob->job_region); ?></span>
                    <?php if (!empty($oneJob->work_arrangement)): ?>
                      <span class="sep">•</span>
                      <span><?php echo htmlspecialchars($oneJob->work_arrangement); ?></span>
                    <?php endif; ?>
                  </div>

                  <div class="job-sub">
                    <span class="posted">Posted: <?php echo date('M d, Y', $postedTs); ?></span>
                  </div>
                </div>

                <div class="job-aside">
                  <span class="due-chip <?php echo $dueClass; ?>">
                    <?php echo $daysLeft > 0 ? $daysLeft . ' day' . ($daysLeft>1?'s':'') . ' left' : 'Closed'; ?>
                  </span>
                  <small class="deadline">
                    <i class="fa fa-clock" aria-hidden="true"></i>
                    Apply before: <?php echo date('M d, Y', $deadlineTs); ?>
                  </small>
                  <?php
                    $totalWindow = max(1, $deadlineTs - $postedTs);
                    $elapsed     = max(0, min($totalWindow, time() - $postedTs));
                    $pct         = round(($elapsed / $totalWindow) * 100);
                  ?>
                  <div class="deadline-track" aria-hidden="true">
                    <div class="deadline-fill" style="width: <?php echo $pct; ?>%;"></div>
                  </div>
                </div>
              </a>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-danger bg-danger text-white">No jobs available at the moment!</div>
        <?php endif; ?>
        </ul>
        <?php if (!empty($totalPages) && $totalPages > 1): ?>
          <nav aria-label="Search results pages">
            <ul class="pagination justify-content-center">
              <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <button type="button" class="page-link" onclick="setSearchPage(<?php echo $page - 1; ?>)">Previous</button>
              </li>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page === $i) ? 'active' : ''; ?>">
                  <button type="button" class="page-link" onclick="setSearchPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                </li>
              <?php endfor; ?>
              <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <button type="button" class="page-link" onclick="setSearchPage(<?php echo $page + 1; ?>)">Next</button>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<script>
// ---- Live filters ----
(function(){
  var list    = document.getElementById('filtered-results');
  if(!list) return;

  var items   = Array.prototype.slice.call(list.querySelectorAll('.job-card'));
  var totalEl = document.getElementById('total-jobs-count');

  // controls
  var typeBoxes = Array.prototype.slice.call(document.querySelectorAll('.js-type'));
  var workBoxes = Array.prototype.slice.call(document.querySelectorAll('.js-work'));
  var salaryPeriod = document.getElementById('salaryPeriod');
  var salMin = document.getElementById('salaryMin');
  var salMax = document.getElementById('salaryMax');
  var salMinOut = document.getElementById('salMinOut');
  var salMaxOut = document.getElementById('salMaxOut');
  var dateSel = document.getElementById('datePosted');
  var clearBtn = document.getElementById('clearFilters');

  var salaryRanges = { hourly:{min:Infinity,max:-Infinity,found:false}, monthly:{min:Infinity,max:-Infinity,found:false}, yearly:{min:Infinity,max:-Infinity,found:false} };
  items.forEach(function(li){
    var p = li.getAttribute('data-salperiod');
    var vmin = parseFloat(li.getAttribute('data-salmin'));
    var vmax = parseFloat(li.getAttribute('data-salmax'));
    if (p && salaryRanges[p] && !isNaN(vmin)) {
      salaryRanges[p].found = true;
      salaryRanges[p].min = Math.min(salaryRanges[p].min, vmin);
      if (!isNaN(vmax)) salaryRanges[p].max = Math.max(salaryRanges[p].max, vmax);
      else salaryRanges[p].max = Math.max(salaryRanges[p].max, vmin);
    }
  });

  function setSliderFor(period){
    if (period === 'any'){
      salMin.disabled = true; salMax.disabled = true;
      salMin.value = 0; salMax.value = 100;
      salMinOut.textContent = '—'; salMaxOut.textContent = '—';
      return;
    }
    var r = salaryRanges[period];
    if (!r || !r.found){
      salMin.disabled = true; salMax.disabled = true;
      salMinOut.textContent = '—'; salMaxOut.textContent = '—';
      return;
    }
    salMin.disabled = false; salMax.disabled = false;

    var min = Math.floor(r.min);
    var max = Math.ceil(r.max);
    if (max < min) { max = min; }

    salMin.min = min; salMin.max = max; salMax.min = min; salMax.max = max;
    salMin.value = min; salMax.value = max;

    salMinOut.textContent = min.toLocaleString();
    salMaxOut.textContent = max.toLocaleString();
  }

  function clampSliders(){
    var a = parseFloat(salMin.value), b = parseFloat(salMax.value);
    if (a > b){ var t=a; a=b; b=t; salMin.value=a; salMax.value=b; }
    salMinOut.textContent = isNaN(a)? '—' : a.toLocaleString();
    salMaxOut.textContent = isNaN(b)? '—' : b.toLocaleString();
  }

  function selectedValues(list){
    return list.filter(function(cb){ return cb.checked; }).map(function(cb){ return cb.value; });
  }

  function applyFilters(){
    var selTypes = selectedValues(typeBoxes);
    var selWorks = selectedValues(workBoxes);

    var filterByType = selTypes.length > 0;
    var filterByWork = selWorks.length > 0;

    var p = salaryPeriod.value;
    var useSalary = (p !== 'any') && !salMin.disabled && !salMax.disabled;
    var minV = parseFloat(salMin.value), maxV = parseFloat(salMax.value);

    var days = dateSel.value;
    var minTs = 0;
    if (days !== 'any'){
      var d = parseInt(days,10);
      if (!isNaN(d)) { minTs = Date.now()/1000 - (d*86400); }
    }

    var shown = 0;
    items.forEach(function(li){
      if (filterByType && selTypes.indexOf(li.getAttribute('data-type')) === -1){
        li.style.display = 'none'; return;
      }
      if (filterByWork && selWorks.indexOf(li.getAttribute('data-work')) === -1){
        li.style.display = 'none'; return;
      }
      if (minTs > 0){
        var posted = parseInt(li.getAttribute('data-posted'),10) || 0;
        if (posted < minTs){ li.style.display='none'; return; }
      }
      if (useSalary){
        var sp = li.getAttribute('data-salperiod');
        if (sp !== p){ li.style.display='none'; return; }
        var smin = parseFloat(li.getAttribute('data-salmin'));
        var smax = parseFloat(li.getAttribute('data-salmax'));
        if (isNaN(smin)){ li.style.display='none'; return; }
        var overlaps = (smin <= maxV) && ((isNaN(smax) ? smin : smax) >= minV);
        if (!overlaps){ li.style.display='none'; return; }
      }
      li.style.display = '';
      shown++;
    });

    // if (totalEl) totalEl.textContent = shown;
    // keep the global total (server-side count) intact
    var shownOut = document.getElementById('shown-count');
    if (shownOut) shownOut.textContent = shown; // optional per-page indicator if you add one

  }

  typeBoxes.forEach(function(cb){ cb.addEventListener('change', applyFilters); });
  workBoxes.forEach(function(cb){ cb.addEventListener('change', applyFilters); });
  salaryPeriod.addEventListener('change', function(){ setSliderFor(salaryPeriod.value); applyFilters(); });
  salMin.addEventListener('input', function(){ clampSliders(); applyFilters(); });
  salMax.addEventListener('input', function(){ clampSliders(); applyFilters(); });
  dateSel.addEventListener('change', applyFilters);

  var clearBtn = document.getElementById('clearFilters');
  if (clearBtn){
    clearBtn.addEventListener('click', function(e){
      e.preventDefault();
      typeBoxes.forEach(function(cb){ cb.checked = true; });
      workBoxes.forEach(function(cb){ cb.checked = true; });
      salaryPeriod.value = 'any'; setSliderFor('any');
      dateSel.value = 'any';
      applyFilters();
    });
  }

  setSliderFor('any');
  applyFilters();
})();
</script>

<script>
(function(){
  function setSearchPage(p){
    var f  = document.getElementById('searchForm');
    var pf = document.getElementById('pageField');
    if (!f || !pf) return;

    window.__searchPaginating = true;   // flag so we don't reset page to 1
    pf.value = p;

    var submitter = f.querySelector('button[name="submit"], input[name="submit"][type="submit"]');
    if (typeof f.requestSubmit === 'function') {
      // includes the submitter so PHP sees $_POST['submit']
      f.requestSubmit(submitter || null);
    } else {
      // fallback: ensure $_POST['submit'] exists, then bypass shadowed .submit
      var h = document.createElement('input');
      h.type = 'hidden';
      h.name = 'submit';
      h.value = '1';
      f.appendChild(h);
      HTMLFormElement.prototype.submit.call(f);
    }
  }
  window.setSearchPage = setSearchPage;

  document.addEventListener('DOMContentLoaded', function(){
    var f = document.getElementById('searchForm');
    if (!f) return;
    f.addEventListener('submit', function(){
      var pf = document.getElementById('pageField');
      if (pf && !window.__searchPaginating) pf.value = 1; // human-initiated search -> reset page
      window.__searchPaginating = false;
    });
  });
})();
</script>


<?php require "includes/footer.php"; ?>
