<?php
/**
 * Tests for ProjectExtraDTO.
 *
 * PHP version 8.3
 *
 * @category Tests
 * @package  Tests\DTO
 * @author   Epicollect5 <info@epicollect.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://five.epicollect.net
 */

namespace Tests\DTO;

use ec5\DTO\ProjectExtraDTO;
use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Services\Project\ProjectExtraService;
use Tests\TestCase;

/**
 * ProjectExtraDTO test cases.
 *
 * @category Tests
 * @package  Tests\DTO
 * @author   Epicollect5 <info@epicollect.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://five.epicollect.net
 */
class ProjectExtraDTOTest extends TestCase
{
    /**
     * Test possible-answer refs follow form list order.
     *
     * @return void
     */
    public function testGetPossibleAnswerRefsUsesFormListOrder(): void
    {
        $projectExtra = new ProjectExtraDTO();
        $projectExtra->init(
            [
                'forms' => [
                    'form_1' => [
                        'lists' => [
                            'multiple_choice_inputs' => [
                                'form' => [
                                    'order' => ['radio_1', 'checkbox_1'],
                                    'checkbox_1' => [
                                        'possible_answers' => [
                                            'pa_c' => 'C',
                                            'pa_d' => 'D',
                                        ],
                                    ],
                                    'radio_1' => [
                                        'possible_answers' => [
                                            'pa_a' => 'A',
                                            'pa_b' => 'B',
                                        ],
                                    ],
                                ],
                                'branch' => [],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertSame(
            ['pa_a', 'pa_b', 'pa_c', 'pa_d'],
            $projectExtra->getPossibleAnswerRefs('form_1')
        );
    }

    /**
     * Test possible-answer refs are scoped for flat branch lists.
     *
     * @return void
     */
    public function testGetPossibleAnswerRefsScopesFlatBranchList(): void
    {
        $projectExtra = new ProjectExtraDTO();
        $projectExtra->init(
            [
                'forms' => [
                    'form_1' => [
                        'branch' => [
                            'branch_1' => ['radio_1', 'group_1'],
                            'branch_2' => ['radio_2'],
                        ],
                        'group' => [
                            'group_1' => ['checkbox_1'],
                        ],
                        'lists' => [
                            'multiple_choice_inputs' => [
                                'form' => [],
                                'branch' => [
                                    'order' => ['radio_2', 'checkbox_1', 'radio_1'],
                                    'radio_1' => [
                                        'possible_answers' => [
                                            'pa_a' => 'A',
                                        ],
                                    ],
                                    'radio_2' => [
                                        'possible_answers' => [
                                            'pa_b' => 'B',
                                        ],
                                    ],
                                    'checkbox_1' => [
                                        'possible_answers' => [
                                            'pa_c' => 'C',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertSame(
            ['pa_c', 'pa_a'],
            $projectExtra->getPossibleAnswerRefs('form_1', 'branch_1')
        );
    }

    /**
     * Test generated definitions map all possible-answer refs.
     *
     * @return void
     */
    public function testGeneratedProjectPossibleAnswerRefsMapDefinitionRefs(): void
    {
        $projectDefinition = ProjectDefinitionGenerator::createProject();
        $projectExtraService = new ProjectExtraService();
        $projectExtraData = $projectExtraService->generateExtraStructure(
            $projectDefinition['data']
        );

        $projectExtra = new ProjectExtraDTO();
        $projectExtra->init($projectExtraData);

        $multipleChoiceQuestionTypes = array_keys(
            config('epicollect.strings.multiple_choice_question_types')
        );

        $collectInputPossibleAnswerRefs = function (
            array $inputs
        ) use (
            &$collectInputPossibleAnswerRefs,
            $multipleChoiceQuestionTypes
        ): array {
            $possibleAnswerRefs = [];
            $groupType = config('epicollect.strings.inputs_type.group');

            foreach ($inputs as $input) {
                if (in_array($input['type'], $multipleChoiceQuestionTypes, true)) {
                    foreach ($input['possible_answers'] as $possibleAnswer) {
                        $possibleAnswerRefs[] = $possibleAnswer['answer_ref'];
                    }
                }

                if ($input['type'] === $groupType) {
                    $possibleAnswerRefs = array_merge(
                        $possibleAnswerRefs,
                        $collectInputPossibleAnswerRefs($input['group'])
                    );
                }
            }

            return array_values(array_unique($possibleAnswerRefs));
        };

        $branchType = config('epicollect.strings.inputs_type.branch');

        foreach ($projectDefinition['data']['project']['forms'] as $form) {
            $expectedFormRefs = $collectInputPossibleAnswerRefs($form['inputs']);

            $this->assertSame(
                $expectedFormRefs,
                $projectExtra->getPossibleAnswerRefs($form['ref'])
            );

            foreach ($form['inputs'] as $input) {
                if ($input['type'] !== $branchType) {
                    continue;
                }

                $expectedBranchRefs = $collectInputPossibleAnswerRefs(
                    $input['branch']
                );

                $this->assertSame(
                    $expectedBranchRefs,
                    $projectExtra->getPossibleAnswerRefs($form['ref'], $input['ref'])
                );
            }
        }
    }
}
