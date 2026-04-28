<?php
session_start();

// ── Connexion DB ──────────────────────────────────────────────
$conn = new mysqli("localhost", "root", "", "artisanat");
if ($conn->connect_error) {
    http_response_code(500);
    exit("Erreur de connexion à la base de données.");
}

// ── Méthode POST uniquement ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Méthode non autorisée.");
}

// ── Protection CSRF ───────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Décommentez ces lignes si vous utilisez le token CSRF côté HTML :
// if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
//     http_response_code(403);
//     exit("Requête non valide (CSRF).");
// }

// ── Protection brute-force (limite de tentatives) ─────────────
$maxAttempts = 5;
$lockoutTime = 15 * 60; // 15 minutes en secondes

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last_attempt'] = time();
}

// Réinitialiser après le délai de blocage
if ((time() - $_SESSION['login_last_attempt']) > $lockoutTime) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SESSION['login_attempts'] >= $maxAttempts) {
    $remaining = $lockoutTime - (time() - $_SESSION['login_last_attempt']);
    http_response_code(429);
    exit("Trop de tentatives. Réessayez dans " . ceil($remaining / 60) . " minute(s).");
}

// ── Récupération et validation des entrées ────────────────────
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
    http_response_code(422);
    exit("E-mail ou mot de passe invalide.");
}

// ── Recherche de l'utilisateur (prepared statement) ───────────
$stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();
$conn->close();

// ── Vérification du mot de passe ──────────────────────────────
if ($user && password_verify($password, $user['password'])) {
    // Réinitialiser les tentatives
    $_SESSION['login_attempts'] = 0;

    // Régénérer l'ID de session pour éviter la fixation de session
    session_regenerate_id(true);

    // Stocker les données utilisateur en session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];

    // Mettre à jour le hash si l'algo a évolué
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        // Idéalement, faire une requête UPDATE ici
    }

    http_response_code(200);
    echo "Connexion réussie ! Bienvenue, " . htmlspecialchars($user['username']) . ".";

    // Redirection côté serveur possible ici :
    // header("Location: dashboard.php");
    // exit();

} else {
    // Incrémenter les tentatives
    $_SESSION['login_attempts']++;
    $_SESSION['login_last_attempt'] = time();

    http_response_code(401);
    echo "Email ou mot de passe incorrect.";
}