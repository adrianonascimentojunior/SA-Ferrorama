# Plano de refatoração — SA-Ferrorama

Este documento registra o plano seguido para a refatoração de legibilidade do backend, no formato de instruções (prompt) que guiou o trabalho. Serve tanto como registro de decisão quanto como roteiro para continuar o mesmo padrão em partes ainda não tocadas.

## Objetivo

Tornar o código fácil de seguir por alguém sem bagagem técnica forte: uma instrução por linha, nomes de arquivo e de função que dizem o que fazem, arquivos pequenos e focados em um único assunto. Sem mudar comportamento, stack ou decisões de design já tomadas pela equipe (PHP 8.2 + PDO, JavaScript puro, MySQL, SPA servida por `index.php`).

## Diagnóstico do estado anterior

| Problema encontrado | Onde | Por que atrapalha a leitura |
| --- | --- | --- |
| Arquivo único com 399 linhas e todas as rotas da API | `app/api.php` | Para entender uma regra de trem é preciso rolar por regras de bilhete, admin, relatório etc. |
| Várias instruções na mesma linha (`if (...) { ...; ...; }` tudo junto) | `app/api.php`, `app/bloco3.php` | Cada linha faz mais de uma coisa; difícil apontar "a linha que faz X" |
| Lógica de permissão de trem/sensor/rota misturada com lógica de HTTP | `app/bloco3.php` | Funções auxiliares (cálculo de indicador, seleção de sensor) escondidas no meio do roteamento |
| HTML e PHP condensados em poucas linhas longas | `index.php`, `includes/cabecalho.php` | Tags e lógica de permissão difíceis de distinguir visualmente |
| Comentários explicando o óbvio | `includes/limites.php` | Texto que não agrega e que o pedido do responsável pediu para remover |

## Instrução seguida (prompt)

```
Divida app/api.php em um arquivo por área de negócio, dentro de app/routes/.
Cada arquivo deve:
  - tratar apenas as rotas daquela área (trens, sensores, rotas, leituras,
    alertas, relatórios, bilhetes, mapa, notificações, perfil, suporte, admin);
  - manter exatamente as mesmas consultas SQL, validações e mensagens de erro
    do arquivo original — isto é refatoração de forma, não de comportamento;
  - usar uma instrução por linha, com "if" sempre em bloco (chaves), mesmo
    quando o corpo é uma linha só;
  - não ter nenhum comentário de texto explicando o código.

Mova para app/bootstrap.php as funções auxiliares usadas por mais de um
arquivo de rota (sensorSelect, indicadorCalculado, rotaDados, filtroId,
modoDeManutencaoAtivo), já que bootstrap.php é o lugar hoje reservado para
utilidades compartilhadas.

Deixe app/api.php como roteador: resolve o caminho da requisição, cuida de
autenticação/CSRF/manutenção uma única vez, e decide qual arquivo de
app/routes/ requerer para cada caso. Sem lógica de negócio nele.

Reformate index.php, includes/cabecalho.php, includes/proteger.php e
sair.php com o mesmo padrão de uma instrução por linha, sem alterar o que
cada página faz.

Rode "php -l" em todos os arquivos tocados. Confira, comparando com o
histórico do Git, que nenhuma rota, validação ou mensagem foi perdida na
divisão.

Não toque em: schema do banco, nomes de tabela/coluna, contrato HTTP da
API (métodos e caminhos), regras de permissão por papel, CSS e JavaScript
do front-end — fora do escopo deste passe.
```

## O que foi feito

- `app/api.php` (399 linhas, com tudo misturado) virou um roteador de ~75 linhas.
- 14 novos arquivos em `app/routes/`, cada um só com as rotas do seu assunto (20 a 150 linhas cada).
- `app/bloco3.php` foi removido; suas funções auxiliares passaram para `app/bootstrap.php`, ao lado das demais funções compartilhadas.
- `index.php`, `includes/cabecalho.php` e `sair.php` reformatados no mesmo padrão.
- Documentação (`README.md`, `docs/ARQUITETURA.md`, `docs/ROTAS_E_REGRAS.md`) atualizada para descrever a nova organização.

## O que ficou fora deste passe (próximos candidatos)

| Item | Situação | Recomendação |
| --- | --- | --- |
| `public/assets/js/app.js` e `bloco3.js` | Já organizados em poucas funções, mas ainda com trechos condensados | Aplicar o mesmo padrão (uma instrução por linha) em outro passe dedicado ao front-end |
| `public/assets/css/app.css` (2051 linhas) | Extenso, mas é normal para CSS de um sistema com várias telas | Só vale dividir por tela/componente se a equipe sentir dificuldade real de navegação |
| `scripts/validate_local.php` (264 linhas) | Script de validação HTTP, não é tela nem API de produção | Pode seguir o mesmo tratamento depois, com menor prioridade |

## Como validar que nada quebrou

1. `C:\xampp\php\php.exe -l` em cada arquivo alterado (já executado, sem erros).
2. Com Apache e MySQL ativos: `C:\xampp\php\php.exe scripts\validate_local.php --all`, que exercita autenticação, CRUD, simulador, relatórios e administração pela própria API.
3. Teste manual pelo roteiro já existente no README/`docs/VALIDACAO.md`.
