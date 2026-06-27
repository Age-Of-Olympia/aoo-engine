<?php

namespace App\View\Action;

use App\Action\Schema\ActionSchemaCatalog;
use App\Entity\ActionTypeInstruction;

/**
 * The "type defaults" editor body: a type picker plus the editable instructions
 * configured on the selected action type (the data-driven home of what used to
 * be code automatics, e.g. an attack's adrenaline). The instructions form is the
 * shared {@see TypeChildFormView} (same as the preconditions editor); this wraps
 * it in the type rail + tabs layout and slots in the injected XP/Journal tabs.
 */
final class TypeDefaultsView
{
    private ActionSchemaCatalog $catalog;
    private TypeChildFormView $form;
    private WorkbenchListHeaderView $listHeader;

    public function __construct(
        ?ActionSchemaCatalog $catalog = null,
        ?TypeChildFormView $form = null,
        ?WorkbenchListHeaderView $listHeader = null,
    ) {
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
        $this->form = $form ?? new TypeChildFormView();
        $this->listHeader = $listHeader ?? new WorkbenchListHeaderView();
    }

    /**
     * @param string                            $treeRail         pre-rendered ActionTypeTreeView rail
     * @param array<int, ActionTypeInstruction> $instructions
     * @param array<int, string>                $instructionTypes available instruction types
     * @param array<int, array{label: string, html: string}> $extraTabs additional tabs (XP, Journal, Préconditions) shown beside Instructions
     */
    public function render(
        string $selectedType,
        string $treeRail,
        array $instructions,
        array $instructionTypes,
        string $csrfTokenField,
        array $extraTabs = [],
    ): string {
        $rows = [];
        foreach ($instructions as $instruction) {
            $id = (int) $instruction->getId();
            $rows[] = [
                'id' => $id,
                'childType' => $instruction->getInstructionType(),
                'schema' => $this->catalog->schemaForOutcomeInstruction($instruction->getInstructionType()),
                'params' => $instruction->getParameters() ?? [],
                'typedPrefix' => 'type_inst[' . $id . ']',
                'rawPrefix' => 'type_inst_raw[' . $id . ']',
                'headExtra' => '',
            ];
        }

        $form = $this->form->render(
            saveAction: '/admin/action-type-save.php',
            addAction: '/admin/action-type-instruction-add.php',
            removeAction: '/admin/action-type-instruction-remove.php',
            csrfTokenField: $csrfTokenField,
            sectionTitle: 'Instructions du type « ' . $selectedType . ' »',
            childSelectName: 'instruction_type',
            childOptions: $instructionTypes,
            addLabel: '+ Instruction',
            removeIdName: 'instruction_id',
            removeConfirm: 'Retirer cette instruction du type ?',
            rows: $rows,
            emptyLabel: 'Aucune instruction pour ce type.',
            hidden: ['type_key' => $selectedType],
        );

        // Instructions + the injected XP/Journal/Préconditions sections become tabs
        // so the wide column shows one config area at a time.
        $tabs = array_merge([['label' => 'Instructions', 'html' => $form]], array_values($extraTabs));

        // The export lives at the top of the left column (a wb-list-header, same
        // component the action/passive workbenches use) instead of floating above
        // the layout.
        $listHeader = $this->listHeader->render('', 'action-type', 'Exporter les défauts par type');

        return '<div class="wb"><div class="wb-col"><div class="wb-col-head">Types d\'action</div>'
            . '<div class="wb-col-body">' . $listHeader . $treeRail . '</div></div>'
            . '<div class="wb-col wb-col--wide"><div class="wb-col-body">' . $this->tabs($tabs) . '</div></div></div>';
    }

    /**
     * A tab bar + panels; the first tab is active. Switching is handled client-side
     * (admin/js/action-type-defaults.js), which also remembers the last tab across
     * the save → redirect.
     *
     * @param array<int, array{label: string, html: string}> $tabs
     */
    private function tabs(array $tabs): string
    {
        $bar = '';
        $panels = '';
        foreach ($tabs as $index => $tab) {
            $active = $index === 0;
            $bar .= '<button type="button" class="wb-tab' . ($active ? ' wb-tab--active' : '') . '" data-tab="' . $index . '">'
                . $this->esc($tab['label']) . '</button>';
            $panels .= '<div class="wb-tabpanel" data-tab="' . $index . '"' . ($active ? '' : ' hidden') . '>' . $tab['html'] . '</div>';
        }

        return '<div class="wb-tabs" role="tablist">' . $bar . '</div>' . $panels;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
