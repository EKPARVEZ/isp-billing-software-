<?php
require_once '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            $error = "ভুল পাসওয়ার্ড!";
        }
    } else {
        $error = "ইউজার নেই!";
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP Billing - লগইন</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px; /* মোবাইলে স্ক্রিনের সাথে যেন লেগে না যায় */
        }
        .login-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px; /* ল্যাপটপে কার্ডটি খুব বেশি বড় হবে না */
        }
        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .login-header h3 {
            color: #333;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
            padding: 12px;
            color: white;
            font-weight: bold;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            opacity: 0.9;
            color: white;
        }
        /* রেসপনসিভ টেক্সট */
        @media (max-width: 576px) {
            .login-card {
                padding: 20px;
            }
            .login-header h3 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <h3>BD TECHNOLOGY বিলিং সিস্টেম</h3>
            <p class="text-muted">আপনার অ্যাকাউন্টে লগইন করুন</p>
        </div>
        
        <?php if ($error != ''): ?>
            <div class="alert alert-danger py-2" style="font-size: 14px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">ইউজারনেম</label>
                <input type="text" name="username" class="form-control" placeholder="আপনার ইউজারনেম" required>
            </div>
            <div class="mb-3">
                <label class="form-label">পাসওয়ার্ড</label>
                <input type="password" name="password" class="form-control" placeholder="আপনার পাসওয়ার্ড" required>
            </div>
            <button type="submit" class="btn btn-login">লগইন করুন</button>
        </form>
        
        <div class="mt-4 text-center">
           
        </div>
    </div>

</body>
</html>