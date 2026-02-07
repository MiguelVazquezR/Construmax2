<script setup>
import { ref, onMounted } from 'vue'; // Importamos onMounted y ref
import { router, Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import axios from 'axios'; // Importamos axios para la consulta ligera

defineProps({
    title: String,
});

const emit = defineEmits(['toggleSidebar']);

// Estado para notificaciones de calendario
const calendarStatus = ref({
    invitations: 0,
    today_events: 0,
    total: 0
});

// Consultar estado al cargar la barra
onMounted(async () => {
    try {
        // Hacemos una petición ligera para no cargar el servidor en cada navegación completa
        const response = await axios.get(route('calendar.overview'));
        calendarStatus.value = response.data;
    } catch (error) {
        console.error('Error cargando estado de calendario', error);
    }
});

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <nav class="bg-white dark:bg-[#1e1e20] border-b border-gray-100 dark:border-[#2b2b2e] h-16 px-4 flex justify-between items-center transition-colors duration-300">
        
        <!-- Left: Toggle & Title -->
        <div class="flex items-center gap-4">
            <button 
                @click="$emit('toggleSidebar')"
                class="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-[#27272a] focus:outline-none transition"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
            
            <h1 v-if="title" class="text-xl font-semibold text-gray-800 dark:text-white hidden sm:block">
                {{ title }}
            </h1>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-2">
            
            <!-- Calendario Icon con Badge -->
            <el-tooltip 
                effect="dark" 
                :content="`Hoy: ${calendarStatus.today_events} eventos | Invitaciones: ${calendarStatus.invitations}`" 
                placement="bottom"
            >
                <Link :href="route('calendar.index')" class="p-2 mr-1 text-gray-400 hover:text-primary transition-colors relative flex items-center">
                    <el-badge 
                        :value="calendarStatus.total" 
                        :hidden="calendarStatus.total === 0" 
                        :max="99" 
                        class="!flex items-center"
                        type="danger"
                    >
                        <el-icon :size="22"><Calendar /></el-icon>
                    </el-badge>
                </Link>
            </el-tooltip>

            <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 mx-2"></div>

            <!-- Teams Dropdown -->
            <div class="relative" v-if="$page.props.jetstream.hasTeamFeatures">
                <Dropdown align="right" width="60">
                    <template #trigger>
                        <span class="inline-flex rounded-md">
                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-[#1e1e20] hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                {{ $page.props.auth.user.current_team.name }}
                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                </svg>
                            </button>
                        </span>
                    </template>

                    <template #content>
                        <div class="w-60">
                            <div class="block px-4 py-2 text-xs text-gray-400">Manage Team</div>
                            <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">Team Settings</DropdownLink>
                            <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">Create New Team</DropdownLink>
                            <template v-if="$page.props.auth.user.all_teams.length > 1">
                                <div class="border-t border-gray-200 dark:border-gray-700" />
                                <div class="block px-4 py-2 text-xs text-gray-400">Switch Teams</div>
                                <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                    <form @submit.prevent="switchToTeam(team)">
                                        <DropdownLink as="button">
                                            <div class="flex items-center">
                                                <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <div>{{ team.name }}</div>
                                            </div>
                                        </DropdownLink>
                                    </form>
                                </template>
                            </template>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!-- Settings Dropdown -->
            <div class="ms-3 relative">
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                            <img class="size-9 rounded-full object-cover shadow-sm" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                        </button>
                        <span v-else class="inline-flex rounded-md">
                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-[#1e1e20] hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                {{ $page.props.auth.user.name }}
                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        </span>
                    </template>

                    <template #content>
                        <div class="block px-4 py-2 text-xs text-gray-400">Manage Account</div>
                        <DropdownLink :href="route('profile.show')">Profile</DropdownLink>
                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">API Tokens</DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-700" />
                        <form @submit.prevent="logout">
                            <DropdownLink as="button">Log Out</DropdownLink>
                        </form>
                    </template>
                </Dropdown>
            </div>
        </div>
    </nav>
</template>