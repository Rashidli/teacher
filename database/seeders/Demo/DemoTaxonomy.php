<?php

namespace Database\Seeders\Demo;

/**
 * Demo məzmunun sümük skeleti: hər fənn üçün rüblərə bölünmüş 4 mövzu və hər mövzunun
 * anlayışları. Suallar bu siyahılardan qurulur (bax `DemoQuestionFactory`).
 *
 * Mövzular DİL ÜZRƏ BÖLÜNMÜR — `topics` cədvəlində dil sütunu yoxdur, mövzu fənnə aiddir.
 * Rus sektorunun sualları eyni mövzulara bağlanır, yalnız sual mətni ruscadır; `terms_ru`
 * həmin mətnlər üçündür. (Rus sektorunda statistikada mövzu adı azərbaycanca görünür —
 * bu, mövzuların tərcüməsi məsələsidir, demo datanın yox.)
 *
 * Fənn dilinin özü mövzu adını təyin edir: rus dili fənnində mövzular rusca, alman
 * dilində almanca — çünki orada fənn dili elə həmin dildir.
 */
class DemoTaxonomy
{
    /**
     * Bir mövzu bloku: `name` mövzunun adı, `terms` az sualları üçün anlayışlar,
     * `terms_ru` isə rus sektoru sualları üçün eyni anlayışların rusca qarşılığı.
     *
     * @return array<string, array<int, array{name: string, terms: array<int, string>, terms_ru?: array<int, string>}>>
     */
    public static function all(): array
    {
        return [
            'azerbaycan-dili' => [
                ['name' => 'Fonetika', 'terms' => ['sait səslər', 'samit səslər', 'heca', 'vurğu', 'ahəng qanunu', 'söz sonunda kar samit'],
                    'terms_ru' => ['гласные звуки', 'согласные звуки', 'слог', 'ударение', 'закон гармонии', 'оглушение в конце слова']],
                ['name' => 'Leksika', 'terms' => ['omonim', 'sinonim', 'antonim', 'frazeoloji birləşmə', 'çoxmənalı söz', 'termin'],
                    'terms_ru' => ['омоним', 'синоним', 'антоним', 'фразеологизм', 'многозначное слово', 'термин']],
                ['name' => 'Morfologiya', 'terms' => ['isim', 'sifət', 'say', 'əvəzlik', 'feil', 'zərf'],
                    'terms_ru' => ['существительное', 'прилагательное', 'числительное', 'местоимение', 'глагол', 'наречие']],
                ['name' => 'Sintaksis', 'terms' => ['mübtəda', 'xəbər', 'tamamlıq', 'təyin', 'zərflik', 'tabeli mürəkkəb cümlə'],
                    'terms_ru' => ['подлежащее', 'сказуемое', 'дополнение', 'определение', 'обстоятельство', 'сложноподчинённое предложение']],
            ],
            'edebiyyat' => [
                ['name' => 'Şifahi xalq ədəbiyyatı', 'terms' => ['bayatı', 'nağıl', 'dastan', 'atalar sözü', 'tapmaca', 'laylay'],
                    'terms_ru' => ['баяты', 'сказка', 'дастан', 'пословица', 'загадка', 'колыбельная']],
                ['name' => 'Klassik poeziya', 'terms' => ['qəzəl', 'qəsidə', 'rübai', 'məsnəvi', 'əruz vəzni', 'rədif'],
                    'terms_ru' => ['газель', 'касыда', 'рубаи', 'месневи', 'аруз', 'редиф']],
                ['name' => 'Nəsr və dramaturgiya', 'terms' => ['roman', 'povest', 'hekayə', 'komediya', 'faciə', 'remarka'],
                    'terms_ru' => ['роман', 'повесть', 'рассказ', 'комедия', 'трагедия', 'ремарка']],
                ['name' => 'Ədəbiyyat nəzəriyyəsi', 'terms' => ['metafora', 'epitet', 'təşbeh', 'mübaliğə', 'təzad', 'alliterasiya'],
                    'terms_ru' => ['метафора', 'эпитет', 'сравнение', 'гипербола', 'антитеза', 'аллитерация']],
            ],
            'tarix' => [
                ['name' => 'Qədim dövr', 'terms' => ['Manna', 'Midiya', 'Atropatena', 'Albaniya', 'Qobustan qaya rəsmləri', 'Kür-Araz mədəniyyəti'],
                    'terms_ru' => ['Манна', 'Мидия', 'Атропатена', 'Албания', 'наскальные рисунки Гобустана', 'кура-аракская культура']],
                ['name' => 'Orta əsrlər', 'terms' => ['Sacilər dövləti', 'Şirvanşahlar', 'Atabəylər', 'Ağqoyunlu', 'Səfəvilər', 'Dədə Qorqud dövrü'],
                    'terms_ru' => ['государство Саджидов', 'Ширваншахи', 'Атабеки', 'Аккоюнлу', 'Сефевиды', 'эпоха Деде Горгуда']],
                ['name' => 'Yeni dövr', 'terms' => ['xanlıqlar', 'Gülüstan müqaviləsi', 'Türkmənçay müqaviləsi', 'neft bumu', 'maarifçilik', 'Şimali Azərbaycan'],
                    'terms_ru' => ['ханства', 'Гюлистанский договор', 'Туркманчайский договор', 'нефтяной бум', 'просветительство', 'Северный Азербайджан']],
                ['name' => 'Müasir dövr', 'terms' => ['Azərbaycan Xalq Cümhuriyyəti', 'aprel işğalı', 'İkinci Dünya müharibəsi', 'müstəqilliyin bərpası', 'Qarabağ', '44 günlük müharibə'],
                    'terms_ru' => ['Азербайджанская Демократическая Республика', 'апрельская оккупация', 'Вторая мировая война', 'восстановление независимости', 'Карабах', '44-дневная война']],
            ],
            'cografiya' => [
                ['name' => 'Ümumi coğrafiya', 'terms' => ['litosfer', 'atmosfer', 'hidrosfer', 'biosfer', 'miqyas', 'coğrafi koordinatlar'],
                    'terms_ru' => ['литосфера', 'атмосфера', 'гидросфера', 'биосфера', 'масштаб', 'географические координаты']],
                ['name' => 'Təbii şərait', 'terms' => ['relyef', 'iqlim tipləri', 'çaylar', 'göllər', 'torpaq örtüyü', 'bitki qurşaqları'],
                    'terms_ru' => ['рельеф', 'типы климата', 'реки', 'озёра', 'почвенный покров', 'растительные пояса']],
                ['name' => 'Əhali coğrafiyası', 'terms' => ['əhali sıxlığı', 'miqrasiya', 'təbii artım', 'urbanizasiya', 'şəhər aqlomerasiyası', 'əmək ehtiyatları'],
                    'terms_ru' => ['плотность населения', 'миграция', 'естественный прирост', 'урбанизация', 'городская агломерация', 'трудовые ресурсы']],
                ['name' => 'İqtisadi coğrafiya', 'terms' => ['neft-qaz sənayesi', 'kənd təsərrüfatı', 'nəqliyyat', 'turizm', 'iqtisadi rayon', 'ixracat'],
                    'terms_ru' => ['нефтегазовая промышленность', 'сельское хозяйство', 'транспорт', 'туризм', 'экономический район', 'экспорт']],
            ],
            'riyaziyyat' => [
                ['name' => 'Ədədlər və faiz', 'terms' => ['rasional ədəd', 'adi kəsr', 'onluq kəsr', 'faiz', 'nisbət', 'tənasüb'],
                    'terms_ru' => ['рациональное число', 'обыкновенная дробь', 'десятичная дробь', 'процент', 'отношение', 'пропорция']],
                ['name' => 'Cəbr', 'terms' => ['birdəyişənli tənlik', 'bərabərsizlik', 'çoxhədli', 'vüsətli vurma düsturları', 'tənliklər sistemi', 'funksiya'],
                    'terms_ru' => ['уравнение с одной переменной', 'неравенство', 'многочлен', 'формулы сокращённого умножения', 'система уравнений', 'функция']],
                ['name' => 'Həndəsə', 'terms' => ['üçbucaq', 'çevrə', 'dairə', 'Pifaqor teoremi', 'sahə', 'həcm'],
                    'terms_ru' => ['треугольник', 'окружность', 'круг', 'теорема Пифагора', 'площадь', 'объём']],
                ['name' => 'Ehtimal və statistika', 'terms' => ['orta qiymət', 'median', 'moda', 'ehtimal', 'kombinatorika', 'diaqram'],
                    'terms_ru' => ['среднее значение', 'медиана', 'мода', 'вероятность', 'комбинаторика', 'диаграмма']],
            ],
            'fizika' => [
                ['name' => 'Mexanika', 'terms' => ['sürət', 'təcil', 'qüvvə', 'Nyuton qanunları', 'impuls', 'iş və güc'],
                    'terms_ru' => ['скорость', 'ускорение', 'сила', 'законы Ньютона', 'импульс', 'работа и мощность']],
                ['name' => 'Molekulyar fizika', 'terms' => ['temperatur', 'istilik miqdarı', 'ideal qaz', 'daxili enerji', 'faza keçidi', 'istilik balansı'],
                    'terms_ru' => ['температура', 'количество теплоты', 'идеальный газ', 'внутренняя энергия', 'фазовый переход', 'тепловой баланс']],
                ['name' => 'Elektrik', 'terms' => ['elektrik yükü', 'cərəyan şiddəti', 'gərginlik', 'müqavimət', 'Om qanunu', 'maqnit sahəsi'],
                    'terms_ru' => ['электрический заряд', 'сила тока', 'напряжение', 'сопротивление', 'закон Ома', 'магнитное поле']],
                ['name' => 'Optika və atom fizikası', 'terms' => ['işığın sınması', 'linza', 'spektr', 'fotoeffekt', 'atom nüvəsi', 'radioaktivlik'],
                    'terms_ru' => ['преломление света', 'линза', 'спектр', 'фотоэффект', 'атомное ядро', 'радиоактивность']],
            ],
            'kimya' => [
                ['name' => 'Atomun quruluşu', 'terms' => ['proton', 'neytron', 'elektron', 'izotop', 'dövri sistem', 'valentlik'],
                    'terms_ru' => ['протон', 'нейтрон', 'электрон', 'изотоп', 'периодическая система', 'валентность']],
                ['name' => 'Kimyəvi rabitə və reaksiyalar', 'terms' => ['kovalent rabitə', 'ion rabitəsi', 'oksidləşmə', 'reduksiya', 'katalizator', 'reaksiya sürəti'],
                    'terms_ru' => ['ковалентная связь', 'ионная связь', 'окисление', 'восстановление', 'катализатор', 'скорость реакции']],
                ['name' => 'Qeyri-üzvi kimya', 'terms' => ['turşu', 'əsas', 'duz', 'oksid', 'neytrallaşma', 'hidroliz'],
                    'terms_ru' => ['кислота', 'основание', 'соль', 'оксид', 'нейтрализация', 'гидролиз']],
                ['name' => 'Üzvi kimya', 'terms' => ['alkan', 'alken', 'spirt', 'karbon turşusu', 'polimer', 'izomer'],
                    'terms_ru' => ['алкан', 'алкен', 'спирт', 'карбоновая кислота', 'полимер', 'изомер']],
            ],
            'biologiya' => [
                ['name' => 'Sitologiya', 'terms' => ['hüceyrə membranı', 'sitoplazma', 'nüvə', 'mitoxondri', 'ribosom', 'xloroplast'],
                    'terms_ru' => ['клеточная мембрана', 'цитоплазма', 'ядро', 'митохондрия', 'рибосома', 'хлоропласт']],
                ['name' => 'Botanika', 'terms' => ['fotosintez', 'kök', 'gövdə', 'yarpaq', 'çiçək', 'toxum'],
                    'terms_ru' => ['фотосинтез', 'корень', 'стебель', 'лист', 'цветок', 'семя']],
                ['name' => 'Zoologiya', 'terms' => ['onurğalılar', 'onurğasızlar', 'qan dövranı', 'tənəffüs sistemi', 'sinir sistemi', 'həzm sistemi'],
                    'terms_ru' => ['позвоночные', 'беспозвоночные', 'кровообращение', 'дыхательная система', 'нервная система', 'пищеварительная система']],
                ['name' => 'Genetika və ekologiya', 'terms' => ['gen', 'xromosom', 'DNT', 'irsiyyət', 'populyasiya', 'ekosistem'],
                    'terms_ru' => ['ген', 'хромосома', 'ДНК', 'наследственность', 'популяция', 'экосистема']],
            ],
            'informatika' => [
                ['name' => 'İnformasiya və kodlaşdırma', 'terms' => ['bit', 'bayt', 'ikilik say sistemi', 'ASCII', 'Unicode', 'informasiyanın ölçülməsi'],
                    'terms_ru' => ['бит', 'байт', 'двоичная система', 'ASCII', 'Unicode', 'измерение информации']],
                ['name' => 'Kompüterin quruluşu', 'terms' => ['prosessor', 'operativ yaddaş', 'sərt disk', 'giriş qurğuları', 'əməliyyat sistemi', 'fayl sistemi'],
                    'terms_ru' => ['процессор', 'оперативная память', 'жёсткий диск', 'устройства ввода', 'операционная система', 'файловая система']],
                ['name' => 'Alqoritmlər', 'terms' => ['xətti alqoritm', 'budaqlanan alqoritm', 'dövr', 'blok-sxem', 'dəyişən', 'massiv'],
                    'terms_ru' => ['линейный алгоритм', 'ветвящийся алгоритм', 'цикл', 'блок-схема', 'переменная', 'массив']],
                ['name' => 'Şəbəkə və təhlükəsizlik', 'terms' => ['IP ünvan', 'protokol', 'brauzer', 'şifrələmə', 'virus', 'ehtiyat nüsxə'],
                    'terms_ru' => ['IP-адрес', 'протокол', 'браузер', 'шифрование', 'вирус', 'резервная копия']],
            ],
            'mentiq' => [
                ['name' => 'Anlayış və mühakimə', 'terms' => ['anlayış', 'anlayışın həcmi', 'anlayışın məzmunu', 'mühakimə', 'təsdiq', 'inkar']],
                ['name' => 'Silloqizm', 'terms' => ['müqəddimə', 'nəticə', 'orta termin', 'deduksiya', 'induksiya', 'analogiya']],
                ['name' => 'Ədədi məntiq', 'terms' => ['ardıcıllıq', 'qanunauyğunluq', 'nisbət məsələsi', 'faiz məsələsi', 'cədvəl analizi', 'qrafik analizi']],
                ['name' => 'Məntiqi məsələlər', 'terms' => ['cədvəl üsulu', 'ziddiyyət üsulu', 'çeşidləmə', 'doğru/yalan məsələləri', 'çəki məsələləri', 'yaş məsələləri']],
            ],
            'qanunvericilik' => [
                ['name' => 'Konstitusiya', 'terms' => ['Konstitusiya', 'hakimiyyət bölgüsü', 'əsas hüquq və azadlıqlar', 'vətəndaşlıq', 'referendum', 'Milli Məclis']],
                ['name' => 'Dövlət qulluğu', 'terms' => ['dövlət qulluqçusu', 'vəzifə dərəcəsi', 'müsabiqə', 'attestasiya', 'intizam tənbehi', 'etik davranış qaydaları']],
                ['name' => 'İnzibati hüquq', 'terms' => ['inzibati xəta', 'inzibati tənbeh', 'şikayət', 'inzibati icraat', 'inzibati akt', 'inzibati məsuliyyət']],
                ['name' => 'Əmək və mülki hüquq', 'terms' => ['əmək müqaviləsi', 'iş vaxtı', 'məzuniyyət', 'əqd', 'mülkiyyət hüququ', 'mülki müqavilə']],
            ],
            'ingilis-dili' => [
                ['name' => 'Tenses', 'terms' => ['Present Simple', 'Present Continuous', 'Past Simple', 'Present Perfect', 'Future Simple', 'Past Continuous'],
                    'terms_ru' => ['Present Simple', 'Present Continuous', 'Past Simple', 'Present Perfect', 'Future Simple', 'Past Continuous']],
                ['name' => 'Grammar structures', 'terms' => ['Passive Voice', 'Reported Speech', 'Conditionals', 'Modal Verbs', 'Gerund', 'Infinitive'],
                    'terms_ru' => ['Passive Voice', 'Reported Speech', 'Conditionals', 'Modal Verbs', 'Gerund', 'Infinitive']],
                ['name' => 'Vocabulary', 'terms' => ['synonyms', 'antonyms', 'phrasal verbs', 'collocations', 'prefixes', 'suffixes'],
                    'terms_ru' => ['synonyms', 'antonyms', 'phrasal verbs', 'collocations', 'prefixes', 'suffixes']],
                ['name' => 'Reading and writing', 'terms' => ['main idea', 'detail question', 'inference', 'linking words', 'paragraph structure', 'summary'],
                    'terms_ru' => ['main idea', 'detail question', 'inference', 'linking words', 'paragraph structure', 'summary']],
            ],
            'rus-dili' => [
                ['name' => 'Фонетика', 'terms' => ['гласные звуки', 'согласные звуки', 'ударение', 'слог', 'звук и буква', 'орфоэпия'],
                    'terms_ru' => ['гласные звуки', 'согласные звуки', 'ударение', 'слог', 'звук и буква', 'орфоэпия']],
                ['name' => 'Лексика', 'terms' => ['синонимы', 'антонимы', 'омонимы', 'фразеологизмы', 'заимствования', 'многозначность'],
                    'terms_ru' => ['синонимы', 'антонимы', 'омонимы', 'фразеологизмы', 'заимствования', 'многозначность']],
                ['name' => 'Морфология', 'terms' => ['существительное', 'прилагательное', 'глагол', 'наречие', 'местоимение', 'причастие'],
                    'terms_ru' => ['существительное', 'прилагательное', 'глагол', 'наречие', 'местоимение', 'причастие']],
                ['name' => 'Синтаксис', 'terms' => ['подлежащее', 'сказуемое', 'дополнение', 'определение', 'обстоятельство', 'сложное предложение'],
                    'terms_ru' => ['подлежащее', 'сказуемое', 'дополнение', 'определение', 'обстоятельство', 'сложное предложение']],
            ],
            'fransiz-dili' => [
                ['name' => 'Articles et genre', 'terms' => ['article défini', 'article indéfini', 'article partitif', 'genre', 'nombre', 'élision']],
                ['name' => 'Verbes', 'terms' => ['présent', 'passé composé', 'imparfait', 'futur simple', 'verbes pronominaux', 'impératif']],
                ['name' => 'Vocabulaire', 'terms' => ['la famille', "l'école", 'la ville', 'le voyage', 'la nourriture', 'le temps']],
                ['name' => 'Compréhension écrite', 'terms' => ['idée principale', 'détail', 'connecteurs', 'texte court', 'dialogue', 'résumé']],
            ],
            'alman-dili' => [
                ['name' => 'Artikel und Kasus', 'terms' => ['Nominativ', 'Akkusativ', 'Dativ', 'Genitiv', 'bestimmter Artikel', 'unbestimmter Artikel']],
                ['name' => 'Verben', 'terms' => ['Präsens', 'Perfekt', 'Präteritum', 'Modalverben', 'trennbare Verben', 'Imperativ']],
                ['name' => 'Wortschatz', 'terms' => ['die Familie', 'die Schule', 'die Stadt', 'die Reise', 'das Essen', 'die Zeit']],
                ['name' => 'Leseverstehen', 'terms' => ['Hauptidee', 'Detailfrage', 'Konnektoren', 'kurzer Text', 'Dialog', 'Zusammenfassung']],
            ],
            'yol-hereketi-qaydalari' => [
                ['name' => 'Yol nişanları', 'terms' => ['xəbərdarlıq nişanı', 'qadağan nişanı', 'məcburi hərəkət nişanı', 'məlumatverici nişan', 'xidmət nişanı', 'əlavə məlumat lövhəsi']],
                ['name' => 'Hərəkət qaydaları', 'terms' => ['yol vermə', 'ötmə', 'dönmə', 'dayanma və durma', 'sürət həddi', 'nizamlayıcının işarələri']],
                ['name' => 'Nəqliyyat vasitəsi və sənədlər', 'terms' => ['texniki baxış', 'sürücülük vəsiqəsi', 'icbari sığorta', 'qoşqu', 'yük daşınması', 'sərnişin daşınması']],
                ['name' => 'Təhlükəsizlik və ilk yardım', 'terms' => ['təhlükəsizlik kəməri', 'qəza zamanı davranış', 'ilk yardım', 'sərxoş halda idarəetmə', 'uşaq oturacağı', 'məhdud görmə şəraiti']],
            ],
            'kurikulum-ve-metodika' => [
                ['name' => 'Kurikulumun əsasları', 'terms' => ['məzmun standartı', 'alt-standart', 'fənn kurikulumu', 'inteqrasiya', 'təlim nəticəsi', 'məzmun xətti']],
                ['name' => 'Təlim strategiyaları', 'terms' => ['interaktiv təlim', 'iş formaları', 'beyin həmləsi', 'müzakirə', 'rollu oyun', 'layihə metodu']],
                ['name' => 'Qiymətləndirmə', 'terms' => ['diaqnostik qiymətləndirmə', 'formativ qiymətləndirmə', 'summativ qiymətləndirmə', 'qiymətləndirmə meyarı', 'rubrik', 'portfolio']],
                ['name' => 'Dərsin planlaşdırılması', 'terms' => ['illik plan', 'gündəlik plan', 'motivasiya', 'tədqiqat sualı', 'refleksiya', 'ev tapşırığı']],
            ],
        ];
    }
}
