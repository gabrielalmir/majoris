##################
Majoris User Guide
##################

- :doc:`License Agreement <license>`
- :doc:`Change Log <changelog>`

.. contents::
   :local:
   :depth: 2

*******
Majoris
*******

Majoris is a drop-in base of CodeIgniter 3 for applications that stay on the
CI3 model. It is not the upstream CodeIgniter 3 project and it is not
CodeIgniter 4.

Replace the application ``system/`` directory, or point ``$system_path`` in
``index.php`` at this tree, and keep the existing ``application/``. Public
``CI_*`` signatures, helpers, existing config keys and the hook order do not
change. ``system/core/CodeIgniter.php`` remains the bootstrap.

PHP 7.4 through 8.5. The reference pages below still use the CodeIgniter 3
names, because that is the API the application already calls.

Scope and status are in the repository file ``MAJORIS.md``. The upstream
CodeIgniter 3 manual remains at
`codeigniter.com/userguide3 <https://codeigniter.com/userguide3/>`_ and is not
this project.

*******
Welcome
*******

.. toctree::
	:titlesonly:

	general/welcome

**********
Basic Info
**********

- :doc:`general/requirements`
- :doc:`general/credits`

************
Installation
************
.. toctree::
	:includehidden:
	:maxdepth: 2
	:titlesonly:

	installation/index

************
Introduction
************

.. toctree::
	:titlesonly:

	overview/index

********
Tutorial
********

.. toctree::
	:includehidden:
	:titlesonly:

	tutorial/index

***********************
Contributing to Majoris
***********************

.. toctree::
	:glob:
	:titlesonly:

	contributing/index

**************
General Topics
**************

.. toctree::
	:glob:
	:titlesonly:

	general/index

*****************
Library Reference
*****************

.. toctree::
	:glob:
	:titlesonly:

	libraries/index

******************
Database Reference
******************

.. toctree::
	:glob:
	:titlesonly:

	database/index

****************
Helper Reference
****************

.. toctree::
	:glob:
	:titlesonly:

	helpers/index

.. toctree::
	:glob:
	:titlesonly:
	:hidden:

	*
	overview/index
	general/requirements
	general/welcome
	installation/index
	general/index
	libraries/index
	database/index
	helpers/index
	tutorial/index
	general/credits
