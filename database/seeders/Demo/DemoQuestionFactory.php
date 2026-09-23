<?php

namespace Database\Seeders\Demo;

use App\Models\Question;

/**
 * Demo sualları qurur. Nəticə TAM DETERMİNİSTİKDİR — eyni giriş həmişə eyni sualı verir,
 * ona görə seeder təkrar işlədiləndə `source` açarı ilə üstünə yazılır, dublikat yaranmır.
 *
 * Suallar `DemoTaxonomy`-dəki mövzu və anlayışlardan qurulur, yəni CAVABLARI DOĞRUDUR:
 * "hansı anlayış bu mövzuya aiddir" tipli sual taksonomiyanın özündən yoxlanılır.
 * Riyaziyyat/fizika/kimya/informatika/məntiq üçün əlavə olaraq hesablanmış KaTeX sualları
 * qurulur (düstur `$...$` içində yazılır, `MathText.vue` onu render edir).
 *
 * Sual mətnində "demo" sözü YOXDUR: şagird tərəfdə nümunə məzmun real məzmundan seçilməməlidir
 * (kart, imtahan səhifəsi, sual mətni — hamısı təmiz olur). Ayırd etmək üçün `questions.is_demo`
 * bayrağı var: admin panel onu nişanla göstərir, `php artisan demo:clear` isə ona görə silir.
 */
class DemoQuestionFactory
{
    /** Hesablanmış düsturlu sual qurula bilən fənlər */
    private const MATH_SUBJECTS = ['riyaziyyat', 'fizika', 'kimya', 'informatika', 'mentiq'];

    /** Şəkilli sual qurulan fənn (sürücülük nəzəri imtahanı) */
    private const ROAD_SUBJECT = 'yol-hereketi-qaydalari';

    /** Hər çərçivənin qurduğu sual tipi — icazəli tiplərə görə süzmək üçün */
    private const FRAME_TYPES = [
        'choice5' => Question::TYPE_MULTIPLE_CHOICE,
        'choice4' => Question::TYPE_MULTIPLE_CHOICE,
        'negative' => Question::TYPE_MULTIPLE_CHOICE,
        'passage' => Question::TYPE_MULTIPLE_CHOICE,
        'passageLong' => Question::TYPE_MULTIPLE_CHOICE,
        'formula' => Question::TYPE_MULTIPLE_CHOICE,
        'coded' => Question::TYPE_OPEN_CODED,
        'formulaCoded' => Question::TYPE_OPEN_CODED,
        'written' => Question::TYPE_OPEN_WRITTEN,
        'writtenLong' => Question::TYPE_OPEN_WRITTEN,
        'pairing' => Question::TYPE_MULTIPLE_CHOICE,
        'quarterClosed' => Question::TYPE_MULTIPLE_CHOICE,
        'passageAlt' => Question::TYPE_MULTIPLE_CHOICE,
    ];

    private const DIFFICULTIES = [
        Question::DIFFICULTY_EASY,
        Question::DIFFICULTY_MEDIUM,
        Question::DIFFICULTY_HARD,
    ];

    /**
     * Bir fənnin bir dildəki bütün demo sualları.
     *
     * @param  array<int, array{name: string, terms: array<int, string>, terms_ru?: array<int, string>}>  $topics
     * @param  int  $perTopic  hər mövzu (rüb) üçün sual sayı
     * @return array<int, array<string, mixed>>
     */
    public function build(
        string $subjectSlug,
        array $topics,
        string $language,
        int $perTopic,
        array $allowedTypes = Question::TYPES,
    ): array {
        $rows = [];

        foreach ($topics as $topicIndex => $topic) {
            for ($n = 0; $n < $perTopic; $n++) {
                $rows[] = $this->question($subjectSlug, $topics, $topicIndex, $language, $n, $allowedTypes) + [
                    'source' => sprintf('DEMO:%s:%s:q%d:%02d', $subjectSlug, $language, $topicIndex + 1, $n + 1),
                    'topic_index' => $topicIndex,
                    'difficulty' => self::DIFFICULTIES[($topicIndex + $n) % 3],
                ];
            }
        }

        return $rows;
    }

    /**
     * Sual çərçivələri növbə ilə dəyişir: qapalı (4 və 5 variantlı), açıq kodlaşdırılan,
     * açıq yazılı, uzun mətnli. Hər mövzuda eyni sıra təkrarlanır, ona görə hər imtahanda
     * növ qarışığı təbii alınır.
     *
     * @param  array<int, array<string, mixed>>  $topics
     * @return array<string, mixed>
     */
    private function question(string $subjectSlug, array $topics, int $topicIndex, string $language, int $n, array $allowedTypes): array
    {
        // Sürücülük nəzəri imtahanı şəkilli testdir: nişan və yolayrıcı sxemləri
        if ($subjectSlug === self::ROAD_SUBJECT && $topicIndex < 3) {
            return $this->roadSign($topicIndex, $n);
        }

        $isMath = in_array($subjectSlug, self::MATH_SUBJECTS, true);
        /*
         * İlk səkkiz çərçivə hər üç tipi qarışdırır. Sondakı üç QAPALI çərçivə yalnız
         * açıq sualın icazəli olmadığı imtahanlarda (sürücülük, MİQ …) işə düşür: orada
         * süzgəcdən sonra yenə səkkiz FƏRQLİ çərçivə qalır, sual mətni təkrarlanmır.
         */
        $frames = $isMath
            ? ['choice5', 'choice4', 'coded', 'written', 'passage', 'negative', 'formula', 'formulaCoded',
                'pairing', 'quarterClosed', 'passageAlt']
            : ['choice5', 'choice4', 'coded', 'written', 'passage', 'negative', 'passageLong', 'writtenLong',
                'pairing', 'quarterClosed', 'passageAlt'];

        // İmtahan növündə icazəsiz tipə aid çərçivələr siyahıdan çıxır
        $frames = array_values(array_filter(
            $frames,
            fn (string $frame) => in_array(self::FRAME_TYPES[$frame], $allowedTypes, true),
        ));

        $frame = $frames[$n % count($frames)];

        return $this->{$frame}($subjectSlug, $topics, $topicIndex, $language, $n);
    }

    /**
     * Şəkilli sürücülük sualı.
     *
     * Rüb 1 — nişanın mənası, rüb 2 — yolayrıcı sxemi (kim birinci keçir), rüb 3 — nişanın
     * hansı qrupa aid olması. Şəklin üzərində nişanın adı YAZILMIR, ona görə sual öz
     * cavabını vermir.
     *
     * @return array<string, mixed>
     */
    private function roadSign(int $topicIndex, int $n): array
    {
        $signs = DemoRoadSigns::all();
        $junctions = DemoRoadSigns::junctions();

        // Rüb 2-nin ilk dörd sualı yolayrıcı sxemidir, qalanı nişan tələbidir
        if ($topicIndex === 1 && $n < count($junctions)) {
            return $this->imageQuestion(
                $junctions[$n],
                $junctions,
                'meaning',
                'Sxemdə bərabərhüquqlu yolayrıcı göstərilib. Hansı nəqliyyat vasitəsi birinci keçməlidir?',
                $n,
            );
        }

        /*
         * Nişanlar təkrarlanmasın deyə hər rüb kataloqun başqa hissəsindən başlayır.
         * Kataloqda 12 nişan var, hər rübdə isə 8-dən çoxu lazım olmur.
         */
        $offset = match ($topicIndex) {
            1 => 8 + ($n - count($junctions)),
            2 => 4 + $n,
            default => $n,
        };

        $sign = $signs[$offset % count($signs)];

        [$field, $question] = match ($topicIndex) {
            1 => ['meaning', 'Aşağıdakı nişan sürücüyə hansı tələbi qoyur?'],
            2 => ['group', 'Şəkildəki nişan hansı qrupa aiddir?'],
            default => ['meaning', 'Şəkildəki yol nişanı nəyi bildirir?'],
        };

        return $this->imageQuestion($sign, $signs, $field, $question, $n);
    }

    /**
     * Şəkilli qapalı sual: doğru cavab nişanın öz mənası (və ya qrupu), yanlış variantlar
     * isə kataloqun digər sətirlərindən.
     *
     * @param  array<string, string>  $item
     * @param  array<int, array<string, string>>  $pool
     * @return array<string, mixed>
     */
    private function imageQuestion(array $item, array $pool, string $field, string $question, int $n): array
    {
        $correct = $item[$field];
        $wrong = [];

        foreach ($pool as $index => $other) {
            $value = $other[$field];

            if ($value !== $correct && ! in_array($value, $wrong, true)) {
                $wrong[] = $value;
            }
        }

        // Yanlış variantlar da sualdan-suala dəyişsin
        $picked = [];

        for ($k = 0; count($picked) < 3 && $k < count($wrong); $k++) {
            $picked[] = $wrong[($n + $k) % count($wrong)];
        }

        return [
            'question_text' => $question,
            'question_image' => DemoRoadSigns::DIRECTORY.'/'.$item['key'].'.svg',
            'question_image_alt' => $item['alt'],
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($correct, array_unique($picked), $n),
            'explanation' => 'Düzgün cavab: '.$correct.'.',
        ];
    }

    /* ---------------------------------------------------------------- çərçivələr */

    /** 5 variantlı: mövzuya aid anlayışı tapmaq */
    private function choice5(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $correct = $this->term($topics, $i, $n, $lang);
        $wrong = $this->otherTerms($topics, $i, $lang, 4, $n);

        return [
            'question_text' => $this->t($lang,
                "Aşağıdakı anlayışlardan hansı «{$topic}» mövzusuna aiddir?",
                "Какое из следующих понятий относится к теме «{$topic}»?"),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($correct, $wrong, $n),
            'explanation' => $this->t($lang,
                "«{$correct}» «{$topic}» mövzusunun anlayışıdır; qalan variantlar başqa mövzulara aiddir.",
                "«{$correct}» относится к теме «{$topic}»; остальные варианты — из других тем."),
        ];
    }

    /** 4 variantlı: anlayışın hansı mövzuya aid olduğunu tapmaq */
    private function choice4(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $term = $this->term($topics, $i, $n + 1, $lang);
        $wrong = [];

        foreach ($topics as $index => $other) {
            if ($index !== $i) {
                $wrong[] = $this->topicName($topics, $index);
            }
        }

        return [
            'question_text' => $this->t($lang,
                "«{$term}» anlayışı hansı mövzuda öyrənilir?",
                "В рамках какой темы изучается понятие «{$term}»?"),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($topic, array_slice($wrong, 0, 3), $n),
            'explanation' => $this->t($lang,
                "«{$term}» «{$topic}» mövzusunun tərkib hissəsidir.",
                "«{$term}» входит в тему «{$topic}»."),
        ];
    }

    /** 5 variantlı inkar sualı: mövzuya AİD OLMAYANI tapmaq */
    private function negative(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $correct = $this->otherTerms($topics, $i, $lang, 1, $n)[0];
        $wrong = $this->ownTerms($topics, $i, $lang, 4, $n);

        return [
            'question_text' => $this->t($lang,
                "Aşağıdakılardan hansı «{$topic}» mövzusuna AİD DEYİL?",
                "Какое из перечисленных понятий НЕ относится к теме «{$topic}»?"),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($correct, $wrong, $n),
            'explanation' => $this->t($lang,
                "«{$correct}» başqa mövzunun anlayışıdır, qalanları «{$topic}» mövzusuna aiddir.",
                "«{$correct}» относится к другой теме, остальные — к теме «{$topic}»."),
        ];
    }

    /** Açıq kodlaşdırılan: rübün nömrəsi (AnswerNormalizer ilə avtomatik yoxlanır) */
    private function coded(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $quarter = $i + 1;

        return [
            'question_text' => $this->t($lang,
                "«{$topic}» mövzusu tədris ilinin neçənci rübündə keçilir? "
                    .'Cavabı yalnız rəqəmlə yazın.',
                "В какой четверти учебного года изучается тема «{$topic}»? "
                    .'Запишите ответ только цифрой.'),
            'type' => Question::TYPE_OPEN_CODED,
            'accepted_answers' => [(string) $quarter, $quarter.($lang === 'ru' ? '-я четверть' : '-ci rüb')],
            'options' => [],
            'explanation' => $this->t($lang,
                "Bu mövzu {$quarter}-ci rübün proqramındadır.",
                "Эта тема входит в программу {$quarter}-й четверти."),
        ];
    }

    /** Açıq yazılı: admin şkala ilə qiymətləndirir */
    private function written(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $term = $this->term($topics, $i, $n, $lang);

        return [
            'question_text' => $this->t($lang,
                "«{$term}» anlayışını öz sözlərinizlə izah edin və «{$topic}» mövzusundan "
                    .'bir nümunə göstərin.',
                "Объясните своими словами понятие «{$term}» и приведите один пример "
                    ."из темы «{$topic}»."),
            'type' => Question::TYPE_OPEN_WRITTEN,
            'options' => [],
            'explanation' => $this->t($lang,
                'Tam cavab: anlayışın tərifi + mövzuya uyğun konkret nümunə.',
                'Полный ответ: определение понятия + конкретный пример по теме.'),
        ];
    }

    /** Uzun mətnli açıq yazılı: dizaynda geniş mətn sahəsini yoxlamaq üçün */
    private function writtenLong(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $a = $this->term($topics, $i, $n, $lang);
        $b = $this->term($topics, $i, $n + 2, $lang);

        return [
            'question_text' => $this->t($lang,
                "«{$topic}» mövzusunda «{$a}» və «{$b}» anlayışları tez-tez qarışdırılır. "
                    .'Aşağıdakı üç bənd üzrə cavab yazın: (1) hər iki anlayışın qısa tərifi; '
                    .'(2) onları bir-birindən fərqləndirən ən azı iki əlamət; '
                    .'(3) hər biri üçün gündəlik həyatdan və ya dərslikdən bir nümunə. '
                    .'Cavabınızı bütöv cümlələrlə, 8–10 cümlə həcmində yazın.',
                "В теме «{$topic}» понятия «{$a}» и «{$b}» часто путают. "
                    .'Ответьте по трём пунктам: (1) краткое определение каждого понятия; '
                    .'(2) не менее двух признаков, отличающих их друг от друга; '
                    .'(3) по одному примеру из повседневной жизни или учебника. '
                    .'Пишите полными предложениями, объёмом 8–10 предложений.'),
            'type' => Question::TYPE_OPEN_WRITTEN,
            'options' => [],
            'explanation' => $this->t($lang,
                'Üç bəndin hər biri ayrıca qiymətləndirilir; tam bal üçün hamısı tam açılmalıdır.',
                'Каждый из трёх пунктов оценивается отдельно; для полного балла нужны все три.'),
        ];
    }

    /** Situasiya mətni + qapalı sual (4 variant) */
    private function passage(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $term = $this->term($topics, $i, $n, $lang);
        $wrong = [];

        foreach ($topics as $index => $other) {
            if ($index !== $i) {
                $wrong[] = $this->topicName($topics, $index);
            }
        }

        return [
            'question_text' => $this->t($lang,
                "Müəllim dərsdə şagirdlərə «{$term}» ilə bağlı praktik tapşırıq verir və "
                    .'onlardan nəticəni cədvəldə ümumiləşdirməyi xahiş edir. Şagirdlər tapşırığı '
                    .'yerinə yetirərkən hansı mövzunun bilikləri ilə işləyirlər?',
                "Учитель даёт ученикам практическое задание, связанное с понятием «{$term}», "
                    .'и просит обобщить результат в таблице. Знаниями какой темы пользуются '
                    .'ученики при выполнении задания?'),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($topic, array_slice($wrong, 0, 3), $n + 1),
            'explanation' => $this->t($lang,
                "Tapşırıq «{$term}» anlayışı üzərində qurulub, o isə «{$topic}» mövzusuna aiddir.",
                "Задание построено на понятии «{$term}», которое относится к теме «{$topic}»."),
        ];
    }

    /** Uzun mətnli qapalı sual (5 variant) — kataloq və imtahan dizaynını yoxlamaq üçün */
    private function passageLong(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $correct = $this->term($topics, $i, $n, $lang);
        $wrong = $this->otherTerms($topics, $i, $lang, 4, $n + 2);

        return [
            'question_text' => $this->t($lang,
                "Aşağıdakı mətni oxuyun və sualı cavablandırın.\n\n"
                    ."«{$topic}» mövzusu tədris proqramında bir neçə dərsə bölünür. İlk dərslərdə "
                    .'şagirdlər əsas anlayışlarla tanış olur, sonrakı dərslərdə isə bu anlayışları '
                    .'praktik tapşırıqlarda tətbiq edirlər. Müəllim mövzunun sonunda kiçik summativ '
                    .'qiymətləndirmə keçirir və nəticələri təhlil edərək hansı anlayışın zəif '
                    .'mənimsənildiyini müəyyən edir. Təhlil göstərir ki, şagirdlərin yarıdan çoxu '
                    .'məhz mövzunun mərkəzi anlayışında çətinlik çəkir.'
                    ."\n\nMətndə söhbət «{$topic}» mövzusunun anlayışlarından gedir. "
                    .'Aşağıdakılardan hansı məhz bu mövzunun anlayışıdır?',
                "Прочитайте текст и ответьте на вопрос.\n\n"
                    ."Тема «{$topic}» разбита в учебной программе на несколько уроков. На первых "
                    .'уроках ученики знакомятся с основными понятиями, а на последующих применяют '
                    .'эти понятия в практических заданиях. В конце темы учитель проводит малое '
                    .'суммативное оценивание и, анализируя результаты, определяет, какое понятие '
                    .'усвоено слабо. Анализ показывает, что более половины учеников испытывают '
                    .'затруднение именно с центральным понятием темы.'
                    ."\n\nВ тексте речь идёт о понятиях темы «{$topic}». "
                    .'Какое из перечисленных понятий относится именно к этой теме?'),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($correct, $wrong, $n),
            'explanation' => $this->t($lang,
                "Mətn «{$topic}» mövzusundan bəhs edir, «{$correct}» isə həmin mövzunun anlayışıdır.",
                "Текст посвящён теме «{$topic}», а «{$correct}» — понятие этой темы."),
        ];
    }

    /** İki anlayış — hansı mövzuda birlikdə öyrənilir (4 variant: mövzu adları) */
    private function pairing(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $a = $this->term($topics, $i, $n, $lang);
        $b = $this->term($topics, $i, $n + 3, $lang);
        $wrong = [];

        foreach ($topics as $index => $other) {
            if ($index !== $i) {
                $wrong[] = $this->topicName($topics, $index);
            }
        }

        return [
            'question_text' => $this->t($lang,
                "«{$a}» və «{$b}» anlayışları hansı mövzuda birlikdə öyrənilir?",
                "В рамках какой темы понятия «{$a}» и «{$b}» изучаются вместе?"),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($topic, array_slice($wrong, 0, 3), $n + 2),
            'explanation' => $this->t($lang,
                "Hər iki anlayış «{$topic}» mövzusunun tərkibindədir.",
                "Оба понятия входят в тему «{$topic}»."),
        ];
    }

    /** Rüb sualının qapalı variantı (açıq sual icazəli olmayan imtahanlar üçün) */
    private function quarterClosed(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $quarter = $i + 1;

        $label = fn (int $number) => $this->t($lang, $number.'-ci rüb', $number.'-я четверть');

        $wrong = [];

        foreach ([1, 2, 3, 4] as $number) {
            if ($number !== $quarter) {
                $wrong[] = $label($number);
            }
        }

        return [
            'question_text' => $this->t($lang,
                "«{$topic}» mövzusu tədris ilinin hansı rübündə keçilir?",
                "В какой четверти учебного года изучается тема «{$topic}»?"),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($label($quarter), $wrong, $n),
            'explanation' => $this->t($lang,
                "Bu mövzu {$quarter}-ci rübün proqramındadır.",
                "Эта тема входит в программу {$quarter}-й четверти."),
        ];
    }

    /** İkinci situasiya sualı: `passage`-dən fərqli quruluş (qapalı) */
    private function passageAlt(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $topic = $this->topicName($topics, $i);
        $correct = $this->term($topics, $i, $n + 4, $lang);
        $wrong = $this->otherTerms($topics, $i, $lang, 4, $n + 1);

        return [
            'question_text' => $this->t($lang,
                "Şagird «{$topic}» mövzusu üzrə təkrar dərsə hazırlaşır və qeyd dəftərinə "
                    .'mövzunun əsas anlayışlarını yazır. Aşağıdakılardan hansı bu siyahıya düşməlidir?',
                "Ученик готовится к повторительному уроку по теме «{$topic}» и выписывает в "
                    .'тетрадь основные понятия темы. Какое из перечисленных должно попасть в этот список?'),
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options($correct, $wrong, $n + 3),
            'explanation' => $this->t($lang,
                "«{$correct}» «{$topic}» mövzusunun anlayışıdır.",
                "«{$correct}» — понятие темы «{$topic}»."),
        ];
    }

    /* ------------------------------------------------- hesablanmış KaTeX sualları */

    /** Düsturlu qapalı sual: variantlar hesablanmış qiymətdən qurulur */
    private function formula(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $item = DemoFormulas::closed($subject, $i, $n, $lang);

        return [
            'question_text' => $item['text'],
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->options(
                $item['answer'],
                $item['distractors'],
                $n,
            ),
            'explanation' => $item['explanation'],
        ];
    }

    /** Düsturlu açıq kodlaşdırılan sual: cavab rəqəmdir, avtomatik yoxlanır */
    private function formulaCoded(string $subject, array $topics, int $i, string $lang, int $n): array
    {
        $item = DemoFormulas::coded($subject, $i, $n, $lang);

        return [
            'question_text' => $item['text'],
            'type' => Question::TYPE_OPEN_CODED,
            'accepted_answers' => $item['accepted'],
            'options' => [],
            'explanation' => $item['explanation'],
        ];
    }

    /* ----------------------------------------------------------------- köməkçilər */

    /**
     * Variantları hazırlayır. Doğru cavabın yeri sualın nömrəsindən asılıdır — həmişə
     * "A" olsaydı, demo imtahanı cavabına baxmadan keçmək olardı.
     *
     * @param  array<int, string>  $wrong
     * @return array<int, array{option_text: string, is_correct: bool}>
     */
    private function options(string $correct, array $wrong, int $n): array
    {
        $values = $wrong;
        array_splice($values, $n % (count($wrong) + 1), 0, [$correct]);

        return array_map(
            fn (string $value) => ['option_text' => $value, 'is_correct' => $value === $correct],
            $values,
        );
    }

    /** @param  array<int, array<string, mixed>>  $topics */
    private function topicName(array $topics, int $i): string
    {
        return $topics[$i]['name'];
    }

    /** Mövzunun anlayışı (dilə görə), siyahı dövrə vurur */
    private function term(array $topics, int $i, int $n, string $lang): string
    {
        $terms = $this->terms($topics[$i], $lang);

        return $terms[$n % count($terms)];
    }

    /**
     * Mövzunun öz anlayışlarından bir neçəsi (doğru cavabdan başqa).
     *
     * @return array<int, string>
     */
    private function ownTerms(array $topics, int $i, string $lang, int $count, int $n): array
    {
        $terms = $this->terms($topics[$i], $lang);
        $picked = [];

        for ($k = 0; count($picked) < $count && $k < count($terms) * 2; $k++) {
            $value = $terms[($n + $k) % count($terms)];

            if (! in_array($value, $picked, true)) {
                $picked[] = $value;
            }
        }

        return $picked;
    }

    /**
     * Başqa mövzuların anlayışları — yanlış variantlar üçün.
     *
     * @return array<int, string>
     */
    private function otherTerms(array $topics, int $i, string $lang, int $count, int $n): array
    {
        $pool = [];

        foreach ($topics as $index => $topic) {
            if ($index === $i) {
                continue;
            }

            foreach ($this->terms($topic, $lang) as $term) {
                $pool[] = $term;
            }
        }

        $picked = [];

        for ($k = 0; count($picked) < $count && $k < count($pool); $k++) {
            $value = $pool[($n * 3 + $k) % count($pool)];

            if (! in_array($value, $picked, true)) {
                $picked[] = $value;
            }
        }

        return $picked;
    }

    /**
     * Anlayışlar dilə görə. Rus sətri verilməyibsə (məsələn alman dili fənni) fənn dilindəki
     * siyahı işlənir — orada anlayış onsuz da tərcümə olunmur.
     *
     * @return array<int, string>
     */
    private function terms(array $topic, string $lang): array
    {
        return $lang === 'ru' && isset($topic['terms_ru']) ? $topic['terms_ru'] : $topic['terms'];
    }

    private function t(string $lang, string $az, string $ru): string
    {
        return $lang === 'ru' ? $ru : $az;
    }
}
