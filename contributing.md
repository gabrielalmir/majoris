# Contribuir com o Majoris

O Majoris é a base drop-in do CodeIgniter 3 mantida neste repositório. Issue e pull request entram em [gabrielalmir/majoris](https://github.com/gabrielalmir/majoris), não no upstream do CodeIgniter.

Antes de abrir uma issue:

1. Não há issue aberta para o mesmo problema.
2. O `main` ainda não corrige.
3. O relato diz o que era esperado, o que aconteceu e como repetir.

Pergunta de uso do modelo CI3 não é issue deste repositório. Defeito ou mudança daqui é.

## O que entra

O escopo está em [`MAJORIS.md`](MAJORIS.md): compatibilidade com o legado CI3, SQL Server 2019, PostgreSQL, SQLite, e a performance e a usabilidade desse caminho. Kernel novo, Maybe, fila, CLI, assets e `src/` ficam de fora.

Uma pull request, uma mudança. Vários commits podem compor essa mudança. Duas mudanças independentes são duas pull requests.

## Código

- PHP 7.4 é o piso. O código precisa seguir válido até 8.5.
- Sem union types, `mixed`, `match`, `?->`, enums, `readonly`, named arguments, `str_contains` e o resto que nasceu no PHP 8.
- Sem `declare(strict_types=1)` em `system/`.
- Método já existente não ganha tipo. Método novo pode ter tipo escalar.
- Um arquivo, um tipo. O nome do arquivo é o que o loader do CI já espera.
- Estilo do arquivo vizinho: tab, chave Allman, `TRUE`/`FALSE`/`NULL`, aspas simples, `elseif`, snake_case.
- Não reformatar o que a mudança não toca.
- Falha de driver entra na mensagem. Config inválida falha. Lock de sessão negado não abre sessão vazia.

A norma completa está em [`CLAUDE.md`](CLAUDE.md). [`AGENTS.md`](AGENTS.md) repete o bloco inegociável.

## Documentação

Mudança de comportamento, chave de config ou método novo atualiza a documentação que o descreve. O guia em `user_guide_src/` documenta a API compatível com o CI3. A identidade do projeto é o [`README.md`](README.md) e o [`MAJORIS.md`](MAJORIS.md).

## Teste

```bash
composer install
vendor/bin/phpcbf <arquivos da fatia>
composer check
```

`composer check` é PHP_CodeSniffer, PHPStan e PHPUnit em `tests/travis/sqlite.phpunit.xml`. Teste de um motor dá `markTestSkipped` quando `DB_DRIVER` não é o dele.

## Branch

A branch de integração é `main`. Não há `develop` nem Git-Flow neste repositório.

1. Fork de [gabrielalmir/majoris](https://github.com/gabrielalmir/majoris).
2. Branch a partir de `main`.
3. Commit no padrão Conventional Commits: `feat`, `fix`, `docs`, `chore`, `test`, `refactor`.
4. Push da branch e pull request para `main`.

## Segurança

Não publique vulnerabilidade em issue aberta. Use o aviso privado de segurança do GitHub neste repositório. O painel `security@codeigniter.com` e o HackerOne do CodeIgniter são do upstream, não deste projeto.

## Licença

O código é MIT. Veja [`license.txt`](license.txt). O texto do Developer's Certificate of Origin está em [`DCO.txt`](DCO.txt).
