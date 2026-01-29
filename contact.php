<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function normalize(string $s, int $maxLen): string {
  $s = trim($s);
  $s = str_replace(["\r\n", "\r"], "\n", $s);
  if (mb_strlen($s) > $maxLen) $s = mb_substr($s, 0, $maxLen);
  return $s;
}
function badRequest(string $msg): void {
  http_response_code(400);
  echo "<!doctype html><meta charset='utf-8'><title>送信エラー</title>";
  echo "<div style='font-family:system-ui;padding:24px'>";
  echo "<h2>送信エラー</h2><p>" . h($msg) . "</p>";
  echo "<p><a href='index.html#contact'>戻る</a></p>";
  echo "</div>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  badRequest('不正なアクセスです。');
}

// honeypot（bot対策）
$honeypot = $_POST['company'] ?? '';
if (is_string($honeypot) && trim($honeypot) !== '') {
  badRequest('送信に失敗しました。');
}

$name    = normalize((string)($_POST['name'] ?? ''), 120);
$email   = normalize((string)($_POST['email'] ?? ''), 160);
$tel     = normalize((string)($_POST['tel'] ?? ''), 40);
$type    = normalize((string)($_POST['type'] ?? ''), 80);
$message = normalize((string)($_POST['message'] ?? ''), 4000);

if ($name === '' || $email === '' || $type === '' || $message === '') {
  badRequest('必須項目が未入力です。');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  badRequest('メールアドレスの形式が正しくありません。');
}

// 保存先（同階層/data に保存）
$saveDir = __DIR__ . '/data';
$csvFile = $saveDir . '/inquiries.csv';

if (!is_dir($saveDir)) {
  if (!mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
    badRequest('サーバ側で保存フォルダを作成できませんでした。');
  }
}

$isNew = !file_exists($csvFile);

$fp = fopen($csvFile, 'ab');
if ($fp === false) badRequest('CSVファイルを開けませんでした。');

if (!flock($fp, LOCK_EX)) {
  fclose($fp);
  badRequest('保存処理に失敗しました（ロック）。');
}

if ($isNew) {
  fputcsv($fp, ['created_at','name','email','tel','type','message','ip','user_agent']);
}

fputcsv($fp, [
  date('c'),
  $name,
  $email,
  $tel,
  $type,
  $message,
  $_SERVER['REMOTE_ADDR'] ?? '',
  $_SERVER['HTTP_USER_AGENT'] ?? ''
]);

fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>送信完了 | 株式会社Jecコンサルティング</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
  <div class="container py-5">
    <div class="p-4 p-md-5 rounded-4 border border-light border-opacity-10" style="background: rgba(255,255,255,.06);">
      <h1 class="h3 fw-bold mb-3">送信完了</h1>
      <p class="text-white-50 mb-4">お問い合わせを受け付けました。内容を確認後、折り返しご連絡します。</p>

      <div class="small text-white-50 mb-2">受付内容（確認）</div>
      <div class="mb-4">
        <div><span class="text-white-50">お名前：</span><?= h($name) ?></div>
        <div><span class="text-white-50">メール：</span><?= h($email) ?></div>
        <div><span class="text-white-50">種別：</span><?= h($type) ?></div>
      </div>

      <a class="btn btn-primary" href="index.html#contact">戻る</a>
    </div>
  </div>
</body>
</html>
