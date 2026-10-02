##################
Majoris User Guide
##################

Source of the user guide shipped with Majoris. The guide documents the
CodeIgniter 3 compatible API that this repository keeps. It is not the
upstream CodeIgniter 3 manual and it is not the CodeIgniter 4 manual.

******************
Setup Instructions
******************

The guide uses Sphinx. Pages are written in
`ReStructured Text <http://sphinx.pocoo.org/rest.html>`_.

Prerequisites
=============

Sphinx requires Python 2.7.  If you are on OS X, then you already have Python.
You can confirm in a Terminal window by executing the ``python`` command
without any parameters.  It should load up and tell you which version you have
installed.

Note: If you're not on Python 2.7, then you must upgrade. E.g. Install 2.7.2
from https://python.org/download/releases/2.7.2/

Installation
============

1. Install `easy_install <http://peak.telecommunity.com/DevCenter/EasyInstall#installing-easy-install>`_
2. ``easy_install "sphinx==1.6.3"``
3. ``easy_install "sphinxcontrib-phpdomain==0.1.3.post1"``
4. Install the CI Lexer which allows PHP, HTML, CSS, and JavaScript syntax highlighting in code examples (see *cilexer/README*)
5. ``cd user_guide_src``
6. ``make html``

Editing and Creating Documentation
==================================

All of the source files exist under *source/* and is where you will add new
documentation or modify existing documentation. Work from a feature branch
and open the pull request against ``main`` of
https://github.com/gabrielalmir/majoris.

Class names, helpers and config keys stay the CodeIgniter 3 names. Do not
rename them in the reference pages. Identity of this repository belongs in
the root ``README.md`` and in ``MAJORIS.md``.

So where's the HTML?
====================

The built HTML is not under source control. From ``user_guide_src``::

	make html

The rendered guide is written to *build/html/*. Delete that directory to
force a full rebuild.

***************
Style Guideline
***************

Please refer to source/documentation/index.rst for general guidelines for
using Sphinx to document this guide.
