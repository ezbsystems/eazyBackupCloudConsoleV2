<?php
use WHMCS\Database\Capsule;
use CometBilling\Crypto;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_token('WHMCS.admin.default')) {
    if (isset($_POST['create'])) {
        Capsule::table('cb_api_keys')->insert([
            'label'     => $_POST['label'],
            'base_url'  => $_POST['base_url'],
            'auth_type' => 'token',
            'token_enc' => Crypto::enc($_POST['token']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);
    }
    if (isset($_POST['delete']) && isset($_POST['id'])) {
        Capsule::table('cb_api_keys')->where('id', (int)$_POST['id'])->delete();
    }
    echo '<div class="infobox">Updated.</div>';
}

$keys = Capsule::table('cb_api_keys')->orderBy('id','desc')->get();
?>
<h3>Additional API Keys</h3>
<form method="post">
  <?php echo generate_token('WHMCS.admin.default'); ?>
  <p>
    Label <input type="text" name="label" required>
    Base URL <input type="text" name="base_url" value="https://account.cometbackup.com" size="50" required>
    Token <input type="password" name="token" size="60" required>
    Active <input type="checkbox" name="is_active" checked>
    <button class="btn btn-primary" type="submit" name="create">Add Key</button>
  </p>
  </form>

<table class="datatable" width="100%">
  <thead><tr><th>ID</th><th>Label</th><th>Base URL</th><th>Active</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($keys as $k): ?>
    <tr>
      <td><?= (int)$k->id ?></td>
      <td><?= htmlspecialchars($k->label) ?></td>
      <td><?= htmlspecialchars($k->base_url) ?></td>
      <td><?= $k->is_active ? 'Yes' : 'No' ?></td>
      <td>
        <form method="post" style="display:inline">
          <?php echo generate_token('WHMCS.admin.default'); ?>
          <input type="hidden" name="id" value="<?= (int)$k->id ?>">
          <button class="btn btn-danger btn-sm" name="delete" value="1" onclick="return confirm('Delete key?')">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  </table>


