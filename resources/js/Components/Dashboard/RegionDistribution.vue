<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Loading } from '@element-plus/icons-vue';
import VChart from 'vue-echarts';
import { use } from 'echarts/core';
import { MapChart } from 'echarts/charts';
import { TooltipComponent, VisualMapComponent, TitleComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import * as echarts from 'echarts/core';

use([CanvasRenderer, MapChart, TooltipComponent, VisualMapComponent, TitleComponent]);

const props = defineProps({
    regions: Array,
});

const loading = ref(true);
const error = ref(null);
const mapsReady = ref(false);

const mexicoOption = ref({});

// Filtrar solo datos de México
const mexicoData = computed(() =>
    props.regions
        .filter(r => {
            const c = (r.country || '').toLowerCase();
            return c === 'méxico' || c === 'mexico' || c === 'mx';
        })
        .map(r => ({ name: r.region, value: r.total }))
);

const globalMax = computed(() => {
    const all = [...mexicoData.value];
    return all.length ? Math.max(...all.map(d => d.value), 1) : 1;
});

function buildOption(title, mapName, data) {
    return {
        title: {
            text: title,
            left: 'center',
            textStyle: { fontSize: 13, fontWeight: 'bold', color: '#374151' },
        },
        tooltip: {
            trigger: 'item',
            formatter: (params) => {
                const val = params.value;
                if (val != null && !Number.isNaN(val) && val > 0) {
                    return `<b>${params.name}</b><br/>Tickets: ${val}`;
                }
                return `<b>${params.name}</b><br/>Sin datos`;
            },
        },
        visualMap: {
            min: 0,
            max: globalMax.value,
            left: 'left',
            bottom: 'bottom',
            calculable: false,
            show: true,
            inRange: { color: ['#e0f2fe', '#0ea5e9', '#0369a1'] },
            text: ['Alto', 'Bajo'],
            textStyle: { color: '#6b7280', fontSize: 10 },
        },
        series: [{
            type: 'map',
            map: mapName,
            roam: true,
            zoom: 1.4,
            center: [-102, 23],
            label: {
                show: true,
                fontSize: 9,
                color: '#4b5563',
            },
            emphasis: {
                label: { show: true, fontSize: 12, fontWeight: 'bold' },
                itemStyle: { areaColor: '#fbbf24' },
            },
            itemStyle: {
                borderColor: '#ffffff',
                borderWidth: 1,
            },
            data,
        }],
    };
}

// ── Build chart options from current regions data ──
function refreshCharts() {
    if (!mapsReady.value) return;
    mexicoOption.value = buildOption('México', 'Mexico', mexicoData.value);
}

// ── React to prop changes (filters update) ──
watch(() => props.regions, () => {
    refreshCharts();
}, { deep: true });

onMounted(async () => {
    const loadMap = async (name, url, specialAreas = {}) => {
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();

            // Validar estructura GeoJSON mínima
            if (!json || !json.features || !Array.isArray(json.features)) {
                throw new Error('El JSON no tiene estructura FeatureCollection válida');
            }

            echarts.registerMap(name, json, specialAreas);
            console.log(`✅ Mapa ${name} cargado: ${json.features.length} regiones`);
            return true;
        } catch (e) {
            console.error(`❌ Error cargando mapa ${name} desde ${url}:`, e.message);
            return false;
        }
    };

    const mexOk = await loadMap('Mexico', 'https://raw.githubusercontent.com/angelnmara/geojson/master/mexicoHigh.json');

    if (!mexOk) {
        error.value = 'No se pudo cargar el mapa';
    }

    mapsReady.value = true;
    refreshCharts();
    loading.value = false;
});
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] p-6 rounded-xl shadow-sm border border-gray-100 dark:border-[#2b2b2e]">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Distribución geográfica</h3>
        <h5 class="text-sm text-gray-600 mt-0">Muestra los tickets activos durante el periodo seleccionado</h5>
        <h6 class="text-xs text-gray-400 mt-0">Hacer scroll para hacer más grande o pequeño el mapa. Mantén presionado el clic para mover el mapa.</h6>
        <div v-if="loading" class="flex items-center justify-center h-80">
            <el-icon class="is-loading text-gray-400" :size="32"><Loading /></el-icon>
        </div>

        <div v-else-if="error" class="flex items-center justify-center h-80 text-gray-400 text-sm">
            ⚠️ {{ error }}. Mostrando indicador alternativo.
        </div>

        <div v-else>
            <VChart v-if="mexicoOption.series" class="h-[520px] w-full" :option="mexicoOption" autoresize />
            <div v-else class="h-[520px] flex items-center justify-center text-gray-400 text-sm">
                Sin datos para México
            </div>
        </div>
    </div>
</template>

