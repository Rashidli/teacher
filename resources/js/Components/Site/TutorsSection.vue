<script setup>
import { Link } from '@inertiajs/vue3';

// Ana səhifədəki repetitor bölməsi. Welcome.vue-da yalnız müəllim modulu aktiv olanda
// (features.teachers) göstərilir, çünki linkləri teacher.* route-larına aparır.
// Mətnlər: lang/{az,ru}/landing.php → tutors. Müqayisə cədvəlində 5 sətir var.
const rows = [0, 1, 2, 3, 4];
</script>

<template>
    <section id="repetitorlar" class="tutors" aria-labelledby="tutors-title">
        <div class="wrap tutors-grid">
            <div class="tutors-copy">
                <h2 id="tutors-title" class="section-title">{{ $t('landing.tutors.title') }}</h2>
                <p class="tutors-lead">{{ $t('landing.tutors.lead') }}</p>
                <div class="tutors-actions">
                    <Link :href="route('teacher.register')" class="button-paper">{{ $t('landing.tutors.register') }}</Link>
                    <Link :href="route('teacher.login')" class="tutors-login">{{ $t('landing.tutors.login') }}</Link>
                </div>
            </div>

            <!-- Telefonda sətirlər kart kimi şaquli düzülür; role-lar display dəyişəndə cədvəl semantikasını saxlayır -->
            <table class="compare" role="table">
                <caption class="visually-hidden">{{ $t('landing.tutors.compare_caption') }}</caption>
                <thead role="rowgroup">
                    <tr role="row">
                        <td role="cell"></td>
                        <th scope="col" role="columnheader">{{ $t('landing.tutors.paper') }}</th>
                        <th scope="col" role="columnheader">{{ $t('landing.tutors.online') }}</th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    <tr v-for="i in rows" :key="i" role="row">
                        <th scope="row" role="rowheader">{{ $t(`landing.tutors.rows.${i}.label`) }}</th>
                        <td :data-label="$t('landing.tutors.paper')" role="cell">{{ $t(`landing.tutors.rows.${i}.paper`) }}</td>
                        <td :data-label="$t('landing.tutors.online')" role="cell">{{ $t(`landing.tutors.rows.${i}.online`) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<style scoped>
/* Başlıq stili Welcome.vue-dakı .section-title ilə eynidir (scoped CSS alt komponentə keçmir) */
.section-title {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.625rem, 1.2rem + 1.9vw, 2.5rem);
    line-height: 1.15;
    letter-spacing: -0.015em;
    text-wrap: balance;
}

.tutors {
    padding-block: 56px;
    background: var(--pen-deep);
    color: #fff;
}

.tutors-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 36px;
}

.tutors .section-title {
    color: #fff;
    max-width: 18ch;
}

.tutors-lead {
    margin: 14px 0 0;
    max-width: 52ch;
    color: #D5DBF0;
    font-size: 1.0625rem;
}

.tutors-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 20px;
    margin-top: 24px;
}

.button-paper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding-inline: 20px;
    border-radius: 6px;
    background: var(--paper);
    color: var(--pen-deep);
    font-size: 1rem;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
}

.button-paper:hover {
    background: #fff;
    box-shadow: 0 0 0 2px #fff;
}

.tutors-login {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding-inline: 2px;
    color: #fff;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: underline;
    text-underline-offset: 4px;
}

.tutors :focus-visible {
    outline-color: #fff;
}

/* Müqayisə: telefonda hər sətir ayrıca blok, "A4 kağızda" / "Platformada" etiketləri ilə */
.compare {
    display: block;
    width: 100%;
    border-collapse: collapse;
    font-size: 1rem;
    line-height: 1.4;
}

.compare thead {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
}

.compare tbody,
.compare tr {
    display: block;
}

.compare tbody tr {
    padding-block: 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
}

.compare tbody tr:first-child {
    border-top: 1px solid rgba(255, 255, 255, 0.5);
}

.compare tbody th {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    text-align: left;
    color: #fff;
}

.compare tbody td {
    display: flex;
    flex-wrap: wrap;
    column-gap: 12px;
    padding-block: 3px;
}

.compare tbody td::before {
    content: attr(data-label);
    flex: 0 0 6.75rem;
    color: #AEB8DC;
    font-size: 0.875rem;
    line-height: 1.6;
}

.compare tbody td:nth-child(2) {
    color: #AEB8DC;
}

.compare tbody td:nth-child(3) {
    color: #fff;
}

/* ================= Böyük ekranlar (yalnız min-width) ================= */
@media (min-width: 720px) {
    /* Müqayisə yenidən cədvəl olur */
    .compare {
        display: table;
    }

    .compare thead {
        display: table-header-group;
        position: static;
        width: auto;
        height: auto;
        overflow: visible;
        clip: auto;
    }

    .compare tbody {
        display: table-row-group;
    }

    .compare tr {
        display: table-row;
    }

    .compare th,
    .compare td,
    .compare tbody th,
    .compare tbody td {
        display: table-cell;
        padding: 12px 8px;
        text-align: left;
        vertical-align: top;
        border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    }

    .compare tbody td::before {
        content: none;
    }

    .compare tbody tr:first-child {
        border-top: 0;
    }

    .compare thead th,
    .compare thead td {
        padding-top: 0;
        font-size: 0.875rem;
        font-weight: 600;
        color: #fff;
        border-bottom-color: rgba(255, 255, 255, 0.5);
    }

    .compare tbody th {
        padding-left: 0;
    }
}

@media (min-width: 960px) {
    .tutors {
        padding-block: 96px;
    }

    .tutors-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
        gap: 64px;
        align-items: center;
    }
}
</style>
