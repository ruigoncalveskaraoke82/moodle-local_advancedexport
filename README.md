# Advanced export of responses (local_advancedexport)

[![Moodle Plugin CI](https://github.com/ruigoncalveskaraoke82/moodle-local_advancedexport/actions/workflows/ci.yml/badge.svg)](https://github.com/ruigoncalveskaraoke82/moodle-local_advancedexport/actions/workflows/ci.yml)

Exports the responses of a **Questionnaire** activity (mod_questionnaire, required) organised by
respondent and by option, as Excel (.xlsx), LibreOffice (.ods), CSV or PDF.

*Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
(Centro de Formação do Ministério dos Negócios Estrangeiros).*

## Description

Questionnaire's own download writes every check box option as a separate 0/1 column. With a
question that offers dozens of courses or languages, the file is hard to read and has to be
rearranged by hand before anyone can use it. This plugin reads the same responses and produces
ready-to-use lists.

**Which responses it exports**

- Answers to option questions are used to organise the export: check boxes, radio buttons,
  dropdown lists and yes/no. You pick one or several of these questions.
- Answers to any other question (text, essay, numeric, date, rate, slider, and "other" free text)
  can be added as columns next to each person.
- Moodle profile fields (name, email, username, ID number, institution, department, phone, city,
  country), custom profile fields and the submission date can also be added as columns.
- Only submitted responses are included by default; saved but unsubmitted ones can be added, with a
  status column.

**Sheets you can choose**

- **Summary**: number and percentage of respondents for each option of each chosen question.
- **By respondent**: one row per person, with the options they chose (one per line) and the columns
  you picked.
- **By option**: the option cell merged vertically over one row per person who chose it. When several
  questions share the same options (for example "languages needed" and "languages wanted"), they are
  grouped together with a ✓ column per question.
- **One sheet per option**: a separate list for each option, handy as a class or group list.

**Other features**

- Excel and LibreOffice files have coloured headers, frozen header rows, filters on plain lists,
  column widths fitted to the content and print settings (landscape, fit to width).
- PDF output prints one titled table per option, so long groups split cleanly across pages.
- CSV output repeats the option on every row; several sheets come as one ZIP with a CSV per sheet.
- The form remembers each exporter's last choices for each activity.
- Anonymous questionnaires can only export the Summary sheet, with no names. This is enforced on the
  server.
- Read only: the plugin creates no tables and never changes Questionnaire data.

## Requirements

- Moodle 4.5 or later.
- [Questionnaire](https://moodle.org/plugins/mod_questionnaire) (mod_questionnaire) 4.4.0 or later.
  Both storage formats used by Questionnaire 4.4 for deleted questions are supported.
- Tested on PostgreSQL 16 and MariaDB, PHP 8.3.

## Installation

1. Site administration > Plugins > Install plugins, upload the ZIP file and follow the steps.
2. No settings are needed.

Manual installation: extract the folder to `local/advancedexport` and visit Site administration > Notifications.

## Usage

- In a Questionnaire activity: **More > Advanced export**.
- Central page listing every Questionnaire on the site: **Site administration > Reports >
  Advanced export of responses**.

## Permissions

Capability `local/advancedexport:export` (module context). Given to the Manager role by default;
site administrators always have it. Teachers and students do not.

## Privacy

The plugin only reads Questionnaire responses and user profile fields to build the file the exporter
downloads. It creates no tables. It stores one user preference per activity with the exporter's last
export choices (declared through the Privacy API).

- Anonymous questionnaires: only the Summary sheet can be exported, without any names. This is enforced
  on the server, not only in the form.
- Saved but unsubmitted responses are excluded unless the exporter explicitly includes them.

## License

GNU GPL v3 or later; see the LICENSE file. © 2026 Rui Pratas.
