#######################
Contributing to Majoris
#######################

.. toctree::
	:titlesonly:

	../documentation/index
	../DCO

Majoris accepts issues and pull requests on
`gabrielalmir/majoris <https://github.com/gabrielalmir/majoris>`_.
That is not the upstream CodeIgniter repository.

Read ``MAJORIS.md`` before sending a change. The scope is the CI3 legacy
model, SQL Server 2019, PostgreSQL, SQLite, and the performance and
usability work those three engines need. A new kernel, queue, CLI,
asset pipeline or ``src/`` tree is out of scope.

One pull request, one change. The integration branch is ``main``.
There is no ``develop`` branch and this repository does not use Git-Flow.

Commits use Conventional Commits: ``feat``, ``fix``, ``docs``, ``chore``,
``test``, ``refactor``.

PHP 7.4 is the floor. Code must stay valid through 8.5. Do not add union
types, ``mixed``, ``match``, ``?->``, enums, ``readonly``, named arguments
or functions added in PHP 8. An existing method does not gain a type.
Copy the style of the file you are editing: tabs, Allman braces,
``TRUE`` / ``FALSE`` / ``NULL``, snake_case.

Before opening the pull request::

	composer install
	composer check

``composer check`` runs PHP_CodeSniffer, PHPStan and PHPUnit.

The root ``contributing.md`` is the same guide in Portuguese.

*******
Support
*******

GitHub issues are for defects and changes in this repository. They are
not a support channel for the upstream CodeIgniter forums.

********
Security
********

Do not disclose a vulnerability in a public issue. Use a private security
advisory on this GitHub repository. ``security@codeigniter.com`` and the
CodeIgniter HackerOne program belong to the upstream project.

****************************
Tips for a Good Issue Report
****************************

Use a descriptive subject line (eg parser library chokes on commas) rather than a vague one (eg. your code broke).

Address a single issue in a report.

Identify the component if you know it (eg. parser library) and the PHP version.

Explain what you expected to happen, and what did happen.
Include error messages and stacktrace, if any.

Include short code segments if they help to explain.
Use a pastebin or dropbox facility to include longer segments of code or screenshots - do not include them in the issue report itself.
This means setting a reasonable expiry for those, until the issue is resolved or closed.

If you know how to fix the issue, you can do so in your own fork & branch, and submit a pull request.
The issue report information above should be part of that.

If your issue report can describe the steps to reproduce the problem, that is great.
If you can include a unit test that reproduces the problem, that is even better, as it gives whoever is fixing
it a clearer target.

*******
License
*******

The code is MIT. See ``license.txt``. The Developer's Certificate of
Origin text is in :doc:`/DCO`.
