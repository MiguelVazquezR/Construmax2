<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const notifications = ref([]);
const unreadCount = ref(0);
const showDropdown = ref(false);
const loading = ref(false);

let pollingInterval = null;

const fetchNotifications = async () => {
    try {
        const response = await axios.get(route('notifications.fetch'));
        notifications.value = response.data.notifications || [];
        unreadCount.value = response.data.unread_count || 0;
    } catch (e) {
        // Silently fail
    }
};

const markAsRead = async (id) => {
    try {
        await axios.post(route('notifications.mark-read', id));
        await fetchNotifications();
    } catch (e) {
        // Silently fail
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post(route('notifications.mark-all-read'));
        await fetchNotifications();
    } catch (e) {
        // Silently fail
    }
};

const deleteNotification = async (id, event) => {
    event.stopPropagation();
    try {
        await axios.delete(route('notifications.delete', id));
        await fetchNotifications();
    } catch (e) {
        // Silently fail
    }
};

const deleteAllNotifications = async () => {
    try {
        await axios.delete(route('notifications.delete-all'));
        await fetchNotifications();
    } catch (e) {
        // Silently fail
    }
};

const handleNotificationClick = (notification) => {
    markAsRead(notification.id);
    const data = notification.data || {};
    if (data.route && data.route_params !== undefined) {
        router.visit(route(data.route, data.route_params));
    } else if (data.ticket_id) {
        router.visit(route('tickets.show', data.ticket_id));
    } else if (data.budget_id) {
        router.visit(route('costs.show', data.budget_id));
    }
    showDropdown.value = false;
};

const formatTime = (date) => {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    const diff = now - d;
    
    if (diff < 60000) return 'Ahora';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm atrás';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h atrás';
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
};

onMounted(() => {
    fetchNotifications();
    pollingInterval = setInterval(fetchNotifications, 30000); // Poll every 30s
});

onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
});
</script>

<template>
    <div class="relative">
        <el-tooltip content="Notificaciones" placement="bottom">
            <button
                @click="showDropdown = !showDropdown"
                class="relative flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 dark:hover:bg-[#27272a] transition-colors"
            >
                <el-badge :value="unreadCount" :hidden="unreadCount === 0" :max="99" type="danger">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 text-gray-500 dark:text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </el-badge>
            </button>
        </el-tooltip>

        <!-- Dropdown -->
        <transition name="el-fade-in">
            <div
                v-if="showDropdown"
                class="absolute right-0 mt-2 w-80 bg-white dark:bg-[#1e1e20] rounded-lg shadow-xl border border-gray-100 dark:border-[#2b2b2e] z-50 max-h-96 flex flex-col"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-100 dark:border-[#2b2b2e] flex justify-between items-center">
                    <span class="text-sm font-bold text-gray-800 dark:text-white">Notificaciones</span>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="unreadCount > 0"
                            @click="markAllAsRead"
                            class="text-xs text-[#f26c17] hover:text-[#d95d0f] font-medium"
                        >
                            Leer todo
                        </button>
                        <el-popconfirm
                            v-if="notifications.length > 0"
                            title="¿Eliminar todas las notificaciones?"
                            confirm-button-text="Eliminar"
                            cancel-button-text="Cancelar"
                            @confirm="deleteAllNotifications"
                        >
                            <template #reference>
                                <button class="text-xs text-gray-400 hover:text-red-500 transition-colors">
                                    Eliminar todo
                                </button>
                            </template>
                        </el-popconfirm>
                    </div>
                </div>

                <!-- List -->
                <div class="overflow-y-auto flex-1">
                    <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                        Sin notificaciones
                    </div>
                    <div
                        v-for="notification in notifications"
                        :key="notification.id"
                        @click="handleNotificationClick(notification)"
                        class="px-4 py-3 border-b border-gray-50 dark:border-[#252529] cursor-pointer hover:bg-gray-50 dark:hover:bg-[#252529] transition-colors group"
                        :class="{ 'bg-orange-50 dark:bg-orange-900/10': !notification.read_at }"
                    >
                        <div class="flex items-start gap-2">
                            <!-- Unread indicator -->
                            <div v-if="!notification.read_at" class="mt-1.5 w-2 h-2 bg-[#f26c17] rounded-full shrink-0"></div>
                            <div v-else class="mt-1.5 w-2 h-2 shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">
                                    {{ notification.data?.message || 'New notification' }}
                                </p>
                                <p class="text-[10px] text-gray-400 mt-1">{{ formatTime(notification.created_at) }}</p>
                            </div>
                            <!-- Delete individual -->
                            <button
                                @click="deleteNotification(notification.id, $event)"
                                class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-300 hover:!text-red-500 shrink-0 mt-0.5"
                                title="Eliminar"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div v-if="can('config.notifications')" class="px-4 py-2 border-t border-gray-100 dark:border-[#2b2b2e] text-center">
                    <a
                        :href="route('config.notifications.index')"
                        class="text-xs text-gray-400 hover:text-[#f26c17] transition-colors"
                    >
                        Configuración de notificaciones
                    </a>
                </div>
            </div>
        </transition>

        <!-- Backdrop -->
        <div
            v-if="showDropdown"
            class="fixed inset-0 z-40"
            @click="showDropdown = false"
        ></div>
    </div>
</template>
