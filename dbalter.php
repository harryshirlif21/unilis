<?php
require_once 'config/db.php';
session_start();

// Optional: Protect it (remove after use)
if (!isset($_GET['debug']) || $_GET['debug'] !== 'showme') {
    die("Add ?debug=showme to URL");
}

echo "<h2>Students Table - Full Details (Email Verification Status)</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; background:#f4f4f4; padding:20px; }
    table { width:100%; border-collapse:collapse; background:white; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    th, td { padding:12px; text-align:left; border-bottom:1px solid #ddd; }
    th { background:#2563eb; color:white; }
    tr:hover { background:#f0f8ff; }
    .verified { color:green; font-weight:bold; }
    .not-verified { color:red; font-weight:bold; }
    .token { font-family:monospace; background:#eee; padding:2px 6px; border-radius:4px; }
</style>";

echo "<table>
<tr>
    <th>ID</th>
    <th>Reg No</th>
    <th>Name</th>
    <th>Email</th>
    <th>Verification Code</th>
    <th>Token Expires</th>
    <th>Status</th>
    <th>Verified At</th>
    <th>Action</th>
</tr>";

$stmt = $conn->prepare("
    SELECT id, reg_no, name, email, verification_code, 
           token_expires_at, is_verified, verified_at 
    FROM students 
    ORDER BY id DESC
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $status = $row['is_verified'] == 1 
        ? "<span class='verified'>VERIFIED</span>" 
        : "<span class='not-verified'>NOT VERIFIED</span>";

    $token_display = $row['verification_code'] 
        ? "<span class='token'>" . substr($row['verification_code'], 0, 20) . "...</span>" 
        : "<em>none</em>";

    $expires = $row['token_expires_at'] 
        ? date('Y-m-d H:i:s', strtotime($row['token_expires_at'])) 
        : "—";

    $verified_at = $row['verified_at'] 
        ? date('Y-m-d H:i:s', strtotime($row['verified_at'])) 
        : "—";

    $link = $row['verification_code'] && $row['is_verified'] == 0
        ? "<a href='https://unilis.jhubafrica.com/verify.php?token={$row['verification_code']}' target='_blank'>Test Link</a>"
        : "—";

    echo "<tr>
        <td>{$row['id']}</td>
        <td>{$row['reg_no']}</td>
        <td><strong>{$row['name']}</strong></td>
        <td>{$row['email']}</td>
        <td>$token_display</td>
        <td>$expires</td>
        <td>$status</td>
        <td>$verified_at</td>
        <td>$link</td>
    </tr>";
}

echo "</table><br>";
echo "<p>Total students: " . $result->num_rows . "</p>";
echo "<p><a href='https://unilis.jhubafrica.com'>Back to Site</a> | 
          <a href='?debug=showme'>Refresh</a></p>";

$stmt->close();
$conn->close();
?>