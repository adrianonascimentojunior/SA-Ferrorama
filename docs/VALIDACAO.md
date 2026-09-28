# Validação local

## Pré-requisitos

Apache do XAMPP, PHP com `pdo_mysql` e MySQL Server 8.4 em `127.0.0.1:3306`. Configure `config/local.php` a partir de `config/local.example.php` sem versionar a senha. Para testar a instalação do banco em um ambiente vazio, importe `database/schema.mysql.sql` e depois `database/seed.mysql.sql` pelo phpMyAdmin.

## Verificações

```powershell
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l app\api.php
C:\xampp\php\php.exe -l app\bootstrap.php
C:\xampp\php\php.exe -l scripts\create_admin.php
python scripts\validate_local.py
```

O teste HTTP usa `ATRAIN_MYSQL_ROOT_PASSWORD` apenas para conferir e limpar registros temporários. Ele percorre cadastro, login, sessão, CSRF, permissões, frota, suporte, notificações, relatórios, simulações, perfil, configurações e administração. Execute em uma instalação local controlada.

`http://localhost/SA-Ferroama/api/health` deve retornar `{"status":"ok","database":true}`. O acesso direto a `app/`, `config/`, `database/`, `scripts/` e documentação deve retornar HTTP 403.

## Resultado nesta instalação — 28/09/2026

- Esquema e seed importados em MySQL 8.4: 15 tabelas, cinco trens, cinco estações, seis sensores e quatro leituras iniciais.
- Gatilhos recalcularam o último valor após inserir, editar, mover e excluir leituras.
- Teste HTTP completo passou para operador, gerente e Super Admin após a organização de diretórios.
- Dez páginas operacionais e duas administrativas renderizaram no Brave em 390 e 1366 pixels sem rolagem horizontal da página.
- phpMyAdmin abriu o banco `frota_ferroviaria` com a conta limitada `atrain_app` no servidor `127.0.0.1`.

E-mail, SMS, push, pagamentos, GPS e sensores físicos não têm integração externa. O código de recuperação só é exibido em desenvolvimento; tamanhos de tela intermediários não foram conferidos manualmente.
