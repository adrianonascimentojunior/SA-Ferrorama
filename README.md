# A-Train / SA-Ferrorama

Sistema local de monitoramento ferroviário em PHP 8.2, JavaScript puro e MySQL Server 8.4, servido pelo Apache do XAMPP. Bilhetes, pagamentos, e-mail, push e SMS são simulações explícitas.

## Requisitos

Apache, PHP 8.2 com `PDO` e `pdo_mysql`, MySQL Server 8.4 e um navegador. Os assets são servidos diretamente, sem etapa de compilação.

## Instalação no XAMPP

1. Confirme a instalação do XAMPP e o caminho real de `htdocs`. Nesta máquina são `C:\xampp` e `C:\xampp\htdocs`. Copie o conteúdo do projeto para `C:\xampp\htdocs\SA-Ferroama`, sem criar uma pasta aninhada.
2. Inicie **Apache** no XAMPP e o serviço Windows **MySQL_A_TRAIN** (MySQL Community Server 8.4.9, `127.0.0.1:3306`). Confirme as extensões PHP `PDO` e `pdo_mysql` com `C:\xampp\php\php.exe -m`.
3. Em `http://localhost/phpmyadmin`, conecte-se ao MySQL em `127.0.0.1` com uma conta administrativa para instalar o banco. Crie `frota_ferroviaria` com charset `utf8mb4` e collation `utf8mb4_unicode_ci`, e importe [schema.mysql.sql](database/schema.mysql.sql) seguido de [seed.mysql.sql](database/seed.mysql.sql) em banco vazio. O esquema da aplicação tem **16 tabelas**.
4. Copie [local.example.php](config/local.example.php) para `config/local.php` e ajuste host, porta, usuário e senha do MySQL. `local.php` é ignorado pelo Git e bloqueado por HTTP. Nesta máquina a aplicação usa a conta local `atrain_app` com permissões limitadas às tabelas necessárias; não grave credenciais no repositório.
5. Abra `http://localhost/SA-Ferroama/`. O primeiro acesso vai ao login; cadastros públicos recebem o papel `operator`.

Para consultar os dados atuais pelo phpMyAdmin nesta máquina, use a conta MySQL `atrain_app` em `127.0.0.1` e abra o banco `frota_ferroviaria`. Essa conta tem permissões de operação nas tabelas necessárias, mas não cria o banco nem importa o esquema. A senha é a da configuração local ignorada pelo Git; não a publique.

## Bloco 2: sessão, trens e sensores

Em uma instalação nova, importe o esquema e os dados iniciais como descrito acima. Em uma instalação existente, importe uma vez [2026-10-06-bloco2.sql](database/migrations/2026-10-06-bloco2.sql) com uma conta administrativa antes de atualizar o código. A migração preserva os trens e sensores atuais; ano e capacidade em toneladas dos trens antigos ficam vazios até a edição, pois a capacidade anterior representa passageiros e não pode ser convertida com segurança. A conta local `atrain_app` tinha apenas `SELECT` e `UPDATE` em `sensors`; execute também como administrador (ajustando banco e conta se forem diferentes):

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON frota_ferroviaria.sensors TO 'atrain_app'@'127.0.0.1';
```

As telas canônicas deste bloco são `trens.php`, `trens_form.php`, `sensores.php`, `sensores_form.php` e `sair.php`. Todas usam a sessão existente. A navegação mostra nome e papel; as telas de gestão exigem `manager` ou `super_admin` também no servidor. Na correspondência com o guia, `operator` é usuário comum, `manager` é gerente e `super_admin` é administrador. O papel de maquinista e a atribuição de um trem a ele ainda não existem nesta base, portanto não se apresenta uma consulta de sensores por maquinista sem uma associação verificável.

O prefixo do trem é único no MySQL e segue `TR-204`; o código do sensor também é único e segue `S-TEMP-001`. O sensor exige trem existente. A chave estrangeira de sensor para trem usa `ON DELETE RESTRICT`: excluir um trem com sensores é bloqueado com mensagem clara, para não apagar sensores e possíveis leituras históricas. No Bloco 2, o indicador de última leitura era preenchido manualmente; o Bloco 3 o calcula a partir da leitura mais recente.

## Bloco 3: rotas, leituras e alertas

Em um banco existente que já recebeu a migração do Bloco 2, importe uma vez [2026-10-16-bloco3.sql](database/migrations/2026-10-16-bloco3.sql) com uma conta administrativa **antes** de usar o código novo. Para banco vazio, importe apenas o esquema e o seed atuais. Se a conta da aplicação tiver permissões específicas por tabela, garanta `SELECT, INSERT, UPDATE, DELETE` em `routes`, `SELECT, INSERT` em `sensor_readings` e `SELECT, INSERT, UPDATE` em `alerts`, além das permissões já existentes. O simulador é limitado a 100 leituras por pedido e exige papel `manager` ou `super_admin`.

As telas deste bloco são `rotas.php`, `rotas_form.php`, `leituras.php`, `simulador.php` e `notificacoes_alertas.php`. Rotas incluem as viagens programadas e filtram por status, intervalo de datas, trem e texto de origem ou destino. Leituras filtram por sensor e trem e exibem no máximo 100 linhas. A central filtra alertas gerados por leitura por severidade e tipo de sensor. Sensores sem leitura exibem “Sem leitura”.

### Decisões de modelagem

| Decisão | Escolha e motivo |
| --- | --- |
| Data e hora da rota | `DATE` e `TIME` separados facilitam o filtro por dia. Chegada informada deve ser posterior à partida no mesmo dia; viagem que atravessa a meia-noite exigiria ampliar o modelo. |
| Formato da leitura | Um valor numérico e sua unidade por leitura permitem novos tipos de sensor sem adicionar colunas; comparações entre medidas diferentes exigem consultar o tipo do sensor. A unidade histórica permanece correta se o cadastro mudar. |
| Local dos limites | [limites.php](includes/limites.php) concentra faixas e limites usados pelo simulador e pela classificação. Ajustar limites exige alterar o código e publicar a mudança. Tipos sem perfil não podem ser simulados. |
| Severidade | Até o limite é normal; acima dele até 10% é atenção; mais de 10% é crítico. Normal não gera alerta. A opção “acima do limite” garante uma leitura crítica por execução para demonstrar a regra. |
| Alertas e notificações | A tabela `alerts` existente concentra os fatos detectados; `notification_reads` registra quais usuários leram cada aviso. Alertas novos apontam para a leitura de origem; alertas antigos sem leitura vinculada permanecem nas telas legadas. Não há entrega externa de avisos. |

`routes.train_id` usa `ON DELETE RESTRICT`: remova ou transfira as rotas antes de excluir o trem. `sensor_readings.sensor_id` usa `ON DELETE CASCADE`; os novos alertas ligados a `reading_id` também usam `ON DELETE CASCADE`, de modo que excluir leitura ou sensor apaga seus alertas derivados. Os alertas legados continuam com `sensor_id ON DELETE SET NULL`. Leitura e alerta são gravados na mesma transação. Índices em `(sensor_id, recorded_at, id)` e `(recorded_at, id)` sustentam os filtros e a ordenação das leituras sem carregar o histórico inteiro. O campo legado `sensors.reading_indicator` permanece no banco por compatibilidade, mas a API e a tela calculam o indicador da leitura mais recente.

O logout por `POST` limpa as variáveis, destrói a sessão e expira o cookie de sessão. As respostas das páginas não são armazenadas em cache, de modo que voltar e recarregar exige novo login. O cookie expirado também remove do navegador o identificador de sessão antigo.

Para criar o primeiro Super Admin, defina `ADMIN_EMAIL` e `ADMIN_PASSWORD` (12+ caracteres) no ambiente de uma sessão de terminal e execute `C:\xampp\php\php.exe scripts\create_admin.php`. O script não sobrescreve conta existente. Não grave senhas no repositório.

## Estrutura

| Caminho | Uso |
| --- | --- |
| `index.php` | Páginas e proteção inicial de sessão/papel |
| `public/assets/css`, `public/assets/js` | Estilo e interações sem compilação |
| `api/index.php`, `app/api.php` | Entrada HTTP e regras da API JSON |
| `app/bootstrap.php` | PDO MySQL, validações e auditoria |
| `scripts/create_admin.php` | Criação controlada do primeiro Super Admin |
| `config/local.php` | Credenciais locais fora do Git |
| `database/schema.mysql.sql`, `database/seed.mysql.sql` | Importação pelo phpMyAdmin |
| `scripts/validate_local.php` | Validação dos fluxos HTTP locais em PHP CLI |
| `docs/` | Arquitetura, rotas, validação e responsabilidades |

O Apache usa o `DocumentRoot` padrão de `htdocs`. `.htaccess` bloqueia acesso direto a `app/`, configuração, SQL, scripts e documentação. A URL funciona no subdiretório `/SA-Ferroama`. [ROTAS_E_REGRAS.md](docs/ROTAS_E_REGRAS.md) relaciona endpoints, páginas e ações. [CONTRIBUTING.md](CONTRIBUTING.md) descreve os papéis da equipe e a revisão de mudanças.

## Operação

As páginas consultam a API a cada cinco segundos quando visíveis e sem edição ativa. Gatilhos do MySQL recalculam o último valor do sensor ao inserir, editar, mover ou excluir leituras. `TIMESTAMP` normaliza as leituras entre sessões SQL com fusos distintos; a aplicação usa UTC e o navegador exibe datas no fuso local. Para inserir horários `DATETIME` por cliente SQL, configure `SET time_zone = '+00:00'` na sessão. A configuração de tempo limite usa `max_execution_time` para consultas SELECT.

Recuperação de senha exibe o código somente com `APP_ENV=development`. Ele expira em dez minutos e permite cinco tentativas. Entrega real exige integração externa. O mapa é esquemático e usa coordenadas cadastradas, sem GPS contínuo.

`http://localhost/SA-Ferroama/api/health` deve responder `{"status":"ok","database":true}`. Com Apache e MySQL 8.4 ativos, `C:\xampp\php\php.exe scripts\validate_local.php --all` exercita autenticação, CSRF, papéis, CRUD, suporte, notificações, relatórios, simulação e administração. `--block2` limita a validação à sessão, saída, trens e sensores. O teste exige `DB_NAME` apontando para um banco isolado com o esquema e os dados iniciais, a mesma configuração no servidor HTTP e `ATRAIN_TEST_BASE_URL` com a URL de teste. Consulte [VALIDACAO.md](docs/VALIDACAO.md) para o procedimento.

## Equipe e contribuição

As responsabilidades de Adriano Nascimento (backend/integração), Nathan T. Pacheco (banco), Erik Felipe (frontend/design) e Gabriel Gonzatto (telas operacionais) estão preservadas em [GIT_EQUIPE.md](docs/GIT_EQUIPE.md) e [CODEOWNERS](.github/CODEOWNERS). Veja [CONTRIBUTING.md](CONTRIBUTING.md) para o fluxo de revisão e validação.
