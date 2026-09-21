<?php

namespace App\Services\QuestionImport;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Faylın ilk sətri başlıqdır (sual, tip, variant_a ... duzgun, izah).
 * Yalnız oxumaq üçündür — validasiya və yazma QuestionImportService-dədir.
 */
class QuestionImportSheet implements ToArray, WithHeadingRow
{
    public function array(array $array): void
    {
        // Excel::toArray() sətirləri onsuz da qaytarır; burada iş görülmür.
    }
}
