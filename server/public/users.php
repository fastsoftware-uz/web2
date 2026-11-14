<?php
/**
 * Who may open this admin.
 *
 * The owner adds an account, gives it one of three roles and can take it away
 * again. Everything on this page is written to the log under the name of the
 * person who did it — handing somebody the right to edit the public site is
 * exactly the kind of act that has to be answerable afterwards.
 *
 * Three things this page refuses, and all three are refusals of a state the
 * installation cannot be got out of from a browser:
 *
 *   - the last owner cannot be removed, deactivated or demoted;
 *   - nobody can deactivate or remove themselves;
 *   - a password is never shown back, and never stored anywhere but as a hash.
 *
 * A new account's first password is typed here and shown once, because the
 * person who will use it is usually standing next to the person making it.
 * There is no mail on this server to send it with.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/adminui.php';

$config = app_config();
$user = admin_boot($config, 'users.manage');

$problem = '';
$madePassword = '';
$madeLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_check_token()) {
        $problem = 'Sahifa eskirgan — qaytadan urinib koʻring.';
    } else {
        $do = (string) ($_POST['do'] ?? '');
        $id = (string) ($_POST['id'] ?? '');
        $target = $id === '' ? null : users_by_id($config, $id);
        try {
            if ($do === 'add') {
                $login = trim(app_str($_POST, 'login', 32));
                $name = app_str($_POST, 'name', 80);
                $role = (string) ($_POST['role'] ?? 'viewer');
                $password = (string) ($_POST['password'] ?? '');

                $complaint = users_login_complaint($config, $login);
                if ($complaint === 'shape') {
                    throw new RuntimeException(
                        'Login 3–32 belgidan iborat boʻlsin: kichik lotin harflari, raqam, nuqta, chiziqcha.',
                    );
                }
                if ($complaint === 'taken') {
                    throw new RuntimeException('Bunday login allaqachon bor.');
                }
                if (!in_array($role, USER_ROLES, true)) {
                    throw new RuntimeException('Bunday huquq yoʻq.');
                }
                $weak = guard_password_complaint($password);
                if ($weak === 'short') {
                    throw new RuntimeException('Parol kamida 10 belgidan boʻlsin.');
                }
                if ($weak === 'digits') {
                    throw new RuntimeException('Faqat raqamdan iborat parol boʻlmaydi.');
                }
                $made = users_add($config, $login, $name, $role, $password, $user['login']);
                audit_log($config, 'user.add', ['login' => $made['login'], 'huquq' => $role], $made['id']);
                $madeLogin = $made['login'];
                $madePassword = $password;
                admin_flash('ok', 'Foydalanuvchi qoʻshildi: ' . $made['login']);
            } elseif ($do === 'role') {
                $role = (string) ($_POST['role'] ?? '');
                if ($target === null || !in_array($role, USER_ROLES, true)) {
                    throw new RuntimeException('Bunday foydalanuvchi yoki huquq yoʻq.');
                }
                if ($target['role'] === 'owner' && $role !== 'owner' && users_owner_count($config) <= 1) {
                    throw new RuntimeException('Bu yagona egasi — huquqini pasaytirib boʻlmaydi.');
                }
                users_update($config, $id, static function (array $u) use ($role): array {
                    $u['role'] = $role;
                    /* The role is in the session, so the sessions this
                       account has open have to be ended for the new role to
                       mean anything. */
                    $u['pwv'] = (int) $u['pwv'] + 1;
                    return $u;
                });
                audit_log($config, 'user.role', [
                    'login' => $target['login'],
                    'edi' => $target['role'],
                    'boʻldi' => $role,
                ], $id);
                admin_flash('ok', $target['login'] . ' — huquqi oʻzgartirildi.');
            } elseif ($do === 'password') {
                $password = (string) ($_POST['password'] ?? '');
                if ($target === null) {
                    throw new RuntimeException('Bunday foydalanuvchi yoʻq.');
                }
                $weak = guard_password_complaint($password);
                if ($weak !== '') {
                    throw new RuntimeException(
                        $weak === 'short' ? 'Parol kamida 10 belgidan boʻlsin.' : 'Faqat raqamdan iborat parol boʻlmaydi.',
                    );
                }
                users_set_password($config, $id, $password);
                audit_log($config, 'user.password', ['login' => $target['login']], $id);
                $madeLogin = $target['login'];
                $madePassword = $password;
                admin_flash('ok', $target['login'] . ' — paroli oʻzgartirildi, ochiq sessiyalari yopildi.');
            } elseif ($do === 'active') {
                $on = ($_POST['on'] ?? '') === '1';
                if ($target === null) {
                    throw new RuntimeException('Bunday foydalanuvchi yoʻq.');
                }
                if ($target['id'] === $user['id'] && !$on) {
                    throw new RuntimeException('Oʻzingizni oʻchira olmaysiz.');
                }
                if (!$on && $target['role'] === 'owner' && users_owner_count($config) <= 1) {
                    throw new RuntimeException('Bu yagona egasi — oʻchirib boʻlmaydi.');
                }
                users_update($config, $id, static function (array $u) use ($on): array {
                    $u['active'] = $on;
                    $u['pwv'] = (int) $u['pwv'] + 1;
                    return $u;
                });
                audit_log($config, 'user.active', ['login' => $target['login'], 'yoqildi' => $on], $id);
                admin_flash('ok', $target['login'] . ($on ? ' — yoqildi.' : ' — oʻchirildi.'));
            } elseif ($do === 'remove') {
                if ($target === null) {
                    throw new RuntimeException('Bunday foydalanuvchi yoʻq.');
                }
                if ($target['id'] === $user['id']) {
                    throw new RuntimeException('Oʻzingizni oʻchira olmaysiz.');
                }
                if ($target['role'] === 'owner' && users_owner_count($config) <= 1) {
                    throw new RuntimeException('Bu yagona egasi — oʻchirib boʻlmaydi.');
                }
                users_remove($config, $id);
                audit_log($config, 'user.remove', ['login' => $target['login'], 'huquq' => $target['role']], $id);
                admin_flash('ok', $target['login'] . ' — oʻchirildi.');
            }
        } catch (Throwable $e) {
            $problem = $e->getMessage();
        }
        if ($problem === '' && $madePassword === '') {
            header('Location: users.php');
            exit;
        }
    }
}

$users = users_all($config);
$single = !users_installed($config);

admin_head($config, $user, 'Foydalanuvchilar', 'users');
?>
<main>
<?php admin_flash_print(); ?>
<?php if ($problem !== ''): ?>
  <p class="err big"><?= app_h($problem) ?></p>
<?php endif; ?>

<?php if ($madePassword !== ''): ?>
  <section class="handover">
    <h2>Parolni bir marta koʻrsatamiz</h2>
    <p>
      <b><?= app_h($madeLogin) ?></b> uchun parol:
      <code class="secret"><?= app_h($madePassword) ?></code>
    </p>
    <p class="muted">
      Bu parol boshqa hech qayerda saqlanmaydi va qaytib koʻrsatilmaydi.
      Hoziroq yetkazing. Yoʻqolsa — shu yerdan yangisini qoʻyasiz.
    </p>
    <a class="btn btn--lead" href="users.php">Tushunarli</a>
  </section>
<?php endif; ?>

<?php if ($single): ?>
  <p class="muted lede">
    Hozircha adminkaga bitta parol bilan kiriladi. Quyida birinchi
    foydalanuvchini qoʻshsangiz, hozirgi parol <b>admin</b> nomli egaga
    biriktiriladi va oʻsha parol bilan ishlashda davom etasiz — serverda
    hech narsani oʻzgartirish shart emas.
  </p>
<?php endif; ?>

<table class="rows">
  <thead>
    <tr><th>Kim</th><th>Login</th><th>Huquqi</th><th>Oxirgi faolligi</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): $self = $u['id'] === $user['id']; ?>
    <tr<?= $u['active'] ? '' : ' class="row--off"' ?>>
      <td>
        <b><?= app_h($u['name']) ?></b><?= $self ? ' <span class="chip">siz</span>' : '' ?>
        <?php if (!$u['active']): ?><span class="chip chip--off">oʻchirilgan</span><?php endif; ?>
        <?php if ($u['created_at'] !== ''): ?>
          <div class="muted small">qoʻshilgan: <?= app_h(substr($u['created_at'], 0, 10)) ?><?= $u['created_by'] !== '' ? ' · ' . app_h($u['created_by']) : '' ?></div>
        <?php endif; ?>
      </td>
      <td class="nowrap"><code><?= app_h($u['login']) ?></code></td>
      <td>
        <form method="post" class="inline">
          <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
          <input type="hidden" name="do" value="role">
          <input type="hidden" name="id" value="<?= app_h($u['id']) ?>">
          <select name="role" data-autosubmit>
            <?php foreach (ROLE_LABELS as $key => $label): ?>
              <option value="<?= app_h($key) ?>" <?= $u['role'] === $key ? 'selected' : '' ?>><?= app_h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit">Saqlash</button></noscript>
        </form>
      </td>
      <td class="nowrap muted"><?= $u['last_seen'] !== '' ? app_h(str_replace('T', ' ', substr($u['last_seen'], 0, 16))) : '—' ?></td>
      <td class="nowrap actions-cell">
        <details class="menu">
          <summary class="btn btn--quiet">Oʻzgartirish</summary>
          <div class="menu__body">
            <form method="post">
              <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
              <input type="hidden" name="do" value="password">
              <input type="hidden" name="id" value="<?= app_h($u['id']) ?>">
              <label for="pw<?= app_h($u['id']) ?>">Yangi parol</label>
              <input id="pw<?= app_h($u['id']) ?>" name="password" type="text" autocomplete="off" minlength="10" required>
              <button type="submit">Parolni qoʻyish</button>
            </form>
            <form method="post" class="inline">
              <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
              <input type="hidden" name="do" value="active">
              <input type="hidden" name="id" value="<?= app_h($u['id']) ?>">
              <input type="hidden" name="on" value="<?= $u['active'] ? '0' : '1' ?>">
              <button class="btn btn--quiet" type="submit"<?= $self && $u['active'] ? ' disabled' : '' ?>>
                <?= $u['active'] ? 'Vaqtincha oʻchirish' : 'Qayta yoqish' ?>
              </button>
            </form>
            <form method="post" class="inline" data-confirm="Foydalanuvchi butunlay oʻchiriladi. Davom etilsinmi?">
              <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
              <input type="hidden" name="do" value="remove">
              <input type="hidden" name="id" value="<?= app_h($u['id']) ?>">
              <button class="btn btn--quiet btn--danger" type="submit"<?= $self ? ' disabled' : '' ?>>Oʻchirish</button>
            </form>
          </div>
        </details>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<section class="pw">
  <form method="post" class="card">
    <h2>Yangi foydalanuvchi</h2>
    <input type="hidden" name="token" value="<?= app_h(admin_token()) ?>">
    <input type="hidden" name="do" value="add">
    <label for="login">Login</label>
    <input id="login" name="login" type="text" required minlength="3" maxlength="32"
           pattern="[a-z0-9][a-z0-9._-]{2,31}" autocomplete="off"
           placeholder="masalan: dilnoza">
    <label for="name">Ism-sharifi</label>
    <input id="name" name="name" type="text" maxlength="80" autocomplete="off" placeholder="Dilnoza Karimova">
    <label for="role">Huquqi</label>
    <select id="role" name="role">
      <?php foreach (ROLE_LABELS as $key => $label): ?>
        <option value="<?= app_h($key) ?>" <?= $key === 'editor' ? 'selected' : '' ?>><?= app_h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="password">Dastlabki parol</label>
    <input id="password" name="password" type="text" required minlength="10" autocomplete="off"
           data-suggest-password>
    <p class="muted">
      Kamida 10 belgi. Parol bir marta koʻrsatiladi va saqlanmaydi.
      Egasi hamma narsani qila oladi, muharrir saytni tahrirlaydi va loyiha
      qoʻshadi, kuzatuvchi faqat koʻradi. Arizalar CSV sini faqat egasi
      yuklab ola oladi.
    </p>
    <button type="submit">Qoʻshish</button>
  </form>
</section>
</main>
<?php
admin_foot();
