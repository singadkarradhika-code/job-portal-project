<?php require "config/config.php";

?>
<?php require "includes/header.php"; ?>
<?php 
$select = $conn->query("
  SELECT j.*, u.fullname AS company_name 
  FROM jobs j
  JOIN users u ON j.company_id = u.id
  WHERE j.status = 1 
    AND j.application_deadline >= CURDATE() 
  ORDER BY j.created_at DESC 
  LIMIT 5
");
$select->execute();
$jobs = $select->fetchAll(PDO::FETCH_OBJ);


  // Fetch top 5 most searched keywords (by frequency)
$searchStmt = $conn->prepare("
    SELECT keyword, COUNT(*) as count 
    FROM searches 
    GROUP BY keyword 
    ORDER BY count DESC, MAX(created_at) DESC 
    LIMIT 5
");
$searchStmt->execute();
$allSearches = $searchStmt->fetchAll(PDO::FETCH_OBJ);


?>


<style>
.overlay::before {
  content: "";
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  background: rgba(0, 0, 0, 0.6);
  z-index: 1;
}

.overlay > .container {
  position: relative;
  z-index: 2;
}

/* Layout helpers */
.min-vh-75{ min-height: 75vh; }

/* Gradient headline */
.hero-title{ line-height:1.15; }
.hero-gradient{
  background: linear-gradient(135deg,#ffffff 0%, #c7d2fe 40%, #a5b4fc 70%, #93c5fd 100%);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  text-shadow: rgba(0,0,0,.15) 0 4px 18px;
}

/* Tiny badge */
.hero-badge{
  display:inline-block; font-size:.85rem; letter-spacing:.02em;
  background: rgba(255,255,255,.12); color:#fff; padding:.35rem .65rem; border-radius:999px;
  box-shadow: 0 8px 24px rgba(0,0,0,.15); backdrop-filter: blur(6px);
}

/* Hero area extras */
.hero-v2{ position:relative; padding-top:5rem; padding-bottom:4rem; }
.hero-v2.overlay::before{
  background: radial-gradient(80% 60% at 50% 20%, rgba(0,0,0,.25), rgba(0,0,0,.65)),
              linear-gradient(180deg, rgba(0,0,0,.20), rgba(0,0,0,.55));
}

/* Glass search card */
.search-card{
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 16px;
  padding: 18px 16px 12px;
  box-shadow: 0 20px 45px rgba(0,0,0,.25);
  backdrop-filter: blur(10px);
}

/* Iconed inputs */
.input-icon{ position:relative; }
.input-icon > i{
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  font-size:1rem; color:#94a3b8;
}
.input-icon .form-control{
  padding-left: 2.1rem; border-radius:12px; border:1px solid rgba(255,255,255,.25);
  background: rgba(255,255,255,.9);
}
.input-icon.select-icon .bootstrap-select > .btn{
  padding-left: 2.1rem !important; border-radius:12px; border:1px solid rgba(255,255,255,.25);
}
.picker-glass{
  background: rgba(255,255,255,.95) !important; color:#0f172a !important;
  border: 1px solid #e5e7eb !important; border-radius:12px !important;
}

/* Call-to-action */
.btn-hero{
  color:#fff; background: linear-gradient(135deg,#6366f1,#22d3ee); border:0;
  border-radius: 999px; box-shadow: 0 14px 30px rgba(34,211,238,.25);
  transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
}
.btn-hero:hover{ transform: translateY(-1px); filter: brightness(1.05); }

/* Trending keyword chips */
.chips-scroll{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
.chips-scroll::-webkit-scrollbar{ height: 6px; }
.chips-scroll::-webkit-scrollbar-thumb{ background: rgba(255,255,255,.25); border-radius: 999px; }
.chip{
  display:inline-block; padding:.35rem .7rem; border-radius:999px;
  background: rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.25);
  transition: background .15s ease, transform .12s ease;
  white-space:nowrap;
}
.chip:hover{ text-decoration:none; background: rgba(255,255,255,.22); transform: translateY(-1px); }

/* Scroll cue */
.scroll-button{
  position:absolute; left:50%; bottom:22px; transform:translateX(-50%);
  width:42px; height:42px; line-height:42px; text-align:center; border-radius:999px;
  background: rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.25);
  transition: background .15s;
}
.scroll-button:hover{ background: rgba(255,255,255,.22); }


</style>

  <style>
/* container */
.job-listings { margin: 0; }

/* card */
.job-card {
  position: relative;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 2px 10px rgba(0,0,0,.04);
  transition: transform .18s ease, box-shadow .18s ease;
  overflow: hidden;
  margin-bottom: 16px;
}
.job-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,.08);
}
.job-link {
  display: grid;
  grid-template-columns: 72px 1fr auto;
  gap: 16px;
  text-decoration: none;
  color: inherit;
  padding: 16px 18px 16px 0;
}

/* left animated accent */
.job-card .accent {
  content: "";
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 0;
  background: linear-gradient(90deg, #06b6d4, #6366f1);
  transition: width .28s ease;
}
.job-card:hover .accent { width: 6px; }

/* logo */
.job-media { display:flex; align-items:center; justify-content:center; width:72px; }
.job-logo {
  width: 66px; height:66px; object-fit: cover;
  border-radius: 12px; border: 1px solid rgba(0,0,0,.06);
  background: #f7f8fa;
}

/* body */
.job-body { display:flex; flex-direction:column; justify-content:center; min-width: 0; }
.job-title-row { display:flex; align-items:center; gap:10px; }
.job-title {
  font-size: 1.125rem; line-height:1.25;
  font-weight: 700;
  margin: 0;
  display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.job-type { white-space: nowrap; }

.job-meta {
  color: #6c757d; font-size: .95rem;
  display:flex; align-items:center; gap:8px;
}
.job-company { font-weight: 600; color:#6c757d; }
.sep { opacity:.5; }
.job-loc i { margin-right: 4px; }

.job-sub { color:#98a2b3; font-size:.9rem; }

/* aside */
.job-aside {
  display:flex; flex-direction:column; align-items:flex-end; justify-content:center;
  gap:8px; min-width: 180px;
}

.due-chip {
  font-size: .875rem; font-weight: 700;
  padding: 6px 10px; border-radius: 999px;
  background: #eef2ff; color: #3730a3;
}
.due-ok    { background:#ecfdf5; color:#065f46; }
.due-warn  { background:#fff7ed; color:#9a3412; }
.due-danger{ background:#fef2f2; color:#991b1b; }

.deadline { color:#6c757d; }
.deadline-track {
  position: relative; width: 160px; height: 6px;
  background: #eef2f7; border-radius: 999px; overflow: hidden;
}
.deadline-fill { height:100%; background: linear-gradient(90deg,#22c55e,#f59e0b,#ef4444); }

.job-card a,
.job-card a:hover,
.job-card a:focus,
.job-card a:active {
  text-decoration: none !important;
}

/* responsive */
@media (max-width: 768px) {
  .job-link {
    grid-template-columns: 56px 1fr;
    padding-right: 14px;
  }
  .job-aside {
    grid-column: 1 / -1;
    align-items:flex-start;
    margin-top: 8px;
  }
  .deadline-track { width: 100%; }
}


.job-logo-initial{
  width:66px; height:66px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-weight:800; font-size:26px; user-select:none;
  border:1px solid rgba(0,0,0,.06);
  background:#6c757d;
}

    </style>
<style>
/* ===== Region Jobs slider (scoped) ===== */
.rj-controls .btn{ border-radius:999px; }
.rj-rail-wrap{ position:relative; }
.rj-rail{
  display:grid; grid-auto-flow:column; grid-auto-columns:minmax(260px, 1fr);
  gap:14px; overflow-x:auto; overscroll-behavior-x:contain; scroll-snap-type:x mandatory;
  padding-bottom:6px;
}
.rj-rail::-webkit-scrollbar{ height:8px; }
.rj-rail::-webkit-scrollbar-thumb{ background:#e5e7eb; border-radius:999px; }

.rj-card{
  display:flex; flex-direction:column; justify-content:space-between;
  scroll-snap-align:start; position:relative; min-width:260px;
  border-radius:14px; background:#fff; text-decoration:none; color:inherit;
  box-shadow:0 6px 20px rgba(0,0,0,.06); border:1px solid #eef2f7;
  padding:12px 12px 12px 12px; transition: transform .15s ease, box-shadow .15s ease;
}
.rj-card:hover{ transform:translateY(-2px); box-shadow:0 12px 28px rgba(0,0,0,.08); text-decoration: none;}

.rj-topline{ position:absolute; left:0; top:0; right:0; height:4px; border-top-left-radius:14px; border-top-right-radius:14px;
  background:linear-gradient(90deg,#22d3ee,#6366f1); }

.rj-head{ display:flex; align-items:center; justify-content:space-between; }
.rj-logo{ width:52px; height:52px; border-radius:12px; overflow:hidden; border:1px solid rgba(0,0,0,.06); background:#f7f8fa; display:flex; align-items:center; justify-content:center; }
.rj-logo img{ width:100%; height:100%; object-fit:cover; }
.rj-logo-initial{ width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:22px; }

.rj-title{ font-weight:800; line-height:1.25; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.rj-company{ font-weight:600; }
.rj-meta i{ color:#9ca3af; }

.rj-foot{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
.rj-chip{ font-size:.8rem; font-weight:700; padding:6px 10px; border-radius:999px; white-space:nowrap; }
.rj-ok{ background:#ecfdf5; color:#065f46; }
.rj-warn{ background:#fff7ed; color:#9a3412; }
.rj-danger{ background:#fef2f2; color:#991b1b; }

@media (min-width: 768px){
  .rj-rail{ grid-auto-columns: minmax(300px, 1fr); }
}
</style>
    <!-- HOME -->
    <!-- HERO / HOME -->
<section class="home-section hero-v2 overlay bg-image" style="background-image:url('images/tst.jpg');" id="home-section">
  <div class="container">
    <div class="row align-items-center justify-content-center min-vh-75">
      <div class="col-lg-10">

        <!-- Tiny badge -->
        <div class="text-center mb-3">
          <span class="hero-badge"><i class="fa fa-bolt mr-1"></i> Find your next role</span>
        </div>

        <!-- Headline -->
        <div class="text-center mb-4">
          <h1 class="display-4 hero-title">
            <span class="hero-gradient">Connecting Careers, Creating Futures</span>
          </h1>
          <p class="lead text-light mb-0">Who understands it’s personal? We do. To us, it’s about <strong>you</strong>.</p>
        </div>

        <!-- Search card -->
        <form method="post" action="search.php" class="search-card mt-4 search-jobs-form">
          <div class="form-row align-items-stretch">

            <!-- Job title -->
            <div class="col-12 col-md-6 mb-3 mb-md-0">
              <div class="input-icon">
                <!-- <i class="fa fa-briefcase"></i> -->
                <input name="job-title" type="text" class="form-control form-control-lg"
                       placeholder="Job title or keyword (e.g., Designer, Nurse, Java)">
              </div>
            </div>

            <?php
              // Fetch active job regions from the database
              $regionsStmt = $conn->prepare("SELECT * FROM job_regions WHERE status = 1 ORDER BY name ASC");
              $regionsStmt->execute();
              $jobRegions = $regionsStmt->fetchAll(PDO::FETCH_OBJ);
            ?>

            <!-- Region -->
            <div class="col-12 col-md-3 mb-3 mb-md-0">
              <div class="input-icon select-icon">
                <i class="fa fa-location-dot"></i>
                <select name="job-region" class="selectpicker" data-style="btn-white btn-lg picker-glass"
                        data-width="100%" data-live-search="true" title="Region">
                  <option value="">Anywhere</option>
                  <?php foreach ($jobRegions as $region): ?>
                    <option value="<?php echo htmlspecialchars($region->code); ?>">
                      <?php echo htmlspecialchars($region->name); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Type -->
            <div class="col-12 col-md-3">
              <div class="input-icon select-icon">
                <i class="fa fa-clock"></i>
                <select name="job-type" class="selectpicker" data-style="btn-white btn-lg picker-glass"
                        data-width="100%" data-live-search="true" title="Type">
                  <option>Part Time</option>
                  <option>Full Time</option>
                  <option>Casual</option>
                  <option>Contract</option>
                  <option>Fixed Term</option>
                </select>
              </div>
            </div>
          </div>

          <div class="text-center mt-3">
            <button type="submit" name="submit" class="btn btn-primary btn-lg px-5">
              <span class="icon-search icon mr-2"></span> Find Jobs
            </button>
          </div>

          <!-- Trending Keywords -->
          <div class="popular-keywords mt-4">
            <div class="d-flex align-items-center mb-2">
              <i class="fa fa-fire mr-2 text-warning"></i>
              <h6 class="text-uppercase text-light mb-0">Trending</h6>
            </div>
            <div class="chips-scroll">
              <ul class="keywords list-unstyled d-inline-flex flex-nowrap mb-0">
                <?php foreach($allSearches as $search) : ?>
                  <li class="mr-2 mb-2">
                    <a href="#" class="chip keyword-link" data-keyword="<?php echo htmlspecialchars($search->keyword); ?>">
                      <?php echo htmlspecialchars($search->keyword); ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>

  <a href="#next-section" class="scroll-button smoothscroll">
  <span class="icon-keyboard_arrow_down"></span>
</a>

</section>

    
  
<?php if (!empty($pendingApplications) && $pendingApplications > 0): ?>
<div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-delay="35000"
     style="position: fixed; bottom: 20px; right: 20px; min-width: 250px; z-index: 9999;">
  <div class="toast-header bg-danger text-white">
    <strong class="mr-auto">Job Applications</strong>
    <small>Just now</small>
    <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
  <div class="toast-body">
    You have <strong><?= $pendingApplications ?></strong> new job application<?= $pendingApplications > 1 ? 's' : '' ?> pending review.
  </div>
</div>
<?php endif; ?>

    

    <section class="site-section next-section">
      <div class="container">
        <h4 class="text-center mb-4">Recently Posted Jobs</h4>

<ul class="job-listings mb-5 list-unstyled">
<?php foreach ($jobs as $job) : ?>
<?php
  // company image
  $imgFile = '';
  $stmt = $conn->prepare("SELECT img FROM users WHERE id = :company_id");
  $stmt->bindParam(':company_id', $job->company_id);
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_OBJ);
  if ($result && !empty($result->img)) $imgFile = $result->img;

  // company name + initial
  $name    = trim($job->company_name ?? '');
  $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');

  // check if a real logo file exists
  $hasLogo = !empty($imgFile) && @file_exists(__DIR__ . "/users/user-images/" . $imgFile);

  // stable color by company name
  $palette = ['#0d6efd','#6f42c1','#20c997','#dc3545','#fd7e14','#198754','#0dcaf0','#6c757d'];
  $bg = $palette[hexdec(substr(md5(mb_strtolower($name,'UTF-8')),0,2)) % count($palette)];

  // dates
  $postedTs   = strtotime($job->created_at);
  $deadlineTs = strtotime($job->application_deadline);
  $nowTs      = time();
  $daysLeft   = max(0, ceil(($deadlineTs - $nowTs) / 86400));

  // “days left” color intent
  $dueClass = $daysLeft <= 3 ? 'due-danger'
            : ($daysLeft <= 10 ? 'due-warn' : 'due-ok');

  // badge color for job type
  $typeClass = 'primary';
  if ($job->job_type == 'Part Time')   $typeClass = 'danger';
  elseif ($job->job_type == 'Full Time') $typeClass = 'success';
  elseif ($job->job_type == 'Contract')  $typeClass = 'info';
  elseif ($job->job_type == 'Casual')    $typeClass = 'secondary';
?>
<li class="job-card">
  <a class="job-link" href="jobs/job-single.php?id=<?php echo $job->id; ?>" aria-label="View <?php echo htmlspecialchars($job->job_title); ?>">
    <div class="accent"></div>

    <div class="job-media">
      <?php if ($hasLogo): ?>
        <img
          loading="lazy"
          src="users/user-images/<?php echo htmlspecialchars($imgFile); ?>"
          alt="<?php echo htmlspecialchars($name ?: 'Company'); ?> logo"
          class="job-logo">
      <?php else: ?>
        <div class="job-logo-initial" style="background:<?php echo $bg; ?>;">
          <?php echo htmlspecialchars($initial ?: '?'); ?>
        </div>
      <?php endif; ?>
    </div>


      <div class="job-body">
        <div class="job-title-row">
          <h2 class="job-title mb-1"><?php echo htmlspecialchars($job->job_title); ?></h2>
          <span class="badge badge-<?php echo $typeClass; ?> job-type"><?php echo htmlspecialchars($job->job_type); ?></span>
        </div>

        <div class="job-meta">
          <span class="job-company"><?php echo htmlspecialchars($job->company_name); ?></span>
          <span class="sep">•</span>
          <span class="job-loc"><i class="icon-room mr-1"></i><?php echo htmlspecialchars($job->job_region); ?></span>
        </div>

        <div class="job-sub">
          <span class="posted">Posted: <?php echo date('M,d Y', $postedTs); ?></span>
        </div>
      </div>

      <div class="job-aside">
        <span class="due-chip <?php echo $dueClass; ?>">
          <?php echo $daysLeft > 0 ? $daysLeft . ' day' . ($daysLeft>1?'s':'') . ' left' : 'Closed'; ?>
        </span>
        <small class="deadline">
          <i class="fa fa-clock" aria-hidden="true"></i>
          Apply before: <?php echo date('M,d Y', $deadlineTs); ?>
        </small>

        <!-- progress bar toward deadline -->
        <?php
          // simple progress: how far from posted->deadline we are
          $totalWindow = max(1, $deadlineTs - $postedTs);
          $elapsed     = max(0, min($totalWindow, $nowTs - $postedTs));
          $pct         = round(($elapsed / $totalWindow) * 100);
        ?>
        <div class="deadline-track" aria-hidden="true">
          <div class="deadline-fill" style="width: <?php echo $pct; ?>%;"></div>
        </div>
      </div>
    </a>
  </li>
<?php endforeach; ?>
</ul>
      </div>
    </section>



    <?php
/* ========= Logged-in Job Seeker: Region-targeted jobs ========= */
$regionJobs = [];
$regionName = $regionCode = null;

if (isset($_SESSION['id']) && ($_SESSION['type'] ?? '') === 'Job Seeker') {
  $meId = (int)$_SESSION['id'];

  // Get seeker's region (name + code)
  $q = $conn->prepare("
    SELECT r.name AS region_name, r.code AS region_code
    FROM users u
    LEFT JOIN job_regions r ON r.id = u.region_id
    WHERE u.id = :uid
    LIMIT 1
  ");
  $q->execute([':uid'=>$meId]);
  $rowReg = $q->fetch(PDO::FETCH_ASSOC);
  $regionName = $rowReg['region_name'] ?? null;
  $regionCode = $rowReg['region_code'] ?? null;

  if ($regionName || $regionCode) {
    // Fetch active & within deadline jobs for this region (match by code OR name)
    $sql = "
      SELECT j.*, u.fullname AS company_name, u.img AS company_img
      FROM jobs j
      JOIN users u ON u.id = j.company_id
      WHERE j.status = 1
        AND j.application_deadline >= CURDATE()
        AND (
          " . ($regionCode ? " j.job_region = :rcode " : " 1=0 ") . "
          OR
          " . ($regionName ? " j.job_region = :rname " : " 1=0 ") . "
        )
      ORDER BY j.created_at DESC
      LIMIT 12
    ";
    $s = $conn->prepare($sql);
    if ($regionCode) $s->bindValue(':rcode', $regionCode);
    if ($regionName) $s->bindValue(':rname', $regionName);
    $s->execute();
    $regionJobs = $s->fetchAll(PDO::FETCH_OBJ);
  }
}
?>

<?php if (!empty($regionJobs)): ?>
<section class="site-section pt-0" id="region-hiring">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <div class="text-uppercase text-muted small mb-1">For You</div>
        <h4 class="mb-0">Hiring Near You<?= $regionName ? ' — '.htmlspecialchars($regionName) : '' ?></h4>
      </div>
      <div class="rj-controls">
        <button class="btn btn-dark btn-sm rj-prev" type="button" aria-label="Previous"><i class="fa-solid fa-arrow-left"></i></button>
        <button class="btn btn-dark btn-sm rj-next" type="button" aria-label="Next"><i class="fa-solid fa-arrow-right"></i></button>
      </div>
    </div>

    <div class="rj-rail-wrap">
      <div class="rj-rail" id="rjRail" tabindex="0" aria-label="Jobs carousel">
        <?php foreach ($regionJobs as $j):
          $name    = trim($j->company_name ?? '');
          $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
          $hasLogo = !empty($j->company_img) && @file_exists(__DIR__ . "/users/user-images/" . $j->company_img);

          $palette = ['#0d6efd','#6f42c1','#20c997','#dc3545','#fd7e14','#198754','#0dcaf0','#6c757d'];
          $bg = $palette[hexdec(substr(md5(mb_strtolower($name,'UTF-8')),0,2)) % count($palette)];

          $postedTs   = strtotime($j->created_at);
          $deadlineTs = strtotime($j->application_deadline);
          $nowTs      = time();
          $daysLeft   = max(0, (int)ceil(($deadlineTs - $nowTs) / 86400));
          $dueClass   = $daysLeft <= 3 ? 'rj-danger' : ($daysLeft <= 10 ? 'rj-warn' : 'rj-ok');

          $typeClass = 'primary';
          if ($j->job_type === 'Part Time')   $typeClass = 'danger';
          elseif ($j->job_type === 'Full Time') $typeClass = 'success';
          elseif ($j->job_type === 'Contract')  $typeClass = 'info';
          elseif ($j->job_type === 'Casual')    $typeClass = 'secondary';
        ?>
        <a class="rj-card" href="jobs/job-single.php?id=<?= (int)$j->id ?>" aria-label="View <?= htmlspecialchars($j->job_title) ?>">
          <span class="rj-topline"></span>
          <div class="rj-head">
            <div class="rj-logo">
              <?php if ($hasLogo): ?>
                <img src="users/user-images/<?= htmlspecialchars($j->company_img) ?>" alt="<?= htmlspecialchars($name ?: 'Company') ?> logo">
              <?php else: ?>
                <div class="rj-logo-initial" style="background:<?= $bg ?>"><?= htmlspecialchars($initial ?: '?') ?></div>
              <?php endif; ?>
            </div>
            <div class="rj-type badge badge-<?= $typeClass ?>"><?= htmlspecialchars($j->job_type) ?></div>
          </div>

          <div class="rj-body">
            <h6 class="rj-title mb-1" title="<?= htmlspecialchars($j->job_title) ?>"><?= htmlspecialchars($j->job_title) ?></h6>
            <div class="rj-company text-muted mb-2">
              <i class="fa fa-building mr-1"></i><?= htmlspecialchars($name) ?>
            </div>
            <div class="rj-meta small mb-2">
              <span><i class="fa fa-location-dot mr-1"></i><?= htmlspecialchars($j->job_region) ?></span>
              <span class="mx-2">•</span>
              <span><i class="fa fa-calendar mr-1"></i>Posted <?= date('M j', $postedTs) ?></span>
            </div>
          </div>

          <div class="rj-foot">
            <span class="rj-chip <?= $dueClass ?>">
              <?= $daysLeft > 0 ? ($daysLeft.' day'.($daysLeft>1?'s':'').' left') : 'Closed' ?>
            </span>
            <small class="text-muted">Apply by <?= date('M j, Y', $deadlineTs) ?></small>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>



<script>
(function(){
  var rail = document.getElementById('rjRail');
  if (!rail) return;
  var step = 320; // px per click
  var prev = document.querySelector('.rj-prev');
  var next = document.querySelector('.rj-next');

  function scrollByStep(dir){
    rail.scrollBy({ left: dir * step, behavior: 'smooth' });
  }
  if (prev) prev.addEventListener('click', function(){ scrollByStep(-1); });
  if (next) next.addEventListener('click', function(){ scrollByStep( 1); });

  // keyboard support
  rail.addEventListener('keydown', function(e){
    if (e.key === 'ArrowRight') { e.preventDefault(); scrollByStep(1); }
    if (e.key === 'ArrowLeft')  { e.preventDefault(); scrollByStep(-1); }
  });
})();
</script>
<?php endif; ?>


<?php
// Show this section only if the user is NOT Employer
if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'Employer'):

  $isLoggedIn = isset($_SESSION['username']);
  $userType   = $isLoggedIn ? $_SESSION['type'] : null;

  $heading    = ($isLoggedIn && $userType === 'Job Seeker')
                  ? "Welcome back!"
                  : "Looking for a job?";
  $subtext    = ($isLoggedIn && $userType === 'Job Seeker')
                  ? "Explore fresh roles tailored to your skills and schedule."
                  : "Create your free account and discover roles from vetted employers.";
  $buttonText = ($isLoggedIn && $userType === 'Job Seeker') ? "Explore Jobs" : "Sign Up";
  $buttonLink = ($isLoggedIn && $userType === 'Job Seeker')
                  ? APPURL . "/findjobs.php"
                  : APPURL . "/auth/loginRegister.php";
?>
<!-- wavy divider (top) -->
<div class="cta-wave-divider" aria-hidden="true">
  <svg viewBox="0 0 1440 80" preserveAspectRatio="none" class="w-100 h-100">
    <path fill="#f8fafc" d="M0,64L80,69.3C160,75,320,85,480,80C640,75,800,53,960,42.7C1120,32,1280,32,1360,32L1440,32L1440,0L1360,0C1280,0,1120,0,960,0C800,0,640,0,480,0C320,0,160,0,80,0L0,0Z"/>
  </svg>
</div>

<section class="cta-ribbon bg-image" style="background-image:url('<?php echo $base_url; ?>/images/tst.jpg');">
  <div class="cta-overlay"></div>

  <div class="container position-relative">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <div class="cta-eyebrow text-uppercase">Career Booster</div>
        <h2 class="cta-title mb-2 text-white"><?php echo htmlspecialchars($heading); ?></h2>
        <p class="lead text-white-50 mb-4"><?php echo htmlspecialchars($subtext); ?></p>

        <ul class="cta-benefits list-unstyled d-flex flex-wrap mb-4">
          <li><i class="fa fa-check"></i> 1-click apply</li>
          <li><i class="fa fa-check"></i> Verified employers</li>
          <li><i class="fa fa-check"></i> Smart job alerts</li>
        </ul>

        <a href="<?php echo $buttonLink; ?>" class="btn btn-primary btn-lg">
          <?php echo htmlspecialchars($buttonText); ?>
          <i class="fa fa-arrow-right ml-2"></i>
        </a>
      </div>

      <div class="col-lg-4 mt-5 mt-lg-0">
        <!-- small glass card highlight -->
        <div class="cta-card shadow-lg">
          <div class="d-flex align-items-center mb-3">
            <div class="badge badge-pill badge-primary mr-2">New</div>
            <strong>Personalized matches</strong>
          </div>
          <p class="mb-3 text-muted">Tell us your skills & availability and we’ll surface roles that fit—no noise.</p>
          <div class="d-flex">
            <div class="mini-stat">
              <div class="num">✨</div>
              <div class="lbl">Fresh roles</div>
            </div>
            <div class="mini-stat">
              <div class="num">🔒</div>
              <div class="lbl">Secure apply</div>
            </div>
            <div class="mini-stat">
              <div class="num">⚡</div>
              <div class="lbl">Fast setup</div>
            </div>
          </div>
        </div>
      </div>
    </div> <!-- /row -->
  </div> <!-- /container -->

  <!-- soft glow accents -->
  <span class="cta-glow cta-glow-1"></span>
  <span class="cta-glow cta-glow-2"></span>
</section>

<style>
/* Wave between hero and CTA */
.cta-wave-divider{ width:100%; height:80px; line-height:0; background:#fff; }
.cta-wave-divider svg{ display:block; }

/* CTA ribbon */
.cta-ribbon{
  position:relative; padding:56px 0; overflow:hidden; background-size:cover; background-position:center;
}
.cta-ribbon .cta-overlay{
  position:absolute; inset:0;
  background:
    linear-gradient(180deg, rgba(15,23,42,.75), rgba(15,23,42,.85)),
    rgba(15,23,42,.5);
}
.cta-eyebrow{
  letter-spacing:.16em; font-weight:700; color:#c7d2fe; font-size:.75rem; margin-bottom:.25rem;
}
.cta-title{ font-weight:800; line-height:1.2; }
.btn-cta{
  background: linear-gradient(135deg,#6366f1,#4f46e5);
  border:0; color:#fff; border-radius:999px; padding:.8rem 1.25rem;
  box-shadow:0 12px 24px rgba(79,70,229,.35); transition: transform .15s ease, box-shadow .15s ease;
}
.btn-cta:hover{ transform: translateY(-2px); box-shadow:0 18px 36px rgba(79,70,229,.45); color:#fff; }

.cta-benefits li{
  color:#e2e8f0; margin-right:16px; margin-bottom:8px; display:flex; align-items:center;
  background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);
  border-radius:999px; padding:.25rem .6rem;
}
.cta-benefits i{ font-size:.85rem; margin-right:.45rem; color:#a5b4fc; }

/* Glass highlight card */
.cta-card{
  border-radius:16px; background: rgba(255,255,255,.9); backdrop-filter: blur(6px);
  padding:18px;
}
.cta-card .badge-primary{ background:#6366f1; }
.cta-card .mini-stat{ flex:1; text-align:center; }
.cta-card .mini-stat .num{ font-size:1.2rem; }
.cta-card .mini-stat .lbl{ font-size:.8rem; color:#64748b; }

/* gentle glows */
.cta-glow{
  position:absolute; width:240px; height:240px; border-radius:50%;
  filter: blur(60px); opacity:.55; pointer-events:none;
}
.cta-glow-1{ background:#818cf8; right:-60px; top:20%; }
.cta-glow-2{ background:#22d3ee; left:-60px; bottom:-40px; }

@media (max-width: 576px){
  .cta-title{ font-size:1.6rem; }
}
</style>
<?php endif; ?>



    


    <!-- <section class="bg-light pt-5 testimony-full">
        
        <div class="owl-carousel single-carousel">

        
          <div class="container">
            <div class="row">
              <div class="col-lg-6 align-self-center text-center text-lg-left">
                <blockquote>
                  <p>&ldquo;Soluta quasi cum delectus eum facilis recusandae nesciunt molestias accusantium libero dolores repellat id in dolorem laborum ad modi qui at quas dolorum voluptatem voluptatum repudiandae.&rdquo;</p>
                  <p><cite> &mdash; Corey Woods, @Dribbble</cite></p>
                </blockquote>
              </div>
              <div class="col-lg-6 align-self-end text-center text-lg-right">
                <img src="images/person_transparent_2.png" alt="Image" class="img-fluid mb-0">
              </div>
            </div>
          </div>

          <div class="container">
            <div class="row">
              <div class="col-lg-6 align-self-center text-center text-lg-left">
                <blockquote>
                  <p>&ldquo;Soluta quasi cum delectus eum facilis recusandae nesciunt molestias accusantium libero dolores repellat id in dolorem laborum ad modi qui at quas dolorum voluptatem voluptatum repudiandae.&rdquo;</p>
                  <p><cite> &mdash; Chris Peters, @Google</cite></p>
                </blockquote>
              </div>
              <div class="col-lg-6 align-self-end text-center text-lg-right">
                <img src="images/person_transparent.png" alt="Image" class="img-fluid mb-0">
              </div>
            </div>
          </div>

      </div>

    </section> -->


    <script>
  $(document).ready(function(){
    $('.toast').toast('show');
  });

  // Smooth scroll (robust)
(function($){
  $(document).on('click', 'a.smoothscroll, .smoothscroll a', function(e){
    var href = $(this).attr('href') || '';
    // only handle same-page hash links
    if (!href || href.charAt(0) !== '#') return;

    var $target = $(href);

    // Fallbacks if the exact ID isn't found
    if (!$target.length) {
      if (href === '#next') {
        $target = $('#next, #next-section').first();
      }
      if (!$target.length) {
        var $afterHero = $('.home-section, .hero, .hero-v2').first().next();
        if ($afterHero.length) $target = $afterHero;
      }
    }

    if ($target.length) {
      e.preventDefault();
      $('html, body').stop().animate(
        { scrollTop: Math.max(0, $target.offset().top - 10) },
        600,
        'swing'
      );
    }
  });
})(jQuery);

</script>

<script>
  (function($){
    $(document).on('click', '.keyword-link', function(e){
      e.preventDefault();
      var kw = $(this).data('keyword') || $(this).text();
      var $input = $('input[name="job-title"]');
      $input.val(kw).focus();
      $('.search-jobs-form').trigger('submit');
    });
  })(jQuery);
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const keywordLinks = document.querySelectorAll('.keyword-link');
  const jobTitleInput = document.querySelector('input[name="job-title"]');

  keywordLinks.forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const keyword = this.getAttribute('data-keyword');
      jobTitleInput.value = keyword;

      // this.closest('form').submit();
    });
  });
});
</script>


    
<?php require "includes/footer.php"; ?>