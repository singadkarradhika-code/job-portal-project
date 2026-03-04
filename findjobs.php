<?php
require "config/config.php";

$limit  = 12;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

/* ------------------ Filters (GET or POST) ------------------ */
$job_title = isset($_REQUEST['job-title']) ? trim($_REQUEST['job-title']) : '';
$job_region= isset($_REQUEST['job-region']) ? trim($_REQUEST['job-region']) : '';
$job_type  = isset($_REQUEST['job-type'])   ? trim($_REQUEST['job-type'])   : '';

/* ------------------ Base WHERE + params ------------------ */
$sql_where = "FROM jobs j
              JOIN users u ON j.company_id = u.id
              WHERE j.status = 1 AND j.application_deadline >= CURDATE()";
$params = [];

if ($job_title !== '') {
  $sql_where .= " AND j.job_title LIKE :job_title";
  $params[':job_title'] = "%{$job_title}%";
}
if ($job_region !== '') {
  $sql_where .= " AND j.job_region = :job_region";
  $params[':job_region'] = $job_region;
}
if ($job_type !== '') {
  $sql_where .= " AND j.job_type = :job_type";
  $params[':job_type'] = $job_type;
}

/* ------------------ Totals & Page Data ------------------ */
$countStmt = $conn->prepare("SELECT COUNT(*) AS total {$sql_where}");
$countStmt->execute($params);
$totalJobs  = (int)$countStmt->fetch(PDO::FETCH_OBJ)->total;
$totalPages = (int)ceil($totalJobs / $limit);

// $dataStmt = $conn->prepare("
//   SELECT j.*, u.fullname AS company_name
//   {$sql_where}
//   ORDER BY j.created_at DESC
//   LIMIT :limit OFFSET :offset
// ");
$dataStmt = $conn->prepare("
  SELECT j.*, u.fullname AS company_name, u.img AS user_img
  {$sql_where}
  ORDER BY j.created_at DESC
  LIMIT :limit OFFSET :offset
");

foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
$dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$jobs = $dataStmt->fetchAll(PDO::FETCH_OBJ);

/* ------------------ Regions (for labels) ------------------ */
$regionsStmt = $conn->query("SELECT * FROM job_regions WHERE status = 1 ORDER BY name ASC");
$jobRegions  = $regionsStmt->fetchAll(PDO::FETCH_OBJ);
$regionMap   = [];
foreach ($jobRegions as $r) $regionMap[$r->code] = $r->name;

/* ------------------ Job Types ------------------ */
$jobTypes = ["Full Time", "Part Time", "Contract", "Casual", "Fixed Term"];

require "includes/header.php";
?>

<style>
  .site-section{ padding-top:2rem; }
/* ===== Hero ===== */
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
/* ===== Filter bar ===== */
.fj-toolbar{
  background:#fff; border-radius:14px; box-shadow:0 10px 24px rgba(0,0,0,.06);
  padding:14px; margin-top:-30px; position:relative; z-index:3;
}
.fj-toolbar .form-control, .fj-toolbar select{ border-radius:10px; }
.fj-chips{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
.fj-chip{
  background:#eef2ff; color:#3730a3; border-radius:999px;
  padding:.35rem .6rem; font-weight:700; font-size:.85rem; display:inline-flex; align-items:center; gap:6px;
}
.fj-chip .close{ line-height:1; opacity:.6; }
.fj-reset{ border-radius:999px; }

/* ===== Grid & Cards ===== */
.fj-grid{
  display:grid; grid-template-columns: repeat(2, 1fr);
  gap:24px; list-style:none; padding:0; margin:0;
}
@media (max-width: 768px){
  .fj-grid{ grid-template-columns: 1fr; }
}

.fj-card{
  position:relative; background:#fff; border-radius:16px; overflow:hidden;
  box-shadow:0 2px 10px rgba(0,0,0,.05); transition:transform .18s ease, box-shadow .18s ease;
}
.fj-card:hover{ transform:translateY(-2px); box-shadow:0 10px 26px rgba(0,0,0,.10); }

.fj-link{ display:block; color:inherit; text-decoration:none !important; padding:18px 18px 16px; }
.fj-accent{ position:absolute; left:0; right:0; top:0; height:4px; background:linear-gradient(90deg,#06b6d4,#6366f1); opacity:.9; }

.fj-header{ display:grid; grid-template-columns:56px 1fr auto; gap:12px; align-items:center; }
.fj-avatar{
  width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center;
  font-weight:800; color:#334155; background:#eef2ff; border:1px solid rgba(0,0,0,.06);
}
.fj-avatar-img{ width:48px; height:48px; border-radius:12px; object-fit:cover; border:1px solid rgba(0,0,0,.06); display:block; }

.fj-title{ margin:0; font-size:1.125rem; line-height:1.25; font-weight:800;
  display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
.fj-meta{ color:#6c757d; display:flex; align-items:center; flex-wrap:wrap; gap:8px; font-size:.95rem; }
.fj-company{ font-weight:600; }
.fj-dot{ opacity:.5; }
.fj-type{ white-space:nowrap; }

.fj-sub{ margin-top:6px; color:#98a2b3; font-size:.9rem; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

/* chips row */
.fj-tags{ margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
.fj-tag{ border-radius:999px; padding:6px 10px; font-size:.82rem; font-weight:700; line-height:1; }
.fj-tag--salary{ background:#f0fdf4; color:#166534; }
.fj-tag--exp{ background:#f1f5f9; color:#0f172a; }
.fj-tag--vac{ background:#eef2ff; color:#3730a3; }
.fj-tag--arr{ background:#fff7ed; color:#9a3412; }

/* Footer */
.fj-footer{ margin-top:12px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.fj-chip-deadline{ border-radius:999px; padding:6px 10px; font-size:.875rem; font-weight:700; line-height:1; }
.fj-due--ok{ background:#ecfdf5; color:#065f46; }
.fj-due--warn{ background:#fff7ed; color:#9a3412; }
.fj-due--danger{ background:#fef2f2; color:#991b1b; }
.fj-deadline{ color:#6c757d; }

/* Progress */
.fj-track{ width:100%; height:6px; background:#eef2f7; border-radius:999px; overflow:hidden; margin-top:10px; }
.fj-fill{ height:100%; background:linear-gradient(90deg,#22c55e,#f59e0b,#ef4444); }

/* Pagination */
.fj-pagination .page-item.active .page-link{ color:#fff; }

</style>

<section class="companies-hero overlay inner-page bg-image" style="background-image:url('images/tst.jpg');" id="home-section">
  <div class="overlay-dark"></div>
  <div class="container hero-inner text-center">
    <div class="ch-eyebrow mb-1">Jobs</div>
    <h2 class="ch-title mb-2">Explore Jobs</h2>
    <p class="ch-sub mb-0">Find roles that match your skills, interests, and goals.</p>
  </div>
</section>

<section class="site-section">
  <div class="container">

    <!-- Filter toolbar -->
    <div class="fj-toolbar mb-4">
      <form method="get" action="findjobs.php" class="m-0">
        <div class="form-row">
          <div class="col-md-5 mb-2">
            <input name="job-title" type="text" class="form-control" placeholder="Keyword (e.g., Designer, Nurse, Java)"
                   value="<?php echo htmlspecialchars($job_title); ?>">
          </div>
          <div class="col-md-3 mb-2">
            <select name="job-region" class="form-control">
              <option value="">Anywhere</option>
              <?php foreach($jobRegions as $region): ?>
                <option value="<?php echo htmlspecialchars($region->code); ?>"
                        <?php if($region->code === $job_region) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($region->name); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 mb-2">
            <select name="job-type" class="form-control">
              <option value="">Any Type</option>
              <?php foreach($jobTypes as $type): ?>
                <option <?php echo ($job_type === $type ? 'selected' : ''); ?>><?php echo $type; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 mb-2">
            <button type="submit" class="btn btn-primary btn-block">Filter</button>
          </div>
        </div>

        <!-- Active filter chips -->
        <?php if ($job_title !== '' || $job_region !== '' || $job_type !== ''): ?>
          <div class="fj-chips">
            <?php if ($job_title !== ''): ?>
              <span class="fj-chip">
                Keyword: <strong><?php echo htmlspecialchars($job_title); ?></strong>
                <a class="close" href="?<?php echo http_build_query(array_merge($_GET, ['job-title'=>'','page'=>1])); ?>">&times;</a>
              </span>
            <?php endif; ?>
            <?php if ($job_region !== ''): ?>
              <span class="fj-chip">
                Region: <strong><?php echo htmlspecialchars($regionMap[$job_region] ?? $job_region); ?></strong>
                <a class="close" href="?<?php echo http_build_query(array_merge($_GET, ['job-region'=>'','page'=>1])); ?>">&times;</a>
              </span>
            <?php endif; ?>
            <?php if ($job_type !== ''): ?>
              <span class="fj-chip">
                Type: <strong><?php echo htmlspecialchars($job_type); ?></strong>
                <a class="close" href="?<?php echo http_build_query(array_merge($_GET, ['job-type'=>'','page'=>1])); ?>">&times;</a>
              </span>
            <?php endif; ?>
            <a href="findjobs.php" class="btn btn-light btn-sm fj-reset">Reset filters</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($totalJobs > 0): ?>
      <h5 class="mb-3">Available Jobs: <?php echo (int)$totalJobs; ?></h5>

      <ul class="fj-grid">
        <?php foreach ($jobs as $job): ?>
          <?php
            $postedTs   = strtotime($job->created_at);
            $deadlineTs = strtotime($job->application_deadline);
            $nowTs      = time();
            $daysLeft   = max(0, ceil(($deadlineTs - $nowTs) / 86400));

            $typeClass = 'primary';
            if ($job->job_type === 'Full Time')     $typeClass = 'success';
            elseif ($job->job_type === 'Part Time') $typeClass = 'danger';
            elseif ($job->job_type === 'Contract')  $typeClass = 'info';
            elseif ($job->job_type === 'Casual')    $typeClass = 'secondary';

            $dueClass = $daysLeft <= 3 ? 'fj-due--danger'
                      : ($daysLeft <= 10 ? 'fj-due--warn' : 'fj-due--ok');

            $totalWindow = max(1, $deadlineTs - $postedTs);
            $elapsed     = max(0, min($totalWindow, $nowTs - $postedTs));
            $pct         = round(($elapsed / $totalWindow) * 100);

            // Region label (code -> name)
            $regionLabel = $regionMap[$job->job_region] ?? $job->job_region;

            // Company logo/initial
            // $companyName = trim((string)$job->company_name);
            // $initial     = strtoupper(substr($companyName, 0, 1));

            // // Prefer logo on the job row (company_image). Fallback: users.img lookup.
            // $imgFile = trim((string)($job->company_image ?? ''));
            // if ($imgFile === '' && !empty($job->company_id)) {
            //   $stmt = $conn->prepare("SELECT img FROM users WHERE id = :id LIMIT 1");
            //   $stmt->execute([':id' => $job->company_id]);
            //   if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            //     if (!empty($row['img'])) $imgFile = $row['img'];
            //   }
            // }
            // $webPath  = "users/user-images/" . $imgFile;
            // $fsPath   = __DIR__ . "/users/user-images/" . $imgFile;
            // $hasLogo  = ($imgFile !== '' && is_file($fsPath));

            // Company logo/initial
            $companyName = trim((string)$job->company_name);
            $initial     = strtoupper(substr($companyName, 0, 1));

            // Try jobs.company_image first; if missing OR not found on disk, fallback to users.img
            $imgFile = trim((string)($job->company_image ?? ''));
            $baseDir = __DIR__ . "/users/user-images/";
            $webBase = "users/user-images/";

            // Normalize: if company_image is present but file doesn't exist => discard it
            if ($imgFile !== '' && !is_file($baseDir . $imgFile)) {
              $imgFile = '';
            }

            // Fallback to users.img if needed (now available as user_img from the JOIN)
            if ($imgFile === '') {
              $userImg = trim((string)($job->user_img ?? ''));
              if ($userImg !== '' && is_file($baseDir . $userImg)) {
                $imgFile = $userImg;
              }
            }

            $hasLogo = ($imgFile !== '');
            $webPath = $hasLogo ? ($webBase . $imgFile) : '';

          ?>
          <li class="fj-card">
            <a class="fj-link" href="jobs/job-single.php?id=<?php echo (int)$job->id; ?>">
              <div class="fj-accent" aria-hidden="true"></div>

              <div class="fj-header">
                <div class="fj-media">
                  <?php if ($hasLogo): ?>
                    <img src="<?php echo htmlspecialchars($webPath); ?>"
                         alt="<?php echo htmlspecialchars($companyName); ?> logo"
                         class="fj-avatar-img" loading="lazy">
                  <?php else: ?>
                    <div class="fj-avatar" aria-hidden="true"><?php echo htmlspecialchars($initial ?: 'J'); ?></div>
                  <?php endif; ?>
                </div>

                <div class="fj-title-wrap">
                  <h3 class="fj-title"><?php echo htmlspecialchars($job->job_title); ?></h3>
                  <div class="fj-meta">
                    <span class="fj-company"><?php echo htmlspecialchars($companyName); ?></span>
                    <span class="fj-dot">•</span>
                    <span class="fj-loc"><i class="icon-room"></i> <?php echo htmlspecialchars($regionLabel); ?></span>
                  </div>
                </div>

                <span class="badge badge-<?php echo $typeClass; ?> fj-type">
                  <?php echo htmlspecialchars($job->job_type); ?>
                </span>
              </div>

              <div class="fj-sub">
                <span class="fj-posted">Posted: <?php echo date('M d, Y', $postedTs); ?></span>
                <?php if (!empty($job->work_arrangement)): ?>
                  <span class="text-muted">• <?php echo htmlspecialchars($job->work_arrangement); ?></span>
                <?php endif; ?>
              </div>

              <!-- Info chips -->
              <div class="fj-tags">
                <?php if (!empty($job->salary)): ?>
                  <span class="fj-tag fj-tag--salary"><i class="fa fa-money-bill-alt"></i> <?php echo htmlspecialchars($job->salary); ?></span>
                <?php endif; ?>
                <?php if (!empty($job->experience)): ?>
                  <span class="fj-tag fj-tag--exp"><i class="fa fa-briefcase"></i> <?php echo htmlspecialchars($job->experience); ?></span>
                <?php endif; ?>
                <span class="fj-tag fj-tag--vac"><i class="fa fa-users"></i> Vacancy: <?php echo (int)$job->vacancy; ?></span>
                <?php if (!empty($job->work_arrangement)): ?>
                  <span class="fj-tag fj-tag--arr"><i class="fa fa-building"></i> <?php echo htmlspecialchars($job->work_arrangement); ?></span>
                <?php endif; ?>
              </div>

              <div class="fj-footer">
                <span class="fj-chip-deadline <?php echo $dueClass; ?>">
                  <?php echo $daysLeft > 0 ? $daysLeft . ' day' . ($daysLeft>1?'s':'') . ' left' : 'Closed'; ?>
                </span>
                <small class="fj-deadline">
                  <i class="fa fa-clock"></i>
                  Apply before: <?php echo date('M d, Y', $deadlineTs); ?>
                </small>
              </div>

              <div class="fj-track" aria-hidden="true">
                <div class="fj-fill" style="width:<?php echo $pct; ?>%;"></div>
              </div>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
          <ul class="pagination fj-pagination justify-content-center">
            <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?php if ($page >= $totalPages) echo 'disabled'; ?>">
              <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

    <?php else: ?>
      <div class="alert alert-info mb-0">No jobs available at the moment!</div>
    <?php endif; ?>
  </div>
</section>

<?php require "includes/footer.php"; ?>
