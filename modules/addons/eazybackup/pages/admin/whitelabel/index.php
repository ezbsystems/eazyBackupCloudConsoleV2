<?php

use WHMCS\Database\Capsule;

// Admin: White-Label Tenants list/actions
// Returns HTML string

$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

// Handle actions (suspend / unsuspend / remove)
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('check_token') && check_token('WHMCS.admin.default')) {
        $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0 && in_array($do, ['suspend','unsuspend','remove'], true)) {
            if ($do === 'suspend') {
                Capsule::table('eb_whitelabel_tenants')->where('id', $id)->update(['status' => 'suspended', 'updated_at' => date('Y-m-d H:i:s')]);
            } else if ($do === 'unsuspend') {
                Capsule::table('eb_whitelabel_tenants')->where('id', $id)->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
            } else if ($do === 'remove') {
                // Safe teardown placeholder: mark removing (actual teardown to be implemented in Builder)
                Capsule::table('eb_whitelabel_tenants')->where('id', $id)->update(['status' => 'removing', 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
    }
} catch (\Throwable $ex) { /* ignore */ }

// Filters
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
// Pagination & sorting
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; if ($page < 1) { $page = 1; }
$perChoices = [25,50,100,250,2000];
$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 25; if (!in_array($perPage, $perChoices, true)) { $perPage = 25; }
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'created_at';
$allowedSort = [
    'id' => 't.id',
    'client' => 'c.email',
    'fqdn' => 't.fqdn',
    'custom_domain' => 't.custom_domain',
    'status' => 't.status',
    'created_at' => 't.created_at',
];
$sortCol = $allowedSort[$sort] ?? 't.created_at';
$dir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

// Base query + filters
$base = Capsule::table('eb_whitelabel_tenants as t')
    ->leftJoin('tblclients as c', 'c.id', '=', 't.client_id');
if ($q !== '') {
    $base = $base->where(function($qq) use ($q){
        $qq->where('t.fqdn','like','%'.$q.'%')
           ->orWhere('t.custom_domain','like','%'.$q.'%')
           ->orWhere('c.email','like','%'.$q.'%');
    });
}
if ($status !== '') {
    $base = $base->where('t.status', $status);
}
// Count for pagination without using clone (build a dedicated count query)
$countBase = Capsule::table('eb_whitelabel_tenants as t')
    ->leftJoin('tblclients as c', 'c.id', '=', 't.client_id');
if ($q !== '') {
    $countBase = $countBase->where(function($qq) use ($q){
        $qq->where('t.fqdn','like','%'.$q.'%')
           ->orWhere('t.custom_domain','like','%'.$q.'%')
           ->orWhere('c.email','like','%'.$q.'%');
    });
}
if ($status !== '') {
    $countBase = $countBase->where('t.status', $status);
}
$totalRows = (int) $countBase->count('t.id');
// Apply sort + pagination
$rows = $base->orderByRaw($sortCol . ' ' . $dir)
    ->offset(($page - 1) * $perPage)
    ->limit($perPage)
    ->get([
    't.id','t.client_id','t.fqdn','t.custom_domain','t.status','t.created_at','c.firstname','c.lastname','c.email'
]);

ob_start();
?>
<div class="container-fluid">
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="#">White-Label Tenants</a></li>
    <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Power Panel</a></li>
  </ul>

  <form method="get" class="mb-3 form-inline" style="margin-bottom:15px">
    <input type="hidden" name="module" value="eazybackup"/>
    <input type="hidden" name="action" value="whitelabel"/>
    <div class="form-group" style="margin-right:15px;margin-bottom:10px">
      <label for="filter-q" class="mr-2">Search</label>
      <input id="filter-q" type="text" class="form-control" name="q" value="<?php echo $e($q); ?>" placeholder="FQDN, custom domain, email"/>
    </div>
    <div class="form-group" style="margin-right:15px;margin-bottom:10px">
      <label for="filter-status" class="mr-2">Status</label>
      <select id="filter-status" class="form-control" name="status">
        <?php foreach (['','queued','building','active','failed','suspended','removing'] as $st): ?>
          <option value="<?php echo $e($st); ?>" <?php echo ($status===$st?'selected':''); ?>><?php echo $st===''?'All':$st; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-right:15px;margin-bottom:10px">
      <label for="perPage" class="mr-2">Per Page</label>
      <select id="perPage" class="form-control" name="perPage">
        <?php foreach ($perChoices as $pp): ?>
          <option value="<?php echo (int)$pp; ?>" <?php echo ($perPage===$pp?'selected':''); ?>><?php echo (int)$pp; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary mb-2 mr-2">Filter</button>
    <a href="addonmodules.php?module=eazybackup&action=whitelabel" class="btn btn-default mb-2">Reset</a>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-condensed">
      <thead><tr>
        <th><a href="addonmodules.php?module=eazybackup&action=whitelabel&<?php echo http_build_query(['q'=>$q,'status'=>$status,'perPage'=>$perPage,'page'=>1,'sort'=>'id','dir'=>($sort==='id'&&$dir==='asc'?'desc':'asc')]); ?>">ID<?php echo ($sort==='id'?' <span class="text-muted">('.strtoupper($dir).')</span>':''); ?></a></th>
        <th><a href="addonmodules.php?module=eazybackup&action=whitelabel&<?php echo http_build_query(['q'=>$q,'status'=>$status,'perPage'=>$perPage,'page'=>1,'sort'=>'client','dir'=>($sort==='client'&&$dir==='asc'?'desc':'asc')]); ?>">Client<?php echo ($sort==='client'?' <span class="text-muted">('.strtoupper($dir).')</span>':''); ?></a></th>
        <th><a href="addonmodules.php?module=eazybackup&action=whitelabel&<?php echo http_build_query(['q'=>$q,'status'=>$status,'perPage'=>$perPage,'page'=>1,'sort'=>'fqdn','dir'=>($sort==='fqdn'&&$dir==='asc'?'desc':'asc')]); ?>">FQDN<?php echo ($sort==='fqdn'?' <span class="text-muted">('.strtoupper($dir).')</span>':''); ?></a></th>
        <th><a href="addonmodules.php?module=eazybackup&action=whitelabel&<?php echo http_build_query(['q'=>$q,'status'=>$status,'perPage'=>$perPage,'page'=>1,'sort'=>'custom_domain','dir'=>($sort==='custom_domain'&&$dir==='asc'?'desc':'asc')]); ?>">Custom Domain<?php echo ($sort==='custom_domain'?' <span class="text-muted">('.strtoupper($dir).')</span>':''); ?></a></th>
        <th><a href="addonmodules.php?module=eazybackup&action=whitelabel&<?php echo http_build_query(['q'=>$q,'status'=>$status,'perPage'=>$perPage,'page'=>1,'sort'=>'status','dir'=>($sort==='status'&&$dir==='asc'?'desc':'asc')]); ?>">Status<?php echo ($sort==='status'?' <span class="text-muted">('.strtoupper($dir).')</span>':''); ?></a></th>
        <th><a href="addonmodules.php?module=eazybackup&action=whitelabel&<?php echo http_build_query(['q'=>$q,'status'=>$status,'perPage'=>$perPage,'page'=>1,'sort'=>'created_at','dir'=>($sort==='created_at'&&$dir==='asc'?'desc':'asc')]); ?>">Created<?php echo ($sort==='created_at'?' <span class="text-muted">('.strtoupper($dir).')</span>':''); ?></a></th>
        <th class="text-right">Actions</th>
      </tr></thead>
      <tbody>
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r->id; ?></td>
            <td><?php echo $e((string)($r->firstname.' '.$r->lastname.' <'.$r->email.'>')); ?></td>
            <td><?php echo $e((string)$r->fqdn); ?></td>
            <td><?php echo $e((string)($r->custom_domain ?? '')); ?></td>
            <td><?php echo $e((string)$r->status); ?></td>
            <td><span class="text-muted small"><?php echo $e((string)$r->created_at); ?></span></td>
            <td class="text-right">
              <form method="post" style="display:inline-block;margin:0;padding:0">
                <?php echo function_exists('generate_token') ? generate_token('input') : ''; ?>
                <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"/>
                <input type="hidden" name="do" value="suspend"/>
                <button type="submit" class="btn btn-warning btn-xs" <?php echo ($r->status==='suspended'?'disabled':''); ?>>Suspend</button>
              </form>
              <form method="post" style="display:inline-block;margin:0 6px;padding:0">
                <?php echo function_exists('generate_token') ? generate_token('input') : ''; ?>
                <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"/>
                <input type="hidden" name="do" value="unsuspend"/>
                <button type="submit" class="btn btn-success btn-xs" <?php echo ($r->status!=='suspended'?'disabled':''); ?>>Unsuspend</button>
              </form>
              <form method="post" style="display:inline-block;margin:0;padding:0" onsubmit="return confirm('Mark tenant for removal? This cannot be undone.');">
                <?php echo function_exists('generate_token') ? generate_token('input') : ''; ?>
                <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"/>
                <input type="hidden" name="do" value="remove"/>
                <button type="submit" class="btn btn-danger btn-xs">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center text-muted">No tenants</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
    // Pagination controls
    $totalPages = (int)max(1, ceil($totalRows / $perPage));
    $cur = $page; if ($cur > $totalPages) { $cur = $totalPages; }
    $mk = function($p) use ($q,$status,$perPage,$sort,$dir){
        return 'addonmodules.php?module=eazybackup&action=whitelabel&' . http_build_query([
            'q'=>$q,'status'=>$status,'perPage'=>$perPage,'page'=>$p,'sort'=>$sort,'dir'=>$dir
        ]);
    };
  ?>
  <div class="clearfix" style="margin:10px 0 15px 0">
    <div class="pull-left" style="padding-top:7px; font-weight:600;">Total: <?php echo (int)$totalRows; ?></div>
    <div class="pull-right">
      <ul class="pagination" style="margin:0">
        <li class="page-item <?php echo ($cur<=1?'disabled':''); ?>"><a class="page-link" href="<?php echo $mk(max(1,$cur-1)); ?>">Prev</a></li>
        <?php for($p=max(1,$cur-2); $p<=min($totalPages,$cur+2); $p++): ?>
          <li class="page-item <?php echo ($p===$cur?'active':''); ?>"><a class="page-link" href="<?php echo $mk($p); ?>"><?php echo (int)$p; ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?php echo ($cur>=$totalPages?'disabled':''); ?>"><a class="page-link" href="<?php echo $mk(min($totalPages,$cur+1)); ?>">Next</a></li>
      </ul>
    </div>
  </div>
</div>
<?php
return ob_get_clean();


