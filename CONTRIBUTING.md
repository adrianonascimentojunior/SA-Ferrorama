# Contribuindo com o A-Train

O código executado no XAMPP fica em `index.php`, `api/`, `app/` e `public/assets/`. O banco é definido em `database/`; `config/local.php` contém a configuração de cada máquina e não entra no Git. Scripts de instalação e validação ficam em `scripts/`. O inventário de rotas e regras está em [docs/ROTAS_E_REGRAS.md](docs/ROTAS_E_REGRAS.md).

## Responsabilidades

As áreas e pessoas responsáveis são:

| Área | Responsável |
| --- | --- |
| Backend e integração | Adriano Nascimento (`@adrianonascimentojunior`) |
| Banco de dados | Nathan T. Pacheco (`@nathanpacheco-boop`) |
| Frontend e design | Erik Felipe (`@ErikFelipeDalonso`) |
| Telas operacionais do frontend | Gabriel Gonzatto (`@gabrielmartinsgonzatto`) |

O [CODEOWNERS](.github/CODEOWNERS) traduz essas áreas para os caminhos da arquitetura PHP/MySQL. A autoria dos commits identifica as contribuições de cada integrante; a revisão de código segue as áreas indicadas.

## Fluxo de alteração

1. Faça mudanças pequenas e descreva o motivo no commit (`feat:`, `fix:`, `docs:`, `test:` ou `chore:`).
2. Preserve os contratos da API e valide no servidor as permissões, entradas e operações que alteram dados.
3. Ao alterar o esquema, mantenha `database/schema.mysql.sql`, `database/seed.mysql.sql` e a documentação de importação compatíveis com MySQL 8.4.
4. Antes de compartilhar, execute `C:\xampp\php\php.exe -l` nos arquivos PHP alterados e, com Apache/MySQL locais ativos, `python scripts/validate_local.py` para mudanças de comportamento. Verifique `git diff --check` e `git status`.
5. Solicite revisão das áreas afetadas conforme `CODEOWNERS`. Preserve o histórico publicado; não use `push --force` na branch compartilhada.

Não adicione senhas, dados pessoais, `config/local.php`, `.env`, caches ou dependências instaladas. Recursos de pagamento e envio de mensagens continuam identificados como simulações até que exista uma integração real.
