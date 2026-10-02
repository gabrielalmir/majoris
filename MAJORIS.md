# Majoris

Base drop-in do CodeIgniter 3 para aplicações que continuam no modelo CI3. O escopo é compatibilidade com o legado, SQL Server 2019, PostgreSQL, SQLite, e as melhorias de performance e usabilidade que esses três motores já exigem no código atual.

PHP 7.4–8.5. O mesmo `system/` de hoje, no Apache em que a aplicação já roda. MIT.

Baseline: `91692f5b0` (CodeIgniter 3.2.0-dev com os ajustes de PHP 7.4–8.5). Status: **planejamento**.

`mysqli` continua funcionando. App legado que aponta para MySQL não entra neste roadmap e não pode quebrar.

---

## Compatibilidade

- O app existente troca o `system/` (ou o `$system_path` em `index.php`) e segue com o mesmo `application/`.
- Assinaturas públicas de `CI_*`, helpers, chaves de config já existentes e a ordem dos hooks não mudam.
- `system/core/CodeIgniter.php` continua sendo o bootstrap. Hooks, `MY_*`, `get_instance()`, `redirect()` e `show_error()` ficam onde estão.
- Chave nova nasce com o comportamento de hoje. Método novo é aditivo.
- `MY_*` que estende um driver continua sendo carregado pelo mesmo mecanismo.
- Nada de pacote novo, árvore `src/`, nem dependência Composer obrigatória.

---

## Onde estamos

O suíte PHPUnit cobre PostgreSQL e SQLite (e MySQL) em Ubuntu. A Action roda só PHP 7.4 e 8.5.

| Motor | O que já funciona | O que o código faz hoje |
|---|---|---|
| SQL Server 2019, `sqlsrv` | Query builder, transação, `OFFSET`/`FETCH` no 2012+ | `Encrypt` só como `0`/`1`; `port`, `TrustServerCertificate` e `LoginTimeout` ignorados. `insert_id()` lê `SCOPE_IDENTITY()` num segundo batch e recebe `NULL`. `num_rows()` no cursor buffered (o padrão) materializa o result inteiro; no cursor forward chama `sqlsrv_num_rows()`, que devolve `FALSE`. `LIMIT` sem `ORDER BY` gera `ORDER BY 1`. Sessão em banco não tem lock. |
| SQL Server 2019, `pdo_sqlsrv` | DSN já aceita porta, `TrustServerCertificate`, `LoginTimeout` | `Encrypt` só entra no DSN quando é `TRUE` (`Encrypt=1`). `LIMIT` repete o `ORDER BY 1`. |
| PostgreSQL | Driver, `LASTVAL()`, `pg_advisory_lock` na sessão | O lock espera sem limite. `insert_id()` estoura erro quando a sessão não tem sequência. |
| SQLite | Driver, `lastInsertRowID()` | Sem `busy_timeout`. Sem caminho para WAL ou foreign keys. Sessão em tabela não tem lock: dois requests perdem update. |

`save_queries` permanece `TRUE`. Quem lê `$this->db->queries` ou usa o profiler depende disso.

---

## Fase 1 — SQL Server 2019

Arquivos: `system/database/drivers/sqlsrv/`, `system/database/drivers/pdo/subdrivers/pdo_sqlsrv_driver.php`, e o ponto em que `system/database/DB.php` carrega o driver.

**Conexão.** No driver nativo, honrar:

- `encrypt`: bool ou `yes` / `no` / `strict` / `optional`
- `trust_server_certificate`
- `login_timeout`
- `port`, anexada ao hostname quando ele ainda não vier como `host,porta`

ODBC Driver 18 criptografa por padrão; sem `TrustServerCertificate` o certificado autoassinado recusa a conexão. No `pdo_sqlsrv`, aceitar `encrypt` como string no mesmo conjunto de valores.

Falha de `sqlsrv_connect` inclui o texto de `sqlsrv_errors()` na mensagem já exibida pelo CI.

**`insert_id()`.** O `INSERT` executado pelo driver (query builder ou `$this->db->query('INSERT ...')`) vai no mesmo batch que `SELECT SCOPE_IDENTITY() AS insert_id`. O valor fica em cache e `insert_id()` o devolve. `affected_rows()` continua reportando o `INSERT`. Gatilho que também insere numa coluna identity segue a regra do `SCOPE_IDENTITY` daquele escopo.

**`num_rows()`.** Cursor client-buffered, static ou keyset usa `sqlsrv_num_rows()`. Forward e dynamic contam pela leitura, porque a API nativa não conta nesses cursores. O padrão segue `SQLSRV_CURSOR_CLIENT_BUFFERED`.

**`LIMIT`.** No caminho `OFFSET`/`FETCH`, ausência de `ORDER BY` usa `ORDER BY (SELECT NULL)`. O fallback `ROW_NUMBER()` para servidor anterior ao 2012 fica como está.

**Drivers extintos.** `dbdriver` `mysql` ou `mssql` produz uma mensagem explícita: a extensão saiu no PHP 7; usar `mysqli` ou `sqlsrv`. Os arquivos desses drivers permanecem. cubrid, ibase, oci8 e odbc ficam como estão.

**Pronto quando.** No SQL Server 2019, com `sqlsrv` e com `pdo_sqlsrv`: a conexão sobe com `TrustServerCertificate`; `insert_id()` devolve o identity; `LIMIT` sem `ORDER BY` executa; `num_rows()` no cursor padrão não materializa o result; PHP 7.4 e 8.5 verdes.

---

## Fase 2 — PostgreSQL e SQLite

### PostgreSQL

Arquivos: `system/database/drivers/postgre/postgre_driver.php`, `system/database/drivers/pdo/subdrivers/pdo_pgsql_driver.php`, `system/libraries/Session/drivers/Session_database_driver.php`.

- `sess_lock_wait` (segundos, default `0`). `0` mantém o `pg_advisory_lock` bloqueante de hoje. Valor positivo usa `pg_try_advisory_lock` em loop, com `sess_lock_retry_ms` (default `100`). Esgotou o tempo: `read()` falha, no mesmo caminho em que o MySQL falha quando `GET_LOCK` não vem. Não abre sessão vazia.
- `insert_id()` sem sequência na sessão devolve `0`, em vez de estourar erro com `db_debug`.

### SQLite

Arquivos: `system/database/drivers/sqlite3/sqlite3_driver.php`, `system/database/drivers/pdo/subdrivers/pdo_sqlite_driver.php`, o mesmo driver de sessão.

- `busy_timeout` (ms), default `0` (falha imediata, como hoje). Passado a `SQLite3::busyTimeout()` / `PDO::ATTR_TIMEOUT`. O `application/config/database.php` de exemplo comenta `5000` como valor prático.
- `wal` e `foreign_keys`, ambos default desligados. Ligados, executam `PRAGMA journal_mode=WAL` e `PRAGMA foreign_keys=ON` depois do connect.
- Sessão em tabela SQLite toma `flock` exclusivo num arquivo ao lado do `.sqlite` (`<db>.ci_session_<md5 do id>.lock`), com o mesmo `sess_lock_wait`. O lock não é `BEGIN IMMEDIATE` na conexão da aplicação: isso reservaria o arquivo inteiro e travaria os writes do request. SQLite é um nó só; flock cobre esse caso. SQL Server e PostgreSQL continuam com lock no servidor, válido para mais de um Apache.

**Pronto quando.** PostgreSQL: espera de lock configurável e `insert_id()` sem sequência devolve `0`. SQLite: `busy_timeout` honrado, WAL e foreign keys só quando ligados, duas escritas de sessão concorrentes não se perdem. Testes na matriz da Action (PostgreSQL e SQLite, PHP 7.4 e 8.5).

---

## Fase 3 — Performance e usabilidade

Arquivos: `system/libraries/Session/Session.php`, `system/libraries/Session/drivers/Session_database_driver.php`, `system/core/CodeIgniter.php`, `system/database/DB_driver.php`, `application/config/config.php` (comentário).

- `CI_Session::close()` chama `session_write_close()` e libera o lock do driver em uso (arquivo, MySQL, PostgreSQL, SQL Server, SQLite). O view roda dentro do método do controller, então gravação de sessão na view continua válida.
- `sess_auto_close`, default `FALSE`. Com `TRUE`, `CodeIgniter.php` chama `close()` depois do método do controller e antes de `_display()` e `post_system`. O lock deixa de cobrir o envio da resposta e o profiler. Gravação de sessão num hook `post_system` não persiste com a chave ligada; o comentário em `config.php` diz isso.
- Lock de sessão no SQL Server: `sp_getapplock` com `@LockOwner = 'Session'` e `@LockTimeout` derivado de `sess_lock_wait` (default `0`, espera, porque hoje não há lock). Liberação com `sp_releaseapplock`. O batch começa com `SET NOCOUNT ON`, que o driver já executa sem cursor scrollable. `is_write_type()` não muda: `EXEC` e `WITH` continuam podendo devolver result set.
- `save_queries_limit` (int, default `0` = sem limite). Acima de zero, `DB_driver` guarda só as últimas N queries e tempos. `last_query()` segue sendo a última. O profiler mostra a janela.

**CI.** Um job com `mcr.microsoft.com/mssql/server:2019-latest`, PHP 7.4 e 8.5, drivers `sqlsrv` e `pdo_sqlsrv`. Cobre conexão com `TrustServerCertificate`, `insert_id`, `LIMIT` sem `ORDER BY`, `num_rows` e duas requests concorrentes na mesma sessão. Os testes novos de PostgreSQL e SQLite entram na matriz atual, sem imagem nova e sem runner Windows.

**Pronto quando.** `close()` libera o lock; `sess_auto_close` default deixa o request igual ao de hoje; o job de SQL Server 2019 verde nos dois drivers; suíte existente verde em PHP 7.4 e 8.5.

---

## Fora do roadmap

Ficou de fora, e não volta como fase futura deste documento:

- Kernel reentrante, container, events, PSR-3, OpenTelemetry, health endpoint.
- Maybe ou qualquer dependência Composer nova.
- Pacote Packagist, skeleton, CLI de scaffolding, fila, scheduler, assets, Vite.
- Harness de benchmark, preload de opcache, autoload preguiçoso.
- Matriz Windows, FreeTDS, SQL Server 2022, MariaDB, PHPStan, Rector.
- Trocar o default de `save_queries`.
- Cursor forward como padrão do `sqlsrv`.
- Tratar `EXEC` e `WITH` como write.

Higiene de `readme.rst`, `E_STRICT` e `user_guide_src` também fica de fora: é documentação legada, não estes objetivos.

---

## Ordem

Fase 1, depois fase 2, depois fase 3. A fase 3 depende do lock de sessão da fase 2 no SQLite e no PostgreSQL, e acrescenta o lock do SQL Server. Cada fase só começa quando a anterior estiver verde.
