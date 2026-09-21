<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Subject;
use App\Models\SubjectGroupScore;
use Database\Seeders\GroupSeeder;
use Database\Seeders\SubjectGroupScoreSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fənnin qrupdakı maksimal balı bal hesablamasının əsasıdır — səhv dəyər bütün nəticələri
 * pozar. Bu test matrisi sənəddəki rəqəmlərlə tam tutuşdurur.
 *
 * Xüsusi diqqət: DİM-də "Tarix" BİR fəndir (II qrupda 100, III qrupda 150).
 */
class SubjectGroupScoreMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Sənəddəki matris: qrup kodu → [fənn slug => maksimal bal] */
    private const EXPECTED = [
        'I' => [
            'riyaziyyat' => '150.00',
            'fizika' => '150.00',
            'kimya' => '100.00',
            'informatika' => '100.00',
        ],
        'II' => [
            'riyaziyyat' => '150.00',
            'tarix' => '100.00',
            'cografiya' => '150.00',
        ],
        'III' => [
            'azerbaycan-dili' => '150.00',
            'edebiyyat' => '100.00',
            'cografiya' => '100.00',
            'tarix' => '150.00',
        ],
        'IV' => [
            'fizika' => '100.00',
            'kimya' => '150.00',
            'biologiya' => '150.00',
        ],
        'I-MERHELE' => [
            'azerbaycan-dili' => '100.00',
            'riyaziyyat' => '100.00',
            'ingilis-dili' => '100.00',
            'rus-dili' => '100.00',
            'fransiz-dili' => '100.00',
            'alman-dili' => '100.00',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubjectSeeder::class);
        $this->seed(GroupSeeder::class);
        $this->seed(SubjectGroupScoreSeeder::class);
    }

    /** @return array<string, string>  fənn slug → maksimal bal */
    private function matrixFor(string $groupCode): array
    {
        $groupId = Group::where('code', $groupCode)->value('id');

        return SubjectGroupScore::where('group_id', $groupId)
            ->join('subjects', 'subjects.id', '=', 'subject_group_scores.subject_id')
            ->pluck('subject_group_scores.max_score', 'subjects.slug')
            ->all();
    }

    /**
     * @dataProvider groupCodes
     */
    public function test_the_matrix_matches_the_specification(string $groupCode): void
    {
        $expected = self::EXPECTED[$groupCode];
        $actual = $this->matrixFor($groupCode);

        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual, "{$groupCode} qrupunun bal matrisi uyğun gəlmir");
    }

    public static function groupCodes(): array
    {
        return [
            'I qrup' => ['I'],
            'II qrup' => ['II'],
            'III qrup' => ['III'],
            'IV qrup' => ['IV'],
            'I mərhələ' => ['I-MERHELE'],
        ];
    }

    /** DİM-də "Tarix" bir fəndir: II qrupda 100, III qrupda 150. */
    public function test_history_is_a_single_subject_with_the_right_scores(): void
    {
        $this->assertSame(
            1,
            Subject::where('slug', 'tarix')->count(),
            '"Tarix" bir fənn olmalıdır'
        );

        $this->assertSame(
            0,
            Subject::whereIn('slug', ['azerbaycan-tarixi', 'umumi-tarix'])->count(),
            'Köhnə iki tarix fənni qalmamalıdır — onlar mövzu qruplarıdır'
        );

        $history = Subject::where('slug', 'tarix')->firstOrFail();

        $scores = SubjectGroupScore::where('subject_id', $history->id)
            ->join('groups', 'groups.id', '=', 'subject_group_scores.group_id')
            ->pluck('subject_group_scores.max_score', 'groups.code')
            ->all();

        $this->assertSame(['II' => '100.00', 'III' => '150.00'], $scores);
    }

    /** Ballar baş qrupda saxlanılır: altqrupların öz sətri olmur. */
    public function test_subgroups_have_no_scores_of_their_own(): void
    {
        foreach (['I-RK', 'I-RI', 'III-DT', 'III-TC'] as $code) {
            $this->assertSame([], $this->matrixFor($code), "{$code} altqrupunun öz balı olmamalıdır");
        }
    }

    /** V qrup qabiliyyət qrupudur: testi və balı yoxdur. */
    public function test_the_aptitude_group_has_no_scores(): void
    {
        $this->assertSame([], $this->matrixFor('V'));
        $this->assertFalse(Group::where('code', 'V')->value('is_testable'));
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $before = SubjectGroupScore::count();

        $this->seed(SubjectGroupScoreSeeder::class);

        $this->assertSame($before, SubjectGroupScore::count());

        $expected = self::EXPECTED['III'];
        $actual = $this->matrixFor('III');
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }
}
