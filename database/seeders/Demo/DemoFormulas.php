<?php

namespace Database\Seeders\Demo;

/**
 * Hesablanmış düsturlu demo sualları: mətndə KaTeX (`$...$`), cavab isə həqiqətən doğrudur.
 *
 * Parametrlər sualın nömrəsindən çıxarılır — təsadüfilik yoxdur, ona görə seeder təkrar
 * işlədiləndə eyni sual alınır və `source` açarı ilə üstünə yazılır.
 *
 * `MathText.vue` `$...$` və `$$...$$` bloklarını KaTeX ilə render edir; burada inline forma
 * işlənir ki, sual mətni bir sətirdə oxunaqlı qalsın.
 */
class DemoFormulas
{
    /**
     * Qapalı (variantlı) düstur sualı.
     *
     * @return array{text: string, answer: string, distractors: array<int, string>, explanation: string}
     */
    public static function closed(string $subject, int $topicIndex, int $n, string $lang): array
    {
        $item = self::item($subject, $topicIndex, $n, $lang);

        return [
            'text' => $item['text'],
            'answer' => $item['answer'].$item['unit'],
            'distractors' => array_map(
                fn (string $value) => $value.$item['unit'],
                self::distractors($item['value'], $item['answer'], 3),
            ),
            'explanation' => $item['explanation'],
        ];
    }

    /**
     * Açıq kodlaşdırılan düstur sualı: cavab rəqəmdir, `AnswerNormalizer` yoxlayır.
     *
     * @return array{text: string, accepted: array<int, string>, explanation: string}
     */
    public static function coded(string $subject, int $topicIndex, int $n, string $lang): array
    {
        // Qapalı variantla eyni sual olmasın deyə nömrə sürüşdürülür
        $item = self::item($subject, $topicIndex, $n + 5, $lang);

        $suffix = $lang === 'ru'
            ? ' Запишите ответ числом (без единиц измерения).'
            : ' Cavabı yalnız ədədlə yazın (ölçü vahidi olmadan).';

        return [
            'text' => $item['text'].$suffix,
            'accepted' => array_values(array_unique([$item['answer'], $item['answer'].$item['unit']])),
            'explanation' => $item['explanation'],
        ];
    }

    /**
     * Fənn və rüb üzrə parametrik məsələ.
     *
     * @return array{text: string, answer: string, value: float, unit: string, explanation: string}
     */
    private static function item(string $subject, int $topicIndex, int $n, string $lang): array
    {
        $ru = $lang === 'ru';

        return match ($subject) {
            'riyaziyyat' => self::mathItem($topicIndex, $n, $ru),
            'fizika' => self::physicsItem($topicIndex, $n, $ru),
            'kimya' => self::chemistryItem($topicIndex, $n, $ru),
            'informatika' => self::informaticsItem($topicIndex, $n, $ru),
            default => self::logicItem($topicIndex, $n, $ru),
        };
    }

    private static function mathItem(int $topicIndex, int $n, bool $ru): array
    {
        return match ($topicIndex) {
            0 => (function () use ($n, $ru) {
                $base = 200 + 50 * ($n % 6);
                $percent = 5 * (($n % 4) + 2);
                $value = $base * $percent / 100;

                return self::make(
                    $ru
                        ? "Найдите $\;{$percent}\\%$ от числа $\;{$base}$."
                        : "$\;{$base}$ ədədinin $\;{$percent}\\%$-ni tapın.",
                    $value,
                    '',
                    $ru
                        ? '$\\frac{'.$base.' \\cdot '.$percent.'}{100} = '.self::number($value).'$'
                        : '$\\frac{'.$base.' \\cdot '.$percent.'}{100} = '.self::number($value).'$',
                );
            })(),
            1 => (function () use ($n, $ru) {
                $a = 2 + $n % 5;
                $root = 3 + $n % 7;
                $b = 4 + $n % 9;
                $c = $a * $root + $b;

                return self::make(
                    $ru
                        ? "Решите уравнение $\;{$a}x + {$b} = {$c}$."
                        : "$\;{$a}x + {$b} = {$c}$ tənliyini həll edin.",
                    $root,
                    '',
                    '$x = \\frac{'.$c.' - '.$b.'}{'.$a.'} = '.$root.'$',
                );
            })(),
            2 => (function () use ($n, $ru) {
                // Pifaqor üçlükləri: kök həmişə tam ədəddir
                [$a, $b, $c] = [[3, 4, 5], [6, 8, 10], [5, 12, 13], [9, 12, 15], [8, 15, 17]][$n % 5];

                return self::make(
                    $ru
                        ? "Катеты прямоугольного треугольника равны $\;{$a}$ и $\;{$b}$. "
                            .'Найдите гипотенузу.'
                        : "Düzbucaqlı üçbucağın katetləri $\;{$a}$ və $\;{$b}$-dir. Hipotenuzu tapın.",
                    $c,
                    '',
                    '$c = \\sqrt{'.$a.'^2 + '.$b.'^2} = \\sqrt{'.($a * $a + $b * $b).'} = '.$c.'$',
                );
            })(),
            default => (function () use ($n, $ru) {
                $start = 2 + $n % 5;
                $numbers = [$start, $start + 2, $start + 4, $start + 6, $start + 8];
                $mean = array_sum($numbers) / count($numbers);
                $list = implode(',\; ', $numbers);

                return self::make(
                    $ru
                        ? "Найдите среднее арифметическое чисел $\;{$list}$."
                        : "$\;{$list}$ ədədlərinin ədədi ortasını tapın.",
                    $mean,
                    '',
                    '$\\frac{'.array_sum($numbers).'}{5} = '.self::number($mean).'$',
                );
            })(),
        };
    }

    private static function physicsItem(int $topicIndex, int $n, bool $ru): array
    {
        return match ($topicIndex) {
            0 => (function () use ($n, $ru) {
                $t = 2 + $n % 5;
                $v = 10 + 5 * ($n % 6);
                $s = $v * $t;

                return self::make(
                    $ru
                        ? "Тело прошло путь $\;s = {$s}\\,\\text{м}$ за $\;t = {$t}\\,\\text{с}$. "
                            .'Найдите скорость.'
                        : "Cisim $\;t = {$t}\\,\\text{s}$ ərzində $\;s = {$s}\\,\\text{m}$ yol gedib. "
                            .'Sürəti tapın.',
                    $v,
                    ' m/s',
                    '$v = \\frac{s}{t} = \\frac{'.$s.'}{'.$t.'} = '.$v.'$',
                );
            })(),
            1 => (function () use ($n, $ru) {
                $c = 2000;
                $m = 1 + $n % 5;
                $dt = 10 + 10 * ($n % 4);
                $q = $c * $m * $dt / 1000;

                return self::make(
                    $ru
                        ? "Вещество массой $\;m = {$m}\\,\\text{кг}$ нагрели на $\;\\Delta t = {$dt}\\,^\\circ C$. "
                            ."Удельная теплоёмкость $\;c = {$c}\\,\\frac{Дж}{кг \\cdot ^\\circ C}$. "
                            .'Сколько теплоты потребовалось (в кДж)?'
                        : "Kütləsi $\;m = {$m}\\,\\text{kq}$ olan maddə $\;\\Delta t = {$dt}\\,^\\circ C$ qızdırılıb. "
                            ."Xüsusi istilik tutumu $\;c = {$c}\\,\\frac{C}{kq \\cdot ^\\circ C}$. "
                            .'Neçə kC istilik lazım olub?',
                    $q,
                    ' kC',
                    '$Q = cm\\Delta t = '.$c.' \\cdot '.$m.' \\cdot '.$dt.' = '.($c * $m * $dt).'$ C',
                );
            })(),
            2 => (function () use ($n, $ru) {
                $r = 2 + $n % 8;
                $i = 1 + $n % 5;
                $u = $r * $i;

                return self::make(
                    $ru
                        ? "Напряжение на участке цепи $\;U = {$u}\\,\\text{В}$, сопротивление "
                            ."$\;R = {$r}\\,\\Omega$. Найдите силу тока."
                        : "Dövrə hissəsində gərginlik $\;U = {$u}\\,\\text{V}$, müqavimət "
                            ."$\;R = {$r}\\,\\Omega$-dur. Cərəyan şiddətini tapın.",
                    $i,
                    ' A',
                    '$I = \\frac{U}{R} = \\frac{'.$u.'}{'.$r.'} = '.$i.'$',
                );
            })(),
            default => (function () use ($n, $ru) {
                $f = [10, 20, 25, 50, 100][$n % 5];
                $d = 100 / $f;

                return self::make(
                    $ru
                        ? "Фокусное расстояние линзы $\;F = {$f}\\,\\text{см}$. "
                            .'Найдите оптическую силу линзы (в диоптриях).'
                        : "Linzanın fokus məsafəsi $\;F = {$f}\\,\\text{sm}$-dir. "
                            .'Linzanın optik gücünü tapın (dioptriya ilə).',
                    $d,
                    ' dpt',
                    '$D = \\frac{1}{F} = \\frac{100}{'.$f.'} = '.self::number($d).'$',
                );
            })(),
        };
    }

    private static function chemistryItem(int $topicIndex, int $n, bool $ru): array
    {
        return match ($topicIndex) {
            0 => (function () use ($n, $ru) {
                // Kütlə ədədi = proton + neytron
                [$element, $z, $a] = [['Na', 11, 23], ['Mg', 12, 24], ['Al', 13, 27], ['Cl', 17, 35], ['K', 19, 39]][$n % 5];
                $neutrons = $a - $z;

                return self::make(
                    $ru
                        ? "В изотопе $\;^{{$a}}_{{$z}}\\text{{$element}}$ определите число нейтронов."
                        : "$\;^{{$a}}_{{$z}}\\text{{$element}}$ izotopunda neytronların sayını tapın.",
                    $neutrons,
                    '',
                    '$N = A - Z = '.$a.' - '.$z.' = '.$neutrons.'$',
                );
            })(),
            1 => (function () use ($n, $ru) {
                $m = 2 + $n % 6;
                $molar = [40, 56, 80, 100, 64][$n % 5];
                $mass = $m * $molar;

                return self::make(
                    $ru
                        ? "Молярная масса вещества $\;M = {$molar}\\,\\frac{г}{моль}$, масса образца "
                            ."$\;m = {$mass}\\,\\text{г}$. Найдите количество вещества."
                        : "Maddənin molyar kütləsi $\;M = {$molar}\\,\\frac{q}{mol}$, nümunənin kütləsi "
                            ."$\;m = {$mass}\\,\\text{q}$-dır. Maddə miqdarını tapın.",
                    $m,
                    ' mol',
                    '$\\nu = \\frac{m}{M} = \\frac{'.$mass.'}{'.$molar.'} = '.$m.'$',
                );
            })(),
            2 => (function () use ($n, $ru) {
                $v = 100 + 50 * ($n % 5);
                $c = [0.1, 0.2, 0.5, 1.0, 2.0][$n % 5];
                $moles = $v * $c / 1000;

                return self::make(
                    $ru
                        ? 'Сколько моль растворённого вещества содержится в $\;'.$v
                            .'\\,\\text{мл}$ раствора с концентрацией $\;'.self::number($c).'\\,\\frac{моль}{л}$?'
                        : '$\;'.$v.'\\,\\text{ml}$ məhlulda qatılıq $\;'.self::number($c)
                            .'\\,\\frac{mol}{l}$-dirsə, həll olmuş maddənin neçə molu var?',
                    $moles,
                    ' mol',
                    '$\\nu = C \\cdot V = '.self::number($c).' \\cdot '.self::number($v / 1000)
                        .' = '.self::number($moles).'$',
                );
            })(),
            default => (function () use ($n, $ru) {
                $carbons = 2 + $n % 7;
                $hydrogens = 2 * $carbons + 2;

                return self::make(
                    $ru
                        ? "В молекуле алкана $\;C_{{$carbons}}H_{x}$ определите число атомов водорода."
                        : "$\;C_{{$carbons}}H_{x}$ alkan molekulunda hidrogen atomlarının sayını tapın.",
                    $hydrogens,
                    '',
                    '$C_nH_{2n+2}:\; 2 \cdot '.$carbons.' + 2 = '.$hydrogens.'$',
                );
            })(),
        };
    }

    private static function informaticsItem(int $topicIndex, int $n, bool $ru): array
    {
        return match ($topicIndex) {
            0 => (function () use ($n, $ru) {
                $kb = 1 + $n % 8;
                $bytes = $kb * 1024;

                return self::make(
                    $ru
                        ? "Сколько байт содержится в $\;{$kb}\\,\\text{КБ}$?"
                        : "$\;{$kb}\\,\\text{KB}$-da neçə bayt var?",
                    $bytes,
                    '',
                    '$'.$kb.' \\cdot 1024 = '.$bytes.'$',
                );
            })(),
            1 => (function () use ($n, $ru) {
                $bits = 3 + $n % 6;
                $count = 2 ** $bits;

                return self::make(
                    $ru
                        ? "Сколько различных значений можно закодировать $\;{$bits}$ битами?"
                        : "$\;{$bits}$ bitlə neçə müxtəlif qiymət kodlaşdırmaq olar?",
                    $count,
                    '',
                    '$2^{'.$bits.'} = '.$count.'$',
                );
            })(),
            2 => (function () use ($n, $ru) {
                $limit = 5 + $n % 10;
                $sum = $limit * ($limit + 1) / 2;

                return self::make(
                    $ru
                        ? "Цикл суммирует все целые числа от $\;1$ до $\;{$limit}$. "
                            .'Какое значение будет в переменной суммы после цикла?'
                        : "Dövr $\;1$-dən $\;{$limit}$-ə qədər bütün tam ədədləri toplayır. "
                            .'Dövrdən sonra cəm dəyişənində hansı qiymət olacaq?',
                    $sum,
                    '',
                    '$\\frac{n(n+1)}{2} = \\frac{'.$limit.' \\cdot '.($limit + 1).'}{2} = '.$sum.'$',
                );
            })(),
            default => (function () use ($n, $ru) {
                $hostBits = 2 + $n % 6;
                $hosts = 2 ** $hostBits - 2;

                return self::make(
                    $ru
                        ? "В подсети под адреса узлов отведено $\;{$hostBits}$ бит. Сколько узлов "
                            .'можно адресовать (без адреса сети и широковещательного)?'
                        : "Alt şəbəkədə qovşaq ünvanları üçün $\;{$hostBits}$ bit ayrılıb. Neçə qovşağa "
                            .'ünvan vermək olar (şəbəkə və yayım ünvanı çıxılmaqla)?',
                    $hosts,
                    '',
                    '$2^{'.$hostBits.'} - 2 = '.$hosts.'$',
                );
            })(),
        };
    }

    private static function logicItem(int $topicIndex, int $n, bool $ru): array
    {
        return match ($topicIndex) {
            // Rüb 2: həndəsi ardıcıllıq — rüb 1-dəki arifmetik ardıcıllıqla eyni sual çıxmasın
            1 => (function () use ($n, $ru) {
                $start = 2 + $n % 4;
                $series = [$start, $start * 2, $start * 4, $start * 8];
                $next = $start * 16;
                $list = implode(',\; ', $series);

                return self::make(
                    $ru
                        ? "Какое число продолжает ряд $\;{$list},\; \ldots$?"
                        : "$\;{$list},\; \ldots$ ardıcıllığını hansı ədəd davam etdirir?",
                    $next,
                    '',
                    $ru
                        ? 'Каждый следующий член вдвое больше предыдущего.'
                        : 'Hər sonrakı üzv əvvəlkindən iki dəfə böyükdür.',
                );
            })(),
            0 => (function () use ($n, $ru) {
                $start = 2 + $n % 5;
                $step = 3 + $n % 4;
                $series = [$start, $start + $step, $start + 2 * $step, $start + 3 * $step];
                $next = $start + 4 * $step;
                $list = implode(',\; ', $series);

                return self::make(
                    $ru
                        ? "Какое число продолжает ряд $\;{$list},\; \\ldots$?"
                        : "$\;{$list},\; \\ldots$ ardıcıllığını hansı ədəd davam etdirir?",
                    $next,
                    '',
                    $ru
                        ? "Каждый следующий член больше предыдущего на $\;{$step}$."
                        : "Hər sonrakı üzv əvvəlkindən $\;{$step}$ vahid böyükdür.",
                );
            })(),
            2 => (function () use ($n, $ru) {
                $price = 200 + 100 * ($n % 6);
                $discount = 10 * (1 + $n % 4);
                $final = $price * (100 - $discount) / 100;

                return self::make(
                    $ru
                        ? "Товар стоимостью $\;{$price}$ манатов подешевел на $\;{$discount}\\%$. "
                            .'Какова новая цена?'
                        : "$\;{$price}$ manatlıq mal $\;{$discount}\\%$ ucuzlaşıb. Yeni qiymət nə qədərdir?",
                    $final,
                    ' AZN',
                    '$'.$price.' \\cdot \\frac{'.(100 - $discount).'}{100} = '.self::number($final).'$',
                );
            })(),
            default => (function () use ($n, $ru) {
                $sonAge = 8 + $n % 7;
                $factor = 3 + $n % 3;
                $fatherAge = $sonAge * $factor;

                return self::make(
                    $ru
                        ? "Отец старше сына в $\;{$factor}$ раза. Сыну $\;{$sonAge}$ лет. "
                            .'Сколько лет отцу?'
                        : "Ata oğlundan $\;{$factor}$ dəfə böyükdür. Oğlunun yaşı $\;{$sonAge}$-dir. "
                            .'Atanın yaşı neçədir?',
                    $fatherAge,
                    '',
                    '$'.$sonAge.' \\cdot '.$factor.' = '.$fatherAge.'$',
                );
            })(),
        };
    }

    /** @return array{text: string, answer: string, value: float, unit: string, explanation: string} */
    private static function make(string $text, float|int $value, string $unit, string $explanation): array
    {
        return [
            'text' => $text,
            'answer' => self::number($value),
            'value' => (float) $value,
            'unit' => $unit,
            'explanation' => $explanation,
        ];
    }

    /**
     * Yanlış variantlar: doğru cavabın ətrafından, təkrarsız və mənfi olmayan.
     *
     * @return array<int, string>
     */
    private static function distractors(float $value, string $answer, int $count): array
    {
        $candidates = [$value + 1, $value - 1, $value * 2, $value + 2, $value / 2, $value + 10, $value * 3];
        $picked = [];

        foreach ($candidates as $candidate) {
            if ($candidate <= 0) {
                continue;
            }

            $formatted = self::number($candidate);

            if ($formatted !== $answer && ! in_array($formatted, $picked, true)) {
                $picked[] = $formatted;
            }

            if (count($picked) === $count) {
                break;
            }
        }

        return $picked;
    }

    /** Tam ədəd tam yazılır, kəsr isə artıq sıfırlar olmadan */
    private static function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
