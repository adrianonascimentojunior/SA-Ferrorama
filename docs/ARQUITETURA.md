# Arquitetura atual

O Apache do XAMPP serve `index.php` e `public/assets`. `index.php` protege páginas por sessão e papel. O JavaScript puro apresenta os módulos, envia JSON à API em `/SA-Ferroama/api/` e atualiza dados visíveis periodicamente. `app/api.php` é o ponto de entrada da API: aplica autenticação, papel, CSRF e manutenção, e encaminha cada requisição para o módulo correspondente em `app/routes/` (um arquivo por área: `auth`, `dashboard`, `trens`, `sensores`, `rotas`, `leituras`, `alertas`, `mapa`, `bilhetes`, `relatorios`, `notificacoes`, `perfil`, `suporte`, `admin`). PDO conecta ao MySQL Server 8.4 em `127.0.0.1:3306`, com prepared statements e `utf8mb4`.

O `.htaccess` da raiz permite por HTTP apenas a entrada `index.php`, a rota `api/` e `public/assets/`. Ferramentas locais de análise de código, caso instaladas, não são servidas e não participam da execução da aplicação.

```text
Brave → Apache /SA-Ferroama/ → página PHP + JS → /api/* → PHP → PDO MySQL → MySQL Server 8.4
```

As 16 tabelas são `users`, `password_resets`, `stations`, `trains`, `sensors`, `routes`, `sensor_readings`, `maintenances`, `alerts`, `notification_reads`, `notification_preferences`, `schedules`, `ticket_simulations`, `support_requests`, `app_settings` e `audit_logs`. FKs usam InnoDB. Três gatilhos chamam `refresh_sensor_latest` após inserção, edição e exclusão de leituras, inclusive troca de `sensor_id`.

O acesso usa hash de senha, regeneração da sessão no login, cookie `HttpOnly`/`SameSite=Lax` e `Secure` em HTTPS. Mutações autenticadas exigem CSRF. Cadastro público cria `operator`; rotas e simulador exigem `manager` ou `super_admin`; administração exige `super_admin`. O último Super Admin ativo não pode excluir ou desativar a própria conta. O modo de manutenção preserva acesso administrativo. Logs de auditoria são apenas leitura na interface. `app_settings` limita a três chaves validadas; `max_execution_time` limita consultas SELECT em milissegundos no MySQL.

O esquema contém 16 tabelas da aplicação. As consultas operacionais usam a conta MySQL `atrain_app`, com permissões limitadas por tabela. A regra de limites está em `includes/limites.php`; leitura e alerta derivado são gravados na mesma transação.

Banco e API usam UTC. `TIMESTAMP` normaliza leituras inseridas em outras sessões; campos `DATETIME` de agenda e recuperação recebem valores explícitos em UTC. O navegador converte a exibição ao fuso local. A API mantém os contratos listados em [ROTAS_E_REGRAS.md](ROTAS_E_REGRAS.md).

Mapa SVG usa coordenadas cadastradas. A simulação de passagem persiste estimativas, sem reserva. Preferências de comunicação são persistidas, mas não há envio externo. O código de recuperação é exibido apenas em desenvolvimento.
