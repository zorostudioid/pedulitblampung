<?php
session_start();
if(isset($_SESSION['admin_logged_in'])) {
    header("Location: admin.php"); exit;
}

require_once 'db.php';

// Seed default credentials jika belum ada
try {
    $chk = $db->query("SELECT count(*) FROM settings WHERE setting_key='admin_username'")->fetchColumn();
    if($chk == 0) {
        $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_username','admin')");
        $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_password','" . password_hash('admin123', PASSWORD_DEFAULT) . "')");
    }
} catch(Exception $e) {}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $db_user = $db->query("SELECT setting_value FROM settings WHERE setting_key='admin_username'")->fetchColumn();
        $db_pass = $db->query("SELECT setting_value FROM settings WHERE setting_key='admin_password'")->fetchColumn();

        $pass_ok = password_verify($password, $db_pass)
                   ?: ($db_pass === 'admin123' && $password === 'admin123'); // fallback plaintext lama

        if($username === $db_user && $pass_ok) {
            $_SESSION['admin_logged_in'] = true;
            header("Location: admin.php"); exit;
        } else {
            $error = "Username atau password salah!";
        }
    } catch(Exception $e) {
        $error = "Gagal verifikasi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Skrining TB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --black: #000000;
            --white: #ffffff;
        }
        body {
            font-family: 'Inter', sans-serif;
            letter-spacing: -0.02em;
            color: var(--black);
        }
        .hero-gradient {
            background: linear-gradient(135deg, #10B981, #FBBF24, #EC4899, #8B5CF6);
            background-size: 400% 400%;
            animation: gradientShift 5s ease infinite;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .input-minimal {
            border: none;
            border-bottom: 2px solid var(--black);
            border-radius: 0;
            background: transparent;
            padding: 12px 0;
            font-size: 1.25rem;
            width: 100%;
            transition: all 0.2s;
        }
        .input-minimal:focus {
            outline: none;
            border-bottom: 2px dashed var(--black);
        }
        .btn-pill-black {
            background: var(--black);
            color: var(--white);
            border-radius: 50px;
            padding: 14px 24px;
            font-weight: 500;
            transition: transform 0.1s;
            display: inline-block;
            text-align: center;
            width: 100%;
            cursor: pointer;
        }
        .btn-pill-black:focus {
            outline: 2px dashed var(--white);
            outline-offset: -4px;
        }
        .font-mono-tag {
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="hero-gradient min-h-screen flex items-center justify-center p-4">
    
    <div class="bg-white p-10 w-full max-w-md shadow-2xl rounded-2xl" style="border-radius: 24px;">
        <div class="mb-10">
            <span class="font-mono-tag text-xs text-gray-500 mb-2 block">System Access</span>
            <h1 class="text-4xl font-bold tracking-tight">Login.</h1>
        </div>

        <?php if($error): ?>
        <div class="mb-6 px-4 py-3 border-2 border-black border-dashed font-mono-tag text-xs">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-6">
                <input type="text" name="username" required placeholder="Username" class="input-minimal">
            </div>
            <div class="mb-10">
                <input type="password" name="password" required placeholder="Password" class="input-minimal">
            </div>
            <button type="submit" class="btn-pill-black text-lg">
                Masuk Sistem <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </form>
    </div>

</body>
</html>
