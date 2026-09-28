<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$email = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Defina ADMIN_EMAIL e ADMIN_PASSWORD (mínimo 12 caracteres).\n"); exit(1);
}
$existing = query('SELECT id FROM users WHERE email=?', [strtolower($email)])->fetchColumn();
if ($existing) { fwrite(STDERR, "E-mail já cadastrado; operação cancelada.\n"); exit(1); }
query("INSERT INTO users (name,email,password_hash,role,job_title) VALUES (?,?,?,'super_admin','Administrador da plataforma')", ['Super Admin',strtolower($email),password_hash($password,PASSWORD_DEFAULT)]);
echo "Super Admin criado.\n";
