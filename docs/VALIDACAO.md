# Validação local

## Pré-requisitos

Apache do XAMPP, PHP com `pdo_mysql` e MySQL Server 8.4 em `127.0.0.1:3306`. Configure `config/local.php` a partir de `config/local.example.php` sem versionar a senha. Para testar a instalação do banco em um ambiente vazio, importe `database/schema.mysql.sql` e depois `database/seed.mysql.sql` pelo phpMyAdmin.

## Verificações

```powershell
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l app\api.php
C:\xampp\php\php.exe -l app\bootstrap.php
C:\xampp\php\php.exe -l scripts\create_admin.php
C:\xampp\php\php.exe -l scripts\validate_local.php
C:\xampp\php\php.exe scripts\validate_local.php --all
```

O teste HTTP usa apenas PHP CLI e deve rodar contra um banco descartável com esquema e seed importados. Defina `DB_NAME` para o nome desse banco tanto no processo do servidor HTTP quanto no terminal que executa o teste; o script recusa o banco padrão `frota_ferroviaria`. Configure `ATRAIN_TEST_BASE_URL` com a URL desse servidor, incluindo `/SA-Ferroama/`, e mantenha as credenciais do banco em `config/local.php` ignorado pelo Git. O teste cria e remove contas, trens e sensores temporários; outros registros de auditoria e de fluxos simulados podem permanecer no banco descartável. `--block2` verifica apenas sessão, CSRF, papéis, trens, sensores e saída; `--all` também percorre os endpoints já existentes de suporte, notificações, relatórios, simulação, perfil e administração.

`http://localhost/SA-Ferroama/api/health` deve retornar `{"status":"ok","database":true}`. O acesso direto a `app/`, `config/`, `database/`, `scripts/` e documentação deve retornar HTTP 403.

## Resultado nesta instalação — 28/09/2026

- Esquema e seed importados em MySQL 8.4: 15 tabelas, cinco trens, cinco estações, seis sensores e quatro leituras iniciais.
- Gatilhos recalcularam o último valor após inserir, editar, mover e excluir leituras.
- Teste HTTP completo passou para operador, gerente e Super Admin após a organização de diretórios.
- Dez páginas operacionais e duas administrativas renderizaram no Brave em 390 e 1366 pixels sem rolagem horizontal da página.
- phpMyAdmin abriu o banco `frota_ferroviaria` com a conta limitada `atrain_app` no servidor `127.0.0.1`.

E-mail, SMS, push, pagamentos, GPS e sensores físicos não têm integração externa. O código de recuperação só é exibido em desenvolvimento; tamanhos de tela intermediários não foram conferidos manualmente.
