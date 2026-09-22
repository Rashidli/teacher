<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useFeatures } from '@/Composables/useFeatures';

const showingNavigationDropdown = ref(false);

const page = usePage();
const user = computed(() => page.props.auth.user);
// Tək guard: menyu hesabın rollarına görə qurulur (bir hesabın bir neçə rolu ola bilər)
const roles = computed(() => user.value?.roles || []);

const { teachersEnabled } = useFeatures();

const isAdmin = computed(() => roles.value.includes('admin'));
// Müəllim menyusu yalnız müəllim modulu aktiv olanda
const isTeacher = computed(() => teachersEnabled.value && roles.value.includes('teacher'));
const isStudent = computed(() => roles.value.includes('student'));

const flash = computed(() => page.props.flash);

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
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route(dashboardRoute)">
                                    <span class="text-xl font-bold text-indigo-600">İmtahan</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
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
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ user.full_name || user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
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
                    class="rounded-md bg-green-50 p-4 border border-green-200"
                >
                    <p class="text-sm text-green-700">{{ flash.success }}</p>
                </div>
                <div
                    v-if="flash.error"
                    class="rounded-md bg-red-50 p-4 border border-red-200"
                >
                    <p class="text-sm text-red-700">{{ flash.error }}</p>
                </div>
            </div>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
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
