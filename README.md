History Log (module for Omeka S)
================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[History Log] is an [Omeka S] module that logs creation, updates, deletions,
imports and exports of Omeka S items, item sets and medias and allows
administrators to recall this information later.

This is not a replacement for your regular backups of the database, even if each
change is logged and each resource can potentially be partially recovered at any
time, **except media files**. An undo button allows to recover the deletion of
a resource.

This is a full rewrite of the [Omeka Classic] [plugin History Log].

**Work in progress**

This is a work in progress: only some features are available for now.
Currently, only the main entries are stored, that is the base of the
module.

TODO:

- [ ] Initial filling
- [ ] Checks
- [ ] Reset (restore at any event)
- [ ] Undelete base
- [ ] Undelete full (with value annotations)
- [ ] Undelete data from modules (mainly Mapping in fact)
- [ ] Batch process
- [ ] Export
- [ ] Direct routes
- [ ] Sites and site pages
- [ ] Other modules (mainly Annotate)

FIXES:

- [ ] Fix loop when removing more than 10 medias

**This readme lists features not yet implemented in Omeka S.**


Installation
------------

See general end user documentation for [installing a module].

This module requires the module [Common], that should be installed first.

* From the zip

Download the last release [HistoryLog.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `HistoryLog`.


Then install it like any other Omeka module and follow the config instructions.


Usage
-----

### Logging

- If the module has not been installed with Omeka S, older resources won't have
  log entries and their stats will be partial.
- Logging is done via standard events. If a modules bypasses the standard
  methods, some logs may be missing.

### Recovering of a deleted resource

- Resources are recreated with the same id.
- It's recommended to undelete item sets before their items.
- Media files cannot be recovered, but their metadata are logged, so they can be
  manually recreated as long as files are backuped.
- Non standard metadata are not saved and can't be recreated..
- Check logs after a successful rebuild to see possible issues.

### Export of logs

Logs can be filtered and exported via the main page of the module. Supported
formats are:

- CSV, with values separated by a tabulation.
- [OpenDocument Spreadsheet] or "ods", the normalized format for
  spreadsheets, that  can be open by any free spreadsheets like [LibreOffice],
  or not free ones. This format requires that Zip to be installed on the server
  (generally by default).
- [Flat OpenDocument Spreadsheet] or "fods", another standard format that can
  be opened by any free spreadsheets or by any text editor (this is a simple xml
  file). Note: With old releases of [LibreOffice] for Windows, a little free
  [filter] may need to be installed.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright 2014-2015 UCSC Library Digital Initiatives
* Copyright Daniel Berthereau, 2015-2024 (see [Daniel-KM] on [GitLab])


[History Log]: https://gitlab.com/Daniel-KM/Omeka-S-module-HistoryLog
[Omeka S]: https://omeka.org/s
[Omeka Classic]: https://omeka.org/classic
[plugin History Log]: https://github.com/UCSCLibrary/HistoryLog
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[HistoryLog.zip]: https://github.com/Daniel-KM/Omeka-S-module-HistoryLog/releases
[installing a module]: https://omeka.org/s/docs/user-manual/modules/
[OpenDocument Spreadsheet]: http://opendocumentformat.org/
[LibreOffice]: https://www.libreoffice.org/
[Flat OpenDocument Spreadsheet]: https://en.wikipedia.org/wiki/OpenDocument_technical_specification
[filter]: http://www.sylphide-consulting.com/shapekit/spreadsheet-generation/15-opendocument-flat-format
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-EasyAdmin/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
