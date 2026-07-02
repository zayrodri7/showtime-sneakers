<?php
session_start();

$error = "";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === "IR247" && $password === "ADMIN123$") {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 40px;
        }

        .login-box {
            background: white;
            padding: 30px;
            max-width: 400px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        input, button {
            width: 100%;
            padding: 12px;
            margin-top: 12px;
        }

        button {
            background: black;
            color: white;
            border: none;
            cursor: pointer;
        }

        .error {
            color: red;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h1>Admin Login</h1>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" name="login">Login</button>
    </form>
</div>

</body>
</html>