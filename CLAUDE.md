# Majoris — norma de engenharia

Framework drop-in do CodeIgniter 3. Produção, concorrência real, falha de sessão ou de identity é incidente. Leia `MAJORIS.md` antes de editar: o comportamento e os defaults de lá são a spec. Este arquivo é a forma de escrever. `AGENTS.md` repete o bloco inegociável para Codex e Grok.

Não amplie o escopo. Kernel, Maybe, fila, CLI, assets, observabilidade, `src/` e pacote novo estão fora.

## Autoria

O autor e o responsável por commit, código e pull request é o usuário git ativo (`git config user.name` e `user.email`). Melhoria ou defeito ficam com essa pessoa.

Não assine trabalho de agent, modelo ou sessão:

- Não defina `GIT_AUTHOR_*`, `GIT_COMMITTER_*`, `--author` nem identidade de bot.
- Não use `Co-authored-by`, `Signed-off-by` de agent, "Generated with", "Made with" ou nome de modelo em commit, PR, PHPDoc, cabeçalho ou comentário.
- Não grave id de sessão, URL de transcript ou rodapé de ferramenta.
- Arquivo novo copia o cabeçalho de licença do vizinho no mesmo diretório.

Vale para Claude, Codex, Grok e qualquer outro agente.

## Compatibilidade

- Assinatura pública já existente de `CI_*`, helper, chave de config e a ordem dos hooks não mudam.
- Método novo é aditivo. Chave nova nasce com o comportamento de hoje.
- `system/core/CodeIgniter.php` continua o bootstrap.
- `MY_*` que estende driver ou core continua no mesmo mecanismo de carga.
- `mysqli` permanece funcionando. Não é frente de trabalho e não pode quebrar.
- Não edite `CLAUDE.md` nem `AGENTS.md` numa fatia de código. Não reescreva `MAJORIS.md` para caber numa implementação.

## PHP 7.4

Tudo que o repositório executa precisa rodar em PHP 7.4 e seguir válido até 8.5.

Proibido em código novo e no código que você alterar:

- union types, `mixed`, `match`, `?->`, promoção de construtor, enums, `readonly`, named arguments
- `str_contains`, `str_starts_with`, `str_ends_with` e outras funções nascidas no PHP 8
- vírgula final na lista de parâmetros
- `#[Attribute]` com efeito em runtime

`#[\AllowDynamicProperties]` já presente pode ficar. `declare(strict_types=1)` não entra em `system/`: config de aplicação chega como string, e a coerção padrão do PHP 7.4 é a compatível com app legado.

Método já existente não ganha tipo de parâmetro nem de retorno. `MY_*` sobrescreve esses métodos. Tipo escalar só em método novo, no modo coercitivo.

## Um tipo por arquivo

Cada arquivo tem uma classe, uma interface ou um trait. O nome do arquivo é o que o loader do CI já espera: `sqlsrv_driver.php` declara `CI_DB_sqlsrv_driver`.

Classe auxiliar nova vai para o próprio arquivo e entra com `require_once` relativo. Não há PSR-4 em `system/`.

Não reorganize, nesta leva, os arquivos que já têm mais de uma classe: `system/libraries/Driver.php`, `system/libraries/Xmlrpc.php`, `tests/mocks/database/db/driver.php` e `tests/codeigniter/libraries/Table_test.php`. Arquivo novo não entra nessa lista.

O `phpcs` não varre código de extensão que o PHP 7 removeu (`mysql`, `mssql`, `ibase`, mcrypt em `Encryption.php`), nem `oci8`, nem `PHP8SessionWrapper.php` (esse arquivo só entra no PHP 8; o PHPStan também o ignora). Não apague essas exclusões para "limpar" o relatório. Aviso de depreciação fora do roadmap não falha o `composer check`; erro falha.

Teste novo: uma classe por arquivo, em `tests/codeigniter/database/` ou `tests/codeigniter/libraries/`. Dublê com classe própria fica em `tests/mocks/`, não dentro do teste.

## Estilo

Copie o arquivo que você está editando e o vizinho do mesmo diretório.

- Tab para indentar. Chave de abertura na linha seguinte (Allman).
- `TRUE`, `FALSE`, `NULL`, aspas simples, `elseif`, nomes em snake_case.
- PHPDoc no método novo. Sem comentário que narra o que a linha já diz.
- Não reformatar arquivo que a mudança não precisa tocar.
- O formatador do projeto é o `phpcbf`, só nos paths da sua fatia, antes de `composer check`.

## Erros e config

- Falha de conexão inclui o texto do driver (`sqlsrv_errors()`, `pg_last_error()`) na mensagem que o CI já exibe.
- Valor de config fora do conjunto aceito falha com mensagem clara. Não conecte no escuro e não troque por um default silencioso.
- Sem `@` novo. Sem `catch` vazio. Sem engolir erro de lock.
- Lock de sessão que não é obtido faz `read()` falhar pelo caminho que o driver já usa quando o lock não vem. Não abra sessão vazia.

## Bancos e sessão

- `is_write_type()` não passa a tratar `EXEC` nem `WITH` como escrita. Stored procedure e CTE de leitura precisam continuar devolvendo result set.
- Cursor padrão do `sqlsrv` permanece `SQLSRV_CURSOR_CLIENT_BUFFERED`.
- `save_queries` permanece `TRUE`. Limite de histórico, quando existir, default `0` (sem corte). `last_query()` continua sendo a última.
- SQLite não usa `BEGIN IMMEDIATE` na conexão da aplicação. Isso reserva o arquivo inteiro.
- `sess_lock_wait` default `0` mantém a espera de hoje. `sess_auto_close` default `FALSE`.
- `busy_timeout`, `wal` e `foreign_keys` no SQLite nascem desligados (`0` / `FALSE`).

## Testes

A suíte é a avaliação. Comportamento novo entra com teste que falharia no tree anterior.

`tests/travis/sqlite.phpunit.xml` inclui `tests/codeigniter/` inteiro. Teste de um motor dá `markTestSkipped` quando `DB_DRIVER` não é o dele. Teste de SQL Server sem skip quebra o job de SQLite.

Testes de banco seguem o vizinho: `set_up()`, tabs, uma classe. Concorrência de sessão SQLite usa dois processos e confere que as duas escritas sobrevivem.

Antes de entregar a fatia:

```bash
vendor/bin/phpcbf <arquivos da fatia>
composer check
```

`composer check` é `phpcs`, PHPStan e PHPUnit em `tests/travis/sqlite.phpunit.xml`. PHPStan está em `phpVersion: 70400`, nível 5, com baseline do tree anterior. Código novo não ganha linha no baseline.

O que cada frente precisa cobrir está em `MAJORIS.md`. O mínimo verificável:

- `LIMIT` sem `ORDER BY` no SQL Server gera `ORDER BY (SELECT NULL)`
- `num_rows()` no cursor buffered não materializa `result_array()`
- `insert_id()` após `INSERT` devolve o identity do mesmo batch, e `affected_rows()` é o do `INSERT`
- `encrypt` inválido não conecta; falha de conexão mostra o texto do driver
- `dbdriver` `mysql` e `mssql` dizem que a extensão saiu no PHP 7
- PostgreSQL: `insert_id()` sem sequência devolve `0`; `sess_lock_wait` positivo expira e `read()` falha
- SQLite: `busy_timeout` honrado; WAL e foreign keys só quando ligados; duas sessões concorrentes não se perdem
- `close()` solta o lock; `sess_auto_close` desligado não muda o request
- `save_queries_limit` `0` guarda tudo; N guarda as últimas N

A suíte SQLite já existente continua verde.

## Fora da fatia

Não crie dependência de runtime. Dev-dependências aceitas, e só se ainda não estiverem no `composer.json`: PHP_CodeSniffer 3.x, PHPCompatibility, PHPStan 1.x que instale com `platform.php` 7.4.33, PHPUnit 9.6.

Não faça commit, push, nem pull request por conta própria. Não altere `git config`.
