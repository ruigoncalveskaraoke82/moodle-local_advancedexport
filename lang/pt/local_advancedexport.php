<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Portuguese strings for local_advancedexport.
 *
 * Developed by Rui Pratas for the Training Centre of the Portuguese Ministry of Foreign Affairs
 * (Centro de Formação do Ministério dos Negócios Estrangeiros).
 *
 * @package    local_advancedexport
 * @copyright  2026 Rui Pratas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['advancedexport:export'] = 'Usar a exportação avançada de respostas';
$string['anonymous'] = 'Anónimo';
$string['anonymouswarning'] = 'Este questionário é anónimo. Para proteger quem respondeu, só é possível exportar a folha Resumo (totais por opção), sem nomes.';
$string['col_count'] = 'Nº de respostas';
$string['col_option'] = 'Opção';
$string['col_percent'] = '% das respostas';
$string['col_pickedin'] = 'Escolheu em {$a}';
$string['col_question'] = 'Pergunta';
$string['col_responses'] = 'Respostas submetidas';
$string['col_status'] = 'Estado';
$string['col_submitted'] = 'Data de submissão';
$string['col_type'] = 'Tipo';
$string['columns_questions'] = 'Respostas a outras perguntas';
$string['columns_user'] = 'Perfil Moodle';
$string['credits'] = 'Desenvolvido por Rui Pratas para o Centro de Formação do Ministério dos Negócios Estrangeiros';
$string['error_nogroup'] = 'Escolhe pelo menos uma pergunta.';
$string['error_nosheet'] = 'Escolhe pelo menos uma folha.';
$string['export'] = 'Exportar';
$string['exporttitle'] = 'Exportação avançada: {$a}';
$string['format'] = 'Formato do ficheiro';
$string['format_csv'] = 'CSV (dados simples; várias folhas vêm num ZIP)';
$string['format_ods'] = 'LibreOffice (.ods)';
$string['format_pdf'] = 'PDF (para imprimir ou enviar)';
$string['format_xlsx'] = 'Excel (.xlsx)';
$string['identified'] = 'Com nomes';
$string['includeincomplete'] = 'Respostas não submetidas';
$string['includeincomplete_desc'] = 'Incluir respostas guardadas mas não submetidas ({$a} neste momento). É acrescentada uma coluna "Estado".';
$string['indexintro'] = 'Todos os questionários (Questionnaire) da plataforma. Escolhe um para exportar as respostas organizadas por formando e por opção.';
$string['intro'] = 'Respostas submetidas: <strong>{$a->complete}</strong>. Guardadas mas não submetidas: {$a->incomplete}.';
$string['menuitem'] = 'Exportação avançada';
$string['nogroupable'] = 'Este questionário não tem perguntas de opções (caixas de seleção, botões de opção, lista pendente ou sim/não).';
$string['noquestionnaires'] = 'Não foram encontrados questionários.';
$string['note_anonymous'] = 'Questionário anónimo: as pessoas não são identificadas.';
$string['note_count'] = 'Pessoas: {$a}';
$string['note_multiple'] = 'Cada pessoa pode escolher mais do que uma opção, por isso os totais podem ser superiores ao nº de respostas.';
$string['note_total'] = 'Total de respostas consideradas: {$a}';
$string['pluginname'] = 'Exportação Avançada de Respostas';
$string['privacy:metadata:preference:lastchoices'] = 'As últimas opções de exportação usadas em cada atividade, para o formulário abrir já preenchido.';
$string['questionprefix'] = 'P{$a}';
$string['searchplaceholder'] = 'Procurar por disciplina ou questionário';
$string['section_columns'] = 'Informação do formando';
$string['section_columns_help'] = 'Colunas que aparecem ao lado de cada pessoa, por esta ordem.';
$string['section_format'] = 'Formato';
$string['section_group'] = 'Perguntas pelas quais organizar';
$string['section_group_help'] = 'As opções destas perguntas passam a ser os grupos da exportação (por exemplo, as línguas ou os cursos escolhidos). Com mais do que uma pergunta, uma coluna ✓ indica onde cada opção foi escolhida.';
$string['section_sheets'] = 'Folhas';
$string['selectallnone'] = 'Selecionar todas / nenhuma';
$string['sheet_byoption'] = 'Por opção';
$string['sheet_byoption_desc'] = '<strong>Por opção</strong>: a célula da opção unida na vertical, uma linha por formando';
$string['sheet_byuser'] = 'Por formando';
$string['sheet_byuser_desc'] = '<strong>Por formando</strong>: uma linha por pessoa, com as opções escolhidas';
$string['sheet_default'] = 'Folha';
$string['sheet_peroption_desc'] = '<strong>Uma folha por opção</strong>: uma lista separada para cada opção (útil para listas de turma)';
$string['sheet_summary'] = 'Resumo';
$string['sheet_summary_desc'] = '<strong>Resumo</strong>: número de pessoas por opção';
$string['status_complete'] = 'Submetida';
$string['status_incomplete'] = 'Não submetida';
