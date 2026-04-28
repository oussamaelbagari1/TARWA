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

// ── Récupération et validation des entrées ────────────────────
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password'] ?? '';

$errors = [];

if (strlen($username) < 3 || strlen($username) > 50) {
    $errors[] = "Le nom d'utilisateur doit contenir entre 3 et 50 caractères.";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Adresse e-mail invalide.";
}
if (strlen($password) < 8) {
    $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
}

if (!empty($errors)) {
    http_response_code(422);
    exit(implode(' ', $errors));
}

// ── Vérification de l'unicité de l'email ──────────────────────
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    exit("Cette adresse e-mail est déjà utilisée.");
}
$stmt->close();

// ── Hachage du mot de passe et insertion ─────────────────────
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $passwordHash);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(201);
    echo "Inscription réussie ! Vous pouvez maintenant vous connecter.";
} else {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    echo "Erreur lors de l'inscription. Veuillez réessayer.";
}