# Relatório de refatoração — SA-Ferrorama

Commit: `90d8cd7` — "Refatorar backend para legibilidade: dividir app/api.php por área de negócio"
Branch: `main` (commitado localmente, ainda não enviado ao GitHub)

## Resumo

| Métrica | Antes | Depois |
| --- | --- | --- |
| Arquivos na API | 2 (`app/api.php`, `app/bloco3.php`) | 15 (`app/api.php` roteador + 14 em `app/routes/`) |
| Linhas em `app/api.php` | 399 | 75 |
| Maior arquivo de rota depois da divisão | — | `app/routes/trens.php`, 161 linhas |
| Instruções por linha | várias (ex.: `if(...){...;...;}` numa linha) | uma por linha, sempre com chaves |
| Comentários explicativos no código | alguns (`includes/limites.php`) | nenhum |
| Comportamento da API (rotas, SQL, validações) | — | inalterado |

## Arquivos criados

| Arquivo | Conteúdo |
| --- | --- |
| `app/routes/auth.php` | Cadastro, login, sessão, logout, recuperação e redefinição de senha |
| `app/routes/dashboard.php` | Indicadores do painel principal |
| `app/routes/trens.php` | CRUD de trens |
| `app/routes/sensores.php` | CRUD de sensores e histórico de leituras por sensor |
| `app/routes/rotas.php` | CRUD de rotas e viagens programadas |
| `app/routes/leituras.php` | Listagem de leituras, perfis de sensor e simulador |
| `app/routes/alertas.php` | Central de alertas operacionais |
| `app/routes/mapa.php` | Estações e posição dos trens |
| `app/routes/bilhetes.php` | Horários e simulação de passagem |
| `app/routes/relatorios.php` | Relatórios por trem/sensor e período |
| `app/routes/notificacoes.php` | Notificações, leitura e preferências |
| `app/routes/perfil.php` | Dados da conta, senha, exportação, desativação |
| `app/routes/suporte.php` | Abertura de chamado de suporte |
| `app/routes/admin.php` | Painel administrativo, configurações e edição de registros |
| `docs/PLANO_REFATORACAO.md` | Plano seguido na refatoração (formato de instruções) |
| `docs/RELATORIO_REFATORACAO.md` | Este relatório |

## Arquivos removidos

| Arquivo | Motivo |
| --- | --- |
| `app/bloco3.php` | Conteúdo dividido entre `app/routes/rotas.php`, `leituras.php`, `alertas.php` e funções movidas para `app/bootstrap.php` |

## Arquivos modificados

| Arquivo | O que mudou |
| --- | --- |
| `app/api.php` | Virou roteador: autentica, valida CSRF, checa manutenção e encaminha para `app/routes/*.php` |
| `app/bootstrap.php` | Recebeu as funções compartilhadas `sensorSelect`, `indicadorCalculado`, `rotaDados`, `filtroId`, `modoDeManutencaoAtivo` |
| `index.php` | Listas e condições reformatadas, uma instrução por linha; HTML indentado |
| `includes/cabecalho.php` | Menu lateral reescrito com arrays nomeados em vez de literais inline |
| `includes/limites.php` | Removidos dois comentários explicativos |
| `sair.php` | Verificação de CSRF dividida em passos nomeados |
| `README.md`, `docs/ARQUITETURA.md`, `docs/ROTAS_E_REGRAS.md` | Atualizados para descrever `app/routes/` |

## O que não foi alterado (fora do escopo deste passe)

| Item | Motivo |
| --- | --- |
| Stack (PHP 8.2 + PDO, JavaScript puro, MySQL, XAMPP/Apache) | Mantida por pedido explícito |
| Schema do banco, nomes de tabela/coluna | Mudar quebraria migrações e dados existentes |
| Contrato HTTP da API (métodos, caminhos, formato de resposta) | O front-end depende dele; qualquer mudança exigiria tocar `public/assets/js/*.js` também |
| Regras de permissão por papel (`operator`, `manager`, `super_admin`) | Comportamento de negócio, não de legibilidade |
| `public/assets/js/app.js`, `bloco3.js`, CSS | Já razoavelmente organizados; tratamento completo ficou para um próximo passe dedicado ao front-end (ver `docs/PLANO_REFATORACAO.md`) |

## Verificação feita

| Verificação | Resultado |
| --- | --- |
| `php -l` em todos os arquivos PHP tocados | Sem erros de sintaxe |
| Conferência de rotas/validações entre o código antigo (histórico do Git) e o novo | Nenhuma rota, validação ou mensagem de erro ausente |
| Execução da suíte `scripts/validate_local.php` | Não executada nesta sessão — exige Apache e MySQL ativos nesta máquina |

## Pendências para quem for revisar

- Rodar `scripts/validate_local.php --all` com o XAMPP de pé antes de confiar 100% no comportamento ponta a ponta.
- O commit está só local; avisar a equipe (Nathan, Erik, Gabriel) antes de dar `git push`, já que reorganiza arquivo que `CODEOWNERS` atribui a mais de uma pessoa.
