// Ana səhifə, footer və kateqoriya səhifələri eyni siyahıdan istifadə edir.
// Mətnlər: lang/{az,ru}/categories.php ($t(`categories.${slug}.name`) və s.).
// Hərflər yalnız ana səhifədəki cavab kartında görünür.
export const categories = [
    { slug: 'abituriyent', letter: 'A', group: 'education' },
    { slug: 'mekteb', letter: 'B', group: 'education' },
    { slug: 'magistratura', letter: 'C', group: 'education' },
    { slug: 'dovlet-qullugu', letter: 'D', group: 'career' },
    { slug: 'miq', letter: 'E', group: 'career' },
    { slug: 'suruculuk-imtahani', letter: 'F', group: 'driving' },
];

// Route adı: category.abituriyent (ru: ru.category.abituriyent, useLocale().lroute ilə)
export const categoryRoute = (category) => `category.${category.slug}`;

export const findCategory = (slug) => categories.find((c) => c.slug === slug);
