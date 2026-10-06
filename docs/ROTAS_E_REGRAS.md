# Rotas e regras de negócio

A API está em `app/api.php` e usa as funções de conexão, autenticação e validação de `app/bootstrap.php`. As páginas PHP e o JavaScript em `public/assets/` usam a mesma sessão. O esquema MySQL define 15 tabelas.

| Método e rota da API | Página/ação na interface atual | Regra/persistência |
| --- | --- | --- |
| `GET /health` | diagnóstico local | PDO consulta banco |
| `POST /auth/register` | cadastro | e-mail único, senha 12–128, papel operator |
| `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` | login, sessão, sair | hash, regeneração da sessão, CSRF |
| `POST /auth/forgot`, `POST /auth/reset` | recuperar/redefinir | código com hash, 10 min, cinco tentativas |
| `GET /dashboard?period=` | dashboard | métricas, frota, tendências, alertas e manutenção |
| `GET/POST /trains`, `GET/PATCH/DELETE /trains/{id}` | frota, formulário, detalhes e exclusão | pesquisa, filtros, validações, auditoria |
| `GET /map` | dashboard, mapa e bilhetes | estações, trens e coordenadas |
| `GET /schedules` | horários e filtros | apenas saídas futuras |
| `POST /tickets/simulate` | simular passagem | ida/volta válidas, 1–8 passageiros, total salvo; sem compra |
| `GET /notifications`, `POST /notifications/{id}/read` | alertas/notificações | janela configurável e leitura por usuário |
| `GET/PATCH /notifications/preferences` | preferências | e-mail/push/SMS persistidos; envios simulados |
| `GET /reports`, `GET /sensors` | relatórios, CSV, impressão | filtros, últimas leituras, ocorrências |
| `GET /sensors/{id}/readings` | detalhes do trem | histórico recente por sensor |
| `GET/PATCH /profile`, `POST /profile/password` | perfil/configurações | nome, cargo, senha e auditoria |
| `GET /profile/export` | exportar JSON | dados pessoais, simulações e suporte |
| `POST /profile/deactivate`, `POST /profile/delete` | encerrar conta | confirmação de senha, proteção último Super Admin |
| `POST /support` | ajuda | assunto/mensagem validados e salvos por usuário |
| `GET /users`, `GET /admin/overview` | usuários/admin | papel super_admin, métricas, auditoria |
| `GET/PATCH /admin/settings` | configurações admin | quatro chaves permitidas, limites e auditoria |
| `PATCH /admin/{users,trains,sensors,maintenances,alerts}/{id}` | editores admin | campos permitidos, enums, leitura/alerta crítico e auditoria |

Todas as mutações autenticadas exigem CSRF no backend. `operator` e `manager` podem usar operações comuns; `super_admin` pode usar a administração. O modo de manutenção bloqueia acesso operacional não administrativo. Leituras de sensores sincronizam o valor materializado por três gatilhos SQL; a interface consulta novamente os dados a cada cinco segundos quando visível e sem edição ativa.

## Checklist de interface

| Área | Verificação de ação e dados |
| --- | --- |
| Autenticação | cadastro, login, logout, recuperação, redefinição e sessão |
| Frota | filtro, cadastro, edição, detalhes com histórico, exclusão e persistência |
| Operação | métricas, mapa, sensores, alertas, manutenção e atualização periódica |
| Bilhetes | filtros de horários, ida/volta, passageiros, total e registro da simulação |
| Notificações | leitura por usuário e preferências persistidas |
| Relatórios | filtros, CSV e impressão/PDF do navegador |
| Conta | perfil, senha, exportação, desativação e exclusão protegida |
| Suporte | validação, persistência e confirmação |
| Administração | usuários, frota, sensores, manutenções, alertas, parâmetros e auditoria |

Veja [VALIDACAO.md](VALIDACAO.md) e `scripts/validate_local.php` para a validação HTTP reproduzível. Pagamentos e integrações de comunicação são simulações.
