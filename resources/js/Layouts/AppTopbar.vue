<script setup>
import { ref, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';

defineProps({
    title: String,
});

const emit = defineEmits(['toggleSidebar']);

// --- LÓGICA DE CALENDARIO ---
const calendarStatus = ref({
    invitations: 0,
    today_events: 0,
    total: 0
});

onMounted(async () => {
    try {
        const response = await axios.get(route('calendar.overview'));
        calendarStatus.value = response.data;
    } catch (error) {
        console.error('Error cargando estado de calendario', error);
    }
});

// --- LÓGICA DE NAVEGACIÓN Y ACCIONES ---
const handleCommand = (command) => {
    if (!command) return;

    // Acciones de Usuario
    if (command === 'logout') {
        router.post(route('logout'));
    } else if (command === 'profile') {
        router.visit(route('profile.show'));
    } else if (command === 'api-tokens') {
        router.visit(route('api-tokens.index'));
    } 
    // Acciones de Equipo
    else if (typeof command === 'object' && command.type === 'team') {
        if (command.action === 'switch') {
            router.put(route('current-team.update'), {
                team_id: command.team.id,
            }, { preserveState: false });
        } else if (command.action === 'settings') {
            router.visit(route('teams.show', command.team));
        } else if (command.action === 'create') {
            router.visit(route('teams.create'));
        }
    }
};
</script>

<template>
    <nav class="bg-white dark:bg-[#1e1e20] border-b border-gray-100 dark:border-[#2b2b2e] h-11 px-4 flex justify-between items-center transition-colors duration-300 sticky top-0 z-40 shadow-sm">
        
        <!-- IZQUIERDA: Toggle Sidebar & Título -->
        <div class="flex items-center gap-3">
            <el-button 
                circle 
                text 
                @click="$emit('toggleSidebar')"
                class="!text-gray-500 dark:!text-gray-400 hover:!bg-gray-100 dark:hover:!bg-[#27272a] !p-2"
            >
                <el-icon :size="20"><Switch /></el-icon>
            </el-button>
            
            <h1 v-if="title" class="text-lg font-bold text-gray-800 dark:text-white hidden sm:block tracking-tight">
                {{ title }}
            </h1>
        </div>

        <!-- DERECHA: Acciones Globales -->
        <div class="flex items-center gap-2">
            
            <!-- Icono Calendario con Notificación -->
            <el-tooltip 
                effect="dark" 
                :content="`Hoy: ${calendarStatus.today_events} eventos | Invitaciones: ${calendarStatus.invitations}`" 
                placement="bottom"
            >
                <Link :href="route('calendar.index')" class="relative flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 dark:hover:bg-[#27272a] transition-colors group">
                    <el-badge 
                        :value="calendarStatus.total" 
                        :hidden="calendarStatus.total === 0" 
                        :max="99" 
                        class="!flex items-center"
                        type="danger"
                    >
                        <el-icon :size="20" class="text-gray-500 dark:text-gray-400 group-hover:text-primary transition-colors"><Calendar /></el-icon>
                    </el-badge>
                </Link>
            </el-tooltip>

            <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 mx-2"></div>

            <!-- DROPDOWN DE EQUIPOS (Si está habilitado) -->
            <el-dropdown 
                v-if="$page.props.jetstream.hasTeamFeatures" 
                trigger="click" 
                @command="handleCommand"
                class="mr-2"
            >
                <span class="el-dropdown-link flex items-center gap-2 cursor-pointer outline-none group">
                    <div class="hidden md:flex flex-col items-end leading-tight">
                        <span class="text-xs text-gray-400 uppercase font-bold tracking-wider">Equipo</span>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 group-hover:text-primary transition-colors">
                            {{ $page.props.auth.user.current_team.name }}
                        </span>
                    </div>
                    <el-icon class="text-gray-400 group-hover:text-primary transition-colors"><ArrowDown /></el-icon>
                </span>

                <template #dropdown>
                    <el-dropdown-menu class="min-w-[220px]">
                        <div class="px-4 py-2 text-xs text-gray-400 uppercase font-bold tracking-wider">Administrar Equipo</div>
                        
                        <el-dropdown-item :command="{ type: 'team', action: 'settings', team: $page.props.auth.user.current_team }">
                            <el-icon><Setting /></el-icon> Configuración
                        </el-dropdown-item>
                        
                        <el-dropdown-item v-if="$page.props.jetstream.canCreateTeams" :command="{ type: 'team', action: 'create' }">
                            <el-icon><Plus /></el-icon> Crear Nuevo Equipo
                        </el-dropdown-item>
                        
                        <div v-if="$page.props.auth.user.all_teams.length > 1">
                            <el-divider class="!my-1" />
                            <div class="px-4 py-2 text-xs text-gray-400 uppercase font-bold tracking-wider">Cambiar de Equipo</div>
                            
                            <el-dropdown-item 
                                v-for="team in $page.props.auth.user.all_teams" 
                                :key="team.id"
                                :command="{ type: 'team', action: 'switch', team: team }"
                            >
                                <div class="flex items-center justify-between w-full">
                                    <span>{{ team.name }}</span>
                                    <el-icon v-if="team.id == $page.props.auth.user.current_team_id" class="text-green-500"><Check /></el-icon>
                                </div>
                            </el-dropdown-item>
                        </div>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>

            <!-- DROPDOWN DE USUARIO -->
            <el-dropdown trigger="click" @command="handleCommand">
                <div class="flex items-center gap-2 cursor-pointer outline-none group">
                    
                    <!-- Foto de Perfil (Jetstream) -->
                    <div v-if="$page.props.jetstream.managesProfilePhotos" class="relative">
                        <img 
                            class="h-9 w-9 rounded-full object-cover border-2 border-transparent group-hover:border-primary transition-all shadow-sm" 
                            :src="$page.props.auth.user.profile_photo_url" 
                            :alt="$page.props.auth.user.name" 
                        />
                        <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full"></div>
                    </div>
                    
                    <!-- Nombre (Fallback) -->
                    <span v-else class="inline-flex items-center">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-primary transition">
                            {{ $page.props.auth.user.name }}
                        </span>
                        <el-icon class="ml-1 text-gray-400"><ArrowDown /></el-icon>
                    </span>
                </div>
                
                <template #dropdown>
                    <el-dropdown-menu class="min-w-[200px]">
                        <!-- Cabecera Usuario -->
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 mb-1 bg-gray-50 dark:bg-[#252529]">
                            <p class="text-sm font-bold text-gray-800 dark:text-white truncate">{{ $page.props.auth.user.name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $page.props.auth.user.email }}</p>
                        </div>
                        
                        <div class="px-4 py-1 text-xs text-gray-400 uppercase font-bold tracking-wider mt-1">Cuenta</div>
                        
                        <el-dropdown-item command="profile">
                            <el-icon><User /></el-icon> Mi Perfil
                        </el-dropdown-item>
                        
                        <el-dropdown-item v-if="$page.props.jetstream.hasApiFeatures" command="api-tokens">
                            <el-icon><Key /></el-icon> API Tokens
                        </el-dropdown-item>
                        
                        <el-divider class="!my-1" />
                        
                        <el-dropdown-item command="logout" class="text-red-500 hover:!bg-red-50 hover:!text-red-600">
                            <el-icon><SwitchButton /></el-icon> Cerrar Sesión
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>

        </div>
    </nav>
</template>

<style scoped>
/* Elimina el borde azul al hacer foco en elementos dropdown */
:deep(.el-dropdown-link:focus-visible),
:deep(.el-tooltip__trigger:focus-visible) {
    outline: none;
}
</style>