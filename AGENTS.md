# Majoris

A norma completa está em `CLAUDE.md`. Leia esse arquivo e o `MAJORIS.md` antes de editar. Os dois valem para Claude, Codex e Grok.

## Inegociável

- Escopo é o `MAJORIS.md`: legado CI3, SQL Server 2019, PostgreSQL, SQLite, performance e usabilidade desse caminho. Sem kernel novo, sem Maybe, sem fila, sem CLI, sem assets, sem `src/`.
- PHP 7.4 é o piso. Sem union types, `mixed`, `match`, `?->`, enums, `readonly`, named arguments, `str_contains` e afins. Sem `declare(strict_types=1)` em `system/`.
- Método já existente não ganha tipo. Método novo pode ter tipo escalar. Chave nova nasce com o comportamento de hoje.
- Um arquivo, um tipo (classe, interface ou trait). Não reorganize `Driver.php`, `Xmlrpc.php` nem os mocks de teste que já declaram mais de uma classe (lista no `CLAUDE.md`). Não apague as exclusões do `phpcs.xml.dist`.
- Estilo do vizinho: tab, Allman, `TRUE`/`FALSE`/`NULL`, snake_case. Não reformatar o que a mudança não toca.
- Erro de driver entra na mensagem. Config inválida falha. Lock negado não abre sessão vazia.
- `is_write_type()` não trata `EXEC` nem `WITH` como escrita. Cursor padrão do `sqlsrv` continua buffered. `save_queries` continua `TRUE`.
- Teste de outro motor dá `markTestSkipped` quando `DB_DRIVER` não é o dele. `composer check` verde antes de entregar.
- Autor de commit, PR e código é o usuário git ativo (`git config user.name` / `user.email`). Sem `Co-authored-by`, sem `GIT_AUTHOR_*`, sem nome de modelo, sem id de sessão em commit, PR, cabeçalho ou comentário.
- Mensagem de commit segue Conventional Commits: `feat`, `fix`, `docs`, `chore`, `test`, `refactor` e afins. Sem mensagem solta.
- Não edite `CLAUDE.md` nem `AGENTS.md`. Não faça commit nem push por conta própria.
