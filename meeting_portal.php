<?php
session_start();
require_once __DIR__ . '/config/meeting.php';

$isLecturer = isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'lecturer';
$success = $_SESSION['open_meeting_success'] ?? '';
$error = $_SESSION['open_meeting_error'] ?? '';
$hostLink = $_SESSION['open_meeting_host_link'] ?? '';
$guestLink = $_SESSION['open_meeting_guest_link'] ?? '';
$code = $_SESSION['open_meeting_code'] ?? '';
unset($_SESSION['open_meeting_success'], $_SESSION['open_meeting_error'], $_SESSION['open_meeting_host_link'], $_SESSION['open_meeting_guest_link'], $_SESSION['open_meeting_code']);

$joinCode = trim((string)($_GET['code'] ?? ''));
if ($joinCode !== '') {
    header('Location: meeting_guest.php?t=' . rawurlencode($joinCode));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Virtual Meetings | UNILIS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
  <header class="border-b bg-white"><div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
    <a href="index.html" class="text-xl font-bold text-blue-900">UNILIS</a>
    <a href="index.html" class="text-sm font-medium text-slate-600 hover:text-blue-800">Back to home</a>
  </div></header>
  <main class="mx-auto max-w-6xl px-5 py-12">
    <div class="mb-10 text-center"><p class="font-semibold text-blue-700">UNILIS virtual meetings</p>
      <h1 class="mt-2 text-4xl font-bold tracking-tight">Meet without selecting a unit</h1>
      <p class="mx-auto mt-3 max-w-2xl text-slate-600">Create a standalone meeting for an event, consultation, or group discussion, then share its invitation code with anyone.</p>
    </div>
    <?php if ($success): ?><div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($guestLink): ?><div class="mb-6 rounded-xl bg-blue-900 p-5 text-white"><p class="font-semibold">Invitation code</p><p class="mt-1 break-all font-mono text-sm"><?= htmlspecialchars($code) ?></p><p class="mt-3 text-sm text-blue-100">Share this link: <a class="underline" href="<?= htmlspecialchars($guestLink) ?>"><?= htmlspecialchars($guestLink) ?></a></p><a class="mt-4 inline-block rounded-lg bg-white px-4 py-2 font-semibold text-blue-900" href="<?= htmlspecialchars($hostLink) ?>">Start meeting</a></div><?php endif; ?>
    <div class="grid gap-6 md:grid-cols-2">
      <section id="create" class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200"><h2 class="text-2xl font-bold">Create a meeting</h2>
        <?php if ($isLecturer): ?>
        <form class="mt-6 space-y-4" method="post" action="actions.php"><input type="hidden" name="action" value="schedule_open_meeting">
          <label class="block text-sm font-medium">Meeting title<input class="mt-1 w-full rounded-lg border border-slate-300 p-3" name="title" required maxlength="255"></label>
          <label class="block text-sm font-medium">Date and time<input class="mt-1 w-full rounded-lg border border-slate-300 p-3" type="datetime-local" name="scheduled_time" required></label>
          <label class="block text-sm font-medium">Duration (minutes)<input class="mt-1 w-full rounded-lg border border-slate-300 p-3" type="number" name="duration" value="60" min="15" max="480" required></label>
          <button class="w-full rounded-lg bg-blue-800 px-4 py-3 font-semibold text-white hover:bg-blue-900">Create meeting</button>
        </form>
        <?php else: ?><p class="mt-4 text-slate-600">Lecturers can create standalone meetings. <a class="font-semibold text-blue-700 underline" href="login.php">Sign in</a> to continue.</p><?php endif; ?>
      </section>
      <section id="join" class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200"><h2 class="text-2xl font-bold">Join a meeting</h2><p class="mt-2 text-slate-600">Paste the invitation code shared by the meeting host.</p>
        <form class="mt-6 space-y-4" method="get"><label class="block text-sm font-medium">Invitation code<input class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-mono" name="code" required pattern="[a-fA-F0-9]{16,64}" placeholder="e.g. a1b2c3…"></label><button class="w-full rounded-lg bg-amber-500 px-4 py-3 font-semibold text-slate-900 hover:bg-amber-400">Join meeting</button></form>
      </section>
    </div>
  </main>
</body></html>
