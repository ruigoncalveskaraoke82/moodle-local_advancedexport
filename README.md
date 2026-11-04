# Advanced export of responses (local_advancedexport)

Exports the responses of a **Questionnaire** activity organised by respondent and by option,
as Excel, LibreOffice, CSV or PDF, with the option cell merged vertically over the people who chose it.

*Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
(Centro de Formação do Ministério dos Negócios Estrangeiros).*

## Description

Questionnaire's own download puts every check box option in its own 0/1 column, which is hard to read
when a question offers dozens of courses or languages. This plugin turns the same responses into
ready-to-use lists:

- **Summary**: number and percentage of respondents per option, for every chosen question.
- **By respondent**: one row per person, with the options they chose (one per line) and any other
  columns you pick.
- **By option**: the option cell merged vertically, one row per person who chose it. When several
  questions share the same options (for example "languages needed" and "languages wanted"), they are
  grouped together with a ✓ column per question.
- **One sheet per option**: a separate list for each option, handy as a class list.

You choose which questions to group by, which columns to show (Moodle profile fields, custom profile
fields, answers to other questions, submission date) and which sheets to include. The form remembers
the last choices for each activity.

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

GNU GPL v3 or later. © 2026 Rui Pratas.
