<?php
declare(strict_types=1);

session_start();

const ADMIN_PASSWORD = 'ChangeMe123!'; // TODO: change after deployment.
const DATA_FILE = __DIR__ . '/data/data.json';
const LANGS = ['en', 'tr'];

$errors = [];
$notice = null;

if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Logout handler
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Login handler
if (isset($_POST['login_password'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['login_password'])) {
        $_SESSION['ADMIN_AUTHENTICATED'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        header('Location: admin.php');
        exit;
    } else {
        $errors[] = 'Incorrect password.';
    }
}

$isAuthenticated = isset($_SESSION['ADMIN_AUTHENTICATED']) && $_SESSION['ADMIN_AUTHENTICATED'] === true;
$csrfToken = $_SESSION['csrf_token'] ?? '';

$countries = json_decode(file_get_contents(DATA_FILE), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($countries)) {
    $countries = [];
}

function persistCountries(array $data): bool
{
    $encoded = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return (bool) file_put_contents(DATA_FILE, $encoded);
}

function findCountry(array $countries, string $iso2): ?array
{
    foreach ($countries as $item) {
        if (isset($item['iso2']) && strtolower($item['iso2']) === strtolower($iso2)) {
            return $item;
        }
    }
    return null;
}

if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token. Please refresh the page.';
    } else {
        $action = $_POST['crud_action'];
        $iso2 = strtolower(trim($_POST['iso2'] ?? ''));

        if ($action === 'delete') {
            $countries = array_values(array_filter($countries, fn($c) => strtolower($c['iso2'] ?? '') !== $iso2));
            if (persistCountries($countries)) {
                $notice = 'Country removed successfully.';
            }
        } else {
            $countryName = trim($_POST['country'] ?? '');
            $visaType = $_POST['visa_type'] ?? '';
            $flagUrl = trim($_POST['flag'] ?? '');
            $source = trim($_POST['source'] ?? '');

            if ($countryName === '' || $iso2 === '' || !preg_match('/^[a-z]{2}$/', $iso2)) {
                $errors[] = 'Country name and valid ISO alpha-2 code are required.';
            }

            if (!in_array($visaType, ['no_visa', 'e_visa', 'consular'], true)) {
                $errors[] = 'Visa type must be one of: no_visa, e_visa, consular.';
            }

            if ($flagUrl === '') {
                $flagUrl = sprintf('https://flagcdn.com/%s.svg', $iso2);
            }

            $descriptions = [];
            $durations = [];
            foreach (LANGS as $langKey) {
                $descriptionField = trim($_POST["description_$langKey"] ?? '');
                $durationField = trim($_POST["duration_$langKey"] ?? '');
                if ($descriptionField === '' || $durationField === '') {
                    $errors[] = "Description and duration are required for language: $langKey.";
                }
                $descriptions[$langKey] = $descriptionField;
                $durations[$langKey] = $durationField;
            }

            if (empty($errors)) {
                $payload = [
                    'country' => $countryName,
                    'iso2' => $iso2,
                    'visa_type' => $visaType,
                    'descriptions' => $descriptions,
                    'durations' => $durations,
                    'flag' => $flagUrl,
                    'source' => $source,
                    'last_update' => date('Y-m-d')
                ];

                $existingIndex = null;
                foreach ($countries as $idx => $current) {
                    if (strtolower($current['iso2'] ?? '') === $iso2) {
                        $existingIndex = $idx;
                        break;
                    }
                }

                if ($action === 'update' && $existingIndex !== null) {
                    $countries[$existingIndex] = $payload;
                    $notice = 'Country updated successfully.';
                } elseif ($action === 'create') {
                    if ($existingIndex !== null) {
                        $errors[] = 'ISO code already exists. Use edit instead.';
                    } else {
                        $countries[] = $payload;
                        $notice = 'Country added successfully.';
                    }
                }

                if (empty($errors) && persistCountries($countries)) {
                    header('Location: admin.php?success=1');
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['success']) && !$notice) {
    $notice = 'Changes saved.';
}

$editIso = isset($_GET['edit']) ? strtolower($_GET['edit']) : null;
$editCountry = $editIso ? findCountry($countries, $editIso) : null;
$formMode = $editCountry ? 'update' : 'create';
$template = array_merge([
    'country' => '',
    'iso2' => '',
    'visa_type' => 'no_visa',
    'flag' => '',
    'source' => '',
    'descriptions' => array_fill_keys(LANGS, ''),
    'durations' => array_fill_keys(LANGS, '')
], $editCountry ?? []);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin · Türkiye Visa Checker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link rel="stylesheet" href="assets/css/custom.css" />
  </head>
  <body class="min-h-screen bg-slate-900 text-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="flex items-center justify-between mb-10">
        <h1 class="text-3xl font-semibold tracking-tight">Admin · Türkiye Visa Checker</h1>
        <?php if ($isAuthenticated): ?>
        <a href="admin.php?action=logout" class="inline-flex items-center gap-2 rounded-full bg-red-500/20 px-4 py-2 text-sm text-red-300 hover:bg-red-500/30 transition">
          Logout
        </a>
        <?php endif; ?>
      </div>

      <?php if (!$isAuthenticated): ?>
        <section class="max-w-md mx-auto backdrop-card rounded-3xl p-6">
          <form method="post" class="space-y-5">
            <label class="block">
              <span class="text-sm uppercase tracking-wide text-slate-300 mb-3 inline-block">Password</span>
              <input type="password" name="login_password" required class="w-full rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500" />
            </label>
            <?php if (!empty($errors)): ?>
              <p class="text-sm text-red-300"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <button type="submit" class="w-full rounded-full bg-sky-500 hover:bg-sky-400 transition px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white">
              Enter
            </button>
          </form>
        </section>
      <?php else: ?>
        <?php if (!empty($errors)): ?>
          <div class="mb-6 rounded-2xl bg-red-500/20 border border-red-500/30 px-5 py-4 text-sm text-red-200">
            <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>
        <?php if ($notice): ?>
          <div class="mb-6 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 px-5 py-4 text-sm text-emerald-200">
            <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>

        <section class="backdrop-card rounded-3xl p-6 md:p-8 mb-10">
          <h2 class="text-xl font-semibold mb-6"><?= $formMode === 'create' ? 'Add New Country' : 'Edit Country'; ?></h2>
          <form method="post" class="grid gap-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="crud_action" value="<?= $formMode; ?>" />

            <div class="grid md:grid-cols-2 gap-6">
              <label class="block">
                <span class="text-sm uppercase tracking-wide text-slate-300 mb-2 inline-block">Country name</span>
                <input type="text" name="country" required value="<?= htmlspecialchars($template['country'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500" />
              </label>
              <label class="block">
                <span class="text-sm uppercase tracking-wide text-slate-300 mb-2 inline-block">ISO Alpha-2</span>
                <input type="text" name="iso2" required maxlength="2" value="<?= htmlspecialchars($template['iso2'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full uppercase tracking-widest rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500" />
              </label>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
              <label class="block">
                <span class="text-sm uppercase tracking-wide text-slate-300 mb-2 inline-block">Visa type</span>
                <select name="visa_type" class="w-full rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500">
                  <option value="no_visa" <?= $template['visa_type'] === 'no_visa' ? 'selected' : ''; ?>>No visa</option>
                  <option value="e_visa" <?= $template['visa_type'] === 'e_visa' ? 'selected' : ''; ?>>e-Visa</option>
                  <option value="consular" <?= $template['visa_type'] === 'consular' ? 'selected' : ''; ?>>Consular</option>
                </select>
              </label>
              <label class="block">
                <span class="text-sm uppercase tracking-wide text-slate-300 mb-2 inline-block">Flag URL</span>
                <input type="url" name="flag" value="<?= htmlspecialchars($template['flag'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://flagcdn.com/xx.svg" class="w-full rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500" />
                <p class="mt-2 text-xs text-slate-400">Leave blank to auto-fill from flagcdn.com.</p>
              </label>
            </div>

            <label class="block">
              <span class="text-sm uppercase tracking-wide text-slate-300 mb-2 inline-block">Official source URL</span>
              <input type="url" name="source" required value="<?= htmlspecialchars($template['source'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.mfa.gov.tr/" class="w-full rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500" />
            </label>

            <div class="grid gap-6 md:grid-cols-2">
              <?php foreach (LANGS as $langKey): ?>
                <fieldset class="space-y-4">
                  <legend class="text-sm uppercase tracking-wider text-slate-400"><?= strtoupper($langKey); ?> content</legend>
                  <label class="block">
                    <span class="text-xs uppercase tracking-wide text-slate-500 mb-2 inline-block">Description (<?= strtoupper($langKey); ?>)</span>
                    <textarea name="description_<?= $langKey; ?>" rows="3" required class="w-full rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500"><?= htmlspecialchars($template['descriptions'][$langKey] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </label>
                  <label class="block">
                    <span class="text-xs uppercase tracking-wide text-slate-500 mb-2 inline-block">Duration (<?= strtoupper($langKey); ?>)</span>
                    <textarea name="duration_<?= $langKey; ?>" rows="2" required class="w-full rounded-2xl border-none bg-slate-900/80 px-4 py-3 text-slate-100 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500"><?= htmlspecialchars($template['durations'][$langKey] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </label>
                </fieldset>
              <?php endforeach; ?>
            </div>

            <div class="flex items-center gap-3">
              <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 hover:bg-emerald-400 transition px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white">
                <?= $formMode === 'create' ? 'Add country' : 'Save changes'; ?>
              </button>
              <?php if ($formMode === 'update'): ?>
                <a href="admin.php" class="inline-flex items-center gap-2 rounded-full bg-slate-700/50 px-6 py-3 text-sm text-slate-200 hover:bg-slate-700 transition">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <section class="backdrop-card rounded-3xl p-6 md:p-8">
          <h2 class="text-xl font-semibold mb-6">Countries (<?= count($countries); ?>)</h2>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-sm">
              <thead class="text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3 text-left">Country</th>
                  <th class="px-4 py-3 text-left">ISO</th>
                  <th class="px-4 py-3 text-left">Visa type</th>
                  <th class="px-4 py-3 text-left">Last update</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5 text-slate-200">
                <?php foreach ($countries as $item): ?>
                  <tr>
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <img src="<?= htmlspecialchars($item['flag'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" alt="" class="w-8 h-6 rounded border border-white/10 object-cover" />
                        <div>
                          <div class="font-medium"><?= htmlspecialchars($item['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                          <div class="text-xs text-slate-400"><?= htmlspecialchars($item['source'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4 uppercase"><?= htmlspecialchars($item['iso2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-4 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $item['visa_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-4"><?= htmlspecialchars($item['last_update'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="px-4 py-4 text-right">
                      <div class="inline-flex items-center gap-2">
                        <a href="admin.php?edit=<?= urlencode($item['iso2'] ?? ''); ?>" class="rounded-full bg-sky-500/20 px-3 py-1 text-xs text-sky-200 hover:bg-sky-500/30 transition">Edit</a>
                        <form method="post" onsubmit="return confirm('Delete this country?');">
                          <input type="hidden" name="crud_action" value="delete" />
                          <input type="hidden" name="iso2" value="<?= htmlspecialchars($item['iso2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                          <button type="submit" class="rounded-full bg-red-500/20 px-3 py-1 text-xs text-red-300 hover:bg-red-500/30 transition">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($countries)): ?>
                  <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-slate-400">No countries added yet.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </body>
</html>

