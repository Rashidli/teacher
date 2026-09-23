<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useFeatures } from '@/Composables/useFeatures';
import { useLocale } from '@/Composables/useLocale';

const showingNavigationDropdown = ref(false);

const page = usePage();
const user = computed(() => page.props.auth.user);
// Tək guard: menyu hesabın rollarına görə qurulur (bir hesabın bir neçə rolu ola bilər)
const roles = computed(() => user.value?.roles || []);

const { teachersEnabled } = useFeatures();
// Kataloq linki interfeys dilinə uyğun olsun (/imtahanlar və ya /ru/imtahanlar)
const { lroute } = useLocale();

const isAdmin = computed(() => roles.value.includes('admin'));
// Müəllim menyusu yalnız müəllim modulu aktiv olanda
const isTeacher = computed(() => teachersEnabled.value && roles.value.includes('teacher'));
const isStudent = computed(() => roles.value.includes('student'));

const flash = computed(() => page.props.flash);

// İctimai başlıqdakı ilə eyni: uzun ad sığmayanda inisiallar qalır
const initials = computed(() => {
    const parts = [user.value?.first_name, user.value?.last_name].filter(Boolean);

    if (parts.length === 0) {
        return (user.value?.email ?? '?').slice(0, 2).toUpperCase();
    }

    return parts.map((part) => part.trim().charAt(0).toUpperCase()).join('');
});

// Çıxış hər rol üçün eyni sessiyanı bağlayır; admin paneli öz səhifəsinə qaytarır
const logoutRoute = computed(() => (isAdmin.value ? 'admin.logout' : 'logout'));

// Ən yüksək səlahiyyətli panel (Panel::homeUrl ilə eyni ardıcıllıq)
const dashboardRoute = computed(() => {
    if (isAdmin.value) return 'admin.dashboard';
    if (isTeacher.value) return 'teacher.dashboard';
    return 'student.dashboard';
});
</script>

<template>
    <div class="panel">
        <div class="panel-shell">
            <nav class="panel-nav">
                <!-- Primary Navigation Menu -->
                <div class="wrap">
                    <div class="flex justify-between panel-nav-inner">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <!-- Loqo ANA SƏHİFƏYƏ aparır: paneldən sayta qayıdış yolu budur -->
                                <Link :href="lroute('home')" class="brand">
                                    <span class="brand-mark" aria-hidden="true"></span>
                                    <span class="brand-name">{{ $t('site.brand') }}</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <!--
                                    Kataloq: şagirdin YENİ imtahan tapmaq yolu. Panel daxilində
                                    ictimai səhifələrə keçid olmadığı üçün görünən yerdə durur.
                                -->
                                <NavLink :href="lroute('exams.catalog')" :active="false">
                                    İmtahanlar
                                </NavLink>

                                <!-- Admin Navigation -->
                                <template v-if="isAdmin">
                                    <NavLink
                                        :href="route('admin.dashboard')"
                                        :active="route().current('admin.dashboard')"
                                    >
                                        Panel
                                    </NavLink>
                                    <NavLink
                                        v-if="teachersEnabled"
                                        :href="route('admin.teachers.index')"
                                        :active="route().current('admin.teachers.*')"
                                    >
                                        Müəllimlər
                                    </NavLink>
                                    <NavLink
                                        :href="route('admin.exams.index')"
                                        :active="route().current('admin.exams.*')"
                                    >
                                        İmtahanlar
                                    </NavLink>
                                    <NavLink
                                        :href="route('admin.subjects.index')"
                                        :active="route().current('admin.subjects.*')"
                                    >
                                        Fənlər
                                    </NavLink>
                                    <NavLink
                                        :href="route('admin.tags.index')"
                                        :active="route().current('admin.tags.*')"
                                    >
                                        Etiketlər
                                    </NavLink>
                                    <NavLink
                                        :href="route('admin.groups.index')"
                                        :active="route().current('admin.groups.*')"
                                    >
                                        Qruplar
                                    </NavLink>
                                </template>

                                <!-- Teacher Navigation -->
                                <template v-if="isTeacher">
                                    <NavLink
                                        :href="route('teacher.dashboard')"
                                        :active="route().current('teacher.dashboard')"
                                    >
                                        Panel
                                    </NavLink>
                                    <NavLink
                                        :href="route('teacher.exams.index')"
                                        :active="route().current('teacher.exams.*')"
                                    >
                                        İmtahanlarım
                                    </NavLink>
                                </template>

                                <!-- Student Navigation -->
                                <template v-if="isStudent">
                                    <NavLink
                                        :href="route('student.dashboard')"
                                        :active="route().current('student.dashboard')"
                                    >
                                        Panel
                                    </NavLink>
                                    <NavLink
                                        :href="route('student.exams.index')"
                                        :active="route().current('student.exams.*')"
                                    >
                                        Mənim imtahanlarım
                                    </NavLink>
                                    <NavLink
                                        :href="route('student.results.index')"
                                        :active="route().current('student.results.*')"
                                    >
                                        Nəticələrim
                                    </NavLink>
                                    <NavLink
                                        :href="route('student.statistics')"
                                        :active="route().current('student.statistics')"
                                    >
                                        Statistika
                                    </NavLink>
                                </template>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button type="button" class="account-toggle">
                                            <span class="account-initials" aria-hidden="true">{{ initials }}</span>
                                            <span class="account-name">{{ user.full_name || user.name }}</span>
                                        </button>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route(logoutRoute)"
                                            method="post"
                                            as="button"
                                        >
                                            Çıxış
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <!-- Kataloq: paneldən sayta qayıdış (mobil) -->
                        <ResponsiveNavLink :href="lroute('exams.catalog')" :active="false">
                            İmtahanlar
                        </ResponsiveNavLink>

                        <!-- Admin Navigation -->
                        <template v-if="isAdmin">
                            <ResponsiveNavLink
                                :href="route('admin.dashboard')"
                                :active="route().current('admin.dashboard')"
                            >
                                Panel
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="teachersEnabled"
                                :href="route('admin.teachers.index')"
                                :active="route().current('admin.teachers.*')"
                            >
                                Müəllimlər
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('admin.exams.index')"
                                :active="route().current('admin.exams.*')"
                            >
                                İmtahanlar
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('admin.subjects.index')"
                                :active="route().current('admin.subjects.*')"
                            >
                                Fənlər
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('admin.tags.index')"
                                :active="route().current('admin.tags.*')"
                            >
                                Etiketlər
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('admin.groups.index')"
                                :active="route().current('admin.groups.*')"
                            >
                                Qruplar
                            </ResponsiveNavLink>
                        </template>

                        <!-- Teacher Navigation -->
                        <template v-if="isTeacher">
                            <ResponsiveNavLink
                                :href="route('teacher.dashboard')"
                                :active="route().current('teacher.dashboard')"
                            >
                                Panel
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('teacher.exams.index')"
                                :active="route().current('teacher.exams.*')"
                            >
                                İmtahanlarım
                            </ResponsiveNavLink>
                        </template>

                        <!-- Student Navigation -->
                        <template v-if="isStudent">
                            <ResponsiveNavLink
                                :href="route('student.dashboard')"
                                :active="route().current('student.dashboard')"
                            >
                                Panel
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('student.exams.index')"
                                :active="route().current('student.exams.*')"
                            >
                                Mənim imtahanlarım
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('student.results.index')"
                                :active="route().current('student.results.*')"
                            >
                                Nəticələrim
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('student.statistics')"
                                :active="route().current('student.statistics')"
                            >
                                Statistika
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ user.full_name || user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route(logoutRoute)" method="post" as="button">
                                Çıxış
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Flash Messages -->
            <div v-if="flash?.success || flash?.error" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4">
                <div
                    v-if="flash.success"
                    class="flash flash--ok"
                >
                    <p>{{ flash.success }}</p>
                </div>
                <div
                    v-if="flash.error"
                    class="flash flash--error"
                >
                    <p>{{ flash.error }}</p>
                </div>
            </div>

            <!-- Page Heading -->
            <header class="page-head" v-if="$slots.header">
                <div class="wrap page-head-inner">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
/*
 * Panel ictimai tərəflə EYNİ vizual dildədir: eyni tokenlər (`:root`), eyni başlıq
 * hündürlüyü (`--header-height`), eyni kağız fon və şriftlər. Tailwind-in boz-mavi
 * defoltları yalnız daxili şəbəkə/boşluq siniflərində qalır.
 */
.panel-shell {
    min-height: 100vh;
    background: var(--paper-sunk);
}

.panel-nav {
    position: sticky;
    top: 0;
    z-index: 40;
    background: var(--paper);
    border-bottom: 1px solid var(--ink-red-line);
}

/* İctimai başlıqla eyni hündürlük */
.panel-nav-inner {
    min-height: var(--header-height);
    align-items: center;
}

/* --------------------------------------------------------------- brend */

.brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    color: var(--graphite);
    text-decoration: none;
}

/* Karandaşla doldurulmuş dairə — ictimai başlıqdakı nişanın eynisi */
.brand-mark {
    width: 20px;
    height: 20px;
    flex: none;
    border-radius: 50%;
    border: 1.5px solid var(--ink-red);
    background: radial-gradient(circle at 40% 38%, #3a3f46 0 45%, var(--graphite) 70%);
    box-shadow: inset 0 0 0 2px var(--paper);
}

.brand-name {
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 1.0625rem;
    letter-spacing: -0.01em;
    line-height: 1.2;
}

/* -------------------------------------------------------- hesab menyusu */

.account-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 6px 10px;
    border: 0;
    border-radius: 999px;
    background: none;
    font: inherit;
    cursor: pointer;
}

.account-toggle:hover {
    background: var(--paper-sunk);
}

.account-initials {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    flex: none;
    border-radius: 50%;
    background: var(--graphite);
    color: var(--paper);
    font-size: 0.8125rem;
    font-weight: 700;
}

.account-name {
    display: none;
    max-width: 14ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--graphite);
}

/* ----------------------------------------------------------- səhifə başı */

.page-head {
    background: var(--paper);
    border-bottom: 1px solid var(--ink-red-line);
}

.page-head-inner {
    padding-block: 20px;
}

/* --------------------------------------------------------------- flash */

.flash {
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 0.9375rem;
}

.flash p {
    margin: 0;
}

.flash--ok {
    border: 1px solid rgba(31, 122, 77, 0.35);
    background: #E6EFEA;
    color: var(--correct);
}

.flash--error {
    border: 1px solid var(--ink-red-line);
    background: #F7E8E9;
    color: var(--ink-red);
}

@media (min-width: 720px) {
    .account-name { display: inline; }
}

/*
 * ÇAP: panelin öz elementləri (naviqasiya, səhifə başlığı, flash) kağıza düşməsin —
 * çap olunan yalnız səhifənin məzmunu olur. Hər səhifə öz çap qaydalarını özü əlavə edir
 * (məs. nəticə səhifəsindəki "imtahan vərəqi").
 */
@media print {
    .panel-nav,
    .page-head,
    .flash {
        display: none !important;
    }

    .panel-shell {
        background: #fff;
        min-height: 0;
    }
}
</style>
