<?php
include 'functions.php';

if (!logged_in()) {
    header('Location: login.php');
    exit;
}

// Read and decode credentials
$UserData = file_get_contents("secure/_sessionData");
$json = json_decode($UserData, true);

if (!$json) {
    exit(json_encode(['error' => 'Invalid or missing credentials']));
}

// Extract values correctly
$expiresInMillis = $json['data']['expiresIn']; // Timestamp in milliseconds
$expiresInSeconds = intval($expiresInMillis / 1000); // Convert to seconds
$expiresAt = date('d/m/Y h:i A', $expiresInSeconds); // Format time in Kolkata timezone

// Calculate remaining time
$currentTimestamp = time();
$remainingTime = $expiresInSeconds - $currentTimestamp;

// Get user details properly
$account = array(
    'expiresAt' => $expiresAt,
    'remainingTime' => $remainingTime,
    'sid' => $json['data']['userDetails']['sid'],
    'sName' => $json['data']['userDetails']['sName'],
    'acStatus' => $json['data']['userDetails']['acStatus'] ?? "INACTIVE" // Default to INACTIVE if not set
);

// Read saved M3U type from type.json (defaults to widevine)
$typeFile = 'secure/type.json';
$savedType = 'widevine';
if (file_exists($typeFile)) {
    $typeData = json_decode(file_get_contents($typeFile), true);
    if (isset($typeData['type']) && in_array($typeData['type'], ['widevine', 'clearkey'])) {
        $savedType = $typeData['type'];
    }
}
$toggleLabel = ($savedType === 'widevine') ? 'Widevine' : 'ClearKey';

// Get the current URL for playlist (single URL only)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$playlistUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/Playlist.m3u';

// Status color class
$statusClass = ($account['acStatus'] === 'ACTIVE') ? 'status-active' : (($account['acStatus'] === 'EXPIRED') ? 'status-expired' : 'status-blocked');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Denver Is Alive</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e14;
            background-image:
                radial-gradient(circle at 20% 10%, rgba(99,102,241,0.15), transparent 40%),
                radial-gradient(circle at 80% 90%, rgba(99,102,241,0.1), transparent 40%);
            color: #e5e7eb;
            min-height: 100vh;
            padding: 32px 16px 60px;
        }

        .top-bar {
            display: flex;
            justify-content: center;
            position: relative;
            max-width: 480px;
            margin: 0 auto;
        }

        h1.brand {
            text-align: center;
            font-size: 2.0rem;
            font-weight: 800;
            background: linear-gradient(90deg, #818cf8, #6366f1);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 4px;
        }

        .subtitle {
            text-align: center;
            color: #9ca3af;
            font-size: 1rem;
            margin-bottom: 28px;
        }

        .card {
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
            background: rgba(28,35,43,0.5);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 14px;
            padding: 12px 14px;
            backdrop-filter: blur(10px);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .dashboard-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #f3f4f6;
            margin-bottom: 20px;
        }

        .url-box {
            background: rgba(10,14,20,0.6);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px;
            padding: 8px 10px;
            font-family: monospace;
            font-size: 0.68rem;
            word-break: break-all;
            color: #d1d5db;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 9px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 8px;
            transition: opacity 0.2s ease;
        }
        .btn:active { opacity: 0.85; }


        .btn-primary { background: linear-gradient(90deg, #6366f1, #4f7dfd); }
        .btn-danger { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .btn-toggle { background: linear-gradient(90deg, #6366f1, #4f7dfd); }

        .warning-box {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.4);
            border-radius: 10px;
            padding: 7px 10px;
            color: #f59e0b;
            font-size: 0.70rem;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .warning-box strong { color: #fbbf24; }


        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .stat-box {
            background: rgba(10,14,20,0.5);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px;
            padding: 8px 6px;
            text-align: center;
        }

        .stat-label {
            font-size: 0.6rem;
            letter-spacing: 0.06em;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .stat-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: #f3f4f6;
        }

        .status-active { color: #22c55e; text-shadow: 0 0 10px rgba(34,197,94,0.5); }
        .status-blocked { color: #ef4444; text-shadow: 0 0 10px rgba(239,68,68,0.5); }
        .status-expired { color: #f59e0b; text-shadow: 0 0 10px rgba(245,158,11,0.5); }

        hr.divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.08);
            margin: 20px 0;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .footer a { color: #818cf8; text-decoration: none; }

        .material-icons { font-size: 1.1rem; vertical-align: middle; }

        .note-box {
            margin-top: 4px;
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.25);
            color: #a5b4fc;
            font-size: 0.62rem;
            line-height: 1.4;
        }
        .note-box strong { color: #c7d2fe; }
    </style>
</head>
<body>

    <h1 class="brand">Denver Is Alive</h1>
    <p class="subtitle">TP User details</p>

    <div class="card">
        <div class="dashboard-title">Your Game File Dashboard</div>

        <div class="url-box" id="playlistUrlDisplay"><?= htmlspecialchars($playlistUrl) ?></div>

        <button type="button" class="btn btn-primary" onclick="copyPlaylistUrl()">
            <i class="material-icons">content_copy</i> Copy Link
        </button>

        <div class="warning-box">
            <strong>Warning:</strong> Hellow <?= htmlspecialchars($account['sName']) ?> , Only 2 devices are allowed At a time, i prefer you use in personal otherwise your account will be ban.
        </div>

        <hr class="divider">

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Status</div>
                <div class="stat-value <?= $statusClass ?>"><?= htmlspecialchars($account['acStatus']) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">STB ID</div>
                <div class="stat-value"><?= htmlspecialchars($account['sid']) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Remaining</div>
                <div class="stat-value" id="countdown"></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Expiry Date</div>
                <div class="stat-value"><?= htmlspecialchars($account['expiresAt']) ?></div>
            </div>
        </div>

        <button type="button" class="btn btn-toggle" id="toggleTypeBtn" data-type="<?= $savedType ?>" onclick="toggleType()">
            <i class="material-icons" id="toggleIcon">toggle_on</i> <span id="toggleLabel"><?= $toggleLabel ?></span>
        </button>

        <form method="POST" action="login.php">
            <button type="submit" class="btn btn-danger">
                <i class="material-icons">logout</i> Logout
            </button>
        </form>

        <div class="note-box">
            <strong>Note:</strong> by default script selected Widevine if your account status was "Active" when everything works perfectly. Else If "Deactivated" then switch to ClearKey, Remember in Clearkey only limited items will work.
        </div>
    </div>

    <h2 class="footer">Coded with  by <a href="https://t.me/DenverIsAlivee" target="_blank">Denver1769</a></h2>

    <script>
        const playlistUrl = <?= json_encode($playlistUrl) ?>;

        function startCountdown(expirationTime) {
            function updateCountdown() {
                let now = Math.floor(Date.now() / 1000);
                let remaining = expirationTime - now;

                if (remaining < 0) {
                    document.getElementById('countdown').innerHTML = "Expired";
                    clearInterval(countdownInterval);
                    return;
                }

                let days = Math.floor(remaining / 86400);
                let hours = Math.floor((remaining % 86400) / 3600);
                let minutes = Math.floor((remaining % 3600) / 60);
                let seconds = remaining % 60;

                let countdownText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
                document.getElementById('countdown').innerHTML = countdownText;
            }

            updateCountdown();
            let countdownInterval = setInterval(updateCountdown, 1000);
        }

        function copyPlaylistUrl() {
            const urlInput = document.createElement("input");
            urlInput.value = playlistUrl;
            document.body.appendChild(urlInput);
            urlInput.select();
            document.execCommand('copy');
            document.body.removeChild(urlInput);

            const btn = document.querySelectorAll('.btn-primary')[0];
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="material-icons">check</i> Copied!';
            setTimeout(() => { btn.innerHTML = original; }, 1000);
        }

        function toggleType() {
            const btn = document.getElementById('toggleTypeBtn');
            const label = document.getElementById('toggleLabel');
            let current = btn.getAttribute('data-type');
            let next = (current === 'widevine') ? 'clearkey' : 'widevine';

            // Update UI immediately
            btn.setAttribute('data-type', next);
            label.innerText = (next === 'widevine') ? 'Widevine' : 'ClearKey';

            // Save to type.json without page refresh
            fetch('save_type.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: next })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to save type');
                }
            })
            .catch(err => console.error('Error saving type:', err));
        }

        window.onload = function() {
            startCountdown(<?= $account['remainingTime'] + time() ?>);
        };
    </script>
</body>
</html>